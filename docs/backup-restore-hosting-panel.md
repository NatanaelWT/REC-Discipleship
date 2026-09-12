# Runbook Backup dan Restore melalui Panel Hosting

Runbook ini sengaja tidak memakai koneksi database workstation. Semua ekspor dan restore dilakukan melalui panel hosting dengan akun operator berizin minimum.

## Cakupan

Backup harus memasangkan:

- dump database aplikasi;
- `storage/app` dan file upload yang dipakai release yang sama;
- identitas release/commit serta waktu snapshot;
- checksum arsip terenkripsi.

Backup mengandung data pribadi dan kredensial/hash autentikasi. Jangan membuka isi row, mencetak nilai sensitif, mengirim lewat chat/email, atau menyimpan salinan plaintext.

## Membuat backup

1. Jadwalkan maintenance window. Catat commit/release aktif. Aktifkan maintenance melalui fasilitas panel/deployment yang disetujui.
2. Dari menu database panel, ekspor database lengkap dalam format SQL terkompresi. Jangan memakai perintah aplikasi lokal.
3. Dari backup manager/file manager panel, buat arsip `storage/app` dan upload terkait pada waktu yang sama.
4. Gunakan enkripsi AES-256 bawaan panel atau vault organisasi. Jika panel hanya menghasilkan plaintext, unduh langsung ke volume terenkripsi, enkripsi segera, lalu hapus plaintext secara aman sesuai prosedur organisasi.
5. Buat SHA-256 **setelah enkripsi** di PowerShell:

```powershell
Get-FileHash -Algorithm SHA256 .\rec-db-YYYYMMDD-HHMM.enc
Get-FileHash -Algorithm SHA256 .\rec-storage-YYYYMMDD-HHMM.enc
```

6. Simpan checksum, waktu snapshot, commit, ukuran file, operator, lokasi vault, dan tanggal kedaluwarsa. Jangan simpan password enkripsi bersama arsip.
7. Batasi akses vault ke operator pemulihan. Pertahankan satu salinan aktif dan satu salinan terpisah sesuai kebijakan organisasi.

## Verifikasi restore disposable

1. Buat database dan situs **disposable** melalui panel. Jangan gunakan hostname publik, cron, queue, webhook, email, atau integrasi produksi.
2. Buat kredensial sementara unik melalui password manager. Jangan menyalin kredensial produksi.
3. Verifikasi SHA-256 arsip terenkripsi. Dekripsi hanya di lingkungan disposable yang terkontrol.
4. Import dump melalui menu import panel. Pulihkan arsip storage ke direktori disposable.
5. Pasang artefak aplikasi pada commit yang dicatat. Arahkan konfigurasi hanya ke database disposable.
6. Validasi tanpa mencetak PII: status import sukses, tabel wajib tersedia, jumlah tabel/row agregat masuk akal, login akun uji, upload, preview/download file, serta endpoint terlarang menghasilkan `403/404`.
7. Catat hasil, waktu, operator, dan checksum. Jangan lampirkan dump, isi row, nama orang, alamat, nomor kontak, hash password, atau path upload privat.
8. Hapus situs, database, user sementara, file hasil dekripsi, log uji, serta arsip plaintext disposable melalui panel. Verifikasi penghapusan.

## Retensi dan rollback

- Retensi default rollback: 7 hari setelah release stabil, kecuali kebijakan organisasi mewajibkan periode lain.
- Hapus arsip yang kedaluwarsa melalui vault/panel. Catat waktu penghapusan dan checksum saja.
- Restore produksi memerlukan maintenance window, persetujuan operator, backup terkini yang terverifikasi, serta rollback release aplikasi dan storage yang berpasangan.
- Jangan menjalankan `migrate:fresh`, `db:wipe`, seeder snapshot, atau restore percobaan terhadap database produksi.

## Tindakan terkoordinasi terpisah

Snapshot produksi masih dapat berada pada histori Git lama. Penghapusan file saat ini tidak membersihkan histori. History rewrite, invalidasi clone/artefak lama, reset password, serta rotasi secret/database credential harus dijadwalkan dan disetujui bersama seluruh pemilik deployment. Setelah rotasi, audit log akses tanpa menyalin nilai secret ke laporan.
