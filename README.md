# Device Verification API & Frontend

A professional, light-themed, secure device verification system designed to prevent multi-accounts on Telegram bots. Using device fingerprinting (FingerprintJS) and Cloudflare Turnstile captchas, this system ensures that only unique physical devices can verify themselves on a specific bot.

## Features
- **Cloudflare Turnstile Verification**: High security verification that validates the user's captcha response on the server side.
- **Device Fingerprinting**: Captures unique browser signatures to uniquely identify physical devices.
- **Multi-Account Prevention**: Restricts different Telegram IDs from verifying on the same Telegram Bot using the same physical device.
- **Dynamic Device Mapping**: Detects and aggregates multiple devices to a single user.
- **Lightweight & Clean**: Built using vanilla PHP and a professional, borderless, responsive HTML/CSS/JS frontend.

---

## Installation & Setup

### 1. Database Configuration
Create a `.env` file in the root directory and configure your MySQL credentials:
```env
DB_NAME=verify-device
DB_USER=verify-device
DB_PASS=your-database-password
DB_HOST=127.0.0.1
DB_PORT=3306

CF_SECRET_KEY=your_cloudflare_turnstile_secret_key
```

### 2. Initialize Database Tables
Run the database setup script to create the necessary tables:
```bash
php db_setup.php
```

### 3. Deploy
Upload all files to your web server (e.g., `verify.arijitiyan.cc`). Ensure the URL structure works for:
- `index.html` (Frontend)
- `verify.php` (Verification API)
- `status.php` (Verification Query API)

---

## Usage

### User Verification Link
Redirect your users to this URL (e.g., from your Telegram bot) to perform verification:
```
https://verify.arijitiyan.cc/index.html?user={telegram_id}&bot={bot_username}
```
*Example*: `https://verify.arijitiyan.cc/index.html?user=123456789&bot=my_telegram_bot`

### Check Verification Status API
To verify if a user has completed the verification and list all associated device fingerprints, make a `GET` request:
```
https://verify.arijitiyan.cc/status.php?user={telegram_id}&bot={bot_username}
```

#### Sample Response:
```json
{
  "success": true,
  "verified": true,
  "telegram_id": "123456789",
  "bot": "my_telegram_bot",
  "devices": [
    "c5e93df14a79bb9557682db1415df8a1"
  ],
  "verified_at": "2026-06-03 12:00:00"
}
```

## Multi-Device Detection Logic
The system automatically tracks if a user utilizes multiple devices to perform verifications over time.

### How it works:
1. **Initial Verification**: When a user verifies for the first time, their Telegram ID is linked to their current device fingerprint.
2. **New Device Verification**: If the same user (using the same Telegram ID) verifies on a different bot from a different physical device (producing a new fingerprint):
   - The system detects the new fingerprint.
   - It appends the new fingerprint to the user's `devices` list (stored as a comma-separated string).
   - This records all devices associated with the account, allowing you to easily detect if a single user account is active across multiple distinct devices.

