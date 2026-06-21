# Statistik website

Statistik website bekerja sepenuhnya dari data request aplikasi. Sistem tidak memakai geolokasi, license key, atau request jaringan eksternal.

## Data yang dicatat

- Page view hanya mencakup pengunjung anonim pada request `GET` dengan respons HTML 2xx untuk route `home`, `public.*`, `materials.*`, dan `auth.login`.
- Setelah user login, seluruh request hanya dicatat dalam audit Aktivitas dan tidak masuk statistik, termasuk ketika membuka kembali halaman publik.
- Request `POST`, redirect, error, download, dan ekspor tidak menjadi page view statistik.
- Bot dan prefetch dicatat terpisah serta tidak masuk KPI manusia.
- Bahasa browser diambil dari prioritas tertinggi header `Accept-Language`. Data ini adalah preferensi bahasa, bukan negara atau kewarganegaraan.
- Distribusi jam akses dihitung dan ditampilkan dalam timezone `Asia/Jakarta`.
- Perangkat, browser, sistem operasi, route, referer, waktu respons, pengunjung, dan sesi tetap dicatat.
- Detail page view dan sesi disimpan permanen.
- Cookie `rec_analytics_visitor` berlaku 12 bulan. Database hanya menyimpan HMAC, bukan nilai cookie mentah.
- IP tetap hanya berada dalam audit aktivitas dan tidak disalin ke statistik website.
- Ringkasan percobaan login (berhasil, gagal, dan terkunci) dihitung langsung dari `activity_events` berdasarkan periode aktif. Tidak ada salinan data login di tabel statistik.

## Pemisahan dari audit Aktivitas

`activity_requests` dan `activity_events` tetap menjadi audit lengkap untuk request publik maupun internal. Daftar Aktivitas menyembunyikan role developer secara default; gunakan filter **Tampilkan aktivitas developer** atau pilih role Developer untuk menampilkannya kembali. Data tersebut tidak dihapus dan detail request tetap dapat dibuka langsung.

## Backfill audit lama

Jalankan command berikut jika page view dari audit lama perlu dimasukkan:

```bash
php artisan analytics:backfill
```

Backfill bersifat idempotent dan memakai scope anonim publik yang sama dengan pencatatan real-time. Bahasa browser untuk data historis menjadi `Tidak diketahui` karena audit lama tidak menyimpan header `Accept-Language`.

Migration `2026_06_21_000004_prune_internal_website_analytics.php` menghapus permanen page view internal/terautentikasi lama, menghapus sesi kosong, dan menghitung ulang sesi yang masih memiliki page view publik.
