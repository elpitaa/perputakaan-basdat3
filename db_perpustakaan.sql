/* =====================================================================
   db_perpustakaan - konversi MariaDB 10.4 (phpMyAdmin dump) ke T-SQL
   Target : Microsoft SQL Server 2012 atau lebih baru (SSMS)
   Catatan: Jalankan pada database yang sedang aktif (tidak ada CREATE DATABASE).
   Urutan : 1) tabel  2) data  3) PK/unique/index  4) foreign key
            5) view   6) trigger
   ===================================================================== */

SET NOCOUNT ON;
SET ANSI_NULLS ON;
SET QUOTED_IDENTIFIER ON;   -- wajib untuk filtered unique index
GO

/* =====================================================================
   1. CREATE TABLE (tanpa PK/FK/index; ENUM -> VARCHAR + CHECK)
   ===================================================================== */

CREATE TABLE [dbo].[cabang] (
  [id_cabang]   INT IDENTITY(1,1) NOT NULL,
  [kode_cabang] VARCHAR(10)  NOT NULL,
  [nama_cabang] VARCHAR(100) NOT NULL,
  [jenis]       VARCHAR(6)   NOT NULL CONSTRAINT [DF_cabang_jenis] DEFAULT 'CABANG',
  [alamat]      VARCHAR(255) NULL,
  [telepon]     VARCHAR(20)  NULL,
  [status]      VARCHAR(8)   NOT NULL CONSTRAINT [DF_cabang_status] DEFAULT 'AKTIF',
  CONSTRAINT [CK_cabang_jenis]  CHECK ([jenis]  IN ('PUSAT','CABANG')),
  CONSTRAINT [CK_cabang_status] CHECK ([status] IN ('AKTIF','NONAKTIF'))
);
GO

CREATE TABLE [dbo].[kategori_buku] (
  [id_kategori]   INT IDENTITY(1,1) NOT NULL,
  [nama_kategori] VARCHAR(100) NOT NULL,
  [deskripsi]     VARCHAR(255) NULL
);
GO

CREATE TABLE [dbo].[petugas] (
  [id_petugas]   INT IDENTITY(1,1) NOT NULL,
  [id_cabang]    INT NOT NULL,
  [nama_petugas] VARCHAR(100) NOT NULL,
  [telepon]      VARCHAR(20)  NULL,
  [status]       VARCHAR(8)   NOT NULL CONSTRAINT [DF_petugas_status] DEFAULT 'AKTIF',
  CONSTRAINT [CK_petugas_status] CHECK ([status] IN ('AKTIF','NONAKTIF'))
);
GO

CREATE TABLE [dbo].[anggota] (
  [id_anggota]       INT IDENTITY(1,1) NOT NULL,
  [nomor_anggota]    VARCHAR(20)  NOT NULL,
  [nama_anggota]     VARCHAR(100) NOT NULL,
  [jenis_kelamin]    VARCHAR(1)   NULL,
  [email]            VARCHAR(100) NULL,
  [telepon]          VARCHAR(20)  NULL,
  [alamat]           VARCHAR(255) NULL,
  [tanggal_daftar]   DATE NOT NULL,
  [id_cabang_daftar] INT  NOT NULL,
  [status]           VARCHAR(8)   NOT NULL CONSTRAINT [DF_anggota_status] DEFAULT 'AKTIF',
  CONSTRAINT [CK_anggota_jenis_kelamin] CHECK ([jenis_kelamin] IN ('L','P')),
  CONSTRAINT [CK_anggota_status]        CHECK ([status] IN ('AKTIF','NONAKTIF','DIBLOKIR'))
);
GO

CREATE TABLE [dbo].[buku] (
  [id_buku]      INT IDENTITY(1,1) NOT NULL,
  [isbn]         VARCHAR(20)  NULL,
  [judul]        VARCHAR(200) NOT NULL,
  [penulis]      VARCHAR(150) NOT NULL,
  [penerbit]     VARCHAR(100) NULL,
  [tahun_terbit] SMALLINT NULL,
  [harga]        DECIMAL(12,2) NOT NULL CONSTRAINT [DF_buku_harga] DEFAULT 0.00,
  [id_kategori]  INT NOT NULL
);
GO

CREATE TABLE [dbo].[akun_pengguna] (
  [id_akun]       INT IDENTITY(1,1) NOT NULL,
  [username]      VARCHAR(50)  NOT NULL,
  [password_hash] VARCHAR(255) NOT NULL,
  [role]          VARCHAR(7)   NOT NULL CONSTRAINT [DF_akun_pengguna_role] DEFAULT 'PETUGAS',
  [id_petugas]    INT NULL,
  [id_anggota]    INT NULL,
  [status]        VARCHAR(8)   NOT NULL CONSTRAINT [DF_akun_pengguna_status] DEFAULT 'AKTIF',
  [terakhir_login] DATETIME2(0) NULL,
  CONSTRAINT [CK_akun_pengguna_role]   CHECK ([role]   IN ('ADMIN','PETUGAS','ANGGOTA')),
  CONSTRAINT [CK_akun_pengguna_status] CHECK ([status] IN ('AKTIF','NONAKTIF'))
);
GO

CREATE TABLE [dbo].[pengaturan] (
  [nama_pengaturan] VARCHAR(50)  NOT NULL,
  [nilai]           VARCHAR(50)  NOT NULL,
  [keterangan]      VARCHAR(255) NULL
);
GO

CREATE TABLE [dbo].[peminjaman] (
  [id_peminjaman]       INT IDENTITY(1,1) NOT NULL,
  [no_transaksi]        VARCHAR(25) NOT NULL,
  [id_anggota]          INT NOT NULL,
  [id_petugas]          INT NOT NULL,
  [id_cabang]           INT NOT NULL,
  [tanggal_pinjam]      DATE NOT NULL,
  [tanggal_jatuh_tempo] DATE NOT NULL,
  [status]              VARCHAR(8) NOT NULL CONSTRAINT [DF_peminjaman_status] DEFAULT 'DIPINJAM',
  [catatan]             VARCHAR(255) NULL,
  CONSTRAINT [CK_peminjaman_status] CHECK ([status] IN ('DIPINJAM','SELESAI'))
);
GO

CREATE TABLE [dbo].[detail_peminjaman] (
  [id_detail_peminjaman] INT IDENTITY(1,1) NOT NULL,
  [id_peminjaman]        INT NOT NULL,
  [id_buku]              INT NOT NULL
);
GO

CREATE TABLE [dbo].[pengembalian] (
  [id_pengembalian] INT IDENTITY(1,1) NOT NULL,
  [no_pengembalian] VARCHAR(25) NOT NULL,
  [id_peminjaman]   INT NOT NULL,
  [id_petugas]      INT NOT NULL,
  [tanggal_kembali] DATE NOT NULL,
  [catatan]         VARCHAR(255) NULL
);
GO

CREATE TABLE [dbo].[detail_pengembalian] (
  [id_detail_pengembalian] INT IDENTITY(1,1) NOT NULL,
  [id_pengembalian]        INT NOT NULL,
  [id_detail_peminjaman]   INT NOT NULL,
  [kondisi_buku]           VARCHAR(6) NOT NULL CONSTRAINT [DF_detail_pengembalian_kondisi] DEFAULT 'BAIK',
  CONSTRAINT [CK_detail_pengembalian_kondisi] CHECK ([kondisi_buku] IN ('BAIK','RUSAK','HILANG'))
);
GO

