<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/csrf.php';

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function abort_request(int $status, string $message): never
{
    http_response_code($status);
    $pageTitle = 'Permintaan tidak dapat diproses';
    require APP_ROOT . '/includes/auth-header.php';
    echo '<div class="auth-card"><div class="eyebrow">KENDARA / ' . $status . '</div><h1>Permintaan tidak dapat diproses</h1><p>' . e($message) . '</p><a class="btn btn-primary" href="index.php">Kembali ke dashboard</a></div>';
    require APP_ROOT . '/includes/auth-footer.php';
    exit;
}

function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = compact('message', 'type');
}

function post_string(string $name, string $default = ''): string
{
    $value = $_POST[$name] ?? $default;
    return is_string($value) ? trim($value) : '';
}

function query_string(string $name, string $default = ''): string
{
    $value = $_GET[$name] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function positive_id(): int
{
    $value = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_string('id') : query_string('id');
    if (!ctype_digit($value) || (int) $value < 1) {
        abort_request(404, 'Data kendaraan tidak ditemukan.');
    }
    return (int) $value;
}

function find_vehicle(int $id): array
{
    $statement = db()->prepare('SELECT * FROM kendaraan WHERE id = ?');
    $statement->execute([$id]);
    $vehicle = $statement->fetch() ?: abort_request(404, 'Data kendaraan tidak ditemukan atau sudah dihapus.');
    return uppercase_vehicle($vehicle);
}

function vehicle_fields(): array
{
    return [
        'pemilik' => ['title' => 'Data pemilik', 'description' => 'Identitas kendaraan dan pemilik yang terdaftar.', 'icon' => 'person', 'fields' => [
            'nomor_polisi' => ['Nomor Polisi / NRKB', 'text', 20, true, 'B 1234 XYZ'],
            'nama_pemilik' => ['Nama Pemilik', 'text', 100, true, 'Nama lengkap pemilik'],
            'nik' => ['NIK', 'text', 16, true, '16 digit NIK'],
            'alamat' => ['Alamat Lengkap', 'textarea', 300, true, 'Jalan, kelurahan, kecamatan, kota, dan provinsi'],
        ]],
        'kendaraan' => ['title' => 'Data kendaraan', 'description' => 'Spesifikasi dan identitas fisik kendaraan.', 'icon' => 'car-front', 'fields' => [
            'merek' => ['Merek Kendaraan', 'text', 60, true, 'Contoh: Toyota'],
            'tipe' => ['Tipe Kendaraan', 'text', 80, true, 'Contoh: Avanza 1.5 G'],
            'jenis' => ['Jenis Kendaraan', 'text', 60, true, 'Contoh: Mobil penumpang'],
            'model' => ['Model Kendaraan', 'text', 60, true, 'Contoh: Minibus'],
            'tahun_pembuatan' => ['Tahun Pembuatan', 'year', 4, true, '2024'],
            'tahun_perakitan' => ['Tahun Perakitan', 'year', 4, false, '2024'],
            'isi_silinder' => ['Isi Silinder (CC)', 'number', 6, true, '1496'],
            'nomor_rangka' => ['Nomor Rangka / VIN', 'text', 50, true, 'Nomor rangka kendaraan'],
            'nomor_mesin' => ['Nomor Mesin', 'text', 50, true, 'Nomor mesin kendaraan'],
            'warna' => ['Warna Kendaraan', 'text', 40, true, 'Contoh: Putih'],
            'bahan_bakar' => ['Bahan Bakar', 'select', 30, true, ''],
            'warna_tnkb' => ['Warna TNKB', 'select', 30, true, ''],
            'tahun_registrasi' => ['Tahun Registrasi', 'year', 4, true, '2024'],
            'nomor_bpkb' => ['Nomor BPKB', 'text', 50, false, 'Nomor BPKB'],
            'nomor_urut' => ['Nomor Urut Pendaftaran', 'text', 50, false, 'Nomor urut'],
            'kode_lokasi' => ['Kode Lokasi', 'text', 30, false, 'Contoh: JKT-01'],
        ]],
        'administrasi' => ['title' => 'Data administrasi', 'description' => 'Masa berlaku dan referensi pencatatan internal.', 'icon' => 'file-earmark-text', 'fields' => [
            'tanggal_penerbitan' => ['Tanggal Penerbitan', 'date', 10, true, ''],
            'berlaku_sampai' => ['Tanggal Berlaku Sampai', 'date', 10, true, ''],
            'nomor_referensi' => ['Nomor Referensi Internal', 'text', 60, false, 'Contoh: INT/2026/0001'],
            'teks_atas_qr' => ['Tulisan di atas QR', 'text', 60, false, 'TELITI ULANG'],
            'wilayah_pengesahan' => ['Wilayah / provinsi', 'text', 60, false, 'Contoh: JAWA TIMUR'],
            'pengesahan_internal' => ['Nama / isi pengesahan', 'textarea', 180, false, 'Contoh: Ahmad Kh Km'],
            'jabatan_pemeriksa' => ['Jabatan', 'text', 100, false, 'Jabatan pemeriksa internal'],
            'nrp_pemeriksa' => ['NRP', 'text', 30, false, 'Nomor registrasi personel'],
            'catatan' => ['Catatan Admin', 'textarea', 2000, false, 'Catatan untuk administrasi internal (tidak dicetak)'],
        ]],
    ];
}

function field_options(string $name): array
{
    return $name === 'bahan_bakar' ? ['BENSIN', 'SOLAR', 'LISTRIK', 'HYBRID', 'GAS', 'LAINNYA']
        : ['PUTIH', 'HITAM', 'KUNING', 'MERAH', 'HIJAU', 'LAINNYA'];
}

function vehicle_columns(): array
{
    $columns = [];
    foreach (vehicle_fields() as $group) {
        $columns = array_merge($columns, array_keys($group['fields']));
    }
    return $columns;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : preg_match_all('/./us', $value);
}

function uppercase_text(string $value): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($value, 'UTF-8') : strtoupper($value);
}

