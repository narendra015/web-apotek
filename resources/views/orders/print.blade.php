<!DOCTYPE html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Print Order - Pesanan #{{ $order->id }}</title>
    <style type="text/css">
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            border: 1px solid #dee2e6;
            padding: 8px 10px;
            text-align: center;
        }

        th {
            background-color: #f1f1f1;
        }

        .total-row td {
            font-weight: bold;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
        }

        .header h3 {
            margin: 5px 0;
        }

        .footer {
            text-align: right;
            margin-top: 30px;
        }
    </style>
</head>

<body>
    <div class="header">
        <h3>Apotek Cahaya Dua</h3>
    </div>
    <div>
        <h4>Detail Pesanan #{{ $order->id }}</h4>
        <p><strong>Supplier:</strong> {{ $order->supplier->name }}</p>
        <p><strong>Tanggal Pesanan:</strong> {{ \Carbon\Carbon::parse($order->order_date)->locale('id')->isoFormat('D MMMM YYYY') }}</p>
        <p><strong>Total Harga:</strong> Rp {{ number_format($order->total_amount, 0, '', '.') }}</p>
    </div>
    <table>
        <thead>
            <tr>
                <th>No.</th>
                <th>Produk</th>
                <th>Jumlah</th>
                <th>Harga</th>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
        @php
            $no = 1;
        @endphp
        @foreach ($order->orderDetails as $detail)
            <tr>
                <td>{{ $no++ }}</td>
                <td>{{ $detail->product->name }}</td>
                <td>{{ $detail->quantity }}</td>
                <td align="right">{{ 'Rp ' . number_format($detail->price, 0, '', '.') }}</td>
                <td align="right">{{ 'Rp ' . number_format($detail->total, 0, '', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="footer">
        Wonosobo, {{ \Carbon\Carbon::now()->locale('id')->isoFormat('D MMMM YYYY') }}
    </div>
</body>

</html>
