# Putting the admin on its own subdomain

The admin lives in the `admin/` folder of the Resources repository and runs on its own subdomain,
**`https://resources.tzivoshashem.com`**. The Resources site itself stays where it is, on its current PHP.
Nothing here is live yet.

**Where it sits on the server:** wherever the Resources repository is checked out (today the site is
served from `mashpia.com/public/resources/site/`), the admin is the `admin/` folder inside it.
The subdomain's **document root is that `admin/public` folder** (for example
`.../public/resources/site/admin/public`, or `resources/admin/public` if it is checked out on its own).
`admin/.htaccess` blocks the admin folder from being opened through mashpia.com, so its settings,
database and uploads can't be downloaded; `admin/public/.htaccess` opens only the public folder again,
and only for the subdomain (it refuses any visit whose document root isn't `admin/public`).

## 1. Server

- **PHP 8.3 or newer** (Laravel 13). The lockfile is resolved for PHP 8.3, so 8.3 is enough; PHP 8.4 is not required. Mashpia's main server (PHP 7.2 / 8.1) is not enough.
- PHP extensions: `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `hash`, `mbstring`, `openssl`,
  `pcre`, `pdo`, `pdo_sqlite`, `session`, `tokenizer`, `xml`, `zip` (zip only for Composer).
- [Composer](https://getcomposer.org) 2.
- HTTPS certificate for the subdomain (Cloudflare is fine).
- The web server's **document root must be the `admin/public/` folder**, not the admin folder.
  Apache: `public/.htaccess` is included (needs `mod_rewrite`, and `AllowOverride All` so the
  `.htaccess` files work). Nginx: send everything to `public/index.php`, and never serve `admin/` itself.

## 2. Install

```bash
# in the admin/ folder of the Resources checkout on the server:
composer install --no-dev --optimize-autoloader
cp .env.example .env        # then fill it in (section 3)
php artisan key:generate     # only on a fresh install; keep the old APP_KEY if moving the PC copy
```

Data is **not** in git (the database, uploads and `.env` are private). Copy `database/database.sqlite`
and `storage/app/public/` (the uploads; still private, see below) from the PC to the same places on the server (pages, people, uploads).
Or start fresh with `touch database/database.sqlite`, `php artisan migrate --force` and the import
commands (`resources:import-pages`, `resources:import-files`).
**Don't run `php artisan db:seed` on the server**: it makes test accounts with the password "password".

```bash
php artisan migrate --force          # safe to run every update
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Permissions: the web server user must be able to write `storage/`, `bootstrap/cache/` and
`database/` (the folder, for SQLite's journal file) and `database/database.sqlite`.
Run the `php artisan` commands as that same user (or fix ownership afterwards), so the web server
can still write the files they create.

**Do not run `php artisan storage:link` and do not create `public/storage`.** Uploaded files are
private and are only served through `/files/{id}/{name}`, which checks sign-in and visibility dates.

No cron job and no queue worker are needed.

## 3. `.env` on the server

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://resources.tzivoshashem.com
APP_FORCE_HTTPS=true
# Who forwards visits to this server (Cloudflare etc.). "*" only if the server can't be reached directly.
TRUSTED_PROXIES=*

SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null           # admin's own cookie stays on the admin subdomain

ADMIN_OWNER_EMAILS=you@your-email   # real Owner emails only (not admin@example.com). Always HQ admin; no other admin can lower or remove them

# Long random secret for the live Resources site to read /api/pages (e.g. `openssl rand -hex 32`).
# Never share it or put it in the code.
RESOURCES_API_TOKEN=

MASHPIA_AUTH_MODE=real
MASHPIA_AUTH_METHOD=handoff
MASHPIA_HANDOFF_URL=https://tzivoshashem.com/resources/adminHandoff.php
MASHPIA_SHARED_SECRET=        # same long random secret Mashpia's adminHandoff.php uses (openssl rand -hex 32)
```

After changing `.env`, run `php artisan config:cache` again.

## 4. Mashpia sign-in

The Resources site checks sign-in by calling `/resources/checkAuth.php` from the browser, with
Mashpia's login cookie (`PHPSESSID`). It answers `{"success":false}` when not signed in, or
`{"success":true,"name":...,"role":...,"initials":...}` when signed in.

That can't be used as-is from a subdomain: Mashpia's cookie is set only for the exact site people
signed in on (no `domain=`), so the browser never sends it to `resources.tzivoshashem.com`; checkAuth.php doesn't check
passwords; and it doesn't return the person's email, which the admin needs to know who it is.

### Recommended: `handoff` (one small new file on Mashpia, nothing else changes)

1. The admin's login page shows **Sign in with Mashpia**, which goes to `MASHPIA_HANDOFF_URL`.
2. That Mashpia page (new, next to checkAuth.php, works on PHP 7.2) uses the same session check as
   checkAuth.php. If the person isn't signed in, it sends them to Mashpia's login. If they are, it
   sends them back to the admin with a short signed note:

```php
<?php
// adminHandoff.php: next to checkAuth.php. Sends a signed-in Mashpia user to the Resources admin.
// 1) Do exactly what checkAuth.php does to find the signed-in user, and set:
//    $email, $name, $role   (leave $email empty if nobody is signed in)
$secret = 'SAME-SECRET-AS-THE-ADMIN';          // keep it out of git (config file / env var)
$admin  = 'https://resources.tzivoshashem.com/login/mashpia/return';  // fixed address only
if (empty($email)) { header('Location: /'); exit; }          // not signed in: Mashpia login
$p = rtrim(strtr(base64_encode(json_encode(array(
    'email' => $email, 'name' => $name, 'role' => $role,
    'exp' => time() + 120, 'nonce' => bin2hex(random_bytes(16)),
))), '+/', '-_'), '=');
header('Location: ' . $admin . '?p=' . $p . '&s=' . hash_hmac('sha256', $p, $secret));
exit;
```

3. The admin checks the signature, that the note is under 2 minutes old and used only once, then signs
   the person in by email. Their admin level is taken from their Mashpia role (see the Mashpia roles
   screen). Nobody becomes HQ admin automatically; an existing HQ admin can grant that level, or the
   person can request it if their Mashpia role allows. A level an HQ admin sets by hand is not overwritten
   at the next Mashpia login.

### Alternative: `session`

People sign in on tzivoshashem.com and Mashpia sets its login cookie for the whole domain
(`session.cookie_domain = .tzivoshashem.com`, which signs everyone out once) and adds `"email"` to checkAuth.php's answer. Then set
`MASHPIA_AUTH_METHOD=session` and `MASHPIA_CHECK_URL=https://tzivoshashem.com/resources/checkAuth.php`;
the admin asks checkAuth.php, server to server, with the person's cookie.
Only works if the admin is on a subdomain of the same domain people sign in on.

### Email and password

People can also sign in with an email and a password (the Owner, or anyone an HQ admin created an account for), even if they have no Mashpia login. The login page still shows this form when **Sign in with Mashpia** is on.

Create the first Owner (this does not set a password):

    php artisan resources:make-owner --email=you@example.com --first=Sholom --last=Chaskind --org="TH HQ" --org-role=Programmer

Then set the password. It is typed hidden, and does not appear on the screen:

    php artisan resources:set-password you@example.com

An Owner always stays HQ admin. No other admin can lower or delete them. Only an Owner can grant or remove the Owner flag.

## 5. Check

- `https://resources.tzivoshashem.com/login` shows **Sign in with Mashpia** (or "not set up yet" if a setting is missing;
  `/admin/login-settings` lists what's missing).
- `https://resources.tzivoshashem.com/storage/anything` gives 404.
- `https://resources.tzivoshashem.com/api/pages/home` gives 401 without sign-in.
- `https://tzivoshashem.com/resources/site/admin/.env` (and any other path into `admin/`) gives 403,
  including `https://tzivoshashem.com/resources/site/admin/public/login`.

## 6. The public Resources site

The site people already use (the built files in `dist/`, served at `mashpia.com/public/resources/site/dist`)
does not change its address or its look. It still has every page it was built with. Those built-in pages
stay on the site as a spare copy. If the steps below are not done yet, or the admin is down, people keep
seeing that spare copy. Nothing on the live site breaks.

When the steps below are done, the site reads pages from the admin instead. A word you change in the admin
shows on the site. A file link is shown only when that person's level may open it, and only inside the
dates you set. The search box also finds file names they may open. Each time they open a page or a file,
the admin writes down their email, their level, what they opened, and when. It does not write their name,
their computer, or where they are.

Do these things on the Mashpia server, in the same folder as `checkAuth.php`
(the public site calls `/resources/siteApi.php`):

1. Copy `admin/deploy/siteApi.php` to that folder.
2. Copy `admin/deploy/siteApi.config.example.php` to `siteApi.config.php` in that same folder.
   Put the real values in `siteApi.config.php` only. Do not commit that file and do not put it in `dist/`.
   - `token` is a long random string (`openssl rand -hex 32`). The same string goes in the admin `.env`
     as `RESOURCES_API_TOKEN`.
   - `admin` is `https://resources.tzivoshashem.com`.
3. Open `siteApi.php` and replace the sign-in block with the same lines `checkAuth.php` uses to find
   who is signed in. Set `$email`, `$name`, and `$role` (`$role` is the Mashpia words, such as `Teacher`
   or `Base Commander`). Leave `$email` empty when nobody is signed in.
4. On the admin, run `php artisan migrate --force` so the stats table exists.
5. The admin must already have the pages and the files (`resources:import-pages` and `resources:import-files`,
   or the copy of the database and `storage/app/public/` from the computer where they were imported).

The secret stays on the two servers. The browser never sees it. The site asks `siteApi.php`. That file
checks the Mashpia sign-in, then asks the admin and sends the email and the Mashpia role. The admin uses
the level saved for that email when the person already has an admin account. Otherwise it uses the Mashpia
role list (Teachers, Base commanders, and so on).

HQ admin opens **Stats** for the most opened pages, the most opened files, each person, a date range,
and a CSV download. Other levels cannot open that screen.
