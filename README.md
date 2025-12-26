# Dolibarr TOTP 2FA 🔐

**Free & Open Source Two-Factor Authentication for Dolibarr**

[![Dolibarr](https://img.shields.io/badge/Dolibarr-22.0%2B-blue.svg)](https://www.dolibarr.org)
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
- **Optional Enforcement** - Admin can make 2FA mandatory for specific users or groups
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

**Requirements:** Dolibarr 22.0+, PHP 7.4+, MySQL/MariaDB

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

### v1.0 (Current Release) ✅
- [x] Basic TOTP implementation (RFC 6238)
- [x] QR code generation for easy setup
- [x] User profile integration (tab in user settings)
- [x] Login page integration (2FA field on main login)
- [x] Backup codes (10 single-use codes)
- [x] Admin configuration panel with statistics
- [x] German and English translations
- [x] AES-256 encryption for stored secrets
- [x] Rate limiting (5 attempts/minute)

### v1.1 (Future)
- [ ] 2FA enforcement by user group
- [ ] Trusted devices (30-day bypass)
- [ ] Email notifications on 2FA changes
- [ ] Admin bypass codes
- [ ] Activity log for 2FA events

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

-----

**Questions?** → [GitHub Issues](https://github.com/Gerrett84/dolibarr-totp-2fa/issues)

-----

**Current Version:** 1.0.0
**Status:** Stable
**Compatibility:** Dolibarr 22.0+
