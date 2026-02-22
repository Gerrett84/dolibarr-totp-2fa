<?php
/* Copyright (C) 2024 TOTP 2FA Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

/**
 * \defgroup   totp2fa     Module TOTP 2FA
 * \brief      Two-Factor Authentication using TOTP (RFC 6238)
 * \file       core/modules/modTOTP2FA.class.php
 * \ingroup    totp2fa
 * \brief      Module descriptor for TOTP 2FA
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 * Description and activation class for module TOTP2FA
 */
class modTOTP2FA extends DolibarrModules
{
    /**
     * Constructor. Define names, constants, directories, boxes, permissions
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        // Module unique ID (must be unique!)
        // Use range 500000-600000 for external modules
        $this->numero = 500200;

        // Key for module
        $this->rights_class = 'totp2fa';

        // Family (can be 'crm', 'financial', 'hr', 'projects', 'products', 'ecm', 'technic', 'interface', 'other')
        $this->family = "interface";

        // Module position in the family on 2 digits ('01', '10', '20', ...)
        $this->module_position = '90';

        // Module label (no space allowed)
        // IMPORTANT: Must be lowercase to match directory name for hook loading!
        $this->name = strtolower(preg_replace('/^mod/i', '', get_class($this)));

        // Module description (shown in module setup)
        $this->description = "Two-Factor Authentication using TOTP (RFC 6238)";
        $this->descriptionlong = "Add Two-Factor Authentication (2FA) to Dolibarr using Time-based One-Time Passwords (TOTP). Compatible with Google Authenticator, Apple Passwords, Microsoft Authenticator, Authy, and other RFC 6238 compliant apps.";

        // Version (semantic versioning: major.minor.patch)
        $this->version = '1.4.0';

        // Editor/Publisher
        $this->editor_name = 'Gerrett84';
        $this->editor_url = 'https://github.com/Gerrett84/dolibarr-totp-2fa';

        // Key used in llx_const table to save module status enabled/disabled
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

        // Name of image file used for this module
        // Using FontAwesome shield-alt icon (standard Dolibarr icon)
        $this->picto = 'fa-shield-alt';

        // Dependencies
        $this->depends = array(); // List of module class names that must be enabled before this one
        $this->requiredby = array(); // List of module class names to disable if this one is disabled
        $this->conflictwith = array(); // List of module class names this module conflicts with
        $this->langfiles = array("totp2fa@totp2fa");
        $this->phpmin = array(7, 4); // Minimum PHP version required
        $this->need_dolibarr_version = array(22, 0); // Minimum Dolibarr version required

        // Constants
        $this->const = array();

        // Config page - accessible via module setup gear icon
        $this->config_page_url = array("setup.php@totp2fa");

        // Array to add module hooks
        $this->module_parts = array(
            'hooks' => array(
                'main',
                'mainloginpage',  // For getLoginPageExtraOptions hook
                'login'           // For beforeLoginAuthentication hook
            )
        );

        // Boxes/Widgets
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Tabs - Add 2FA tab to user profile
        $this->tabs = array(
            'user:+2fa:TwoFactorAuth:totp2fa@totp2fa:$conf->totp2fa->enabled:/custom/totp2fa/user_setup.php?id=__ID__'
        );

        // Permissions
        $this->rights = array();
        $r = 0;

        // Admin permission
        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Administer TOTP 2FA settings';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'admin';
        $this->rights[$r][5] = 'write';

        // Menu entries - only visible when in Home > Setup area
        $this->menu = array();
        $r = 0;

        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=home,fk_leftmenu=setup',
            'type' => 'left',
            'titre' => 'TOTP2FASetup',
            'url' => '/custom/totp2fa/admin/setup.php?mainmenu=home&leftmenu=setup',
            'langs' => 'totp2fa@totp2fa',
            'position' => 450,
            'enabled' => 'isModEnabled("totp2fa") && preg_match(\'/^(setup|all)/\', $leftmenu)',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 0,
        );
    }

    /**
     * Function called when module is enabled
     * The init function adds constants, boxes, permissions and menus
     * It also creates data directories
     *
     * @param string $options Options when enabling module
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        // Load sql files
        $result = $this->loadTables();
        if ($result < 0) {
            return -1;
        }

        // Create data directory
        $dir = DOL_DATA_ROOT.'/totp2fa';
        if (!is_dir($dir)) {
            dol_mkdir($dir);
        }

        // Permissions
        $this->remove($options);

        $sql = array();

        return $this->_init($sql, $options);
    }

    /**
     * Function called when module is disabled
     * Remove from database constants, boxes and permissions from Dolibarr database
     * Data directories are not deleted
     *
     * @param string $options Options when disabling module
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }

    /**
     * Load database tables
     *
     * @return int <0 if KO, >0 if OK
     */
    private function loadTables()
    {
        return $this->_load_tables('/totp2fa/sql/');
    }
}
