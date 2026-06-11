<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GPG_Signature {

    /**
     * Generate Geidea signature for Create Session.
     *
     * @param string $merchantPublicKey
     * @param float|string $amount
     * @param string $currency
     * @param string $merchantReferenceId
     * @param string $apiPassword
     * @param string $timestamp
     * @return string
     */
    public static function generate( $merchantPublicKey, $amount, $currency, $merchantReferenceId, $apiPassword, $timestamp ) {
        // Format amount to 2 decimal places
        $amountStr = number_format( (float) $amount, 2, '.', '' );
        
        // Concatenate data exactly as required by Geidea: MerchantPublicKey + AmountFormatted2Decimals + Currency + MerchantReferenceId + Timestamp
        $data = $merchantPublicKey . $amountStr . $currency . $merchantReferenceId . $timestamp;
        
        // Create HMAC SHA-256 hash using API password
        $hash = hash_hmac( 'sha256', $data, $apiPassword, true );
        
        // Return Base64 encoded hash
        return base64_encode( $hash );
    }
}

