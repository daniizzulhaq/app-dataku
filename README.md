# Kendara — Administrasi & Cetak Data Kendaraan

Aplikasi PHP Native untuk alur **login → input → simpan MySQL → preview → print**. Dibuat langsung di folder `stnk`, tanpa framework backend atau proses build frontend.

Cetakan menampilkan nilai kendaraan tanpa judul tambahan, dengan watermark **CONTOH / TIDAK BERLAKU** dan footer permanen **DRAFT — BUKAN DOKUMEN RESMI**. Judul dan tulisan draft dekoratif di formulir pengisian dihilangkan; penanda draft pada dokumen hasil cetak tetap ada. Aplikasi ini untuk administrasi internal dan percobaan tata letak, tidak menghasilkan STNK resmi. Gambar QR dan tanda tangan/stempel berasal dari referensi pengguna di `assets/images/`; area tanda tangan juga memiliki penanda **CONTOH** yang ikut dicetak.

## Kebutuhan

- PHP 8.2+ dengan PDO MySQL, mbstring, session.
- MySQL 8+ atau MariaDB 10.4+ (bawaan XAMPP juga dapat dipakai).
- Apache XAMPP/Laragon, `AllowOverride All` untuk `.htaccess`.
- Browser desktop terbaru untuk cetak; Chrome/Edge dianjurkan.
- Bootstrap 5.3.8 dan Bootstrap Icons 1.13.2 sudah disertakan lokal beserta lisensinya. Aplikasi dan ikon berfungsi tanpa internet. Font Inter diambil dari CDN jsDelivr; saat offline digunakan font sistem. Dokumen cetak memakai Times New Roman lokal dengan ukuran awal 8 pt, termasuk pengesahan dan keterangan QR.

## Instalasi XAMPP

1. Letakkan folder proyek di `C:\xampp\htdocs\stnk` (folder aktif ini sudah benar).
2. Nyalakan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin`, pilih **Import**, lalu impor `database/kendaraan.sql`. File SQL otomatis membuat database **db_kendaraan_print**, tabel, dan dua profil cetak; tidak menghapus tabel/data yang sudah ada.
4. Konfigurasi awal di `config/database.php`: host `127.0.0.1`, port `3306`, database `db_kendaraan_print`, user `root`, password kosong. Jika berbeda, salin `config/database.local.example.php` menjadi `config/database.local.php` dan sesuaikan isinya. Jangan membagikan file konfigurasi lokal.
5. Buat token setup dari PowerShell:

   ```powershell
   cd C:\xampp\htdocs\stnk
   C:\xampp\php\php.exe setup.php
   ```

6. Buka `http://localhost/stnk/setup.php` **dari komputer server itu sendiri**. Tempel token terminal, isi nama, username, password unik minimal 12 karakter (maksimal 72 byte), dan konfirmasi password. Token berlaku 30 menit. Jika kedaluwarsa, ulangi perintah tadi.
7. Setelah admin pertama dibuat, setup otomatis terkunci dan token dihapus. Tidak ada username/password bawaan. Masuk melalui `http://localhost/stnk/login.php`.

SQL dapat diimpor melalui terminal juga:

```powershell
Get-Content database\kendaraan.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root --default-character-set=utf8mb4
```

Untuk database dengan password, tambahkan `-p` agar MySQL meminta password secara interaktif.

## Instalasi Laragon

1. Salin proyek ke `C:\laragon\www\kendaraan-print`.
2. Pilih PHP 8.2+ dan Apache, lalu **Start All**.
3. Impor file SQL menggunakan phpMyAdmin/HeidiSQL.
4. Atur koneksi bila perlu melalui `config/database.local.php`.
5. Dari terminal Laragon, jalankan `php setup.php`. Buka `http://localhost/kendaraan-print/setup.php` dari komputer lokal dan buat administrator.
6. Login di `http://localhost/kendaraan-print/login.php`.

Setup lokal memeriksa alamat koneksi, tidak mempercayai header proxy. Gunakan akses langsung localhost untuk setup. Untuk deployment internal melalui jaringan, selesaikan setup terlebih dahulu di server.

## Mencoba alur utama

