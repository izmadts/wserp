{{--
    Google-Search-Console-style "date range + compare" picker.
    Include with: @include('admin.reports.partials.period-picker', [
        'routeName' => 'admin.reports.business-summary',
        'from' => $curFrom, 'to' => $curTo,
        'compareMode' => $compareMode,       // 'previous_period' | 'previous_year' | 'custom' | 'none'
        'compareFrom' => $prevFrom, 'compareTo' => $prevTo,
        'allowNoCompare' => false,            // profit-loss passes true (compare is opt-in there)
    ])
    On Apply it does a normal GET navigation to $routeName with
    from_date/to_date/compare/compare_from/compare_to query params, which
    every controller using resolveComparePeriod() reads back.
--}}
@php
    $__allowNoCompare = $allowNoCompare ?? false;
@endphp
<div x-data="wserpPeriodPicker({
        action: @js(route($routeName)),
        from: @js($from),
        to: @js($to),
        compareMode: @js($compareMode ?? 'previous_period'),
        compareFrom: @js($compareFrom),
        compareTo: @js($compareTo),
        allowNoCompare: @js($__allowNoCompare),
    })" x-cloak class="relative inline-block text-left" @keydown.escape.window="open=false">
    <button type="button" @click="open = !open"
        class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 shadow-sm">
        <i class="fas fa-calendar-days text-gray-400"></i>
        <span x-text="rangeLabel()"></span>
        <template x-if="compareOn">
            <span class="text-gray-400" x-text="'vs ' + compareLabel()"></span>
        </template>
        <i class="fas fa-chevron-down text-xs text-gray-400"></i>
    </button>

    <div x-show="open" @click.outside="open = false" x-transition
        class="absolute z-30 mt-2 w-[19rem] sm:w-[34rem] bg-white rounded-xl shadow-xl border border-gray-200 flex flex-col sm:flex-row">
        {{-- Quick presets --}}
        <div class="sm:w-40 border-b sm:border-b-0 sm:border-r border-gray-100 p-2 space-y-0.5 max-h-64 overflow-y-auto">
            <template x-for="p in presets" :key="p.key">
                <button type="button" @click="applyPreset(p.key)"
                    class="w-full text-left px-3 py-1.5 rounded-lg text-sm"
                    :class="preset === p.key ? 'bg-blue-50 text-blue-700 font-medium' : 'text-gray-600 hover:bg-gray-50'"
                    x-text="p.label"></button>
            </template>
        </div>

        <div class="flex-1 p-4 space-y-4">
            {{-- Current range --}}
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Period</p>
                <div class="flex items-center gap-2">
                    <input type="date" x-model="from" @change="preset = 'custom'; recalcCompare()" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    <span class="text-gray-400">-</span>
                    <input type="date" x-model="to" @change="preset = 'custom'; recalcCompare()" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                </div>
            </div>

            {{-- Compare --}}
            <div>
                <label class="flex items-center gap-2 text-sm text-gray-700 font-medium mb-2">
                    <input type="checkbox" x-model="compareOn" @change="recalcCompare()" class="rounded border-gray-300">
                    Compare
                </label>
                <div x-show="compareOn" class="space-y-2 pl-1">
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="m in compareOptions" :key="m.key">
                            <button type="button" @click="compareMode = m.key; recalcCompare()"
                                class="px-2.5 py-1 rounded-full text-xs"
                                :class="compareMode === m.key ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                                x-text="m.label"></button>
                        </template>
                    </div>
                    <div x-show="compareMode === 'custom'" class="flex items-center gap-2">
                        <input type="date" x-model="compareFrom" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                        <span class="text-gray-400">-</span>
                        <input type="date" x-model="compareTo" class="w-full px-2 py-1.5 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <p class="text-xs text-gray-400" x-show="compareMode !== 'custom'">
                        <span x-text="compareLabel()"></span>
                    </p>
                </div>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" @click="open = false" class="px-3 py-1.5 text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                <button type="button" @click="apply()" class="px-4 py-1.5 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Apply</button>
            </div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
