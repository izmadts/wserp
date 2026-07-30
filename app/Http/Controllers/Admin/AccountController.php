<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function index()
    {
        $accounts = Account::with('parent')->orderBy('code')->get();
        
        // ✅ Calculate balance for each account
        foreach ($accounts as $account) {
            $account->balance = $account->balance;
        }
        
        return view('admin.accounts.index', compact('accounts'));
    }

    public function create()
    {
        $parents = Account::whereNull('parent_id')->get();
        return view('admin.accounts.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:accounts',
            'name' => 'required|string|max:255',
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'normal_balance' => 'required|in:Debit,Credit',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        Account::create($validated);

        return redirect()->route('admin.accounts.index')
            ->with('success', 'Account created successfully!');
    }

    public function show(Account $account)
    {
        $account->load('children');
        $account->balance = $account->balance;
        
        $transactions = JournalEntry::where('account_id', $account->id)
            ->orderBy('entry_date', 'desc')
            ->limit(20)
            ->get();
        
        return view('admin.accounts.show', compact('account', 'transactions'));
    }

    public function edit(Account $account)
    {
        $parents = Account::whereNull('parent_id')->where('id', '!=', $account->id)->get();
        return view('admin.accounts.edit', compact('account', 'parents'));
    }

    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'code' => ['required', 'string', Rule::unique('accounts')->ignore($account->id)],
            'name' => 'required|string|max:255',
            'type' => 'required|in:Asset,Liability,Equity,Revenue,Expense',
            'normal_balance' => 'required|in:Debit,Credit',
            'parent_id' => 'nullable|exists:accounts,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;

        $account->update($validated);

        return redirect()->route('admin.accounts.index')
            ->with('success', 'Account updated successfully!');
    }

    public function destroy(Account $account)
    {
        if ($account->children()->count() > 0) {
            return back()->with('error', 'Cannot delete account with children!');
        }

        if (JournalEntry::where('account_id', $account->id)->exists()) {
            return back()->with('error', 'Cannot delete account with transactions!');
        }
        
        $account->delete();
        return redirect()->route('admin.accounts.index')
            ->with('success', 'Account deleted successfully!');
    }

    public function toggleStatus(Account $account)
    {
        $account->is_active = !$account->is_active;
        $account->save();
        
        return back()->with('success', 'Account status updated!');
    }
}