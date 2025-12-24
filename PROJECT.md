# TOTP 2FA Module - Technical Documentation

## 🏗️ Architecture

### Module Structure

```
totp2fa/
├── core/
│   └── modules/
│       └── modTOTP2FA.class.php      # Module definition
├── admin/
│   └── setup.php                      # Admin configuration page
├── class/
│   ├── totp.class.php                 # TOTP core logic (RFC 6238)
│   └── user2fa.class.php              # User 2FA settings
├── lib/
│   ├── totp2fa.lib.php                # Helper functions
│   └── qrcode.lib.php                 # QR code generation
├── langs/
│   ├── de_DE/
│   │   └── totp2fa.lang               # German translations
│   └── en_US/
│       └── totp2fa.lang               # English translations
├── sql/
│   ├── llx_totp2fa_user_settings.sql  # User settings table
│   └── llx_totp2fa_backup_codes.sql   # Backup codes table
└── img/
    └── totp2fa.png                    # Module icon
```

## 🔐 TOTP Implementation (RFC 6238)

### Algorithm Overview

1. **Secret Generation**
   - 160-bit random secret (Base32 encoded)
   - Stored encrypted in database
   - Generated once per user on 2FA activation

2. **Code Generation**
   ```php
   TOTP = HOTP(Secret, T)
   where T = floor(current_unix_time / 30)
   ```

3. **Validation**
   - Accept current code (T)
   - Accept previous code (T-1) for clock drift
   - Accept next code (T+1) for clock drift
   - Rate limiting: max 5 attempts per minute

### Security Measures

- **Secret Storage**: AES-256 encryption in database
- **Rate Limiting**: Prevent brute-force attacks
- **Code Reuse Prevention**: Mark codes as used within time window
- **Backup Codes**: 10 single-use recovery codes
- **Session Management**: Force re-authentication on 2FA changes

## 📊 Database Schema

### llx_totp2fa_user_settings

```sql
CREATE TABLE llx_totp2fa_user_settings (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    secret VARCHAR(255) NOT NULL,        -- Encrypted TOTP secret
    is_enabled TINYINT DEFAULT 0,        -- 0=disabled, 1=enabled
    last_used_code VARCHAR(10),          -- Prevent code reuse
    last_used_time INT,                  -- Timestamp of last code use
    failed_attempts INT DEFAULT 0,       -- Failed login attempts
    last_failed_attempt INT,             -- Timestamp of last failure
    date_created DATETIME,
    date_modified DATETIME,
    UNIQUE KEY uk_fk_user (fk_user)
);
```

### llx_totp2fa_backup_codes

```sql
CREATE TABLE llx_totp2fa_backup_codes (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_user INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,     -- SHA-256 hash of backup code
    is_used TINYINT DEFAULT 0,           -- 0=unused, 1=used
    date_created DATETIME,
    date_used DATETIME,
    KEY idx_fk_user (fk_user)
);
```

## 🔄 Login Flow

### Without 2FA (Current)
```
1. User enters username/password
2. Authentication check
3. Session created → Dashboard
```

### With 2FA (New)
```
1. User enters username/password
2. Authentication check (standard Dolibarr)
3. Check if user has 2FA enabled
   ├─ NO  → Session created → Dashboard
   └─ YES → Partial session created
           ├─ Show 2FA code entry page
           ├─ User enters 6-digit code
           ├─ Validate TOTP code
           │  ├─ Valid → Full session → Dashboard
           │  └─ Invalid → Show error, retry
           └─ "Use backup code" option
```

## 🎨 User Interface

### User Profile - 2FA Settings

**Location:** User → Profile → Security → Two-Factor Authentication

**States:**
1. **Disabled** (Initial State)
   - Show explanation of 2FA benefits
   - Button: "Enable Two-Factor Authentication"

2. **Setup** (After clicking Enable)
   - Display QR code
   - Show manual secret (for manual entry)
   - Instructions for scanning
   - Verification code input
   - Button: "Verify and Enable"

