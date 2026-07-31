<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       login_extension.php
 * \ingroup    totp2fa
 * \brief      Login page extension for 2FA code input
 *
 * This file adds 2FA functionality directly to the login page
 * The field is always shown but optional - server validates if 2FA is required
 */

// This is included by Dolibarr's main login page via hooks

global $conf, $db, $langs;

// Only run if module is enabled
if (empty($conf->totp2fa->enabled)) {
    return;
}

$langs->load("totp2fa@totp2fa");

$nonce = function_exists('getNonce') ? htmlspecialchars(getNonce(), ENT_QUOTES, 'UTF-8') : '';
?>
<!-- TOTP 2FA Login Extension -->
<style<?php echo $nonce ? ' nonce="'.$nonce.'"' : ''; ?> type="text/css">
/* The theme styles the login inputs by id, not by class:
     .login_table input#username, input#password, input#securitycode
   so a field with any other id inherits none of the padding, margins or
   font-size and ends up misaligned with the fields above it. These rules
   mirror the theme's declarations onto #totp_code. Keep in sync with
   theme/eldy/global.inc.php if Dolibarr changes them. */
.login_table input#totp_code {
    border-bottom: solid 1px rgba(180, 180, 180, .4);
    padding: 8px;
    padding-left: 12px;
    margin-left: 5px;
    margin-top: 5px;
    margin-bottom: 5px;
    margin-right: 10px;
}
.login_table input#totp_code:focus {
    outline: none !important;
}
.login_table .tdinputlogin input#totp_code {
    font-size: 1.1em;
}
/* fa-shield-alt is a wider glyph than fa-user / fa-key and the theme pins
   .fa to width:14px. Keep that box width so the input stays aligned, but let
   the glyph render past it (centred) instead of being clipped. */
.login_table .tdinputlogin span.fa-shield-alt {
    text-align: center;
    overflow: visible;
}
</style>
<script<?php echo $nonce ? ' nonce="'.$nonce.'"' : ''; ?> type="text/javascript">
(function() {
    function buildFromTemplate(usernameRow) {
        // Clone the username row so the 2FA field inherits every class and
        // attribute the theme relies on. The username row is used (not the
        // password row) because the password row embeds a <script> block for
        // the show/hide-password eye, which must not be duplicated.
        var row = usernameRow.cloneNode(true);
        row.id = 'totp2fa_row';

        var s = row.getElementsByTagName('script');
        while (s.length) { s[0].parentNode.removeChild(s[0]); }

        var cell = row.querySelector('.tdinputlogin');
        if (cell) cell.removeAttribute('id');

        var icon = row.querySelector('.fa');
        if (icon) icon.className = 'fa fa-shield-alt';

        var input = row.querySelector('input');
        if (!input) return null;
        input.type = 'text';
        input.id = 'totp_code';
        input.name = 'totp_code';
        input.placeholder = '2FA Code';
        input.value = '';
        input.maxLength = 10;
        input.tabIndex = 3;
        input.removeAttribute('autofocus');
        input.setAttribute('autocomplete', 'one-time-code');
        input.setAttribute('inputmode', 'numeric');

        return row;
    }

    function buildManually() {
        var row = document.createElement('div');
        row.id = 'totp2fa_row';
        row.className = 'trinputlogin';

        var cell = document.createElement('div');
        cell.className = 'tagtd nowraponall center valignmiddle tdinputlogin';

        var icon = document.createElement('span');
        icon.className = 'fa fa-shield-alt';

        var input = document.createElement('input');
        input.type = 'text';
        input.id = 'totp_code';
        input.name = 'totp_code';
        input.placeholder = '2FA Code';
        input.maxLength = 10;
        input.tabIndex = 3;
        input.value = '';
        input.setAttribute('autocomplete', 'one-time-code');
        input.setAttribute('inputmode', 'numeric');
        input.className = 'flat input-icon-user minwidth150 input-nobottom';

        cell.appendChild(icon);
        cell.appendChild(input);
        row.appendChild(cell);

        return row;
    }

    function addTotpField() {
        if (document.getElementById('totp2fa_row')) return;

        var passwordInput = document.querySelector('input[name="password"]');
        if (!passwordInput) return;
        var passwordRow = passwordInput.closest('.trinputlogin');
        if (!passwordRow) return;

        var row = null;
        var usernameInput = document.querySelector('input[name="username"]');
        var usernameRow = usernameInput ? usernameInput.closest('.trinputlogin') : null;
        if (usernameRow) {
            row = buildFromTemplate(usernameRow);
        }
        if (!row) {
            row = buildManually();
        }

        passwordRow.parentNode.insertBefore(row, passwordRow.nextSibling);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addTotpField);
    } else {
        addTotpField();
    }
})();
</script>
<?php
