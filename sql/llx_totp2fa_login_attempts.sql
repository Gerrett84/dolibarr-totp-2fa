-- Login attempts logging table
-- Stores all login attempts (successful and failed) for security monitoring

CREATE TABLE llx_totp2fa_login_attempts (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,          -- IPv4 or IPv6
    username        VARCHAR(128) DEFAULT NULL,      -- Attempted username
    user_agent      VARCHAR(512) DEFAULT NULL,      -- Browser/client info
    attempt_type    VARCHAR(32) NOT NULL,           -- 'success', 'failed_password', 'failed_2fa', 'blocked'
    country_code    VARCHAR(2) DEFAULT NULL,        -- Optional: GeoIP country
    datec           DATETIME NOT NULL,              -- Attempt timestamp
    entity          INTEGER DEFAULT 1 NOT NULL,
    INDEX idx_ip_address (ip_address),
    INDEX idx_datec (datec),
    INDEX idx_attempt_type (attempt_type)
) ENGINE=InnoDB;
