<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       lib/totp2fa.lib.php
 * \ingroup    totp2fa
 * \brief      Helper functions for TOTP 2FA
 */

/**
 * Prepare admin pages header tabs
 *
 * @return array Array of tabs
 */
function totp2fa_admin_prepare_head()
{
    global $langs, $conf;

    $langs->load("totp2fa@totp2fa");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/totp2fa/admin/setup.php", 1);
    $head[$h][1] = $langs->trans("Settings");
    $head[$h][2] = 'settings';
    $h++;

    complete_head_from_modules($conf, $langs, null, $head, $h, 'totp2fa');

    complete_head_from_modules($conf, $langs, null, $head, $h, 'totp2fa', 'remove');

    return $head;
}

/**
 * Check if user has 2FA enabled
 *
 * @param DoliDB $db Database handler
 * @param int $fk_user User ID
 * @return bool True if enabled
 */
function totp2fa_is_enabled_for_user($db, $fk_user)
{
    $sql = "SELECT is_enabled FROM ".MAIN_DB_PREFIX."totp2fa_user_settings";
    $sql .= " WHERE fk_user = ".(int)$fk_user;

    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        if ($obj) {
            return ($obj->is_enabled == 1);
        }
    }

    return false;
}

/**
 * Get 2FA statistics
 *
 * @param DoliDB $db Database handler
 * @return array Array with statistics
 */
function totp2fa_get_stats($db)
{
    $stats = array(
        'total_users' => 0,
        'users_with_2fa' => 0,
        'percentage' => 0,
    );

    // Total users
    $sql = "SELECT COUNT(*) as total FROM ".MAIN_DB_PREFIX."user WHERE entity IN (".getEntity('user').")";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['total_users'] = $obj->total;
    }

    // Users with 2FA enabled
    $sql = "SELECT COUNT(DISTINCT fk_user) as total FROM ".MAIN_DB_PREFIX."totp2fa_user_settings WHERE is_enabled = 1";
    $resql = $db->query($sql);
    if ($resql) {
        $obj = $db->fetch_object($resql);
        $stats['users_with_2fa'] = $obj->total;
    }

    // Calculate percentage
    if ($stats['total_users'] > 0) {
        $stats['percentage'] = round(($stats['users_with_2fa'] / $stats['total_users']) * 100, 1);
    }

    return $stats;
}

/**
 * Check whether an IP address equals a single address or lies within a CIDR range (IPv4/IPv6)
 *
 * @param string $ip    IP address
 * @param string $range Single IP or CIDR (e.g. 192.168.0.0/16)
 * @return bool
 */
function totp2fa_ip_matches($ip, $range)
{
    $range = trim($range);
    $bits = null;
    if (strpos($range, '/') !== false) {
        list($range, $bits) = explode('/', $range, 2);
        if (!ctype_digit($bits)) {
            return false;
        }
        $bits = (int) $bits;
    }

    $a = @inet_pton(trim($ip));
    $b = @inet_pton($range);
    if ($a === false || $b === false) {
        return false;
    }
    // Normalise IPv4-mapped IPv6 addresses (::ffff:a.b.c.d)
    $mapped = str_repeat("\0", 10)."\xff\xff";
    if (strlen($a) == 16 && strncmp($a, $mapped, 12) === 0) {
        $a = substr($a, 12);
    }
    if (strlen($b) == 16 && strncmp($b, $mapped, 12) === 0) {
        $b = substr($b, 12);
    }
    if (strlen($a) != strlen($b)) {
        return false;
    }

    $max = strlen($a) * 8;
    if ($bits === null) {
        return $a === $b;
    }
    if ($bits < 0 || $bits > $max) {
        return false;
    }

    $fullBytes = intdiv($bits, 8);
    if ($fullBytes > 0 && strncmp($a, $b, $fullBytes) !== 0) {
        return false;
    }
    $rest = $bits % 8;
    if ($rest) {
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        if ((ord($a[$fullBytes]) & $mask) !== (ord($b[$fullBytes]) & $mask)) {
            return false;
        }
    }
    return true;
}

/**
 * Validate an IP address or CIDR range
 *
 * @param string $value Value
 * @return bool
 */
function totp2fa_is_valid_ip_or_range($value)
{
    $value = trim($value);
    if (strpos($value, '/') === false) {
        return filter_var($value, FILTER_VALIDATE_IP) !== false;
    }
    list($addr, $bits) = explode('/', $value, 2);
    if (filter_var($addr, FILTER_VALIDATE_IP) === false || !ctype_digit($bits)) {
        return false;
    }
    return (int) $bits <= (strpos($addr, ':') !== false ? 128 : 32);
}

/**
 * Check whether an address belongs to a trusted reverse proxy.
 * Configurable via TOTP2FA_TRUSTED_PROXIES (comma separated IPs/CIDRs);
 * default: loopback and private networks.
 *
 * @param string $ip IP address
 * @return bool
 */
