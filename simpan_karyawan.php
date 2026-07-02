<?php
declare(strict_types=1);

// Endpoint ini selalu mengembalikan JSON. Cegah warning/notice PHP ikut tercetak
// ke output (agar respons tetap JSON valid); error tetap tercatat di log server.
ini_set('display_errors', '0');

header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/db.php';

function respond(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['success' => false, 'message' => 'Metode tidak diizinkan.']);
}

$requiredFields = [
    'nama_lengkap', 'nik', 'id_karyawan', 'tempat_lahir', 'tgl_lahir',
    'jenis_kelamin', 'status_karyawan', 'hp', 'email', 'alamat', 'rt', 'rw',
    'provinsi', 'kabupaten', 'kecamatan', 'desa', 'kodepos', 'agama',
    'pendidikan', 'status_nikah', 'jumlah_anak', 'nama_darurat', 'hp_darurat',
];

$missing = [];
foreach ($requiredFields as $field) {
    if (trim((string)($_POST[$field] ?? '')) === '') {
        $missing[] = $field;
    }
}
if ($missing) {
    respond(400, ['success' => false, 'message' => 'Field wajib belum lengkap: ' . implode(', ', $missing)]);
}

if (!preg_match('/^\d{16}$/', $_POST['nik'])) {
    respond(400, ['success' => false, 'message' => 'NIK harus 16 digit angka.']);
}

$statusNikah = $_POST['status_nikah'];
if ($statusNikah === 'Kawin') {
    foreach (['nama_pasangan', 'tempat_lahir_pasangan', 'tgl_lahir_pasangan'] as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            respond(400, ['success' => false, 'message' => 'Data pasangan wajib diisi jika status pernikahan Kawin.']);
        }
    }
}

$jumlahAnak = (int)$_POST['jumlah_anak'];
if ($jumlahAnak < 0 || $jumlahAnak > 3) {
    respond(400, ['success' => false, 'message' => 'Jumlah anak tidak valid.']);
}
for ($i = 1; $i <= $jumlahAnak; $i++) {
    foreach (["anak{$i}_nama", "anak{$i}_tempat_lahir", "anak{$i}_tgl_lahir"] as $field) {
        if (trim((string)($_POST[$field] ?? '')) === '') {
            respond(400, ['success' => false, 'message' => "Data anak ke-{$i} belum lengkap."]);
        }
    }
}

$allowedExt = ['jpg', 'jpeg', 'png', 'pdf'];
$maxSize = 5 * 1024 * 1024; // 5MB
$uploadDir = __DIR__ . '/uploads/karyawan/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$savedFiles = [];

function store_upload(string $inputName, array $allowedExt, int $maxSize, string $uploadDir, array &$savedFiles): ?string {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$inputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException("Gagal mengunggah berkas ({$inputName}).");
    }
    if ($file['size'] > $maxSize) {
        throw new RuntimeException("Ukuran berkas ({$inputName}) melebihi 5MB.");
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        throw new RuntimeException("Format berkas ({$inputName}) tidak didukung.");
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $destination = $uploadDir . $storedName;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException("Gagal menyimpan berkas ({$inputName}).");
    }
    $savedFiles[] = $destination;
    return 'uploads/karyawan/' . $storedName;
}

