<x-app-layout>
    <x-page-title>Tambah Pembelian</x-page-title>

    <div class="bg-white rounded-2 shadow-sm p-4 mb-5">
        {{-- Menampilkan pesan error --}}
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        {{-- Form Tambah Pesanan --}}
        <form action="{{ route('orders.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-lg-6">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Pembelian <span class="text-danger">*</span></label>
                        <input type="date" name="order_date" class="form-control" value="{{ now()->format('Y-m-d') }}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pemasok <span class="text-danger">*</span></label>
                        <select name="supplier_id" class="form-control select2-supplier @error('supplier_id') is-invalid @enderror" required>
                            <option></option>
                            @foreach($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                            @endforeach
                        </select>
                        @error('supplier_id')
                            <div class="alert alert-danger mt-2">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Tabel Produk --}}
            <table class="table" id="items-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kuantitas</th>
                        <th>Harga</th>
                        <th>Total</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <select name="order_details[0][product_id]" class="form-control product-select select2-product" required>
                                <option></option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-qty="{{ $product->qty }}">
                                        {{ $product->name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="number" name="order_details[0][quantity]" class="form-control qty-input" required></td>
                        <td><input type="text" name="order_details[0][price]" class="form-control price-input" data-raw="0" readonly required></td>
                        <td><input type="text" name="order_details[0][total]" class="form-control total-input" readonly required></td>
                        <td><button type="button" class="btn btn-danger remove-item">Hapus</button></td>
                    </tr>
                </tbody>
            </table>

            {{-- Total Harga --}}
            <div class="mt-3">
                <table class="table w-auto">
                    <tr>
                        <td><strong>Total Harga</strong></td>
                        <td style="width:250px"><input type="text" id="total-price" class="form-control" readonly></td>
                    </tr>
                </table>
            </div>

            <button type="button" id="add-item" class="btn btn-primary">Tambah Produk</button>
            <button type="submit" class="btn btn-success">Simpan Pesanan</button>
        </form>
    </div>

    {{-- Select2 Style --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>
    <style>
        .select2-container .select2-selection--single {
            height: 38px !important;
            padding: 6px 12px !important;
            font-size: 0.9rem !important;
            border: 1px solid #ced4da;
            border-radius: .375rem;
        }
        .select2-container--default .select2-selection__arrow { height: 38px !important; top: 0 !important; right: 8px }
        .select2-container--default .select2-selection__rendered { color: #6c757d }
    </style>

    {{-- Scripts --}}
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        let itemIndex = 1;

        // Format angka ke ribuan
        function formatNumber(val) {
            return new Intl.NumberFormat('id-ID').format(val);
        }

        // Inisialisasi Select2
        function initSelect2(scope = document) {
            $(scope).find('.select2-supplier').select2({
                placeholder: 'Pilih pemasok', allowClear: true, width: '100%',
                language: { noResults: () => 'Tidak ditemukan' }
            });
            $(scope).find('.select2-product').select2({
                placeholder: 'Pilih produk', allowClear: true, width: '100%',
                language: { noResults: () => 'Tidak ditemukan' }
            });
        }

        initSelect2();

        // Fokus ke kolom pencarian saat dropdown terbuka
        $(document).on('select2:open', () => {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field')?.focus();
            }, 0);
        });

        // Tambah baris baru
        document.getElementById('add-item').addEventListener('click', function() {
            const row = `
                <tr>
                    <td>
                        <select name="order_details[${itemIndex}][product_id]" class="form-control product-select select2-product" required>
                            <option></option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" data-price="{{ $product->price }}" data-qty="{{ $product->qty }}">
                                    {{ $product->name }}
                                </option>
                            @endforeach
                        </select>
                    </td>
                    <td><input type="number" name="order_details[${itemIndex}][quantity]" class="form-control qty-input" required></td>
                    <td><input type="text" name="order_details[${itemIndex}][price]" class="form-control price-input" data-raw="0" readonly required></td>
                    <td><input type="text" name="order_details[${itemIndex}][total]" class="form-control total-input" readonly required></td>
                    <td><button type="button" class="btn btn-danger remove-item">Hapus</button></td>
                </tr>`;
            $('#items-table tbody').append(row);
            initSelect2($('#items-table tbody tr:last'));
            itemIndex++;
        });

        // Hapus baris produk
        $(document).on('click', '.remove-item', function() {
            $(this).closest('tr').remove();
            calculateTotalPrice();
        });

        // Saat produk dipilih
        $(document).on('change', '.product-select', function() {
            const row = $(this).closest('tr');
            const opt = this.selectedOptions[0];
            const price = parseInt(opt.dataset.price || 0);
            const stock = parseInt(opt.dataset.qty || 0);

            row.find('.price-input').val(formatNumber(price));
            row.find('.price-input').attr('data-raw', price);
            row.find('.qty-input').attr('max', stock).val('');
            row.find('.total-input').val('');
            calculateTotalPrice();
        });

        // Saat jumlah berubah
        $(document).on('input', '.qty-input', function() {
            const row = $(this).closest('tr');
            const qty = parseInt(this.value) || 0;
            const price = parseInt(row.find('.price-input').attr('data-raw')) || 0;
            const total = qty * price;
            row.find('.total-input').val(qty ? formatNumber(total) : '');
            calculateTotalPrice();
        });

        // Hitung total semua item
        function calculateTotalPrice() {
            let total = 0;
            $('.total-input').each(function() {
                total += parseInt(this.value.replace(/\./g, '')) || 0;
            });
            $('#total-price').val(formatNumber(total));
        }
    </script>
</x-app-layout>
