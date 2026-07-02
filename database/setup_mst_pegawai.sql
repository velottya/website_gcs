-- Setup skema untuk form_data_karyawan_gcs.html
-- Aman dijalankan berulang kali (idempotent):
--   - Jika tabel belum ada -> dibuat.
--   - Jika tabel sudah ada -> hanya kolom yang belum ada yang ditambahkan (tidak ada data yang dihapus/diubah).
-- Jalankan di database GCS, schema dbo (mis. lewat SSMS atau sqlcmd di server yang sama dengan SQL Server-nya).

USE GCS;
GO

IF OBJECT_ID('dbo.MST_PEGAWAI', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MST_PEGAWAI (
        ID_PEGAWAI              INT IDENTITY(1,1) PRIMARY KEY,
        NAMA_LENGKAP            NVARCHAR(150)   NOT NULL,
        NIK                     VARCHAR(20)     NOT NULL,
        ID_KARYAWAN             VARCHAR(30)     NOT NULL,
        TEMPAT_LAHIR            NVARCHAR(100)   NULL,
        TGL_LAHIR               DATE            NULL,
        JENIS_KELAMIN           VARCHAR(15)     NULL,
        STATUS_KARYAWAN         VARCHAR(20)     NULL,
        FILE_KTP                NVARCHAR(255)   NULL,
        NO_HP                   VARCHAR(20)     NULL,
        EMAIL                   VARCHAR(150)    NULL,
        ALAMAT                  NVARCHAR(255)   NULL,
        RT                      VARCHAR(5)      NULL,
        RW                      VARCHAR(5)      NULL,
        PROVINSI                NVARCHAR(100)   NULL,
        KABUPATEN               NVARCHAR(100)   NULL,
        KECAMATAN               NVARCHAR(100)   NULL,
        DESA                    NVARCHAR(100)   NULL,
        KODE_POS                VARCHAR(10)     NULL,
        AGAMA                   VARCHAR(20)     NULL,
        PENDIDIKAN              VARCHAR(20)     NULL,
        FILE_IJAZAH             NVARCHAR(255)   NULL,
        RIWAYAT_KESEHATAN       NVARCHAR(500)   NULL,
        STATUS_NIKAH            VARCHAR(20)     NULL,
        NAMA_PASANGAN           NVARCHAR(150)   NULL,
        TEMPAT_LAHIR_PASANGAN   NVARCHAR(100)   NULL,
        TGL_LAHIR_PASANGAN      DATE            NULL,
        FILE_BUKU_NIKAH         NVARCHAR(255)   NULL,
        JUMLAH_ANAK             INT             NULL,
        NAMA_DARURAT            NVARCHAR(150)   NULL,
        HP_DARURAT              VARCHAR(20)     NULL,
        FILE_KK                 NVARCHAR(255)   NULL,
        FILE_SIM                NVARCHAR(255)   NULL,   -- SIM B1/B2 (wajib untuk driver)
        FILE_GADA_PRATAMA       NVARCHAR(255)   NULL,   -- Sertifikat Gada Pratama (wajib untuk satpam)
        FILE_K3                 NVARCHAR(255)   NULL,   -- Sertifikasi K3 (wajib untuk petugas K3)
        CREATED_AT              DATETIME        NOT NULL DEFAULT GETDATE(),
        CONSTRAINT UQ_MST_PEGAWAI_NIK UNIQUE (NIK),
        CONSTRAINT UQ_MST_PEGAWAI_ID_KARYAWAN UNIQUE (ID_KARYAWAN)
    );
END
GO

-- Tambahkan kolom yang belum ada bila tabel MST_PEGAWAI ternyata sudah ada sebelumnya
-- dengan struktur yang berbeda/lebih sedikit.
DECLARE @cols TABLE (name SYSNAME, def NVARCHAR(200));
INSERT INTO @cols(name, def) VALUES
 ('NAMA_LENGKAP','NVARCHAR(150) NULL'),
 ('NIK','VARCHAR(20) NULL'),
 ('ID_KARYAWAN','VARCHAR(30) NULL'),
 ('TEMPAT_LAHIR','NVARCHAR(100) NULL'),
 ('TGL_LAHIR','DATE NULL'),
 ('JENIS_KELAMIN','VARCHAR(15) NULL'),
 ('STATUS_KARYAWAN','VARCHAR(20) NULL'),
 ('FILE_KTP','NVARCHAR(255) NULL'),
 ('NO_HP','VARCHAR(20) NULL'),
 ('EMAIL','VARCHAR(150) NULL'),
 ('ALAMAT','NVARCHAR(255) NULL'),
 ('RT','VARCHAR(5) NULL'),
 ('RW','VARCHAR(5) NULL'),
 ('PROVINSI','NVARCHAR(100) NULL'),
 ('KABUPATEN','NVARCHAR(100) NULL'),
 ('KECAMATAN','NVARCHAR(100) NULL'),
 ('DESA','NVARCHAR(100) NULL'),
 ('KODE_POS','VARCHAR(10) NULL'),
 ('AGAMA','VARCHAR(20) NULL'),
 ('PENDIDIKAN','VARCHAR(20) NULL'),
 ('FILE_IJAZAH','NVARCHAR(255) NULL'),
 ('RIWAYAT_KESEHATAN','NVARCHAR(500) NULL'),
 ('STATUS_NIKAH','VARCHAR(20) NULL'),
 ('NAMA_PASANGAN','NVARCHAR(150) NULL'),
 ('TEMPAT_LAHIR_PASANGAN','NVARCHAR(100) NULL'),
 ('TGL_LAHIR_PASANGAN','DATE NULL'),
 ('FILE_BUKU_NIKAH','NVARCHAR(255) NULL'),
 ('JUMLAH_ANAK','INT NULL'),
 ('NAMA_DARURAT','NVARCHAR(150) NULL'),
 ('HP_DARURAT','VARCHAR(20) NULL'),
 ('FILE_KK','NVARCHAR(255) NULL'),
 ('FILE_SIM','NVARCHAR(255) NULL'),
 ('FILE_GADA_PRATAMA','NVARCHAR(255) NULL'),
 ('FILE_K3','NVARCHAR(255) NULL'),
 ('CREATED_AT','DATETIME NULL');

DECLARE @name SYSNAME, @def NVARCHAR(200), @sql NVARCHAR(400);
DECLARE cur CURSOR LOCAL FOR SELECT name, def FROM @cols;
OPEN cur;
FETCH NEXT FROM cur INTO @name, @def;
WHILE @@FETCH_STATUS = 0
BEGIN
    IF COL_LENGTH('dbo.MST_PEGAWAI', @name) IS NULL
    BEGIN
        SET @sql = N'ALTER TABLE dbo.MST_PEGAWAI ADD ' + QUOTENAME(@name) + ' ' + @def;
        EXEC sp_executesql @sql;
    END
    FETCH NEXT FROM cur INTO @name, @def;
END
CLOSE cur;
DEALLOCATE cur;
GO

-- Tabel anak (maks. 3 per pegawai), 1 baris per anak, terhubung ke MST_PEGAWAI.
IF OBJECT_ID('dbo.MST_ANAK_PEGAWAI', 'U') IS NULL
BEGIN
    CREATE TABLE dbo.MST_ANAK_PEGAWAI (
        ID_ANAK             INT IDENTITY(1,1) PRIMARY KEY,
        ID_PEGAWAI          INT NOT NULL,
        URUTAN_ANAK         INT NOT NULL,
        NAMA_ANAK           NVARCHAR(150) NULL,
        TEMPAT_LAHIR_ANAK   NVARCHAR(100) NULL,
        TGL_LAHIR_ANAK      DATE NULL,
        FILE_AKTA           NVARCHAR(255) NULL,
        CREATED_AT          DATETIME NOT NULL DEFAULT GETDATE(),
        CONSTRAINT FK_MST_ANAK_PEGAWAI_PEGAWAI FOREIGN KEY (ID_PEGAWAI)
            REFERENCES dbo.MST_PEGAWAI (ID_PEGAWAI)
    );
END
GO
