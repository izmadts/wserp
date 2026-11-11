@extends('layouts.admin')

@section('title', 'Products')
@section('page-title', 'Product Management')

@section('content')
<div class="space-y-6">

{{-- Summary cards. Filled in by the script below from whatever rows the
     search box currently leaves visible. --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Products</p>
                <p class="text-2xl font-bold text-gray-900" data-card="count">0</p>
                <p class="text-xs text-gray-500 mt-1" data-card="count-sub">&nbsp;</p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center"><i class="fas fa-box text-blue-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Low Stock</p>
                <p class="text-2xl font-bold text-red-600" data-card="low">0</p>
            </div>
            <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center"><i class="fas fa-exclamation-circle text-red-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Out of Stock</p>
                <p class="text-2xl font-bold text-gray-700" data-card="out">0</p>
            </div>
            <div class="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center"><i class="fas fa-ban text-gray-600 text-xl"></i></div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-card p-4">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500">Inventory Value</p>
                <p class="text-2xl font-bold text-green-600" data-card="value">Rs. 0.00</p>
                <p class="text-xs text-gray-500 mt-1">at purchase price</p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center"><i class="fas fa-warehouse text-green-600 text-xl"></i></div>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-box text-gray-400 mr-2"></i> All Products
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $products->count() }} total</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.products.low-stock') }}" 
               class="px-4 py-2 bg-yellow-50 text-yellow-700 rounded-lg text-sm font-medium hover:bg-yellow-100 transition-colors duration-200">
                <i class="fas fa-exclamation-triangle mr-1"></i> Low Stock
                @php $count = App\Models\Product::lowStock()->count(); @endphp
                @if($count > 0)
                    <span class="ml-1 bg-yellow-200 text-yellow-800 px-2 py-0.5 rounded-full text-xs">{{ $count }}</span>
                @endif
            </a>
            <a href="{{ route('admin.products.create') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> Add Product
            </a>
        </div>
    </div>
    
    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full" id="productsTable">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Code</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Name</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Category</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Unit</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Purchase</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Sale</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Stock</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($products as $product)
                    <tr class="hover:bg-gray-50 transition-colors duration-150" data-stock="{{ (float) $product->current_stock }}" data-value="{{ (float) $product->current_stock * (float) $product->purchase_price }}" data-active="{{ $product->is_active ? 1 : 0 }}" data-low="{{ $product->isLowStock() ? 1 : 0 }}">
                        <td class="py-3 px-2">
                            <code class="text-xs bg-gray-100 px-2 py-1 rounded">{{ $product->code }}</code>
                        </td>
                        <td class="py-3 px-2">
                            <span class="font-medium text-gray-900">{{ $product->name }}</span>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">
                            {{ $product->category->name ?? '-' }}
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $product->unit }}</td>
                        <td class="py-3 px-2 text-right text-sm text-gray-600">
                            Rs. {{ number_format($product->purchase_price, 2) }}
                        </td>
                        <td class="py-3 px-2 text-right text-sm text-gray-600">
                            Rs. {{ number_format($product->sale_price, 2) }}
                        </td>
                        <td class="py-3 px-2 text-center">
                            @if($product->isLowStock())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-exclamation-circle mr-1"></i>
                                    {{ number_format($product->current_stock, 2) }}
                                </span>
                            @elseif($product->isOverStock())
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">
                                    <i class="fas fa-box-open mr-1"></i>
                                    {{ number_format($product->current_stock, 2) }}
                                </span>
                            @elseif($product->current_stock == 0)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    {{ number_format($product->current_stock, 2) }}
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    {{ number_format($product->current_stock, 2) }}
                                </span>
                            @endif
                        </td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $product->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                {{ $product->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <a href="{{ route('admin.products.show', $product) }}" 
                                   class="p-1.5 text-blue-600 hover:bg-blue-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-eye text-sm"></i>
                                </a>
                                <a href="{{ route('admin.products.edit', $product) }}" 
                                   class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                <form action="{{ route('admin.products.toggle-status', $product) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="p-1.5 {{ $product->is_active ? 'text-gray-600 hover:bg-gray-100' : 'text-green-600 hover:bg-green-50' }} rounded-lg transition-colors duration-200">
                                        <i class="fas {{ $product->is_active ? 'fa-pause' : 'fa-play' }} text-sm"></i>
                                    </button>
                                </form>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm('Are you sure?')" 
                                            class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-trash text-sm"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const tableEl = document.getElementById('productsTable');
        if (!tableEl) return;

        const table = new DataTable('#productsTable', {
            pageLength: 25,
            order: [[1, 'asc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries",
                info: "Showing _START_ to _END_ of _TOTAL_ entries",
                emptyTable: "No products found. Click \"Add Product\" to create one."
            }
        });

        const money = n => 'Rs. ' + n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const card = (key, value, sub) => {
            const v = document.querySelector('[data-card="' + key + '"]');
            const s = document.querySelector('[data-card="' + key + '-sub"]');
            if (v) v.textContent = value;
            if (s) s.textContent = sub || ' ';
        };

        function refreshCards() {
            const rows = table.rows({ search: 'applied' }).nodes().toArray();
            let active = 0, low = 0, out = 0, value = 0;

            rows.forEach(tr => {
                const stock = parseFloat(tr.dataset.stock) || 0;
                if (tr.dataset.active === '1') active++;
                if (tr.dataset.low === '1') low++;
                if (stock <= 0.005) out++;
                value += parseFloat(tr.dataset.value) || 0;
            });

            const n = rows.length;
            card('count', n.toLocaleString('en-US'), active + ' active · ' + (n - active) + ' inactive');
            card('low', low.toLocaleString('en-US'));
            card('out', out.toLocaleString('en-US'));
            card('value', money(value));
        }

        table.on('draw', refreshCards);
        refreshCards();
    });
</script>
@endpush