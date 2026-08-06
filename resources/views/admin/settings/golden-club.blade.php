@extends('layouts.admin')

@section('title', 'Settings - Golden Club')
@section('page-title', 'Settings')

@section('content')
@include('admin.settings.partials.tabs')

<div class="bg-white rounded-xl shadow-card overflow-hidden max-w-3xl">
    <div class="px-6 py-4 border-b border-gray-200">
        <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-crown text-yellow-500 mr-2"></i> Golden Club Settings</h4>
    </div>
    <div class="p-6">
        <form action="{{ route('admin.settings.golden-club.update') }}" method="POST">
            @csrf
            <div class="space-y-6">

                <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-2">Loyalty Points Formula</h5>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Rs. spent per 1 point
                        <x-help-tooltip>Every fully-paid sale earns points as (sale total ÷ this number). E.g. at 100, a Rs. 10,000 sale earns 100 points. Lower this number to make points easier to earn (more generous); raise it to make them harder to earn. Only applies once a sale is genuinely paid in full - a partially-paid or draft sale earns nothing yet.</x-help-tooltip>
                    </label>
                    <input type="number" step="0.01" name="point_rate" value="{{ old('point_rate', $settings['point_formula']['rate'] ?? 100) }}" required min="1"
                        class="w-full sm:w-1/3 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <hr>

                <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-2">Lucky Draw Formula</h5>
                    <p class="text-xs text-gray-500 mb-2 flex items-center">
                        How many lucky draw entries a paid sale earns automatically, on top of any entries a customer separately redeems points for.
                        <x-help-tooltip>This only awards entries into whichever campaign is currently active (status = Active, today's date within its start/end range) and, if the campaign has its own minimum-purchase requirement, only when the sale meets it too. A reward with type "Lucky Draw Entry" is a separate, independent way to earn entries by spending points - the two don't affect each other.</x-help-tooltip>
                    </p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Purchase amount (Rs.)</label>
                            <input type="number" step="0.01" name="lucky_draw_amount" value="{{ old('lucky_draw_amount', $settings['lucky_draw_formula']['amount'] ?? 20000) }}" required min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">= entries earned</label>
                            <input type="number" name="lucky_draw_entries" value="{{ old('lucky_draw_entries', $settings['lucky_draw_formula']['entries'] ?? 1) }}" required min="1"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <hr>

                <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-2">
                        Membership Thresholds (total lifetime purchase)
                        <x-help-tooltip>A customer's tier is computed live from their lifetime purchase total against these three numbers - it's never manually set. Each threshold must be higher than the one before it (Gold > Silver, Platinum > Gold). Raising a threshold can move customers who already qualified for that tier back down; lowering one can upgrade customers immediately, without a new sale.</x-help-tooltip>
                    </h5>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-medal text-gray-400 mr-1"></i> Silver minimum</label>
                            <input type="number" step="0.01" name="membership_silver_minimum" value="{{ old('membership_silver_minimum', $settings['membership_silver']['minimum'] ?? 100000) }}" required min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-medal text-yellow-500 mr-1"></i> Gold minimum</label>
                            <input type="number" step="0.01" name="membership_gold_minimum" value="{{ old('membership_gold_minimum', $settings['membership_gold']['minimum'] ?? 500000) }}" required min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-medal text-blue-400 mr-1"></i> Platinum minimum</label>
                            <input type="number" step="0.01" name="membership_platinum_minimum" value="{{ old('membership_platinum_minimum', $settings['membership_platinum']['minimum'] ?? 1000000) }}" required min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    @error('membership_gold_minimum')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('membership_platinum_minimum')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <hr>

                @php
                    // A checkbox that's unchecked simply isn't submitted at all, so
                    // old('referral_program_enabled') is null both on a fresh page
                    // load AND after the admin deliberately unchecked it and a
                    // different field failed validation - those two cases must be
                    // told apart via a field that's always present when the form
                    // was actually submitted, or an unrelated error would silently
                    // revert this checkbox to its old server value.
                    $wasResubmitted = old('point_rate') !== null;
                    $referralEnabled = $wasResubmitted ? old('referral_program_enabled') : ($settings['referral_program']['enabled'] ?? true);
                @endphp
                <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-2">Referral Program</h5>
                    <div class="inline-flex items-center gap-2 mb-3">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="referral_program_enabled" value="1" {{ $referralEnabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Enable referral program</span>
                        </label>
                        <x-help-tooltip>When on, a new customer who signs up in the app with a friend's referral code creates a pending referral for that friend. When off, new sign-ups ignore any referral code sent by the app - no new referrals are created. This does NOT affect referrals already pending from before you turned it off - those still convert and pay out normally once that customer's first sale is paid, so nobody who was already promised a bonus loses it.</x-help-tooltip>
                    </div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Points awarded to the referrer
                        <x-help-tooltip>Paid once, automatically, the moment the REFERRED customer's first sale is fully paid (not at sign-up time - an unpaid or never-purchasing referral pays nothing). Goes to the person who shared their code, not the new customer.</x-help-tooltip>
                    </label>
                    <input type="number" step="0.01" name="referral_bonus_points" value="{{ old('referral_bonus_points', $settings['referral_bonus']['points'] ?? 50) }}" required min="0"
                        class="w-full sm:w-1/3 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <hr>

                @php
                    $pointsExpiryEnabled = $wasResubmitted ? old('points_expiry_enabled') : ($settings['points_expiry']['enabled'] ?? false);
                    $tierMonths = $settings['points_expiry']['tier_months'] ?? [];
                @endphp
                <div>
                    <h5 class="text-sm font-semibold text-gray-800 mb-2">Points Expiry</h5>
                    <div class="inline-flex items-center gap-2 mb-3">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="points_expiry_enabled" value="1" {{ $pointsExpiryEnabled ? 'checked' : '' }}
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm font-medium text-gray-700">Enable points expiry</span>
                        </label>
                        <x-help-tooltip>When off (the default), points never expire - a customer keeps every point they've ever earned. When on, each batch of points expires on its own schedule counted from the day it was EARNED, not from today - so turning this on does not suddenly expire anything old; it only starts the clock on points earned from now on. Oldest points are always spent first when a customer redeems a reward, so a customer redeeming regularly rarely loses anything to expiry.</x-help-tooltip>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Default expiry (months)
                                <x-help-tooltip>The flat rule that applies to every tier unless overridden below. E.g. at 12, points earned today expire in exactly 12 months if unspent.</x-help-tooltip>
                            </label>
                            <input type="number" name="points_expiry_default_months" value="{{ old('points_expiry_default_months', $settings['points_expiry']['default_months'] ?? 12) }}" required min="1" max="60"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('points_expiry_default_months') border-red-500 @enderror">
                            @error('points_expiry_default_months')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Warning notice (days before expiry)
                                <x-help-tooltip>How long before points expire to send a customer a heads-up notification, so they have a chance to redeem something before losing them. Sent at most once a week per customer, even if several batches are expiring soon, so nobody gets spammed.</x-help-tooltip>
                            </label>
                            <input type="number" name="points_expiry_warning_days" value="{{ old('points_expiry_warning_days', $settings['points_expiry']['warning_days'] ?? 30) }}" required min="1" max="180"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 @error('points_expiry_warning_days') border-red-500 @enderror">
                            @error('points_expiry_warning_days')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>

                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Per-tier override (optional)
                        <x-help-tooltip>Leave a tier blank to just use the default above. Fill one in to reward that tier with a longer-lasting (or shorter) window - e.g. set Platinum to 18 so your best customers' points last 6 months longer than everyone else's. Only applies to points earned AFTER you set this - a customer's already-earned points keep whatever expiry they were given at the time.</x-help-tooltip>
                    </label>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1"><i class="fas fa-medal text-gray-400 mr-1"></i> Silver (months)</label>
                            <input type="number" name="points_expiry_tier_months_silver" value="{{ old('points_expiry_tier_months_silver', $tierMonths['silver'] ?? null) }}" min="1" max="60" placeholder="Use default"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1"><i class="fas fa-medal text-yellow-500 mr-1"></i> Gold (months)</label>
                            <input type="number" name="points_expiry_tier_months_gold" value="{{ old('points_expiry_tier_months_gold', $tierMonths['gold'] ?? null) }}" min="1" max="60" placeholder="Use default"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1"><i class="fas fa-medal text-blue-400 mr-1"></i> Platinum (months)</label>
                            <input type="number" name="points_expiry_tier_months_platinum" value="{{ old('points_expiry_tier_months_platinum', $tierMonths['platinum'] ?? null) }}" min="1" max="60" placeholder="Use default"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
                    <i class="fas fa-save mr-1"></i> Save Settings
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
