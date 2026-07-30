<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Product;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\StockMovement;
use App\Models\JournalEntry;
use App\Models\Account;


class SaleController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index()
    {
        $sales = Sale::with('customer', 'agent', 'createdBy')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.sales.index', compact('sales'));
    }

    public function create()
    {
        $customers = Customer::active()->orderBy('name')->get();
        $agents = User::where('role', 'sales_agent')
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get();
        $products = Product::active()->where('current_stock', '>', 0)->orderBy('name')->get();
        return view('admin.sales.create', compact('customers', 'agents', 'products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'agent_id' => 'nullable|exists:users,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
            'status' => 'required|in:draft,confirmed,paid',
            'sub_total' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'tax' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $subTotal = 0;
            $itemsData = [];
            
            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount'] ?? 0;
                $itemTax = $item['tax'] ?? 0;
                $totalPrice = $itemTotal - $itemDiscount + $itemTax;
                
                $subTotal += $totalPrice;
                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $item['tax'] ?? 0,
                    'total_price' => $totalPrice,
                ];
            }

            $commissionAmount = 0;
            if ($validated['agent_id']) {
                $agent = User::find($validated['agent_id']);
                if ($agent) {
                    $commissionAmount = $agent->commission_rate_cash ?? 0;
                }
            }

            $discountAmount = $validated['discount_type'] == 'percentage' 
                ? ($subTotal * ($validated['discount'] ?? 0) / 100) 
                : ($validated['discount'] ?? 0);
            
            $totalAmount = $subTotal - $discountAmount + ($validated['tax'] ?? 0) + ($validated['shipping_cost'] ?? 0);

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'agent_id' => $validated['agent_id'] ?? null,
                'sale_date' => $validated['sale_date'],
                'payment_term' => $validated['payment_term'],
                'status' => $validated['status'],
                'sub_total' => $subTotal,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? 'fixed',
                'tax' => $validated['tax'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'commission_amount' => $commissionAmount,
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
                
                $customer = Customer::find($validated['customer_id']);
                $customer->incrementOrderCount();
            }

            $this->saleService->applyStockAndAccounting($sale);

            if ($validated['status'] == 'paid') {
                $sale->markAsPaid();
                $this->saleService->recordPayment($sale, $totalAmount);
            }
        });

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale created successfully! Stock and accounting updated.');
    }

    /**
     * ✅ ADD THIS METHOD - Display sale details
     */
    public function show(Sale $sale)
    {
        $sale->load('customer', 'agent', 'items.product', 'payments', 'createdBy');
        return view('admin.sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot edit a paid sale!');
        }

        $customers = Customer::active()->orderBy('name')->get();
        $agents = User::where('role', 'sales_agent')
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get();
        $products = Product::active()->where('current_stock', '>', 0)->orderBy('name')->get();
        $sale->load('items');
        return view('admin.sales.edit', compact('sale', 'customers', 'agents', 'products'));
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot update a paid sale!');
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'agent_id' => 'nullable|exists:users,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
            'status' => 'required|in:draft,confirmed,paid',
            'sub_total' => 'required|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'discount_type' => 'nullable|in:fixed,percentage',
            'tax' => 'nullable|numeric|min:0',
            'shipping_cost' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $sale) {
            $subTotal = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount'] ?? 0;
                $itemTax = $item['tax'] ?? 0;
                $totalPrice = $itemTotal - $itemDiscount + $itemTax;

                $subTotal += $totalPrice;
                $itemsData[] = [
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount' => $item['discount'] ?? 0,
                    'tax' => $item['tax'] ?? 0,
                    'total_price' => $totalPrice,
                ];
            }

            // =============================================
            // ✅ FIX: Reverse old stock FIRST
            // =============================================
            foreach ($sale->items as $oldItem) {
                $product = Product::find($oldItem->product_id);
                if ($product) {
                    $product->current_stock = floatval($product->current_stock) + floatval($oldItem->quantity);
                    $product->save();
                }
            }

            // Delete old stock movements
            StockMovement::where('reference_type', 'sale')
                ->where('reference_id', $sale->id)
                ->delete();

            // Delete old journal entries
            JournalEntry::where('reference_type', 'sale')
                ->where('reference_id', $sale->id)
                ->delete();

            // Delete old items
            $sale->items()->delete();

            // =============================================
            // ✅ Create new items and apply new stock
            // =============================================
            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);

                // Update stock for new items
                $product = Product::find($itemData['product_id']);
                if ($product) {
                    $product->current_stock = floatval($product->current_stock) - floatval($itemData['quantity']);
                    $product->save();

                    // Create stock movement
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'out',
                        'reference_type' => 'sale',
                        'reference_id' => $sale->id,
                        'quantity' => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                        'total_price' => $itemData['total_price'],
                        'stock_before' => floatval($product->current_stock) + floatval($itemData['quantity']),
                        'stock_after' => $product->current_stock,
                        'notes' => "Sale #{$sale->invoice_no} (Updated)"
                    ]);
                }
            }

            // Update sale header
            $discountAmount = $validated['discount_type'] == 'percentage'
                ? ($subTotal * ($validated['discount'] ?? 0) / 100)
                : ($validated['discount'] ?? 0);

            $totalAmount = $subTotal - $discountAmount + ($validated['tax'] ?? 0) + ($validated['shipping_cost'] ?? 0);

            $sale->update([
                'customer_id' => $validated['customer_id'],
                'agent_id' => $validated['agent_id'] ?? null,
                'sale_date' => $validated['sale_date'],
                'payment_term' => $validated['payment_term'],
                'status' => $validated['status'],
                'sub_total' => $subTotal,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? 'fixed',
                'tax' => $validated['tax'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'due_amount' => $totalAmount - $sale->paid_amount,
                'notes' => $validated['notes'] ?? null,
            ]);

            // =============================================
            // ✅ Post accounting entries
            // =============================================
            $this->postSaleAccounting($sale);
        });

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale updated successfully! Stock and accounting adjusted.');
    }

    /**
     * Post accounting entries for sale
     */
    private function postSaleAccounting($sale)
    {
        $inventoryAccount = Account::where('code', '1030')->first();
        $revenueAccount = Account::where('code', '4010')->first();
        $receivableAccount = Account::where('code', '1040')->first();
        $cashAccount = Account::where('code', '1010')->first();

        if (!$inventoryAccount || !$revenueAccount) {
            return;
        }

        $entries = [];

        // DEBIT: Cash OR Customer Receivable
        if ($sale->payment_term == 'cash') {
            if ($cashAccount) {
                $entries[] = [
                    'account_id' => $cashAccount->id,
                    'type' => 'debit',
                    'amount' => $sale->total_amount,
                    'description' => "Cash Sale #{$sale->invoice_no}"
                ];
            }
        } else {
            if ($receivableAccount) {
                $entries[] = [
                    'account_id' => $receivableAccount->id,
                    'type' => 'debit',
                    'amount' => $sale->total_amount,
                    'description' => "Credit Sale #{$sale->invoice_no} - Customer: {$sale->customer->name}"
                ];
            }
        }

        // CREDIT: Sales Revenue
        $entries[] = [
            'account_id' => $revenueAccount->id,
            'type' => 'credit',
            'amount' => $sale->total_amount,
            'description' => "Sale Revenue #{$sale->invoice_no}"
        ];

        // COGS
        $cogsAmount = $sale->items->sum(function ($item) {
            $product = Product::find($item->product_id);
            return floatval($item->quantity) * floatval($product->purchase_price ?? 0);
        });

        if ($cogsAmount > 0) {
            $cogsAccount = Account::where('code', '5010')->first();
            if ($cogsAccount) {
                $entries[] = [
                    'account_id' => $cogsAccount->id,
                    'type' => 'debit',
                    'amount' => $cogsAmount,
                    'description' => "COGS for Sale #{$sale->invoice_no}"
                ];

                $entries[] = [
                    'account_id' => $inventoryAccount->id,
                    'type' => 'credit',
                    'amount' => $cogsAmount,
                    'description' => "Inventory reduction for Sale #{$sale->invoice_no}"
                ];
            }
        }

        foreach ($entries as $entry) {
            if ($entry['account_id']) {
                JournalEntry::create([
                    'account_id' => $entry['account_id'],
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'description' => $entry['description'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'entry_date' => now()->toDateString(),
                ]);
            }
        }
    }
    public function destroy(Sale $sale)
    {
        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot delete a paid sale!');
        }

        DB::transaction(function () use ($sale) {
            $this->saleService->reverseStockAndAccounting($sale);
            $sale->delete();
        });

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale deleted successfully! Stock and accounting reversed.');
    }

    public function addPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $sale->due_amount,
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated, $sale) {
            $sale->payments()->create([
                'customer_id' => $sale->customer_id,
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'payment_method' => $validated['payment_method'],
                'reference_no' => $validated['reference_no'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $sale->paid_amount += $validated['amount'];
            $sale->due_amount = $sale->total_amount - $sale->paid_amount;
            
            if ($sale->due_amount <= 0) {
                $sale->status = 'paid';
                $sale->save();
                $this->saleService->recordPayment($sale, $validated['amount']);
            } else {
                $sale->status = 'partial';
                $sale->save();
            }
        });

        return back()->with('success', 'Payment added successfully!');
    }
}