<?php
function get_pdo(): PDO {
    $config = require __DIR__ . '/config.php';
    $dsn = sprintf(
        'sqlsrv:Server=%s,%s;Database=%s;TrustServerCertificate=true',
        $config['host'],
        $config['port'],
        $config['database']
    );
    return new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
}

/**
 * Cek apakah NIK dan/atau ID Karyawan sudah ada di dbo.MST_PEGAWAI.
 * Mengembalikan ['nik' => bool, 'id_karyawan' => bool].
 * Nilai kosong dianggap tidak perlu dicek (false).
 */
function find_duplicates(PDO $pdo, string $nik, string $idKaryawan): array {
    $result = ['nik' => false, 'id_karyawan' => false];
    if ($nik === '' && $idKaryawan === '') {
        return $result;
    }
    $stmt = $pdo->prepare(
        'SELECT NIK, ID_KARYAWAN FROM dbo.MST_PEGAWAI WHERE NIK = :nik OR ID_KARYAWAN = :idk'
    );
    $stmt->execute(['nik' => $nik, 'idk' => $idKaryawan]);
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($nik !== '' && (string)$row['NIK'] === $nik) {
            $result['nik'] = true;
        }
        if ($idKaryawan !== '' && (string)$row['ID_KARYAWAN'] === $idKaryawan) {
            $result['id_karyawan'] = true;
        }
    }
    return $result;
}
