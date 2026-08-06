<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reward;
use App\Models\RedeemedReward;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\LuckyDrawCampaign;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class GoldenClubRewardController extends Controller
{
    public function index()
    {
        $rewards = Reward::withCount('redeemedRewards')->orderBy('title')->get();
        $pendingRedemptions = RedeemedReward::whereIn('status', ['pending', 'approved'])->with('customer', 'reward')->orderByDesc('created_at')->get();

        return view('admin.golden-club.rewards.index', compact('rewards', 'pendingRedemptions'));
    }

    public function create()
    {
        $loyaltyProducts = Product::loyalty()->active()->orderBy('name')->get(['id', 'name', 'image', 'current_stock']);
        $campaigns = LuckyDrawCampaign::orderByDesc('created_at')->get(['id', 'title', 'status']);
        return view('admin.golden-club.rewards.create', compact('loyaltyProducts', 'campaigns'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateReward($request);

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('golden-club/rewards', 'public');
        }

        Reward::create($validated);

        return redirect()->route('admin.golden-club.rewards.index')->with('success', 'Reward created successfully!');
    }

    public function edit(Reward $reward)
    {
        $loyaltyProducts = Product::loyalty()->active()->orderBy('name')->get(['id', 'name', 'image', 'current_stock']);
        $campaigns = LuckyDrawCampaign::orderByDesc('created_at')->get(['id', 'title', 'status']);
        return view('admin.golden-club.rewards.edit', compact('reward', 'loyaltyProducts', 'campaigns'));
    }

    public function update(Request $request, Reward $reward)
    {
        $validated = $this->validateReward($request);

        if ($request->hasFile('image')) {
            if ($reward->image && \Illuminate\Support\Facades\Storage::disk('public')->exists($reward->image)) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($reward->image);
            }
            $validated['image'] = $request->file('image')->store('golden-club/rewards', 'public');
        }

        $reward->update($validated);

        return redirect()->route('admin.golden-club.rewards.index')->with('success', 'Reward updated successfully!');
    }

    /**
     * Shared validation + metadata assembly for store()/update(). A
     * product-linked reward's stock IS the product's current_stock
     * (Reward::isInStock()), so product_id and stock are mutually
     * exclusive on the form - stock is only stored when there's no linked
     * product to defer to. metadata is assembled from flat, type-specific
     * form fields rather than requiring the admin to hand-write JSON.
     */
    private function validateReward(Request $request): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'reward_type' => 'required|in:gift,coupon,product,discount,free_delivery,lucky_draw_entry',
            'product_id' => 'nullable|exists:products,id',
            'eligible_membership_levels' => 'nullable|array',
            'eligible_membership_levels.*' => 'in:silver,gold,platinum',
            'points_required' => 'required|numeric|min:0',
            'stock' => 'nullable|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required|in:active,inactive',
            'metadata_discount_amount' => 'nullable|numeric|min:0',
            'metadata_discount_percent' => 'nullable|numeric|min:0|max:100',
            'metadata_coupon_code' => 'nullable|string|max:50',
            'metadata_entry_count' => 'required_if:reward_type,lucky_draw_entry|nullable|integer|min:1',
            'metadata_campaign_id' => 'nullable|exists:lucky_draw_campaigns,id',
        ]);

        $metadata = [];
        if (in_array($validated['reward_type'], ['coupon', 'discount'])) {
            if (!empty($validated['metadata_discount_amount'])) {
                $metadata['discount_amount'] = (float) $validated['metadata_discount_amount'];
            }
            if (!empty($validated['metadata_discount_percent'])) {
                $metadata['discount_percent'] = (float) $validated['metadata_discount_percent'];
            }
            // 'nullable' rules only populate $validated when the key is
            // actually present in the request - a field left off entirely
            // (not just submitted empty) makes direct array access here an
            // undefined-key error, confirmed live when testing this reward
            // type without a coupon code entered.
            $metadata['coupon_code'] = ($validated['metadata_coupon_code'] ?? null) ?: strtoupper(Str::random(8));
        } elseif ($validated['reward_type'] === 'lucky_draw_entry') {
            $metadata['entry_count'] = (int) $validated['metadata_entry_count'];
            if (!empty($validated['metadata_campaign_id'])) {
                $metadata['campaign_id'] = (int) $validated['metadata_campaign_id'];
            }
        }

        $isProductLinked = in_array($validated['reward_type'], ['gift', 'product']) && !empty($validated['product_id']);

        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'reward_type' => $validated['reward_type'],
            'product_id' => $isProductLinked ? $validated['product_id'] : null,
            'eligible_membership_levels' => !empty($validated['eligible_membership_levels']) ? array_values($validated['eligible_membership_levels']) : null,
            'points_required' => $validated['points_required'],
            'stock' => $isProductLinked ? 0 : ($validated['stock'] ?? 0),
            'metadata' => !empty($metadata) ? $metadata : null,
            'status' => $validated['status'],
        ];
    }

    public function destroy(Reward $reward)
    {
        if ($reward->redeemedRewards()->count() > 0) {
            return back()->with('error', 'Cannot delete a reward that has been redeemed!');
        }

        $reward->delete();

        return redirect()->route('admin.golden-club.rewards.index')->with('success', 'Reward deleted successfully!');
    }

    /**
     * Approve/fulfill a pending redemption (customer already spent the
     * points at redemption time - this is the admin fulfillment step). A
     * product-linked reward decrements the SAME products.current_stock
     * every sale uses (locked, negative-stock guarded, same discipline as
     * every other stock-mutating path in the app) and logs a real
     * StockMovement - a free-text (unlinked) reward keeps using its own
     * simple stock counter, unchanged from before.
     */
    public function approveRedemption(RedeemedReward $redeemedReward)
    {
        try {
            DB::transaction(function () use ($redeemedReward) {
                $reward = $redeemedReward->reward;

                if ($reward->product_id) {
                    $product = Product::where('id', $reward->product_id)->lockForUpdate()->firstOrFail();

                    if ((float) $product->current_stock < 1) {
                        throw new \Exception("Cannot approve: {$product->name} is out of stock.");
                    }

                    $stockBefore = $product->current_stock;
                    $product->current_stock -= 1;
                    $product->save();

                    StockMovement::create([
                        'product_id' => $product->id,
                        'type' => 'out',
                        'reference_type' => 'reward_redemption',
                        'reference_id' => $redeemedReward->id,
                        'quantity' => 1,
                        'unit_price' => $product->purchase_price,
                        'total_price' => round((float) $product->purchase_price, 2),
                        'stock_before' => $stockBefore,
                        'stock_after' => $product->current_stock,
                        'notes' => "Golden Club redemption - {$reward->title}",
                    ]);
                } elseif ($reward->stock > 0) {
                    $reward->decrement('stock');
                }

                $redeemedReward->approve();
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Redemption approved!');
    }

    public function deliverRedemption(RedeemedReward $redeemedReward)
    {
        $redeemedReward->deliver();

        return back()->with('success', 'Redemption marked as delivered!');
    }

    /**
     * Only a pending or approved redemption can be cancelled - previously
     * this had no status guard at all, so it could be called on an
     * already-delivered or already-cancelled row and refund the same
     * points a second time. If it had already been approved, the stock
     * that step deducted is reversed here too, or inventory permanently
     * loses a unit for a reward that was never actually handed over.
     */
    public function cancelRedemption(RedeemedReward $redeemedReward)
    {
        if (!in_array($redeemedReward->status, ['pending', 'approved'])) {
            return back()->with('error', 'Only a pending or approved redemption can be cancelled.');
        }

        DB::transaction(function () use ($redeemedReward) {
            $reward = $redeemedReward->reward;

            if ($redeemedReward->status === 'approved' && $reward) {
                if ($reward->product_id) {
                    $product = Product::where('id', $reward->product_id)->lockForUpdate()->first();
                    if ($product) {
                        $stockBefore = $product->current_stock;
                        $product->current_stock += 1;
                        $product->save();

                        StockMovement::create([
                            'product_id' => $product->id,
                            'type' => 'in',
                            'reference_type' => 'reward_redemption',
                            'reference_id' => $redeemedReward->id,
                            'quantity' => 1,
                            'unit_price' => $product->purchase_price,
                            'total_price' => round((float) $product->purchase_price, 2),
                            'stock_before' => $stockBefore,
                            'stock_after' => $product->current_stock,
                            'notes' => "Golden Club redemption cancelled - {$reward->title}",
                        ]);
                    }
                } else {
                    $reward->increment('stock');
                }
            }

            // Refund the points since the reward is no longer being fulfilled.
            $customer = $redeemedReward->customer;
            $customer->loyalty_points += $redeemedReward->points_used;
            $customer->save();

            $redeemedReward->update(['status' => 'cancelled']);
        });

        return back()->with('success', 'Redemption cancelled and points refunded.');
    }
}
