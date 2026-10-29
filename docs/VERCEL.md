# Deploying XEVION FaceMatch on Vercel

Vercel has no built-in PHP and no MySQL, and its servers have no permanent disk. The app adapts automatically when it runs on Vercel. All settings, including the database connection, still come from `config/config.php`, exactly as on any other host.

| Concern | Ordinary PHP hosting / XAMPP | Vercel |
|---|---|---|
| PHP | Apache + mod_php | Community runtime `vercel-php` (PHP 8.4), see `vercel.json` |
| Routing | `.htaccess` | `vercel.json` sends everything except `/assets/*` and `robots.txt` to `api/index.php` |
| Settings | `config/config.php` | `config/config.php` |
| Sessions | `sessions` table | `sessions` table |
| Photos and banners | `media/uploads/` | `media_files` table (chosen automatically) |
| Logs | `storage/logs/` | stderr, shown under Vercel → Logs (chosen automatically) |
| Image processing | GD re-encodes every photo | The browser resizes to JPEG; PHP validates and strips all metadata (GD is not available on Vercel) |
| Maintenance | A cron job calls `/api/cron` | Vercel Cron calls `/api/cron` daily |

## Without a database: matches live in the browser

Deployed as-is, with no `config/config.php`, the site runs on Vercel **without any database** and the main features still work:

- **Create a match.** The normal form saves the match and its photos in the visitor's own browser (IndexedDB). Nothing is uploaded to the server.
- **Vote and see results.** `/play` shows head-to-head matchups, a progress bar, live standings (win rate, then total wins, ties share a place), and a "Next voter" button so a group can pass one phone around.
- **Share.** The Share button makes a link that carries the whole match, with smaller photos, in the part of the URL after `#`, which browsers never send to the server. Anyone who opens it gets the match on their own device and can vote there.
- **Private matches.** Choosing "Private, with an access code" encrypts the link (AES-GCM, key derived from a random 10-character code with PBKDF2, 210,000 rounds). Without the code the link shows only a lock screen. The organiser sends the code separately.
- **Upload checks.** Only real JPEG, PNG and WebP files are accepted (checked by their first bytes, not the name), within size and pixel limits. Every photo is redrawn into a new JPEG, which drops camera and location data. Photos inside a shared link are decoded and size-checked again before they are shown.
- **Your matches.** Explore and the category pages list the matches saved in this browser.

What this mode cannot do, because there is nowhere shared to keep data:

- Votes from different people are not added together. Each device has its own standings.
- Clearing the browser's site data deletes its matches.
- Results are not tamper-proof: anyone can change data in their own browser, so per-device standings are for fun, not official results.
- Once someone has opened a shared link, the organiser cannot revoke or delete their copy. A private link plus its code works for anyone they are passed on to.
- Share links with many photos are long; some chat apps shorten or cut very long links.
- There is no public listing of other people's matches, no moderation, no admin dashboard, no reports or contact form, and no ads. Those pages say they are not available.

### Short share links

By default a share link carries the whole match, photos included, so it is very long. Connect **Vercel Blob** to get short links such as `https://www.xevion.pk/s/aB3dE5fG7h`:

1. In Vercel open **Project → Storage → Create Database → Blob**, choose **Private** access, and create it.
2. Connect it to this project (all environments). Vercel adds `BLOB_READ_WRITE_TOKEN` automatically.
3. Redeploy.

**Share** then stores the match in Blob through `api/share.js` (a small Node function that uses the official `@vercel/blob` library) and shows the short link. Short links get a proper preview card in chat apps, with the match title and question; private matches only show "Private match". Without Blob, or if storing fails, Share falls back to the long link, so sharing always works.

Blob is file storage, not a database. It holds one small text file per shared match. Blocking a short link (its ID in `BLOCKED_MATCHES`) is enforced by the server.

### Admin dashboard without a database

`/admin/` stays switched off (it shows setup steps) until you add these in **Project → Settings → Environment Variables** and redeploy. See `.env.example`:

| Variable | Purpose |
|---|---|
| `ADMIN_EMAIL` | Sign-in email |
| `ADMIN_PASSWORD_HASH` or `ADMIN_PASSWORD` | bcrypt hash (preferred), or a plain password of 12+ characters |
| `APP_SECRET` | Optional; signs admin sessions so they survive password changes |
| `CONTACT_EMAIL` | Optional; shown on the Contact and Report pages |
| `BLOCKED_MATCHES` | Optional; comma-separated match IDs that must no longer open on the site |

