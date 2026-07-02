<?php
declare(strict_types=1);

// Endpoint cek duplikat NIK / ID Karyawan secara real-time (dipakai form saat mengetik).
// Selalu mengembalikan JSON.
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/auth.php';
require_login_api();
require __DIR__ . '/db.php';

$nik = trim((string)($_GET['nik'] ?? $_POST['nik'] ?? ''));
$idKaryawan = trim((string)($_GET['id_karyawan'] ?? $_POST['id_karyawan'] ?? ''));

if ($nik === '' && $idKaryawan === '') {
    echo json_encode(['nik' => false, 'id_karyawan' => false]);
    exit;
}

try {
    $pdo = get_pdo();
    $dup = find_duplicates($pdo, $nik, $idKaryawan);
    echo json_encode($dup);
} catch (Throwable $e) {
    // Jika DB bermasalah, jangan blokir input (validasi tetap ditegakkan saat submit & oleh UNIQUE constraint).
    http_response_code(200);
    echo json_encode(['nik' => false, 'id_karyawan' => false, 'error' => true]);
}
