<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Purchase;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\Expense;
use App\Models\Income;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportController extends Controller
{
    // =============================================
    // 1. EXPORT TO CSV
    // =============================================
    
    public function exportCSV(Request $request)
    {
        $type = $request->type;
        $data = $this->getExportData($type, $request);
        
        $filename = $type . '_' . date('Y-m-d') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];
        
        $callback = function() use ($data, $type) {
            $file = fopen('php://output', 'w');
            
            // Headers
            $headers = $this->getHeaders($type);
            fputcsv($file, $headers);
            
            // Data
            foreach ($data as $row) {
                fputcsv($file, $this->formatRow($row, $type));
            }
            
            fclose($file);
        };
        
        return Response::stream($callback, 200, $headers);
    }
    
    // =============================================
    // 2. EXPORT TO EXCEL (via Maatwebsite)
    // =============================================
    
    public function exportExcel(Request $request)
    {
        $type = $request->type;
        $data = $this->getExportData($type, $request);
        
        return Excel::download(new class($data, $type) implements \Maatwebsite\Excel\Concerns\FromArray, \Maatwebsite\Excel\Concerns\WithHeadings {
            private $data;
            private $type;
            
            public function __construct($data, $type)
            {
                $this->data = $data;
                $this->type = $type;
            }
            
            public function array(): array
            {
                $rows = [];
                foreach ($this->data as $row) {
                    $rows[] = $this->formatRow($row, $this->type);
                }
                return $rows;
            }
            
            public function headings(): array
            {
                return $this->getHeaders($this->type);
            }

            // ✅ Helper methods inside anonymous class
            private function getHeaders($type)
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

            private function formatRow($row, $type)
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
        }, $type . '_' . date('Y-m-d') . '.xlsx');
    }

    // =============================================
    // 3. EXPORT TO PDF
    // =============================================

    public function exportPDF(Request $request)
    {
        $type = $request->type;
        $data = $this->getExportData($type, $request);
        $title = ucfirst(str_replace('_', ' ', $type)) . ' Report';

        $pdf = Pdf::loadView('admin.exports.pdf', [
            'data' => $data,
            'type' => $type,
            'title' => $title,
            'controller' => $this
        ]);

        return $pdf->download($type . '_' . date('Y-m-d') . '.pdf');
    }

    // =============================================
    // 4. GET EXPORT DATA
    // =============================================
    
    public function getExportData($type, $request)
    {
        switch ($type) {
            case 'sales':
                return Sale::with('customer')->orderBy('created_at', 'desc')->get();
                
            case 'purchases':
                return Purchase::with('supplier')->orderBy('created_at', 'desc')->get();
                
            case 'customers':
                return Customer::orderBy('name')->get();
                
            case 'suppliers':
                return Supplier::orderBy('name')->get();
                
            case 'expenses':
                return Expense::with('category')->orderBy('expense_date', 'desc')->get();
                
            case 'incomes':
                return Income::with('category')->orderBy('income_date', 'desc')->get();
                
            case 'agents':
                return User::where('role', 'sales_agent')->orderBy('name')->get();
                
            case 'receivable':
                return Customer::where('balance', '>', 0)->orderBy('balance', 'desc')->get();
                
            default:
                return [];
        }
    }
    
    // =============================================
    // 5. GET HEADERS
    // =============================================
    
    public function getHeaders($type)
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
    
    // =============================================
    // 6. FORMAT ROW
    // =============================================
    
    public function formatRow($row, $type)
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