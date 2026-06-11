jQuery(document).ready(function($) {
    $('.gbg-pay-button').on('click', function(e) {
        e.preventDefault();

        var $button = $(this);
        var bookingId = $button.data('booking-id');
        var $messageContainer = $('#gbg-message-' + bookingId);

        $button.prop('disabled', true);
        $messageContainer.html('<p class="gbg-loading">' + gpgData.loading_text + '</p>');

        $.ajax({
            url: gpgData.rest_url,
            method: 'POST',
            beforeSend: function ( xhr ) {
                xhr.setRequestHeader( 'X-WP-Nonce', gpgData.nonce );
            },
            data: {
                booking_id: bookingId,
                nonce: gpgData.nonce
            },
            success: function(response) {
                if (response.success && response.checkout_url) {
                    // Redirect to Geidea Hosted Checkout Page in same tab
                    window.location.href = response.checkout_url;
                } else {
                    $button.prop('disabled', false);
                    $messageContainer.html('<p class="gbg-error">' + (response.message || gpgData.error_text) + '</p>');
                }
            },
            error: function(xhr, status, error) {
                $button.prop('disabled', false);
                var errorMsg = gpgData.error_text;
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                $messageContainer.html('<p class="gbg-error">' + errorMsg + '</p>');
            }
        });
    });
});

