<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Supplier;
use Barryvdh\DomPDF\Facade\Pdf; // Make sure to include the PDF Facade
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Menampilkan daftar pesanan.
     */
    public function index(Request $request)
    {
        $pagination = 10;
        $search = $request->search;

        // Mengambil data pesanan dengan relasi supplier dan orderDetails
        $orders = Order::with(['supplier', 'orderDetails.product'])
            ->when($search, function ($query) use ($search) {
                $query->where('order_date', 'LIKE', '%' . $search . '%')
                    ->orWhereHas('supplier', function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%' . $search . '%');
                    })
                    ->orWhereHas('orderDetails.product', function ($q) use ($search) {
                        $q->where('name', 'LIKE', '%' . $search . '%');
                    });
            })
            ->latest()
            ->paginate($pagination);

        return view('orders.index', compact('orders'))
            ->with('i', ($request->input('page', 1) - 1) * $pagination);
    }

    /**
     * Menampilkan form tambah pesanan.
     */
    public function create()
    {
        $suppliers = Supplier::all(['id', 'name']);
        $products = Product::where('qty', '>', 0)->get(['id', 'name', 'price', 'qty']);

        return view('orders.create', compact('suppliers', 'products'));
    }

    /**
     * Menyimpan pesanan baru.
     */
    public function store(Request $request)
    {
        // Validasi input dari form
        $request->validate([
            'order_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'order_details' => 'required|array|min:1',
            'order_details.*.product_id' => 'required|exists:products,id',
            'order_details.*.quantity' => 'required|integer|min:1',
        ]);

        // Memulai transaksi database
        DB::beginTransaction();
        try {
            // Mengambil data produk berdasarkan id yang dikirim
            $productIds = collect($request->order_details)->pluck('product_id')->toArray();
            $productData = Product::whereIn('id', $productIds)->get()->keyBy('id');

            // Memeriksa ketersediaan stok produk
            foreach ($request->order_details as $orderDetail) {
                if (!isset($productData[$orderDetail['product_id']])) {
                    throw new \Exception("Produk tidak ditemukan.");
                }

                if ($productData[$orderDetail['product_id']]->qty < $orderDetail['quantity']) {
                    throw new \Exception("Stok produk '{$productData[$orderDetail['product_id']]->name}' tidak cukup.");
                }
            }

            // Membuat pesanan baru
            $order = Order::create([
                'order_date' => $request->order_date,
                'supplier_id' => $request->supplier_id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0;

            // Menyimpan detail pesanan tanpa mengurangi stok produk
            foreach ($request->order_details as $orderDetail) {
                $product = $productData[$orderDetail['product_id']];
                $subtotal = $product->price * $orderDetail['quantity'];

                // Menambahkan detail pesanan
                $order->orderDetails()->create([
                    'product_id' => $product->id,
                    'quantity' => $orderDetail['quantity'],
                    'price' => $product->price,
                    'total' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            // Update total amount pesanan
            $order->update(['total_amount' => $totalAmount]);

            DB::commit();  // Menyelesaikan transaksi
            return redirect()->route('orders.index')->with('success', 'Pesanan berhasil disimpan!');
        } catch (\Exception $e) {
            DB::rollBack();  // Membatalkan transaksi jika terjadi kesalahan
            Log::error('Pesanan Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menyimpan pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan form edit pesanan.
     */
    public function edit($id)
    {
        $order = Order::with('orderDetails.product')->findOrFail($id);
        $suppliers = Supplier::all(['id', 'name']);
        $products = Product::all(['id', 'name', 'price', 'qty']);

        return view('orders.edit', compact('order', 'suppliers', 'products'));
    }

    /**
     * Memperbarui pesanan.
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'order_date' => 'required|date',
            'supplier_id' => 'required|exists:suppliers,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.id' => 'nullable|exists:order_details,id', // To handle the existing order details
        ]);

        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Handle order details update without stock changes
            $order->orderDetails()->delete();

            $productIds = collect($request->items)->pluck('product_id')->toArray();
            $productData = Product::whereIn('id', $productIds)->get()->keyBy('id');

            $totalAmount = 0;

            // Validate stock availability and handle the order details without affecting stock
            foreach ($request->items as $orderDetail) {
                if (!isset($productData[$orderDetail['product_id']])) {
                    throw new \Exception("Produk tidak ditemukan.");
                }

                $product = $productData[$orderDetail['product_id']];

                if ($product->qty < $orderDetail['quantity']) {
                    throw new \Exception("Stok produk '{$product->name}' tidak cukup.");
                }

                // Create the new order details
                $subtotal = $product->price * $orderDetail['quantity'];
                $order->orderDetails()->create([
                    'product_id' => $orderDetail['product_id'],
                    'quantity' => $orderDetail['quantity'],
                    'price' => $product->price,
                    'total' => $subtotal,
                ]);

                $totalAmount += $subtotal;
            }

            // Update the order's total amount
            $order->update(['total_amount' => $totalAmount]);

            DB::commit();

            return redirect()->route('orders.index')->with('success', 'Pesanan berhasil diperbarui!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Pesanan Update Error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal memperbarui pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Menghapus pesanan.
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = Order::findOrFail($id);

            // Menghapus detail pesanan dan pesanan itu sendiri
            $order->orderDetails()->delete();
            $order->delete();

            DB::commit();
            return redirect()->route('orders.index')->with('success', 'Pesanan berhasil dihapus!');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Gagal menghapus pesanan: ' . $e->getMessage());
        }
    }

    /**
     * Menampilkan detail pesanan.
     */
    public function show($id)
    {
        // Mengambil data pesanan beserta detail produk yang terkait
        $order = Order::with('orderDetails.product')->findOrFail($id);

        // Mengembalikan data pesanan ke view 'orders.show'
        return view('orders.show', compact('order'));
    }

    public function printOrder($id)
    {
        // Fetch the order data along with related supplier and order details
        $order = Order::with(['supplier', 'orderDetails.product'])->findOrFail($id);
    
        // Load the 'orders.print' view and pass the order data to it
        $pdf = Pdf::loadView('orders.print', compact('order'))
                  ->setPaper('a4', 'landscape'); // Set paper size and orientation
    
        // Return the PDF file as a stream (or you can use download instead of stream)
        return $pdf->stream('Order_' . $order->id . '.pdf');
    }
}
