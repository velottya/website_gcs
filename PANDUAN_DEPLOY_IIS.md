# Panduan Deploy `website_gcs` ke IIS

Dokumen ini menjelaskan cara men-deploy folder ini ke server **IIS** perusahaan.
File `web.config` (root) dan `uploads/karyawan/web.config` sudah disiapkan di dalam folder ini.

> ⚠️ **Penting:** Form ini mengumpulkan **data pribadi sensitif** (NIK, KTP, KK, ijazah, akta anak).
> Deploy sebaiknya sebagai **aplikasi internal (intranet, di belakang login)**, bukan situs publik terbuka.
> Jika tetap online, WAJIB pakai **HTTPS** + **user DB terbatas** (bukan `sa`) + kontrol akses.

---

## Ringkasan yang harus disiapkan di server IIS

| # | Kebutuhan | Kenapa |
|---|-----------|--------|
| 1 | IIS + peran CGI | Untuk menjalankan PHP via FastCGI |
| 2 | PHP (versi NTS/x64) | IIS menjalankan PHP mode Non-Thread-Safe |
| 3 | Driver `php_pdo_sqlsrv` + `php_sqlsrv` (cocok versi PHP) | Agar PHP bisa konek SQL Server |
| 4 | ODBC Driver 17/18 for SQL Server | Dependensi driver di atas |
| 5 | Handler mapping `*.php` → FastCGI | Agar `simpan_karyawan.php` tereksekusi |
| 6 | Izin tulis folder `uploads/karyawan` | Agar unggahan berkas berhasil |
| 7 | Koneksi jaringan ke SQL Server | `config.php` harus bisa menjangkau DB |
| 8 | Sertifikat HTTPS | Melindungi data pribadi saat dikirim |

---

## Langkah 1 — Aktifkan IIS + CGI

Server Manager → **Add Roles and Features** → **Web Server (IIS)**, pastikan tercentang:
- **Web Server → Application Development → CGI**
- **Web Server → Common HTTP Features → Static Content, Default Document**
- **Web Server → Security → Request Filtering**

(Atau via PowerShell admin:)
```powershell
Enable-WindowsOptionalFeature -Online -FeatureName IIS-CGI, IIS-WebServerRole, IIS-StaticContent, IIS-DefaultDocument, IIS-RequestFiltering
```

## Langkah 2 — Pasang PHP untuk IIS

Cara termudah: **Web Platform Installer** → cari "PHP" → install (mis. PHP 8.2 **Non-Thread-Safe**).
Atau manual: unduh PHP NTS x64 dari windows.php.net, ekstrak ke mis. `C:\PHP`, salin `php.ini-production` → `php.ini`.

Di `php.ini` server, aktifkan minimal:
```ini
extension_dir = "ext"
extension=pdo_sqlsrv
extension=sqlsrv
file_uploads = On
upload_max_filesize = 5M
post_max_size = 50M          ; total beberapa lampiran
max_file_uploads = 20
display_errors = Off         ; jangan tampilkan error ke publik
```

## Langkah 3 — Pasang driver SQL Server untuk PHP

1. Unduh **Microsoft Drivers for PHP for SQL Server** yang **cocok dengan versi & arsitektur PHP** (mis. PHP 8.2, x64, **NTS**). Ambil `php_pdo_sqlsrv_82_nts_x64.dll` dan `php_sqlsrv_82_nts_x64.dll`, taruh di folder `ext` PHP.
2. Unduh & install **ODBC Driver 18 (atau 17) for SQL Server** di server.
3. Verifikasi:
   ```powershell
   & "C:\PHP\php.exe" -m   # harus muncul: pdo_sqlsrv dan sqlsrv
   ```

## Langkah 4 — Daftarkan handler PHP di IIS

Gunakan **PHP Manager for IIS** (register PHP version), atau IIS Manager → **Handler Mappings** → **Add Module Mapping**:
- Request path: `*.php`
- Module: `FastCgiModule`
- Executable: `C:\PHP\php-cgi.exe`
- Name: `PHP_via_FastCGI`

> Handler ini di tingkat server, jadi **tidak** ditaruh di `web.config` (agar tidak bergantung path).

## Langkah 5 — Salin folder & buat Site/Application

