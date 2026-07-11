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
<script<?php echo $nonce ? ' nonce="'.$nonce.'"' : ''; ?> type="text/javascript">
(function() {
    function addTotpField() {
        if (document.getElementById('totp2fa_row')) return;

        var passwordInput = document.querySelector('input[name="password"]');
        if (!passwordInput) return;
        var passwordRow = passwordInput.closest('.trinputlogin');
        if (!passwordRow) return;

        var totpDiv = document.createElement('div');
        totpDiv.id = 'totp2fa_row';
        totpDiv.className = 'trinputlogin';

        var inner = document.createElement('div');
        inner.className = 'tagtd nowraponall center valignmiddle tdinputlogin';

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
        input.className = 'flat input-icon-user minwidth150 input-nobottom';

        inner.appendChild(icon);
        inner.appendChild(input);
        totpDiv.appendChild(inner);

        passwordRow.parentNode.insertBefore(totpDiv, passwordRow.nextSibling);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', addTotpField);
    } else {
        addTotpField();
    }
})();
</script>
<?php
