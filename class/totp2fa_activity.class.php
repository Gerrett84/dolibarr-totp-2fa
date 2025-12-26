<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       class/totp2fa_activity.class.php
 * \ingroup    totp2fa
 * \brief      Activity logging for 2FA events
 */

/**
 * Class for logging 2FA activities
 */
class Totp2faActivity
{
    /**
     * @var DoliDB Database handler
     */
    public $db;

    /**
     * @var string Error message
     */
    public $error = '';

    // Activity types
    const ACTION_2FA_ENABLED = '2fa_enabled';
    const ACTION_2FA_DISABLED = '2fa_disabled';
    const ACTION_LOGIN_SUCCESS = 'login_success';
    const ACTION_LOGIN_FAILED = 'login_failed';
    const ACTION_BACKUP_CODE_USED = 'backup_code_used';
    const ACTION_SECRET_REGENERATED = 'secret_regenerated';

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Log an activity
     *
     * @param int    $user_id   User ID
     * @param string $action    Action type (use class constants)
     * @param string $details   Additional details (optional)
     * @return int              >0 if OK, <0 if KO
     */
    public function log($user_id, $action, $details = '')
    {
        global $conf;

        $ip_address = $this->getClientIP();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_activity_log (";
        $sql .= "fk_user, action, ip_address, user_agent, details, datec, entity";
        $sql .= ") VALUES (";
        $sql .= ((int) $user_id).", ";
        $sql .= "'".$this->db->escape($action)."', ";
        $sql .= "'".$this->db->escape($ip_address)."', ";
        $sql .= "'".$this->db->escape($user_agent)."', ";
        $sql .= "'".$this->db->escape($details)."', ";
        $sql .= "'".$this->db->idate(dol_now())."', ";
        $sql .= ((int) $conf->entity);
        $sql .= ")";

        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            return -1;
        }

        return 1;
    }

    /**
     * Get activity log for a user
     *
     * @param int $user_id  User ID (0 for all users)
     * @param int $limit    Maximum number of records
     * @param int $offset   Offset for pagination
     * @return array        Array of activity records
     */
    public function getLog($user_id = 0, $limit = 50, $offset = 0)
    {
        global $conf;

        $logs = array();

        $sql = "SELECT l.rowid, l.fk_user, l.action, l.ip_address, l.user_agent, l.details, l.datec,";
        $sql .= " u.login, u.firstname, u.lastname";
        $sql .= " FROM ".MAIN_DB_PREFIX."totp2fa_activity_log as l";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."user as u ON l.fk_user = u.rowid";
        $sql .= " WHERE l.entity = ".((int) $conf->entity);

        if ($user_id > 0) {
            $sql .= " AND l.fk_user = ".((int) $user_id);
        }

        $sql .= " ORDER BY l.datec DESC";
        $sql .= " LIMIT ".((int) $limit);
        if ($offset > 0) {
            $sql .= " OFFSET ".((int) $offset);
        }

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $logs[] = array(
                    'id' => $obj->rowid,
                    'user_id' => $obj->fk_user,
                    'user_login' => $obj->login,
                    'user_name' => trim($obj->firstname.' '.$obj->lastname),
                    'action' => $obj->action,
                    'ip_address' => $obj->ip_address,
                    'user_agent' => $obj->user_agent,
                    'details' => $obj->details,
                    'date' => $this->db->jdate($obj->datec)
                );
            }
        }

        return $logs;
    }

    /**
     * Count failed login attempts for a user in the last X minutes
     *
     * @param int $user_id  User ID
     * @param int $minutes  Time window in minutes
     * @return int          Number of failed attempts
     */
    public function countRecentFailedAttempts($user_id, $minutes = 5)
    {
        global $conf;

        // Use Dolibarr's date functions for consistency
        $timeLimit = dol_now() - ($minutes * 60);

        $sql = "SELECT COUNT(*) as cnt FROM ".MAIN_DB_PREFIX."totp2fa_activity_log";
        $sql .= " WHERE fk_user = ".((int) $user_id);
        $sql .= " AND action = 'login_failed'";
        $sql .= " AND datec > '".$this->db->idate($timeLimit)."'";
        $sql .= " AND entity = ".((int) $conf->entity);

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return (int) $obj->cnt;
        }

        return 0;
    }

    /**
     * Get client IP address
     *
     * @return string IP address
     */
    private function getClientIP()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return trim($ip);
    }

    /**
     * Get action label for display
     *
     * @param string $action Action code
     * @return string        Translated label
     */
    public static function getActionLabel($action)
    {
        global $langs;

        $langs->load("totp2fa@totp2fa");

        $labels = array(
            self::ACTION_2FA_ENABLED => $langs->trans("Activity2FAEnabled"),
            self::ACTION_2FA_DISABLED => $langs->trans("Activity2FADisabled"),
            self::ACTION_LOGIN_SUCCESS => $langs->trans("Activity2FALoginSuccess"),
            self::ACTION_LOGIN_FAILED => $langs->trans("Activity2FALoginFailed"),
            self::ACTION_BACKUP_CODE_USED => $langs->trans("ActivityBackupCodeUsed"),
            self::ACTION_SECRET_REGENERATED => $langs->trans("ActivitySecretRegenerated")
        );

        return isset($labels[$action]) ? $labels[$action] : $action;
    }
}
