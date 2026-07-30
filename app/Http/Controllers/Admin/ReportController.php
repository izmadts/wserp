<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Account;
use App\Models\JournalEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\User;


class ReportController extends Controller
{

    public function profitLoss(Request $request)
    {
        // Get date range
        $fromDate = $request->from_date ?? date('Y-m-01');
        $toDate = $request->to_date ?? date('Y-m-t');

        // =============================================
        // 1. INCOME (Revenue)
        // =============================================

        // Sales Revenue
        $salesRevenue = Sale::whereBetween('sale_date', [$fromDate, $toDate])
            ->where('status', 'paid')
            ->sum('total_amount');

        // Other Income (from income module)
        $otherIncome = Income::whereBetween('income_date', [$fromDate, $toDate])
            ->sum('amount');

        $totalIncome = $salesRevenue + $otherIncome;

        // =============================================
        // 2. COST OF GOODS SOLD (COGS)
        // =============================================

        // Calculate COGS from purchase items
        $cogs = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->whereBetween('purchases.purchase_date', [$fromDate, $toDate])
            ->whereIn('purchases.status', ['received', 'paid'])
            ->sum(DB::raw('purchase_items.quantity * purchase_items.unit_price'));

        // =============================================
        // 3. GROSS PROFIT
        // =============================================

        $grossProfit = $totalIncome - $cogs;

        // =============================================
        // 4. EXPENSES
        // =============================================

        // Operating Expenses (from expense module)
        $operatingExpenses = Expense::whereBetween('expense_date', [$fromDate, $toDate])
            ->whereIn('status', ['approved', 'paid'])
            ->sum('amount');

        // =============================================
        // 5. NET PROFIT / LOSS
        // =============================================

        $netProfit = $grossProfit - $operatingExpenses;

        // =============================================
        // 6. BREAKDOWN BY CATEGORY
        // =============================================

        // Income by category
        $incomeByCategory = Income::whereBetween('income_date', [$fromDate, $toDate])
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        // Expenses by category
        $expensesByCategory = Expense::whereBetween('expense_date', [$fromDate, $toDate])
            ->whereIn('status', ['approved', 'paid'])
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->with('category')
            ->get();

        // =============================================
        // 7. MONTHLY BREAKDOWN
        // =============================================

        $monthlyData = [];
        $startMonth = date('m', strtotime($fromDate));
        $endMonth = date('m', strtotime($toDate));
        $year = date('Y', strtotime($fromDate));

        for ($month = $startMonth; $month <= $endMonth; $month++) {
            $monthStart = date('Y-m-01', strtotime("$year-$month-01"));
            $monthEnd = date('Y-m-t', strtotime("$year-$month-01"));

            $monthlySales = Sale::whereBetween('sale_date', [$monthStart, $monthEnd])
                ->where('status', 'paid')
                ->sum('total_amount');

            $monthlyExpenses = Expense::whereBetween('expense_date', [$monthStart, $monthEnd])
                ->whereIn('status', ['approved', 'paid'])
                ->sum('amount');

            $monthlyData[] = [
                'month' => date('M', strtotime("$year-$month-01")),
                'income' => $monthlySales,
                'expenses' => $monthlyExpenses,
                'profit' => $monthlySales - $monthlyExpenses,
            ];
        }

        return view('admin.reports.profit-loss', compact(
            'fromDate',
            'toDate',
            'totalIncome',
            'salesRevenue',
            'otherIncome',
            'cogs',
            'grossProfit',
            'operatingExpenses',
            'netProfit',
            'incomeByCategory',
            'expensesByCategory',
            'monthlyData'
        ));
    }
    // =============================================
    // 1. CUSTOMER REPORTS
    // =============================================

