# WPCodex — Security Model

> **WPCodex is designed for development and staging environments only.**
>
> It gives AI agents the ability to execute arbitrary PHP code, run WP-CLI commands, query the database directly, and read or write any file the web server can access. This is intentional and powerful. It is also a significant attack surface if misused or misconfigured.
>
> Read this document before activating WPCodex on any site.

---

## Threat Model

### What WPCodex Can Do

An authenticated agent can:

- Execute any PHP code inside the WordPress process (including `system()`, `shell_exec()`, file operations, HTTP requests)
- Run any WP-CLI command the web server user has permission to run
- Read any file accessible to PHP on the server (`wp-config.php`, `.env`, private uploads, etc.)
- Write, overwrite, or create any file the web server user can write
- Query or mutate any database table, including dropping tables
- Install, activate, or deactivate plugins and themes
- Create or delete WordPress users, including administrators

### What WPCodex Cannot Do

- Bypass server-level `open_basedir` or `disable_functions` restrictions (these apply to the PHP process as normal)
- Access files outside `open_basedir` if it is configured
- Circumvent Linux file permissions — it runs as the web server user

### Risk Summary

| Risk | Mitigated by |
|---|---|
| Unauthorised MCP access | WordPress Application Passwords — native WP auth, revocable per client |
| Credential interception | HTTPS requirement, plugin warns if SSL not detected |
| Compromised Application Password | Revoke it instantly from Users → Application Passwords — no plugin changes needed |
| Path traversal in file operations | `realpath()` validation anchored to `ABSPATH` in `FileManager` |
| SQL injection | All parameterised queries through `$wpdb->prepare()` |
| Subprocess env leakage | `HTTP_AUTHORIZATION` and sensitive env vars stripped before `proc_open` |
| Sandbox file persistence | Temp PHP files always `unlink()`-ed, even on exception |
| Directory listing of sandbox | `.htaccess Deny from all` + `index.php` stub in `wpcodex-sandbox/` |
| CSRF on admin actions | `wp_nonce_field()` + `check_admin_referer()` on every form |
| Privilege escalation via admin | `current_user_can('manage_options')` on every render, handler, and ability `permission_callback` |

---

## Authentication

WPCodex uses **WordPress Application Passwords** for authentication, provided by the `wordpress/mcp-adapter` transport layer. This is native WordPress authentication — no custom keys, no separate secrets.

### How It Works

```
AI client
   │
   │  Authorization: Basic base64(username:app-password)
   ▼
wordpress/mcp-adapter  ←  validates against WordPress user table
   │
   │  Only proceeds if the user exists and the password matches
   ▼
Ability permission_callback  ←  current_user_can('manage_options')
   │
   │  Only executes if the user has the required capability
   ▼
execute_callback  ←  the actual logic runs
```

### Creating an Application Password

1. Go to **WordPress Admin → Users → Your Profile**
2. Scroll to **Application Passwords**
3. Enter a name for the client (e.g. `Claude Code`, `Cursor`)
4. Click **Add New Application Password**
5. Copy the generated password — it is shown only once
6. Use your WordPress username + this password in your AI client config

### Per-Client Access Control

Create one Application Password per AI client. This gives you a clean audit trail and lets you revoke access for a single client without affecting others.

Revoke an Application Password at any time from the same **Application Passwords** section. The revocation takes effect immediately.

### What Happens on an Invalid Request

| Condition | Response |
|---|---|
| Missing `Authorization` header | 401 Unauthorized |
| Invalid username or password | 401 Unauthorized |
| Valid credentials but `manage_options` capability missing | Ability returns a `WP_Error` permission denied |

---

## Transport Security

WPCodex requires HTTPS. Transmitting Application Password credentials over plain HTTP exposes them to any network observer.

- The plugin shows a persistent admin error notice if the site is not running HTTPS
- The **Connect** page shows an additional warning on non-HTTPS sites
- There is no option to suppress these warnings — they exist to protect you

If you are developing locally with HTTP, use a tool such as [mkcert](https://github.com/FiloSottile/mkcert) to set up local HTTPS, or restrict access to localhost only.

---

## PHP Execution Sandbox

PHP code is executed via a temporary file inside `wp-content/wpcodex-sandbox/`:

1. Code is written to `wpcodex-sandbox/exec_{random_16_hex}.php`
2. The file is `include()`-ed inside a `try/catch(\Throwable)` with output buffering
3. The file is **always** deleted after execution — even if an exception is thrown
4. The sandbox directory is protected by `.htaccess Deny from all` (Apache) and an `index.php` stub

**There is no process-level isolation.** The code runs inside the PHP-FPM worker that handled the request, with full access to WordPress globals, the database connection, and the server filesystem.

This is intentional — it is what makes the tool useful. It also means that a compromised Application Password gives an attacker full server access equivalent to a web shell. Protect your credentials accordingly.

**Future:** A `WPCODEX_SAFE_MODE` constant (planned for v1.1) will switch execution to a subprocess with `disable_functions` restrictions for teams that need tighter isolation.

---

## File Operations

All file write operations in `FileManager`:

1. Validate the path using `realpath()` to prevent traversal outside `ABSPATH`
2. Create a `.bak` backup of the existing file before overwriting
3. Register the backup path in a WordPress transient (24-hour TTL) for the admin restore UI
4. Write content to a temp file (`{path}.tmp_{random}`) first
5. `rename()` the temp file to the target — atomic on POSIX systems

**Path traversal protection:** Any path that resolves outside of `ABSPATH` is rejected with an `\InvalidArgumentException`. This applies to `FileManager`, which handles all direct file read/write operations.

---

## WP-CLI Subprocess

WP-CLI runs as a child process via `proc_open`. Security measures:

- `HTTP_AUTHORIZATION` and any credential-bearing environment variables are removed from the subprocess environment
- The process is killed if it exceeds the configured timeout (default: 30 seconds; override with `WPCODEX_CLI_TIMEOUT` constant)
- `--no-color` and `--path=ABSPATH` are always appended to prevent path ambiguity

---

## Production Use

**Do not activate WPCodex on a production site unless you fully understand the implications.**

WPCodex on a production site means:

- Any AI client with a valid Application Password has full, unrestricted server access
- PHP execution errors, bugs, or AI mistakes run live against real data
- There is no read-only mode (planned for v1.2)

If you choose to use WPCodex on production anyway:

1. Create a dedicated WordPress user with `manage_options` capability only — do not use an administrator account
2. Revoke the Application Password immediately after each session
3. Keep database and filesystem backups current before every session
4. Consider network-level access controls (IP allowlist, VPN) on the MCP endpoint
5. Monitor the WordPress error log and server access log during sessions

---

## Reporting a Vulnerability

If you discover a security vulnerability in WPCodex, please do **not** open a public GitHub issue.

Report it privately to: **security@wpcodex.ai**

Include:
- A description of the vulnerability
- Steps to reproduce
- The potential impact
- Any suggested fix

We will acknowledge receipt within 48 hours and aim to release a fix within 14 days for critical issues.