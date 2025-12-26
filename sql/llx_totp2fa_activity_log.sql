-- Copyright (C) 2024 TOTP 2FA Module
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.

CREATE TABLE IF NOT EXISTS llx_totp2fa_activity_log (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user         INTEGER NOT NULL,
    action          VARCHAR(50) NOT NULL,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(255),
    details         TEXT,
    datec           DATETIME NOT NULL,
    entity          INTEGER DEFAULT 1 NOT NULL
) ENGINE=InnoDB;

ALTER TABLE llx_totp2fa_activity_log ADD INDEX idx_totp2fa_activity_user (fk_user);
ALTER TABLE llx_totp2fa_activity_log ADD INDEX idx_totp2fa_activity_date (datec);
ALTER TABLE llx_totp2fa_activity_log ADD INDEX idx_totp2fa_activity_action (action);