1. Login, tekan **Tambah data**.
2. Isi langsung formulir horizontal dengan pola **Label : [kolom isian]**. Susunan pemilik di atas, spesifikasi di kiri, identitas tambahan di tengah, dan administrasi di kanan memudahkan pengisian mengikuti template. Pada HP, kolom disusun ke bawah. Isi kolom bertanda bintang. Nomor polisi harus unik; NIK harus 16 digit. VIN, nomor mesin, NIK, nomor polisi, BPKB, dan nomor urut disimpan sebagai string agar nol di depan tetap utuh. CC `0` diperbolehkan untuk kendaraan listrik. Label dan tanda titik dua pada formulir tidak ikut dicetak.
3. Klik **Simpan & Preview** untuk menyimpan lalu melihat dokumen. Tombol **Simpan** membuka detail kendaraan.
4. Gunakan **Edit data** untuk koreksi. Backend mencegah penyimpanan jika halaman lain sudah memperbarui data yang sama.
5. Pilih profil cetak dan klik **Terapkan**. Opsi garis potong muncul pada A4.
6. Tekan **Lanjut cetak**, lalu **Print / Cetak ulang**. Aplikasi memakai `window.print()` dan selalu mengambil ulang data dari MySQL pada pembukaan halaman cetak.
7. Pencarian nomor polisi dan nama pemilik dapat dipakai bersamaan di dashboard. Daftar menampilkan 10 baris per halaman. Tindakan hapus meminta konfirmasi dan hanya menerima POST dengan CSRF.

### Mengisi pengesahan dan melihat QR internal

Di form tambah/edit, area kanan atas menyediakan **Wilayah** dan **Provinsi** (masing-masing maksimal 60 karakter), **Tanggal wilayah**, **Nama / isi pengesahan** (180 karakter, mendukung beberapa baris), **Jabatan** (100 karakter), dan **NRP** (30 karakter; nol di depan dipertahankan). Semua kolom opsional. Wilayah dan provinsi sejajar atas–bawah; tanggal berada di kanan wilayah dengan font 75% (7,5 pt), misalnya `GRESIK, 08-januari-2026`. Tanggal kosong tidak menampilkan koma. Di bawahnya ada gambar tanda tangan/stempel, nama bergaris bawah, serta jabatan dan NRP. Gambar referensi ditampilkan melalui CSS tanpa mengubah berkas aslinya. Klik **Simpan & Preview** untuk melihat hasilnya; data juga tersedia di detail. Jika nama kosong, dokumen menampilkan `BELUM DIISI`.

Bagian kanan dokumen menampilkan **TELITI ULANG — CONTOH** dan gambar QR tetap dari `assets/images/qr-contoh.jpg`. Isi QR mengikuti gambar pengguna, bukan data kendaraan yang sedang dibuka. Keterangan tanggal dan nomor referensi di samping QR tidak dicetak. Generator QR internal lama tetap tersedia di `includes/qr.php`, tetapi tidak dipakai template ini. Pustaka BaconQrCode 2.0.8 dan DASPRiD Enum 1.0.3 disertakan dengan lisensi BSD-2-Clause di `includes/vendor/`.

Untuk memperbarui instalasi lama tanpa menghapus data, jalankan dari folder proyek:

```powershell
C:\xampp\php\php.exe database\migrate.php
```

Migrasi menambahkan kolom opsional `pengesahan_internal`, `jabatan_pemeriksa`, `wilayah`, `tanggal_wilayah`, `wilayah_pengesahan`, `nrp_pemeriksa`, dan `teks_atas_qr` dan aman dijalankan ulang. Data provinsi lama tetap memakai `wilayah_pengesahan`; kolom wilayah dan tanggal baru kosong sampai diisi. QR diturunkan 5 mm untuk memberi ruang baris tambahan. Instalasi baru sudah menyertakan semuanya di `database/kendaraan.sql`. File SQL utama tidak mengubah struktur tabel yang sudah ada; gunakan migrasi untuk pembaruan instalasi lama.

Kolom **Tulisan di atas QR** pada bagian Administrasi di formulir tambah/edit disimpan per kendaraan dan tampil pada preview serta hasil cetak. Nilai awalnya `TELITI ULANG`; kosongkan untuk menyembunyikan tulisan. Isian otomatis kapital dengan batas 60 karakter.

