
<h1 align="center">Lockfront</h1>

<p align="center">
  <strong>Password-protect your WordPress site while it's under development.</strong><br>
  Clean, fast, zero dependencies.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-%3E%3D6.0-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.0+">
  <img src="https://img.shields.io/badge/PHP-%3E%3D7.4-777bb4?logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/badge/License-GPL--2.0-green" alt="GPL-2.0">
  <img src="https://img.shields.io/badge/Version-1.0.0-blue" alt="Version 1.0.0">
  <img src="https://img.shields.io/badge/Tested%20up%20to-WP%206.9-21759b" alt="Tested up to WP 6.9">
</p>

---

## Overview

Lockfront places a stylish, fully customisable password gate in front of your entire WordPress site while you build or maintain it. Once a visitor enters the correct password, a secure cookie lets them through without being asked again.

It is built entirely on native WordPress APIs — **no external frameworks, no npm build step, no bloat**.

---

## Features

### 🔒 Core Protection
- Enable / disable toggle — turn the gate on or off instantly
- Configurable site password with show / hide button
- Unlock duration in days, or browser-session only
- wp-admin is always excluded — you never lock yourself out
- Bypass for already logged-in administrators

### 🛡️ Security
- **Brute-force protection** — configurable max attempts and rolling time window
- **IP whitelist** — per-IP address and CIDR notation (e.g. `192.168.0.0/24`)
- **HTTP status code** — choose `503 Service Unavailable` (with `Retry-After` for SEO), `401 Unauthorized`, or `200 OK`
- HMAC-based secure cookies tied to the current password
- Nonce verification and capability checks on all admin actions
- Full input sanitisation and output escaping throughout

### 🚪 Bypass Mechanisms
- **URL Key Bypass** — a permanent secret `key=value` URL parameter grants 8-hour access (e.g. `?preview=secret123`)
- **Bypass Links** — time-limited, use-limited one-time tokens stored in the database; create, copy, and delete them from the admin

### 📋 Login Logs
- Every attempt recorded: date/time, IP, status, bypass type, user agent
- Filter by status: All / Success / Failed / Blocked
- Paginated table view
- Clear all logs with one click

### 🎨 Fully Customisable Password Page
| Section | Options |
|---|---|
| **Text** | Headline, sub-headline, input placeholder, button label, error message, footer |
| **Layout** | Form placement — centre, left, or right |
| **Background** | Solid colour, two-colour gradient (with direction slider), or image |
| **Image overlay** | Colour + opacity slider |
| **Card** | Background colour, max width, border radius, optional drop shadow |
| **Typography** | Google Fonts picker with live admin preview; CSS font-family fallback |
| **Logo** | Custom image (replaces the default lock icon), max width control |
| **Input field** | Background, border, text, focus ring colour; border radius |
| **Button** | Background, hover, text colour; border radius |
| **Error message** | Custom colour |

Live preview opens in a new tab directly from the Template tab.

### 🔌 Optionally Allow Through
- WordPress REST API (`/wp-json/`)
- RSS and Atom feeds

---

## Screenshots

### General Settings — Protection, HTTP status code & access rules
![General Settings](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-1.png)

---

### Brute Force Protection — Max attempts & lockout window
![Brute Force Settings](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-2.png)

---

### Bypass URL — Permanent secret key/value URL bypass
![Bypass URL Settings](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-3.png)

---

### Template — Text content, layout placement & background type
![Template Settings](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-4.png)

---

### Login Logs — Filterable table of all access attempts
![Login Logs](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-5.png)

---

