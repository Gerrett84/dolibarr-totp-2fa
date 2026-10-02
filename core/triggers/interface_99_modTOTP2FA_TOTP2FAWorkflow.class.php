<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file       core/triggers/interface_99_modTOTP2FA_TOTP2FAWorkflow.class.php
 * \ingroup    totp2fa
 * \brief      Trigger file for 2FA login workflow
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
dol_include_once('/totp2fa/class/user2fa.class.php');

/**
 * Trigger class for TOTP 2FA workflow
 */
class InterfaceTOTP2FAWorkflow extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "totp2fa";
        $this->description = "TOTP 2FA Login Workflow Triggers";
        $this->version = '1.0';
        $this->picto = 'totp2fa@totp2fa';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }

    /**
     * Execute action
     *
     * @param array         $parameters Parameters
     * @param CommonObject  $object     Object
     * @param string        $action     Action name
     * @param HookManager   $hookmanager Hook manager
     * @return int 0 if OK, <0 if KO
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (!is_object($conf) || !is_object($langs) || !is_object($user)) {
            dol_syslog("Trigger '".$this->name."' called with wrong parameters", LOG_ERR);
            return -1;
        }

        $ret = 0;

        // Do nothing if module is not enabled
        if (!isModEnabled('totp2fa')) {
            return 0;
        }

        dol_include_once('/totp2fa/lib/totp2fa.lib.php');

        if ($action == 'USER_LOGIN') {
            // 2FA code and password were both accepted: now trust this browser (if requested)
            if (!empty($_SESSION['totp2fa_trust_pending']) && (int) $_SESSION['totp2fa_trust_pending'] === (int) $user->id) {
                totp2fa_trust_device($this->db, $user->id, getDolGlobalInt('TOTP2FA_TRUSTED_DEVICE_DAYS', 30));
            }
            unset($_SESSION['totp2fa_trust_pending']);
        } elseif ($action == 'USER_LOGIN_FAILED') {
            unset($_SESSION['totp2fa_trust_pending']);
            // Log wrong passwords (2FA failures are already logged by the login hook)
            if (empty($GLOBALS['totp2fa_attempt_logged'])) {
                $username = GETPOST('username', 'alphanohtml', 2);
                if ($username !== '') {
                    $sql = "INSERT INTO ".MAIN_DB_PREFIX."totp2fa_login_attempts";
                    $sql .= " (ip_address, username, user_agent, attempt_type, datec, entity)";
                    $sql .= " VALUES ('".$this->db->escape(totp2fa_get_client_ip())."',";
                    $sql .= " '".$this->db->escape(dol_trunc($username, 128, 'right', 'UTF-8', 1))."',";
                    $sql .= " '".$this->db->escape(substr(isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '', 0, 500))."',";
                    $sql .= " 'failed_password', NOW(), ".(int) $conf->entity.")";
                    $this->db->query($sql);
                }
            }
        }

        return $ret;
    }
}
