<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index()
    {
        $customers = Customer::with('createdByAgent', 'customerGroup')->withCount('sales')->orderBy('name')->get();
        return view('admin.customers.index', compact('customers'));
    }

    public function create()
    {
        $agents = User::where('role', 'sales_agent')->where('is_active', true)->whereNotNull('approved_at')->orderBy('name')->get();
        $customerGroups = CustomerGroup::active()->orderBy('name')->get();
        return view('admin.customers.create', compact('agents', 'customerGroups'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'nullable|string|unique:customers',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|unique:customers',
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'ntn' => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'created_by_agent_id' => 'nullable|exists:users,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;
        $validated['opening_balance'] = $validated['opening_balance'] ?? 0;
        $validated['credit_limit'] = $validated['credit_limit'] ?? 0;
        $validated['credit_days'] = $validated['credit_days'] ?? 0;
        if (!empty($validated['created_by_agent_id'])) {
            $validated['is_agent_customer'] = true;
        } else {
            $validated['is_agent_customer'] = false;
        }
        Customer::create($validated);

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer created successfully!');
    }

    public function show(Customer $customer)
    {
        $customer->load(['sales' => function ($query) {
            $query->orderBy('created_at', 'desc')->limit(10);
        }, 'salePayments']);
        return view('admin.customers.show', compact('customer'));
    }

    public function edit(Customer $customer)
    {
        $agents = User::where('role', 'sales_agent')->where('is_active', true)->whereNotNull('approved_at')->orderBy('name')->get();
        $customerGroups = CustomerGroup::active()->orderBy('name')->get();
        return view('admin.customers.edit', compact('customer', 'agents', 'customerGroups'));
    }

    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', Rule::unique('customers')->ignore($customer->id)],
            'name' => 'required|string|max:255',
            'email' => ['nullable', 'email', Rule::unique('customers')->ignore($customer->id)],
            'phone' => 'nullable|string|max:50',
            'mobile' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'ntn' => 'nullable|string|max:20',
            'opening_balance' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'credit_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'created_by_agent_id' => 'nullable|exists:users,id',
            'customer_group_id' => 'nullable|exists:customer_groups,id',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? false;
        if (!empty($validated['created_by_agent_id'])) {
            $validated['is_agent_customer'] = true;
        } else {
            $validated['is_agent_customer'] = false;
        }

        $customer->update($validated);

        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer updated successfully!');
    }

    public function destroy(Customer $customer)
    {
        if ($customer->sales()->count() > 0) {
            return back()->with('error', 'Cannot delete customer with sales records!');
        }

        $customer->delete();
        return redirect()->route('admin.customers.index')
            ->with('success', 'Customer deleted successfully!');
    }

    public function toggleStatus(Customer $customer)
    {
        $customer->is_active = !$customer->is_active;
        $customer->save();
        return back()->with('success', 'Customer status updated!');
    }
}
