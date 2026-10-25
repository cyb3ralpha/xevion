# Deployment

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `gd` (WebP support recommended), `fileinfo`, `sodium`, `mbstring`
- MySQL 5.7.8+ or MariaDB 10.3+
- Apache 2.4 with `mod_rewrite` (`mod_headers` recommended). Nginx works too — see below.
- No root access, Node.js or Composer required.

## 1. Upload

Upload the whole folder to your web root (or a sub-folder). Keep the folder structure as it is; the `.htaccess` files deny web access to `app/`, `config/`, `storage/`, `database/`, `templates/`, `cli/`, `tests/` and `docs/`.

If your host lets you choose the document root, the project root is the document root (there is no separate `public/` folder).

## 2. Permissions

PHP must be able to write to:

- `config/` (once, for the installer to create `config/config.php`)
- `storage/` and its sub-folders `media/`, `logs/`, `sessions/`

Typical: `chmod 750 storage storage/*` and `chmod 750 config`. After installing you may make `config/` read-only.

## 3. Database

Create an empty database with `utf8mb4_unicode_ci` and a user limited to that database. The installer creates the tables.

## 4. Install

**Web:** visit `https://your-domain/install/`. It checks the server, then asks for:

1. The **setup key** from `storage/setup-key.txt` (open it in your host's file manager). This stops anyone else from claiming a freshly uploaded site.
2. Site URL, database details and the first **Super Administrator** (12+ character password).

On success the installer writes `config/config.php` (with a random 256-bit `app_secret`) and `storage/installed.lock`, and permanently disables itself. You can also delete the `install/` folder.

**CLI** (if you have SSH):

```bash
php cli/install.php
# or non-interactive
FM_DB_PASS=… FM_ADMIN_PASSWORD=… php cli/install.php --base-url=https://example.com \
  --db-host=localhost --db-name=facematch --db-user=facematch \
  --admin-email=you@example.com --admin-name="Your Name"
```

There is no default password anywhere in the code.

## 5. PHP limits

`.htaccess` (mod_php) and `.user.ini` (PHP-FPM/CGI) raise upload limits to 8 MB per file and 120 MB per request (20 photos × 5 MB plus overhead). If your host ignores both, set these in its PHP settings panel:

```
upload_max_filesize = 8M
post_max_size = 120M
max_file_uploads = 25
```

If the total upload is too large the form shows a clear error instead of failing silently.

## 6. HTTPS

Install a certificate, then uncomment the two "Force HTTPS" lines in `.htaccess`. Cookies are automatically marked `Secure` and HSTS is sent when the request is HTTPS. Make sure `base_url` in `config/config.php` starts with `https://`.

Behind Cloudflare or another reverse proxy, set `'trust_proxy' => true` in `config/config.php` so the real visitor IP and protocol are used. Only do this when a proxy is actually in front of the site — otherwise visitors could spoof their IP.

## 7. Cron (recommended)

```
*/15 * * * * php /path/to/facematch/cli/maintenance.php >> /path/to/facematch/storage/logs/cron.log 2>&1
```

It closes expired matches, purges rate-limit rows, clears IP-derived keys after the retention period, and archives long-finished ad campaigns. The same job also runs opportunistically on about 2% of page views, so the site keeps working without cron, just less punctually.

## 8. Backups

Back up the database **and** `storage/media/` (photos and ad creatives) together, plus `config/config.php`. Keep the `app_secret`: if it changes, existing private invite links, management links, voter identifiers and IP hashes stop matching.

## Nginx

```nginx
root /var/www/facematch;
index index.php;
location ~ ^/(app|config|storage|database|templates|cli|tests|docs)(/|$) { deny all; }
location ~ /\.(?!well-known) { deny all; }
location ~ \.(sql|md|log|lock|ini|sh)$ { deny all; }
location ^~ /assets/ { try_files $uri =404; expires 30d; }
location ^~ /admin { try_files $uri /admin/index.php?$query_string; }
location = /install/ { try_files $uri /install/index.php; }
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|admin/index|install/index)\.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { deny all; }
client_max_body_size 120m;
```

## Account recovery

- A Super Administrator can issue a temporary password from **Administrators**.
- With SSH: `php cli/reset-password.php --email=you@example.com [--activate]`.
- Add more admins from the CLI: `php cli/create-admin.php --email=… --name=… --role=moderator`.

All of these are written to the audit log.

## Updating

Replace the code files but keep `config/config.php` and `storage/`. Read the release notes of any future version for database changes before upgrading.

## Before launch checklist

- [ ] `config/config.php` has `'env' => 'production'`
- [ ] HTTPS forced, `base_url` uses https
- [ ] `install/` deleted or confirmed disabled (returns 404)
- [ ] Cron job added
- [ ] Contact email set in **Settings**
- [ ] Blocked-terms list reviewed for your audience's languages
- [ ] Privacy Policy and Terms reviewed by someone qualified for your jurisdiction (the included texts accurately describe what the software does, but they are not legal advice)
- [ ] At least one more administrator so moderation isn't a single point of failure
