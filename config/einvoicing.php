<?php

/*
| E-invoicing with the tax authorities. Every official invoice and credit note is reported before the customer
| receives it; the authority's number and QR code print on the document.
|
| Credentials are entered per country under Payments & tax → E-invoicing and stored encrypted on the country.
| 'test' mode with no credentials uses the built-in sandbox, which applies the same checks (buyer TIN for
| business customers, standard or exempt tax rates) and prints a "test environment" line on documents.
|
| The EFRIS, FIRS and FNE drivers follow each authority's published approach; field mappings must be confirmed
| against the authority's current technical specification during onboarding (see README, "E-invoicing").
*/

return [
    'systems' => [
        'UG' => ['system' => 'efris', 'label' => 'EFRIS', 'authority' => 'URA', 'number_label' => 'FDN', 'buyer_tin_required' => true,
            'driver' => \App\Services\EInvoicing\Drivers\EfrisDriver::class, 'url' => env('EFRIS_URL')],
        'NG' => ['system' => 'firs', 'label' => 'FIRS e-invoicing (MBS)', 'authority' => 'FIRS', 'number_label' => 'IRN', 'buyer_tin_required' => true,
            'driver' => \App\Services\EInvoicing\Drivers\AccreditedProviderDriver::class, 'url' => env('FIRS_PROVIDER_URL')],
        'CI' => ['system' => 'fne', 'label' => 'FNE', 'authority' => 'DGI', 'number_label' => 'FNE no.', 'buyer_tin_required' => true,
            'driver' => \App\Services\EInvoicing\Drivers\AccreditedProviderDriver::class, 'url' => env('FNE_URL')],
        'CM' => ['system' => null, 'label' => 'To be confirmed', 'authority' => 'DGI', 'number_label' => 'Reference', 'buyer_tin_required' => false,
            'driver' => null, 'url' => null, 'note' => 'Scope to confirm with a local tax adviser before connecting.'],
    ],

    'sandbox_driver' => \App\Services\EInvoicing\Drivers\SandboxDriver::class,

    // Taxpayer number labels printed on documents.
    'tax_id_labels' => ['UG' => 'TIN', 'NG' => 'TIN', 'CM' => 'NIU', 'CI' => 'NCC'],

    'http_timeout' => 30,
];