CREATE TABLE [dbo].[denda] (
  [id_denda]               INT IDENTITY(1,1) NOT NULL,
  [id_detail_pengembalian] INT NOT NULL,
  [jenis_denda]            VARCHAR(9)  NOT NULL,
  [jumlah_hari_terlambat]  INT NOT NULL CONSTRAINT [DF_denda_hari] DEFAULT 0,
  [jumlah_denda]           DECIMAL(12,2) NOT NULL,
  [status_bayar]           VARCHAR(11) NOT NULL CONSTRAINT [DF_denda_status_bayar] DEFAULT 'BELUM_LUNAS',
  [tanggal_bayar]          DATE NULL,
  CONSTRAINT [CK_denda_jenis]        CHECK ([jenis_denda]  IN ('TERLAMBAT','RUSAK','HILANG')),
  CONSTRAINT [CK_denda_status_bayar] CHECK ([status_bayar] IN ('BELUM_LUNAS','LUNAS'))
);
GO

CREATE TABLE [dbo].[stok_buku_cabang] (
  [id_cabang]       INT NOT NULL,
  [id_buku]         INT NOT NULL,
  [jumlah_total]    INT NOT NULL CONSTRAINT [DF_stok_total] DEFAULT 0,
  [jumlah_tersedia] INT NOT NULL CONSTRAINT [DF_stok_tersedia] DEFAULT 0,
  [lokasi_rak]      VARCHAR(30) NULL
);
GO

CREATE TABLE [dbo].[pengajuan_peminjaman] (
  [id_pengajuan]      INT IDENTITY(1,1) NOT NULL,
  [no_pengajuan]      VARCHAR(25) NOT NULL,
  [id_anggota]        INT NOT NULL,
  [id_cabang]         INT NOT NULL,
  [tanggal_pengajuan] DATETIME2(0) NOT NULL CONSTRAINT [DF_pengajuan_tanggal] DEFAULT SYSDATETIME(),
  [status]            VARCHAR(10) NOT NULL CONSTRAINT [DF_pengajuan_status] DEFAULT 'MENUNGGU',
  [id_petugas]        INT NULL,
  [tanggal_diproses]  DATETIME2(0) NULL,
  [catatan_anggota]   VARCHAR(255) NULL,
  [catatan_petugas]   VARCHAR(255) NULL,
  CONSTRAINT [CK_pengajuan_status] CHECK ([status] IN ('MENUNGGU','DISETUJUI','DITOLAK','DIBATALKAN'))
);
GO

CREATE TABLE [dbo].[detail_pengajuan_peminjaman] (
  [id_detail_pengajuan] INT IDENTITY(1,1) NOT NULL,
  [id_pengajuan]        INT NOT NULL,
  [id_buku]             INT NOT NULL
);
GO

/* =====================================================================
   2. INSERT DATA (trigger belum ada, sehingga data dimuat apa adanya)
   ===================================================================== */

