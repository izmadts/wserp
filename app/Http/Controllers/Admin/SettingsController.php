<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\GoldenClubSetting;
use App\Services\CommissionService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function general()
    {
        $settings = [
            'app_name' => Setting::get('app_name', config('app.name')),
            'currency_code' => Setting::get('currency_code', 'PKR'),
            'currency_symbol' => Setting::get('currency_symbol', 'Rs.'),
            'logo' => Setting::get('logo'),
            'favicon' => Setting::get('favicon'),
            'timezone' => Setting::get('timezone', config('app.timezone')),
            'date_format' => Setting::get('date_format', 'd-m-Y'),
            'theme_color' => Setting::get('theme_color', config('themes.default')),
            // Stored as the literal strings "1"/"0" (see updateGeneral()) -
            // matches how the View::composer in AppServiceProvider reads it.
            'dark_mode_enabled' => Setting::get('dark_mode_enabled', '1') === '1',
            // Gates new-account sign-up (Sale Agent web form + Flutter API
            // register endpoints) - see AgentRegistrationController and
            // Api\Agent\AuthController::register(). Does not affect existing
            // accounts or the admin-approval step itself.
            'registration_enabled' => Setting::get('registration_enabled', '1') === '1',
        ];

        // DateTimeZone::listIdentifiers() is PHP's own canonical, always-
        // current list of valid IANA timezone names - avoids hand-maintaining
        // one that drifts out of date, and guarantees whatever is selected
        // is something PHP/Carbon can actually parse.
        $timezones = \DateTimeZone::listIdentifiers();
        $themes = config('themes.presets');

        return view('admin.settings.general', compact('settings', 'timezones', 'themes'));
    }

    public function updateGeneral(Request $request)
    {
        $validated = $request->validate([
            'app_name' => 'required|string|max:100',
            'currency_code' => 'required|string|max:10',
            'currency_symbol' => 'required|string|max:10',
            'timezone' => 'required|string|max:100|timezone',
            'date_format' => 'required|string|max:20',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,svg|max:1024',
            // No 'image' rule here - PHP's getimagesize() (which that rule
            // relies on) doesn't reliably recognize .ico files, which would
            // wrongly reject the single most common real favicon format.
            'favicon' => 'nullable|mimes:ico,png,jpg,jpeg,svg|max:512',
            'theme_color' => 'required|string|in:' . implode(',', array_keys(config('themes.presets'))),
            'dark_mode_enabled' => 'nullable|boolean',
            'registration_enabled' => 'nullable|boolean',
        ]);

        foreach (['app_name', 'currency_code', 'currency_symbol', 'timezone', 'date_format', 'theme_color'] as $key) {
            Setting::set($key, $validated[$key]);
        }
        Setting::set('dark_mode_enabled', $request->boolean('dark_mode_enabled') ? '1' : '0');
        Setting::set('registration_enabled', $request->boolean('registration_enabled') ? '1' : '0');

        if ($request->hasFile('logo')) {
            $oldLogo = Setting::get('logo');
            if ($oldLogo && file_exists(public_path($oldLogo))) {
                unlink(public_path($oldLogo));
            }
            $path = $request->file('logo')->store('uploads/settings', 'public');
            Setting::set('logo', 'storage/' . $path);
        }

        if ($request->hasFile('favicon')) {
            $oldFavicon = Setting::get('favicon');
            if ($oldFavicon && file_exists(public_path($oldFavicon))) {
                unlink(public_path($oldFavicon));
            }
            $path = $request->file('favicon')->store('uploads/settings', 'public');
            Setting::set('favicon', 'storage/' . $path);
        }

        return back()->with('success', 'General settings updated successfully!');
    }

    public function commission()
    {
        $settings = [];
        foreach (CommissionService::defaults() as $key => $default) {
            $settings[$key] = CommissionService::getSetting($key);
        }

        return view('admin.settings.commission', compact('settings'));
    }

    public function updateCommission(Request $request)
    {
        $validated = $request->validate([
            'cash_tiers' => 'required|array|min:1',
            'cash_tiers.*.from' => 'required|numeric|min:0',
            'cash_tiers.*.to' => 'nullable|numeric|gt:cash_tiers.*.from',
            'cash_tiers.*.rate' => 'required|numeric|min:0|max:100',
            'credit_rate' => 'required|numeric|min:0|max:100',
            'new_customer_bonus_amount' => 'required|numeric|min:0',
            'new_customer_bonus_min_orders' => 'required|integer|min:1',
            'recovery_bonus_threshold_pct' => 'required|numeric|min:0|max:100',
            'recovery_bonus_rate_pct' => 'required|numeric|min:0|max:100',
            'target_bonus_tiers' => 'required|array|min:1',
            'target_bonus_tiers.*.achievement_pct' => 'required|numeric|min:0',
            'target_bonus_tiers.*.bonus' => 'required|numeric|min:0',
            'credit_hold_grace_days' => 'required|integer|min:1',
            'enforce_credit_block' => 'nullable|boolean',
            'enforce_credit_limit' => 'nullable|boolean',
            'require_customer_verification' => 'nullable|boolean',
        ]);

        $cashTiers = collect($validated['cash_tiers'])->map(fn ($t) => [
            'from' => (float) $t['from'],
            'to' => $t['to'] !== null && $t['to'] !== '' ? (float) $t['to'] : null,
            'rate' => (float) $t['rate'],
        ])->sortBy('from')->values()->toArray();

        $targetTiers = collect($validated['target_bonus_tiers'])->map(fn ($t) => [
            'achievement_pct' => (float) $t['achievement_pct'],
            'bonus' => (float) $t['bonus'],
        ])->sortBy('achievement_pct')->values()->toArray();

        CommissionService::setSetting('commission.cash_tiers', $cashTiers);
        CommissionService::setSetting('commission.credit_rate', $validated['credit_rate']);
        CommissionService::setSetting('commission.new_customer_bonus_amount', $validated['new_customer_bonus_amount']);
        CommissionService::setSetting('commission.new_customer_bonus_min_orders', $validated['new_customer_bonus_min_orders']);
        CommissionService::setSetting('commission.recovery_bonus_threshold_pct', $validated['recovery_bonus_threshold_pct']);
        CommissionService::setSetting('commission.recovery_bonus_rate_pct', $validated['recovery_bonus_rate_pct']);
        CommissionService::setSetting('commission.target_bonus_tiers', $targetTiers);
        CommissionService::setSetting('commission.credit_hold_grace_days', $validated['credit_hold_grace_days']);
        CommissionService::setSetting('commission.enforce_credit_block', $request->boolean('enforce_credit_block'));
        CommissionService::setSetting('commission.enforce_credit_limit', $request->boolean('enforce_credit_limit'));
        CommissionService::setSetting('commission.require_customer_verification', $request->boolean('require_customer_verification'));

        return back()->with('success', 'Commission & bonus policy updated successfully!');
    }

    public function goldenClub()
    {
        $settings = [
            'point_formula' => GoldenClubSetting::get('point_formula', ['rate' => 100]),
            'lucky_draw_formula' => GoldenClubSetting::get('lucky_draw_formula', ['amount' => 20000, 'entries' => 1]),
            'membership_silver' => GoldenClubSetting::get('membership_silver', ['minimum' => 100000]),
            'membership_gold' => GoldenClubSetting::get('membership_gold', ['minimum' => 500000]),
            'membership_platinum' => GoldenClubSetting::get('membership_platinum', ['minimum' => 1000000]),
            'referral_bonus' => GoldenClubSetting::get('referral_bonus', ['points' => 50]),
            'referral_program' => GoldenClubSetting::get('referral_program', ['enabled' => true]),
            'points_expiry' => GoldenClubSetting::get('points_expiry', [
                'enabled' => false,
                'default_months' => 12,
                'warning_days' => 30,
                'tier_months' => ['silver' => null, 'gold' => null, 'platinum' => null],
            ]),
        ];

        return view('admin.settings.golden-club', compact('settings'));
    }

    public function updateGoldenClub(Request $request)
    {
        $validated = $request->validate([
            'point_rate' => 'required|numeric|min:1',
            'lucky_draw_amount' => 'required|numeric|min:1',
            'lucky_draw_entries' => 'required|integer|min:1',
            'membership_silver_minimum' => 'required|numeric|min:0',
            'membership_gold_minimum' => 'required|numeric|gt:membership_silver_minimum',
            'membership_platinum_minimum' => 'required|numeric|gt:membership_gold_minimum',
            'referral_bonus_points' => 'required|numeric|min:0',
            'points_expiry_default_months' => 'required|integer|min:1|max:60',
            'points_expiry_warning_days' => 'required|integer|min:1|max:180',
            'points_expiry_tier_months_silver' => 'nullable|integer|min:1|max:60',
            'points_expiry_tier_months_gold' => 'nullable|integer|min:1|max:60',
            'points_expiry_tier_months_platinum' => 'nullable|integer|min:1|max:60',
        ]);

        GoldenClubSetting::set('point_formula', ['rate' => (float) $validated['point_rate']], 'Points per Rs. X = 1 point');
        GoldenClubSetting::set('lucky_draw_formula', [
            'amount' => (float) $validated['lucky_draw_amount'],
            'entries' => (int) $validated['lucky_draw_entries'],
        ], 'Entries per Rs. X purchase');
        GoldenClubSetting::set('membership_silver', ['minimum' => (float) $validated['membership_silver_minimum']], 'Silver membership minimum purchase');
        GoldenClubSetting::set('membership_gold', ['minimum' => (float) $validated['membership_gold_minimum']], 'Gold membership minimum purchase');
        GoldenClubSetting::set('membership_platinum', ['minimum' => (float) $validated['membership_platinum_minimum']], 'Platinum membership minimum purchase');
        GoldenClubSetting::set('referral_bonus', ['points' => (float) $validated['referral_bonus_points']], 'Referral bonus points');
        GoldenClubSetting::set('referral_program', ['enabled' => $request->boolean('referral_program_enabled')], 'Whether new sign-ups can be credited with a referral code');
        GoldenClubSetting::set('points_expiry', [
            'enabled' => $request->boolean('points_expiry_enabled'),
            'default_months' => (int) $validated['points_expiry_default_months'],
            'warning_days' => (int) $validated['points_expiry_warning_days'],
            // A blank number input still submits as an empty string, not an
            // absent key, so isset() alone would treat "left blank" as
            // "override to 0 months" - empty() correctly catches both.
            'tier_months' => [
                'silver' => !empty($validated['points_expiry_tier_months_silver']) ? (int) $validated['points_expiry_tier_months_silver'] : null,
                'gold' => !empty($validated['points_expiry_tier_months_gold']) ? (int) $validated['points_expiry_tier_months_gold'] : null,
                'platinum' => !empty($validated['points_expiry_tier_months_platinum']) ? (int) $validated['points_expiry_tier_months_platinum'] : null,
            ],
        ], 'When newly-earned points expire, with optional per-tier overrides');

        return back()->with('success', 'Golden Club settings updated successfully!');
    }
}