Contoh data fiktif: polisi `B 1234 UJI`, pemilik `Pemilik Fiktif`, NIK `0012345678901234`, rangka `000VIN-UJI-123456789`, mesin `000MESIN-UJI-98765`, Toyota / Avanza / mobil penumpang / minibus, tahun `2024`, CC `1496`, putih, bensin, TNKB putih, tanggal penerbitan `2026-10-01`, berlaku sampai `2031-10-01`. Lengkapi kolom wajib lainnya sesuai label. Contoh ini tidak dimasukkan otomatis ke database operasional.

Catatan admin disimpan dan ditampilkan di detail serta di bawah preview. Catatan tidak dicetak karena merupakan informasi kerja admin. Preview dan print menampilkan nilai data saja, tanpa judul tambahan, label kolom, titik dua, atau garis pemisah vertikal. Nomor polisi dan NIK sejajar di bagian atas, diikuti nama serta alamat; spesifikasi kendaraan ada di kiri dan tengah, administrasi di kanan. Tahun pembuatan/perakitan ditampilkan sekali jika sama, atau dipisahkan `/` jika berbeda. Nilai opsional kosong menyisakan tempat kosong. Watermark draft serta placeholder pengesahan internal tetap ditampilkan.

## Format cetak tetap

Format memakai **landscape 297 × 230 mm**, kembali ke setelan sebelum percobaan Letter. Isi STNK diputar **90° searah jarum jam** dengan tiga posisi: **kiri, tengah, kanan**. Pilihan awal kolom tengah; posisi diingat selama sesi. Satu kali cetak tetap satu STNK.

Template dasar 230 × 75 mm memakai bidang cetak sepanjang 210 mm. Seluruh isi dinaikkan total **10,5 mm pada arah baca STNK** untuk mengoreksi posisi setelah memakai ukuran kertas custom; karena rotasi 90 derajat, koreksi diterapkan pada sumbu X kertas tanpa mengubah koordinat antarblok. Font utama **Times New Roman 10 pt** dikompensasi terhadap skala bidang agar ukuran huruf tercetak tetap. Jarak antarbaris utama 5 mm pada template. Pengesahan, QR 20 mm, dan teks tebal di bawah QR memakai pengaturan tetap. Tanggal memakai posisi kanan yang diminta; geseran sementara ke kiri dibatalkan. Area cetak pada driver perlu sesuai ukuran halaman aplikasi agar ujung tahun tidak terpotong. Tanggal memakai nama bulan lengkap dengan huruf awal kapital, misalnya **04 November 2031**, dalam bidang selebar 42 mm. Koordinat masing-masing blok diatur terpisah dalam `assets/css/preview.css`; tanggal dibatasi agar tidak terpotong di tepi halaman.

Di preview, halaman cetak, atau **Posisi cetak**, pilih kolom lalu klik **Terapkan**. Pilihan profil, ukuran, orientasi, font, margin, dan kalibrasi sudah dihapus. Parameter lama pada URL atau POST tidak mengubah format. Data pengaturan lama di database dipertahankan tetapi tidak digunakan oleh format tetap.

Percobaan perubahan ke Letter dibatalkan. Ukuran halaman aplikasi kembali **297 × 230 mm**, tanpa mengubah font 10 pt atau koreksi isian sebelumnya.

Preview dan print memakai template yang sama. Penanda kolom hanya tampil di layar. Tanggal penerbitan di atas wilayah tidak ditampilkan. Alamat otomatis turun baris 2 mm sebelum tulisan jabatan pemeriksa, dengan ukuran huruf tetap 10 pt. Enter yang dimasukkan pada alamat juga dipertahankan. Ukuran huruf kolom lainnya dan jarak baris tetap. Bila isian masih melewati ruang tersedia, cetak diblokir dengan pesan untuk meringkas isian agar data tidak terpotong.

## Struktur

Struktur utama sesuai brief: `config/`, `includes/`, `templates/`, `assets/css/`, `assets/js/`, `database/`, endpoint CRUD serta halaman preview/print serta pilihan posisi. Tambahan:

- `setup.php`: token CLI dan setup admin pertama.
- `templates/pengesahan.php`: komponen pengesahan internal terpisah.
- `assets/vendor/`: Bootstrap dan ikon lokal dengan lisensi MIT.
- `storage/`: hash token setup sementara, diblokir melalui HTTP.
- `router.php`: melindungi direktori privat saat memakai server bawaan PHP.
- `tests/`: pengujian integrasi, browser, dan artefak hasil.

