-- MySQL 8 / MariaDB 10.4+, PHP 8.2+. Tidak ada akun/password bawaan.
-- Database hosting sudah dibuat melalui cPanel.
USE datamast_data;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS kendaraan (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nomor_polisi VARCHAR(20) NOT NULL UNIQUE,
    nama_pemilik VARCHAR(100) NOT NULL,
    nik VARCHAR(16) NOT NULL,
    alamat VARCHAR(300) NOT NULL,
    merek VARCHAR(60) NOT NULL,
    tipe VARCHAR(80) NOT NULL,
    jenis VARCHAR(60) NOT NULL,
    model VARCHAR(60) NOT NULL,
    tahun_pembuatan SMALLINT UNSIGNED NOT NULL,
    tahun_perakitan SMALLINT UNSIGNED NULL,
    isi_silinder INT UNSIGNED NOT NULL,
    nomor_rangka VARCHAR(50) NOT NULL,
    nomor_mesin VARCHAR(50) NOT NULL,
    warna VARCHAR(40) NOT NULL,
    bahan_bakar VARCHAR(30) NOT NULL,
    warna_tnkb VARCHAR(30) NOT NULL,
    tahun_registrasi SMALLINT UNSIGNED NOT NULL,
    nomor_bpkb VARCHAR(50) NULL,
    nomor_urut VARCHAR(50) NULL,
    kode_lokasi VARCHAR(30) NULL,
    tanggal_penerbitan DATE NOT NULL,
    berlaku_sampai DATE NOT NULL,
    nomor_referensi VARCHAR(60) NULL,
    teks_atas_qr VARCHAR(60) NULL DEFAULT 'TELITI ULANG',
    pengesahan_internal VARCHAR(180) NULL,
    jabatan_pemeriksa VARCHAR(100) NULL,
    wilayah_pengesahan VARCHAR(60) NULL,
    nrp_pemeriksa VARCHAR(30) NULL,
    catatan TEXT NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    INDEX idx_pemilik (nama_pemilik),
    INDEX idx_created (created_at),
    INDEX idx_berlaku (berlaku_sampai)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS print_settings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paper_profile VARCHAR(20) NOT NULL UNIQUE,
    paper_width_mm DECIMAL(6,2) NOT NULL,
    paper_height_mm DECIMAL(6,2) NOT NULL,
    margin_mm DECIMAL(5,2) NOT NULL DEFAULT 0,
    offset_x_mm DECIMAL(6,2) NOT NULL DEFAULT 0,
    offset_y_mm DECIMAL(6,2) NOT NULL DEFAULT 0,
    font_size_pt DECIMAL(4,2) NOT NULL DEFAULT 6,
    line_height DECIMAL(4,2) NOT NULL DEFAULT 1.1,
    padding_mm DECIMAL(4,2) NOT NULL DEFAULT 4,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Rate limit disimpan di database, tetap berlaku saat cookie/sesi diganti.
CREATE TABLE IF NOT EXISTS login_attempts (
    rate_key CHAR(64) PRIMARY KEY,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    window_started DATETIME NOT NULL,
    INDEX idx_window (window_started)
) ENGINE=InnoDB;

INSERT INTO print_settings (paper_profile, paper_width_mm, paper_height_mm)
SELECT 'custom', 230, 75 WHERE NOT EXISTS (SELECT 1 FROM print_settings WHERE paper_profile = 'custom');
INSERT INTO print_settings (paper_profile, paper_width_mm, paper_height_mm)
SELECT 'a4', 297, 210 WHERE NOT EXISTS (SELECT 1 FROM print_settings WHERE paper_profile = 'a4');
