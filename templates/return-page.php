<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;

if ( ! $booking_id ) {
    echo '<div class="gbg-return-wrapper"><div class="gbg-status-error"><h3>' . esc_html__( 'طلب غير صالح', 'geidea-payment-gateway' ) . '</h3><p>' . esc_html__( 'لم يتم العثور على رقم الحجز.', 'geidea-payment-gateway' ) . '</p></div></div>';
    return;
}

// Get the actual status from the DB using a custom filter or logic.
// Since we don't own the DB structure, we use a filter to ask the booking system for the status.
// Fallback is 'payment_pending_verification' if not provided.
$status = apply_filters( 'GPG_get_booking_status', 'payment_pending_verification', $booking_id );

$success_page_url = GPG_Settings::get_setting( 'GPG_success_url' );
$failed_page_url = GPG_Settings::get_setting( 'GPG_failed_url' );
$cancel_page_url = GPG_Settings::get_setting( 'GPG_cancel_url' );

// We don't redirect to pending if we are already on pending, we just display the pending message.
if ( $status === 'paid' || $status === 'confirmed' || $status === 'paid_test' || $status === 'confirmed_test' ) {
    if ( ! empty( $success_page_url ) ) {
        ?>
        <script>window.location.href = "<?php echo esc_url_raw( add_query_arg( 'booking_id', $booking_id, $success_page_url ) ); ?>";</script>
        <?php
        exit;
    }
}

if ( $status === 'payment_failed' ) {
    if ( ! empty( $failed_page_url ) ) {
        ?>
        <script>window.location.href = "<?php echo esc_url_raw( add_query_arg( 'booking_id', $booking_id, $failed_page_url ) ); ?>";</script>
        <?php
        exit;
    }
}

if ( $status === 'cancelled' ) {
    if ( ! empty( $cancel_page_url ) ) {
        ?>
        <script>window.location.href = "<?php echo esc_url_raw( add_query_arg( 'booking_id', $booking_id, $cancel_page_url ) ); ?>";</script>
        <?php
        exit;
    }
}

// Display Pending State
?>
<div class="gbg-return-wrapper">
    <div id="gbg-status-container" class="gbg-status-pending">
        <h3><?php esc_html_e( 'تم استلام طلب الدفع وجاري التحقق', 'geidea-payment-gateway' ); ?></h3>
        <p><?php esc_html_e( 'يتم الآن تأكيد عملية الدفع. يرجى الانتظار...', 'geidea-payment-gateway' ); ?></p>
        <div class="gbg-spinner"></div>
    </div>
    <script>
        // Refresh page after 5 seconds to check if webhook updated the status
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    </script>
</div>

