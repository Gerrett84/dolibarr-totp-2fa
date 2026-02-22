-- IP Blacklist table
-- Stores blocked IP addresses

CREATE TABLE llx_totp2fa_ip_blacklist (
    rowid           INTEGER AUTO_INCREMENT PRIMARY KEY,
    ip_address      VARCHAR(45) NOT NULL,          -- IPv4 or IPv6 (can be CIDR notation like 192.168.1.0/24)
    reason          VARCHAR(255) DEFAULT NULL,      -- Why blocked
    blocked_by      INTEGER DEFAULT NULL,           -- User ID who blocked
    datec           DATETIME NOT NULL,              -- When blocked
    date_expiry     DATETIME DEFAULT NULL,          -- Optional: auto-unblock date (NULL = permanent)
    active          TINYINT DEFAULT 1 NOT NULL,     -- 1 = active, 0 = disabled
    entity          INTEGER DEFAULT 1 NOT NULL,
    UNIQUE INDEX idx_ip_unique (ip_address, entity),
    INDEX idx_active (active)
) ENGINE=InnoDB;