function uppercase_vehicle(array $vehicle): array
{
    foreach (vehicle_fields() as $group) {
        foreach ($group['fields'] as $name => [, $type]) {
            if (in_array($type, ['text', 'textarea', 'select'], true) && isset($vehicle[$name]) && is_string($vehicle[$name])) {
                $vehicle[$name] = uppercase_text($vehicle[$name]);
            }
        }
    }
    return $vehicle;
}

function validate_vehicle(array $input): array
{
    $input += ['teks_atas_qr' => 'TELITI ULANG'];
    $input = uppercase_vehicle($input);
    $data = $errors = [];
    foreach (vehicle_fields() as $group) {
        foreach ($group['fields'] as $name => [$label, $type, $max, $required]) {
            $raw = $input[$name] ?? '';
            $value = is_string($raw) ? trim($raw) : '';
            $value = str_replace(["\r\n", "\r"], "\n", $value);
            if (in_array($name, ['nomor_polisi', 'nomor_rangka', 'nomor_mesin'], true)) {
                $value = strtoupper(preg_replace('/\s+/u', ' ', $value) ?? $value);
            }
            $data[$name] = $value === '' ? null : $value;
            if ($required && $value === '') {
                $errors[$name] = "$label wajib diisi.";
            } elseif ($value !== '') {
                if (!preg_match('//u', $value) || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
                    $errors[$name] = "$label mengandung karakter tidak valid.";
                } elseif (text_length($value) > $max) {
                    $errors[$name] = "$label maksimal $max karakter.";
                } elseif ($name === 'nik' && !preg_match('/^\d{16}$/D', $value)) {
                    $errors[$name] = 'NIK harus tepat 16 digit angka.';
                } elseif ($type === 'year' && (!preg_match('/^\d{4}$/D', $value) || (int) $value < 1900 || (int) $value > (int) date('Y') + 1)) {
                    $errors[$name] = 'Tahun harus antara 1900 dan ' . ((int) date('Y') + 1) . '.';
                } elseif ($type === 'number' && (!ctype_digit($value) || (int) $value > 100000)) {
                    $errors[$name] = 'Isi silinder harus 0–100000 cc. Gunakan 0 untuk kendaraan listrik.';
                } elseif ($type === 'select' && !in_array($value, field_options($name), true)) {
                    $errors[$name] = "Pilih $label yang tersedia.";
                } elseif ($type === 'date') {
                    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                    if (!$date || $date->format('Y-m-d') !== $value || $value < '1900-01-01' || $value > '2199-12-31') {
                        $errors[$name] = 'Tanggal harus valid, antara tahun 1900–2199.';
                    }
                }
            }
        }
    }
    if (!isset($errors['berlaku_sampai']) && !isset($errors['tanggal_penerbitan']) && $data['berlaku_sampai'] && $data['tanggal_penerbitan'] && $data['berlaku_sampai'] < $data['tanggal_penerbitan']) {
        $errors['berlaku_sampai'] = 'Masa berlaku tidak boleh sebelum tanggal penerbitan.';
    }
    return [$data, $errors];
}

