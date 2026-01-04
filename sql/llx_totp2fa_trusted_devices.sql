-- Table for trusted devices (skip 2FA for X days)
-- Copyright (C) 2024 TOTP 2FA Module

CREATE TABLE IF NOT EXISTS llx_totp2fa_trusted_devices (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    fk_user         INTEGER NOT NULL,
    device_hash     VARCHAR(64) NOT NULL,
    device_name     VARCHAR(255),
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(512),
    trusted_until   DATETIME NOT NULL,
    date_creation   DATETIME NOT NULL,
    date_last_use   DATETIME,
    tms             TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_device (fk_user, device_hash),
    KEY idx_user (fk_user),
    KEY idx_trusted_until (trusted_until)
) ENGINE=InnoDB;
