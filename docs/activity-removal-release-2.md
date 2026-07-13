# Runbook Release 2: Penghapusan Data Activity, Analytics, dan Maintenance

Dalam artefak Release 1, migration destruktif disimpan di `database/migrations/deferred`, sehingga tidak ikut dijalankan oleh `php artisan migrate` normal. Setelah Release 1 stabil sedikitnya tujuh hari penuh, file yang sama harus dipromosikan ke direktori migration normal ketika artefak Release 2 dibuat. Migration harus tetap berada di sana sesudah deployment agar instalasi baru dan `migrate:fresh` tidak membuat kembali tabel tracking dari migration historis.

## Membuat artefak Release 2

Lakukan perubahan berikut pada source Release 2 sebelum build, bukan langsung di server produksi:

1. Pindahkan `database/migrations/deferred/2026_07_20_000001_drop_activity_analytics_and_maintenance_tables.php` ke `database/migrations/2026_07_20_000001_drop_activity_analytics_and_maintenance_tables.php`. Jangan mengganti basename migration.
2. Hapus shim transisi `app/Http/Middleware/ExpireLegacyAnalyticsIdentity.php`, lalu hapus import dan registrasinya dari `bootstrap/app.php`. Shim hanya diperlukan selama Release 1 untuk membersihkan cookie/session lama.
3. Jalankan seluruh test, termasuk skenario `migrate:fresh` pada database disposable, dan pastikan semua nama tabel tracking tetap tidak ada.
4. Build artefak produksi dengan Composer authoritative dan frontend production build. Pastikan migration yang dipromosikan ikut artefak dan tidak ada direktori `deferred` yang kosong/tertinggal.

Jangan mempromosikan migration atau menghapus shim sebelum gerbang tujuh hari Release 1 terpenuhi.

## Gerbang sebelum eksekusi

1. Catat jumlah row semua tabel activity/analytics saat Release 1 dipasang. Setelah tujuh hari, pastikan jumlah itu tidak berubah dan aplikasi tidak membuat header activity, cookie analytics, session identity, atau file baru di `storage/app/private/activity-spool`.
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