try {
    $fileKtp = store_upload('ktp', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileIjazah = store_upload('ijazah', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileBukuNikah = store_upload('buku_nikah', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileKk = store_upload('kk', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileSim = store_upload('sim', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileGadaPratama = store_upload('gada_pratama', $allowedExt, $maxSize, $uploadDir, $savedFiles);
    $fileK3 = store_upload('k3', $allowedExt, $maxSize, $uploadDir, $savedFiles);

    $childFiles = [];
    for ($i = 1; $i <= $jumlahAnak; $i++) {
        $childFiles[$i] = store_upload("anak{$i}_akta", $allowedExt, $maxSize, $uploadDir, $savedFiles);
    }

    $pdo = get_pdo();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('
        INSERT INTO dbo.MST_PEGAWAI (
            NAMA_LENGKAP, NIK, ID_KARYAWAN, TEMPAT_LAHIR, TGL_LAHIR, JENIS_KELAMIN,
            STATUS_KARYAWAN, FILE_KTP, NO_HP, EMAIL, ALAMAT, RT, RW, PROVINSI,
            KABUPATEN, KECAMATAN, DESA, KODE_POS, AGAMA, PENDIDIKAN, FILE_IJAZAH,
            RIWAYAT_KESEHATAN, STATUS_NIKAH, NAMA_PASANGAN, TEMPAT_LAHIR_PASANGAN,
            TGL_LAHIR_PASANGAN, FILE_BUKU_NIKAH, JUMLAH_ANAK, NAMA_DARURAT,
            HP_DARURAT, FILE_KK, FILE_SIM, FILE_GADA_PRATAMA, FILE_K3
        )
        OUTPUT INSERTED.ID_PEGAWAI
        VALUES (
            :nama_lengkap, :nik, :id_karyawan, :tempat_lahir, :tgl_lahir, :jenis_kelamin,
            :status_karyawan, :file_ktp, :hp, :email, :alamat, :rt, :rw, :provinsi,
            :kabupaten, :kecamatan, :desa, :kodepos, :agama, :pendidikan, :file_ijazah,
            :kesehatan, :status_nikah, :nama_pasangan, :tempat_lahir_pasangan,
            :tgl_lahir_pasangan, :file_buku_nikah, :jumlah_anak, :nama_darurat,
            :hp_darurat, :file_kk, :file_sim, :file_gada_pratama, :file_k3
        )
    ');

    $stmt->execute([
        'nama_lengkap' => $_POST['nama_lengkap'],
        'nik' => $_POST['nik'],
        'id_karyawan' => $_POST['id_karyawan'],
        'tempat_lahir' => $_POST['tempat_lahir'],
        'tgl_lahir' => $_POST['tgl_lahir'],
        'jenis_kelamin' => $_POST['jenis_kelamin'],
        'status_karyawan' => $_POST['status_karyawan'],
        'file_ktp' => $fileKtp,
        'hp' => $_POST['hp'],
        'email' => $_POST['email'],
        'alamat' => $_POST['alamat'],
        'rt' => $_POST['rt'],
        'rw' => $_POST['rw'],
        'provinsi' => $_POST['provinsi'],
        'kabupaten' => $_POST['kabupaten'],
        'kecamatan' => $_POST['kecamatan'],
        'desa' => $_POST['desa'],
        'kodepos' => $_POST['kodepos'],
        'agama' => $_POST['agama'],
        'pendidikan' => $_POST['pendidikan'],
        'file_ijazah' => $fileIjazah,
        'kesehatan' => $_POST['kesehatan'] ?? null,
        'status_nikah' => $statusNikah,
        'nama_pasangan' => $_POST['nama_pasangan'] ?? null,
        'tempat_lahir_pasangan' => $_POST['tempat_lahir_pasangan'] ?? null,
        'tgl_lahir_pasangan' => ($_POST['tgl_lahir_pasangan'] ?? '') ?: null,
        'file_buku_nikah' => $fileBukuNikah,
        'jumlah_anak' => $jumlahAnak,
        'nama_darurat' => $_POST['nama_darurat'],
        'hp_darurat' => $_POST['hp_darurat'],
        'file_kk' => $fileKk,
        'file_sim' => $fileSim,
        'file_gada_pratama' => $fileGadaPratama,
        'file_k3' => $fileK3,
    ]);

    $idPegawai = (int)$stmt->fetchColumn();
    $stmt->closeCursor();

    if ($jumlahAnak > 0) {
        $stmtAnak = $pdo->prepare('
            INSERT INTO dbo.MST_ANAK_PEGAWAI (
                ID_PEGAWAI, URUTAN_ANAK, NAMA_ANAK, TEMPAT_LAHIR_ANAK, TGL_LAHIR_ANAK, FILE_AKTA
            ) VALUES (
                :id_pegawai, :urutan, :nama_anak, :tempat_lahir_anak, :tgl_lahir_anak, :file_akta
            )
        ');
        for ($i = 1; $i <= $jumlahAnak; $i++) {
            $stmtAnak->execute([
                'id_pegawai' => $idPegawai,
                'urutan' => $i,
                'nama_anak' => $_POST["anak{$i}_nama"],
                'tempat_lahir_anak' => $_POST["anak{$i}_tempat_lahir"],
                'tgl_lahir_anak' => $_POST["anak{$i}_tgl_lahir"],
                'file_akta' => $childFiles[$i],
            ]);
        }
    }

    $pdo->commit();
    respond(200, ['success' => true, 'id' => $idPegawai]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
    $isDuplicate = str_contains($e->getMessage(), 'UQ_MST_PEGAWAI');
    respond($isDuplicate ? 409 : 500, [
        'success' => false,
        'message' => $isDuplicate
            ? 'NIK atau ID Karyawan sudah terdaftar sebelumnya.'
            : 'Gagal menyimpan data ke database.',
    ]);
}
