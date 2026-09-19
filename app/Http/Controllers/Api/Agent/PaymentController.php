<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Http\Request;

/**
 * The agent's own payment history - every payment they've submitted against
 * their own invoices, with its approval state. Read-only: submitting a new
 * payment goes through SaleController::addPayment (POST /sales/{sale}/payments).
 */
class PaymentController extends ApiController
{
    public function index(Request $request)
    {
        $agentId = $this->agent()->id;

        $baseQuery = SalePayment::whereHas('sale', fn ($q) => $q->where('agent_id', $agentId));

        $query = (clone $baseQuery)->with('sale.customer');

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $term = trim($request->search);
            $query->whereHas('sale', function ($q) use ($term) {
                $q->where('invoice_no', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%"));
            });
        }

        $payments = $query->orderByDesc('created_at')->paginate($request->input('per_page', 20));

        $items = $payments->getCollection()->map(fn ($p) => [
            'id' => $p->id,
            'sale_id' => $p->sale_id,
            'invoice_no' => $p->sale?->invoice_no,
            'customer_name' => $p->sale?->customer?->name,
            'amount' => (float) $p->amount,
            'payment_date' => optional($p->payment_date)->format('Y-m-d'),
            'payment_method' => $p->payment_method,
            'reference_no' => $p->reference_no,
            'notes' => $p->notes,
            'status' => $p->status,
            'created_at' => optional($p->created_at)->toIso8601String(),
        ])->values();

        $totals = (clone $baseQuery)
            ->selectRaw('status, COUNT(*) as cnt, SUM(amount) as total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $sum = fn (string $status) => (float) ($totals[$status]->total ?? 0);
        $count = fn (string $status) => (int) ($totals[$status]->cnt ?? 0);

        return $this->success($items, 'OK', 200, [
            'current_page' => $payments->currentPage(),
            'last_page' => $payments->lastPage(),
            'per_page' => $payments->perPage(),
            'total' => $payments->total(),
            'summary' => [
                'approved_total' => $sum('approved'),
                'pending_total' => $sum('pending'),
                'rejected_total' => $sum('rejected'),
                'approved_count' => $count('approved'),
                'pending_count' => $count('pending'),
                'rejected_count' => $count('rejected'),
                // Still owed across the agent's live invoices, net of what's
                // already been submitted for approval.
                'outstanding_total' => (float) Sale::where('agent_id', $agentId)
                    ->whereNotIn('status', ['cancelled', 'paid'])
                    ->where('due_amount', '>', 0)
                    ->sum('due_amount'),
            ],
        ]);
    }
}