    public function customers(Request $request)
    {
        $query = Customer::withCount('sales');

        // Filter by status
        if ($request->status && $request->status != 'all') {
            $query->where('is_active', $request->status == 'active');
        }

        // Filter by city
        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        // Filter by date range
        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $customers = $query->orderBy('name')->get();

        // Summary
        $totalCustomers = $customers->count();
        $activeCustomers = $customers->where('is_active', true)->count();
        $totalSales = $customers->sum('total_sales');
        $totalBalance = $customers->sum('balance');

        return view('admin.reports.customers', compact(
            'customers',
            'totalCustomers',
            'activeCustomers',
            'totalSales',
            'totalBalance'
        ));
    }

    public function customerDetail(Customer $customer)
    {
        $customer->load(['sales' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }, 'salePayments']);

        return view('admin.reports.customer-detail', compact('customer'));
    }
   
    // =============================================
    // 2. SUPPLIER REPORTS
    // =============================================

    /**
     * Suppliers Report
     */
    public function suppliers(Request $request)
    {
        $query = Supplier::withCount('purchases');

        if ($request->status && $request->status != 'all') {
            $query->where('is_active', $request->status == 'active');
        }

        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->from_date) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $suppliers = $query->orderBy('name')->get();

        // Calculate totals for each supplier
        foreach ($suppliers as $supplier) {
            $supplier->total_purchases = $supplier->purchases()->sum('total_amount');
            $supplier->total_paid = $supplier->purchasePayments()->sum('amount');
            $supplier->total_due = $supplier->total_purchases - $supplier->total_paid;
            $supplier->balance = $supplier->opening_balance + $supplier->total_purchases - $supplier->total_paid;
        }

        $totalSuppliers = $suppliers->count();
        $activeSuppliers = $suppliers->where('is_active', true)->count();
        $totalPurchases = $suppliers->sum('total_purchases');
        $totalPaid = $suppliers->sum('total_paid');
        $totalDue = $suppliers->sum('total_due');
        $totalBalance = $suppliers->sum('balance');

        return view('admin.reports.suppliers', compact(
            'suppliers',
            'totalSuppliers',
            'activeSuppliers',
            'totalPurchases',
            'totalPaid',
            'totalDue',
            'totalBalance'
        ));
    }


    /**
     * Supplier Detail
     */
    public function supplierDetail(Supplier $supplier)
    {
        $supplier->load(['purchases' => function ($q) {
            $q->orderBy('created_at', 'desc');
        }, 'purchasePayments']);

        // Calculate financials
        $totalPurchases = $supplier->purchases()->sum('total_amount');
        $totalPaid = $supplier->purchasePayments()->sum('amount');
        $totalDue = $totalPurchases - $totalPaid;
        $balance = $supplier->opening_balance + $totalPurchases - $totalPaid;

        return view('admin.reports.supplier-detail', compact(
            'supplier',
            'totalPurchases',
            'totalPaid',
            'totalDue',
            'balance'
        ));
    }

    // =============================================
    // 3. RECEIVABLE REPORT (Customer Outstanding)
    // =============================================

    public function receivable(Request $request)
    {
        $query = Customer::with('sales', 'salePayments');

        if ($request->min_balance) {
            $query->whereHas('sales', function ($q) {
                $q->havingRaw('SUM(total_amount) - (SELECT COALESCE(SUM(amount), 0) FROM sale_payments WHERE sale_payments.customer_id = customers.id) >= ?', [request('min_balance')]);
            });
        }

        if ($request->city) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        $customers = $query->orderBy('name')->get();

        // ✅ Calculate balance in PHP (not in database)
        $customersWithBalance = $customers->filter(function ($customer) {
            return $customer->balance > 0;
        })->sortByDesc('balance');

        $totalReceivable = $customersWithBalance->sum('balance');
        $totalCustomers = $customersWithBalance->count();
        $avgReceivable = $totalCustomers > 0 ? $totalReceivable / $totalCustomers : 0;

        return view('admin.reports.receivable', compact(
            'customersWithBalance',
            'totalReceivable',
            'totalCustomers',
            'avgReceivable'
        ));
    }

    // =============================================
    // 4. EXPENSE REPORTS
    // =============================================

    public function expenses(Request $request)
    {
        $query = Expense::with('category');

        if ($request->from_date) {
            $query->whereDate('expense_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('expense_date', '<=', $request->to_date);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->status && $request->status != 'all') {
            $query->where('status', $request->status);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();

        $totalExpenses = $expenses->sum('amount');
        $byCategory = $expenses->groupBy('category_id')->map(function ($items) {
            return [
                'category' => $items->first()->category->name ?? 'Uncategorized',
                'total' => $items->sum('amount'),
                'count' => $items->count()
            ];
        });

        $categories = \App\Models\ExpenseCategory::active()->get();

        return view('admin.reports.expenses', compact(
            'expenses',
            'totalExpenses',
            'byCategory',
            'categories'
        ));
    }

    // =============================================
    // 5. INCOME REPORTS
    // =============================================

    public function incomes(Request $request)
    {
        $query = Income::with('category');

        if ($request->from_date) {
            $query->whereDate('income_date', '>=', $request->from_date);
        }
        if ($request->to_date) {
            $query->whereDate('income_date', '<=', $request->to_date);
        }

        if ($request->category_id) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->source && $request->source != 'all') {
            $query->where('source', $request->source);
        }

        $incomes = $query->orderBy('income_date', 'desc')->get();

        $totalIncome = $incomes->sum('amount');
        $byCategory = $incomes->groupBy('category_id')->map(function ($items) {
            return [
                'category' => $items->first()->category->name ?? 'Uncategorized',
                'total' => $items->sum('amount'),
                'count' => $items->count()
            ];
        });

        $categories = \App\Models\IncomeCategory::active()->get();

        return view('admin.reports.incomes', compact(
            'incomes',
            'totalIncome',
            'byCategory',
            'categories'
        ));
    }

    // =============================================
    // 6. AGENT REPORTS (Admin View)
    // =============================================

    public function agents(Request $request)
    {
        $query = User::where('role', 'sales_agent');

        if ($request->status && $request->status != 'all') {
            $query->where('is_active', $request->status == 'active');
        }

        $agents = $query->withCount('sales')->get();

        foreach ($agents as $agent) {
            $agent->total_sales = $agent->sales()->sum('total_amount');
            $agent->total_commission = $agent->sales()->sum('commission_amount');
            $agent->total_customers = $agent->customers()->count();
        }

        return view('admin.reports.agents', compact('agents'));
    }

    public function agentDetail(User $agent)
    {
        if ($agent->role != 'sales_agent') {
            abort(404);
        }

        $agent->load(['sales' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(20);
        }, 'customers', 'commissionLogs']);

        $totalSales = $agent->sales()->sum('total_amount');
        $totalCommission = $agent->sales()->sum('commission_amount');
        $totalCustomers = $agent->customers()->count();

        return view('admin.reports.agent-detail', compact(
            'agent',
            'totalSales',
            'totalCommission',
            'totalCustomers'
        ));
    }

    // =============================================
    // 7. DAILY SUMMARY REPORT
    // =============================================

    public function dailySummary(Request $request)
    {
        $date = $request->date ?? date('Y-m-d');

        $sales = Sale::whereDate('sale_date', $date)->get();
        $purchases = Purchase::whereDate('purchase_date', $date)->get();
        $expenses = Expense::whereDate('expense_date', $date)->whereIn('status', ['approved', 'paid'])->get();
        $incomes = Income::whereDate('income_date', $date)->get();

        $summary = [
            'date' => $date,
            'total_sales' => $sales->sum('total_amount'),
            'sales_count' => $sales->count(),
            'total_purchases' => $purchases->sum('total_amount'),
            'purchases_count' => $purchases->count(),
            'total_expenses' => $expenses->sum('amount'),
            'expenses_count' => $expenses->count(),
            'total_income' => $incomes->sum('amount'),
            'income_count' => $incomes->count(),
            'net_profit' => $sales->sum('total_amount') + $incomes->sum('amount') - $purchases->sum('total_amount') - $expenses->sum('amount'),
        ];

        return view('admin.reports.daily-summary', compact('summary'));
    }

    // =============================================
    // 8. TAX REPORT
    // =============================================

    public function taxReport(Request $request)
    {
        $fromDate = $request->from_date ?? date('Y-m-01');
        $toDate = $request->to_date ?? date('Y-m-t');

        $sales = Sale::whereBetween('sale_date', [$fromDate, $toDate])
            ->where('status', 'paid')
            ->get();

        $purchases = Purchase::whereBetween('purchase_date', [$fromDate, $toDate])
            ->whereIn('status', ['received', 'paid'])
            ->get();

        $salesTax = $sales->sum('tax');
        $purchaseTax = $purchases->sum('tax');
        $netTax = $salesTax - $purchaseTax;

        return view('admin.reports.tax', compact(
            'fromDate',
            'toDate',
            'sales',
            'purchases',
            'salesTax',
            'purchaseTax',
            'netTax'
        ));
    }
    /**
     * Trial Balance Report
     */
    public function trialBalance(Request $request)
    {
        // Get date range
        $fromDate = $request->from_date ?? date('Y-m-01');
        $toDate = $request->to_date ?? date('Y-m-t');

        // =============================================
        // 1. GET ALL ACCOUNTS WITH BALANCES
        // =============================================
        
        $accounts = Account::where('is_active', true)
            ->orderBy('code')
            ->get();

        $trialBalance = [];

        foreach ($accounts as $account) {
            // Calculate total debits for this account
            $totalDebits = JournalEntry::where('account_id', $account->id)
                ->where('type', 'debit')
                ->whereBetween('entry_date', [$fromDate, $toDate])
                ->sum('amount');

            // Calculate total credits for this account
            $totalCredits = JournalEntry::where('account_id', $account->id)
                ->where('type', 'credit')
                ->whereBetween('entry_date', [$fromDate, $toDate])
                ->sum('amount');

            // Calculate net balance
            $balance = 0;
            $balanceType = '';

            if ($account->normal_balance == 'Debit') {
                $balance = $totalDebits - $totalCredits;
                $balanceType = $balance >= 0 ? 'debit' : 'credit';
            } else {
                $balance = $totalCredits - $totalDebits;
                $balanceType = $balance >= 0 ? 'credit' : 'debit';
            }

            // Only include accounts with balance > 0
            if (abs($balance) > 0) {
                $trialBalance[] = [
                    'account' => $account,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type,
                    'normal_balance' => $account->normal_balance,
                    'total_debits' => $totalDebits,
                    'total_credits' => $totalCredits,
                    'balance' => abs($balance),
                    'balance_type' => $balanceType,
                ];
            }
        }

        // =============================================
        // 2. CALCULATE TOTALS
        // =============================================
        
        $totalDebitBalance = collect($trialBalance)
            ->where('balance_type', 'debit')
            ->sum('balance');

        $totalCreditBalance = collect($trialBalance)
            ->where('balance_type', 'credit')
            ->sum('balance');

        // =============================================
        // 3. GROUP BY ACCOUNT TYPE
        // =============================================
        
        $groupedByType = collect($trialBalance)->groupBy('type')->map(function($items) {
            return [
                'debit' => $items->where('balance_type', 'debit')->sum('balance'),
                'credit' => $items->where('balance_type', 'credit')->sum('balance'),
                'count' => $items->count(),
            ];
        });

        return view('admin.reports.trial-balance', compact(
            'fromDate',
            'toDate',
            'trialBalance',
            'totalDebitBalance',
            'totalCreditBalance',
            'groupedByType'
        ));
    }
}