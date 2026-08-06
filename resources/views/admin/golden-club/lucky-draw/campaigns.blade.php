@extends('layouts.admin')

@section('title', 'Lucky Draw Campaigns')
@section('page-title', 'Lucky Draw Campaigns')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-trophy text-gray-400 mr-2"></i> All Campaigns
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $campaigns->count() }} total</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.golden-club.lucky-draw.winners') }}"
               class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors duration-200">
                <i class="fas fa-crown mr-1"></i> View Winners
            </a>
            <a href="{{ route('admin.golden-club.lucky-draw.campaigns.create') }}"
               class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors duration-200">
                <i class="fas fa-plus mr-1"></i> New Campaign
            </a>
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Title</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Period</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Entry Rule</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Entries</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Status</th>
                        <th class="text-center text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($campaigns as $campaign)
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="py-3 px-2">
                            <span class="font-medium text-gray-900">{{ $campaign->title }}</span>
                            @if($campaign->minimum_purchase > 0)
                            <p class="text-xs text-gray-500">Min purchase: Rs. {{ number_format($campaign->minimum_purchase, 0) }}</p>
                            @endif
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-600">
                            {{ $campaign->start_date->format('d-m-Y') }} &rarr; {{ $campaign->end_date->format('d-m-Y') }}
                        </td>
                        <td class="py-3 px-2 text-right text-sm text-gray-600">
                            {{ $campaign->entry_formula['entries'] ?? 1 }} entr{{ ($campaign->entry_formula['entries'] ?? 1) == 1 ? 'y' : 'ies' }}
                            / Rs. {{ number_format($campaign->entry_formula['amount'] ?? 0, 0) }}
                        </td>
                        <td class="py-3 px-2 text-right font-medium">{{ number_format($campaign->entries_count) }}</td>
                        <td class="py-3 px-2 text-center">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $campaign->status_color }}">
                                {{ $campaign->status_label }}
                            </span>
                        </td>
                        <td class="py-3 px-2 text-center">
                            <div class="flex items-center justify-center space-x-1">
                                <a href="{{ route('admin.golden-club.lucky-draw.campaigns.edit', $campaign) }}"
                                   class="p-1.5 text-yellow-600 hover:bg-yellow-50 rounded-lg transition-colors duration-200">
                                    <i class="fas fa-edit text-sm"></i>
                                </a>
                                @if($campaign->status == 'active')
                                <form action="{{ route('admin.golden-club.lucky-draw.campaigns.draw-winner', $campaign) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" onclick="return confirm('Draw a random winner for this campaign now?')"
                                            class="px-2.5 py-1.5 bg-purple-600 text-white rounded-lg text-xs font-medium hover:bg-purple-700">
                                        <i class="fas fa-dice mr-1"></i> Draw Winner
                                    </button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="py-8 text-center text-gray-500">No lucky draw campaigns yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
