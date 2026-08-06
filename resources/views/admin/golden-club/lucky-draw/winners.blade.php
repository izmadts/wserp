@extends('layouts.admin')

@section('title', 'Lucky Draw Winners')
@section('page-title', 'Lucky Draw Winners')

@section('content')
<div class="bg-white rounded-xl shadow-card overflow-hidden">
    <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <span class="text-sm font-medium text-gray-700">
                <i class="fas fa-crown text-gray-400 mr-2"></i> All Winners
            </span>
            <span class="ml-2 text-sm text-gray-500">{{ $winners->count() }} total</span>
        </div>
        <a href="{{ route('admin.golden-club.lucky-draw.campaigns') }}"
           class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors duration-200">
            <i class="fas fa-arrow-left mr-1"></i> Back to Campaigns
        </a>
    </div>

    <div class="p-4 sm:p-6">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-200">
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Customer</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Campaign</th>
                        <th class="text-right text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Winning Entries</th>
                        <th class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider py-3 px-2">Drawn On</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($winners as $winner)
                    <tr class="hover:bg-gray-50 transition-colors duration-150">
                        <td class="py-3 px-2">
                            <a href="{{ route('admin.golden-club.customers.show', $winner->customer_id) }}" class="font-medium text-blue-600 hover:underline">
                                {{ $winner->customer->name ?? 'Unknown' }}
                            </a>
                        </td>
                        <td class="py-3 px-2 text-sm text-gray-700">{{ $winner->campaign->title ?? '-' }}</td>
                        <td class="py-3 px-2 text-right">{{ $winner->entry_count }}</td>
                        <td class="py-3 px-2 text-sm text-gray-600">{{ $winner->updated_at->format('d-m-Y') }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="py-8 text-center text-gray-500">No winners drawn yet.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
