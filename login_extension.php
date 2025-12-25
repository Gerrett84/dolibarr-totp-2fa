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
 */

// This is included by Dolibarr's main login page via hooks

global $conf, $db, $langs;

// Only run if module is enabled
if (empty($conf->totp2fa->enabled)) {
    return;
}

$langs->load("totp2fa@totp2fa");

// Get username if submitted
$username = GETPOST('username', 'alpha');
$totp_code = GETPOST('totp_code', 'alpha');

// Check if user has 2FA enabled (only if username is provided)
$show_2fa_field = false;
if (!empty($username)) {
    dol_include_once('/totp2fa/class/user2fa.class.php');

    // Get user ID by username
    $sql = "SELECT u.rowid FROM ".MAIN_DB_PREFIX."user as u";
    $sql .= " WHERE u.login = '".$db->escape($username)."'";
    $sql .= " AND u.entity IN (".getEntity('user').")";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $user_id = $obj->rowid;

        // Check if this user has 2FA enabled
        $user2fa = new User2FA($db);
        $result = $user2fa->fetch($user_id);

        if ($result > 0 && $user2fa->is_enabled) {
            $show_2fa_field = true;
        }
    }
}

// JavaScript to add 2FA field dynamically
?>
<!-- ======================================== -->
<!-- TOTP 2FA LOGIN EXTENSION LOADED         -->
<!-- Module enabled: <?php echo $conf->totp2fa->enabled ? 'YES' : 'NO'; ?> -->
<!-- ======================================== -->
<div id="totp2fa_debug" style="position: fixed; top: 10px; right: 10px; background: #ff9800; color: white; padding: 5px 10px; border-radius: 3px; font-size: 11px; z-index: 9999;">
    TOTP 2FA Module Active - Check Console (F12)
</div>
<script type="text/javascript">
console.log('TOTP 2FA: Script loading...');

if (typeof jQuery === 'undefined') {
    console.error('TOTP 2FA: jQuery not found!');
} else {
    console.log('TOTP 2FA: jQuery found, version: ' + jQuery.fn.jquery);
}

jQuery(document).ready(function() {
    console.log('TOTP 2FA: Document ready');

    // Check if we need to show 2FA field based on username
    var username = jQuery('input[name="username"]').val();
    console.log('TOTP 2FA: Current username value: "' + username + '"');

    if (username && username.length > 0) {
        // Username is already filled, check if user has 2FA
        console.log('TOTP 2FA: Username found, checking 2FA status...');
        checkUserHas2FA(username);
    }

    // Monitor username field changes
    jQuery('input[name="username"]').on('blur change', function() {
        var username = jQuery(this).val();
        console.log('TOTP 2FA: Username field changed to: "' + username + '"');
        if (username && username.length > 0) {
            checkUserHas2FA(username);
        } else {
            hide2FAField();
        }
    });

    function checkUserHas2FA(username) {
        console.log('TOTP 2FA: Making AJAX request for username: ' + username);

        // Make AJAX call to check if user has 2FA
        // Using direct DB version to bypass main.inc.php issues
        jQuery.ajax({
            url: '/custom/totp2fa/ajax/check_user_2fa_direct.php',
            type: 'POST',
            data: {
                username: username
            },
            success: function(response) {
                console.log('TOTP 2FA: AJAX response received:', response);
                try {
                    var data = JSON.parse(response);
                    console.log('TOTP 2FA: Parsed data:', data);
                    if (data.has_2fa) {
                        console.log('TOTP 2FA: User has 2FA, showing field');
                        show2FAField();
                    } else {
                        console.log('TOTP 2FA: User does not have 2FA, hiding field');
                        hide2FAField();
                    }
                } catch (e) {
                    console.error('TOTP 2FA: Error parsing JSON:', e);
                }
            },
            error: function(xhr, status, error) {
                console.error('TOTP 2FA: AJAX error:', status, error);
                console.error('TOTP 2FA: Response:', xhr.responseText);
            }
        });
    }

    function show2FAField() {
        console.log('TOTP 2FA: show2FAField() called');

        if (jQuery('#totp2fa_field').length > 0) {
            console.log('TOTP 2FA: Field already exists, showing it');
            jQuery('#totp2fa_field').show();
            return;
        }

        // Create 2FA field after password field
        var passwordRow = jQuery('input[name="password"]').closest('.trinline');
        console.log('TOTP 2FA: Password row found:', passwordRow.length);

        if (passwordRow.length > 0) {
            var html = '<div class="tagtable centpercent" id="totp2fa_field" style="margin-top: 10px;">';
            html += '<div class="trinline">';
            html += '<div class="tdinline login_left nowraponall valignmiddle">';
            html += '<span class="fa fa-key"></span>';
            html += '</div>';
            html += '<div class="tdinline login_right nowraponall valignmiddle">';
            html += '<input type="text" id="totp_code" name="totp_code" class="flat input-lg" ';
            html += 'placeholder="<?php echo $langs->trans("Enter6DigitCode"); ?>" ';
            html += 'maxlength="10" pattern="[0-9-]*" autocomplete="off" ';
            html += 'style="font-size: 16px; letter-spacing: 2px;">';
            html += '</div>';
            html += '</div>';
            html += '<div class="trinline" style="margin-top: 5px;">';
            html += '<div class="tdinline login_left"></div>';
            html += '<div class="tdinline login_right">';
            html += '<small style="color: #666;"><?php echo $langs->trans("EnterCodeFromApp"); ?></small>';
            html += '</div>';
            html += '</div>';
            html += '</div>';

            passwordRow.after(html);
            console.log('TOTP 2FA: Field HTML inserted');
        } else {
            console.error('TOTP 2FA: Password row not found! Cannot add 2FA field.');
        }
    }

    function hide2FAField() {
        console.log('TOTP 2FA: hide2FAField() called');
        jQuery('#totp2fa_field').hide();
    }
});
</script>
<!-- TOTP 2FA Debug: Script end -->
<?php
