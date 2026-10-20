<?php
session_start();
include "koneksi.php";

$error = '';
$success = isset($_GET['registered']) ? 'Pendaftaran berhasil. Silakan masuk dengan akun Anda.' : '';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Username dan password wajib diisi.';
    } else {
        $cek_kolom = mysqli_query($conn, "SHOW COLUMNS FROM users LIKE 'password'");
        if (!$cek_kolom || mysqli_num_rows($cek_kolom) === 0) {
            $error = 'Kolom password belum tersedia di tabel users.';
        } else {
            $countResult = mysqli_query($conn, 'SELECT COUNT(*) AS total FROM users');
            $countData = $countResult ? mysqli_fetch_assoc($countResult)['total'] : 0;

            if ((int) $countData === 0) {
                $defaultPassword = password_hash('admin123', PASSWORD_DEFAULT);
                mysqli_query($conn, "INSERT INTO users (nama, username, password, level) VALUES ('Administrator', 'admin', '$defaultPassword', 'Admin')");
            }

            $stmt = mysqli_prepare($conn, 'SELECT id, nama, username, password, level FROM users WHERE username = ? LIMIT 1');
            if (!$stmt) {
                $error = 'Gagal menyiapkan query login.';
            } else {
                mysqli_stmt_bind_param($stmt, 's', $username);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = mysqli_fetch_assoc($result);

                if (!$user) {
                    $error = 'Username tidak ditemukan.';
                } else {
                    $storedPassword = (string) $user['password'];
                    $isValid = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

                    if (!$isValid) {
                        $error = 'Password salah.';
                    } else {
                        $passwordInfo = password_get_info($storedPassword);
                        if (!empty($passwordInfo['algo']) && password_needs_rehash($storedPassword, PASSWORD_DEFAULT)) {
                            $newHash = password_hash($password, PASSWORD_DEFAULT);
                            $update = mysqli_prepare($conn, 'UPDATE users SET password = ? WHERE id = ?');
                            mysqli_stmt_bind_param($update, 'si', $newHash, $user['id']);
                            mysqli_stmt_execute($update);
                        }

                        session_regenerate_id(true);
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['level'] = $user['level'];
                        header('Location: dashboard.php');
                        exit;
                    }
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<main class="login-card">
<div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div>
<h1>Masuk</h1>
<p class="welcome">Gunakan akun Anda untuk melanjutkan.</p>
<?php if ($error): ?>
    <div class="alert"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert" style="border-color: var(--teal); color: var(--teal-dark); background: #eaf3e5;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<form class="form-grid" method="post">
    <div class="field full"><label for="username">Username</label><input id="username" type="text" name="username" required autofocus></div>
    <div class="field full"><label for="password">Password</label><input id="password" type="password" name="password" required></div>
    <div class="field full form-actions"><button class="button" type="submit" name="login">Masuk</button></div>
</form>
<p class="welcome" style="margin-top: 20px; text-align: center;">Belum punya akun? <a href="register.php">Daftar sekarang</a></p>
</main>
</body>
</html>