function wserpPeriodPicker(cfg) {
    const fmt = d => { const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), day = String(d.getDate()).padStart(2, '0'); return `${y}-${m}-${day}`; };
    const parse = s => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
    const addDays = (d, n) => { const r = new Date(d); r.setDate(r.getDate() + n); return r; };
    const startOfMonth = d => new Date(d.getFullYear(), d.getMonth(), 1);
    const endOfMonth = d => new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const niceDate = s => { const d = parse(s); return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' }); };

    return {
        open: false,
        preset: 'custom',
        from: cfg.from,
        to: cfg.to,
        compareOn: cfg.compareMode !== 'none',
        compareMode: cfg.compareMode === 'none' ? 'previous_period' : cfg.compareMode,
        compareFrom: cfg.compareFrom || '',
        compareTo: cfg.compareTo || '',
        allowNoCompare: cfg.allowNoCompare,
        presets: [
            { key: 'today', label: 'Today' },
            { key: 'yesterday', label: 'Yesterday' },
            { key: 'last7', label: 'Last 7 days' },
            { key: 'last28', label: 'Last 28 days' },
            { key: 'thisMonth', label: 'This month' },
            { key: 'lastMonth', label: 'Last month' },
            { key: 'last3m', label: 'Last 3 months' },
            { key: 'last6m', label: 'Last 6 months' },
            { key: 'last12m', label: 'Last 12 months' },
            { key: 'custom', label: 'Custom range' },
        ],
        compareOptions: [
            { key: 'previous_period', label: 'Previous period' },
            { key: 'previous_year', label: 'Same period last year' },
            { key: 'custom', label: 'Custom' },
        ],

        rangeLabel() {
            if (!this.from || !this.to) return 'Select dates';
            return this.from === this.to ? niceDate(this.from) : niceDate(this.from) + ' – ' + niceDate(this.to);
        },
        compareLabel() {
            if (!this.compareFrom || !this.compareTo) return '';
            return niceDate(this.compareFrom) + ' – ' + niceDate(this.compareTo);
        },
        applyPreset(key) {
            this.preset = key;
            if (key === 'custom') return;
            const today = new Date();
            let from, to;
            switch (key) {
                case 'today': from = to = today; break;
                case 'yesterday': from = to = addDays(today, -1); break;
                case 'last7': from = addDays(today, -6); to = today; break;
                case 'last28': from = addDays(today, -27); to = today; break;
                case 'thisMonth': from = startOfMonth(today); to = today; break;
                case 'lastMonth': { const lm = new Date(today.getFullYear(), today.getMonth() - 1, 1); from = startOfMonth(lm); to = endOfMonth(lm); break; }
                case 'last3m': from = addDays(today, -89); to = today; break;
                case 'last6m': from = addDays(today, -179); to = today; break;
                case 'last12m': from = addDays(today, -364); to = today; break;
                default: from = today; to = today;
            }
            this.from = fmt(from);
            this.to = fmt(to);
            this.recalcCompare();
        },
        recalcCompare() {
            if (!this.compareOn || this.compareMode === 'custom' || !this.from || !this.to) return;
            const from = parse(this.from), to = parse(this.to);
            const days = Math.round((to - from) / 86400000) + 1;
            if (this.compareMode === 'previous_year') {
                const cFrom = new Date(from.getFullYear() - 1, from.getMonth(), from.getDate());
                const cTo = new Date(to.getFullYear() - 1, to.getMonth(), to.getDate());
                this.compareFrom = fmt(cFrom); this.compareTo = fmt(cTo);
            } else {
                const cTo = addDays(from, -1);
                const cFrom = addDays(cTo, -(days - 1));
                this.compareFrom = fmt(cFrom); this.compareTo = fmt(cTo);
            }
        },
        apply() {
            this.recalcCompare();
            const p = new URLSearchParams();
            p.set('from_date', this.from);
            p.set('to_date', this.to);
            p.set('compare', this.compareOn ? this.compareMode : 'none');
            if (this.compareOn && this.compareFrom && this.compareTo) {
                p.set('compare_from', this.compareFrom);
                p.set('compare_to', this.compareTo);
            }
            window.location.href = cfg.action + '?' + p.toString();
        },
    };
}
</script>
@endpush
@endonce