function save_vehicle(?int $id = null): never
{
    verify_csrf();
    if ($id !== null) {
        $existing = find_vehicle($id);
    }
    [$data, $errors] = validate_vehicle($_POST);
    $duplicate = db()->prepare('SELECT id FROM kendaraan WHERE nomor_polisi = ? AND id <> ?');
    $duplicate->execute([$data['nomor_polisi'], $id ?? 0]);
    if ($duplicate->fetch()) {
        $errors['nomor_polisi'] = 'Nomor polisi sudah digunakan pada data lain.';
    }
    if ($id !== null && post_string('version') !== $existing['updated_at']) {
        $errors['_form'] = 'Data telah diubah di halaman lain. Buka ulang halaman edit untuk memuat data terbaru.';
    }
    if (!$errors) {
        $columns = vehicle_columns();
        try {
            if ($id === null) {
                $sql = 'INSERT INTO kendaraan (' . implode(',', $columns) . ') VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
                db()->prepare($sql)->execute(array_values($data));
                $id = (int) db()->lastInsertId();
            } else {
                $sql = 'UPDATE kendaraan SET ' . implode(', ', array_map(fn($name) => "$name = ?", $columns)) . ', updated_at = CURRENT_TIMESTAMP(6) WHERE id = ? AND updated_at = ?';
                $statement = db()->prepare($sql);
                $statement->execute([...array_values($data), $id, post_string('version')]);
                if ($statement->rowCount() !== 1) {
                    $errors['_form'] = 'Data telah diubah atau dihapus di halaman lain. Muat ulang sebelum menyimpan.';
                }
            }
        } catch (PDOException $exception) {
            if (($exception->errorInfo[1] ?? 0) !== 1062) {
                throw $exception;
            }
            $errors['nomor_polisi'] = 'Nomor polisi sudah digunakan pada data lain.';
        }
        if (!$errors) {
            flash('Data kendaraan berhasil disimpan.');
            redirect(post_string('action') === 'preview' ? 'preview.php?id=' . $id : 'detail.php?id=' . $id);
        }
    }
    http_response_code(422);
    $vehicle = $data;
    if (isset($existing)) {
        $vehicle['id'] = $id;
        $vehicle['updated_at'] = post_string('version');
    }
    $pageTitle = $id ? 'Edit kendaraan' : 'Tambah kendaraan';
    require APP_ROOT . '/includes/header.php';
    require APP_ROOT . '/includes/vehicle-form.php';
    require APP_ROOT . '/includes/footer.php';
    exit;
}

function format_date(?string $value): string
{
    if (!$value) return '—';
    $months = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    $date = new DateTimeImmutable($value);
    return $date->format('d') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
}

// Satu format cetak tetap sesuai hasil yang disetujui.
function print_settings(): array
{
    return ['paper_width_mm' => 297, 'paper_height_mm' => 230, 'document_print_height_mm' => 210,
        'font_size_pt' => 9, 'line_height' => 1.2, 'padding_mm' => 4, 'margin_mm' => 0, 'document_raise_mm' => 10.5];
}

function selected_print_position(): string
{
    $default = $_SESSION['print_position'] ?? 'middle';
    $position = $_SERVER['REQUEST_METHOD'] === 'POST'
        ? post_string('position', $default) : query_string('position', $default);
    if (!in_array($position, ['left', 'middle', 'right'], true)) $position = 'middle';
    $_SESSION['print_position'] = $position;
    return $position;
}

function print_layout_geometry(string $position): array
{
    $settings = print_settings();
    $slot = match ($position) { 'left' => 0, 'right' => 2, default => 1 };
    $slotWidth = $settings['paper_width_mm'] / 3;
    // Tambah ruang halaman tanpa mengubah skala dan posisi isian yang sudah dikalibrasi.
    $scale = min(1.0, $slotWidth / 75, $settings['document_print_height_mm'] / 230);
    $printedWidth = 75 * $scale;
    $printedHeight = 230 * $scale;
    $left = $slot * $slotWidth + ($slotWidth - $printedWidth) / 2;
    $top = 0;
    // Isi diputar 90 derajat: geser +X pada kertas untuk menaikkan seluruh isi pada arah baca STNK.
    return ['width' => 230, 'height' => 75, 'scale' => $scale,
        'x' => $left + $printedWidth + $settings['document_raise_mm'], 'y' => $top,
        'printed_width' => $printedWidth, 'printed_height' => $printedHeight,
        'slot_left' => $slot * $slotWidth, 'slot_width' => $slotWidth];
}

function print_css(string $position = 'middle'): string
{
    $g = print_layout_geometry($position);
    $settings = print_settings();
    // Pertahankan ukuran huruf cetak meskipun bidang STNK diperkecil.
    $layoutFont = $settings['font_size_pt'] / $g['scale'];
    $number = fn($value) => number_format((float) $value, 2, '.', '');
    return '<style id="paper-rules">@page { size: ' . $number($settings['paper_width_mm']) . 'mm ' . $number($settings['paper_height_mm']) . 'mm; margin: 0; } :root {'
        . '--paper-w:' . $number($settings['paper_width_mm']) . 'mm;--paper-h:' . $number($settings['paper_height_mm']) . 'mm;--doc-w:230mm;--doc-h:75mm;'
        . '--doc-x:' . $number($g['x']) . 'mm;--doc-y:' . $number($g['y']) . 'mm;'
        . '--doc-rotation:90deg;--doc-scale:' . number_format($g['scale'], 6, '.', '') . ';'
        . '--slot-left:' . $number($g['slot_left']) . 'mm;--slot-width:' . $number($g['slot_width']) . 'mm;'
        . '--doc-font:' . $number($layoutFont) . 'pt;--doc-line:' . $number($settings['line_height']) . ';'
        . '--doc-pad:' . $number($settings['padding_mm']) . 'mm;--safe-margin:0mm;}</style>';
}
