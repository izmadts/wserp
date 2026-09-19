<?php

namespace App\Http\Controllers\Api\Agent;

use App\Models\Setting;

/**
 * Business details printed on the invoice PDF an agent shares with a
 * customer (the app builds the PDF itself - it just needs to know whose
 * letterhead to use). Public company info only, nothing sensitive.
 */
class CompanyController extends ApiController
{
    public function show()
    {
        return $this->success([
            // The brand printed on invoices ("IZMA Food"), deliberately not
            // app_name ("IZMA ERP") - that's the name of the internal system,
            // not of the business the customer is buying from. Can be
            // overridden by adding an `invoice_name` setting.
            'name' => Setting::get('invoice_name') ?: 'IZMA Food',
            'phone' => Setting::get('company_phone'),
            'address' => Setting::get('company_address'),
        ]);
    }
}
