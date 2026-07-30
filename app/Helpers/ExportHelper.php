<?php

namespace App\Helpers;

class ExportHelper
{
    public static function getHeaders($type)
    {
        $headers = [
            'sales' => ['Invoice No', 'Customer', 'Date', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status'],
            'purchases' => ['Invoice No', 'Supplier', 'Date', 'Total Amount', 'Paid Amount', 'Due Amount', 'Status'],
            'customers' => ['Code', 'Name', 'Email', 'Phone', 'City', 'Total Sales', 'Balance', 'Status'],
            'suppliers' => ['Code', 'Name', 'Email', 'Phone', 'City', 'Total Purchases', 'Balance', 'Status'],
            'expenses' => ['Expense No', 'Title', 'Category', 'Amount', 'Date', 'Payment Method', 'Status'],
            'incomes' => ['Income No', 'Title', 'Category', 'Amount', 'Date', 'Payment Method', 'Source'],
            'agents' => ['Name', 'Email', 'Phone', 'City', 'Total Customers', 'Total Sales', 'Total Commission', 'Status'],
            'receivable' => ['Code', 'Customer Name', 'Phone', 'City', 'Total Sales', 'Total Paid', 'Balance'],
        ];
        
        return $headers[$type] ?? ['Data'];
    }
    
    public static function formatRow($row, $type)
    {
        switch ($type) {
            case 'sales':
                return [
                    $row->invoice_no,
                    $row->customer->name ?? 'N/A',
                    $row->sale_date->format('d-m-Y'),
                    number_format($row->total_amount, 2),
                    number_format($row->paid_amount, 2),
                    number_format($row->due_amount, 2),
                    $row->status_label,
                ];
                
            case 'purchases':
                return [
                    $row->invoice_no,
                    $row->supplier->name ?? 'N/A',
                    $row->purchase_date->format('d-m-Y'),
                    number_format($row->total_amount, 2),
                    number_format($row->paid_amount, 2),
                    number_format($row->due_amount, 2),
                    ucfirst($row->status),
                ];
                
            case 'customers':
                return [
                    $row->code,
                    $row->name,
                    $row->email ?? '',
                    $row->phone ?? '',
                    $row->city ?? '',
                    number_format($row->total_sales, 2),
                    number_format($row->balance, 2),
                    $row->is_active ? 'Active' : 'Inactive',
                ];
                
            case 'suppliers':
                return [
                    $row->code,
                    $row->name,
                    $row->email ?? '',
                    $row->phone ?? '',
                    $row->city ?? '',
                    number_format($row->total_purchases, 2),
                    number_format($row->balance, 2),
                    $row->is_active ? 'Active' : 'Inactive',
                ];
                
            case 'expenses':
                return [
                    $row->expense_no,
                    $row->title,
                    $row->category->name ?? 'Uncategorized',
                    number_format($row->amount, 2),
                    $row->expense_date->format('d-m-Y'),
                    $row->payment_method_label,
                    $row->status_label,
                ];
                
            case 'incomes':
                return [
                    $row->income_no,
                    $row->title,
                    $row->category->name ?? 'Uncategorized',
                    number_format($row->amount, 2),
                    $row->income_date->format('d-m-Y'),
                    $row->payment_method_label,
                    $row->source_label,
                ];
                
            case 'agents':
                return [
                    $row->name,
                    $row->email,
                    $row->phone ?? '',
                    $row->city ?? '',
                    $row->customers()->count(),
                    number_format($row->sales()->sum('total_amount'), 2),
                    number_format($row->sales()->sum('commission_amount'), 2),
                    $row->is_active ? 'Active' : 'Inactive',
                ];
                
            case 'receivable':
                return [
                    $row->code,
                    $row->name,
                    $row->phone ?? '',
                    $row->city ?? '',
                    number_format($row->total_sales, 2),
                    number_format($row->total_paid, 2),
                    number_format($row->balance, 2),
                ];
                
            default:
                return (array) $row;
        }
    }
}