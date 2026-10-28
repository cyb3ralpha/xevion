# Deployment

## Requirements

- PHP 8.1 or newer with `pdo_mysql`, `gd` (WebP support recommended), `fileinfo`, `sodium`, `mbstring`
- MySQL 5.7.8+ or MariaDB 10.3+
- Apache 2.4 with `mod_rewrite` (`mod_headers` recommended). Nginx works too — see below.
- No root access, Node.js or Composer required.

## 1. Upload

Upload the whole folder to your web root (or a sub-folder). Keep the folder structure as it is; the `.htaccess` files deny web access to `app/`, `config/`, `storage/`, `database/`, `templates/` and `docs/`.

If your host lets you choose the document root, the project root is the document root (there is no separate `public/` folder).

## 2. Permissions

PHP must be able to write to `storage/` and its sub-folders `media/` and `logs/`. Typical: `chmod 750 storage storage/*`.

## 3. Database

Create an empty database with `utf8mb4_unicode_ci` and a user limited to that database. You do not create any tables yourself.

## 4. Configure

Create `config/config.php`:

```php
<?php
return [
    'env'            => 'production',             // 'development' shows error details; never in production
    'base_url'       => 'https://example.com',    // no trailing slash; include a sub-folder if any
    'app_secret'     => '64 random hex characters', // php -r "echo bin2hex(random_bytes(32));"
    'trust_proxy'    => false,                    // true only behind a proxy/CDN that sets X-Forwarded-For
    'storage_path'   => __DIR__ . '/../storage',
    'admin_email'    => 'you@example.com',        // first super administrator, created on the first visit
    'admin_password' => 'a password of 12+ characters',
    'admin_name'     => 'Your Name',
    'cron_secret'    => '',                       // optional; enables /api/cron (see Cron below)
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'facematch',
        'user' => 'facematch',
        'pass' => 'database password',
    ],
];
```

Open the site. The first visit creates the tables and the super administrator. Sign in at `/admin/`, then change the password under **Account** and remove `admin_password` from the file. The account is only created when no super administrator exists, so removing it changes nothing else.

Keep `config/config.php` private and out of version control. There is no default password anywhere in the code.
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

Set `cron_secret` in `config/config.php` to a random string of 16+ characters, then call the maintenance endpoint every 15 minutes:

```
*/15 * * * * curl -fsS -H "Authorization: Bearer YOUR_CRON_SECRET" https://example.com/api/cron > /dev/null
```

It closes expired matches, purges rate-limit rows, clears IP-derived keys after the retention period, and archives long-finished ad campaigns. The same job also runs on about 2% of page views, so the site keeps working without cron, just less punctually.
## 8. Backups

Back up the database **and** `storage/media/` (photos and ad creatives) together, plus `config/config.php`. Keep the `app_secret`: if it changes, existing private invite links, management links, voter identifiers and IP hashes stop matching.

## Nginx

```nginx
root /var/www/facematch;
index index.php;
location ~ ^/(app|config|storage|database|templates|tests|docs)(/|$) { deny all; }
location ~ /\.(?!well-known) { deny all; }
location ~ \.(sql|md|log|lock|ini|sh)$ { deny all; }
location ^~ /assets/ { try_files $uri =404; expires 30d; }
location ^~ /admin { try_files $uri /admin/index.php?$query_string; }
location / { try_files $uri /index.php?$query_string; }
location ~ ^/(index|admin/index)\.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
location ~ \.php$ { deny all; }
client_max_body_size 120m;
```

## Account recovery

- A Super Administrator can issue a temporary password from **Administrators** and add more administrators there.
- If every super administrator is locked out, set a new password hash directly in the database:

```bash
php -r "echo password_hash('a new password of 12+ characters', PASSWORD_DEFAULT), PHP_EOL;"
# then: UPDATE admins SET password_hash = '<the hash>', is_active = 1 WHERE email = 'you@example.com';
```

Changes made in the dashboard are written to the audit log.
## Updating

Replace the code files but keep `config/config.php` and `storage/`. New tables in `database/schema.sql` are applied automatically on the next visit.

## Before launch checklist

- [ ] `config/config.php` has `'env' => 'production'`
- [ ] HTTPS forced, `base_url` uses https
- [ ] `admin_password` removed from `config/config.php` after the first sign-in
- [ ] Cron job added
- [ ] Contact email set in **Settings**
- [ ] Blocked-terms list reviewed for your audience's languages
- [ ] Privacy Policy and Terms reviewed by someone qualified for your jurisdiction (the included texts accurately describe what the software does, but they are not legal advice)
- [ ] At least one more administrator so moderation isn't a single point of failure
