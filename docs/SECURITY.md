# Security and privacy

## Identity model

| Who | How they're recognised |
|---|---|
| Visitor | Session cookie `fm_s` (HttpOnly, SameSite=Lax, Secure on HTTPS) and a random long-lived `fm_v` voter cookie, stored server-side only as an HMAC. No accounts, no fingerprinting. |
| Creator | A 256-bit management link `/manage/{token}`. Only its HMAC is stored. Shown once at creation; the creator can replace it (old link stops working). |
| Private-match guest | A 192-bit invite link `/p/{token}` (HMAC stored, plus an encrypted copy so the creator can see it again), optional expiry and optional access code (password-hashed, rate-limited). |
| Administrator | Email + password (`password_hash`), separate session cookie scoped to `/admin/` with SameSite=Strict, 30-minute idle and 12-hour absolute timeout, session ID regenerated at login, logged out automatically when deactivated or when the password changes. |

Grants in a session remember which token hash produced them, so revoking or regenerating an invite or management link immediately cuts off everyone who used the old one. Administrators cannot sign in as a creator or visitor.

## Controls

- **SQL:** every query uses PDO prepared statements with native (non-emulated) prepares.
- **XSS:** all output passes through `e()`; strict Content-Security-Policy (`script-src 'self'`, no inline script, `object-src 'none'`, `frame-ancestors 'none'`).
- **CSRF:** per-session token on every form and on the JSON vote API (`X-CSRF-Token`).
- **Uploads:** size, MIME (by content, not extension), dimensions and pixel-count limits; decoded with GD and written as new WebP/JPEG files, which strips EXIF/GPS and any embedded payload. Files live in `media/uploads/` (not web-accessible), have random names, and are served through PHP after an authorisation check with `nosniff` and a sandboxing CSP. Ad creatives follow the same path; SVG/GIF are not accepted.
- **Removal:** deleting a photo deletes the file. Public images use a 5-minute cache; private images are `no-store`.
- **Enumeration:** public IDs are random (12 or 16 characters); database IDs never appear in public URLs. A private match that exists but isn't unlocked returns the same 404 as one that doesn't exist.
- **Rate limits** (MySQL-backed, per keyed IP hash): creating matches (5/hour, 15/day by default), upload volume (300 MB/day), votes (40/min), reports (10/hour), contact (5/hour), access-code attempts (5 per 15 min per match), admin login (10 per 15 min per network and 6 per account).
- **Outbound links:** ad and sponsor URLs must be absolute `http(s)` with no credentials or control characters; clicks go through `/go/{id}`.
- **Headers:** HSTS on HTTPS, `X-Frame-Options: DENY`, `Referrer-Policy` (`no-referrer` on invite and management pages), `Permissions-Policy`, `noindex` on private, admin and management pages (as an extra layer, not as access control).
- **Errors:** production shows generic error pages; details go to `storage/logs/` (never web-accessible). Database connection errors don't reveal host or user.
- **Setup:** there is no installer page. The first administrator comes only from server-side configuration (`config/config.php`) and is created only while no super administrator exists.
- **Audit log:** every admin action, creator management action and automatic moderation action is recorded with actor, target and details. There is no way to edit or delete entries from the application.

## Browser mode (no database)

When the site runs without a database, matches never reach the server:

- **Uploads** are checked in the browser by file signature (JPEG, PNG or WebP only; SVG, HTML and anything else is refused), size and dimensions, then redrawn onto a canvas as a new JPEG. That removes metadata such as GPS. Nothing is uploaded, so there is no server file to attack.
- **Share links** keep the match after `#`, which browsers never send to servers or put in `Referer` headers. On opening, the data is size-capped, parsed as JSON, every name is inserted as plain text, every photo must be a `data:image` URL that decodes to an image no larger than 2000 pixels, and the CSP blocks scripts from any other source.
- **Private matches** encrypt the link with AES-256-GCM; the key comes from a random 10-character access code (about 50 bits) through PBKDF2-SHA256 with 210,000 iterations and a random salt. Wrong codes fail the authentication check and reveal nothing. There is no server to rate-limit guesses, so the strength rests on the code length and the slow key derivation.
- **Admin sign-in** compares the email and password with server-side environment variables (bcrypt hash supported, constant-time comparisons). The session is an HMAC-SHA256-signed cookie (HttpOnly, SameSite=Strict, path `/admin`, 8-hour expiry). Forms carry a double-submit CSRF token and are refused when the `Origin` header names another site. Failed sign-ins are slowed down, but there is no shared store for lockouts, so use a long password; Vercel's firewall can add rate limiting. Nothing secret is sent to the browser.
- **Blocking** works by match ID: the server lists blocked IDs and the site refuses to open them. Because the match data never reaches the server, this is enforced in the visitor's browser and cannot remove copies already saved on devices.
- **Limits that cannot be fixed without shared storage:** votes are counted per device and can be edited by whoever controls that browser; nothing stops someone forwarding a link and its code; shared copies cannot be revoked; there is no moderation or reporting. The interface says results are "on this device" and never presents them as official.

## Personal data and retention

- Raw IP addresses are **never stored**. Rate limiting and abuse heuristics use `HMAC-SHA256(app_secret, ip)` (IPv6 reduced to /64). These keys are deleted from votes, matches, reports and messages after `ip_retention_days` (default 30; audit log 90). Rate-limit rows are purged after 2 days.
- Because the hash is keyed with a secret that never leaves the server, the stored values can't be reversed with a lookup table — but anyone holding both the database and `config/config.php` could test guesses. Treat both as confidential.
- Reporter and contact emails are optional and visible only to administrators.
- Creators can delete their match (and all photo files) at any time.

## Content safety defaults

- Uploaders must confirm permission and that everyone pictured is 18+.
- Public matches are unlisted until a moderator approves them (configurable); custom categories always need approval.
- Titles, categories, descriptions and names are screened against an editable blocked-terms list (with leetspeak and spaced-letter normalisation). It's a first filter, not a replacement for review — extend it for your languages.
- One report moves a photo into the moderation queue; 3 open reports (configurable) or a single "under 18" report hides it until reviewed; 3 open reports on a listed match delist it.

## Reporting a vulnerability

Send details through the site's Contact page (topic "Other") or the configured contact email.