SET IDENTITY_INSERT [dbo].[cabang] ON;
INSERT INTO [dbo].[cabang] ([id_cabang], [kode_cabang], [nama_cabang], [jenis], [alamat], [telepon], [status]) VALUES
(1, 'PST', 'Perpustakaan Pusat', 'PUSAT', 'Jl. Merdeka No. 1', '031-5550001', 'AKTIF'),
(2, 'CB01', 'Cabang Barat', 'CABANG', 'Jl. Raya Barat No. 25', '031-5550002', 'AKTIF'),
(3, 'CB02', 'Cabang Timur', 'CABANG', 'Jl. Kampus Timur No. 8', '031-5550003', 'AKTIF');
SET IDENTITY_INSERT [dbo].[cabang] OFF;
DBCC CHECKIDENT (N'dbo.cabang', RESEED, 3) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[kategori_buku] ON;
INSERT INTO [dbo].[kategori_buku] ([id_kategori], [nama_kategori], [deskripsi]) VALUES
(1, 'Pemrograman', 'Buku tentang bahasa dan teknik pemrograman'),
(2, 'Basis Data', 'Buku tentang perancangan dan pengelolaan basis data'),
(3, 'Jaringan Komputer', 'Buku tentang jaringan dan keamanan jaringan'),
(4, 'Sistem Informasi', 'Buku tentang analisis dan perancangan sistem'),
(5, 'Fiksi', 'Novel dan cerita fiksi'),
(6, 'Sejarah', 'Buku sejarah dan sosial budaya');
SET IDENTITY_INSERT [dbo].[kategori_buku] OFF;
DBCC CHECKIDENT (N'dbo.kategori_buku', RESEED, 6) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[petugas] ON;
INSERT INTO [dbo].[petugas] ([id_petugas], [id_cabang], [nama_petugas], [telepon], [status]) VALUES
(1, 1, 'Hendra Kurniawan', '081200000001', 'AKTIF'),
(2, 1, 'Lestari Ayu', '081200000002', 'AKTIF'),
(3, 2, 'Agus Setiawan', '081200000003', 'AKTIF'),
(4, 3, 'Rina Marlina', '081200000004', 'AKTIF'),
(5, 2, 'Joko Susilo', '081200000005', 'NONAKTIF');
SET IDENTITY_INSERT [dbo].[petugas] OFF;
DBCC CHECKIDENT (N'dbo.petugas', RESEED, 5) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[anggota] ON;
INSERT INTO [dbo].[anggota] ([id_anggota], [nomor_anggota], [nama_anggota], [jenis_kelamin], [email], [telepon], [alamat], [tanggal_daftar], [id_cabang_daftar], [status]) VALUES
(1, 'AGT-0001', 'Andi Pratama', 'L', 'andi.pratama@mail.com', '081311110001', 'Jl. Melati No. 3', '2025-09-22', 1, 'AKTIF'),
(2, 'AGT-0002', 'Siti Rahmawati', 'P', 'siti.rahma@mail.com', '081311110002', 'Jl. Mawar No. 12', '2025-11-11', 2, 'NONAKTIF'),
(3, 'AGT-0003', 'Budi Santoso', 'L', 'budi.santoso@mail.com', '081311110003', 'Jl. Kenanga No. 7', '2025-12-31', 3, 'AKTIF'),
(4, 'AGT-0004', 'Dewi Anggraini', 'P', 'dewi.anggraini@mail.com', '081311110004', 'Jl. Anggrek No. 21', '2026-02-19', 1, 'AKTIF'),
(5, 'AGT-0005', 'Rizky Maulana', 'L', 'rizky.maulana@mail.com', '081311110005', 'Jl. Dahlia No. 5', '2026-04-10', 2, 'NONAKTIF'),
(6, 'AGT-0006', 'Nurul Hidayah', 'P', 'nurul.hidayah@mail.com', '081311110006', 'Jl. Flamboyan No. 9', '2026-05-30', 3, 'AKTIF'),
(7, 'AGT-0007', 'Fajar Nugroho', 'L', 'fajar.nugroho@mail.com', '081311110007', 'Jl. Cempaka No. 14', '2026-06-29', 1, 'AKTIF'),
(8, 'AGT-0008', 'Maya Putri', 'P', 'maya.putri@mail.com', '081311110008', 'Jl. Teratai No. 2', '2025-06-14', 2, 'NONAKTIF'),
(9, '10', 'elvita', 'P', 'elvitadianpraaa@gmail.com', '0837462682774', 'bangkalan', '2026-10-30', 3, 'AKTIF');
SET IDENTITY_INSERT [dbo].[anggota] OFF;
DBCC CHECKIDENT (N'dbo.anggota', RESEED, 9) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[buku] ON;
INSERT INTO [dbo].[buku] ([id_buku], [isbn], [judul], [penulis], [penerbit], [tahun_terbit], [harga], [id_kategori]) VALUES
(1, '978-602-0000-01-1', 'Clean Code', 'Robert C. Martin', 'Prentice Hall', 2008, 450000.00, 1),
(2, '978-602-0000-02-2', 'Belajar Python untuk Pemula', 'Budi Raharjo', 'Informatika', 2021, 95000.00, 1),
(3, '978-602-0000-03-3', 'Pemrograman Web dengan PHP dan MySQL', 'Abdul Kadir', 'Andi Offset', 2019, 85000.00, 1),
(4, '978-602-0000-04-4', 'Database System Concepts', 'Abraham Silberschatz', 'McGraw-Hill', 2019, 650000.00, 2),
(5, '978-602-0000-05-5', 'Basis Data', 'Fathansyah', 'Informatika', 2018, 90000.00, 2),
(6, '978-602-0000-06-6', 'Perancangan Basis Data Relasional', 'Rina Kusuma', 'Graha Ilmu', 2020, 75000.00, 2),
(7, '978-602-0000-07-7', 'Jaringan Komputer', 'Andrew S. Tanenbaum', 'Pearson', 2011, 500000.00, 3),
(8, '978-602-0000-08-8', 'Dasar-Dasar Keamanan Jaringan', 'Hendra Wijaya', 'Informatika', 2020, 98000.00, 3),
(9, '978-602-0000-09-9', 'Analisis dan Perancangan Sistem Informasi', 'Jogiyanto HM', 'Andi Offset', 2005, 120000.00, 4),
(10, '978-602-0000-10-0', 'Laskar Pelangi', 'Andrea Hirata', 'Bentang Pustaka', 2005, 85000.00, 5),
(11, '978-602-0000-11-1', 'Bumi Manusia', 'Pramoedya Ananta Toer', 'Lentera Dipantara', 1980, 110000.00, 5),
(12, '978-602-0000-12-2', 'Sejarah Indonesia Modern', 'M.C. Ricklefs', 'Serambi', 2008, 180000.00, 6);
SET IDENTITY_INSERT [dbo].[buku] OFF;
DBCC CHECKIDENT (N'dbo.buku', RESEED, 12) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[akun_pengguna] ON;
INSERT INTO [dbo].[akun_pengguna] ([id_akun], [username], [password_hash], [role], [id_petugas], [id_anggota], [status], [terakhir_login]) VALUES
(1, 'superadmin', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', 'ADMIN', NULL, NULL, 'AKTIF', '2026-11-03 18:07:19'),
(2, 'hendra.admin', '$2y$10$qfnRYK29votluyZCsmQuYuj1fVxsoVhHzs0KvicCiTOTaiSjLkDNK', 'ADMIN', 1, NULL, 'AKTIF', NULL),
(3, 'lestari', '$2y$10$MUokV8vCqUv1lg1ZnFy.YOiX2vCTo1/6SnbItHeJa9mCu02EM.jpC', 'PETUGAS', 2, NULL, 'AKTIF', '2026-10-30 19:12:24'),
(4, 'agus', '$2y$10$kb2h/PO6fDdXaigh94hqGOgROIOk.i44cnvg/ofxRX8.ShngCY1FC', 'PETUGAS', 3, NULL, 'AKTIF', '2026-11-03 18:06:38'),
(5, 'rina', '$2y$10$w9kj4eUc90H3xk8HtrOizei4GDNy6HKehE8sH/TtjPBuc0HtvQXqu', 'PETUGAS', 4, NULL, 'AKTIF', '2026-11-03 18:06:23'),
(6, 'joko', '$2y$10$XOmuKlIPIXFcgtOYxQ3h9uYE3QDBUHdS5yH2nXvX14Q7QWLD.oR8C', 'PETUGAS', 5, NULL, 'NONAKTIF', NULL),
(7, 'elvita', '$2y$10$isw31i.AcFxbVUvmNJmweeuHAziUxS/Sd86NZOaQMtVCqVi6Sfl.e', 'ANGGOTA', NULL, 9, 'AKTIF', '2026-11-03 18:10:12');
SET IDENTITY_INSERT [dbo].[akun_pengguna] OFF;
DBCC CHECKIDENT (N'dbo.akun_pengguna', RESEED, 7) WITH NO_INFOMSGS;
GO

INSERT INTO [dbo].[pengaturan] ([nama_pengaturan], [nilai], [keterangan]) VALUES
('denda_per_hari', '1000', 'Denda keterlambatan per buku per hari (Rupiah)'),
('lama_pinjam_hari', '7', 'Lama peminjaman standar (hari) - dipakai aplikasi saat mengisi jatuh tempo'),
('maks_buku_per_anggota', '3', 'Jumlah maksimal buku yang boleh dipinjam bersamaan oleh satu anggota'),
('persen_denda_hilang', '100', 'Denda buku hilang = persen ini x harga buku'),
('persen_denda_rusak', '25', 'Denda buku rusak = persen ini x harga buku');
GO

SET IDENTITY_INSERT [dbo].[peminjaman] ON;
INSERT INTO [dbo].[peminjaman] ([id_peminjaman], [no_transaksi], [id_anggota], [id_petugas], [id_cabang], [tanggal_pinjam], [tanggal_jatuh_tempo], [status], [catatan]) VALUES
(1, 'PJM-0001', 1, 2, 1, '2026-09-27', '2026-10-04', 'SELESAI', 'Dikembalikan tepat waktu'),
(2, 'PJM-0002', 2, 3, 2, '2026-10-07', '2026-10-14', 'DIPINJAM', 'Dikembalikan sebagian'),
(3, 'PJM-0003', 3, 4, 3, '2026-10-12', '2026-10-19', 'SELESAI', 'Dikembalikan terlambat'),
(4, 'PJM-0004', 4, 2, 1, '2026-10-22', '2026-10-29', 'DIPINJAM', 'Masih berjalan'),
(5, 'PJM-0005', 5, 3, 2, '2026-10-15', '2026-10-22', 'DIPINJAM', 'Belum kembali, terlambat'),
(6, 'PJM-0006', 6, 4, 3, '2026-09-17', '2026-09-24', 'SELESAI', 'Satu buku rusak'),
(7, 'PJM-0007', 7, 1, 1, '2026-08-28', '2026-09-04', 'SELESAI', 'Buku hilang');
SET IDENTITY_INSERT [dbo].[peminjaman] OFF;
DBCC CHECKIDENT (N'dbo.peminjaman', RESEED, 7) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[detail_peminjaman] ON;
INSERT INTO [dbo].[detail_peminjaman] ([id_detail_peminjaman], [id_peminjaman], [id_buku]) VALUES
(1, 1, 1),
(2, 1, 4),
(3, 2, 2),
(4, 2, 3),
(5, 3, 4),
(6, 3, 5),
(7, 4, 2),
(8, 4, 5),
(9, 4, 10),
(10, 5, 9),
(11, 5, 10),
(12, 6, 7),
(13, 6, 12),
(14, 7, 11);
SET IDENTITY_INSERT [dbo].[detail_peminjaman] OFF;
DBCC CHECKIDENT (N'dbo.detail_peminjaman', RESEED, 14) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[pengembalian] ON;
INSERT INTO [dbo].[pengembalian] ([id_pengembalian], [no_pengembalian], [id_peminjaman], [id_petugas], [tanggal_kembali], [catatan]) VALUES
(1, 'KMB-0001', 1, 2, '2026-10-02', NULL),
(2, 'KMB-0002', 2, 3, '2026-10-17', 'Hanya 1 buku dikembalikan'),
(3, 'KMB-0003', 3, 4, '2026-10-25', NULL),
(4, 'KMB-0004', 6, 4, '2026-09-26', 'Buku jaringan sampulnya rusak'),
(5, 'KMB-0005', 7, 1, '2026-09-12', 'Anggota melaporkan buku hilang');
SET IDENTITY_INSERT [dbo].[pengembalian] OFF;
DBCC CHECKIDENT (N'dbo.pengembalian', RESEED, 5) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[detail_pengembalian] ON;
INSERT INTO [dbo].[detail_pengembalian] ([id_detail_pengembalian], [id_pengembalian], [id_detail_peminjaman], [kondisi_buku]) VALUES
(1, 1, 1, 'BAIK'),
(2, 1, 2, 'BAIK'),
(3, 2, 3, 'BAIK'),
(4, 3, 5, 'BAIK'),
(5, 3, 6, 'BAIK'),
(6, 4, 12, 'RUSAK'),
(7, 4, 13, 'BAIK'),
(8, 5, 14, 'HILANG');
SET IDENTITY_INSERT [dbo].[detail_pengembalian] OFF;
DBCC CHECKIDENT (N'dbo.detail_pengembalian', RESEED, 8) WITH NO_INFOMSGS;
GO

SET IDENTITY_INSERT [dbo].[denda] ON;
INSERT INTO [dbo].[denda] ([id_denda], [id_detail_pengembalian], [jenis_denda], [jumlah_hari_terlambat], [jumlah_denda], [status_bayar], [tanggal_bayar]) VALUES
(1, 3, 'TERLAMBAT', 3, 3000.00, 'LUNAS', '2026-10-18'),
(2, 4, 'TERLAMBAT', 6, 6000.00, 'BELUM_LUNAS', NULL),
(3, 5, 'TERLAMBAT', 6, 6000.00, 'BELUM_LUNAS', NULL),
(4, 6, 'TERLAMBAT', 2, 2000.00, 'LUNAS', '2026-09-27'),
(5, 6, 'RUSAK', 0, 125000.00, 'BELUM_LUNAS', NULL),
(6, 7, 'TERLAMBAT', 2, 2000.00, 'LUNAS', '2026-09-27'),
(7, 8, 'TERLAMBAT', 8, 8000.00, 'LUNAS', '2026-09-13'),
(8, 8, 'HILANG', 0, 110000.00, 'LUNAS', '2026-09-13');
SET IDENTITY_INSERT [dbo].[denda] OFF;
DBCC CHECKIDENT (N'dbo.denda', RESEED, 8) WITH NO_INFOMSGS;
GO

INSERT INTO [dbo].[stok_buku_cabang] ([id_cabang], [id_buku], [jumlah_total], [jumlah_tersedia], [lokasi_rak]) VALUES
(1, 1, 5, 5, 'A-01'),
(1, 2, 3, 2, 'A-02'),
(1, 3, 3, 3, 'A-03'),
(1, 4, 2, 2, 'B-01'),
(1, 5, 4, 3, 'B-02'),
(1, 6, 2, 2, 'B-03'),
(1, 7, 2, 2, 'C-01'),
(1, 8, 2, 2, 'C-02'),
(1, 9, 2, 2, 'D-01'),
(1, 10, 3, 2, 'E-01'),
(1, 11, 2, 2, 'E-02'),
(1, 12, 2, 2, 'F-01'),
(2, 1, 2, 2, 'A-01'),
(2, 2, 3, 3, 'A-02'),
(2, 3, 2, 1, 'A-03'),
(2, 5, 2, 2, 'B-01'),
(2, 9, 1, 0, 'D-01'),
(2, 10, 2, 1, 'E-01'),
(2, 11, 1, 1, 'E-02'),
(3, 2, 2, 2, 'A-01'),
(3, 3, 2, 2, 'A-02'),
(3, 4, 1, 1, 'B-01'),
(3, 5, 3, 3, 'B-02'),
(3, 7, 2, 2, 'C-01'),
(3, 10, 2, 2, 'E-01'),
(3, 12, 2, 2, 'F-01');
GO


/* =====================================================================
   3. PRIMARY KEY, UNIQUE INDEX, INDEX
   Catatan: UNIQUE MySQL mengizinkan banyak NULL; SQL Server tidak.
   Karena itu kolom nullable memakai filtered unique index (WHERE ... IS NOT NULL).
   ===================================================================== */

ALTER TABLE [dbo].[akun_pengguna] ADD CONSTRAINT [PK_akun_pengguna] PRIMARY KEY CLUSTERED ([id_akun]);
CREATE UNIQUE INDEX [uq_akun_username] ON [dbo].[akun_pengguna] ([username]);
CREATE UNIQUE INDEX [uq_akun_petugas]  ON [dbo].[akun_pengguna] ([id_petugas]) WHERE [id_petugas] IS NOT NULL;
CREATE UNIQUE INDEX [uq_akun_anggota]  ON [dbo].[akun_pengguna] ([id_anggota]) WHERE [id_anggota] IS NOT NULL;
GO

ALTER TABLE [dbo].[anggota] ADD CONSTRAINT [PK_anggota] PRIMARY KEY CLUSTERED ([id_anggota]);
CREATE UNIQUE INDEX [uq_anggota_nomor] ON [dbo].[anggota] ([nomor_anggota]);
CREATE UNIQUE INDEX [uq_anggota_email] ON [dbo].[anggota] ([email]) WHERE [email] IS NOT NULL;
CREATE INDEX [fk_anggota_cabang]  ON [dbo].[anggota] ([id_cabang_daftar]);
CREATE INDEX [idx_anggota_nama]   ON [dbo].[anggota] ([nama_anggota]);
GO

ALTER TABLE [dbo].[buku] ADD CONSTRAINT [PK_buku] PRIMARY KEY CLUSTERED ([id_buku]);
CREATE UNIQUE INDEX [uq_buku_isbn] ON [dbo].[buku] ([isbn]) WHERE [isbn] IS NOT NULL;
CREATE INDEX [fk_buku_kategori] ON [dbo].[buku] ([id_kategori]);
CREATE INDEX [idx_buku_judul]   ON [dbo].[buku] ([judul]);
CREATE INDEX [idx_buku_penulis] ON [dbo].[buku] ([penulis]);
GO

ALTER TABLE [dbo].[cabang] ADD CONSTRAINT [PK_cabang] PRIMARY KEY CLUSTERED ([id_cabang]);
CREATE UNIQUE INDEX [uq_cabang_kode] ON [dbo].[cabang] ([kode_cabang]);
GO

ALTER TABLE [dbo].[denda] ADD CONSTRAINT [PK_denda] PRIMARY KEY CLUSTERED ([id_denda]);
CREATE UNIQUE INDEX [uq_denda_item_jenis] ON [dbo].[denda] ([id_detail_pengembalian], [jenis_denda]);
CREATE INDEX [idx_denda_status] ON [dbo].[denda] ([status_bayar]);
GO

ALTER TABLE [dbo].[detail_peminjaman] ADD CONSTRAINT [PK_detail_peminjaman] PRIMARY KEY CLUSTERED ([id_detail_peminjaman]);
CREATE UNIQUE INDEX [uq_detail_peminjaman] ON [dbo].[detail_peminjaman] ([id_peminjaman], [id_buku]);
CREATE INDEX [idx_detailpinjam_buku] ON [dbo].[detail_peminjaman] ([id_buku]);
GO

ALTER TABLE [dbo].[detail_pengajuan_peminjaman] ADD CONSTRAINT [PK_detail_pengajuan_peminjaman] PRIMARY KEY CLUSTERED ([id_detail_pengajuan]);
CREATE UNIQUE INDEX [uq_detail_pengajuan_buku] ON [dbo].[detail_pengajuan_peminjaman] ([id_pengajuan], [id_buku]);
CREATE INDEX [idx_detail_pengajuan_buku] ON [dbo].[detail_pengajuan_peminjaman] ([id_buku]);
GO

ALTER TABLE [dbo].[detail_pengembalian] ADD CONSTRAINT [PK_detail_pengembalian] PRIMARY KEY CLUSTERED ([id_detail_pengembalian]);
CREATE UNIQUE INDEX [uq_detail_pengembalian_item] ON [dbo].[detail_pengembalian] ([id_detail_peminjaman]);
CREATE INDEX [idx_detailkembali_pengembalian] ON [dbo].[detail_pengembalian] ([id_pengembalian]);
GO

ALTER TABLE [dbo].[kategori_buku] ADD CONSTRAINT [PK_kategori_buku] PRIMARY KEY CLUSTERED ([id_kategori]);
CREATE UNIQUE INDEX [uq_kategori_nama] ON [dbo].[kategori_buku] ([nama_kategori]);
GO

ALTER TABLE [dbo].[peminjaman] ADD CONSTRAINT [PK_peminjaman] PRIMARY KEY CLUSTERED ([id_peminjaman]);
CREATE UNIQUE INDEX [uq_peminjaman_no] ON [dbo].[peminjaman] ([no_transaksi]);
CREATE INDEX [fk_peminjaman_petugas]      ON [dbo].[peminjaman] ([id_petugas]);
CREATE INDEX [idx_peminjaman_anggota]     ON [dbo].[peminjaman] ([id_anggota], [tanggal_pinjam]);
CREATE INDEX [idx_peminjaman_cabang_tgl]  ON [dbo].[peminjaman] ([id_cabang], [tanggal_pinjam]);
CREATE INDEX [idx_peminjaman_status_tempo] ON [dbo].[peminjaman] ([status], [tanggal_jatuh_tempo]);
GO

ALTER TABLE [dbo].[pengajuan_peminjaman] ADD CONSTRAINT [PK_pengajuan_peminjaman] PRIMARY KEY CLUSTERED ([id_pengajuan]);
CREATE UNIQUE INDEX [uq_pengajuan_no] ON [dbo].[pengajuan_peminjaman] ([no_pengajuan]);
CREATE INDEX [idx_pengajuan_anggota_status] ON [dbo].[pengajuan_peminjaman] ([id_anggota], [status]);
CREATE INDEX [idx_pengajuan_cabang_status]  ON [dbo].[pengajuan_peminjaman] ([id_cabang], [status]);
CREATE INDEX [idx_pengajuan_petugas]        ON [dbo].[pengajuan_peminjaman] ([id_petugas]);
GO

ALTER TABLE [dbo].[pengaturan] ADD CONSTRAINT [PK_pengaturan] PRIMARY KEY CLUSTERED ([nama_pengaturan]);
GO

ALTER TABLE [dbo].[pengembalian] ADD CONSTRAINT [PK_pengembalian] PRIMARY KEY CLUSTERED ([id_pengembalian]);
CREATE UNIQUE INDEX [uq_pengembalian_no] ON [dbo].[pengembalian] ([no_pengembalian]);
CREATE INDEX [fk_pengembalian_petugas]     ON [dbo].[pengembalian] ([id_petugas]);
CREATE INDEX [idx_pengembalian_peminjaman] ON [dbo].[pengembalian] ([id_peminjaman]);
CREATE INDEX [idx_pengembalian_tgl]        ON [dbo].[pengembalian] ([tanggal_kembali]);
GO

ALTER TABLE [dbo].[petugas] ADD CONSTRAINT [PK_petugas] PRIMARY KEY CLUSTERED ([id_petugas]);
CREATE INDEX [fk_petugas_cabang] ON [dbo].[petugas] ([id_cabang]);
GO

ALTER TABLE [dbo].[stok_buku_cabang] ADD CONSTRAINT [PK_stok_buku_cabang] PRIMARY KEY CLUSTERED ([id_cabang], [id_buku]);
CREATE INDEX [fk_stok_buku] ON [dbo].[stok_buku_cabang] ([id_buku]);
GO

/* =====================================================================
   4. FOREIGN KEY
   ON UPDATE CASCADE dari MariaDB TIDAK dibawa: SQL Server menolaknya
   (error 1785 "multiple cascade paths") dan tidak ada gunanya karena
   semua kunci induk berupa IDENTITY yang tidak bisa di-UPDATE.
   ON DELETE CASCADE pada detail_pengajuan_peminjaman dipertahankan.
   ===================================================================== */

ALTER TABLE [dbo].[akun_pengguna] ADD CONSTRAINT [fk_akun_anggota] FOREIGN KEY ([id_anggota]) REFERENCES [dbo].[anggota] ([id_anggota]);
ALTER TABLE [dbo].[akun_pengguna] ADD CONSTRAINT [fk_akun_petugas] FOREIGN KEY ([id_petugas]) REFERENCES [dbo].[petugas] ([id_petugas]);
ALTER TABLE [dbo].[anggota] ADD CONSTRAINT [fk_anggota_cabang] FOREIGN KEY ([id_cabang_daftar]) REFERENCES [dbo].[cabang] ([id_cabang]);
ALTER TABLE [dbo].[buku] ADD CONSTRAINT [fk_buku_kategori] FOREIGN KEY ([id_kategori]) REFERENCES [dbo].[kategori_buku] ([id_kategori]);
ALTER TABLE [dbo].[denda] ADD CONSTRAINT [fk_denda_detailkembali] FOREIGN KEY ([id_detail_pengembalian]) REFERENCES [dbo].[detail_pengembalian] ([id_detail_pengembalian]);
ALTER TABLE [dbo].[detail_peminjaman] ADD CONSTRAINT [fk_detailpinjam_buku] FOREIGN KEY ([id_buku]) REFERENCES [dbo].[buku] ([id_buku]);
ALTER TABLE [dbo].[detail_peminjaman] ADD CONSTRAINT [fk_detailpinjam_peminjaman] FOREIGN KEY ([id_peminjaman]) REFERENCES [dbo].[peminjaman] ([id_peminjaman]);
ALTER TABLE [dbo].[detail_pengajuan_peminjaman] ADD CONSTRAINT [fk_detail_pengajuan_buku] FOREIGN KEY ([id_buku]) REFERENCES [dbo].[buku] ([id_buku]);
ALTER TABLE [dbo].[detail_pengajuan_peminjaman] ADD CONSTRAINT [fk_detail_pengajuan_header] FOREIGN KEY ([id_pengajuan]) REFERENCES [dbo].[pengajuan_peminjaman] ([id_pengajuan]) ON DELETE CASCADE;
ALTER TABLE [dbo].[detail_pengembalian] ADD CONSTRAINT [fk_detailkembali_detailpinjam] FOREIGN KEY ([id_detail_peminjaman]) REFERENCES [dbo].[detail_peminjaman] ([id_detail_peminjaman]);
ALTER TABLE [dbo].[detail_pengembalian] ADD CONSTRAINT [fk_detailkembali_pengembalian] FOREIGN KEY ([id_pengembalian]) REFERENCES [dbo].[pengembalian] ([id_pengembalian]);
ALTER TABLE [dbo].[peminjaman] ADD CONSTRAINT [fk_peminjaman_anggota] FOREIGN KEY ([id_anggota]) REFERENCES [dbo].[anggota] ([id_anggota]);
ALTER TABLE [dbo].[peminjaman] ADD CONSTRAINT [fk_peminjaman_cabang] FOREIGN KEY ([id_cabang]) REFERENCES [dbo].[cabang] ([id_cabang]);
ALTER TABLE [dbo].[peminjaman] ADD CONSTRAINT [fk_peminjaman_petugas] FOREIGN KEY ([id_petugas]) REFERENCES [dbo].[petugas] ([id_petugas]);
ALTER TABLE [dbo].[pengajuan_peminjaman] ADD CONSTRAINT [fk_pengajuan_anggota] FOREIGN KEY ([id_anggota]) REFERENCES [dbo].[anggota] ([id_anggota]);
ALTER TABLE [dbo].[pengajuan_peminjaman] ADD CONSTRAINT [fk_pengajuan_cabang] FOREIGN KEY ([id_cabang]) REFERENCES [dbo].[cabang] ([id_cabang]);
ALTER TABLE [dbo].[pengajuan_peminjaman] ADD CONSTRAINT [fk_pengajuan_petugas] FOREIGN KEY ([id_petugas]) REFERENCES [dbo].[petugas] ([id_petugas]);
ALTER TABLE [dbo].[pengembalian] ADD CONSTRAINT [fk_pengembalian_peminjaman] FOREIGN KEY ([id_peminjaman]) REFERENCES [dbo].[peminjaman] ([id_peminjaman]);
ALTER TABLE [dbo].[pengembalian] ADD CONSTRAINT [fk_pengembalian_petugas] FOREIGN KEY ([id_petugas]) REFERENCES [dbo].[petugas] ([id_petugas]);
ALTER TABLE [dbo].[petugas] ADD CONSTRAINT [fk_petugas_cabang] FOREIGN KEY ([id_cabang]) REFERENCES [dbo].[cabang] ([id_cabang]);
ALTER TABLE [dbo].[stok_buku_cabang] ADD CONSTRAINT [fk_stok_buku] FOREIGN KEY ([id_buku]) REFERENCES [dbo].[buku] ([id_buku]);
ALTER TABLE [dbo].[stok_buku_cabang] ADD CONSTRAINT [fk_stok_cabang] FOREIGN KEY ([id_cabang]) REFERENCES [dbo].[cabang] ([id_cabang]);
GO

/* =====================================================================
   5. VIEW (urutan: v_riwayat_peminjaman dulu karena dipakai v_buku_terlambat)
   ===================================================================== */

CREATE VIEW [dbo].[v_riwayat_peminjaman] AS
SELECT
  dm.[id_detail_peminjaman] AS [id_detail_peminjaman],
  p.[id_peminjaman]         AS [id_peminjaman],
  p.[no_transaksi]          AS [no_transaksi],
  c.[id_cabang]             AS [id_cabang],
  c.[nama_cabang]           AS [nama_cabang],
  a.[id_anggota]            AS [id_anggota],
  a.[nomor_anggota]         AS [nomor_anggota],
  a.[nama_anggota]          AS [nama_anggota],
  b.[id_buku]               AS [id_buku],
  b.[judul]                 AS [judul],
  p.[tanggal_pinjam]        AS [tanggal_pinjam],
  p.[tanggal_jatuh_tempo]   AS [tanggal_jatuh_tempo],
  pg.[tanggal_kembali]      AS [tanggal_kembali],
  dk.[kondisi_buku]         AS [kondisi_buku],
  CASE WHEN dk.[id_detail_pengembalian] IS NULL THEN 'DIPINJAM' ELSE 'DIKEMBALIKAN' END AS [status_buku],
  CASE
    WHEN dk.[id_detail_pengembalian] IS NULL THEN
      CASE WHEN DATEDIFF(DAY, p.[tanggal_jatuh_tempo], CONVERT(date, GETDATE())) > 0
           THEN DATEDIFF(DAY, p.[tanggal_jatuh_tempo], CONVERT(date, GETDATE())) ELSE 0 END
    ELSE
      CASE WHEN DATEDIFF(DAY, p.[tanggal_jatuh_tempo], pg.[tanggal_kembali]) > 0
           THEN DATEDIFF(DAY, p.[tanggal_jatuh_tempo], pg.[tanggal_kembali]) ELSE 0 END
  END AS [hari_terlambat],
  COALESCE((SELECT SUM(dn.[jumlah_denda]) FROM [dbo].[denda] AS dn
            WHERE dn.[id_detail_pengembalian] = dk.[id_detail_pengembalian]), 0) AS [total_denda]
FROM [dbo].[detail_peminjaman] AS dm
JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = dm.[id_peminjaman]
JOIN [dbo].[cabang]     AS c ON c.[id_cabang]     = p.[id_cabang]
JOIN [dbo].[anggota]    AS a ON a.[id_anggota]    = p.[id_anggota]
JOIN [dbo].[buku]       AS b ON b.[id_buku]       = dm.[id_buku]
LEFT JOIN [dbo].[detail_pengembalian] AS dk ON dk.[id_detail_peminjaman] = dm.[id_detail_peminjaman]
LEFT JOIN [dbo].[pengembalian]        AS pg ON pg.[id_pengembalian]      = dk.[id_pengembalian];
GO

CREATE VIEW [dbo].[v_buku_terlambat] AS
SELECT
  r.[no_transaksi], r.[nama_cabang], r.[nomor_anggota], r.[nama_anggota], r.[judul],
  r.[tanggal_pinjam], r.[tanggal_jatuh_tempo], r.[hari_terlambat],
  r.[hari_terlambat] * (SELECT CAST(pt.[nilai] AS DECIMAL(12,2))
                        FROM [dbo].[pengaturan] AS pt
                        WHERE pt.[nama_pengaturan] = 'denda_per_hari') AS [estimasi_denda]
FROM [dbo].[v_riwayat_peminjaman] AS r
WHERE r.[status_buku] = 'DIPINJAM' AND r.[hari_terlambat] > 0;
GO

CREATE VIEW [dbo].[v_ringkasan_denda_anggota] AS
SELECT
  a.[id_anggota], a.[nomor_anggota], a.[nama_anggota],
  COALESCE(SUM(dn.[jumlah_denda]), 0) AS [total_denda],
  COALESCE(SUM(CASE WHEN dn.[status_bayar] = 'LUNAS'       THEN dn.[jumlah_denda] END), 0) AS [denda_lunas],
  COALESCE(SUM(CASE WHEN dn.[status_bayar] = 'BELUM_LUNAS' THEN dn.[jumlah_denda] END), 0) AS [denda_belum_lunas]
FROM [dbo].[anggota] AS a
LEFT JOIN [dbo].[peminjaman]          AS p  ON p.[id_anggota] = a.[id_anggota]
LEFT JOIN [dbo].[detail_peminjaman]   AS dm ON dm.[id_peminjaman] = p.[id_peminjaman]
LEFT JOIN [dbo].[detail_pengembalian] AS dk ON dk.[id_detail_peminjaman] = dm.[id_detail_peminjaman]
LEFT JOIN [dbo].[denda]               AS dn ON dn.[id_detail_pengembalian] = dk.[id_detail_pengembalian]
GROUP BY a.[id_anggota], a.[nomor_anggota], a.[nama_anggota];
GO

CREATE VIEW [dbo].[v_stok_cabang] AS
SELECT
  c.[id_cabang], c.[kode_cabang], c.[nama_cabang],
  b.[id_buku], b.[isbn], b.[judul], b.[penulis],
  k.[nama_kategori],
  s.[jumlah_total], s.[jumlah_tersedia],
  s.[jumlah_total] - s.[jumlah_tersedia] AS [jumlah_dipinjam],
  s.[lokasi_rak]
FROM [dbo].[stok_buku_cabang] AS s
JOIN [dbo].[cabang]        AS c ON c.[id_cabang]   = s.[id_cabang]
JOIN [dbo].[buku]          AS b ON b.[id_buku]     = s.[id_buku]
JOIN [dbo].[kategori_buku] AS k ON k.[id_kategori] = b.[id_kategori];
GO

/* =====================================================================
   6. TRIGGER
   SQL Server tidak punya BEFORE trigger. Trigger BEFORE MariaDB dikonversi
   menjadi AFTER trigger yang memvalidasi lalu ROLLBACK + THROW (efeknya sama:
   perubahan dibatalkan). Semua logika set-based (aman untuk multi-row).
   Nama trigger asli dipertahankan (suffix _bi/_bu/_bd tetap, walau tipe AFTER).
   ===================================================================== */

-- ---------- denda ----------
CREATE TRIGGER [dbo].[trg_denda_bu] ON [dbo].[denda] AFTER UPDATE
AS
BEGIN
  SET NOCOUNT ON;
  IF EXISTS (
    SELECT 1
    FROM inserted AS i
    JOIN deleted  AS d ON d.[id_denda] = i.[id_denda]
    WHERE i.[jumlah_denda] <> d.[jumlah_denda]
       OR i.[id_detail_pengembalian] <> d.[id_detail_pengembalian]
       OR i.[jenis_denda] <> d.[jenis_denda])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Ditolak: nominal dan jenis denda tidak boleh diubah.', 1;
  END
END;
GO

-- ---------- detail_peminjaman ----------
CREATE TRIGGER [dbo].[trg_detail_peminjaman_bi] ON [dbo].[detail_peminjaman] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  IF NOT EXISTS (SELECT 1 FROM inserted) RETURN;

  -- transaksi peminjaman harus masih berstatus DIPINJAM
  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
             WHERE p.[status] <> 'DIPINJAM')
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: transaksi peminjaman sudah selesai.', 1;
  END

  -- transaksi belum boleh sudah mulai dikembalikan
  IF EXISTS (SELECT 1 FROM inserted AS i
             WHERE EXISTS (SELECT 1 FROM [dbo].[pengembalian] AS pg WHERE pg.[id_peminjaman] = i.[id_peminjaman]))
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: transaksi ini sudah mulai dikembalikan, buat transaksi baru.', 1;
  END

  -- permintaan per (cabang, buku) dalam statement ini
  DECLARE @req TABLE ([id_cabang] INT NOT NULL, [id_buku] INT NOT NULL, [jml] INT NOT NULL);
  INSERT INTO @req ([id_cabang], [id_buku], [jml])
  SELECT p.[id_cabang], i.[id_buku], COUNT(*)
  FROM inserted AS i
  JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
  GROUP BY p.[id_cabang], i.[id_buku];

  -- buku harus tercatat di stok cabang
  IF EXISTS (SELECT 1 FROM @req AS x
             LEFT JOIN [dbo].[stok_buku_cabang] AS s ON s.[id_cabang] = x.[id_cabang] AND s.[id_buku] = x.[id_buku]
             WHERE s.[id_buku] IS NULL)
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: buku ini tidak tercatat di stok cabang tersebut.', 1;
  END

  -- stok tersedia (dibaca sebelum trg_detail_peminjaman_ai menguranginya) harus cukup
  IF EXISTS (SELECT 1 FROM @req AS x
             JOIN [dbo].[stok_buku_cabang] AS s ON s.[id_cabang] = x.[id_cabang] AND s.[id_buku] = x.[id_buku]
             WHERE s.[jumlah_tersedia] < x.[jml])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: stok buku di cabang ini sedang habis.', 1;
  END

  -- batas jumlah buku aktif per anggota (baris baru sudah ikut terhitung)
  DECLARE @maks INT = (SELECT CAST([nilai] AS INT) FROM [dbo].[pengaturan] WHERE [nama_pengaturan] = 'maks_buku_per_anggota');
  IF EXISTS (
    SELECT 1
    FROM [dbo].[detail_peminjaman] AS dm
    JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = dm.[id_peminjaman]
    LEFT JOIN [dbo].[detail_pengembalian] AS dk ON dk.[id_detail_peminjaman] = dm.[id_detail_peminjaman]
    WHERE dk.[id_detail_pengembalian] IS NULL
      AND p.[id_anggota] IN (SELECT p2.[id_anggota]
                             FROM inserted AS i
                             JOIN [dbo].[peminjaman] AS p2 ON p2.[id_peminjaman] = i.[id_peminjaman])
    GROUP BY p.[id_anggota]
    HAVING COUNT(*) > @maks)
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: melebihi batas jumlah buku yang boleh dipinjam anggota.', 1;
  END
