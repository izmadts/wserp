@extends('layouts.customer')

@section('title', 'Golden Club')
@section('page-title', 'My Golden Club')

@section('content')
<div class="space-y-6">

    <!-- Membership Card -->
    <div class="bg-gradient-to-r from-yellow-400 to-yellow-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm opacity-80">Membership Level</p>
                <p class="text-3xl font-bold">{{ ucfirst($customer->membership_level ?? 'Silver') }}</p>
                <p class="text-sm opacity-80 mt-1">Member since {{ $customer->club_join_date ? $customer->club_join_date->format('d M Y') : 'N/A' }}</p>
            </div>
            <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center">
                @if($customer->membership_level == 'platinum')
                    <i class="fas fa-crown text-4xl"></i>
                @elseif($customer->membership_level == 'gold')
                    <i class="fas fa-star text-4xl"></i>
                @else
                    <i class="fas fa-user text-4xl"></i>
                @endif
            </div>
        </div>
        <div class="mt-4 grid grid-cols-2 gap-2">
            <div class="bg-white/20 rounded-lg p-2 text-center">
                <p class="text-xs opacity-80">Loyalty Points</p>
                <p class="text-xl font-bold">{{ number_format($customer->loyalty_points ?? 0) }}</p>
            </div>
            <div class="bg-white/20 rounded-lg p-2 text-center">
                <p class="text-xs opacity-80">Lucky Draw Entries</p>
                <p class="text-xl font-bold">{{ $customer->lucky_draw_entries ?? 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white rounded-xl shadow-card p-4 text-center">
            <p class="text-sm text-gray-500">Total Purchase</p>
            <p class="text-xl font-bold text-blue-600">Rs. {{ number_format($customer->total_purchase ?? 0, 2) }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4 text-center">
            <p class="text-sm text-gray-500">Lifetime Purchase</p>
            <p class="text-xl font-bold text-purple-600">Rs. {{ number_format($customer->lifetime_purchase ?? 0, 2) }}</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-2 gap-4">
        <a href="{{ route('customer.golden-club.points') }}" 
           class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-lg p-4 text-white hover:shadow-xl transition-shadow duration-200 text-center">
            <i class="fas fa-coins text-2xl block mb-1"></i>
            <span class="text-sm font-semibold">My Points</span>
        </a>
        <a href="{{ route('customer.golden-club.rewards') }}" 
           class="bg-gradient-to-r from-green-600 to-green-700 rounded-xl shadow-lg p-4 text-white hover:shadow-xl transition-shadow duration-200 text-center">
            <i class="fas fa-gift text-2xl block mb-1"></i>
            <span class="text-sm font-semibold">Rewards</span>
        </a>
    </div>

    <!-- Rewards Progress -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-chart-line text-gray-400 mr-2"></i> Next Membership Level
            </h4>
        </div>
        <div class="p-6">
            @php
                $currentLevel = $customer->membership_level ?? 'silver';
                $totalPurchase = $customer->total_purchase ?? 0;
                $nextLevel = '';
                $target = 0;
                $progress = 0;

                if ($currentLevel == 'silver') {
                    $nextLevel = 'Gold';
                    $target = 500000;
                    $progress = min(($totalPurchase / $target) * 100, 100);
                } elseif ($currentLevel == 'gold') {
                    $nextLevel = 'Platinum';
                    $target = 1000000;
                    $progress = min(($totalPurchase / $target) * 100, 100);
                } else {
                    $nextLevel = 'Max Level Achieved!';
                    $target = $totalPurchase;
                    $progress = 100;
                }
            @endphp
            @if($nextLevel != 'Max Level Achieved!')
                <div class="flex justify-between text-sm mb-1">
                    <span>Progress to {{ $nextLevel }}</span>
                    <span>{{ number_format($progress, 1) }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-2.5">
                    <div class="bg-gradient-to-r from-blue-500 to-purple-500 h-2.5 rounded-full" style="width: {{ $progress }}%"></div>
                </div>
                <p class="text-xs text-gray-500 mt-1">Need Rs. {{ number_format($target - $totalPurchase, 2) }} more for {{ $nextLevel }}</p>
            @else
                <p class="text-center text-green-600 font-semibold">🏆 You have reached the highest level!</p>
            @endif
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h4 class="text-lg font-semibold text-gray-900">
                <i class="fas fa-history text-gray-400 mr-2"></i> Recent Transactions
            </h4>
        </div>
        <div class="p-4">
            @if(isset($recentTransactions) && $recentTransactions->count() > 0)
                @foreach($recentTransactions->take(5) as $transaction)
                <div class="border-b border-gray-100 py-2 last:border-0">
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $transaction->type_label }}</p>
                            <p class="text-xs text-gray-500">{{ $transaction->created_at->format('d-m-Y H:i') }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-semibold {{ $transaction->type == 'earn' ? 'text-green-600' : 'text-red-600' }}">
                                {{ $transaction->type == 'earn' ? '+' : '-' }}{{ number_format($transaction->points) }}
                            </p>
                            <p class="text-xs text-gray-500">{{ Str::limit($transaction->remarks, 30) }}</p>
                        </div>
                    </div>
                </div>
                @endforeach
            @else
                <p class="text-center text-gray-500 py-4">No transactions yet</p>
            @endif
        </div>
    </div>
</div>
@endsection