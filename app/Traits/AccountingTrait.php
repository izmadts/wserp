<?php

namespace App\Traits;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

trait AccountingTrait
{
    /**
     * Post double entry accounting
     */
    public function postDoubleEntry($description, $entries, $referenceType, $referenceId)
    {
        DB::transaction(function () use ($description, $entries, $referenceType, $referenceId) {
            $totalDebit = 0;
            $totalCredit = 0;

            foreach ($entries as $entry) {
                if (!isset($entry['account_id']) || !isset($entry['type']) || !isset($entry['amount'])) {
                    throw new \Exception('Invalid entry format: missing account_id, type, or amount');
                }

                if ($entry['type'] == 'debit') {
                    $totalDebit += $entry['amount'];
                } else if ($entry['type'] == 'credit') {
                    $totalCredit += $entry['amount'];
                }

                JournalEntry::create([
                    'account_id' => $entry['account_id'],
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'description' => $description,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'entry_date' => now()->toDateString(),
                ]);
            }

            // Verify accounting equation
            if (round($totalDebit, 2) != round($totalCredit, 2)) {
                throw new \Exception("Accounting equation violated: Debits ($totalDebit) do not equal Credits ($totalCredit)");
            }
        });
    }
}