END;
GO

CREATE TRIGGER [dbo].[trg_detail_peminjaman_ai] ON [dbo].[detail_peminjaman] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  UPDATE s
     SET s.[jumlah_tersedia] = s.[jumlah_tersedia] - x.[jml]
  FROM [dbo].[stok_buku_cabang] AS s
  JOIN (SELECT p.[id_cabang], i.[id_buku], COUNT(*) AS [jml]
        FROM inserted AS i
        JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
        GROUP BY p.[id_cabang], i.[id_buku]) AS x
    ON x.[id_cabang] = s.[id_cabang] AND x.[id_buku] = s.[id_buku];
END;
GO

CREATE TRIGGER [dbo].[trg_detail_peminjaman_bd] ON [dbo].[detail_peminjaman] AFTER DELETE
AS
BEGIN
  SET NOCOUNT ON;
  IF EXISTS (SELECT 1 FROM deleted)
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Ditolak: detail peminjaman tidak boleh dihapus.', 1;
  END
END;
GO

CREATE TRIGGER [dbo].[trg_detail_peminjaman_bu] ON [dbo].[detail_peminjaman] AFTER UPDATE
AS
BEGIN
  SET NOCOUNT ON;
  IF EXISTS (
    SELECT 1
    FROM inserted AS i
    JOIN deleted  AS d ON d.[id_detail_peminjaman] = i.[id_detail_peminjaman]
    WHERE i.[id_peminjaman] <> d.[id_peminjaman] OR i.[id_buku] <> d.[id_buku])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Ditolak: detail peminjaman tidak boleh diubah.', 1;
  END