The dashboard offers **Status** (this deployment's details), **Moderation** (paste a reported share link to see its content and ID; block it by adding the ID to `BLOCKED_MATCHES`) and **Matches in this browser** (list, delete, back up to a file, restore). It cannot see or delete matches on other people's devices.

To get shared results, accounts for admins and everything else, connect a MySQL database as described below. The site switches to the full version automatically.
## 1. Create a MySQL database

Vercel does not host MySQL. Use a MySQL 8 or MariaDB service that accepts connections from the internet, for example TiDB Cloud Serverless, Aiven for MySQL, Railway or your own server. Free hosting databases such as InfinityFree only accept connections from their own servers and will not work.

Create an empty database and note the host, port, database name, user and password. Photos are stored in this database, roughly 100–300 KB each, so pick a plan with enough storage.

## 2. Create config/config.php

```php
<?php
return [
    'base_url'       => 'https://www.example.com',  // your public address, no trailing slash
    'app_secret'     => '64 random hex characters',   // php -r "echo bin2hex(random_bytes(32));"
    'admin_email'    => 'you@example.com',            // first super administrator
    'admin_password' => 'a password of 12+ characters',
    'admin_name'     => 'Your Name',
    'cron_secret'    => 'a random string of 16+ characters',
    'db' => [
        'host' => 'your-database-host',
        'port' => 4000,                               // TiDB uses 4000; most MySQL services use 3306
        'name' => 'xevion',
        'user' => 'your-database-user',
        'pass' => 'your-database-password',
        'ssl'  => true,                               // hosted databases require TLS
    ],
];
```

On Vercel the app automatically trusts Vercel's proxy headers, stores photos in the database and logs to stderr. You do not set those.

Never change `app_secret` after launch: it signs invite and management links and encrypts stored codes.

## 3. Get the file onto Vercel

Vercel builds from your GitHub repository, but `config/config.php` holds your database password and is excluded from git so it cannot leak. Choose one of these:

- **Private repository (simplest).** Make the GitHub repository private (GitHub → Settings → General → Danger Zone → Change visibility). Then remove the line `config/config.php` from `.gitignore`, commit the file and push. Vercel deploys it with the rest of the code. Never do this while the repository is public.
- **Deploy from your computer.** Install the Vercel CLI (`npm i -g vercel`), then run `vercel --prod` in the project folder. The CLI uploads your local files, including `config/config.php`, without it ever going to GitHub. Pushes to GitHub then deploy without the file, so keep using the CLI for production.

For the daily maintenance call, also add `CRON_SECRET` in **Project → Settings → Environment Variables** with the same value as `cron_secret`. Vercel Cron uses it to authenticate its call.

## 4. Open the site

There is no installation step. The first visit creates the tables, the sessions table and the administrator account. Sign in at `/admin/` with `admin_email` and `admin_password`, change the password under **Account**, then remove `admin_password` from the file.

Later deploys that add tables apply them automatically.

## Troubleshooting

**The site downloads a file named "download" instead of showing a page.** Production is pointing at a deployment built before Vercel support existed: commit `c1bd9a6` or earlier, which has no `vercel.json`. Vercel then serves `index.php` as a plain file. This usually happens after an **Instant Rollback** to an old deployment. A rollback also stops new pushes from going live automatically.

To fix it, open **Deployments**, pick the newest Ready deployment from `main`, open its **⋯** menu and choose **Promote to Production**. Promoting also switches automatic production updates back on. Do not roll back to anything older than commit `2bb4a98`.

**"Configuration needed" page.** The deployment does not contain `config/config.php`. See step 3.

**"Cannot reach the database" page.** The file is there but the connection fails. Check the host, port, name, user and password in `config/config.php`, and that the database accepts connections from the internet. The Vercel function logs show the MySQL error code.

## Limits to know

- **Request size:** Vercel rejects requests over 4.5 MB. The create form resizes every photo in the browser and shrinks them further until the whole match fits. With JavaScript disabled, uploads larger than that fail.
- **Without JavaScript,** only JPG and PNG are accepted on Vercel, because WebP cannot be cleaned without GD.
- **Thumbnails** reuse the full photo on Vercel; pages crop it with CSS.
- **Vercel Cron on the Hobby plan** runs at most once a day. Housekeeping also runs on about 2% of normal requests, and closing times are always checked live, so a daily run is enough.
- **Cold starts:** the first request after a quiet period takes longer.
- `vercel-php` is a community runtime, not an official Vercel product.

## Local check of Vercel mode

Run PHP's built-in server with the `VERCEL` flag set. It uses your normal `config/config.php`:

```bash
VERCEL=1 php -S localhost:8090 api/index.php
```