function totp2fa_is_trusted_proxy($ip)
{
    $list = getDolGlobalString('TOTP2FA_TRUSTED_PROXIES');
    if ($list === '') {
        $list = '127.0.0.0/8,::1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,fc00::/7,fe80::/10';
    }
    foreach (explode(',', $list) as $range) {
        if ($range !== '' && totp2fa_ip_matches($ip, $range)) {
            return true;
        }
    }
    return false;
}

/**
 * Get the client IP. X-Forwarded-For / X-Real-IP are only honoured when the direct peer
 * is a trusted proxy; the XFF chain is walked from the right so client-supplied
 * entries cannot be spoofed.
 *
 * @return string
 */
function totp2fa_get_client_ip()
{
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim($_SERVER['REMOTE_ADDR']) : '';
    if ($remote === '' || !totp2fa_is_trusted_proxy($remote)) {
        return $remote !== '' ? $remote : '0.0.0.0';
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_reverse(array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])));
        foreach ($chain as $candidate) {
            if (filter_var($candidate, FILTER_VALIDATE_IP) === false) {
                break;
            }
            if (!totp2fa_is_trusted_proxy($candidate)) {
                return $candidate;
            }
            $last = $candidate;
        }
        if (!empty($last)) {
            return $last;
        }
    }
    if (!empty($_SERVER['HTTP_X_REAL_IP']) && filter_var(trim($_SERVER['HTTP_X_REAL_IP']), FILTER_VALIDATE_IP) !== false) {
        return trim($_SERVER['HTTP_X_REAL_IP']);
    }
    return $remote;
}

/**
 * Name of the trusted-device cookie for a user
 *
 * @param int $fk_user User id
 * @return string
 */
function totp2fa_device_cookie_name($fk_user)
{
    return 'totp2fa_did_'.(int) $fk_user;
}

/**
 * Check whether the current browser holds a valid trusted-device token for the user
 *
 * @param DoliDB $db      Database handler
 * @param int    $fk_user User id
 * @return bool
 */
function totp2fa_is_device_trusted($db, $fk_user)
{
    $name = totp2fa_device_cookie_name($fk_user);
    $token = isset($_COOKIE[$name]) ? $_COOKIE[$name] : '';
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return false;
    }

    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " WHERE fk_user = ".(int) $fk_user;
    $sql .= " AND device_hash = '".$db->escape(hash('sha256', $token))."'";
    $sql .= " AND trusted_until > NOW()";
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $db->query("UPDATE ".MAIN_DB_PREFIX."totp2fa_trusted_devices SET date_last_use = NOW() WHERE rowid = ".(int) $obj->rowid);
        return true;
    }
    return false;
}

/**
 * Register the current browser as trusted device: random token in an HttpOnly cookie,
 * only its hash is stored in the database.
 *
 * @param DoliDB $db      Database handler
 * @param int    $fk_user User id
 * @param int    $days    Trust duration in days
 * @return bool
 */
function totp2fa_trust_device($db, $fk_user, $days)
{
    $name = totp2fa_device_cookie_name($fk_user);

    // Drop the row of the token currently held by this browser (renewal)
    if (!empty($_COOKIE[$name]) && preg_match('/^[a-f0-9]{64}$/', $_COOKIE[$name])) {
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."totp2fa_trusted_devices WHERE fk_user = ".(int) $fk_user." AND device_hash = '".$db->escape(hash('sha256', $_COOKIE[$name]))."'");
    }

    $token = bin2hex(random_bytes(32));
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    $deviceName = 'Unbekanntes Gerät';
    if (preg_match('/iPhone|iPad/', $userAgent)) {
        $deviceName = 'Apple iOS';
    } elseif (preg_match('/Android/', $userAgent)) {
        $deviceName = 'Android';
    } elseif (preg_match('/Windows/', $userAgent)) {
        $deviceName = 'Windows PC';
    } elseif (preg_match('/Macintosh/', $userAgent)) {
        $deviceName = 'Mac';
    } elseif (preg_match('/Linux/', $userAgent)) {
        $deviceName = 'Linux';
    }

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_trusted_devices";
    $sql .= " (fk_user, device_hash, device_name, ip_address, user_agent, trusted_until, date_creation)";
    $sql .= " VALUES (".(int) $fk_user.",";
    $sql .= " '".$db->escape(hash('sha256', $token))."',";
    $sql .= " '".$db->escape($deviceName)."',";
    $sql .= " '".$db->escape(totp2fa_get_client_ip())."',";
    $sql .= " '".$db->escape(substr($userAgent, 0, 500))."',";
    $sql .= " DATE_ADD(NOW(), INTERVAL ".(int) $days." DAY), NOW())";
    if (!$db->query($sql)) {
        return false;
    }

    if (!headers_sent()) {
        setcookie($name, $token, array(
            'expires' => time() + (int) $days * 86400,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }
    return true;
}