END;
GO

-- validasi harus berjalan sebelum pengurangan stok
EXEC sp_settriggerorder @triggername = N'dbo.trg_detail_peminjaman_bi', @order = N'First', @stmttype = N'INSERT';
EXEC sp_settriggerorder @triggername = N'dbo.trg_detail_peminjaman_ai', @order = N'Last',  @stmttype = N'INSERT';
GO

-- ---------- detail_pengembalian ----------
CREATE TRIGGER [dbo].[trg_detail_pengembalian_bi] ON [dbo].[detail_pengembalian] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  IF NOT EXISTS (SELECT 1 FROM inserted) RETURN;

  -- item harus bagian dari transaksi peminjaman yang dikembalikan
  IF EXISTS (
    SELECT 1
    FROM inserted AS i
    JOIN [dbo].[pengembalian]      AS pg ON pg.[id_pengembalian]      = i.[id_pengembalian]
    JOIN [dbo].[detail_peminjaman] AS dm ON dm.[id_detail_peminjaman] = i.[id_detail_peminjaman]
    WHERE dm.[id_peminjaman] <> pg.[id_peminjaman])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: buku ini bukan bagian dari transaksi peminjaman yang dikembalikan.', 1;
  END

  -- item tidak boleh dikembalikan dua kali
  -- (baris baru sudah ada di tabel, jadi cari yang jumlahnya > 1)
  IF EXISTS (
    SELECT 1
    FROM [dbo].[detail_pengembalian] AS dk
    WHERE dk.[id_detail_peminjaman] IN (SELECT [id_detail_peminjaman] FROM inserted)
    GROUP BY dk.[id_detail_peminjaman]
    HAVING COUNT(*) > 1)
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Gagal: buku ini sudah pernah dikembalikan.', 1;
  END
