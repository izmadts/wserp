<?php

namespace App\Http\Controllers\Agent;
use App\Models\User;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Customer;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\AgentCommissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SaleController extends Controller
{
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
            ->orderBy('name')
            ->get();

        $products = Product::active()
            ->where('current_stock', '>', 0)
            ->orderBy('name')
            ->get();
        $agents = User::where('role', 'sales_agent')->get();
        return view('agent.sales.create', compact('customers', 'products', 'agents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
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

            $discountAmount = $validated['discount_type'] == 'percentage'
                ? ($subTotal * ($validated['discount'] ?? 0) / 100)
                : ($validated['discount'] ?? 0);

            $totalAmount = $subTotal - $discountAmount + ($validated['tax'] ?? 0) + ($validated['shipping_cost'] ?? 0);

            // Calculate commission based on agent's rates
            $agent = Auth::user();
            $commissionAmount = 0;

            if ($validated['payment_term'] == 'cash') {
                // Tiered commission for cash sales
                $amount = $totalAmount;
                if ($amount <= 300000) {
                    $rate = 1;
                } elseif ($amount <= 700000) {
                    $rate = 1.5;
                } elseif ($amount <= 1500000) {
                    $rate = 2;
                } else {
                    $rate = 2.5;
                }
                $commissionAmount = ($amount * $rate / 100);
            } else {
                // Credit sales - 1% on recovered amount (will be calculated on payment)
                $commissionAmount = 0;
            }

            $sale = Sale::create([
                'customer_id' => $validated['customer_id'],
                'agent_id' => Auth::id(),
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
                'commission_due_amount' => $commissionAmount,
                'paid_amount' => 0,
                'due_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($itemsData as $itemData) {
                $sale->items()->create($itemData);

                $product = Product::find($itemData['product_id']);
                $oldStock = $product->current_stock;
                $product->current_stock -= $itemData['quantity'];
                $product->save();

                StockMovement::create([
                    'product_id' => $product->id,
                    'type' => 'out',
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'total_price' => $itemData['total_price'],
                    'stock_before' => $oldStock,
                    'stock_after' => $product->current_stock,
                    'notes' => "Sale #{$sale->invoice_no} - Agent: " . Auth::user()->name
                ]);
            }

            // If cash sale, log commission immediately
            if ($validated['payment_term'] == 'cash' && $commissionAmount > 0) {
                AgentCommissionLog::create([
                    'agent_id' => Auth::id(),
                    'sale_id' => $sale->id,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'amount' => $commissionAmount,
                    'commission_rate' => $rate,
                    'commission_type' => 'cash',
                    'description' => "Cash sale commission for #{$sale->invoice_no}",
                    'is_paid' => false,
                ]);
            }

            // Update customer order count
            $customer = Customer::find($validated['customer_id']);
            $customer->incrementOrderCount();

            // If customer becomes active (3+ orders), check for new customer bonus
            if ($customer->order_count >= 3 && $customer->is_active) {
                $this->checkNewCustomerBonus($customer, $sale);
            }

            if ($validated['status'] == 'paid') {
                $sale->markAsPaid();
            }
        });

        return redirect()->route('agent.sales.index')
            ->with('success', 'Sale created successfully!');
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

        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot edit a paid sale!');
        }

        $customers = Customer::where('created_by_agent_id', Auth::id())->active()->orderBy('name')->get();
        $products = Product::active()->orderBy('name')->get();
        $sale->load('items');
        return view('agent.sales.edit', compact('sale', 'customers', 'products'));
    }

    public function update(Request $request, Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot update a paid sale!');
        }

        // Similar to store method but with update logic
        // ... (same as admin sale update with agent-specific validation)
    }

    public function destroy(Sale $sale)
    {
        if ($sale->agent_id != Auth::id()) {
            abort(403, 'Unauthorized access.');
        }

        if ($sale->status == 'paid') {
            return back()->with('error', 'Cannot delete a paid sale!');
        }

        // Reverse stock
        foreach ($sale->items as $item) {
            $product = Product::find($item->product_id);
            $product->current_stock += $item->quantity;
            $product->save();
        }

        $sale->delete();
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
            $sale->updateRecoveryPercentage();

            if ($sale->due_amount <= 0) {
                $sale->status = 'paid';
                $sale->markAsPaid();

                // For credit sales, calculate commission on recovery
                if ($sale->payment_term == 'credit') {
                    $agent = Auth::user();
                    $commissionRate = $agent->commission_rate_credit ?? 1;
                    $commissionAmount = ($sale->paid_amount * $commissionRate / 100);

                    if ($commissionAmount > 0) {
                        $sale->commission_amount = $commissionAmount;
                        $sale->commission_due_amount = $commissionAmount;
                        $sale->save();

                        AgentCommissionLog::create([
                            'agent_id' => Auth::id(),
                            'sale_id' => $sale->id,
                            'reference_type' => 'sale',
                            'reference_id' => $sale->id,
                            'amount' => $commissionAmount,
                            'commission_rate' => $commissionRate,
                            'commission_type' => 'credit',
                            'description' => "Credit sale commission for #{$sale->invoice_no} (Recovered: " . number_format($sale->paid_amount, 2) . ")",
                            'is_paid' => false,
                        ]);
                    }
                }

                // Check recovery bonus (>95% recovery)
                if ($sale->recovery_percentage >= 95) {
                    $bonusAmount = $sale->total_amount * 0.005; // 0.5% bonus
                    if ($bonusAmount > 0) {
                        AgentCommissionLog::create([
                            'agent_id' => Auth::id(),
                            'sale_id' => $sale->id,
                            'reference_type' => 'recovery_bonus',
                            'reference_id' => $sale->id,
                            'amount' => $bonusAmount,
                            'commission_rate' => 0.5,
                            'commission_type' => 'bonus',
                            'description' => "Recovery bonus for #{$sale->invoice_no} ({$sale->recovery_percentage}% recovery)",
                            'is_paid' => false,
                        ]);
                    }
                }
            } else {
                $sale->status = 'partial';
            }

            $sale->save();
        });

        return back()->with('success', 'Payment added successfully!');
    }

    private function checkNewCustomerBonus($customer, $sale)
    {
        // Check if bonus already given
        $existingBonus = AgentCommissionLog::where('agent_id', Auth::id())
            ->where('reference_type', 'new_customer_bonus')
            ->where('reference_id', $customer->id)
            ->first();

        if (!$existingBonus) {
            $bonusAmount = rand(500, 1000); // Random between 500-1000 as per policy

            AgentCommissionLog::create([
                'agent_id' => Auth::id(),
                'sale_id' => $sale->id,
                'reference_type' => 'new_customer_bonus',
                'reference_id' => $customer->id,
                'amount' => $bonusAmount,
                'commission_rate' => 0,
                'commission_type' => 'bonus',
                'description' => "New customer bonus for {$customer->name} (3+ orders, active)",
                'is_paid' => false,
            ]);
        }
    }
}