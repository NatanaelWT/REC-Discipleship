# Statistik website

## Persiapan GeoLite2

1. Buat license key GeoLite2 pada akun MaxMind.
2. Isi `MAXMIND_LICENSE_KEY` di `.env` produksi.
3. Jalankan `php artisan analytics:geoip-update`.
4. Setelah database lokasi tersedia, jalankan `php artisan analytics:backfill` untuk memproses audit lama.

Database aktif disimpan di `storage/app/geoip/GeoLite2-City.mmdb` dan tidak masuk Git. Untuk memasang file yang sudah diunduh secara manual, gunakan:

```bash
php artisan analytics:geoip-update --source=/path/GeoLite2-City.mmdb
```

## Penjadwalan Hostinger

Tambahkan cron berikut agar Laravel menjalankan pembaruan GeoLite2 mingguan yang terdaftar di scheduler:

```cron
* * * * * cd /path/to/rec && php artisan schedule:run >> /dev/null 2>&1
```

Pembaruan menguji checksum dan database baru sebelum mengganti file aktif. Jika pembaruan gagal, file lama tetap digunakan.

## Perilaku data

- Detail page view dan sesi disimpan permanen.
- KPI manusia tidak memasukkan bot dan prefetch.
- Lokasi adalah perkiraan berdasarkan IP; IP mentah tetap berada pada tabel audit dan tidak disalin ke tabel statistik.
- Cookie `rec_analytics_visitor` berlaku 12 bulan. Database hanya menyimpan HMAC, bukan nilai cookie mentah.