END;
GO

CREATE TRIGGER [dbo].[trg_detail_pengembalian_ai] ON [dbo].[detail_pengembalian] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  IF NOT EXISTS (SELECT 1 FROM inserted) RETURN;

  -- (a) Stok: HILANG mengurangi jumlah_total, selain itu kembali ke rak
  UPDATE s
     SET s.[jumlah_total]    = s.[jumlah_total]    - x.[jml_hilang],
         s.[jumlah_tersedia] = s.[jumlah_tersedia] + x.[jml_kembali]
  FROM [dbo].[stok_buku_cabang] AS s
  JOIN (SELECT p.[id_cabang], dm.[id_buku],
               SUM(CASE WHEN i.[kondisi_buku] =  'HILANG' THEN 1 ELSE 0 END) AS [jml_hilang],
               SUM(CASE WHEN i.[kondisi_buku] <> 'HILANG' THEN 1 ELSE 0 END) AS [jml_kembali]
        FROM inserted AS i
        JOIN [dbo].[detail_peminjaman] AS dm ON dm.[id_detail_peminjaman] = i.[id_detail_peminjaman]
        JOIN [dbo].[peminjaman]        AS p  ON p.[id_peminjaman]         = dm.[id_peminjaman]
        GROUP BY p.[id_cabang], dm.[id_buku]) AS x
    ON x.[id_cabang] = s.[id_cabang] AND x.[id_buku] = s.[id_buku];

  -- (b) Denda keterlambatan
  DECLARE @tarif DECIMAL(12,2) =
    (SELECT CAST([nilai] AS DECIMAL(12,2)) FROM [dbo].[pengaturan] WHERE [nama_pengaturan] = 'denda_per_hari');

  INSERT INTO [dbo].[denda] ([id_detail_pengembalian], [jenis_denda], [jumlah_hari_terlambat], [jumlah_denda])
  SELECT i.[id_detail_pengembalian], 'TERLAMBAT', h.[hari], h.[hari] * @tarif
  FROM inserted AS i
  JOIN [dbo].[detail_peminjaman] AS dm ON dm.[id_detail_peminjaman] = i.[id_detail_peminjaman]
  JOIN [dbo].[peminjaman]        AS p  ON p.[id_peminjaman]         = dm.[id_peminjaman]
  JOIN [dbo].[pengembalian]      AS pg ON pg.[id_pengembalian]      = i.[id_pengembalian]
  CROSS APPLY (SELECT DATEDIFF(DAY, p.[tanggal_jatuh_tempo], pg.[tanggal_kembali]) AS [hari]) AS h
  WHERE h.[hari] > 0;

  -- (c) Denda buku rusak / hilang
  DECLARE @persen_rusak  DECIMAL(5,2) =
    (SELECT CAST([nilai] AS DECIMAL(5,2)) FROM [dbo].[pengaturan] WHERE [nama_pengaturan] = 'persen_denda_rusak');
  DECLARE @persen_hilang DECIMAL(5,2) =
    (SELECT CAST([nilai] AS DECIMAL(5,2)) FROM [dbo].[pengaturan] WHERE [nama_pengaturan] = 'persen_denda_hilang');

  INSERT INTO [dbo].[denda] ([id_detail_pengembalian], [jenis_denda], [jumlah_denda])
  SELECT i.[id_detail_pengembalian], i.[kondisi_buku],
         b.[harga] * (CASE i.[kondisi_buku] WHEN 'RUSAK' THEN @persen_rusak ELSE @persen_hilang END) / 100
  FROM inserted AS i
  JOIN [dbo].[detail_peminjaman] AS dm ON dm.[id_detail_peminjaman] = i.[id_detail_peminjaman]
  JOIN [dbo].[buku]              AS b  ON b.[id_buku] = dm.[id_buku]
  WHERE i.[kondisi_buku] IN ('RUSAK','HILANG');

  -- (d) Jika semua buku dalam transaksi sudah kembali -> status SELESAI
  UPDATE p
     SET p.[status] = 'SELESAI'
  FROM [dbo].[peminjaman] AS p
  WHERE p.[id_peminjaman] IN (SELECT dm.[id_peminjaman]
                              FROM inserted AS i
                              JOIN [dbo].[detail_peminjaman] AS dm ON dm.[id_detail_peminjaman] = i.[id_detail_peminjaman])
    AND NOT EXISTS (SELECT 1
                    FROM [dbo].[detail_peminjaman] AS dm2
                    LEFT JOIN [dbo].[detail_pengembalian] AS dk ON dk.[id_detail_peminjaman] = dm2.[id_detail_peminjaman]
                    WHERE dm2.[id_peminjaman] = p.[id_peminjaman] AND dk.[id_detail_pengembalian] IS NULL);
