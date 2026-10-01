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
 * Render a QR code as inline SVG, generated locally with the TCPDF library
 * bundled with Dolibarr. The data (TOTP secret) never leaves the server.
 *
 * @param string $data Data to encode in the QR code
 * @return string SVG markup, or empty string if the library is unavailable
 */
function totp2fa_getQRCodeSVG($data)
{
    $lib = DOL_DOCUMENT_ROOT.'/includes/tecnickcom/tcpdf/tcpdf_barcodes_2d.php';
    if (!is_readable($lib)) {
        return '';
    }
    require_once $lib;

    $barcode = new TCPDF2DBarcode($data, 'QRCODE,M');
    $svg = $barcode->getBarcodeSVGcode(5, 5, 'black');

    return substr($svg, (int) strpos($svg, '<svg'));
}

/**
 * Generate QR code HTML (inline SVG)
 *
 * @param string $data Data to encode in QR code
 * @param int $size Size of QR code in pixels (default 200)
 * @return string HTML
 */
function totp2fa_getQRCodeHTML($data, $size = 200)
{
    global $langs;

    $svg = totp2fa_getQRCodeSVG($data);

    $html = '<div class="totp2fa-qrcode-container" style="text-align: center; margin: 20px 0;">';
    if ($svg !== '') {
        $html .= '<div style="display: inline-block; width: '.((int) $size).'px; max-width: 100%; border: 2px solid #ddd; padding: 10px; background: white; box-sizing: content-box;">';
        $html .= preg_replace('/<svg width="(\d+)" height="(\d+)"/', '<svg width="100%" viewBox="0 0 $1 $2"', $svg, 1);
        $html .= '</div>';
        $html .= '<p style="margin-top: 10px; font-size: 12px; color: #666;">';
        $html .= $langs->trans('ScanQRCodeWithAuthApp');
        $html .= '</p>';
    }
    $html .= '</div>';

    return $html;
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
