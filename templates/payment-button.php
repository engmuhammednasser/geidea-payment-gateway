<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="gbg-payment-wrapper" id="gbg-wrapper-<?php echo esc_attr( $booking_id ); ?>">
    <button class="gbg-pay-button button" data-booking-id="<?php echo esc_attr( $booking_id ); ?>">
        <?php esc_html_e( 'ادفع الآن', 'geidea-payment-gateway' ); ?>
    </button>
    <div id="gbg-message-<?php echo esc_attr( $booking_id ); ?>" class="gbg-message-container"></div>
</div>