END;
GO

CREATE TRIGGER [dbo].[trg_detail_pengembalian_bd] ON [dbo].[detail_pengembalian] AFTER DELETE
AS
BEGIN
  SET NOCOUNT ON;
  IF EXISTS (SELECT 1 FROM deleted)
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Ditolak: detail pengembalian tidak boleh dihapus.', 1;
  END
END;
GO

EXEC sp_settriggerorder @triggername = N'dbo.trg_detail_pengembalian_bi', @order = N'First', @stmttype = N'INSERT';
EXEC sp_settriggerorder @triggername = N'dbo.trg_detail_pengembalian_ai', @order = N'Last',  @stmttype = N'INSERT';
GO

-- ---------- peminjaman ----------
CREATE TRIGGER [dbo].[trg_peminjaman_bi] ON [dbo].[peminjaman] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  IF NOT EXISTS (SELECT 1 FROM inserted) RETURN;

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[anggota] AS a ON a.[id_anggota] = i.[id_anggota]
             WHERE a.[status] <> 'AKTIF')
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Peminjaman ditolak: anggota tidak berstatus AKTIF.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[petugas] AS t ON t.[id_petugas] = i.[id_petugas]
             WHERE t.[status] <> 'AKTIF')
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Peminjaman ditolak: petugas tidak berstatus AKTIF.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[petugas] AS t ON t.[id_petugas] = i.[id_petugas]
             WHERE t.[id_cabang] <> i.[id_cabang])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Peminjaman ditolak: petugas bukan petugas cabang ini.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i WHERE i.[tanggal_pinjam] > CONVERT(date, GETDATE()))
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Peminjaman ditolak: tanggal pinjam tidak boleh di masa depan.', 1;
  END

  -- anggota yang masih punya denda belum lunas tidak boleh meminjam
  IF EXISTS (
    SELECT 1
    FROM inserted AS i
    WHERE EXISTS (
      SELECT 1
      FROM [dbo].[denda] AS dn
      JOIN [dbo].[detail_pengembalian] AS dk ON dk.[id_detail_pengembalian] = dn.[id_detail_pengembalian]
      JOIN [dbo].[detail_peminjaman]   AS dm ON dm.[id_detail_peminjaman]   = dk.[id_detail_peminjaman]
      JOIN [dbo].[peminjaman]          AS p  ON p.[id_peminjaman]           = dm.[id_peminjaman]
      WHERE p.[id_anggota] = i.[id_anggota] AND dn.[status_bayar] = 'BELUM_LUNAS'))
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Peminjaman ditolak: anggota masih punya denda belum lunas.', 1;
  END

  -- transaksi baru selalu berstatus DIPINJAM (pengganti SET NEW.status)
  UPDATE p
     SET p.[status] = 'DIPINJAM'
  FROM [dbo].[peminjaman] AS p
  JOIN inserted AS i ON i.[id_peminjaman] = p.[id_peminjaman]
  WHERE p.[status] <> 'DIPINJAM';
