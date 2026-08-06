@extends('layouts.admin')

@section('title', 'Customer Profile')
@section('page-title', 'Golden Club Customer Profile')

@section('content')
<div class="space-y-6">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('admin.golden-club.customers.index') }}" class="text-sm text-gray-600 hover:text-gray-900">
            <i class="fas fa-arrow-left mr-1"></i> Back to Customers
        </a>
        <div class="flex gap-2">
            <a href="{{ route('admin.customers.edit', $customer) }}" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-medium hover:bg-yellow-700">
                <i class="fas fa-edit mr-1"></i> Edit Customer
            </a>
            @unless($customer->otp_verified)
            <form action="{{ route('admin.golden-club.customers.verify', $customer) }}" method="POST">
                @csrf
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm font-medium hover:bg-green-700">
                    <i class="fas fa-check-double mr-1"></i> Verify Customer
                </button>
            </form>
            @endunless
        </div>
    </div>

    <!-- Profile Header -->
    <div class="bg-white rounded-xl shadow-card p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h2 class="text-xl font-bold text-gray-900">{{ $customer->name }}</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                        {{ $customer->membership_level == 'platinum' ? 'bg-purple-100 text-purple-800' :
                           ($customer->membership_level == 'gold' ? 'bg-yellow-100 text-yellow-800' :
                           'bg-gray-100 text-gray-800') }}">
                        {{ ucfirst($customer->membership_level ?? 'silver') }}
                    </span>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $customer->otp_verified ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                        {{ $customer->otp_verified ? 'Verified' : 'Pending Verification' }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    <code class="text-xs bg-gray-100 px-2 py-0.5 rounded">{{ $customer->code }}</code>
                    @if($customer->phone) &middot; {{ $customer->phone }} @endif
                    @if($customer->email) &middot; {{ $customer->email }} @endif
                </p>
                @if($customer->shop_name)
                <p class="text-sm text-gray-500 mt-1"><i class="fas fa-store mr-1"></i> {{ $customer->shop_name }} @if($customer->category) &middot; {{ $customer->category }} @endif</p>
                @endif
                @if($customer->city || $customer->address)
                <p class="text-sm text-gray-500 mt-1"><i class="fas fa-map-marker-alt mr-1"></i> {{ $customer->address }} {{ $customer->city }}</p>
                @endif
            </div>
            @if($customer->createdByAgent)
            <div class="text-right text-sm text-gray-500">
                <p>Registered by agent</p>
                <p class="font-medium text-gray-900">{{ $customer->createdByAgent->name }}</p>
            </div>
            @endif
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100">
            <div>
                <p class="text-xs text-gray-500">Loyalty Points</p>
                <p class="text-lg font-bold text-purple-600">{{ number_format($customer->loyalty_points, 0) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Lucky Draw Entries</p>
                <p class="text-lg font-bold text-yellow-600">{{ $customer->lucky_draw_entries }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">This Period Purchase</p>
                <p class="text-lg font-bold text-gray-900">Rs. {{ number_format($customer->total_purchase, 2) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Lifetime Purchase</p>
                <p class="text-lg font-bold text-gray-900">Rs. {{ number_format($customer->lifetime_purchase, 2) }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Timeline -->
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-history text-gray-400 mr-2"></i> Timeline</h4>
            </div>
            <div class="p-4">
                @forelse($timeline as $item)
                <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0">
                    <div class="w-8 h-8 rounded-full bg-{{ $item['color'] }}-100 flex items-center justify-center flex-shrink-0">
                        <i class="fas {{ $item['icon'] }} text-{{ $item['color'] }}-600 text-xs"></i>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $item['event'] }}</p>
                        <p class="text-xs text-gray-500">{{ optional($item['date'])->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No history yet</p>
                @endforelse
            </div>
        </div>

        <!-- Loyalty Transactions -->
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-coins text-gray-400 mr-2"></i> Recent Points Activity</h4>
            </div>
            <div class="p-4">
                @forelse($customer->loyaltyTransactions as $txn)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium {{ $txn->type_color }}">{{ $txn->type_label }}</p>
                        <p class="text-xs text-gray-500">{{ $txn->remarks }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold {{ $txn->points >= 0 ? 'text-green-600' : 'text-red-600' }}">
                            {{ $txn->points >= 0 ? '+' : '' }}{{ number_format($txn->points, 0) }}
                        </p>
                        <p class="text-xs text-gray-500">{{ $txn->created_at->diffForHumans() }}</p>
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No points activity yet</p>
                @endforelse
            </div>
        </div>

        <!-- Lucky Draw Entries -->
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-trophy text-gray-400 mr-2"></i> Lucky Draw Entries</h4>
            </div>
            <div class="p-4">
                @forelse($customer->luckyDrawEntries as $entry)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $entry->campaign->title ?? 'Campaign' }}</p>
                        <p class="text-xs text-gray-500">Rs. {{ number_format($entry->purchase_amount, 0) }} purchase</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900">{{ $entry->entry_count }} entries</p>
                        @if($entry->is_winner)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">Winner</span>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No lucky draw entries yet</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Redeemed Rewards -->
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-gift text-gray-400 mr-2"></i> Redeemed Rewards</h4>
            </div>
            <div class="p-4">
                @forelse($customer->redeemedRewards as $redemption)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $redemption->reward->title ?? 'Reward' }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($redemption->points_used, 0) }} points &middot; {{ $redemption->created_at->diffForHumans() }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $redemption->status_color }}">
                        {{ $redemption->status_label }}
                    </span>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No rewards redeemed yet</p>
                @endforelse
            </div>
        </div>

        <!-- Referrals -->
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h4 class="text-lg font-semibold text-gray-900"><i class="fas fa-user-friends text-gray-400 mr-2"></i> Referrals</h4>
            </div>
            <div class="p-4">
                @forelse($customer->referrals as $referral)
                <div class="flex justify-between items-center py-2 border-b border-gray-100 last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-900">{{ $referral->referred_customer }}</p>
                        <p class="text-xs text-gray-500">{{ $referral->referred_phone ?? '-' }}</p>
                    </div>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $referral->status_color }}">
                        {{ $referral->status_label }}
                    </span>
                </div>
                @empty
                <p class="text-center text-gray-500 py-4">No referrals yet</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
