-- Migrasi Tahap 2: role ANGGOTA dan pengajuan peminjaman
-- Jalankan satu kali melalui phpMyAdmin menggunakan akun root.
-- Database target: db_perpustakaan

USE db_perpustakaan;

ALTER TABLE akun_pengguna
    MODIFY role ENUM('ADMIN', 'PETUGAS', 'ANGGOTA') NOT NULL DEFAULT 'PETUGAS',
    ADD COLUMN id_anggota INT UNSIGNED NULL AFTER id_petugas,
    ADD UNIQUE KEY uq_akun_anggota (id_anggota),
    ADD CONSTRAINT fk_akun_anggota
        FOREIGN KEY (id_anggota) REFERENCES anggota (id_anggota)
        ON UPDATE CASCADE;

-- Jika database awal memiliki CHECK ck_akun_role_petugas, ganti agar
-- akun ANGGOTA boleh menggunakan id_anggota tanpa id_petugas.
ALTER TABLE akun_pengguna
    DROP CONSTRAINT IF EXISTS ck_akun_role_petugas,
    ADD CONSTRAINT ck_akun_role_relasi CHECK (
        (role = 'ADMIN')
        OR (role = 'PETUGAS' AND id_petugas IS NOT NULL AND id_anggota IS NULL)
        OR (role = 'ANGGOTA' AND id_petugas IS NULL AND id_anggota IS NOT NULL)
    );

CREATE TABLE pengajuan_peminjaman (
    id_pengajuan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    no_pengajuan VARCHAR(25) NOT NULL,
    id_anggota INT UNSIGNED NOT NULL,
    id_cabang INT UNSIGNED NOT NULL,
    tanggal_pengajuan DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('MENUNGGU', 'DISETUJUI', 'DITOLAK', 'DIBATALKAN') NOT NULL DEFAULT 'MENUNGGU',
    id_petugas INT UNSIGNED NULL,
    tanggal_diproses DATETIME NULL,
    catatan_anggota VARCHAR(255) NULL,
    catatan_petugas VARCHAR(255) NULL,
    PRIMARY KEY (id_pengajuan),
    UNIQUE KEY uq_pengajuan_no (no_pengajuan),
    KEY idx_pengajuan_anggota_status (id_anggota, status),
    KEY idx_pengajuan_cabang_status (id_cabang, status),
    KEY idx_pengajuan_petugas (id_petugas),
    CONSTRAINT fk_pengajuan_anggota
        FOREIGN KEY (id_anggota) REFERENCES anggota (id_anggota)
        ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_cabang
        FOREIGN KEY (id_cabang) REFERENCES cabang (id_cabang)
        ON UPDATE CASCADE,
    CONSTRAINT fk_pengajuan_petugas
        FOREIGN KEY (id_petugas) REFERENCES petugas (id_petugas)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE detail_pengajuan_peminjaman (
    id_detail_pengajuan INT UNSIGNED NOT NULL AUTO_INCREMENT,
    id_pengajuan INT UNSIGNED NOT NULL,
    id_buku INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_detail_pengajuan),
    UNIQUE KEY uq_detail_pengajuan_buku (id_pengajuan, id_buku),
    KEY idx_detail_pengajuan_buku (id_buku),
    CONSTRAINT fk_detail_pengajuan_header
        FOREIGN KEY (id_pengajuan) REFERENCES pengajuan_peminjaman (id_pengajuan)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detail_pengajuan_buku
        FOREIGN KEY (id_buku) REFERENCES buku (id_buku)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Hak minimum untuk aplikasi setelah tabel dibuat.
GRANT SELECT, INSERT, UPDATE
    ON db_perpustakaan.pengajuan_peminjaman
    TO 'perpus_app'@'localhost';

GRANT SELECT, INSERT
    ON db_perpustakaan.detail_pengajuan_peminjaman
    TO 'perpus_app'@'localhost';

FLUSH PRIVILEGES;
