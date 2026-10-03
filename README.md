# Team portfolio website · سایت رزومه تیم

A multilingual site that presents the team, its expertise, its members (with skill levels) and its portfolio, plus a full admin panel. Built on **Laravel 13**.

- **Languages:** English is the default, at `https://example-domain.com/`. Every other language lives under its own prefix, e.g. `/fa`.
- **Fonts (self-hosted):** Persian uses **Vazirmatn** with Persian digits. English and other Latin-script languages use **Plus Jakarta Sans**.
- **Images:** every upload is converted to **WebP**. Each section of the site gets its own size, generated once and then cached.
- **Admin panel:** `/admin`. Content in every language, images, the contact-form inbox, and backups.

## Setup
Requires PHP 8.3+ with GD or Imagick (WebP support), and Composer.

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
# In .env set APP_URL (e.g. https://example-domain.com), ADMIN_EMAIL, ADMIN_PASSWORD
touch database/database.sqlite          # or configure MySQL in .env
php artisan migrate --seed              # tables + first admin + sample content
php artisan storage:link
php artisan serve                       # http://127.0.0.1:8000
```

In production, point the web server's document root at `backend/public`.
To create an admin or reset a password: `php artisan admin:create you@example.com`

## Adding a language
1. Add an entry to `backend/config/site.php` under `locales` (name, direction, font).
2. Copy `backend/lang/en/site.php` to `backend/lang/{code}/site.php` and translate the interface strings.

The new language appears at `/{code}`, in the language switcher, in the sitemap and in the admin panel. Any untranslated content falls back to English.

## Images
- **On upload:** the image is EXIF-rotated, scaled down to at most 2560px, and saved as WebP (quality 90) with metadata stripped.
- **Per-section sizes:** each preset in `config/site.php` defines the shape and widths for one part of the site. The defaults are `avatar` (square: 64/128/256), `cover` (16:10: 480–1600) and `og` (1200×630).
- **Delivery:** the page uses `srcset`, so each browser downloads the right size. A size is generated on its first request and stored under `storage/app/public/cache/`, after which the web server serves it as a static file.
- **No upscaling:** images are never enlarged.

## API
| Method | Path | Description |
|---|---|---|
| GET | `/api/site` | All content, every translation, plus the language list |
| POST | `/api/contact` | Contact form (rate-limited, honeypot) |
| POST | `/api/auth/login` | Admin login (Sanctum token) |
| PUT | `/api/admin/site` | Save all content (fully validated) |
| POST | `/api/admin/uploads` | Upload an image (converted to WebP) |
| GET/PATCH/DELETE | `/api/admin/messages…` | Manage contact messages |

Tests: `cd backend && php artisan test`
