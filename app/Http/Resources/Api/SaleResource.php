<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    /**
     * Uses the withSum() alias when the list query pre-computed it (avoids an
     * N+1), otherwise falls back to the eager-loaded payments, otherwise a
     * single query.
     */
    private function pendingPaymentsTotal(): float
    {
        if (isset($this->pending_payments_total)) {
            return (float) $this->pending_payments_total;
        }

        if ($this->relationLoaded('payments')) {
            return (float) $this->payments->where('status', 'pending')->sum('amount');
        }

        return (float) $this->payments()->where('status', 'pending')->sum('amount');
    }

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'invoice_no' => $this->invoice_no,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'code' => $this->customer->code,
                'phone' => $this->customer->phone,
            ] : null),
            'sale_date' => optional($this->sale_date)->format('Y-m-d'),
            'payment_term' => $this->payment_term,
            'source' => $this->source,
            'status' => $this->status,
            'status_label' => $this->status_label,
            'status_color' => $this->status_color,
            'sub_total' => (float) $this->sub_total,
            'discount' => (float) $this->discount,
            'discount_type' => $this->discount_type,
            'tax' => (float) $this->tax,
            'shipping_cost' => (float) $this->shipping_cost,
            'total_amount' => (float) $this->total_amount,
            'commission_amount' => (float) $this->commission_amount,
            'commission_paid_amount' => (float) $this->commission_paid_amount,
            'commission_due_amount' => (float) $this->commission_due_amount,
            'recovery_percentage' => (float) $this->recovery_percentage,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            // Money the agent has already submitted that admin hasn't approved
            // yet, and what's genuinely left for the agent to collect after
            // that - the app caps every payment input at collectable_amount so
            // an invoice can never be over-collected.
            'pending_payments_total' => $pendingPayments = $this->pendingPaymentsTotal(),
            'collectable_amount' => in_array($this->status, ['cancelled', 'paid'], true)
                ? 0.0
                : round(max(0, (float) $this->due_amount - $pendingPayments), 2),
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name,
                'product_unit' => $item->product?->unit,
                'product_image' => $item->product?->image,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) $item->discount,
                'tax' => (float) $item->tax,
                'total_price' => (float) $item->total_price,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'amount' => (float) $p->amount,
                'payment_date' => optional($p->payment_date)->format('Y-m-d'),
                'payment_method' => $p->payment_method,
                'reference_no' => $p->reference_no,
                'notes' => $p->notes,
                // A payment an agent submits sits 'pending' (no ledger effect)
                // until admin approves it - without this the app has no way
                // to tell a pending/rejected payment apart from an approved
                // one in the payment history list.
                'status' => $p->status,
            ])),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
