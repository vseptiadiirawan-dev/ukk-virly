<?php
session_start();
include "koneksi.php";

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$nama = '';
$username = '';

if (isset($_POST['register'])) {
    $nama = trim($_POST['nama'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($nama === '' || $username === '' || $password === '') {
        $error = 'Semua kolom wajib diisi.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($password !== $passwordConfirmation) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $check = mysqli_prepare($conn, 'SELECT id FROM users WHERE username = ? LIMIT 1');
        if (!$check) {
            $error = 'Gagal menyiapkan query pendaftaran.';
        } else {
            mysqli_stmt_bind_param($check, 's', $username);
            mysqli_stmt_execute($check);
            $existingUser = mysqli_stmt_get_result($check);

            if (mysqli_fetch_assoc($existingUser)) {
                $error = 'Username sudah digunakan.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = mysqli_prepare($conn, 'INSERT INTO users (nama, username, password, level) VALUES (?, ?, ?, ?)');
                if (!$stmt) {
                    $error = 'Gagal menyiapkan data pendaftaran.';
                } else {
                    $level = 'Peminjam';
                    mysqli_stmt_bind_param($stmt, 'ssss', $nama, $username, $passwordHash, $level);

                    if (mysqli_stmt_execute($stmt)) {
                        header('Location: login.php?registered=1');
                        exit;
                    }

                    $error = 'Pendaftaran gagal. Periksa kembali data Anda.';
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
    <title>Daftar Akun</title>
    <link rel="stylesheet" href="style.css">
</head>
<body class="login-page">
<main class="login-card">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div>
    <h1>Daftar</h1>
    <p class="welcome">Buat akun untuk mulai menggunakan AlatKita.</p>
    <?php if ($error): ?>
        <div class="alert"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form class="form-grid" method="post">
        <div class="field full">
            <label for="nama">Nama lengkap</label>
            <input id="nama" type="text" name="nama" value="<?= htmlspecialchars($nama) ?>" required autofocus>
        </div>
        <div class="field full">
            <label for="username">Username</label>
            <input id="username" type="text" name="username" value="<?= htmlspecialchars($username) ?>" required autocomplete="username">
        </div>
        <div class="field full">
            <label for="password">Password</label>
            <input id="password" type="password" name="password" minlength="8" required autocomplete="new-password">
        </div>
        <div class="field full">
            <label for="password_confirmation">Konfirmasi password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" minlength="8" required autocomplete="new-password">
        </div>
        <div class="field full form-actions">
            <button class="button" type="submit" name="register">Buat akun</button>
        </div>
    </form>
    <p class="welcome" style="margin-top: 20px; text-align: center;">Sudah punya akun? <a href="login.php">Masuk</a></p>
</main>
</body>
</html>