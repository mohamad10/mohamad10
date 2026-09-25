# سایت رزومه تیم برنامه‌نویسی

سایت معرفی تیم، تخصص‌ها، اعضا (با سطح تخصص و مهارت‌ها) و نمونه‌کارها + پنل مدیریت + بک‌اند **Laravel 13**.

```
.
├── index.html / admin.html      فرانت‌اند (سایت + پنل مدیریت)
├── assets/
│   ├── config.js                آدرس API (اتصال فرانت به بک‌اند)
│   ├── data.js                  داده‌های پیش‌فرض / حالت آفلاین
│   ├── store.js                 لایه داده و کلاینت API
│   ├── app.js, style.css        سایت
│   └── admin.js, admin.css      پنل مدیریت
└── backend/                     API لاراول ۱۳ (Sanctum)
```

## راه‌اندازی بک‌اند
پیش‌نیاز: PHP 8.3+ و Composer.

```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate
# در .env: ADMIN_EMAIL / ADMIN_PASSWORD / CORS_ALLOWED_ORIGINS را تنظیم کنید
touch database/database.sqlite          # یا MySQL را در .env تنظیم کنید
php artisan migrate --seed              # جداول + مدیر اولیه + داده‌های نمونه
php artisan storage:link                # برای نمایش تصاویر آپلودی
php artisan serve                       # http://127.0.0.1:8000
```

ساخت مدیر جدید یا تغییر رمز: `php artisan admin:create you@example.com`

## راه‌اندازی فرانت‌اند
در `assets/config.js` مقدار `apiUrl` را برابر آدرس API بگذارید (پیش‌فرض `http://127.0.0.1:8000/api`) و پوشه اصلی را با هر وب‌سرور استاتیکی سرو کنید:

```bash
python3 -m http.server 5500   # http://127.0.0.1:5500
```

- اگر API در دسترس نباشد، سایت خودکار از `assets/data.js` استفاده می‌کند.
- اگر `apiUrl` خالی باشد، پنل در حالت محلی (بدون سرور) کار می‌کند؛ برای انتشار باید `data.js` را از پنل دانلود و جایگزین کنید.

## پنل مدیریت (`admin.html`)
- ورود با ایمیل و رمز مدیر (توکن Sanctum، اعتبار ۷ روز)
- ویرایش اطلاعات تیم، خدمات، اعضا (عکس، سطح، سابقه، مهارت‌ها با درصد، تحصیلات، لینک‌ها) و پروژه‌ها
- ذخیره خودکار روی سرور، آپلود تصویر، صندوق پیام‌های فرم تماس، تغییر رمز، پشتیبان‌گیری و بازیابی JSON

## API
| متد | مسیر | توضیح |
|---|---|---|
| GET | `/api/site` | کل محتوای سایت (کش‌شده) |
| POST | `/api/contact` | ارسال پیام از فرم تماس (محدودیت ۵ در دقیقه + honeypot) |
| POST | `/api/auth/login` | ورود و دریافت توکن |
| GET / POST / PUT | `/api/auth/me` · `/logout` · `/password` | حساب مدیر |
| PUT | `/api/admin/site` | ذخیره کل محتوا (با اعتبارسنجی کامل) |
| POST | `/api/admin/uploads` | آپلود تصویر |
| GET / PATCH / DELETE | `/api/admin/messages[/{id}[/read]]` | مدیریت پیام‌ها |

تست‌ها: `cd backend && php artisan test`
