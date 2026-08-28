<?php

namespace App\Http\Controllers\Agent;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Customer;
use App\Models\Product;
use App\Services\SaleService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
    protected $saleService;
    protected $commissionService;

    public function __construct(SaleService $saleService, CommissionService $commissionService)
    {
        $this->saleService = $saleService;
        $this->commissionService = $commissionService;
    }

    public function index()
    {
        $sales = Sale::where('agent_id', Auth::id())
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('agent.sales.index', compact('sales'));
    }

    public function create()
    {
        $customers = Customer::where('created_by_agent_id', Auth::id())
            ->active()
            ->with('customerGroup')
            ->orderBy('name')
            ->get();

        $products = Product::active()
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();
        $agents = User::where('role', 'sales_agent')->get();
        $productsForJs = $this->productsForJs($products);
        return view('agent.sales.create', compact('customers', 'products', 'agents', 'productsForJs'));
    }

    /**
     * Flat product data for the sale form's Alpine component - filtered
     * client-side by is_retail/is_wholesale to match the selected
     * customer's group, and used to auto-fill the right price for that
     * group instead of always defaulting to retail.
     */
    private function productsForJs($products)
    {
        return $products->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'code' => $p->code,
            'sale_price' => (float) $p->sale_price,
            'wholesale_price' => (float) $p->wholesale_price,
            'purchase_price' => (float) $p->purchase_price,
            'current_stock' => (float) $p->current_stock,
            'is_retail' => (bool) $p->is_retail,
            'is_wholesale' => (bool) $p->is_wholesale,
        ])->values();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
            'status' => 'required|in:draft,confirmed',
            'amount_received' => 'nullable|numeric|min:0',
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

        try {
            $this->storeSale($validated);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Catches SaleService's defensive throws (insufficient stock, a
            // missing chart-of-accounts entry) - without this they surfaced
            // as a raw 500 error page instead of telling the agent what
            // actually went wrong.
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('agent.sales.index')
            ->with('success', 'Sale created successfully!');
    }

    private function storeSale(array $validated)
    {
        DB::transaction(function () use ($validated) {
            $customer = Customer::find($validated['customer_id']);
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

            $discountAmount = $validated['discount_type'] == 'percentage'
                ? ($subTotal * ($validated['discount'] ?? 0) / 100)
                : ($validated['discount'] ?? 0);

            $totalAmount = $subTotal - $discountAmount + ($validated['tax'] ?? 0) + ($validated['shipping_cost'] ?? 0);

            $amountReceived = (float) ($validated['amount_received'] ?? 0);
            if ($amountReceived > $totalAmount) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_received' => 'Amount received cannot exceed the sale total.',
                ]);
            }

            // A 'cash' sale posts its FULL total straight to the Cash account
            // the moment it's approved (SaleService::postAccounting) - there
            // is no receivable behind a cash sale to collect the rest from
            // later. Submitting one for less than the full total would
            // overstate Cash by the shortfall with nothing tracking the
            // difference once admin confirms it. If the customer isn't
            // paying it all today, this has to be a Credit sale instead.
            // Checked at submission time (not just at admin confirm) so bad
            // data is caught immediately instead of surfacing as a confusing
            // failure in the approvals queue later.
            if ($amountReceived > 0 && $validated['payment_term'] === 'cash' && abs($amountReceived - $totalAmount) > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_received' => 'A cash sale must be paid in full. Enter the full amount received, or choose Credit payment term if the customer will pay over time.',
                ]);
            }

            // Every agent-submitted sale lands as draft, regardless of what
            // the form sent for 'status' or whether a payment was collected
            // up front - admin approval (Admin\SaleController::confirm) is
            // the only thing that now posts stock/accounting for an
            // agent-originated sale.
            $status = 'draft';

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'agent_id' => Auth::id(),
                'sale_date' => $validated['sale_date'],
                'payment_term' => $validated['payment_term'],
                'status' => $status,
                'sub_total' => $subTotal,
                'discount' => $validated['discount'] ?? 0,
                'discount_type' => $validated['discount_type'] ?? 'fixed',
                'tax' => $validated['tax'] ?? 0,
                'shipping_cost' => $validated['shipping_cost'] ?? 0,
                'total_amount' => $totalAmount,
                'commission_amount' => 0,
                'commission_due_amount' => 0,
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            // No applyStockAndAccounting() call here - the sale is always a
            // draft at this point (see $status above), and that method is a
            // no-op for drafts anyway. Stock/accounting only post once an
            // admin confirms it (Admin\SaleController::confirm).

            // Update customer order count and check the new-customer bonus
            // (fixed, admin-configurable amount - the service itself checks
            // the order-count threshold and activity, and won't double-award).
            $customer->incrementOrderCount();
            $this->commissionService->awardNewCustomerBonus($customer, $sale);

            if ($amountReceived > 0) {
                // Recorded as a PENDING payment - it sits alongside the
                // draft sale with zero ledger effect until admin confirms
                // the sale (which approves any pending payment on it too,
                // see Admin\SaleController::confirm).
                $this->saleService->recordPayment($sale, $amountReceived, 'cash', $validated['sale_date'], null, null, 'pending', Auth::id());
            }
        });
    }

    public function show(Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        $sale->load('customer', 'items.product', 'payments');
        return view('agent.sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        // Once admin has acted on this sale (confirmed/paid/partial, or
        // rejected to cancelled), it's locked from agent-side edits - only
        // a still-pending draft can be changed here.
        if ($sale->status !== 'draft') {
            return back()->with('error', 'This sale has already been reviewed and confirmed by admin, so it can no longer be edited here. Please contact your admin or manager if changes are needed.');
        }

        $customers = Customer::where('created_by_agent_id', Auth::id())->active()->with('customerGroup')->orderBy('name')->get();
        $sale->load('items', 'customer.customerGroup');

        // Union "currently sellable" products with whatever this sale's
        // existing items already reference, so an item on a product that's
        // since gone inactive/out-of-stock still shows correctly instead of
        // the edit form silently blanking its selection.
        $existingProductIds = $sale->items->pluck('product_id');
        $products = Product::where(function ($q) {
                $q->where('is_active', true)->where('current_stock', '>', 0);
            })
            ->orWhereIn('id', $existingProductIds)
            ->orderBy('name')
            ->get();

        $productsForJs = $this->productsForJs($products);
        return view('agent.sales.edit', compact('sale', 'customers', 'products', 'productsForJs'));
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        // Same lock as edit() above - once admin has confirmed/rejected it,
        // this endpoint can no longer touch it (which also means it can
        // never reach syncItemsAndUpdate() and re-post live stock/ledger
        // entries for a sale admin has already acted on).
        if ($sale->status !== 'draft') {
            return back()->with('error', 'This sale has already been reviewed and confirmed by admin, so it can no longer be edited here. Please contact your admin or manager if changes are needed.');
        }

        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
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

        // Agents may only reassign a sale to their own customers.
        $customer = Customer::where('id', $validated['customer_id'])
            ->where('created_by_agent_id', Auth::id())
            ->first();
        if (!$customer) {
            abort(403, 'Unauthorized access.');
        }

        // Same rule as creation: a 'cash' sale posts its FULL total straight
        // to Cash with nothing tracking any shortfall. This form doesn't
        // collect a payment, so a sale that still owes money can't be
        // (re)labeled 'cash' here - Add Payment is the only real way to
        // settle it, or Credit is the correct term for it either way.
        if ($sale->status !== 'draft' && $validated['payment_term'] === 'cash' && (float) $sale->due_amount > 0.01) {
            return back()->with('error', 'This sale still has an outstanding balance, so it cannot be set to Cash. Use Credit instead, or record the remaining payment first via Add Payment.');
        }

        try {
            DB::transaction(function () use ($validated, $sale) {
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $itemTotal = $item['quantity'] * $item['unit_price'];
                    $itemDiscount = $item['discount'] ?? 0;
                    $itemTax = $item['tax'] ?? 0;
                    $totalPrice = $itemTotal - $itemDiscount + $itemTax;

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'discount' => $item['discount'] ?? 0,
                        'tax' => $item['tax'] ?? 0,
                        'total_price' => $totalPrice,
                    ];
                }

                // Status is deliberately NOT settable here - see the API
                // agent controller's update() for why (self-promoting a
                // draft to confirmed here would bypass admin review).
                $sale->update([
                    'customer_id' => $validated['customer_id'],
                    'sale_date' => $validated['sale_date'],
                    'payment_term' => $validated['payment_term'],
                    'status' => $sale->status,
                    'discount' => $validated['discount'] ?? 0,
                    'discount_type' => $validated['discount_type'] ?? 'fixed',
                    'tax' => $validated['tax'] ?? 0,
                    'shipping_cost' => $validated['shipping_cost'] ?? 0,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->saleService->syncItemsAndUpdate($sale, $itemsData);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('agent.sales.index')
            ->with('success', 'Sale updated successfully!');
    }

    public function destroy(Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        // Same lock as edit()/update() above - once admin has acted on it,
        // an agent can no longer delete it either.
        if ($sale->status !== 'draft') {
            return back()->with('error', 'This sale has already been reviewed and confirmed by admin, so it can no longer be deleted here. Please contact your admin or manager if changes are needed.');
        }

        try {
            DB::transaction(function () use ($sale) {
                $this->saleService->reverseForDeletion($sale);
                $sale->delete();
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('agent.sales.index')
            ->with('success', 'Sale deleted successfully!');
    }

    public function addPayment(Request $request, Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $sale->due_amount,
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            // Recorded as PENDING - admin must approve it (see
            // Admin\ApprovalController) before it posts to the ledger.
            $this->saleService->recordPayment(
                $sale,
                $validated['amount'],
                $validated['payment_method'],
                $validated['payment_date'],
                $validated['reference_no'] ?? null,
                $validated['notes'] ?? null,
                'pending',
                Auth::id()
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment submitted - pending admin approval.');
    }
}