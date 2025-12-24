<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       lib/qrcode.lib.php
 * \ingroup    totp2fa
 * \brief      QR Code generation helper functions
 */

/**
 * Generate QR code image URL
 *
 * This function uses an external QR code generation service
 * For production use, consider using a PHP library like endroid/qr-code
 *
 * @param string $data Data to encode in QR code
 * @param int $size Size of QR code in pixels (default 200)
 * @return string Data URL or image URL
 */
function totp2fa_getQRCodeImageUrl($data, $size = 200)
{
    // Option 1: Use quickchart.io (free, no API key required)
    $url = 'https://quickchart.io/qr?text='.urlencode($data).'&size='.$size;
    return $url;
}

/**
 * Generate QR code as inline SVG (using simple library-free approach)
 * This is a fallback method that generates a basic data URL
 *
 * @param string $data Data to encode
 * @return string HTML img tag with QR code
 */
function totp2fa_getQRCodeHTML($data, $size = 200)
{
    global $langs;

    // Use external service for now
    $url = totp2fa_getQRCodeImageUrl($data, $size);

    $html = '<div class="totp2fa-qrcode-container" style="text-align: center; margin: 20px 0;">';
    $html .= '<img src="'.$url.'" alt="QR Code" style="border: 2px solid #ddd; padding: 10px; background: white;" />';
    $html .= '<p style="margin-top: 10px; font-size: 12px; color: #666;">';
    $html .= $langs->trans('ScanQRCodeWithAuthApp');
    $html .= '</p>';
    $html .= '</div>';

    return $html;
}

/**
 * Generate QR code using PHP QR Code library if available
 * This requires the endroid/qr-code library to be installed via composer
 *
 * @param string $data Data to encode
 * @param int $size Size in pixels
 * @return string Base64 encoded PNG or external URL
 */
function totp2fa_generateQRCodeImage($data, $size = 200)
{
    // Check if endroid/qr-code is available
    if (class_exists('Endroid\QrCode\QrCode')) {
        // Use library if available
        require_once DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';

        try {
            $qrCode = new \Endroid\QrCode\QrCode($data);
            $qrCode->setSize($size);
            $qrCode->setMargin(10);

            // Return base64 encoded PNG
            return 'data:image/png;base64,'.base64_encode($qrCode->writeString());
        } catch (Exception $e) {
            // Fall back to external service
            return totp2fa_getQRCodeImageUrl($data, $size);
        }
    }

    // Fallback: Use external service
    return totp2fa_getQRCodeImageUrl($data, $size);
}

/**
 * Display manual secret entry (alternative to QR code scanning)
 *
 * @param string $secret Base32 encoded secret
 * @return string HTML for manual entry
 */
function totp2fa_getManualEntryHTML($secret)
{
    global $langs;

    // Format secret in groups of 4 for easier reading
    $formattedSecret = trim(chunk_split($secret, 4, ' '));

    $html = '<div class="totp2fa-manual-entry" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
    $html .= '<h4 style="margin-top: 0;">'.$langs->trans('ManualEntry').'</h4>';
    $html .= '<p style="font-size: 13px; color: #666;">'.$langs->trans('CannotScanQRCode').'</p>';
    $html .= '<div style="margin: 10px 0;">';
    $html .= '<strong>'.$langs->trans('Secret').':</strong><br>';
    $html .= '<code style="font-size: 16px; background: white; padding: 8px 12px; display: inline-block; border: 1px solid #ccc; border-radius: 3px; letter-spacing: 2px;">';
    $html .= $formattedSecret;
    $html .= '</code>';
    $html .= '</div>';
    $html .= '<p style="font-size: 12px; color: #888; margin-bottom: 0;">';
    $html .= $langs->trans('EnterThisSecretInYourApp');
    $html .= '</p>';
    $html .= '</div>';

    return $html;
}
