<?php

/*
| Delivery of invoices, receipts, reminders and portal codes to customers.
| If a message cannot be delivered it falls back to the next channel in 'fallback'.
| Automatic messages go out at 'send_at' branch time and never during quiet hours.
*/

return [
    'fallback' => ['WhatsApp', 'SMS', 'Email'],
    'send_at' => '09:00',
    'quiet_hours' => ['20:00', '07:00'],

    'channels' => [
        'WhatsApp' => [
            'driver' => \App\Services\Messaging\Channels\WhatsAppCloudChannel::class,
            'enabled' => (bool) env('WHATSAPP_ENABLED', false),
            'graph_url' => env('WHATSAPP_GRAPH_URL', 'https://graph.facebook.com/v21.0'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
            'token' => env('WHATSAPP_TOKEN'),
            'app_secret' => env('WHATSAPP_APP_SECRET'), // checks the X-Hub-Signature-256 header on delivery updates
            'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
            // Approved message templates by kind; WhatsApp only allows business-initiated messages from approved templates.
            'templates' => ['Invoice' => 'dopay_invoice', 'Receipt' => 'dopay_receipt', 'Payment reminder' => 'dopay_reminder', 'Payment overdue' => 'dopay_overdue', 'Portal code' => 'dopay_code'],
            'template_language' => ['en' => 'en', 'fr' => 'fr'],
        ],
        'SMS' => [
            'driver' => \App\Services\Messaging\Channels\AfricasTalkingSmsChannel::class,
            'enabled' => (bool) env('SMS_ENABLED', false),
            'url' => env('AFRICASTALKING_URL', 'https://api.africastalking.com/version1/messaging'),
            'username' => env('AFRICASTALKING_USERNAME'),
            'api_key' => env('AFRICASTALKING_API_KEY'),
            'sender_id' => env('AFRICASTALKING_SENDER_ID', 'DOPAY'),
            'callback_secret' => env('SMS_CALLBACK_SECRET'), // part of the delivery-report URL, since the provider does not sign callbacks
        ],
        'Email' => [
            'driver' => \App\Services\Messaging\Channels\MailChannel::class,
            'enabled' => true,
        ],
    ],
];
