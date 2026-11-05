# Dependensi QR lokal

- `bacon-qr-code/`: BaconQrCode 2.0.8, lisensi BSD-2-Clause.
- `enum/`: DASPRiD Enum 1.0.3, lisensi BSD-2-Clause.

File lisensi asli disertakan di setiap direktori. Autoloader minimal di `includes/qr.php` hanya memuat dua namespace ini. Aplikasi menggunakan encoder matriks dan membuat SVG sendiri, sehingga tidak membutuhkan Composer, Imagick, atau XMLWriter pada saat dijalankan.

Penyesuaian kompatibilitas PHP 8.4/8.5: parameter nullable dibuat eksplisit pada `Encoder::chooseMode()` dan `ReedSolomonCodec::decode()`. Tidak ada perubahan algoritme QR. QR memuat nomor data dan nomor referensi internal, tanpa nama pemilik, NIK, alamat, tanda tangan, atau kode autentikasi resmi.
