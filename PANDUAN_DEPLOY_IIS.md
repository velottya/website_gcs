# Panduan Deploy `website_gcs` (Form Data Karyawan) ke IIS

Panduan lengkap men-deploy aplikasi ini ke server **IIS** perusahaan.
File `web.config` (root) dan `uploads/karyawan/web.config` sudah disiapkan di dalam folder — ikut disalin apa adanya.

> ⚠️ **Penting:** Aplikasi ini mengumpulkan **data pribadi sensitif** (NIK, KTP, KK, ijazah, akta anak) dan
> punya **login**. Deploy sebaiknya sebagai **aplikasi internal (intranet)**. Jika online, **WAJIB HTTPS**
> + **user DB terbatas** (bukan `sa`) + `display_errors=Off`.

---

## 0. Gambaran aplikasi (apa yang di-deploy)

- **Entry point: `login.php`** — user login pakai akun MyGCS (`email` atau `user_easy`) dari tabel `GCS.easy.users` (password bcrypt, hanya `status='Aktif'`).
- Setelah login → **`form_data_karyawan_gcs.php`** (form utama, responsif/mobile-friendly).
- Form kirim data (AJAX) ke **`simpan_karyawan.php`** → INSERT ke `GCS.dbo.MST_PEGAWAI` (+ anak ke `MST_ANAK_PEGAWAI`). Lampiran disimpan di `uploads/karyawan/`, path-nya di DB.
- **`cek_duplikat.php`** — deteksi NIK / ID Karyawan ganda secara real-time & saat submit.
- **`logout.php`** — akhiri sesi. Login butuh **PHP session** (perlu folder session yang writable).

### File & folder penting
| Item | Fungsi | Boleh diakses publik? |
|------|--------|-----------------------|
| `login.php`, `logout.php`, `auth.php` | Autentikasi & sesi | `login`/`logout` ya; `auth.php` hanya di-include |
| `form_data_karyawan_gcs.php` | Form utama (butuh login) | Ya (setelah login) |
| `simpan_karyawan.php`, `cek_duplikat.php` | Endpoint data (butuh login) | Ya (dipanggil form) |
| `config.php` | **Kredensial DB** | **TIDAK** (diblokir web.config) |
| `db.php` | Koneksi + helper | TIDAK (diblokir web.config) |
| `database/*.sql` | Skrip setup DB | TIDAK (diblokir web.config) |
| `*.md`, `.htaccess`, `config.example.php` | Dokumen/contoh | TIDAK (diblokir web.config) |
| `uploads/karyawan/` | Berkas unggahan (nama `IDKaryawan_Dokumen.ext`) | **TIDAK sama sekali** — akses browser diblokir; hanya bisa dibuka dari server (filesystem/RDP) |

---

## 1. Aktifkan IIS + CGI

Server Manager → **Add Roles and Features** → **Web Server (IIS)**, centang:
- **Application Development → CGI**
- **Common HTTP Features → Static Content, Default Document**
- **Security → Request Filtering**

Atau via PowerShell (admin):
```powershell
Enable-WindowsOptionalFeature -Online -FeatureName IIS-CGI, IIS-WebServerRole, IIS-StaticContent, IIS-DefaultDocument, IIS-RequestFiltering
```

## 2. Pasang PHP (Non-Thread-Safe)

Termudah: **Web Platform Installer** → cari "PHP" → install **PHP 8.2 NTS**.
Manual: unduh PHP **NTS x64** dari windows.php.net → ekstrak ke `C:\PHP` → salin `php.ini-production` menjadi `php.ini`.

Edit `php.ini` (minimal):
```ini
extension_dir = "ext"
extension=pdo_sqlsrv
extension=sqlsrv

file_uploads = On
upload_max_filesize = 5M
post_max_size = 60M          ; total beberapa lampiran @5MB
max_file_uploads = 20

display_errors = Off         ; jangan bocorkan error ke publik
log_errors = On

; Session (dibutuhkan login). Pastikan folder ini ADA & writable oleh App Pool.
session.save_path = "C:\PHP\sessions"
session.cookie_httponly = 1
session.gc_maxlifetime = 7200
```
Buat foldernya: `New-Item -ItemType Directory C:\PHP\sessions` (izin ditur di Langkah 6).

## 3. Pasang driver SQL Server untuk PHP

1. Unduh **Microsoft Drivers for PHP for SQL Server** yang **cocok versi & arsitektur** (PHP 8.2, x64, **NTS**). Taruh `php_pdo_sqlsrv_82_nts_x64.dll` & `php_sqlsrv_82_nts_x64.dll` di folder `C:\PHP\ext`.
2. Install **ODBC Driver 18 (atau 17) for SQL Server** di server.
3. Verifikasi:
   ```powershell
   & "C:\PHP\php.exe" -m    # harus memunculkan: pdo_sqlsrv dan sqlsrv
   ```

## 4. Daftarkan handler PHP di IIS

Pakai **PHP Manager for IIS** (Register new PHP version), atau IIS Manager → **Handler Mappings** → **Add Module Mapping**:
- Request path: `*.php`
- Module: `FastCgiModule`
- Executable: `C:\PHP\php-cgi.exe`
- Name: `PHP_via_FastCGI`

> Handler ini di tingkat server, jadi **tidak** ditaruh di `web.config` (agar tak bergantung path server).

## 5. Salin folder & buat Site/Application

