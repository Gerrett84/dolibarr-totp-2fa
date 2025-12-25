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
<script type="text/javascript">
jQuery(document).ready(function() {
    // Check if we need to show 2FA field based on username
    var username = jQuery('input[name="username"]').val();

    if (username && username.length > 0) {
        // Username is already filled, check if user has 2FA
        checkUserHas2FA(username);
    }

    // Monitor username field changes
    jQuery('input[name="username"]').on('blur change', function() {
        var username = jQuery(this).val();
        if (username && username.length > 0) {
            checkUserHas2FA(username);
        } else {
            hide2FAField();
        }
    });

    function checkUserHas2FA(username) {
        // Make AJAX call to check if user has 2FA
        jQuery.ajax({
            url: '<?php echo dol_buildpath('/custom/totp2fa/ajax/check_user_2fa.php', 1); ?>',
            type: 'POST',
            data: {
                username: username,
                token: '<?php echo newToken(); ?>'
            },
            success: function(response) {
                var data = JSON.parse(response);
                if (data.has_2fa) {
                    show2FAField();
                } else {
                    hide2FAField();
                }
            }
        });
    }

    function show2FAField() {
        if (jQuery('#totp2fa_field').length > 0) {
            jQuery('#totp2fa_field').show();
            return;
        }

        // Create 2FA field after password field
        var passwordRow = jQuery('input[name="password"]').closest('.trinline');
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
        }
    }

    function hide2FAField() {
        jQuery('#totp2fa_field').hide();
    }
});
</script>
<?php
