@extends('layouts.admin')

@section('title', 'Rewards Management')
@section('page-title', 'Rewards Management')

@section('content')
<div class="space-y-6">

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-sm text-gray-500">Total Rewards</p>
            <p class="text-2xl font-bold">{{ $rewards->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-sm text-gray-500">Active Rewards</p>
            <p class="text-2xl font-bold text-green-600">{{ $rewards->where('status', 'active')->count() }}</p>
        </div>
        <div class="bg-white rounded-xl shadow-card p-4">
            <p class="text-sm text-gray-500">Pending Redemptions</p>
            <p class="text-2xl font-bold text-purple-600">{{ $pendingRedemptions->count() }}</p>
        </div>
    </div>

    <!-- Pending / Approved Redemptions -->
    @if($pendingRedemptions->count() > 0)
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200">
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-clock text-gray-400 mr-2"></i> Redemptions Awaiting Fulfillment
            </span>
        </div>
        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Customer</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Reward</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Points Used</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Requested</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($pendingRedemptions as $redemption)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="py-3 px-2">
                                <a href="{{ route('admin.golden-club.customers.show', $redemption->customer_id) }}" class="font-medium text-blue-600 hover:underline">
                                    {{ $redemption->customer->name ?? 'Unknown' }}
                                </a>
                            </td>
                            <td class="py-3 px-2 text-sm text-gray-700">{{ $redemption->reward->title ?? 'Reward' }}</td>
                            <td class="py-3 px-2 text-right">{{ number_format($redemption->points_used, 0) }}</td>
                            <td class="py-3 px-2 text-sm text-gray-600">{{ $redemption->created_at->diffForHumans() }}</td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $redemption->status_color }}">
                                    {{ $redemption->status_label }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if($redemption->status == 'pending')
                                    <form action="{{ route('admin.golden-club.redemptions.approve', $redemption) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700">
                                            <i class="fas fa-check mr-1"></i> Approve
                                        </button>
                                    </form>
                                    @elseif($redemption->status == 'approved')
                                    <form action="{{ route('admin.golden-club.redemptions.deliver', $redemption) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="px-2.5 py-1.5 bg-green-600 text-white rounded-lg text-xs font-medium hover:bg-green-700">
                                            <i class="fas fa-truck mr-1"></i> Deliver
                                        </button>
                                    </form>
                                    @endif
                                    <form action="{{ route('admin.golden-club.redemptions.cancel', $redemption) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" onclick="return confirm('Cancel this redemption and refund points?')" class="px-2.5 py-1.5 bg-red-600 text-white rounded-lg text-xs font-medium hover:bg-red-700">
                                            <i class="fas fa-times mr-1"></i> Cancel
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <!-- Rewards Table -->
    <div class="bg-white rounded-xl shadow-card overflow-hidden">
        <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
            <div>
                <span class="text-sm font-medium text-gray-700">
                    <i class="fas fa-gift text-gray-400 mr-2"></i> All Rewards
                </span>
                <span class="ml-2 text-sm text-gray-500">{{ $rewards->count() }} total</span>
            </div>
            <a href="{{ route('admin.golden-club.rewards.create') }}" 
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> Add Reward
            </a>
        </div>

        <div class="p-4 sm:p-6">
            <div class="overflow-x-auto">
                <table class="w-full" id="rewardsTable">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Title</th>
                            <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Type</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Points</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Stock</th>
                            <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Redeemed</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                            <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rewards as $reward)
                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                            <td class="py-3 px-2">
                                <span class="font-medium text-gray-900">{{ $reward->title }}</span>
                            </td>
                            <td class="py-3 px-2">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    {{ $reward->reward_type_label }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-right">{{ number_format($reward->points_required) }}</td>
                            <td class="py-3 px-2 text-right">{{ $reward->stock }}</td>
                            <td class="py-3 px-2 text-right">{{ $reward->redeemedRewards->count() }}</td>
                            <td class="py-3 px-2 text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $reward->status == 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' }}">
                                    {{ ucfirst($reward->status) }}
                                </span>
                            </td>
                            <td class="py-3 px-2 text-center">
                                <div class="flex items-center justify-center space-x-1">
                                    <a href="{{ route('admin.golden-club.rewards.edit', $reward) }}" 
                                       class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                        <i class="fas fa-edit text-sm"></i>
                                    </a>
                                    <form action="{{ route('admin.golden-club.rewards.destroy', $reward) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" onclick="return confirm('Are you sure?')" 
                                                class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors duration-200">
                                            <i class="fas fa-trash text-sm"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection