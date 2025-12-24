<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/totp.class.php
 * \ingroup    totp2fa
 * \brief      TOTP (Time-based One-Time Password) implementation - RFC 6238
 */

/**
 * TOTP Class - Implements RFC 6238 TOTP algorithm
 */
class TOTP
{
    /**
     * @var int Time step in seconds (default 30)
     */
    private $timeStep = 30;

    /**
     * @var int Code length (6 or 8 digits)
     */
    private $codeLength = 6;

    /**
     * @var string Hash algorithm (sha1, sha256, sha512)
     */
    private $hashAlgorithm = 'sha1';

    /**
     * @var int Time drift tolerance (±1 = 90 seconds window)
     */
    private $timeDrift = 1;

    /**
     * Constructor
     *
     * @param int $timeStep Time step in seconds (default 30)
     * @param int $codeLength Code length (default 6)
     * @param string $hashAlgorithm Hash algorithm (default sha1)
     */
    public function __construct($timeStep = 30, $codeLength = 6, $hashAlgorithm = 'sha1')
    {
        $this->timeStep = $timeStep;
        $this->codeLength = $codeLength;
        $this->hashAlgorithm = $hashAlgorithm;
    }

    /**
     * Generate a random secret (Base32 encoded)
     *
     * @param int $length Secret length in bytes (default 20 = 160 bits)
     * @return string Base32 encoded secret
     */
    public function generateSecret($length = 20)
    {
        // Generate cryptographically secure random bytes
        if (function_exists('random_bytes')) {
            $secret = random_bytes($length);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $secret = openssl_random_pseudo_bytes($length);
        } else {
            // Fallback (less secure)
            $secret = '';
            for ($i = 0; $i < $length; $i++) {
                $secret .= chr(mt_rand(0, 255));
            }
        }

        return $this->base32Encode($secret);
    }

    /**
     * Generate TOTP code for a given secret and time
     *
     * @param string $secret Base32 encoded secret
     * @param int|null $timestamp Unix timestamp (null = current time)
     * @return string 6-digit TOTP code
     */
    public function generateCode($secret, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Calculate time counter
        $timeCounter = floor($timestamp / $this->timeStep);

        // Decode secret from Base32
        $secretKey = $this->base32Decode($secret);

        // Generate HOTP code
        return $this->generateHOTP($secretKey, $timeCounter);
    }

    /**
     * Verify TOTP code
     *
     * @param string $secret Base32 encoded secret
     * @param string $code Code to verify
     * @param int|null $timestamp Unix timestamp (null = current time)
     * @return bool True if code is valid
     */
    public function verifyCode($secret, $code, $timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        // Check code with time drift tolerance
        for ($i = -$this->timeDrift; $i <= $this->timeDrift; $i++) {
            $testTime = $timestamp + ($i * $this->timeStep);
            $testCode = $this->generateCode($secret, $testTime);

            if ($this->timingSafeEquals($code, $testCode)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate HOTP code (RFC 4226)
     *
     * @param string $key Secret key (raw bytes)
     * @param int $counter Counter value
     * @return string 6-digit code
     */
    private function generateHOTP($key, $counter)
    {
        // Convert counter to 8-byte binary string (big-endian)
        $counterBytes = pack('N*', 0, $counter);

        // HMAC-SHA1
        $hash = hash_hmac($this->hashAlgorithm, $counterBytes, $key, true);

        // Dynamic truncation (RFC 4226 Section 5.3)
        $offset = ord(substr($hash, -1)) & 0x0F;
        $truncatedHash = substr($hash, $offset, 4);

        // Convert to integer
        $value = unpack('N', $truncatedHash)[1];
        $value = $value & 0x7FFFFFFF; // Remove sign bit

        // Generate code
        $modulo = pow(10, $this->codeLength);
        $code = $value % $modulo;

        // Pad with zeros
        return str_pad($code, $this->codeLength, '0', STR_PAD_LEFT);
    }

    /**
     * Generate QR code URL for authenticator apps
     * Format: otpauth://totp/LABEL?secret=SECRET&issuer=ISSUER
     *
     * @param string $secret Base32 encoded secret
     * @param string $label Account label (e.g., "user@example.com")
     * @param string $issuer Issuer name (e.g., "My Company")
     * @return string OTP Auth URL
     */
    public function getQRCodeUrl($secret, $label, $issuer = 'Dolibarr')
    {
        $params = array(
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($this->hashAlgorithm),
            'digits' => $this->codeLength,
            'period' => $this->timeStep,
        );

        $url = 'otpauth://totp/'.rawurlencode($issuer.':'.$label).'?'.http_build_query($params);

        return $url;
    }

    /**
     * Base32 encode (RFC 4648)
     *
     * @param string $data Raw data
     * @return string Base32 encoded string
     */
    private function base32Encode($data)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $v = ($v << 8) | ord($data[$i]);
            $vbits += 8;

            while ($vbits >= 5) {
                $vbits -= 5;
                $output .= $alphabet[($v >> $vbits) & 0x1F];
            }
        }

        if ($vbits > 0) {
            $output .= $alphabet[($v << (5 - $vbits)) & 0x1F];
        }

        return $output;
    }

    /**
     * Base32 decode (RFC 4648)
     *
     * @param string $data Base32 encoded string
     * @return string Raw data
     */
    private function base32Decode($data)
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $data = strtoupper($data);
        $output = '';
        $v = 0;
        $vbits = 0;

        for ($i = 0, $j = strlen($data); $i < $j; $i++) {
            $c = $data[$i];
            $pos = strpos($alphabet, $c);

            if ($pos === false) {
                continue; // Skip invalid characters
            }

            $v = ($v << 5) | $pos;
            $vbits += 5;

            if ($vbits >= 8) {
                $vbits -= 8;
                $output .= chr(($v >> $vbits) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Timing-safe string comparison
     * Prevents timing attacks
     *
     * @param string $a First string
     * @param string $b Second string
     * @return bool True if equal
     */
    private function timingSafeEquals($a, $b)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($a, $b);
        }

        // Fallback for older PHP versions
        if (strlen($a) !== strlen($b)) {
            return false;
        }

        $result = 0;
        for ($i = 0; $i < strlen($a); $i++) {
            $result |= ord($a[$i]) ^ ord($b[$i]);
        }

        return $result === 0;
    }

    /**
     * Get current time counter
     *
     * @param int|null $timestamp Unix timestamp (null = current time)
     * @return int Time counter
     */
    public function getTimeCounter($timestamp = null)
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        return floor($timestamp / $this->timeStep);
    }

    /**
     * Get remaining seconds in current time step
     *
     * @return int Seconds remaining
     */
    public function getRemainingSeconds()
    {
        return $this->timeStep - (time() % $this->timeStep);
    }
}
