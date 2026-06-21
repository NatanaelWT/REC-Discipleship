# Statistik website

Statistik website bekerja sepenuhnya dari data request aplikasi. Sistem tidak memakai geolokasi, license key, atau request jaringan eksternal.

## Data yang dicatat

- Page view utama hanya mencakup request `GET` dengan respons HTML 2xx.
- Bot dan prefetch dicatat terpisah serta tidak masuk KPI manusia.
- Bahasa browser diambil dari prioritas tertinggi header `Accept-Language`. Data ini adalah preferensi bahasa, bukan negara atau kewarganegaraan.
- Distribusi jam akses dihitung dan ditampilkan dalam timezone `Asia/Jakarta`.
- Perangkat, browser, sistem operasi, route, referer, waktu respons, pengunjung, dan sesi tetap dicatat.
- Detail page view dan sesi disimpan permanen.
- Cookie `rec_analytics_visitor` berlaku 12 bulan. Database hanya menyimpan HMAC, bukan nilai cookie mentah.
- IP tetap hanya berada dalam audit aktivitas dan tidak disalin ke statistik website.

## Backfill audit lama

Jalankan command berikut jika page view dari audit lama perlu dimasukkan:

```bash
php artisan analytics:backfill
```

Backfill bersifat idempotent. Bahasa browser untuk data historis menjadi `Tidak diketahui` karena audit lama tidak menyimpan header `Accept-Language`.
