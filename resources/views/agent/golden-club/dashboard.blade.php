@extends('layouts.agent')

@section('title', 'Golden Club')
@section('page-title', 'Golden Club Dashboard')

@section('content')
<div class="space-y-6">

    <!-- Stats -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">My Customers</p>
                    <p class="text-2xl font-bold text-blue-600 truncate" title="{{ number_format($stats['total_customers']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['total_customers']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Verified</p>
                    <p class="text-2xl font-bold text-green-600 truncate" title="{{ number_format($stats['verified']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['verified']) }}
                    </p>
                    <p class="text-xs text-gray-500">Pending: {{ $stats['pending_verification'] }}</p>
                </div>
                <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-check-circle text-green-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Total Points Issued</p>
                    <p class="text-2xl font-bold text-purple-600 truncate" title="{{ number_format($stats['total_points_issued']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['total_points_issued']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-coins text-purple-600 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow-card p-4">
            <div class="flex items-center justify-between">
                <div class="min-w-0">
                    <p class="text-sm text-gray-500">Lucky Draw Entries</p>
                    <p class="text-2xl font-bold text-yellow-600 truncate" title="{{ number_format($stats['total_lucky_draw_entries']) }}">
                        {{ \App\Helpers\NumberHelper::compact($stats['total_lucky_draw_entries']) }}
                    </p>
                </div>
                <div class="w-12 h-12 bg-yellow-100 rounded-xl flex items-center justify-center flex-shrink-0">
                    <i class="fas fa-trophy text-yellow-600 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Membership Breakdown -->
    <div class="bg-white rounded-xl shadow-card p-4">
        <p class="text-sm font-medium text-gray-700 mb-2"><i class="fas fa-crown text-gray-400 mr-2"></i> Membership Breakdown</p>
        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
            <span><span class="inline-block w-2 h-2 rounded-full bg-gray-400 mr-1"></span> Silver: {{ $stats['silver'] }}</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-yellow-400 mr-1"></span> Gold: {{ $stats['gold'] }}</span>
            <span><span class="inline-block w-2 h-2 rounded-full bg-purple-400 mr-1"></span> Platinum: {{ $stats['platinum'] }}</span>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <a href="{{ route('agent.customers.create') }}"
           class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-user-plus text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold">Register Customer</h3>
                    <p class="text-blue-100 text-sm">Add new customer to club</p>
                </div>
            </div>
        </a>

        <a href="{{ route('agent.golden-club.customers') }}"
           class="bg-gradient-to-r from-purple-600 to-purple-700 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-users text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold">My Customers</h3>
                    <p class="text-purple-100 text-sm">View club members</p>
                </div>
            </div>
        </a>

        <a href="{{ route('agent.golden-club.rewards') }}"
           class="bg-gradient-to-r from-green-600 to-green-700 rounded-xl shadow-lg p-6 text-white hover:shadow-xl transition-shadow duration-200">
            <div class="flex items-center">
                <div class="w-12 h-12 bg-white/20 rounded-xl flex items-center justify-center">
                    <i class="fas fa-gift text-2xl"></i>
                </div>
                <div class="ml-4">
                    <h3 class="text-lg font-semibold">Rewards</h3>
                    <p class="text-green-100 text-sm">Redeem for a customer</p>
                </div>
            </div>
        </a>
    </div>
</div>
@endsection