3. **Enabled** (After successful setup)
   - Status: "2FA is active ✓"
   - Show backup codes (one-time display)
   - Button: "Download Backup Codes"
   - Button: "Regenerate Secret" (disables current, starts new setup)
   - Button: "Disable 2FA"

### Login Page Modifications

**New 2FA Code Entry Page:**
```
┌──────────────────────────────────┐
│  Two-Factor Authentication       │
│                                  │
│  Enter the 6-digit code from     │
│  your authenticator app:         │
│                                  │
│  ┌────┬────┬────┬────┬────┬────┐│
│  │    │    │    │    │    │    ││
│  └────┴────┴────┴────┴────┴────┘│
│                                  │
│  [ Verify ]                      │
│                                  │
│  ──────── or ────────            │
│                                  │
│  [ Use Backup Code ]             │
│  [ Cancel Login ]                │
└──────────────────────────────────┘
```

## 🚀 Development Phases

### Phase 1: Core TOTP Implementation
- [x] Create repository structure
- [ ] Implement TOTP class (RFC 6238)
  - [ ] Secret generation
  - [ ] Code generation
  - [ ] Code validation
  - [ ] Time drift handling
- [ ] Database schema
- [ ] Unit tests for TOTP functions

### Phase 2: User Interface
- [ ] User profile integration
  - [ ] Enable/disable toggle
  - [ ] QR code display
  - [ ] Secret regeneration
- [ ] QR code generation
- [ ] Backup codes generation
- [ ] Setup wizard

### Phase 3: Login Integration
- [ ] Hook into Dolibarr login process
- [ ] 2FA code entry page
- [ ] Session management
- [ ] Rate limiting
- [ ] Error handling

### Phase 4: Admin Features
- [ ] Admin configuration page
  - [ ] Enable/disable module
  - [ ] View 2FA usage stats
  - [ ] Force 2FA for specific users
- [ ] User management
  - [ ] Admin can disable user's 2FA (emergency)
  - [ ] View 2FA status per user

### Phase 5: Polish & Release
- [ ] Translations (DE/EN)
- [ ] Documentation
- [ ] Installation guide
- [ ] Security audit
- [ ] Release v1.0

## 🧪 Testing Plan

### Unit Tests
- TOTP secret generation
- TOTP code generation
- TOTP code validation
- Time drift handling
- Backup code generation
- Backup code validation

### Integration Tests
- User enables 2FA
- User disables 2FA
- Login with 2FA
- Login with backup code
- Failed login attempts
- Rate limiting

### Security Tests
- Brute force protection
- Code reuse prevention
- Secret encryption
- Session hijacking prevention

## 📚 Dependencies

### Required
- PHP 7.4+ (for Dolibarr 22.0+)
- PHP Extensions:
  - `openssl` - For secret encryption
  - `gd` or `imagick` - For QR code generation
  - `hash` - For HMAC-SHA1

### Optional
- `random_bytes()` - For cryptographic random (PHP 7.0+)

## 🔗 External Libraries

We'll implement TOTP from scratch to avoid external dependencies, but for reference:

- RFC 6238: TOTP specification
- RFC 4226: HOTP specification (base for TOTP)
- Base32 encoding for secrets

## 📖 Resources

- [RFC 6238 - TOTP](https://tools.ietf.org/html/rfc6238)
- [RFC 4226 - HOTP](https://tools.ietf.org/html/rfc4226)
- [Google Authenticator Key URI Format](https://github.com/google/google-authenticator/wiki/Key-Uri-Format)
- [Dolibarr Module Development Guide](https://wiki.dolibarr.org/index.php/Module_development)

## 🎯 Goals

1. **Free Alternative** - Provide free 2FA for Dolibarr users
2. **Security First** - Follow industry best practices
3. **Easy to Use** - Simple setup and usage
4. **Universal Compatibility** - Work with all TOTP apps
5. **Open Source** - Transparent and community-driven
