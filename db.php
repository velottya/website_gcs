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
