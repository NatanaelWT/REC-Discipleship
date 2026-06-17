# REC Discipleship Laravel

Project ini adalah aplikasi Laravel mandiri untuk REC. Aplikasi tidak membutuhkan folder `discipleship` lama untuk berjalan.

## Struktur Penting

- `routes/web.php` berisi route Laravel per domain, misalnya `/publik/jurnal-dg`, `/materi`, `/pemuridan/dashboard`, `/pemuridan/msk`, `/pemuridan/pohon`, dan `/ibadah/penatalayan`.
- `app/Http/Controllers` berisi controller Laravel untuk domain auth, publik, pemuridan, ibadah, settings, file aman, dan kompatibilitas URL.
- `app/Models` berisi model Eloquent yang terhubung ke tabel MySQL aplikasi.
- `app/Services` berisi service domain untuk data halaman, penulisan data, upload file, autentikasi, dan katalog materi.
- `app/Support/Helpers` berisi helper domain yang masih dipakai oleh view/service hasil refactor.
- `resources/views` berisi seluruh tampilan aktif dalam format Blade, dengan layout dan partial reusable.
- `public/assets` berisi CSS, JS, logo, dan vendor PDF.js untuk browser.
- `storage/app/private/rec_runtime/uploads` berisi file upload aplikasi yang dilayani lewat route Laravel seperti `/file-aman`.

## Database

Data aplikasi disimpan di tabel MySQL Laravel seperti:

- `users`
- `login_attempts`
- `difficult_questions`
- `discipleship_targets`
- `discipleship_people`
- `discipleship_groups`
- `discipleship_relationships`
- `discipleship_group_memberships`
- `discipleship_group_leaderships`
- `discipleship_group_multiplications`
- `discipleship_meeting_reports`
- `discipleship_member_feedback_journals`
- `msk_participants`
- `church_files`
- `public_material_menus`
- `worship_service_schedules`

Jalankan migrasi database:

```bash
php artisan migrate --force
```

## Menjalankan Lokal

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Lalu buka:

```text
http://127.0.0.1:8000/
```

## Verifikasi

```bash
php artisan test
php artisan migrate:status
php artisan route:list --except-vendor
```

URL lama dengan query `?page=...` tetap diterima hanya sebagai kompatibilitas dan diarahkan ke route Laravel bersih. Contoh:

```text
/?page=public_materials&menu=materi_dg_1 -> /materi?menu=materi_dg_1
/?page=login -> /login
/?page=discipleship_dashboard -> /pemuridan/dashboard
```

Modul jemaat lama, ulang tahun bulanan, kelengkapan data, dashboard utama, akses akun, dan halaman kutisari sudah dihapus. Semua entitas orang diperlakukan sebagai peserta MSK atau anggota pemuridan.