1. Salin seluruh isi folder ini ke server, mis. `C:\inetpub\wwwroot\gcs` (atau buat Site tersendiri).
2. IIS Manager → tambah **Website** atau **Application** yang menunjuk ke folder tersebut.
3. `web.config` yang sudah ada akan otomatis dipakai (dokumen default, batas unggah 50MB, blokir `config.php`/`db.php`/`database`/`.sql`/`.md`, dan proteksi eksekusi di `uploads/karyawan`).

## Langkah 6 — Izin tulis folder uploads

Beri **Application Pool identity** (mis. `IIS AppPool\<NamaAppPool>`) hak **Modify** pada folder `uploads\karyawan`:
```powershell
icacls "C:\inetpub\wwwroot\gcs\uploads\karyawan" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)M"
```
(ganti `DefaultAppPool` dengan nama App Pool site Anda)

## Langkah 7 — Sesuaikan `config.php`

Buka `config.php` di server dan pastikan `host`/`port` benar dari sudut pandang **server IIS**:
- Jika IIS bisa me-resolve nama: `'host' => 'WIN-0UDHQPRP2VK\\GCS'`
- Jika hanya lewat IP: `'host' => '192.168.100.2'`, `'port' => '49291'`

Pastikan firewall server DB mengizinkan koneksi dari IP server IIS ke port SQL Server.

## Langkah 8 — (SANGAT DISARANKAN) pakai user DB terbatas, bukan `sa`

Jalankan di SQL Server (SSMS), lalu ganti kredensial di `config.php` ke user ini:
```sql
USE GCS;
CREATE LOGIN gcs_form_app WITH PASSWORD = 'GantiPasswordKuatDiSini!';
CREATE USER gcs_form_app FOR LOGIN gcs_form_app;
GRANT INSERT ON dbo.MST_PEGAWAI     TO gcs_form_app;
GRANT INSERT ON dbo.MST_ANAK_PEGAWAI TO gcs_form_app;
-- (opsional) izin SELECT bila nanti ada halaman lihat data:
-- GRANT SELECT ON dbo.MST_PEGAWAI TO gcs_form_app;
-- GRANT SELECT ON dbo.MST_ANAK_PEGAWAI TO gcs_form_app;
```
Lalu di `config.php`: `'username' => 'gcs_form_app'`, `'password' => 'GantiPasswordKuatDiSini!'`.
Ini membatasi kerusakan bila kredensial bocor (tidak bisa akses tabel lain / drop / dll).

## Langkah 9 — HTTPS

IIS Manager → **Bindings** → tambah **https** dengan sertifikat (dari perusahaan / Let's Encrypt via win-acme).
Disarankan tambah redirect HTTP→HTTPS.

## Langkah 10 — Uji

1. Buka `https://<server>/gcs/login.php` → login pakai akun MyGCS (email atau `user_easy`).
   - Form (`form_data_karyawan_gcs.php`) hanya bisa diakses **setelah login**; akses langsung tanpa sesi akan dialihkan ke halaman login.
2. Isi form + unggah 1 lampiran → klik **Simpan Data**.
3. Harus muncul "Data berhasil tersimpan ke database."
4. Cek baris masuk di tabel `dbo.MST_PEGAWAI`.

> **Autentikasi:** data akun diambil dari `GCS.easy.users` (password bcrypt/Laravel), hanya `status = 'Aktif'` yang boleh masuk. Karena itu user DB aplikasi butuh **SELECT** ke `easy.users` (lihat `database/create_limited_user.sql`).

---

## Checklist cepat sebelum go-live publik

- [ ] HTTPS aktif
- [ ] `config.php` pakai **user DB terbatas** (bukan `sa`)
- [ ] `display_errors = Off` di php.ini server
- [ ] Folder `uploads/karyawan` writable oleh App Pool, tapi **tidak** bisa eksekusi skrip (sudah via `web.config`)
- [ ] Akses form dibatasi (login/intranet) bila memungkinkan
- [ ] Ada mekanisme anti-abuse (rate limit / captcha) bila benar-benar publik

---

## Kenapa `.htaccess` tidak dipakai di IIS

`.htaccess` hanya berlaku di Apache. Di IIS, aturan setara ditulis di `web.config`.
File `.htaccess` boleh dibiarkan (diabaikan IIS) — berguna jika suatu saat kembali ke Apache.
