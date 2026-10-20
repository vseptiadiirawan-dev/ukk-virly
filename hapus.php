<?php
include "auth.php";

if ($_SESSION['level'] === 'Peminjam') {
    header('Location: dashboard.php');
    exit;
}

$table = $_GET['table'] ?? 'alat';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$redirects = ['alat' => 'tampil1.php', 'kategori' => 'kategori.php', 'users' => 'user.php'];
if (!array_key_exists($table, $redirects) || !$id) {
	die('Data tidak valid.');
}

if ($table === 'users' && $_SESSION['level'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$stmt = mysqli_prepare($conn, "DELETE FROM $table WHERE id = ?");
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	mysqli_stmt_bind_param($stmt, 'i', $id);
	if (!mysqli_stmt_execute($stmt)) {
		die('Data gagal dihapus: ' . mysqli_error($conn));
	}

	header('Location: ' . $redirects[$table]);
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<title>Konfirmasi Hapus</title>
	<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
	<div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
	<nav><a href="dashboard.php"><span class="nav-icon">▦</span>Dashboard</a><a href="tampil1.php"><span class="nav-icon">▣</span>Data Alat</a><a href="kategori.php"><span class="nav-icon">◈</span>Kategori</a><a href="user.php"><span class="nav-icon">♙</span>Pengguna</a><a href="logaktvs.php"><span class="nav-icon">◷</span>Log Aktivitas</a></nav>
	<div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Tindakan data</p><h1>Hapus data</h1><p class="welcome">Periksa kembali sebelum melanjutkan.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<section class="page-section confirm-card">
	<h2>Yakin ingin menghapus data ini?</h2>
	<p>Data yang sudah dihapus tidak dapat dikembalikan. Pastikan Anda memilih data yang benar.</p>
	<form class="form-actions" method="post">
		<button class="button danger" type="submit">Ya, hapus data</button>
		<a class="button secondary" href="<?= $redirects[$table] ?>">Batalkan</a>
	</form>
</section>
</main></div>
</body>
</html>