END;
GO

-- ---------- pengembalian ----------
CREATE TRIGGER [dbo].[trg_pengembalian_bi] ON [dbo].[pengembalian] AFTER INSERT
AS
BEGIN
  SET NOCOUNT ON;
  IF NOT EXISTS (SELECT 1 FROM inserted) RETURN;

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
             WHERE p.[status] <> 'DIPINJAM')
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Pengembalian ditolak: transaksi peminjaman sudah selesai.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[petugas] AS t ON t.[id_petugas] = i.[id_petugas]
             WHERE t.[status] <> 'AKTIF')
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Pengembalian ditolak: petugas tidak berstatus AKTIF.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
             JOIN [dbo].[petugas]    AS t ON t.[id_petugas]    = i.[id_petugas]
             WHERE t.[id_cabang] <> p.[id_cabang])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Pengembalian ditolak: buku harus dikembalikan di cabang tempat meminjam.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i
             JOIN [dbo].[peminjaman] AS p ON p.[id_peminjaman] = i.[id_peminjaman]
             WHERE i.[tanggal_kembali] < p.[tanggal_pinjam])
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Pengembalian ditolak: tanggal kembali lebih awal dari tanggal pinjam.', 1;
  END

  IF EXISTS (SELECT 1 FROM inserted AS i WHERE i.[tanggal_kembali] > CONVERT(date, GETDATE()))
  BEGIN
    IF @@TRANCOUNT > 0 ROLLBACK TRANSACTION;
    THROW 50001, N'Pengembalian ditolak: tanggal kembali tidak boleh di masa depan.', 1;
  END
END;
GO
