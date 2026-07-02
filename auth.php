<?php
// Pengelola sesi & guard autentikasi untuk area form karyawan.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

/** Untuk halaman (HTML): arahkan ke login bila belum masuk. */
function require_login(): void {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
}

/** Untuk endpoint JSON (AJAX): balas 401 bila belum masuk. */
function require_login_api(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => 'Sesi login berakhir. Silakan login ulang.']);
        exit;
    }
}

function current_user(): array {
    return [
        'id'        => $_SESSION['user_id']    ?? null,
        'name'      => $_SESSION['user_name']  ?? '',
        'user_easy' => $_SESSION['user_easy']  ?? '',
    ];
}
