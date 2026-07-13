# Runbook Release 2: Penghapusan Data Activity, Analytics, dan Maintenance

Source Release 2 sudah siap: migration destruktif berada di `database/migrations/2026_07_20_000001_drop_activity_analytics_and_maintenance_tables.php` dan shim identitas analytics Release 1 sudah dihapus. Migration tetap harus berada di direktori migration normal sesudah deployment agar instalasi baru dan `migrate:fresh` tidak membuat kembali tabel tracking dari migration historis.

## Verifikasi artefak Release 2

Sebelum deployment produksi:

1. Jalankan seluruh test, termasuk skenario `migrate:fresh` pada database disposable, dan pastikan semua nama tabel tracking tidak ada.
2. Build artefak produksi dengan Composer authoritative dan frontend production build.
3. Pastikan migration penghapusan ikut artefak dan `ExpireLegacyAnalyticsIdentity` tidak lagi berada dalam autoload maupun middleware web.
4. Jangan menjalankan migration terhadap database aktif sebelum snapshot database dan storage yang berpasangan selesai dibuat serta diverifikasi.

## Gerbang sebelum eksekusi

1. Bandingkan jumlah row semua tabel activity/analytics saat Release 1 dipasang dengan kondisi sebelum Release 2. Pastikan jumlah itu tidak berubah selama interval validasi yang disetujui dan aplikasi tidak membuat header activity, cookie analytics, session identity, atau file baru di `storage/app/private/activity-spool`.
2. Pastikan test suite, `php artisan rec:schema-health`, dan smoke test route produksi lulus pada build Release 2.
3. Jadwalkan maintenance window dan pastikan operator tetap memiliki akses shell. Jangan menjalankan migration penghapusan saat traffic masih masuk.

## Eksekusi

1. Aktifkan maintenance mode dengan `php artisan down`, lalu pastikan request normal sudah ditolak.
2. Buat snapshot database dan `storage/app/private` dari deployment yang sama, enkripsi snapshot, buat checksum, lalu verifikasi restore pada database sementara. Simpan snapshot hanya selama masa rollback tujuh hari.
3. Catat ukuran dan jumlah row sebelum penghapusan untuk bukti operasional. Jangan menyalin isi row yang mengandung data pribadi ke log deployment.
4. Pastikan `php artisan migrate:status` menunjukkan migration `2026_07_20_000001_drop_activity_analytics_and_maintenance_tables` sebagai pending, lalu jalankan rangkaian berikut dari artefak Release 2:

```bash
php artisan optimize:clear
php artisan rec:schema-health
php artisan migrate --force
php artisan rec:schema-health
php artisan route:list
php artisan cache:clear
php artisan optimize
```

Perintah migration harus menjalankan tepat satu migration penghapusan yang sudah dipromosikan. Jangan memakai `migrate:fresh` atau `db:wipe` pada database produksi. `migrate:fresh` hanya boleh digunakan sebelumnya pada database disposable untuk memverifikasi artefak Release 2.

Setelah migration berhasil:

1. Pastikan tabel `audit_events`, `activity_events`, `peristiwa_aktivitas`, `website_page_views`, `kunjungan_halaman`, `website_daily_rollups`, `maintenance_runs`, `request_activities`, `activity_requests`, `permintaan_aktivitas`, `website_sessions`, `sesi`, dan `aktivitas` sudah tidak ada.
2. Periksa `storage/app/private/activity-spool`. Hapus hanya salinan yang sudah termasuk di snapshot ber-checksum; jangan menyentuh direktori media atau quarantine.
3. Jalankan smoke test untuk halaman publik, login/logout, mutasi data, upload, preview/download PDF, import, dan export. Pastikan tidak ada query yang menyebut tabel di atas.
4. Buka traffic dengan `php artisan up`, lalu pantau log `warning/error` dan slow-request monitoring.

## Rollback dan penutupan

`down()` migration sengaja melempar exception karena membuat tabel kosong tidak memulihkan data. Jika rollback diperlukan, aktifkan maintenance mode, pulihkan snapshot database dan storage yang berpasangan, lalu deploy kembali Release 1.

Setelah tujuh hari Release 2 stabil, hapus snapshot terenkripsi, salinan spool, dan artefak maintenance sesuai kebijakan retensi. Dokumentasikan waktu penghapusan dan checksum snapshot tanpa menyimpan data aktivitasnya.
