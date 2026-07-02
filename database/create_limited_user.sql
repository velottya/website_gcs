-- Membuat user DB TERBATAS untuk dipakai aplikasi form (pengganti akun 'sa').
-- Jalankan di SQL Server (SSMS) yang menampung database GCS.
-- Setelah ini, ubah config.php agar memakai user 'gcs_form_app'.
--
-- GANTI dulu password di bawah dengan password yang kuat.

USE GCS;
GO

IF NOT EXISTS (SELECT 1 FROM sys.server_principals WHERE name = 'gcs_form_app')
    CREATE LOGIN gcs_form_app WITH PASSWORD = 'GantiPasswordKuatDiSini!';
GO

IF NOT EXISTS (SELECT 1 FROM sys.database_principals WHERE name = 'gcs_form_app')
    CREATE USER gcs_form_app FOR LOGIN gcs_form_app;
GO

-- Hanya izin yang benar-benar dibutuhkan aplikasi (INSERT ke 2 tabel form)
GRANT INSERT ON dbo.MST_PEGAWAI      TO gcs_form_app;
GRANT INSERT ON dbo.MST_ANAK_PEGAWAI TO gcs_form_app;

-- Login membaca data user dari easy.users (hanya SELECT, read-only)
GRANT SELECT ON easy.users TO gcs_form_app;

-- (Opsional) aktifkan bila nanti dibuat halaman untuk melihat data:
-- GRANT SELECT ON dbo.MST_PEGAWAI      TO gcs_form_app;
-- GRANT SELECT ON dbo.MST_ANAK_PEGAWAI TO gcs_form_app;
GO