### Bypass Links — Create & manage time-limited one-time access links
![Bypass Links](https://raw.githubusercontent.com/appscreo/lockfront/main/assets/screenshot-6.png)

---

## Installation

### From WordPress Admin
1. Download the latest `lockfront.zip` from [Releases](../../releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and click **Install Now**
4. Activate the plugin

### Manual
1. Extract `lockfront.zip` into `/wp-content/plugins/lockfront/`
2. Activate via **Plugins → Installed Plugins**

### First-time setup
1. Navigate to **Lockfront → Settings**
2. On the **General** tab, enter a **Site Password**
3. Set the **HTTP Response Code** (503 recommended)
4. Toggle **Enable Protection** on
5. Click **Save Changes**

---

## How It Works

```
Visitor requests any front-end page
        │
        ▼
Is protection enabled + password set?  ──No──▶  Pass through
        │ Yes
        ▼
Is this wp-admin / wp-login / AJAX / cron?  ──Yes──▶  Pass through
        │ No
        ▼
Bypass checks (URL key → token → IP whitelist → admin → cookie)
        │ None match
        ▼
Show password page  (HTTP 503 / 401 / 200)
        │ Correct password entered via fetch()
        ▼
Set HMAC cookie  →  Redirect to original URL
```

---

## Settings Reference

### General Tab
| Setting | Default | Description |
|---|---|---|
| Enable Protection | Off | Master on/off switch |
| Site Password | — | The password visitors must enter |
| Unlock Duration | 1 day | How long the unlock cookie lasts (0 = session) |
| HTTP Status Code | 503 | Code sent with the password page |
| Bypass for Admins | On | Logged-in admins skip the gate |
| Allow REST API | Off | Pass `/wp-json/` requests through |
| Allow RSS | Off | Pass feed requests through |
| IP Whitelist | — | One IP or CIDR per line |

### Brute Force Tab
| Setting | Default | Description |
|---|---|---|
| Enable | On | Toggle brute-force lockout |
| Max Attempts | 5 | Failed attempts before lockout |
| Lockout Window | 15 min | Rolling window for counting attempts |

### Bypass URL Tab
| Setting | Description |
|---|---|
| URL Parameter Name | The `key` in `?key=value` |
| URL Parameter Value | The `value` (secret) in `?key=value` |

---

## HTTP Status Codes Explained

| Code | Use Case | SEO Impact |
|---|---|---|
| **503** *(recommended)* | Site temporarily unavailable (maintenance) | Google pauses crawling, adds `Retry-After: 3600` — safe for SEO |
| **401** | Access requires authentication | Most crawlers stop indexing — good for long-term private sites |
| **200** | Silent / invisible gate | Crawlers may index the password page — use only for short-term or internal use |

---

## File Structure

```
lockfront/
├── lockfront.php                    # Bootstrap, constants, lkfr_get() helper
├── uninstall.php                    # Removes all data on plugin deletion
├── readme.txt                       # WordPress.org readme
├── assets/
│   ├── css/admin.css                # Admin UI styles
│   └── js/admin.js                  # Media picker, colour pickers, AJAX
└── includes/
    ├── class-lkfr-database.php      # DB tables, queries
    ├── class-lkfr-protection.php    # Gate logic, cookie, AJAX login handler
    ├── class-lkfr-bypass.php        # URL key + token bypass
    ├── class-lkfr-template.php      # Front-end password page
    └── class-lkfr-admin.php         # Settings UI, logs, bypass links
```

---

## Database Tables

| Table | Purpose |
|---|---|
| `{prefix}lkfr_login_logs` | Every login attempt (success / failed / blocked) |
| `{prefix}lkfr_bypass_tokens` | Time-limited bypass tokens |
| `{prefix}lkfr_login_attempts` | Brute-force attempt tracking |

All tables are created on activation via `dbDelta()` and removed cleanly on plugin deletion via `uninstall.php`.

---

## Cookies Set

| Cookie | Duration | Purpose |
|---|---|---|
| `lkfr_access` | Configurable (default 1 day) | Main unlock cookie |
| `lkfr_bypass_key` | 8 hours | Remembers URL key bypass for current session |
| `lkfr_bypass_token` | 8 hours | Remembers token bypass for current session |

All cookies are `HttpOnly`, `SameSite=Lax`, and `Secure` on HTTPS. Values are HMAC-signed with `wp_salt('auth')`.

---

## Requirements

| Requirement | Minimum |
|---|---|
| WordPress | 6.0 |
| PHP | 7.4 |
| MySQL / MariaDB | 5.6 / 10.0 |

---

## Changelog

### 1.0.0
- Initial release

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-feature`)
3. Commit your changes (`git commit -m 'Add some feature'`)
4. Push to the branch (`git push origin feature/my-feature`)
5. Open a Pull Request

---

## License

Distributed under the **GPL-2.0+** License. See [`LICENSE`](LICENSE) for more information.

---

<p align="center">
  Built by <a href="https://appscreo.com">AppsCreo</a>
</p>