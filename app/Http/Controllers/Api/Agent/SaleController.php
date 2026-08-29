<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\Sale;
use App\Models\Customer;
use App\Http\Resources\Api\SaleResource;
use App\Services\SaleService;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SaleController extends ApiController
{
    protected $saleService;
    protected $commissionService;

    public function __construct(SaleService $saleService, CommissionService $commissionService)
    {
        $this->saleService = $saleService;
        $this->commissionService = $commissionService;
    }

    public function index(Request $request)
    {
        $query = Sale::where('agent_id', $this->agent()->id)->with('customer');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('sale_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('sale_date', '<=', $request->to_date);
        }

        $sales = $query->orderByDesc('sale_date')->paginate($request->input('per_page', 20));

        return $this->paginated($sales, fn ($s) => new SaleResource($s));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
            'status' => 'required|in:draft,confirmed',
            'amount_received' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque,credit_card',
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

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();

        // The web agent form doesn't check this either (a gap this API
        // shouldn't repeat) - an agent may only sell to their own customers.
        $customer = Customer::where('id', $validated['customer_id'])
            ->where('created_by_agent_id', $this->agent()->id)
            ->with('customerGroup')
            ->first();

        if (!$customer) {
            return $this->error('Customer not found or not accessible.', 404);
        }

        // Defense in depth: customer creation is already channel-gated
        // (Api\Agent\CustomerController), but a customer's group can change
        // afterward (e.g. an admin reassigns it), so a channel-restricted
        // agent is blocked from selling to a now-mismatched customer too.
        $priceField = $customer->customerGroup->price_field ?? 'sale_price';
        if (!$this->agent()->allowsPriceField($priceField)) {
            return $this->error('This customer is outside your assigned channel.', 403);
        }

        try {
            $sale = $this->storeSale($validated, $customer);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Catches SaleService's defensive throws (insufficient stock, a
            // missing chart-of-accounts entry) - without this they surfaced
            // as a raw 500 with no usable message for the mobile client.
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new SaleResource($sale->load('customer', 'items.product', 'payments')), 'Sale created successfully.', 201);
    }

    private function storeSale(array $validated, Customer $customer): Sale
    {
        return DB::transaction(function () use ($validated, $customer) {
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
                    'discount' => $itemDiscount,
                    'tax' => $itemTax,
                    'total_price' => $totalPrice,
                ];
            }

            $discountAmount = ($validated['discount_type'] ?? 'fixed') === 'percentage'
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
            // the client sent for 'status' or whether a payment was
            // collected up front - admin approval (Admin\SaleController::
            // confirm) is the only thing that now posts stock/accounting for
            // an agent-originated sale. Same rule the customer-app order
            // channel already followed (see Api\Customer\OrderController).
            $status = 'draft';

            $sale = Sale::create([
                'customer_id' => $customer->id,
                'agent_id' => $this->agent()->id,
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
                'created_by' => $this->agent()->id,
            ]);

            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            // No applyStockAndAccounting() call here - the sale is always a
            // draft at this point (see $status above), and that method is a
            // no-op for drafts anyway. Stock/accounting only post once an
            // admin confirms it (Admin\SaleController::confirm).
            $customer->incrementOrderCount();
            $this->commissionService->awardNewCustomerBonus($customer, $sale);

            if ($amountReceived > 0) {
                // Recorded as a PENDING payment - it sits alongside the
                // draft sale with zero ledger effect until admin confirms
                // the sale (which approves any pending payment on it too,
                // see Admin\SaleController::confirm).
                $this->saleService->recordPayment($sale, $amountReceived, $validated['payment_method'] ?? 'cash', $validated['sale_date'], null, null, 'pending', $this->agent()->id);
            }

            return $sale;
        });
    }

    public function show(Sale $sale)
    {
        if ($sale->agent_id != $this->agent()->id) {
            return $this->error('You do not have access to this sale.', 403);
        }

        $sale->load('customer', 'items.product', 'payments');

        return $this->success(new SaleResource($sale));
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->agent_id != $this->agent()->id) {
            return $this->error('You do not have access to this sale.', 403);
        }

        // Once admin has acted on this sale (confirmed/paid/partial, or
        // rejected to cancelled), it's locked from agent-side edits - only
        // a still-pending draft can be changed here. Without this, an
        // already-confirmed sale could still reach syncItemsAndUpdate()
        // below and re-post live stock/ledger entries agent-side.
        if ($sale->status !== 'draft') {
            return $this->error('This sale has already been reviewed and confirmed by admin, so it can no longer be edited here. Please contact your admin or manager if changes are needed.', 422);
        }

        $validator = Validator::make($request->all(), [
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

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();

        $customer = Customer::where('id', $validated['customer_id'])
            ->where('created_by_agent_id', $this->agent()->id)
            ->with('customerGroup')
            ->first();

        if (!$customer) {
            return $this->error('Customer not found or not accessible.', 404);
        }

        $priceField = $customer->customerGroup->price_field ?? 'sale_price';
        if (!$this->agent()->allowsPriceField($priceField)) {
            return $this->error('This customer is outside your assigned channel.', 403);
        }

        // Same rule as creation: a 'cash' sale posts its FULL total straight
        // to Cash with nothing tracking any shortfall. This endpoint doesn't
        // collect a payment, so a sale that still owes money can't be
        // (re)labeled 'cash' here - Add Payment is the only real way to
        // settle it, or Credit is the correct term for it either way.
        if ($sale->status !== 'draft' && $validated['payment_term'] === 'cash' && (float) $sale->due_amount > 0.01) {
            return $this->error('This sale still has an outstanding balance, so it cannot be set to Cash. Use Credit instead, or record the remaining payment first via Add Payment.', 422);
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
                        'discount' => $itemDiscount,
                        'tax' => $itemTax,
                        'total_price' => $totalPrice,
                    ];
                }

                // Status is deliberately NOT settable here - it stays
                // whatever it already was. Draft stays draft (only admin
                // confirm/reject moves it off draft); an already-confirmed
                // sale stays confirmed. Without this, an agent could use
                // this endpoint to self-promote a draft straight to
                // confirmed (which syncItemsAndUpdate below would then post
                // stock/accounting for), completely bypassing admin review.
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
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new SaleResource($sale->fresh(['customer', 'items.product', 'payments'])), 'Sale updated successfully.');
    }

    public function destroy(Sale $sale)
    {
        if ($sale->agent_id != $this->agent()->id) {
            return $this->error('You do not have access to this sale.', 403);
        }

        // Same lock as update() above - once admin has acted on it, an
        // agent can no longer delete it either.
        if ($sale->status !== 'draft') {
            return $this->error('This sale has already been reviewed and confirmed by admin, so it can no longer be deleted here. Please contact your admin or manager if changes are needed.', 422);
        }

        try {
            DB::transaction(function () use ($sale) {
                $this->saleService->reverseForDeletion($sale);
                $sale->delete();
            });
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(null, 'Sale deleted successfully.');
    }

    public function addPayment(Request $request, Sale $sale)
    {
        if ($sale->agent_id != $this->agent()->id) {
            return $this->error('You do not have access to this sale.', 403);
        }

        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:0.01|max:' . $sale->due_amount,
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->error('Validation failed', 422, $validator->errors());
        }

        $validated = $validator->validated();

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
                $this->agent()->id
            );
        } catch (\Exception $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success(new SaleResource($sale->fresh(['customer', 'items.product', 'payments'])), 'Payment submitted - pending admin approval.');
    }
}
