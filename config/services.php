<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
     * SI-02: sumber data lokasi dan tarif ongkir. Kunci hanya dipakai di
     * server; versi API mengikuti dokumentasi RajaOngkir saat implementasi.
     */
    'rajaongkir' => [
        'key' => env('RAJAONGKIR_API_KEY'),
        'base_url' => env('RAJAONGKIR_BASE_URL', 'https://rajaongkir.komerce.id/api/v1'),
    ],

    /*
     * SI-01: payment gateway Midtrans. `server_key` hanya dipakai di server dan
     * tidak pernah dikirim ke browser; `client_key` dipakai Snap di sisi klien.
     */
    'midtrans' => [
        'server_key' => env('MIDTRANS_SERVER_KEY'),
        'client_key' => env('MIDTRANS_CLIENT_KEY'),
        'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
    ],

    /*
     * FR-ORDR-04 #3: pelacakan memakai tautan eksternal; integrasi SI-02 hanya
     * mencakup lokasi dan tarif, bukan endpoint tracking.
     */
    'tracking' => [
        'base_url' => env('TRACKING_BASE_URL', 'https://www.resi.co.id/'),
    ],

];
