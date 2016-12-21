<?php

return [
    'email' => [
        'confirmation' => [
            'subject' => 'Thank you for your order #:reference'
        ],
        'processing' => [
            'subject' => 'We are processing your order #:reference'
        ],
        'shipped' => [
            'subject' => 'Your order #:reference is shipped'
        ],
        'completed' => [
            'subject' => 'Your order #:reference is completed'
        ],
        'cancelled' => [
            'subject' => 'Your order #:reference is cancelled'
        ]
    ],
    'coupons' => [
        'successfully_added' => 'Coupon ":coupon_code" is successfully added.',
        'not_exist' => 'Coupon ":coupon_code" is invalid.',
        'invalid' => 'Coupon ":coupon_code" is not valid. Please check your order.',
        'max_usage_exceeded' => 'Coupon ":coupon_code" has reached usage limit.',
        'max_usage_per_email_exceeded' => 'You have used coupon ":coupon_code" before.',
    ],
    'address' => [
        'select_country' => 'Country',
        'select_state' => 'State',
        'select_city' => 'City',
        'select_district' => 'District',
        'select_area' => 'Area',
    ],
    'shipping' => [
        'estimated_working_day' => 'Approximately :estimated working day|Approximately :estimated working days',
    ],
];