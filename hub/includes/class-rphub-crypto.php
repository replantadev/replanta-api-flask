<?php
/**
 * Replanta Hub — Encryption helper for sensitive DB options
 *
 * Algorithm : AES-256-GCM  (authenticated encryption — detects tampering)
 * Key source: RPHUB_ENCRYPTION_KEY constant in wp-config.php (recommended)
 *             Falls back to WordPress AUTH_KEY if the constant is absent.
 *
 * Usage — define in wp-config.php:
 *   define( 'RPHUB_ENCRYPTION_KEY', 'your-random-64-hex-chars-here' );
 *
 * Stored format: base64( iv [12 bytes] + tag [16 bytes] + ciphertext )
 *
 * Migration-safe: if decrypt() receives plaintext that was stored BEFORE
 * encryption was introduced it returns the value unchanged, so existing
 * options keep working after a plugin update.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class RPHUB_Crypto {

    /** Cipher to use — AES-256-GCM provides authenticated encryption. */
    const CIPHER = 'aes-256-gcm';

    /** Tag length in bytes required by GCM. */
    const TAG_LENGTH = 16;

    /** IV length in bytes (GCM standard). */
    const IV_LENGTH = 12;

    /**
     * Encrypt a plaintext string.
     *
     * @param  string $plaintext Value to encrypt.
     * @return string            Encrypted, base64-encoded blob, or empty string on failure.
     */
    public static function encrypt( string $plaintext ): string {
        if ( $plaintext === '' ) {
            return '';
        }

        $key = self::get_key();
        $iv  = random_bytes( self::IV_LENGTH );
        $tag = '';

        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ( $ciphertext === false ) {
            error_log( '[RPHUB Crypto] Encryption failed: ' . openssl_error_string() );
            return '';
        }

        return base64_encode( $iv . $tag . $ciphertext );
    }

    /**
     * Decrypt a value that was previously encrypted with encrypt().
     *
     * Migration-safe: returns the raw value unchanged when it does not look
     * like a valid encrypted blob (i.e. legacy plaintext stored before
     * encryption was introduced).
     *
     * @param  string $encrypted Base64-encoded encrypted blob.
     * @return string            Plaintext, or empty string on failure.
     */
    public static function decrypt( string $encrypted ): string {
        if ( $encrypted === '' ) {
            return '';
        }

        $raw = base64_decode( $encrypted, true );

        // Must contain at least iv + tag + 1 byte of ciphertext
        $min_length = self::IV_LENGTH + self::TAG_LENGTH + 1;
        if ( $raw === false || strlen( $raw ) < $min_length ) {
            // Not a valid encrypted blob — treat as legacy plaintext
            return $encrypted;
        }

        $iv         = substr( $raw, 0, self::IV_LENGTH );
        $tag        = substr( $raw, self::IV_LENGTH, self::TAG_LENGTH );
        $ciphertext = substr( $raw, self::IV_LENGTH + self::TAG_LENGTH );
        $key        = self::get_key();

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ( $plaintext === false ) {
            // Decryption failed — could be a key change or corruption.
            // Return the encrypted string so the caller can detect the issue.
            error_log( '[RPHUB Crypto] Decryption failed. Check RPHUB_ENCRYPTION_KEY.' );
            return $encrypted;
        }

        return $plaintext;
    }

    /**
     * Re-encrypt a value using the current key.
     * Safe to call on already-encrypted or plaintext strings.
     *
     * @param  string $value Plaintext or encrypted value.
     * @return string        Newly encrypted blob.
     */
    public static function re_encrypt( string $value ): string {
        return self::encrypt( self::decrypt( $value ) );
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Return the 32-byte raw key used for encryption.
     *
     * Priority:
     *  1. RPHUB_ENCRYPTION_KEY constant defined in wp-config.php
     *  2. WordPress AUTH_KEY secret (always present)
     *
     * The key is hashed to ensure it is always exactly 32 bytes regardless of
     * the length of the source constant.
     *
     * @return string 32-byte binary key.
     */
    private static function get_key(): string {
        if ( defined( 'RPHUB_ENCRYPTION_KEY' ) && RPHUB_ENCRYPTION_KEY !== '' ) {
            $source = RPHUB_ENCRYPTION_KEY;
        } else {
            $source = defined( 'AUTH_KEY' ) ? AUTH_KEY : wp_salt( 'auth' );
        }

        // hash() with raw=true returns exactly 32 bytes for sha256
        return hash( 'sha256', $source, true );
    }
}
