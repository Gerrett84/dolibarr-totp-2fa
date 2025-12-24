# Installation Guide - TOTP 2FA Module

## 📋 Requirements

- **Dolibarr:** Version 22.0 or higher
- **PHP:** 7.4 or higher
- **PHP Extensions:**
  - `openssl` - For encryption
  - `hash` - For HMAC operations
  - `mbstring` - For string operations
- **Database:** MySQL 5.6+ or MariaDB 10.0+
- **Permissions:** Write access to Dolibarr custom modules directory

## 📦 Installation

### Method 1: Git Clone (Recommended for Development)

```bash
# Navigate to Dolibarr's custom modules directory
cd /var/www/dolibarr/htdocs/custom

# Clone the repository
git clone https://github.com/Gerrett84/dolibarr-totp-2fa.git totp2fa

# Set correct permissions
chown -R www-data:www-data totp2fa
chmod -R 755 totp2fa
```

### Method 2: Download ZIP

```bash
# Download the latest release
cd /var/www/dolibarr/htdocs/custom
wget https://github.com/Gerrett84/dolibarr-totp-2fa/archive/refs/heads/master.zip

# Extract
unzip master.zip
mv dolibarr-totp-2fa-master totp2fa

# Set permissions
chown -R www-data:www-data totp2fa
chmod -R 755 totp2fa
```

### Method 3: Dolibarr Module Installer

1. Download the ZIP file from GitHub
2. Log into Dolibarr as Administrator
3. Go to **Home → Setup → Modules → Deploy/Install external app/module**
4. Upload the ZIP file
5. Click Install

## ⚙️ Configuration

### 1. Activate the Module

1. Log into Dolibarr as Administrator
2. Go to **Home → Setup → Modules/Applications**
3. Find "TOTP 2FA" in the list
4. Click **Activate**

The module will automatically:
- Create database tables (`llx_totp2fa_user_settings`, `llx_totp2fa_backup_codes`)
- Add menu entries
- Register authentication hooks

### 2. Verify Installation

1. Check that module is active: **Setup → Modules → TOTP 2FA** (green checkmark)
2. Access admin page: **Setup → TOTP 2FA**
3. Verify statistics show: "Module is active"

## 👤 User Setup

### For Users

1. Go to **User Menu (top right) → User Card**
2. Click on **2FA** tab
3. Click **Enable 2FA**
4. Scan QR code with your authenticator app:
   - Google Authenticator (iOS/Android)
   - Apple Passwords (iOS 15+, macOS Monterey+)
   - Microsoft Authenticator
   - Authy
   - Any RFC 6238 TOTP app
5. Enter the 6-digit code to verify
6. **IMPORTANT:** Save your 10 backup codes in a secure location
7. Done! 2FA is now active

### For Administrators

Administrators can:
- View 2FA statistics: **Setup → TOTP 2FA**
- See which users have 2FA enabled
- Access module configuration

**Note:** Administrators cannot enable 2FA for other users - each user must enable it themselves. This ensures only the user has access to their secret.

## 🔐 Login Flow

### Without 2FA (Standard)
```
1. Enter username/password
2. Click Login
3. → Dashboard
```

### With 2FA (Enhanced Security)
```
1. Enter username/password
2. Click Login
3. → 2FA Code Entry Page
4. Enter 6-digit code from authenticator app
5. → Dashboard
```

### Using Backup Codes
```
1. Enter username/password
2. Click Login
3. → 2FA Code Entry Page
4. Click "Use Backup Code"
5. Enter backup code (format: 1234-5678)
6. → Dashboard
```

**Note:** Backup codes are single-use only!

## 🔧 Troubleshooting

### Module Not Appearing in List

**Solution:**
```bash
# Clear Dolibarr cache
cd /var/www/dolibarr/htdocs
rm -rf documents/admin/temp/*

# Check file permissions
ls -la custom/totp2fa/
chown -R www-data:www-data custom/totp2fa
```

### Database Tables Not Created

**Solution:**
```bash
# Deactivate and reactivate module
# Or manually run SQL files:
mysql -u dolibarr_user -p dolibarr_db < custom/totp2fa/sql/llx_totp2fa_user_settings.sql
mysql -u dolibarr_user -p dolibarr_db < custom/totp2fa/sql/llx_totp2fa_backup_codes.sql
```

### QR Code Not Displaying

**Causes:**
- External QR code service (quickchart.io) is blocked by firewall
- No internet connection

**Solution:**
- Use manual entry instead (secret is displayed below QR code)
- Or configure firewall to allow quickchart.io

### "Invalid Code" Error

**Possible causes:**
1. **Time drift** - Server and phone time not synchronized
   - **Solution:** Sync server time with NTP
   ```bash
   sudo ntpdate pool.ntp.org
   ```

2. **Code already used** - TOTP codes are single-use within 30-second window
   - **Solution:** Wait for new code to generate

3. **Wrong secret** - Scanned wrong QR code
   - **Solution:** Regenerate secret and re-scan

4. **Rate limiting** - Too many failed attempts
   - **Solution:** Wait 1 minute before retrying

### Lost Phone / Cannot Access Authenticator App

**Solution 1: Use Backup Code**
- Enter one of your saved backup codes at login

**Solution 2: Administrator Assistance**
- Ask administrator to disable your 2FA
- Administrator goes to: User → [Your User] → 2FA → Disable 2FA
- You can then re-enable and re-scan

## 🔄 Upgrade

### From Git

```bash
cd /var/www/dolibarr/htdocs/custom/totp2fa
git pull origin master

# Deactivate and reactivate module in Dolibarr
# Home → Setup → Modules → TOTP 2FA → Deactivate → Activate
```

### From ZIP

1. Backup current installation
2. Download new version
3. Extract and replace files
4. Deactivate and reactivate module

## 🗑️ Uninstallation

### 1. Disable 2FA for All Users

**Important:** Before uninstalling, disable 2FA for all users or they won't be able to log in!

```sql
-- Disable 2FA for all users
UPDATE llx_totp2fa_user_settings SET is_enabled = 0;
```

### 2. Deactivate Module

1. Go to **Setup → Modules**
2. Find "TOTP 2FA"
3. Click **Deactivate**

### 3. Remove Files

```bash
cd /var/www/dolibarr/htdocs/custom
rm -rf totp2fa
```

### 4. Remove Database Tables (Optional)

```sql
DROP TABLE IF EXISTS llx_totp2fa_user_settings;
DROP TABLE IF EXISTS llx_totp2fa_backup_codes;
```

## 📞 Support

- **Documentation:** [README.md](README.md)
- **Issues:** [GitHub Issues](https://github.com/Gerrett84/dolibarr-totp-2fa/issues)
- **Project:** [PROJECT.md](PROJECT.md)

## 🔒 Security Notes

- **Secrets are encrypted** in database using AES-256
- **Backup codes are hashed** using SHA-256
- **Rate limiting** prevents brute-force attacks (5 attempts/minute)
- **Code reuse prevention** blocks replay attacks
- **Time-based codes** expire every 30 seconds
- **HTTPS recommended** for production use

## ✅ Post-Installation Checklist

- [ ] Module activated successfully
- [ ] Admin page accessible
- [ ] Test user can enable 2FA
- [ ] QR code displays correctly
- [ ] Code verification works
- [ ] Backup codes generated and saved
- [ ] Login with 2FA works
- [ ] Backup code login works
- [ ] Server time synchronized with NTP
- [ ] HTTPS enabled (recommended)

---

**Installation complete!** Your Dolibarr installation now has enterprise-grade Two-Factor Authentication. 🎉
