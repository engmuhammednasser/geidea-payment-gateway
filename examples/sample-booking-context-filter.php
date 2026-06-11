<?php
/**
 * Sample Filter for Geidea Saudi Car Booking Gateway.
 * 
 * Copy this into your theme's functions.php or your custom plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter('GPG_booking_context', function (array $context, int $booking_id): array {
    // In a real scenario, fetch $daily_rate and $days from your database using $booking_id
    // $booking = get_my_booking($booking_id);
    
    $daily_rate = 250.00; // SAR
    $days       = 3;

    $context['amount']   = $daily_rate * $days;
    $context['currency'] = 'SAR';
    $context['language'] = 'ar';

    $context['customer'] = [
        'email'            => 'customer@example.com',
        'phoneNumber'      => '500000000', // Start with 5 for KSA
        'phonecountrycode' => '+966',
        'firstName'        => 'محمد',
        'lastName'         => 'أحمد',
        'address'          => [
            'billing' => [
                'country'    => 'SAU',
                'city'       => 'Riyadh',
                'street'     => 'Riyadh',
                'postalCode' => '00000',
            ],
            'shipping' => [
                'country'    => 'SAU',
                'city'       => 'Riyadh',
                'street'     => 'Riyadh',
                'postalCode' => '00000',
            ],
        ],
    ];

    $context['car'] = [
        'id'         => 55,
        'name'       => 'Hyundai Elantra',
        'daily_rate' => $daily_rate,
        'days'       => $days,
    ];

    $context['items'] = [
        [
            'merchantItemId' => 'car-55',
            'name'           => 'Hyundai Elantra Rental',
            'description'    => 'Car rental for 3 days',
            'categories'     => 'Car Rental',
            'count'          => $days,
            'price'          => $daily_rate,
            'sku'            => 'car-55',
        ],
    ];

    return $context;
}, 10, 2);

// Actions Example:

add_action( 'GPG_payment_completed', function( $booking_id, $payload, $status ) {
    // $status will be 'paid' (Live) or 'paid_test' (Sandbox)
    // Update booking status in your DB
    // update_booking_status($booking_id, $status);
}, 10, 3 );

add_action( 'GPG_payment_failed', function( $booking_id, $payload, $status ) {
    // $status will be 'payment_failed'
    // Update booking status in your DB
    // update_booking_status($booking_id, $status);
}, 10, 3 );

