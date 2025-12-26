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

// Simple approach: Always show 2FA field, server validates if needed
?>
<!-- TOTP 2FA Login Extension -->
<script type="text/javascript">
jQuery(document).ready(function() {
    // Add 2FA field after password field
    var passwordRow = jQuery('input[name="password"]').closest('.trinputlogin');

    if (passwordRow.length > 0) {
        // Clone the password row and modify it for 2FA
        var totpRow = passwordRow.clone();

        // Update the cloned row
        totpRow.attr('id', 'totp2fa_row');
        totpRow.find('#tdpasswordlogin').removeAttr('id');
        totpRow.find('#togglepassword').remove();

        // Change icon
        totpRow.find('.fa').removeClass('fa-key').addClass('fa-shield-alt');

        // Change input
        var input = totpRow.find('input');
        input.attr({
            'type': 'text',
            'id': 'totp_code',
            'name': 'totp_code',
            'placeholder': '2FA Code',
            'maxlength': '10',
            'tabindex': '3',
            'value': ''
        });
        input.removeClass('input-icon-password').addClass('input-icon-user');

        passwordRow.after(totpRow);
    }
});
</script>
<?php
