# REC Discipleship Laravel

Project ini adalah port Laravel dari aplikasi native PHP REC sebelumnya.

Aplikasi ini berdiri sendiri di folder `rec`: runtime kompatibilitas aplikasi lama ada di `app/RecRuntime`, asset publik ada di `public/assets`, file runtime ada di `storage/app/private/rec_runtime`, dan data aplikasi disimpan di tabel MySQL `rec_*` melalui migrasi Laravel.

## Struktur Penting

- `routes/web.php` berisi route Laravel bersih per domain, misalnya `/publik/jurnal-dg`, `/materi`, `/pemuridan/dashboard`, `/jemaat/data`, dan `/ibadah/penatalayan`.
- `app/Http/Controllers/Legacy/*Controller.php` memisahkan entry point berdasarkan domain: publik, auth, jemaat, pemuridan, ibadah, file aman, dan kompatibilitas URL lama.
- `app/Models/Rec` berisi model Eloquent untuk tabel sumber REC yang sudah dipisah.
- `app/Services/Legacy/LegacyRenderer.php` menjalankan renderer lama sebagai bridge sampai seluruh logika domain selesai dipindah ke controller/service Laravel murni.
- `app/Support/LegacyDataStore.php` menjadi adapter baca/tulis data aplikasi langsung ke tabel MySQL.
- `app/RecRuntime/index.php` sekarang hanya bootstrap/dispatcher kompatibilitas, bukan lagi satu file besar seluruh halaman.
- `app/RecRuntime/support` berisi helper/domain function non-tampilan yang sudah dipisah.
- `app/RecRuntime/actions` berisi modul action.
- `resources/views/pages` berisi semua modul halaman dengan ekstensi `.blade.php`.
- `resources/views/partials` berisi tampilan reusable yang dipakai di banyak halaman, seperti layout head/footer, sidebar, alert, input search, form jemaat, pohon pemuridan, dan komponen render lain.
- `storage/app/private/rec_runtime/uploads` berisi file upload aplikasi yang dilayani lewat route Laravel.
- `storage/app/private/rec_runtime/templates` dan `storage/app/private/rec_runtime/assets` disiapkan untuk file runtime internal.
- `public/assets` berisi CSS, JS, logo, dan vendor PDF.js untuk browser.
- Tabel MySQL `rec_*` memakai kolom eksplisit per data dan tidak lagi memakai kolom metadata generik seperti `document_*`, `sort_order`, `legacy_id`, `payload`, `payload_checksum`, `source_updated_at`, `uploaded_at_legacy`, atau `updated_at_legacy`.

## Database

Migrasi tambahan:

- `sessions` untuk session Laravel.
- tabel sumber REC terpisah per JSON lama: `rec_users`, `rec_church_files`, `rec_people_registry`, `rec_discipleship_groups`, `rec_discipleship_relationships`, `rec_dg_meeting_reports`, `rec_dg_member_feedback_journals`, `rec_discipleship_targets`, `rec_worship_penatalayan_schedules`, `rec_login_attempts`, dan `rec_difficult_questions`.

Jalankan migrasi database:

```bash
php artisan migrate --force
```

## Menjalankan Lokal

```bash
php artisan serve --host=127.0.0.1 --port=8001
```

Lalu buka:

```text
http://127.0.0.1:8001/
```

## Verifikasi

```bash
php artisan test
php artisan migrate:status
```

Route tetap kompatibel dengan pola aplikasi lama, misalnya:

```text
/?page=public_materials&menu=materi_dg_1
/?page=login
/?page=discipleship_dashboard
```

Pola lama tersebut akan diarahkan ke route Laravel bersih:

```text
/materi?menu=materi_dg_1
/login
/pemuridan/dashboard
```
