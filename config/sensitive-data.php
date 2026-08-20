<?php

return [
    'mask' => '••••',

    'storage' => [
        'disk' => env('SENSITIVE_DATA_DISK', 'private'),
        'legacy_disk' => 'public',
    ],

    'fields' => [
        'persons' => [
            'emails' => 'email',
            'contact_numbers' => 'phone',
            'unique_id' => 'hidden',
        ],

        'organizations' => [
            'address' => 'address',
        ],

        'leads' => [
            'description' => 'hidden',
            'lead_value' => 'financial',
            'lost_reason' => 'hidden',
            'lead_source_id' => 'hidden',
            'source' => 'hidden',
            'products' => 'hidden',
        ],

        'activities' => [
            'title' => 'hidden',
            'comment' => 'hidden',
            'location' => 'hidden',
            'additional' => 'activity',
            'files' => 'hidden',
        ],

        'emails' => [
            'name' => 'hidden',
            'subject' => 'hidden',
            'source' => 'hidden',
            'reply' => 'hidden',
            'from' => 'email',
            'sender' => 'email',
            'reply_to' => 'email',
            'cc' => 'email',
            'bcc' => 'email',
            'unique_id' => 'hidden',
            'message_id' => 'hidden',
            'reference_ids' => 'hidden',
            'attachments' => 'hidden',
        ],

        'quotes' => [
            'description' => 'hidden',
            'billing_address' => 'address',
            'shipping_address' => 'address',
            'discount_percent' => 'financial',
            'discount_amount' => 'financial',
            'tax_amount' => 'financial',
            'adjustment_amount' => 'financial',
            'sub_total' => 'financial',
            'grand_total' => 'financial',
        ],
    ],

    'attribute_types' => [
        'email' => 'email',
        'phone' => 'phone',
        'address' => 'address',
    ],

    'document_patterns' => [
        '/(^|_)(cpf|cnpj|rg|document|documento|tax_id|vat)(_|$)/i',
    ],
];