Server pengembangan opsional (MySQL tetap perlu aktif):

```powershell
C:\xampp\php\php.exe -S 127.0.0.1:8025 router.php
```

Buka `http://127.0.0.1:8025`. Selalu sertakan `router.php`; server PHP bawaan tidak membaca `.htaccess`. Untuk penggunaan normal, gunakan Apache XAMPP/Laragon. Jika memakai Nginx, buat aturan deny untuk direktori config, includes, templates, database, storage, tests, serta dotfiles sebelum menjalankan aplikasi.

## Keamanan dan operasional

- Prepared statements PDO dengan native prepares, validasi backend, escaping HTML, CSRF pada seluruh POST, password hashing, session ID baru saat login, cookie HttpOnly/SameSite, secure cookie otomatis saat HTTPS.
- Sesi berakhir setelah 30 menit tanpa aktivitas. Login dibatasi 5 percobaan per username dan 20 per IP per 15 menit. Pembatasan tersimpan pada tabel tambahan `login_attempts`, sehingga mengganti cookie tidak mereset limit.
- Semua halaman kendaraan, preview, print, dan kalibrasi memerlukan login. Respons data pribadi memakai `Cache-Control: no-store`, tanpa analitik atau API pihak ketiga.
- Pesan kegagalan konfigurasi/database bersifat umum; rincian masuk ke log PHP/Apache. Jangan menerbitkan log atau backup database ke folder publik.
- Setup hanya menerima koneksi loopback serta token CLI berumur 30 menit; otomatis terkunci setelah satu admin dibuat. Tidak ada endpoint tambah admin publik.
- Gunakan akun database khusus aplikasi dan HTTPS untuk penggunaan dalam jaringan. Cadangkan MySQL secara teratur ke lokasi di luar web root. Jangan gunakan root/password kosong pada server produksi.
- Konfigurasi juga dapat diberikan lewat `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD`. Nilai file lokal mengambil prioritas bila tersedia.
- Bila lupa password, administrator server perlu melakukan reset melalui proses administrasi database dengan hash dari `password_hash()`. Tidak disediakan reset password publik tanpa verifikasi.

## Pengujian ulang

Jalankan MySQL, pastikan Node.js 22+ dan Google Chrome tersedia, lalu:

```powershell
cd C:\xampp\htdocs\stnk
node tests\run.mjs
```

Runner membuat database **sementara** bernama `db_kendaraan_print_test_<acak>`, menjalankan server uji pada `127.0.0.1:8026`, dan Chrome headless pada port `9228`. Pengujian tidak mengisi database operasional. Database sementara, token setup uji, sesi browser, dan server uji dibersihkan setelah selesai. Akun MySQL pengujian harus boleh membuat/menghapus database sementara. Jika konfigurasi koneksi berbeda, berikan environment variable sesuai server; jalankan pengujian dengan konfigurasi lokal yang tidak mengalihkan nama database.

Pilihan executable:

```powershell
$env:PHP_BIN = 'C:\laragon\bin\php\php-8.2\php.exe'
$env:CHROME_BIN = 'C:\Program Files\Google\Chrome\Application\chrome.exe'
node tests\run.mjs
```

Mencakup setup, hash password, login, proteksi akses, validasi, CSRF, penyimpanan, nol di depan, escape XSS, pencarian, pagination, update/data terbaru, konflik edit, hapus POST, format tetap, tiga posisi kolom, proteksi parameter lama, ukuran PDF landscape, kesamaan preview/print, teks panjang, tampilan desktop/HP, logout, dan limit login lintas sesi. Screenshot, tiga PDF uji, dan ringkasan disimpan di `tests/artifacts/`, diblokir dari HTTP.

Cetakan printer fisik tetap perlu diuji menggunakan penggaris karena driver dan toleransi mekanis tidak dapat diverifikasi dari browser.

## Referensi teknis

- [Bootstrap 5 — distribusi resmi](https://getbootstrap.com/docs/5.3/getting-started/download/)
- [PHP — pengaturan keamanan session](https://www.php.net/manual/en/session.security.ini.php)
- [MDN — ukuran halaman CSS @page](https://developer.mozilla.org/en-US/docs/Web/CSS/Reference/At-rules/@page/size)
