@extends('layouts.admin')

@section('title', 'New Sale')
@section('page-title', 'Create Sale')

@section('content')
<div x-data="saleForm()" class="space-y-4 sm:space-y-6">
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <h3 class="text-base sm:text-lg font-semibold text-gray-900"><i class="fas fa-plus-circle text-blue-600 mr-2"></i> New Sale</h3>
        </div>

        <div class="p-4 sm:p-6">
            <form action="{{ route('admin.sales.store') }}" method="POST">
                @csrf

                <!-- Header Fields -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                    <div class="sm:col-span-1">
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                        <select name="customer_id" x-model="customer_id" required class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Customer</option>
                            @foreach($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} ({{ $customer->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Agent</label>
                        <select name="agent_id" x-model="agent_id" @change="updateCommission()" class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Select Agent</option>
                            @foreach($agents as $agent)
                            <option value="{{ $agent->id }}" data-rate="{{ $agent->commission_rate }}" data-type="{{ $agent->commission_type }}">{{ $agent->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="sale_date" value="{{ date('Y-m-d') }}" required class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Payment <span class="text-red-500">*</span></label>
                        <select name="payment_term" required class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="cash">Cash</option>
                            <option value="credit">Credit</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-xs sm:text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                        <select name="status" required class="w-full px-2 sm:px-3 py-1.5 sm:py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="draft">Draft</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                </div>

                <!-- Products -->
                <div class="mt-4 sm:mt-6">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <label class="text-sm font-medium text-gray-700"><i class="fas fa-boxes text-gray-400 mr-1"></i> Products <span class="text-red-500">*</span> <span class="ml-1 text-xs text-gray-500" x-text="'(' + items.length + ' items)'"></span></label>
                        <button type="button" @click="addRow()" class="px-3 py-1.5 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 transition-colors duration-200"><i class="fas fa-plus mr-1"></i> Add Product</button>
                    </div>
                    
                    <!-- Desktop Table -->
                    <div class="hidden sm:block overflow-x-auto">
                        <table class="w-full min-w-[700px]">
                            <thead><tr class="bg-gray-50 border-b border-gray-200"><th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 min-w-[150px]">Product</th><th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[80px]">Qty</th><th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[100px]">Price</th><th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[80px]">Disc</th><th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[80px]">Tax</th><th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[100px]">Total</th><th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-2 px-2 w-[50px]"></th></tr></thead>
                            <tbody>
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="border-b border-gray-100 hover:bg-blue-50/30 transition-colors duration-150">
                                        <td class="py-1.5 px-1.5">
                                            <select :name="'items['+index+'][product_id]'" x-model="item.product_id" @change="selectProduct(index)" class="w-full px-2 py-1 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white">
                                                <option value="">Select Product</option>
                                                @foreach($products as $product)
                                                <option value="{{ $product->id }}" data-price="{{ $product->sale_price }}">{{ $product->name }} ({{ $product->code }})</option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="py-1.5 px-1.5"><input type="number" step="0.01" :name="'items['+index+'][quantity]'" x-model="item.quantity" @input="calculateRow(index)" class="w-full px-1 py-1 text-sm text-center border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0.01" step="0.01"></td>
                                        <td class="py-1.5 px-1.5"><input type="number" step="0.01" :name="'items['+index+'][unit_price]'" x-model="item.unit_price" @input="calculateRow(index)" class="w-full px-1 py-1 text-sm text-right border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" step="0.01"></td>
                                        <td class="py-1.5 px-1.5"><input type="number" step="0.01" :name="'items['+index+'][discount]'" x-model="item.discount" @input="calculateRow(index)" class="w-full px-1 py-1 text-sm text-right border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" step="0.01"></td>
                                        <td class="py-1.5 px-1.5"><input type="number" step="0.01" :name="'items['+index+'][tax]'" x-model="item.tax" @input="calculateRow(index)" class="w-full px-1 py-1 text-sm text-right border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" min="0" step="0.01"></td>
                                        <td class="py-1.5 px-1.5 text-right"><span class="text-sm font-medium text-blue-600" x-text="'Rs. ' + item.total.toFixed(2)"></span></td>
                                        <td class="py-1.5 px-1.5 text-center"><button type="button" @click="removeRow(index)" class="text-red-500 hover:text-red-700 hover:bg-red-50 p-1 rounded-lg"><i class="fas fa-times"></i></button></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Mobile View -->
                    <div class="sm:hidden space-y-2">
                        <template x-for="(item, index) in items" :key="index">
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3 space-y-2">
                                <select :name="'items['+index+'][product_id]'" x-model="item.product_id" @change="selectProduct(index)" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded-lg bg-white"><option value="">Select Product</option>@foreach($products as $product)<option value="{{ $product->id }}" data-price="{{ $product->sale_price }}">{{ $product->name }}</option>@endforeach</select>
                                <div class="grid grid-cols-3 gap-2"><div><label class="text-xs text-gray-500">Qty</label><input type="number" step="0.01" :name="'items['+index+'][quantity]'" x-model="item.quantity" @input="calculateRow(index)" class="w-full px-2 py-1 text-sm text-center border border-gray-300 rounded-lg" min="0.01" step="0.01"></div><div><label class="text-xs text-gray-500">Price</label><input type="number" step="0.01" :name="'items['+index+'][unit_price]'" x-model="item.unit_price" @input="calculateRow(index)" class="w-full px-2 py-1 text-sm text-right border border-gray-300 rounded-lg" min="0" step="0.01"></div><div><label class="text-xs text-gray-500">Total</label><p class="text-sm font-semibold text-blue-600 text-right pt-1" x-text="'Rs. ' + item.total.toFixed(2)"></p></div></div>
                                <button type="button" @click="removeRow(index)" class="w-full text-center text-red-500 hover:text-red-700 text-sm py-1 border border-red-200 rounded-lg hover:bg-red-50">Remove</button>
                            </div>
                        </template>
                    </div>
                    
                    <div x-show="items.length === 0" class="text-center py-8 text-gray-400"><i class="fas fa-box-open text-3xl mb-2 block"></i><p class="text-sm">No products added. Click "Add Product" to start.</p></div>
                </div>

                <!-- Totals Footer -->
                <div class="mt-4 border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                            <input type="text" name="notes" placeholder="Add notes..." class="w-full px-3 py-2 text-sm border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 justify-end">
                                <span class="text-sm text-gray-600">Sub Total:</span>
                                <span class="text-sm font-semibold" x-text="'Rs. ' + subTotal.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-2 justify-end">
                                <span class="text-sm text-gray-600">Discount:</span>
                                <input type="number" step="0.01" name="discount" x-model="discount" @input="calculateTotals()" class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded-lg" min="0" step="0.01">
                                <select name="discount_type" x-model="discountType" @change="calculateTotals()" class="w-16 px-1 py-1 text-xs border border-gray-300 rounded-lg"><option value="fixed">Rs</option><option value="percentage">%</option></select>
                                <span class="text-sm text-red-600" x-text="'- Rs. ' + discountAmount.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-2 justify-end">
                                <span class="text-sm text-gray-600">Tax:</span>
                                <input type="number" step="0.01" name="tax" x-model="tax" @input="calculateTotals()" class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded-lg" min="0" step="0.01">
                                <span class="text-sm" x-text="'Rs. ' + tax.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-2 justify-end">
                                <span class="text-sm text-gray-600">Shipping:</span>
                                <input type="number" step="0.01" name="shipping_cost" x-model="shipping" @input="calculateTotals()" class="w-24 px-2 py-1 text-sm text-right border border-gray-300 rounded-lg" min="0" step="0.01">
                                <span class="text-sm" x-text="'Rs. ' + shipping.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-2 justify-end border-t border-gray-200 pt-2">
                                <span class="text-sm font-semibold text-gray-700">Commission:</span>
                                <span class="text-sm font-semibold text-purple-600" x-text="'Rs. ' + commissionAmount.toFixed(2)"></span>
                            </div>
                            <div class="flex items-center gap-2 justify-end bg-blue-50 p-2 rounded-lg border-2 border-blue-200">
                                <span class="text-base font-bold text-gray-700">Grand Total:</span>
                                <span class="text-lg font-bold text-blue-600" x-text="'Rs. ' + grandTotal.toFixed(2)"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="sub_total" x-bind:value="subTotal">
                <input type="hidden" name="commission_amount" x-bind:value="commissionAmount">

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <button type="submit" class="w-full sm:w-auto px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200"><i class="fas fa-save mr-1"></i> Create Sale</button>
                    <a href="{{ route('admin.sales.index') }}" class="w-full sm:w-auto px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors duration-200 text-center">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function saleForm() {
    const agents = @json($agents);
    const products = @json($products);

    return {
        items: [],
        customer_id: '',
        agent_id: '',
        discount: 0,
        discountType: 'fixed',
        tax: 0,
        shipping: 0,
        subTotal: 0,
        discountAmount: 0,
        commissionAmount: 0,
        grandTotal: 0,
        commissionRate: 0,
        commissionType: 'percentage',

        init() { this.addRow(); },

        addRow() {
            this.items.push({ product_id: '', quantity: 1, unit_price: 0, discount: 0, tax: 0, total: 0 });
            this.calculateTotals();
        },

        removeRow(index) {
            if (this.items.length > 1) { this.items.splice(index, 1); this.calculateTotals(); }
        },

        // Fires when the product dropdown changes: pulls the sale price
        // for the chosen product and fills it into the row, then recalculates.
        selectProduct(index) {
            const item = this.items[index];
            const product = products.find(p => p.id == item.product_id);
            item.unit_price = product ? parseFloat(product.sale_price) || 0 : 0;
            this.calculateRow(index);
        },

        calculateRow(index) {
            const item = this.items[index];
            const qty = parseFloat(item.quantity) || 0;
            const price = parseFloat(item.unit_price) || 0;
            const disc = parseFloat(item.discount) || 0;
            const tax = parseFloat(item.tax) || 0;
            item.total = (qty * price) - disc + tax;
            this.calculateTotals();
        },

        updateCommission() {
            let agent = agents.find(a => a.id == this.agent_id);
            if (agent) {
                this.commissionRate = parseFloat(agent.commission_rate);
                this.commissionType = agent.commission_type;
            } else {
                this.commissionRate = 0;
                this.commissionType = 'percentage';
            }
            this.calculateTotals();
        },

        calculateTotals() {
            this.subTotal = this.items.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0);

            const discount = parseFloat(this.discount) || 0;
            this.discountAmount = this.discountType === 'percentage' ? (this.subTotal * discount / 100) : discount;

            const tax = parseFloat(this.tax) || 0;
            const shipping = parseFloat(this.shipping) || 0;

            let netTotal = this.subTotal - this.discountAmount + tax + shipping;

            // Commission
            if (this.agent_id && this.commissionRate > 0) {
                this.commissionAmount = this.commissionType === 'percentage' 
                    ? (netTotal * this.commissionRate / 100) 
                    : this.commissionRate;
            } else {
                this.commissionAmount = 0;
            }

            this.grandTotal = netTotal + this.commissionAmount;
        }
    }
}
</script>
@endsection