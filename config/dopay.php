<?php

/*
|--------------------------------------------------------------------------
| Dopay CRM business rules
|--------------------------------------------------------------------------
| Everything here comes from the finance policy templates (Appendices A–K).
| Change policy here (or later from Settings), not in code.
*/

return [

    'base_currency' => 'USD',

    // Invoice / receipt / payment / expense numbering. Tokens: {CC} ISO country, {DOC} document code, {YYYY}, {SEQ} 6-digit
    'document_pattern' => env('DOPAY_DOCUMENT_PATTERN', 'DOPAY-{CC}-{DOC}-{YYYY}-{SEQ}'),

    'require_two_factor' => (bool) env('DOPAY_REQUIRE_2FA', true),
    'share_link_days' => 14,
    'signing_pin_digits' => [4, 6],

    'payment_methods' => ['Cash', 'Bank transfer', 'Mobile money', 'Card', 'Online payment', 'Other'],

    'payment_terms' => [0 => 'Due on receipt', 15 => 'Net 15', 30 => 'Net 30', 45 => 'Net 45'],

    'customer_categories' => ['Distributor', 'Retail', 'Health partner', 'Cooperative', 'Member'],

    // The 21 categories from the Appendix F / G drop-down list. [name, group, note]
    'expense_categories' => [
        ['Office Expenses', 'recurrent', 'Stationery, fuel, electricity, water, internet, telephone, cleaning'],
        ['Hotel & Transportation', 'recurrent', 'Accommodation, flight tickets, overseas/domestic travel allowances'],
        ['Salary Advance', 'recurrent', 'Cash advances paid to staff against future salary'],
        ['Welfare and Project Supplies', 'recurrent', 'Staff refreshments, uniforms, gifts, event supplies, project materials'],
        ['Commission', 'recurrent', 'Commission paid to bank, e-wallet, agents or sales partners'],
        ['Salary and Wages', 'recurrent', 'Monthly salaries, overtime, non-salary wages'],
        ['Local Transportation and Waybills', 'recurrent', 'Vehicle running & maintenance, local delivery, domestic waybills'],
        ['Marketing & Media', 'recurrent', 'Advertising, branding, promotional materials, media team'],
        ['Cargo & Freight', 'recurrent', 'International freight, import/export shipping, clearance fees'],
        ['Professional & Levy', 'recurrent', 'Legal, accounting, audit, visa and other professional fees'],
        ['Bank Charges', 'recurrent', 'Bank transaction fees, SMS alerts, transfer fees'],
        ['Pension', 'recurrent', 'Pension contributions for staff'],
        ['Tax', 'recurrent', 'Company income tax, WHT, VAT and other levies'],
        ['Insurance', 'recurrent', 'Staff or asset insurance premiums'],
        ['Furniture and Fittings', 'fixed', 'Furniture purchases and fitting costs'],
        ['Land and Building', 'fixed', 'Rent, lease payments, property purchases'],
        ['Computers & IT', 'fixed', 'Laptops, phones, software, subscriptions, IT equipment'],
        ['Office Equipment and Machinery', 'fixed', 'Printers, generators, office machinery'],
        ['Motor Vehicles', 'fixed', 'Vehicle purchases, major vehicle items'],
        ['Intangible Assets', 'fixed', 'Software licences, trademarks, brand registrations'],
        ['Other (Specify)', 'fixed', 'Anything not listed above; description required'],
    ],

    /*
    | Permissions and the default grant per role.
    */
    'permissions' => [
        'customers.manage' => 'Create & edit customers',
        'suppliers.manage' => 'Add & edit suppliers',
        'invoices.create' => 'Create invoices & drafts',
        'invoices.approve' => 'Approve invoices',
        'payments.record' => 'Record payments & receipts',
        'payments.reverse' => 'Refund / reverse payments',
        'expenses.create' => 'Submit expenses',
        'expenses.approve' => 'Review & approve expenses',
        'documents.manage' => 'Manage templates & documents',
        'reports.view' => 'View reports',
        'audit.view' => 'View audit trail',
        'users.manage' => 'Manage users & roles',
        'settings.manage' => 'Change settings',
        'forms.submit' => 'Fill & submit finance forms',
        'forms.admin' => 'Manage form numbering & revisions',
        'messages.use' => 'Use team messages',
    ],

    'roles' => [
        'Super Administrator' => ['*'],
        'Country Administrator' => ['customers.manage', 'suppliers.manage', 'users.manage', 'settings.manage', 'documents.manage', 'reports.view', 'audit.view', 'expenses.approve', 'messages.use'],
        'CEO' => ['reports.view', 'audit.view', 'forms.submit', 'messages.use'],
        'CFO' => ['invoices.approve', 'payments.reverse', 'expenses.approve', 'reports.view', 'audit.view', 'forms.submit', 'messages.use'],
        'Financial Controller' => ['invoices.approve', 'payments.reverse', 'expenses.approve', 'reports.view', 'audit.view', 'forms.submit', 'forms.admin', 'messages.use'],
        'Regional Manager' => ['invoices.approve', 'expenses.approve', 'reports.view', 'audit.view', 'forms.submit', 'messages.use'],
        'Country Manager' => ['customers.manage', 'invoices.create', 'invoices.approve', 'expenses.approve', 'reports.view', 'audit.view', 'forms.submit', 'messages.use'],
        'Assistant Manager' => ['customers.manage', 'invoices.create', 'invoices.approve', 'payments.record', 'expenses.create', 'expenses.approve', 'reports.view', 'forms.submit', 'messages.use'],
        'Finance Manager' => ['customers.manage', 'suppliers.manage', 'invoices.create', 'invoices.approve', 'payments.record', 'payments.reverse', 'expenses.create', 'expenses.approve', 'documents.manage', 'reports.view', 'audit.view', 'forms.submit', 'forms.admin', 'messages.use'],
        'Accountant' => ['customers.manage', 'invoices.create', 'payments.record', 'expenses.create', 'reports.view', 'forms.submit', 'messages.use'],
        'Accounting Officer' => ['customers.manage', 'invoices.create', 'payments.record', 'expenses.create', 'reports.view', 'forms.submit', 'messages.use'],
        'Branch Manager' => ['customers.manage', 'invoices.create', 'invoices.approve', 'expenses.approve', 'reports.view', 'forms.submit', 'messages.use'],
        'Sales/Customer Officer' => ['customers.manage', 'invoices.create', 'forms.submit', 'messages.use'],
        'Auditor' => ['reports.view', 'audit.view'],
        'Document Officer' => ['documents.manage', 'forms.submit', 'forms.admin', 'messages.use'],
    ],

    // Each country is its own account. Only these roles can open every account.
    'global_roles' => ['Super Administrator'],

    // Group approvers sign finance forms routed to them from any country, but only browse their own country's account.
    'group_approver_roles' => ['Financial Controller', 'Regional Manager', 'CFO', 'CEO'],

    /*
    | Finance forms. Each step: label, roles that may sign, or 'who' (preparer|holder|purchaser).
    | 'paying' => the signer must belong to the paying country (Appendix J).
    | 'not_holder' => the petty cash holder may never sign this step (Appendix B).
    */
    'forms' => [
        'A' => [
            'name' => 'Expense Memorandum', 'appendix' => 'Appendix A', 'file' => 'docx',
            'pattern' => '{CODE}{YYYY}-A-{###}', 'footer' => 'Expense Memorandum Template', 'effective' => 'Effective 1 August 2026',
            'routes' => [
                'CFO' => ['to' => 'CFO', 'steps' => [
                    ['label' => '1. Acknowledged by Accountant or Assistant Manager', 'roles' => ['Accountant', 'Assistant Manager', 'Country Manager']],
                    ['label' => '2. Reviewed by Country Manager and Regional Manager', 'roles' => ['Country Manager', 'Regional Manager']],
                    ['label' => '3. Checked by Global Financial Controller', 'roles' => ['Financial Controller']],
                    ['label' => '4. Final Approved by CFO or CEO', 'roles' => ['CFO', 'CEO']],
                ]],
                'RM' => ['to' => 'Regional Manager', 'steps' => [
                    ['label' => '1. Acknowledged by Accountant or Assistant Manager', 'roles' => ['Accountant', 'Assistant Manager', 'Country Manager']],
                    ['label' => '2. Reviewed by Assistant Manager or Country Manager', 'roles' => ['Assistant Manager', 'Country Manager']],
                    ['label' => '3. Checked by Global Financial Controller', 'roles' => ['Financial Controller']],
                    ['label' => '4. Final Approved by Regional Manager', 'roles' => ['Regional Manager']],
                ]],
            ],
            'requester_signature' => true,
            'requires_attachment' => true,
            'fields' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
                ['key' => 'route', 'label' => 'Approval route', 'type' => 'select', 'options' => ['CFO' => 'CFO or CEO (Template 2)', 'RM' => 'Regional Manager (Template 1)'], 'required' => true],
                ['key' => 'subject', 'label' => 'Subject (one line)', 'type' => 'text', 'required' => true, 'full' => true],
                ['key' => 'pay_method', 'label' => 'Payment method', 'type' => 'select', 'options' => ['Cash' => 'Cash', 'Bank transfer' => 'Bank transfer', 'Other' => 'Other']],
                ['key' => 'pay_other', 'label' => 'Other method', 'type' => 'text'],
                ['key' => 'from_bank', 'label' => 'Transfer from bank', 'type' => 'text'],
                ['key' => 'account_no', 'label' => 'Account number', 'type' => 'text'],
                ['key' => 'pay_date', 'label' => 'Payment date', 'type' => 'date'],
                ['key' => 'bank_fee', 'label' => 'Bank fee', 'type' => 'text'],
                ['key' => 'justification', 'label' => 'Detailed description / justification', 'type' => 'textarea', 'required' => true],
                ['key' => 'requester_name', 'label' => 'Requester name (Head of Department)', 'type' => 'text'],
                ['key' => 'requester_department', 'label' => 'Department', 'type' => 'text'],
            ],
            'lines' => [
                ['key' => 'description', 'label' => 'Description', 'type' => 'text', 'wide' => true],
                ['key' => 'category', 'label' => 'Category', 'type' => 'category'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'account_details', 'label' => 'Acct name / number / bank', 'type' => 'text'],
            ],
        ],
        'B' => [
            'name' => 'Petty Cash Voucher (Impress)', 'appendix' => 'Appendix B', 'file' => 'docx',
            'pattern' => '{CODE}-{BR}-{YYYY}-B-{###}', 'footer' => 'Petty Cash Voucher Template (Appendix B)', 'effective' => 'Effective 1 July 2026',
            'steps' => [
                ['label' => 'Paid by (Petty Cash Holder)', 'who' => 'holder'],
                ['label' => 'Approved by (Accountant/Branch manager)', 'roles' => ['Accountant', 'Branch Manager', 'Assistant Manager', 'Country Manager'], 'not_holder' => true],
            ],
            'fields' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
                ['key' => 'pay_to', 'label' => 'Pay to', 'type' => 'text', 'required' => true],
                ['key' => 'holder_id', 'label' => 'Petty cash holder', 'type' => 'user', 'required' => true],
                ['key' => 'memo_ref', 'label' => 'Expense Memorandum No.', 'type' => 'text'],
                ['key' => 'reimbursed_on', 'label' => 'Date reimbursed', 'type' => 'date'],
            ],
            'lines' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'description', 'label' => 'Description', 'type' => 'text', 'wide' => true],
                ['key' => 'category', 'label' => 'Category', 'type' => 'category'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ],
        ],
        'C' => [
            'name' => 'Receipt Reimbursement Certification', 'appendix' => 'Appendix C', 'file' => 'xlsx',
            'pattern' => '{CODE}{YYYY}-C-{###}', 'footer' => 'Receipt Reimbursement Certification (Appendix C)', 'effective' => 'Effective 1 July 2026',
            'steps' => [
                ['label' => 'Purchaser', 'who' => 'purchaser'],
                ['label' => 'Approver (Accountant or Assistant Manager)', 'roles' => ['Accountant', 'Assistant Manager']],
            ],
            'fields' => [
                ['key' => 'purchaser_id', 'label' => 'Purchaser (certifying employee)', 'type' => 'user', 'required' => true],
                ['key' => 'position', 'label' => 'Position', 'type' => 'text', 'required' => true],
                ['key' => 'period_from', 'label' => 'Period from', 'type' => 'date'],
                ['key' => 'period_to', 'label' => 'Period to', 'type' => 'date'],
            ],
            'lines' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'description', 'label' => 'Transaction detail', 'type' => 'text', 'wide' => true],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
                ['key' => 'notes', 'label' => 'Note', 'type' => 'text'],
            ],
        ],
        'F' => [
            'name' => 'Expense Budget', 'appendix' => 'Appendix F', 'file' => 'xlsx',
            'pattern' => '{CODE}-F-{YYYY}-{PERIOD}', 'footer' => 'Budget Templates (Appendix F)', 'effective' => 'Version 2.01',
            'steps' => [
                ['label' => 'Prepared by (Accountant)', 'who' => 'preparer'],
                ['label' => 'Reviewed by (Financial Controller)', 'roles' => ['Financial Controller']],
            ],
            'fields' => [
                ['key' => 'period', 'label' => 'Template', 'type' => 'select', 'options' => ['Monthly' => 'Monthly', 'Quarterly' => 'Quarterly', 'Annual' => 'Annual'], 'required' => true],
                ['key' => 'year', 'label' => 'Year', 'type' => 'number', 'required' => true],
                ['key' => 'month', 'label' => 'Month (monthly)', 'type' => 'month_number'],
                ['key' => 'quarter', 'label' => 'Quarter (quarterly)', 'type' => 'select', 'options' => ['1' => 'Q1', '2' => 'Q2', '3' => 'Q3', '4' => 'Q4']],
            ],
            'lines' => 'budget',
        ],
        'G' => [
            'name' => 'Branch Financial Report', 'appendix' => 'Appendix G', 'file' => 'xlsx',
            'pattern' => '{CODE}-G-{YYYY}-{MM}', 'footer' => 'Branch Financial Report (Appendix G)', 'effective' => 'Effective 1 July 2026',
            'due_day' => 15,
            'steps' => [
                ['label' => 'Prepared by (Accountant)', 'who' => 'preparer'],
                ['label' => 'Reviewed by (Country Manager or Assistant Manager)', 'roles' => ['Country Manager', 'Assistant Manager']],
                ['label' => 'Approved by (Financial Manager)', 'roles' => ['Finance Manager']],
                ['label' => 'Received by (Financial Controller)', 'roles' => ['Financial Controller']],
            ],
            'fields' => [
                ['key' => 'month', 'label' => 'Month', 'type' => 'month', 'required' => true],
                ['key' => 'bank', 'label' => 'Bank', 'type' => 'text'],
                ['key' => 'account_name', 'label' => 'Account name', 'type' => 'text'],
                ['key' => 'account_no', 'label' => 'Account number', 'type' => 'text'],
                ['key' => 'opening_balance', 'label' => 'Opening balance (bal b/f)', 'type' => 'money', 'required' => true],
                ['key' => 'bank_closing', 'label' => 'Closing balance per bank statement', 'type' => 'money', 'required' => true],
            ],
            'lines' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date'],
                ['key' => 'memo_ref', 'label' => 'Memo ref', 'type' => 'text'],
                ['key' => 'party', 'label' => 'Payee / payer', 'type' => 'text'],
                ['key' => 'description', 'label' => 'Purpose', 'type' => 'text', 'wide' => true],
                ['key' => 'inflow', 'label' => 'Inflow', 'type' => 'money'],
                ['key' => 'outflow', 'label' => 'Outflow', 'type' => 'money'],
                ['key' => 'category', 'label' => 'Category', 'type' => 'category_or_inflow'],
                ['key' => 'notes', 'label' => 'Notes', 'type' => 'text'],
            ],
        ],
        'J' => [
            'name' => 'Interbranch Transfer Memorandum', 'appendix' => 'Appendix J', 'file' => 'docx',
            'pattern' => '{CODE}-J-{YYYY}-{###}', 'footer' => 'Interbranch Transfer Memorandum (Appendix J)', 'effective' => 'Effective 1 July 2026',
            'repayment_days' => 30,
            'steps' => [
                ['label' => 'Prepared by Accountant (Requesting Branch)', 'who' => 'preparer'],
                ['label' => 'Acknowledged by Accountant (Paying Branch)', 'roles' => ['Accountant', 'Assistant Manager'], 'paying' => true],
                ['label' => 'Noted by (Financial Controller)', 'roles' => ['Financial Controller']],
            ],
            'fields' => [
                ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
                ['key' => 'paying_country_id', 'label' => 'Paying branch (provides the funds)', 'type' => 'country', 'required' => true],
            ],
            'lines' => [
                ['key' => 'description', 'label' => 'Description', 'type' => 'text', 'wide' => true],
                ['key' => 'amount', 'label' => 'Amount to pay', 'type' => 'money'],
                ['key' => 'memo_ref', 'label' => 'Memo ref', 'type' => 'text'],
            ],
        ],
        'K' => [
            'name' => 'Cash Advance Request Form', 'appendix' => 'Appendix K', 'file' => 'docx',
            'pattern' => '{CODE}{YYYY}-K-{###}', 'footer' => 'Cash Advance Request Form Template (Appendix K)', 'effective' => 'Effective 1 August 2026',
            'liquidation_working_days' => 5,
            'requester_signature' => true,
            'steps' => [
                ['label' => '1. Accountant or Assistant Manager', 'roles' => ['Accountant', 'Assistant Manager']],
                ['label' => '2. Country Mgr (CM) or Regional Manager (RM)', 'roles' => ['Country Manager', 'Regional Manager']],
                ['label' => '3. Financial Controller', 'roles' => ['Financial Controller']],
                ['label' => '4. CFO', 'roles' => ['CFO']],
                ['label' => '5. CEO', 'roles' => ['CEO']],
            ],
            'fields' => [
                ['key' => 'date', 'label' => 'Date filed', 'type' => 'date', 'required' => true],
                ['key' => 'department', 'label' => 'Department', 'type' => 'text'],
                ['key' => 'subject', 'label' => 'Subject (one-line purpose)', 'type' => 'text', 'required' => true, 'full' => true],
                ['key' => 'purpose', 'label' => 'Purpose of cash advance', 'type' => 'select', 'options' => ['1' => '1. Clearing Customs', '2' => '2. Pocket money for international trips', '3' => '3. Company Events', '4' => '4. Other approved projects'], 'required' => true],
                ['key' => 'purpose_other', 'label' => 'Specify project', 'type' => 'text'],
                ['key' => 'justification', 'label' => 'Justification / business need', 'type' => 'textarea', 'required' => true],
                ['key' => 'use_date', 'label' => 'Expected date of use', 'type' => 'date', 'required' => true],
                ['key' => 'liquidation_date', 'label' => 'Expected date of liquidation', 'type' => 'date', 'hint' => 'Leave empty: 5 working days after use (Policy 14.4)'],
                ['key' => 'pay_method', 'label' => 'Payment method', 'type' => 'select', 'options' => ['Cash' => 'Cash', 'Bank Transfer' => 'Bank Transfer', 'Mobile Money' => 'Mobile Money']],
                ['key' => 'payable_to', 'label' => 'Payable to / account no.', 'type' => 'text', 'required' => true],
            ],
            'lines' => [
                ['key' => 'description', 'label' => 'Description', 'type' => 'text', 'wide' => true],
                ['key' => 'category', 'label' => 'Category', 'type' => 'category'],
                ['key' => 'amount', 'label' => 'Amount', 'type' => 'money'],
            ],
        ],
    ],

    'reminders' => [
        ['label' => 'Payment reminder', 'offset_days' => -7, 'channel' => 'WhatsApp'],
        ['label' => 'Invoice due today', 'offset_days' => 0, 'channel' => 'WhatsApp'],
        ['label' => 'Payment overdue', 'offset_days' => 3, 'channel' => 'Email'],
        ['label' => 'Second payment reminder', 'offset_days' => 7, 'channel' => 'WhatsApp'],
    ],
];
