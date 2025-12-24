-- Copyright (C) 2024 TOTP 2FA Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.

CREATE TABLE IF NOT EXISTS llx_totp2fa_user_settings (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    secret VARCHAR(255) NOT NULL COMMENT 'Encrypted TOTP secret (Base32)',
    is_enabled TINYINT DEFAULT 0 COMMENT '0=disabled, 1=enabled',
    last_used_code VARCHAR(10) DEFAULT NULL COMMENT 'Last used code to prevent reuse',
    last_used_time INT DEFAULT NULL COMMENT 'Timestamp of last code use',
    failed_attempts INT DEFAULT 0 COMMENT 'Failed login attempts counter',
    last_failed_attempt INT DEFAULT NULL COMMENT 'Timestamp of last failed attempt',
    date_created DATETIME NOT NULL,
    date_modified DATETIME DEFAULT NULL,
    UNIQUE KEY uk_fk_user (fk_user),
    KEY idx_is_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
