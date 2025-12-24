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

CREATE TABLE IF NOT EXISTS llx_totp2fa_backup_codes (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL COMMENT 'SHA-256 hash of backup code',
    is_used TINYINT DEFAULT 0 COMMENT '0=unused, 1=used',
    date_created DATETIME NOT NULL,
    date_used DATETIME DEFAULT NULL,
    KEY idx_fk_user (fk_user),
    KEY idx_is_used (is_used)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
