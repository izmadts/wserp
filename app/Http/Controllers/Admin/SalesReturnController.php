<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use App\Models\SalesReturnItem;
use App\Models\Sale;
use App\Models\Customer;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SalesReturnController extends Controller
{
    public function index()
    {
        $returns = SalesReturn::with('customer', 'sale', 'createdBy')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.sales-returns.index', compact('returns'));
    }

    public function create()
    {
        $sales = Sale::where('status', 'paid')
            ->orWhere('status', 'partial')
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.sales-returns.create', compact('sales'));
    }

    public function getSaleDetails($saleId)
    {
        $sale = Sale::with('items.product', 'customer')->findOrFail($saleId);
        return response()->json($sale);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'sale_id' => 'required|exists:sales,id',
            'return_date' => 'required|date',
            'reason' => 'nullable|string',
            'refund_method' => 'required|in:cash,credit,cheque,bank_transfer',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.sale_item_id' => 'required|exists:sale_items,id',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.tax' => 'nullable|numeric|min:0',
            'items.*.total_price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated) {
            $sale = Sale::find($validated['sale_id']);

            // Calculate totals
            $subTotal = 0;
            $totalDiscount = 0;
            $totalTax = 0;
            $itemsData = [];

            foreach ($validated['items'] as $item) {
                $itemTotal = $item['quantity'] * $item['unit_price'];
                $itemDiscount = $item['discount'] ?? 0;
                $itemTax = $item['tax'] ?? 0;
                $totalPrice = $itemTotal - $itemDiscount + $itemTax;

                $subTotal += $totalPrice;
                $totalDiscount += $itemDiscount;
                $totalTax += $itemTax;
                $itemsData[] = $item;
            }

            $totalAmount = $subTotal - $totalDiscount + $totalTax;

            // Create sales return
            $salesReturn = SalesReturn::create([
                'sale_id' => $validated['sale_id'],
                'customer_id' => $sale->customer_id,
                'return_date' => $validated['return_date'],
                'reason' => $validated['reason'] ?? null,
                'sub_total' => $subTotal,
                'discount' => $totalDiscount,
                'tax' => $totalTax,
                'total_amount' => $totalAmount,
                'refund_method' => $validated['refund_method'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Create return items
            foreach ($itemsData as $itemData) {
                $salesReturn->items()->create([
                    'sale_item_id' => $itemData['sale_item_id'],
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'tax' => $itemData['tax'] ?? 0,
                    'total_price' => $itemData['total_price'],
                ]);
            }

            // Update sale status if fully returned
            // (you can add logic to check if sale is fully returned)
        });

        return redirect()->route('admin.sales-returns.index')
            ->with('success', 'Sales return created successfully!');
    }

    public function show(SalesReturn $salesReturn)
    {
        $salesReturn->load('customer', 'sale', 'items.product', 'createdBy');
        return view('admin.sales-returns.show', compact('salesReturn'));
    }

    public function destroy(SalesReturn $salesReturn)
    {
        // Model's deleting event will handle reversing
        $salesReturn->delete();

        return redirect()->route('admin.sales-returns.index')
            ->with('success', 'Sales return deleted successfully!');
    }
}
