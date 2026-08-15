{{--
    Copy/Share action for the Customer/Supplier ledger reports - lets staff
    send the on-screen statement (respecting whatever date filter is
    currently applied) straight to WhatsApp/Messenger/etc. without manually
    retyping figures. Not included on the internal Account Ledger - that one
    has no external contact to share it with.

    Expects: $partyName (string), $partyPhone (nullable string), plus the
    same $rows/$opening_balance/$opening_balance_side/$closing_balance/
    $closing_balance_side/$total_debit/$total_credit/$from_date/$to_date
    already in scope from ReportController::buildPeriodLedger().
--}}
<div class="bg-white rounded-xl shadow-card p-4 flex flex-wrap items-center gap-3"
    x-data="{
        copied: false,
        text() { return document.getElementById('ledgerShareText').value; },
        async copy() {
            const value = this.text();
            try {
                await navigator.clipboard.writeText(value);
            } catch (e) {
                const el = document.getElementById('ledgerShareText');
                el.classList.remove('hidden');
                el.select();
                document.execCommand('copy');
                el.classList.add('hidden');
            }
            this.copied = true;
            setTimeout(() => this.copied = false, 2000);
        },
        async share() {
            if (navigator.share) {
                try {
                    await navigator.share({ title: 'Ledger Statement - {{ addslashes($partyName) }}', text: this.text() });
                    return;
                } catch (e) {
                    // User cancelled the share sheet, or the browser rejected
                    // it - fall back to copy so the click still does something.
                }
            }
            this.copy();
        },
        whatsapp() {
            const phone = {!! $partyPhone ? "'" . preg_replace('/\D/', '', $partyPhone) . "'" : 'null' !!};
            const base = phone ? ('https://wa.me/' + phone) : 'https://wa.me/';
            window.open(base + '?text=' + encodeURIComponent(this.text()), '_blank');
        },
    }">
    <span class="text-sm font-medium text-gray-700"><i class="fas fa-share-alt text-gray-400 mr-1"></i> Share Statement</span>

    <button type="button" @click="whatsapp()"
        class="inline-flex items-center justify-center px-4 py-2 bg-[#25D366] text-white rounded-lg font-medium hover:opacity-90 transition-opacity duration-200">
        <i class="fab fa-whatsapp mr-1.5 text-base"></i> WhatsApp
    </button>

    <button type="button" @click="share()"
        class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors duration-200">
        <i class="fas fa-paper-plane mr-1.5"></i> Share
    </button>

    <button type="button" @click="copy()"
        class="inline-flex items-center justify-center px-4 py-2 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300 transition-colors duration-200">
        <i class="fas mr-1.5" :class="copied ? 'fa-check text-green-600' : 'fa-copy'"></i>
        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
    </button>

    @if(!$partyPhone)
    <span class="text-xs text-gray-400">No phone on file - WhatsApp will ask you to pick a contact.</span>
    @endif
</div>

<textarea id="ledgerShareText" class="hidden" readonly>{{ \App\Helpers\PdfHelper::companyName() }}
Ledger Statement - {{ $partyName }}
@if($from_date || $to_date)Period: {{ $from_date ? \Carbon\Carbon::parse($from_date)->format('d-M-Y') : 'Start' }} to {{ $to_date ? \Carbon\Carbon::parse($to_date)->format('d-M-Y') : 'Today' }}
@else
Period: All Time
@endif

Opening Balance: Rs. {{ number_format(abs($opening_balance), 2) }} {{ $opening_balance_side }}
------------------------------------
@forelse($rows as $row)
{{ \Carbon\Carbon::parse($row['date'])->format('d-M-Y') }} - {{ $row['particulars'] }}{{ $row['reference'] ? ' (' . $row['reference'] . ')' : '' }}
{{ $row['debit'] > 0 ? '  Dr Rs. ' . number_format($row['debit'], 2) : '  Cr Rs. ' . number_format($row['credit'], 2) }} | Balance: Rs. {{ number_format($row['balance_abs'], 2) }} {{ $row['balance_side'] }}
@empty
No transactions in this period.
@endforelse
------------------------------------
Total Debit: Rs. {{ number_format($total_debit, 2) }}
Total Credit: Rs. {{ number_format($total_credit, 2) }}
Closing Balance: Rs. {{ number_format(abs($closing_balance), 2) }} {{ $closing_balance_side }}

Generated via {{ \App\Helpers\PdfHelper::companyName() }} on {{ now()->format('d-M-Y h:i A') }}</textarea>
