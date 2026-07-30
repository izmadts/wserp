<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationItem;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankReconciliationController extends Controller
{
    public function index()
    {
        $reconciliations = BankReconciliation::with('account', 'createdBy')
            ->orderBy('statement_date', 'desc')
            ->get();
        
        return view('admin.bank-reconciliations.index', compact('reconciliations'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['Asset'])
            ->orderBy('name')
            ->get();
        
        return view('admin.bank-reconciliations.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Get system balance for this account
        $systemBalance = $this->getAccountBalance($validated['account_id']);

        DB::transaction(function () use ($validated, $systemBalance) {
            $reconciliation = BankReconciliation::create([
                'account_id' => $validated['account_id'],
                'statement_date' => $validated['statement_date'],
                'statement_balance' => $validated['statement_balance'],
                'system_balance' => $systemBalance,
                'difference' => $systemBalance - $validated['statement_balance'],
                'status' => 'pending',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Add reconciliation items from journal entries
            $this->addReconciliationItems($reconciliation);
        });

        return redirect()->route('admin.bank-reconciliations.index')
            ->with('success', 'Bank reconciliation created successfully!');
    }

    public function show(BankReconciliation $bankReconciliation)
    {
        $bankReconciliation->load('account', 'items', 'createdBy');
        return view('admin.bank-reconciliations.show', compact('bankReconciliation'));
    }

    public function edit(BankReconciliation $bankReconciliation)
    {
        if ($bankReconciliation->status == 'reconciled') {
            return back()->with('error', 'Cannot edit a reconciled reconciliation!');
        }

        $accounts = Account::where('is_active', true)
            ->whereIn('type', ['Asset'])
            ->orderBy('name')
            ->get();
        
        return view('admin.bank-reconciliations.edit', compact('bankReconciliation', 'accounts'));
    }

    public function update(Request $request, BankReconciliation $bankReconciliation)
    {
        if ($bankReconciliation->status == 'reconciled') {
            return back()->with('error', 'Cannot update a reconciled reconciliation!');
        }

        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'statement_date' => 'required|date',
            'statement_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        $systemBalance = $this->getAccountBalance($validated['account_id']);

        $bankReconciliation->update([
            'account_id' => $validated['account_id'],
            'statement_date' => $validated['statement_date'],
            'statement_balance' => $validated['statement_balance'],
            'system_balance' => $systemBalance,
            'difference' => $systemBalance - $validated['statement_balance'],
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update reconciliation items
        $bankReconciliation->items()->delete();
        $this->addReconciliationItems($bankReconciliation);

        return redirect()->route('admin.bank-reconciliations.index')
            ->with('success', 'Bank reconciliation updated successfully!');
    }

    public function destroy(BankReconciliation $bankReconciliation)
    {
        if ($bankReconciliation->status == 'reconciled') {
            return back()->with('error', 'Cannot delete a reconciled reconciliation!');
        }

        $bankReconciliation->delete();
        return redirect()->route('admin.bank-reconciliations.index')
            ->with('success', 'Bank reconciliation deleted successfully!');
    }

    public function reconcile(Request $request, BankReconciliation $bankReconciliation)
    {
        if ($bankReconciliation->status == 'reconciled') {
            return back()->with('error', 'Already reconciled!');
        }

        $validated = $request->validate([
            'adjusted_balance' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($bankReconciliation, $validated) {
            $bankReconciliation->update([
                'statement_balance' => $validated['adjusted_balance'],
                'difference' => $bankReconciliation->system_balance - $validated['adjusted_balance'],
                'status' => 'reconciled',
                'notes' => $validated['notes'] ?? $bankReconciliation->notes,
                'reconciled_at' => now(),
            ]);

            // Mark all items as cleared
            $bankReconciliation->items()->update(['status' => 'cleared']);
        });

        return redirect()->route('admin.bank-reconciliations.index')
            ->with('success', 'Bank reconciliation completed successfully!');
    }

    private function getAccountBalance($accountId)
    {
        $debits = JournalEntry::where('account_id', $accountId)
            ->where('type', 'debit')
            ->sum('amount');

        $credits = JournalEntry::where('account_id', $accountId)
            ->where('type', 'credit')
            ->sum('amount');

        return $debits - $credits;
    }

    private function addReconciliationItems($reconciliation)
    {
        $accountId = $reconciliation->account_id;
        
        // Get all journal entries for this account
        $entries = JournalEntry::where('account_id', $accountId)
            ->whereBetween('entry_date', [now()->subMonths(3), $reconciliation->statement_date])
            ->orderBy('entry_date', 'asc')
            ->get();

        foreach ($entries as $entry) {
            $type = $entry->type == 'debit' ? 'deposit' : 'withdrawal';
            
            BankReconciliationItem::create([
                'reconciliation_id' => $reconciliation->id,
                'type' => $type,
                'transaction_date' => $entry->entry_date,
                'description' => $entry->description,
                'amount' => $entry->amount,
                'status' => 'uncleared',
                'reference_no' => $entry->reference_type . '#' . $entry->reference_id,
            ]);
        }
    }
}