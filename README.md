# Dolibarr TOTP 2FA 🔐

**Free & Open Source Two-Factor Authentication for Dolibarr**

[![Dolibarr](https://img.shields.io/badge/Dolibarr-21.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)
[![TOTP](https://img.shields.io/badge/RFC-6238-orange.svg)](https://tools.ietf.org/html/rfc6238)

Add enterprise-grade Two-Factor Authentication (2FA) to your Dolibarr installation - completely free and open source.

-----

## 🎯 Features

- **TOTP Standard** - RFC 6238 compliant Time-based One-Time Passwords
- **Universal Compatibility** - Works with Google Authenticator, Apple Passwords, Authy, Microsoft Authenticator, etc.
- **QR Code Setup** - Scan and configure in seconds
- **User Self-Service** - Each user manages their own 2FA settings
- **Backup Codes** - Emergency access codes for recovery
- **Trusted Devices** - Skip 2FA on trusted devices for configurable days (1-90)
- **IP Blocking** - Block suspicious IPs manually, view login attempts and statistics
- **Login Logging** - Track all login attempts (success, failed 2FA, blocked)
- **Optional Enforcement** - Admin can make 2FA mandatory for specific users or groups
- **Multi-Company Support** - Works with Dolibarr Multi-Company module
- **Secure** - Industry-standard TOTP with 30-second rotating codes

-----

## 🔐 How It Works

1. **User enables 2FA** in their profile settings
2. **Scan QR code** with authenticator app (Google Auth, Apple Passwords, etc.)
3. **Enter 6-digit code** after normal login
4. **Done!** Account is now protected with 2FA

-----

## 📦 Installation

```bash
# 1. Clone into custom modules directory
cd /var/www/dolibarr/htdocs/custom
git clone https://github.com/Gerrett84/dolibarr-totp-2fa.git totp2fa

# 2. Set permissions
chown -R www-data:www-data totp2fa
chmod -R 755 totp2fa

# 3. Activate in Dolibarr
# Setup → Modules → TOTP 2FA → Activate
```

**Requirements:** Dolibarr 21.0+, PHP 7.4+, MySQL/MariaDB

-----

## 🚀 Quick Start

### For Users

1. Go to **User Profile → Security → Two-Factor Authentication**
2. Click **Enable 2FA**
3. Scan QR code with your authenticator app
4. Enter verification code
5. Save backup codes in a safe place

### For Administrators

1. Activate the module in **Setup → Modules**
2. Configure settings in **Setup → TOTP 2FA**
3. Optionally enforce 2FA for specific users/groups

-----

## 🔧 Compatible Authenticator Apps

- ✅ **Google Authenticator** (iOS, Android)
- ✅ **Apple Passwords** (iOS 15+, macOS Monterey+)
- ✅ **Microsoft Authenticator** (iOS, Android)
- ✅ **Authy** (iOS, Android, Desktop)
- ✅ **1Password** (iOS, Android, Desktop)
- ✅ **Bitwarden** (iOS, Android, Desktop)
- ✅ Any RFC 6238 compliant TOTP app

-----

## 📋 Roadmap

### v1.4 (Current Release) ✅
- [x] **IP Blocking** - Manually block suspicious IP addresses
- [x] **Login Attempt Logging** - Track all login attempts with IP, browser, timestamp
- [x] **IP Management Admin Page** - Three tabs: Login Attempts, IP Blacklist, Statistics
- [x] **Top Failing IPs** - View and block top failing IPs with one click
- [x] **Purge Old Logs** - Clean up old login attempt records
- [x] Menu only visible in Setup section (not permanently)

### v1.3.1 ✅
- [x] **Dolibarr 21 Support** - Lowered minimum version requirement
- [x] **Multi-Company Support** - Confirmed working with Multi-Company module (thanks @fefed22!)

### v1.3 ✅
- [x] **Trust Renewal** - Enter code on trusted device to renew trust period
- [x] Show remaining trust days on login page
- [x] Skip button for trusted devices (proceed without code)
- [x] Improved UX for trusted device handling

### v1.2 ✅
- [x] Trusted Devices - Skip 2FA for trusted devices (configurable 1-90 days)
- [x] Admin-only setting to enable/disable trusted devices
- [x] Detailed trusted devices overview in admin panel
- [x] Automatic device detection (Windows, Mac, Linux, iOS, Android)

### v1.1 ✅
- [x] Email notifications (2FA enabled/disabled, 3 failed attempts warning)
- [x] Activity log for all 2FA events
- [x] Admin can disable 2FA for users (emergency)
- [x] Backup codes can be used at login

### v1.0 ✅
- [x] Basic TOTP implementation (RFC 6238)
- [x] QR code generation for easy setup
- [x] User profile integration (tab in user settings)
- [x] Login page integration (2FA field on main login)
- [x] Backup codes (10 single-use codes)
- [x] Admin configuration panel with statistics
- [x] German and English translations
- [x] AES-256 encryption for stored secrets
- [x] Rate limiting (5 attempts/minute)

-----

## 🛡️ Security

- **Standard Compliant** - Implements RFC 6238 TOTP
- **Secure Secrets** - 160-bit secrets, cryptographically random
- **Rate Limiting** - Protection against brute-force attacks
- **Time-based Codes** - 30-second validity window
- **Database Encryption** - Secrets stored encrypted
- **No External Dependencies** - All TOTP logic is self-contained

-----

## 🆚 Why This Module?

Compared to commercial 2FA modules for Dolibarr:

| Feature | This Module | Commercial Modules |
|---------|-------------|-------------------|
| **Price** | Free (GPL-3.0) | €30-50 |
| **Open Source** | ✅ Yes | ❌ No |
| **TOTP/RFC 6238** | ✅ Yes | ✅ Yes |
| **QR Code Setup** | ✅ Yes | ✅ Yes |
| **Backup Codes** | ✅ Yes | ⚠️ Some |
| **Self-Service** | ✅ Yes | ✅ Yes |
| **Community Support** | ✅ Yes | ❌ Paid only |

-----

## 🤝 Contributing

Contributions are welcome! This is a community project.

```bash
git checkout -b feature/NewFeature
git commit -m 'Add: Cool Feature'
git push origin feature/NewFeature
# → Create Pull Request
```

-----

## 📄 License

GPL v3 or higher - Same as Dolibarr

-----

## 👤 Author

**Gerrett84** - [GitHub](https://github.com/Gerrett84)

-----

## 🙏 Acknowledgments

- Dolibarr Community
- RFC 6238 TOTP Standard
- Open Source Security Community
- [@fefed22](https://github.com/fefed22) - Multi-Company & Dolibarr 21 compatibility testing

-----

**Questions?** → [GitHub Issues](https://github.com/Gerrett84/dolibarr-totp-2fa/issues)

-----

## ⚠️ Backup

Before upgrading, always backup your database:

```bash
# Backup 2FA tables
mysqldump -u root -p dolibarr llx_totp2fa_user_settings llx_totp2fa_backup_codes llx_totp2fa_activity_log llx_totp2fa_trusted_devices llx_totp2fa_login_attempts llx_totp2fa_ip_blacklist > totp2fa_backup.sql
```

-----

**Current Version:** 1.4.0
**Status:** Stable
**Compatibility:** Dolibarr 21.0+
