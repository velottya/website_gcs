<?php
declare(strict_types=1);
require __DIR__ . '/auth.php';

// Bila sudah login, langsung ke form.
if (is_logged_in()) {
    header('Location: form_data_karyawan_gcs.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/db.php';
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $error = 'Username/email dan password wajib diisi.';
    } else {
        try {
            $pdo = get_pdo();
            // Login bisa pakai email ATAU user_easy; hanya user berstatus Aktif.
            $stmt = $pdo->prepare(
                'SELECT TOP 1 id, name, email, user_easy, password, status
                 FROM easy.users
                 WHERE (email = :id1 OR user_easy = :id2)'
            );
            $stmt->execute(['id1' => $identifier, 'id2' => $identifier]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($password, (string)$user['password'])) {
                $error = 'Username/email atau password salah.';
            } elseif (strcasecmp((string)$user['status'], 'Aktif') !== 0) {
                $error = 'Akun Anda tidak aktif. Hubungi administrator.';
            } else {
                // Sukses — buat sesi baru (cegah session fixation).
                session_regenerate_id(true);
                $_SESSION['user_id']    = $user['id'];
                $_SESSION['user_name']  = $user['name'];
                $_SESSION['user_easy']  = $user['user_easy'];
                header('Location: form_data_karyawan_gcs.php');
                exit;
            }
        } catch (Throwable $e) {
            $error = 'Terjadi masalah koneksi. Coba lagi beberapa saat.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login — MyGCS</title>
<style>
  :root{
    --forest:#0f3d2e; --forest-deep:#0a2a20; --gold:#c9a24b; --gold-soft:#e8d7a8;
    --cream:#f7f5ef; --paper:#ffffff; --ink:#1c2621; --ink-soft:#5b6a62;
    --line:#dfe3dc; --danger:#b3492f;
    font-family:'Segoe UI',-apple-system,BlinkMacSystemFont,sans-serif;
  }
  *{box-sizing:border-box;}
  body{
    margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center;
    background:linear-gradient(135deg,var(--forest-deep),var(--forest)); padding:20px; color:var(--ink);
  }
  .card{
    background:var(--paper); width:100%; max-width:390px; border-radius:14px;
    box-shadow:0 18px 50px rgba(0,0,0,.28); overflow:hidden;
  }
  .head{ background:var(--forest); color:#fff; padding:26px 28px 22px; }
  .eyebrow{ font-size:12px; letter-spacing:.14em; text-transform:uppercase; color:var(--gold-soft); }
  .head h1{ margin:8px 0 4px; font-size:20px; }
  .head p{ margin:0; font-size:12.5px; color:#d8e2db; }
  .gold-rule{ height:3px; width:56px; background:var(--gold); margin-top:14px; border-radius:2px; }
  form{ padding:24px 28px 28px; }
  label{ display:block; font-size:13px; font-weight:600; margin:0 0 6px; }
  input{
    width:100%; padding:11px 12px; border:1px solid var(--line); border-radius:8px;
    font-size:14px; font-family:inherit; margin-bottom:16px;
  }
  input:focus{ outline:none; border-color:var(--gold); box-shadow:0 0 0 3px rgba(201,162,75,.18); }
  button{
    width:100%; padding:12px; border:0; border-radius:8px; cursor:pointer;
    background:var(--forest); color:#fff; font-size:14.5px; font-weight:600; font-family:inherit;
  }
  button:hover{ background:var(--forest-deep); }
  .error{
    background:#fbeae6; color:var(--danger); border:1px solid #eebfb3;
    padding:10px 12px; border-radius:8px; font-size:13px; margin-bottom:16px;
  }
  .foot{ text-align:center; font-size:11.5px; color:var(--ink-soft); padding:0 28px 22px; }
</style>
</head>
<body>
  <div class="card">
    <div class="head">
      <div class="eyebrow">MyGCS · Akses Terbatas</div>
      <h1>Masuk untuk Mengisi Data</h1>
      <p>Gunakan akun MyGCS Anda untuk melanjutkan.</p>
      <div class="gold-rule"></div>
    </div>
    <form method="post" autocomplete="off">
      <?php if ($error !== ''): ?>
        <div class="error"><?= htmlspecialchars($error, ENT_QUOTES) ?></div>
      <?php endif; ?>
      <label for="identifier">Username atau Email</label>
      <input type="text" id="identifier" name="identifier" required autofocus
             value="<?= htmlspecialchars((string)($_POST['identifier'] ?? ''), ENT_QUOTES) ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <button type="submit">Masuk</button>
    </form>
    <div class="foot">Halaman ini hanya untuk karyawan PT GCS yang berwenang.</div>
  </div>
</body>
</html>
