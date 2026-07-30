<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StockAdjustmentController extends Controller
{
    public function index()
    {
        $adjustments = StockAdjustment::with('product', 'createdBy')
            ->orderBy('created_at', 'desc')
            ->get();
        return view('admin.inventory.adjustments.index', compact('adjustments'));
    }

    public function create()
    {
        $products = Product::active()->orderBy('name')->get();
        return view('admin.inventory.adjustments.create', compact('products'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'type' => 'required|in:in,out',
            'quantity' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($validated) {
            $product = Product::findOrFail($validated['product_id']);
            
            // =============================================
            // FIX: Use proper decimal handling with bcmath
            // =============================================
            
            // Get current stock with proper decimal
            $oldStock = (float) $product->current_stock;
            $quantity = (float) $validated['quantity'];
            
            // Use bcmath for precise calculations (if available)
            if (function_exists('bcadd')) {
                // Precise decimal operations
                if ($validated['type'] == 'in') {
                    $newStock = (float) bcadd((string) $oldStock, (string) $quantity, 2);
                } else {
                    if ($oldStock < $quantity) {
                        throw new \Exception('Insufficient stock! Available: ' . number_format($oldStock, 2));
                    }
                    $newStock = (float) bcsub((string) $oldStock, (string) $quantity, 2);
                }
            } else {
                // Fallback: Use round to fix floating point
                if ($validated['type'] == 'in') {
                    $newStock = round($oldStock + $quantity, 2);
                } else {
                    if ($oldStock < $quantity) {
                        throw new \Exception('Insufficient stock! Available: ' . number_format($oldStock, 2));
                    }
                    $newStock = round($oldStock - $quantity, 2);
                }
            }

            // Update product stock
            $product->current_stock = $newStock;
            $product->save();

            // Create adjustment record
            StockAdjustment::create([
                'product_id' => $product->id,
                'type' => $validated['type'],
                'quantity' => $quantity,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'reason' => $validated['reason'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Create stock movement
            StockMovement::create([
                'product_id' => $product->id,
                'type' => $validated['type'],
                'reference_type' => 'adjustment',
                'reference_id' => 0,
                'quantity' => $quantity,
                'unit_price' => 0,
                'total_price' => 0,
                'stock_before' => $oldStock,
                'stock_after' => $newStock,
                'notes' => $validated['reason'] ?? 'Stock adjustment'
            ]);
        });

        return redirect()->route('admin.inventory.adjustments.index')
            ->with('success', 'Stock adjusted successfully!');
    }

    public function destroy(StockAdjustment $adjustment)
    {
        $adjustment->delete();
        return back()->with('success', 'Adjustment deleted successfully!');
    }
}