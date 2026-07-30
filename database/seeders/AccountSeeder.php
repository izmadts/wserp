<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Account;

class AccountSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Assets (Debit normal balance)
        $cash = Account::create([
            'code' => '1010',
            'name' => 'Cash Account',
            'type' => 'Asset',
            'normal_balance' => 'Debit'
        ]);

        $bank = Account::create([
            'code' => '1020',
            'name' => 'Bank Account',
            'type' => 'Asset',
            'normal_balance' => 'Debit'
        ]);

        $stock = Account::create([
            'code' => '1030',
            'name' => 'Inventory Stock',
            'type' => 'Asset',
            'normal_balance' => 'Debit'
        ]);

        $receivable = Account::create([
            'code' => '1040',
            'name' => 'Accounts Receivable (Customers)',
            'type' => 'Asset',
            'normal_balance' => 'Debit'
        ]);

        // 2. Liabilities (Credit normal balance)
        $payable = Account::create([
            'code' => '2010',
            'name' => 'Accounts Payable (Suppliers)',
            'type' => 'Liability',
            'normal_balance' => 'Credit'
        ]);

        $agent_payable = Account::create([
            'code' => '2020',
            'name' => 'Agent Commission Payable',
            'type' => 'Liability',
            'normal_balance' => 'Credit'
        ]);

        // 3. Equity (Credit normal balance)
        $capital = Account::create([
            'code' => '3010',
            'name' => 'Owner\'s Capital',
            'type' => 'Equity',
            'normal_balance' => 'Credit'
        ]);

        // 4. Revenue (Credit normal balance)
        $sales = Account::create([
            'code' => '4010',
            'name' => 'Sales Revenue',
            'type' => 'Revenue',
            'normal_balance' => 'Credit'
        ]);

        // 5. Expenses (Debit normal balance)
        $purchase = Account::create([
            'code' => '5010',
            'name' => 'Purchase Cost',
            'type' => 'Expense',
            'normal_balance' => 'Debit'
        ]);

        $commission = Account::create([
            'code' => '5020',
            'name' => 'Agent Commission Expense',
            'type' => 'Expense',
            'normal_balance' => 'Debit'
        ]);

        $expense = Account::create([
            'code' => '5030',
            'name' => 'General Expenses',
            'type' => 'Expense',
            'normal_balance' => 'Debit'
        ]);

        $this->command->info('✅ Accounts seeded successfully!');
    }
}
