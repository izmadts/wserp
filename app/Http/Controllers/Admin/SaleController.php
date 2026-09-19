<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Models\User;
use App\Models\Product;
use App\Models\Expense;
use App\Services\SaleService;
use App\Services\CommissionService;
use App\Services\FcmService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
    protected $saleService;
    protected $commissionService;
    protected $fcmService;

    public function __construct(SaleService $saleService, CommissionService $commissionService, FcmService $fcmService)
    {
        $this->saleService = $saleService;
        $this->commissionService = $commissionService;
        $this->fcmService = $fcmService;
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
        $customers = Customer::active()->with('customerGroup')->orderBy('name')->get();
        $agents = User::where('role', 'sales_agent')
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get(['id', 'name']);
        $products = Product::active()->where('current_stock', '>', 0)->orderBy('name')->get();
        $commissionPreview = $this->commissionPreviewData($agents);
        $productsForJs = $this->productsForJs($products);
        return view('admin.sales.create', compact('customers', 'agents', 'products', 'commissionPreview', 'productsForJs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'agent_id' => 'nullable|exists:users,id',
            'sale_date' => 'required|date',
            'payment_term' => 'required|in:cash,credit',
            'status' => 'required|in:draft,confirmed',
            'amount_received' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|in:cash,bank_transfer,cheque,credit_card',
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
            // as a raw 500 error page instead of telling the admin what
            // actually went wrong. DB::transaction() below still rolls back
            // correctly regardless of what happens to the exception here.
            return back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale created successfully! Stock and accounting updated.');
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
            // the moment it's confirmed (SaleService::postAccounting) - there
            // is no receivable behind a cash sale to collect the rest from
            // later. Confirming one for less than the full total would
            // overstate Cash by the shortfall with nothing tracking the
            // difference. If the customer isn't paying it all today, this
            // has to be a Credit sale instead.
            if ($validated['status'] !== 'draft' && $validated['payment_term'] === 'cash' && abs($amountReceived - $totalAmount) > 0.01) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount_received' => 'A cash sale must be paid in full. Enter the full amount received, save as Draft instead, or choose Credit payment term if the customer will pay over time.',
                ]);
            }

            // A real payment can't be received against a draft/quote - if
            // money changed hands, the sale is confirmed, regardless of what
            // the form's status field happened to submit. status only ever
            // reaches 'paid'/'partial' via recordPayment() below, never by
            // being written directly here - that's what let a sale be
            // labeled "Paid" with $0 actually recorded (and, separately,
            // suppressed the Golden Club event - see SaleService::recordPayment).
            $status = $amountReceived > 0 ? 'confirmed' : $validated['status'];

            // Credit-hold / credit-limit gate - both off by default, admin
            // opt-in via Settings > Commission & Bonus. A draft sale hasn't
            // posted a receivable yet, so it's not gated here.
            if ($status !== 'draft' && $validated['payment_term'] === 'credit') {
                $blockMessage = $this->commissionService->creditGateMessage($customer, $totalAmount);
                if ($blockMessage) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'customer_id' => $blockMessage,
                    ]);
                }
            }

            // Commission is calculated by CommissionService below (settings-
            // driven progressive tiers for cash, per-payment accrual for
            // credit) - not a flat agent rate stored as a lump amount here.
            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'agent_id' => $validated['agent_id'] ?? null,
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
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);
            }

            // Once per sale, not once per line item.
            $customer->incrementOrderCount();
            $this->commissionService->awardNewCustomerBonus($customer, $sale);

            $this->saleService->applyStockAndAccounting($sale);

            if ($amountReceived > 0) {
                // recordPayment() sets paid_amount/due_amount/status itself
                // (it flips to 'paid' once due_amount reaches 0, 'partial'
                // otherwise) and fires SaleCreated (Golden Club processing)
                // the moment it genuinely reaches paid for the first time -
                // routing an instant full payment through here (instead of
                // creating the row already status='paid') is what makes that
                // event actually fire for a pay-in-full-at-checkout sale.
                $this->saleService->recordPayment($sale, $amountReceived, $validated['payment_method'] ?? 'cash', $validated['sale_date'], null, null, 'approved', Auth::id(), Auth::id());
            }
        });
    }

    /**
     * ✅ ADD THIS METHOD - Display sale details
     */
    public function show(Sale $sale)
    {
        $sale->load('customer', 'agent', 'items.product', 'payments.createdBy', 'createdBy');
        return view('admin.sales.show', compact('sale'));
    }

    public function edit(Sale $sale)
    {
        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot edit a paid sale!');
        }

        $customers = Customer::active()->with('customerGroup')->orderBy('name')->get();
        $agents = User::where('role', 'sales_agent')
            ->where('is_active', true)
            ->whereNotNull('approved_at')
            ->orderBy('name')
            ->get(['id', 'name']);
        $sale->load('items', 'customer.customerGroup');

        // The sale's own customer / agent must always be selectable, even if
        // they've since been deactivated (an agent can deactivate a customer
        // from the app). Otherwise the dropdown silently drops them, the
        // select falls back to the first option and saving would re-assign
        // the sale to somebody else without the admin noticing.
        if ($sale->customer && !$customers->contains('id', $sale->customer_id)) {
            $customers = $customers->push($sale->customer)
                ->sortBy(fn ($c) => mb_strtolower($c->name))->values();
        }
        if ($sale->agent_id && !$agents->contains('id', $sale->agent_id)) {
            $missingAgent = User::find($sale->agent_id, ['id', 'name']);
            if ($missingAgent) {
                $agents = $agents->push($missingAgent)->sortBy(fn ($a) => mb_strtolower($a->name))->values();
            }
        }

        // Payments the agent recorded that are still waiting for approval -
        // shown on the form so a wrong amount/method/reference can be
        // corrected (or the payment dropped) before the sale is confirmed.
        $pendingPayments = $sale->payments()->pending()->orderBy('id')->get();
        $returnTo = request('return') === 'approvals' ? 'approvals' : null;

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

        $commissionPreview = $this->commissionPreviewData($agents, $sale->id);
        $productsForJs = $this->productsForJs($products);
        return view('admin.sales.edit', compact('sale', 'customers', 'agents', 'products', 'commissionPreview', 'productsForJs', 'pendingPayments', 'returnTo'));
    }

    /**
     * Cash-tier table, credit rate, and each agent's month-to-date confirmed
     * cash sales - everything the create/edit form's JS needs to preview the
     * real settings-driven commission (CommissionService::calculateCashCommission)
     * instead of a flat rate that hasn't matched the actual engine since the
     * Phase 3 rebuild. $excludeSaleId keeps an in-progress edit from double
     * counting the sale's own prior amount into its own bracket.
     */
    private function commissionPreviewData($agents, $excludeSaleId = null)
    {
        $now = now();
        $mtdByAgent = [];

        foreach ($agents as $agent) {
            $query = Sale::where('agent_id', $agent->id)
                ->where('payment_term', 'cash')
                ->whereIn('status', ['confirmed', 'partial', 'paid'])
                ->whereMonth('sale_date', $now->month)
                ->whereYear('sale_date', $now->year);

            if ($excludeSaleId) {
                $query->where('id', '!=', $excludeSaleId);
            }

            $mtdByAgent[$agent->id] = (float) $query->sum('total_amount');
        }

        return [
            'cash_tiers' => CommissionService::getSetting('commission.cash_tiers'),
            'credit_rate' => (float) CommissionService::getSetting('commission.credit_rate'),
            'agent_mtd_cash' => $mtdByAgent,
        ];
    }

    /**
     * Flat product data for the sale form's Alpine component - it filters
     * this client-side by is_retail/is_wholesale to match the selected
     * customer's group, and reads sale_price/wholesale_price to auto-fill
     * the right price for that group instead of always defaulting to retail.
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
            // 'partial'/'paid' deliberately excluded - those are derived
            // from recorded payments (SaleService::recordPayment), never
            // picked directly, or a sale could land on 'paid' with $0 paid.
            'status' => 'required|in:draft,confirmed',
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
            // Corrections to payments the agent recorded that are still
            // waiting for approval (keyed by sale_payments.id).
            'pending_payments' => 'nullable|array',
            'pending_payments.*.amount' => 'nullable|numeric|min:0.01',
            'pending_payments.*.payment_method' => 'nullable|in:cash,bank_transfer,cheque,credit_card',
            'pending_payments.*.payment_date' => 'nullable|date',
            'pending_payments.*.reference_no' => 'nullable|string|max:100',
            'pending_payments.*.reject' => 'nullable|boolean',
            // A payment the admin records while reviewing a draft (cash / bank
            // ...) - kept pending and approved with the sale, like the app's.
            'new_payment' => 'nullable|array',
            'new_payment.amount' => 'nullable|numeric|min:0',
            'new_payment.payment_method' => 'nullable|in:cash,bank_transfer,cheque,credit_card',
            'new_payment.payment_date' => 'nullable|date',
            'new_payment.reference_no' => 'nullable|string|max:100',
            'return_to' => 'nullable|in:approvals',
            'confirm_after' => 'nullable|boolean',
        ]);

        // Same rule as creation: a 'cash' sale posts its FULL total straight
        // to Cash with nothing tracking any shortfall. This form doesn't
        // collect a payment, so a sale that still owes money can't be
        // (re)labeled 'cash' here - Add Payment is the only real way to
        // settle it, or Credit is the correct term for it either way.
        if ($validated['status'] !== 'draft' && $validated['payment_term'] === 'cash' && (float) $sale->due_amount > 0.01) {
            return back()->with('error', 'This sale still has an outstanding balance, so it cannot be set to Cash. Use Credit instead, or record the remaining payment first via Add Payment.');
        }

        $sale->load('items');
        $before = $this->saleFingerprint($sale);
        $wasDraft = $sale->status === 'draft';

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

                $sale->update([
                    'customer_id' => $validated['customer_id'],
                    'agent_id' => $validated['agent_id'] ?? null,
                    'sale_date' => $validated['sale_date'],
                    'payment_term' => $validated['payment_term'],
                    'status' => $validated['status'],
                    'discount' => $validated['discount'] ?? 0,
                    'discount_type' => $validated['discount_type'] ?? 'fixed',
                    'tax' => $validated['tax'] ?? 0,
                    'shipping_cost' => $validated['shipping_cost'] ?? 0,
                    'notes' => $validated['notes'] ?? null,
                ]);

                // Reverses old stock/accounting, syncs items, re-applies fresh
                // stock/accounting (also recalculates sub_total/total_amount/
                // due_amount from the new items via Sale::calculateTotals()).
                $this->saleService->syncItemsAndUpdate($sale, $itemsData);

                // A payment belongs to the customer the sale belongs to.
                // Customer balances are worked out from sale_payments.customer_id,
                // so when the admin corrects the customer, every payment on the
                // sale has to follow - otherwise the money stays on the old
                // (wrong) customer's account.
                $sale->payments()->where('customer_id', '!=', $sale->customer_id)->update(['customer_id' => $sale->customer_id]);

                $this->applyPendingPaymentEdits($sale, $validated['pending_payments'] ?? [], $validated['new_payment'] ?? []);
            });
        } catch (\Exception $e) {
            // Catches SaleService's defensive throws (insufficient stock, a
            // missing chart-of-accounts entry) - without this they surfaced
            // as a raw 500 error page instead of telling the admin what
            // actually went wrong.
            return back()->with('error', $e->getMessage())->withInput();
        }

        $sale->refresh()->load('items');
        $returnTo = ($validated['return_to'] ?? null) === 'approvals' ? route('admin.approvals.index') : route('admin.sales.index');

        // "Save & Confirm": the corrections are saved, then the sale goes
        // through the normal approval (stock, ledger, the agent's pending
        // payment approved, agent notified) in one step.
        if (!empty($validated['confirm_after']) && $sale->status === 'draft') {
            $error = $this->confirmDraft($sale);
            if ($error) {
                return redirect()->route('admin.sales.edit', array_filter([$sale, 'return' => $validated['return_to'] ?? null]))
                    ->with('error', 'Your corrections were saved, but the sale could not be confirmed: ' . $error);
            }

            return redirect($returnTo)->with('success', 'Sale corrected and confirmed - stock and accounting updated.');
        }

        // Still waiting for approval: tell the agent the admin changed
        // something they submitted, so a different customer/amount doesn't
        // come as a surprise.
        if ($wasDraft && $sale->agent && $before !== $this->saleFingerprint($sale)) {
            $this->fcmService->sendToUser(
                $sale->agent,
                'Sale Corrected',
                "Admin made corrections to your sale #{$sale->invoice_no} before approving it.",
                ['type' => 'sale_updated', 'sale_id' => $sale->id]
            );
        }

        return redirect($returnTo)->with('success', $wasDraft
            ? 'Sale updated - it is still awaiting your approval.'
            : 'Sale updated successfully! Stock and accounting adjusted.');
    }

    /**
     * Applies the admin's corrections to the payments an agent recorded that
     * are still pending: amount / method / date / reference, or "reject" to
     * drop one. Runs inside update()'s transaction AFTER the items were
     * re-synced, so the amounts are checked against the corrected total - a
     * failure throws and the whole edit rolls back.
     */
    private function applyPendingPaymentEdits(Sale $sale, array $edits, array $newPayment = []): void
    {
        $newAmount = round((float) ($newPayment['amount'] ?? 0), 2);
        $addNew = $newAmount > 0 && $sale->status === 'draft';

        if (empty($edits) && !$addNew) {
            return;
        }

        $sale->refresh();

        // A payment the admin records while reviewing a draft (the agent
        // forgot to, or it arrived later): stays pending like the agent's
        // own and is approved together with the sale on Confirm.
        if ($addNew) {
            $this->saleService->recordPayment(
                $sale,
                $newAmount,
                $newPayment['payment_method'] ?? 'cash',
                $newPayment['payment_date'] ?? now()->toDateString(),
                $newPayment['reference_no'] ?? null,
                null,
                'pending',
                Auth::id()
            );
        }

        $kept = 0.0;

        foreach ($sale->payments()->pending()->get() as $payment) {
            $edit = $edits[$payment->id] ?? null;

            if ($edit === null) {
                $kept += (float) $payment->amount;   // not on the form: leave untouched
                continue;
            }

            if (!empty($edit['reject'])) {
                $this->saleService->rejectPayment($payment, Auth::id());
                continue;
            }

            $payment->update(array_filter([
                'amount' => $edit['amount'] ?? null,
                'payment_method' => $edit['payment_method'] ?? null,
                'payment_date' => $edit['payment_date'] ?? null,
            ], fn ($v) => $v !== null && $v !== '') + [
                // the field is always on the form, so an empty value means "cleared"
                'reference_no' => array_key_exists('reference_no', $edit) ? $edit['reference_no'] : $payment->reference_no,
            ]);

            $kept += (float) $payment->amount;
        }

        $due = round((float) $sale->total_amount - (float) $sale->paid_amount, 2);

        if ($kept > $due + 0.005) {
            throw new \Exception(
                'The payments waiting for approval add up to Rs. ' . number_format($kept, 2)
                . ', but only Rs. ' . number_format(max(0, $due), 2)
                . ' is due on this sale after your changes. Lower the payment amount or tick Reject for it.'
            );
        }

        // Same rule the agent app enforces: a Cash sale is paid in full or
        // not at all (a cash sale posts its whole total to Cash on confirm).
        if ($sale->payment_term === 'cash' && $kept > 0.005 && abs($kept - $due) > 0.01) {
            throw new \Exception(
                'A Cash sale must be paid in full or not at all. The sale total is Rs. ' . number_format($due, 2)
                . ' but the agent\'s payment is Rs. ' . number_format($kept, 2)
                . ' - set the payment to the full amount, reject it, or switch the sale to Credit.'
            );
        }
    }

    /**
     * Cheap "did the admin actually change anything the agent submitted?"
     * signature: customer, agent, date, terms, amounts, items and the
     * pending payments.
     */
    private function saleFingerprint(Sale $sale): string
    {
        return md5(json_encode([
            $sale->customer_id, $sale->agent_id, (string) $sale->sale_date, $sale->payment_term,
            (float) $sale->discount, $sale->discount_type, (float) $sale->tax, (float) $sale->shipping_cost, $sale->notes,
            $sale->items->map(fn ($i) => [$i->product_id, (float) $i->quantity, (float) $i->unit_price, (float) $i->discount, (float) $i->tax])->sortBy(0)->values(),
            $sale->payments()->pending()->orderBy('id')->get()->map(fn ($p) => [$p->id, (float) $p->amount, $p->payment_method, (string) $p->payment_date, $p->reference_no])->all(),
        ]));
    }

    public function destroy(Sale $sale)
    {
        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot delete a paid sale!');
        }

        try {
            DB::transaction(function () use ($sale) {
                $this->saleService->reverseForDeletion($sale);
                $sale->delete();
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales.index')
            ->with('success', 'Sale deleted successfully! Stock and accounting reversed.');
    }

    /**
     * Undo an incorrectly-recorded paid/partial sale (e.g. confirmed with an
     * incorrect amount_received, or the wrong payment_term) - reverses
     * payments/commission/accounting and applies the corrected payment_term
     * in one step. Never touches items/stock, so it works even if some of
     * the sold stock has already moved on since. See SaleService::
     * reopenSale() for exactly what gets reversed.
     */
    public function reopen(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'payment_term' => 'required|in:cash,credit',
        ]);

        try {
            $this->saleService->reopenSale($sale, Auth::id(), $validated['payment_term']);
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.sales.show', $sale)
            ->with('success', 'Sale reopened - payments, commission, and accounting were reversed and reposted with the corrected payment term. It is now unpaid; use Add Payment if a real payment needs recording.');
    }

    /**
     * Commits a still-draft sale - flips it to confirmed and runs
     * SaleService::applyStockAndAccounting (a draft sale has no stock/ledger
     * effect yet, see the status gate at the top of that method). Primarily
     * for customer-placed orders with no agent (source=customer_app,
     * agent_id null - "direct" orders per the customer API), which only an
     * admin can act on since no agent owns them, but works for any draft.
     */
    public function confirm(Sale $sale)
    {
        $error = $this->confirmDraft($sale);

        return $error
            ? back()->with('error', $error)
            : back()->with('success', 'Order confirmed - stock and accounting updated.');
    }

    /**
     * The actual confirmation of a draft (shared by the plain Confirm button
     * and the edit form's "Save & Confirm"). Returns an error message, or
     * null when the sale was confirmed.
     */
    private function confirmDraft(Sale $sale): ?string
    {
        if ($sale->status !== 'draft') {
            return 'Only a draft sale can be confirmed.';
        }

        $sale->status = 'confirmed';
        $sale->approved_by = Auth::id();
        $sale->approved_at = now();
        $sale->save();

        try {
            DB::transaction(function () use ($sale) {
                $this->saleService->applyStockAndAccounting($sale);

                // Any payment the agent bundled in at submission time (or
                // added later while this sale still sat as a draft) has
                // been waiting on this same confirm click - approve it now
                // rather than making the admin do a second action for one
                // submission.
                foreach ($sale->payments()->pending()->get() as $payment) {
                    $this->saleService->approvePayment($payment, Auth::id());
                }
            });
        } catch (\Exception $e) {
            // Most likely: stock this draft reserved got sold elsewhere in
            // the meantime. Roll the status change back so the sale stays a
            // confirmable draft instead of getting stuck 'confirmed' with no
            // stock/ledger effect behind it.
            $sale->status = 'draft';
            $sale->approved_by = null;
            $sale->approved_at = null;
            $sale->saveQuietly();
            return $e->getMessage();
        }

        if ($sale->agent) {
            $this->fcmService->sendToUser(
                $sale->agent,
                'Sale Confirmed',
                "Your sale #{$sale->invoice_no} has been confirmed by admin.",
                ['type' => 'sale_confirmed', 'sale_id' => $sale->id]
            );
        }

        return null;
    }

    /**
     * No stock/ledger reversal needed - a draft sale never had either
     * posted in the first place. Any pending payment on it is rejected too,
     * since it was submitted as part of the same order.
     */
    public function reject(Sale $sale)
    {
        if ($sale->status !== 'draft') {
            return back()->with('error', 'Only a draft sale can be rejected.');
        }

        DB::transaction(function () use ($sale) {
            foreach ($sale->payments()->pending()->get() as $payment) {
                $this->saleService->rejectPayment($payment, Auth::id());
            }

            $sale->update(['status' => 'cancelled']);
        });

        if ($sale->agent) {
            $this->fcmService->sendToUser(
                $sale->agent,
                'Sale Rejected',
                "Your sale #{$sale->invoice_no} has been rejected by admin.",
                ['type' => 'sale_rejected', 'sale_id' => $sale->id]
            );
        }

        return back()->with('success', 'Order rejected.');
    }

    public function addPayment(Request $request, Sale $sale)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:' . $sale->due_amount,
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,bank_transfer,cheque,credit_card',
            'reference_no' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'bank_service_charge' => 'nullable|numeric|min:0',
        ]);

        try {
            // Single call: creates the payment row, updates paid/due/recovery%/
            // status, and posts the payment journal entry for credit sales - on
            // every payment, not just the one that finally reaches $0 due. A
            // credit sale paid off in 3 installments needs all 3 to hit the
            // ledger, not just the last one.
            $this->saleService->recordPayment(
                $sale,
                $validated['amount'],
                $validated['payment_method'],
                $validated['payment_date'],
                $validated['reference_no'] ?? null,
                $validated['notes'] ?? null,
                'approved',
                Auth::id(),
                Auth::id()
            );

            Expense::recordBankServiceCharge(
                $validated['bank_service_charge'] ?? 0,
                $validated['payment_date'],
                $validated['payment_method'],
                "Bank charge for payment on Sale #{$sale->invoice_no}"
            );
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment added successfully!');
    }
}