@extends('layouts.admin')

@section('title', 'Add Reward')
@section('page-title', 'Create New Reward')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden" x-data="{
        rewardType: '{{ old('reward_type', '') }}',
        productId: '{{ old('product_id', '') }}',
        isProductType() { return this.rewardType === 'gift' || this.rewardType === 'product'; },
    }">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
        <h3 class="text-base sm:text-lg font-semibold text-gray-900">
            <i class="fas fa-plus-circle text-blue-600 mr-2"></i> New Reward
        </h3>
    </div>

    <div class="p-4 sm:p-6">
        <form action="{{ route('admin.golden-club.rewards.store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Title -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reward Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-500 @enderror">
                    @error('title')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Reward Type -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reward Type <span class="text-red-500">*</span></label>
                    <select name="reward_type" x-model="rewardType" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('reward_type') border-red-500 @enderror">
                        <option value="">Select Type</option>
                        <option value="gift" {{ old('reward_type') == 'gift' ? 'selected' : '' }}>Gift</option>
                        <option value="product" {{ old('reward_type') == 'product' ? 'selected' : '' }}>Product</option>
                        <option value="coupon" {{ old('reward_type') == 'coupon' ? 'selected' : '' }}>Coupon</option>
                        <option value="discount" {{ old('reward_type') == 'discount' ? 'selected' : '' }}>Discount</option>
                        <option value="free_delivery" {{ old('reward_type') == 'free_delivery' ? 'selected' : '' }}>Free Delivery</option>
                        <option value="lucky_draw_entry" {{ old('reward_type') == 'lucky_draw_entry' ? 'selected' : '' }}>Lucky Draw Entry</option>
                    </select>
                    @error('reward_type')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Status <span class="text-red-500">*</span></label>
                    <select name="status" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('status') border-red-500 @enderror">
                        <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    @error('status')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Points Required -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Points Required <span class="text-red-500">*</span></label>
                    <input type="number" step="0.01" min="0" name="points_required" value="{{ old('points_required', 0) }}" required
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('points_required') border-red-500 @enderror">
                    @error('points_required')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Eligible membership tiers (any reward type can be tier-gated) -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Who Can Redeem This?
                        <x-help-tooltip>Leave every box unchecked to make this reward available to <strong>every</strong> tier. Check one or more to restrict it to exactly those tiers - e.g. check only "Platinum" for an Event Invitation nobody else should see, or check "Gold" and "Platinum" together if both should qualify but Silver shouldn't.</x-help-tooltip>
                    </label>
                    <div class="flex flex-wrap gap-4 px-3 py-2 border border-gray-300 rounded-lg @error('eligible_membership_levels') border-red-500 @enderror">
                        @foreach(['silver' => 'Silver', 'gold' => 'Gold', 'platinum' => 'Platinum'] as $value => $label)
                            <label class="inline-flex items-center gap-1.5 text-sm text-gray-700">
                                <input type="checkbox" name="eligible_membership_levels[]" value="{{ $value }}"
                                    {{ in_array($value, (array) old('eligible_membership_levels', [])) ? 'checked' : '' }}
                                    class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @error('eligible_membership_levels')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Product link (gift/product types only) -->
                <div x-show="isProductType()" x-cloak class="md:col-span-2 bg-blue-50 border border-blue-100 rounded-lg p-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Link to an existing product (optional)</label>
                    <select name="product_id" x-model="productId"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('product_id') border-red-500 @enderror">
                        <option value="">Not linked - enter stock manually below</option>
                        @foreach($loyaltyProducts as $product)
                            <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>{{ $product->name }} ({{ number_format($product->current_stock, 0) }} in stock)</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-600">Linking a product means redeeming this reward uses the SAME stock as regular sales - no separate number to keep in sync. Only products flagged "Loyalty eligible" appear here.</p>
                    @error('product_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Stock (hidden when a product is linked - stock comes from the product instead) -->
                <div x-show="!(isProductType() && productId)" x-cloak>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Stock</label>
                    <input type="number" min="0" name="stock" value="{{ old('stock', 0) }}"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('stock') border-red-500 @enderror">
                    @error('stock')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Coupon/discount metadata -->
                <template x-if="rewardType === 'coupon' || rewardType === 'discount'">
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Discount Amount (Rs.)</label>
                            <input type="number" step="0.01" min="0" name="metadata_discount_amount" value="{{ old('metadata_discount_amount') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">or Discount %</label>
                            <input type="number" step="0.01" min="0" max="100" name="metadata_discount_percent" value="{{ old('metadata_discount_percent') }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Coupon Code</label>
                            <input type="text" name="metadata_coupon_code" value="{{ old('metadata_coupon_code') }}" placeholder="Leave blank to auto-generate"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </template>

                <!-- Lucky draw entry metadata -->
                <template x-if="rewardType === 'lucky_draw_entry'">
                    <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 bg-gray-50 border border-gray-200 rounded-lg p-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Entries Awarded <span class="text-red-500">*</span></label>
                            <input type="number" min="1" name="metadata_entry_count" value="{{ old('metadata_entry_count', 1) }}"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('metadata_entry_count') border-red-500 @enderror">
                            @error('metadata_entry_count')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign</label>
                            <select name="metadata_campaign_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">Whichever campaign is active at redemption</option>
                                @foreach($campaigns as $campaign)
                                    <option value="{{ $campaign->id }}" {{ old('metadata_campaign_id') == $campaign->id ? 'selected' : '' }}>{{ $campaign->title }} ({{ ucfirst($campaign->status) }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </template>

                <!-- Image -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reward Image</label>
                    <input type="file" name="image" accept="image/*"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('image') border-red-500 @enderror">
                    @error('image')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>

                <!-- Description -->
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                    @error('description')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="mt-6 flex flex-wrap items-center gap-3">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-save mr-1"></i> Create Reward
                </button>
                <a href="{{ route('admin.golden-club.rewards.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors duration-200">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
