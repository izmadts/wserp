<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;

/**
 * Everything submitted by a sales agent (app or web portal) or a customer
 * through the mandi API lands here for review before it has any stock/ledger
 * effect - see Api\Agent\SaleController, Agent\SaleController, and
 * Api\Customer\OrderController, none of which can post directly any more.
 */
class ApprovalController extends Controller
{
    protected $saleService;

    public function __construct(SaleService $saleService)
    {
        $this->saleService = $saleService;
    }

    public function index()
    {
        $pendingSales = Sale::where('status', 'draft')
            ->with('customer', 'agent', 'createdBy', 'payments')
            ->orderByDesc('created_at')
            ->get();

        // Payments still tied to a draft sale are resolved together with
        // that sale's own Confirm/Reject action (see Admin\SaleController::
        // confirm/reject), so they're excluded here to avoid listing the
        // same pending item twice.
        $pendingPayments = SalePayment::where('status', 'pending')
            ->whereHas('sale', fn ($q) => $q->where('status', '!=', 'draft'))
            ->with('sale.customer', 'createdBy')
            ->orderByDesc('created_at')
            ->get();

        return view('admin.approvals.index', compact('pendingSales', 'pendingPayments'));
    }

    public function approvePayment(SalePayment $payment)
    {
        try {
            $this->saleService->approvePayment($payment, Auth::id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment approved and posted to the ledger.');
    }

    public function rejectPayment(SalePayment $payment)
    {
        try {
            $this->saleService->rejectPayment($payment, Auth::id());
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment rejected.');
    }
}
