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
        if (empty($conf->totp2fa) || empty($conf->totp2fa->enabled)) {
            return 0;
        }

        // Note: In Dolibarr, we need to hook into the authentication process
        // This is better done via a hook in the login page itself
        // The trigger system is mainly for database events

        return $ret;
    }
}