1. Salin seluruh isi folder ke server, mis. `C:\inetpub\wwwroot\gcs`.
2. IIS Manager → tambah **Website** baru (punya domain/port sendiri) atau **Application** di bawah Default Web Site.
3. `web.config` bawaan otomatis dipakai: dokumen default, batas unggah 50MB, blokir `config.php`/`db.php`/`database`/`.sql`/`.md`, blokir total akses URL ke folder `uploads`, dan proteksi eksekusi skrip di `uploads/karyawan`.

> **Akses berkas lampiran:** file KTP/KK/dll **tidak bisa dibuka lewat browser sama sekali** (mis. `/gcs/uploads/karyawan/123_KTP.png` → 404/403). Berkas hanya bisa dibuka **langsung dari server** (File Explorer / RDP) di folder `...\uploads\karyawan`. Untuk blokir ini, IIS butuh peran **Request Filtering** aktif (Langkah 1).

> Catatan: `config.php` berisi password DB. Jika Anda menyalin dari lingkungan lain, **tinjau ulang** isinya (lihat Langkah 7). Jangan pernah commit `config.php` ke git (sudah di `.gitignore`).

## 6. Izin folder (uploads + session)

Beri **Application Pool identity** hak **Modify** pada folder unggahan **dan** folder session:
```powershell
icacls "C:\inetpub\wwwroot\gcs\uploads\karyawan" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)M"
icacls "C:\PHP\sessions" /grant "IIS AppPool\DefaultAppPool:(OI)(CI)M"
```
Ganti `DefaultAppPool` dengan nama App Pool site Anda. (Tanpa izin session, login akan gagal menyimpan sesi.)

## 7. Sesuaikan `config.php`

Pastikan `host`/`port` benar **dari sudut pandang server IIS**:
- Jika IIS bisa me-resolve nama: `'host' => 'WIN-0UDHQPRP2VK\\GCS'`
- Jika hanya lewat IP: `'host' => '192.168.100.2'`, `'port' => '49291'`

Pastikan firewall server DB mengizinkan koneksi dari IP server IIS ke port SQL Server.

## 8. (SANGAT DISARANKAN) Pakai user DB terbatas, bukan `sa`

Jalankan skrip **`database/create_limited_user.sql`** di SSMS (ganti dulu password-nya). Skrip itu membuat login `gcs_form_app` dengan izin minimal:
- `INSERT` ke `dbo.MST_PEGAWAI` & `dbo.MST_ANAK_PEGAWAI`
- `SELECT` ke `easy.users` (dibutuhkan proses login)

Lalu di `config.php`: `'username' => 'gcs_form_app'`, `'password' => '...'`.
Ini membatasi kerusakan bila kredensial bocor (tidak bisa akses tabel lain / drop / ubah).

## 9. HTTPS (wajib karena ada login + data pribadi)

IIS Manager → **Bindings** → tambah **https** dengan sertifikat (perusahaan / Let's Encrypt via win-acme).
Disarankan tambahkan redirect HTTP→HTTPS. Setelah HTTPS aktif, boleh perketat sesi di `php.ini`:
```ini
session.cookie_secure = 1
```

## 10. Uji end-to-end

1. Buka `https://<server>/gcs/login.php` → login pakai akun MyGCS (email atau `user_easy`).
   - Akses `form_data_karyawan_gcs.php` tanpa login harus dialihkan ke `login.php`.
2. Isi form + unggah 1 lampiran → **Simpan Data** → muncul "Data berhasil tersimpan ke database."
3. Cek baris masuk di `dbo.MST_PEGAWAI` (dan `MST_ANAK_PEGAWAI` bila ada anak); berkas ada di `uploads/karyawan/`.
4. Uji **duplikat**: input NIK/ID Karyawan yang sudah ada → harus ditolak.
5. Uji **logout** → kembali ke halaman login.

---

## Checklist sebelum go-live

- [ ] `pdo_sqlsrv` & `sqlsrv` terbaca (`php -m`)
- [ ] Handler `*.php` → FastCGI terpasang
- [ ] Folder `uploads/karyawan` **dan** `session.save_path` writable oleh App Pool
- [ ] `config.php` pakai **user DB terbatas** (bukan `sa`)
- [ ] `display_errors = Off`
- [ ] **HTTPS** aktif (+ `session.cookie_secure=1`)
- [ ] Login berhasil, form tersimpan, duplikat tertolak, logout jalan
- [ ] (bila publik) ada anti-abuse (rate limit/captcha) & akses dibatasi ke user yang berhak

---

## Troubleshooting cepat

| Gejala | Kemungkinan sebab | Solusi |
|--------|-------------------|--------|
| Halaman `.php` ter-download / tampil kode | Handler PHP belum terpasang | Ulangi Langkah 4 |
| `HTTP 500` saat buka `.php` | php.ini/driver salah, atau FastCGI error | Lihat Event Viewer / log PHP; cek `php -m` |
| Login selalu gagal walau password benar | Folder session tidak writable | Beri izin ke `session.save_path` (Langkah 6) |
| "Gagal menyimpan data ke database" | Koneksi DB / kredensial / firewall | Cek `config.php`, port SQL, firewall (Langkah 7) |
| Upload gagal untuk file besar | Batas ukuran | Cek `upload_max_filesize`/`post_max_size` (php.ini) & `maxAllowedContentLength` (web.config) |
| Bisa mengakses `config.php` dari browser | web.config tidak terbaca | Pastikan Request Filtering aktif & web.config ikut tersalin |

---

## Kenapa `.htaccess` tidak dipakai di IIS

`.htaccess` hanya untuk Apache. Di IIS, aturan setara ditulis di `web.config` (sudah disediakan).
File `.htaccess` boleh dibiarkan (diabaikan IIS) — berguna bila suatu saat kembali ke Apache/XAMPP.
