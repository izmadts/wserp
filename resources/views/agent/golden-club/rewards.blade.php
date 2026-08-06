@extends('layouts.agent')

@section('title', 'Golden Club Rewards')
@section('page-title', 'Golden Club Rewards')

@section('content')
<div class="space-y-6" x-data="{ redeemModal: false, rewardId: null, rewardTitle: '', rewardPoints: 0 }">

    <p class="text-sm text-gray-500">
        <i class="fas fa-info-circle mr-1"></i>
        Redeeming submits a request on the customer's behalf - an admin will approve and hand over the reward.
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($rewards as $reward)
        <div class="bg-white rounded-xl shadow-card overflow-hidden">
            @if($reward->image)
            <img src="{{ Storage::url($reward->image) }}" alt="{{ $reward->title }}" class="w-full h-32 object-cover">
            @endif
            <div class="p-4">
                <div class="flex items-start justify-between gap-2">
                    <h4 class="font-semibold text-gray-900">{{ $reward->title }}</h4>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 whitespace-nowrap">
                        {{ $reward->reward_type_label }}
                    </span>
                </div>
                @if($reward->description)
                <p class="text-sm text-gray-500 mt-1">{{ $reward->description }}</p>
                @endif
                <div class="flex items-center justify-between mt-3">
                    <span class="text-lg font-bold text-purple-600">{{ $reward->formatted_points_required }} pts</span>
                    <span class="text-xs text-gray-500">Stock: {{ $reward->stock }}</span>
                </div>
                <button type="button"
                        @click="redeemModal = true; rewardId = {{ $reward->id }}; rewardTitle = '{{ addslashes($reward->title) }}'; rewardPoints = {{ (float) $reward->points_required }}"
                        @if(!$reward->isInStock()) disabled @endif
                        class="mt-3 w-full px-4 py-2 rounded-lg text-sm font-medium transition-colors duration-200
                            {{ $reward->isInStock() ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-gray-200 text-gray-400 cursor-not-allowed' }}">
                    <i class="fas fa-gift mr-1"></i> {{ $reward->isInStock() ? 'Redeem for Customer' : 'Out of Stock' }}
                </button>
            </div>
        </div>
        @empty
        <div class="col-span-full bg-white rounded-xl shadow-card p-8 text-center text-gray-500">
            No active rewards available right now.
        </div>
        @endforelse
    </div>

    <!-- Redeem Modal -->
    <div x-show="redeemModal" x-cloak
         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
         style="display: none;">
        <div @click.outside="redeemModal = false" class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-1">Redeem <span x-text="rewardTitle"></span></h3>
            <p class="text-sm text-gray-500 mb-4">Costs <span x-text="rewardPoints"></span> points. Select the customer to redeem for.</p>

            <form :action="'{{ url('agent/golden-club/rewards') }}/' + rewardId + '/redeem'" method="POST">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-1">Customer <span class="text-red-500">*</span></label>
                <select name="customer_id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Select customer</option>
                    @foreach($customers as $customer)
                    <option value="{{ $customer->id }}" {{ $customer->loyalty_points < 0 ? 'disabled' : '' }}>
                        {{ $customer->name }} ({{ $customer->code }}) &middot; {{ number_format($customer->loyalty_points, 0) }} pts
                    </option>
                    @endforeach
                </select>

                <div class="mt-6 flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 bg-green-600 text-white rounded-lg font-medium hover:bg-green-700">
                        Confirm Redemption
                    </button>
                    <button type="button" @click="redeemModal = false" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
