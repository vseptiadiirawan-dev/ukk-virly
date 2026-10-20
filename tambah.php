<?php
include "auth.php";

if ($_SESSION['level'] === 'Peminjam') {
    header('Location: dashboard.php');
    exit;
}

$table = $_GET['table'] ?? 'alat';
$allowed = ['alat', 'kategori', 'users'];
if (!in_array($table, $allowed, true)) {
    die('Tabel tidak valid.');
}

if ($table === 'users' && $_SESSION['level'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$categoriesQuery = mysqli_query($conn, 'SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC');
$categories = $categoriesQuery ? mysqli_fetch_all($categoriesQuery, MYSQLI_ASSOC) : [];
$categoryIds = array_map('intval', array_column($categories, 'id'));
$error = '';

if (isset($_POST['simpan'])) {
    if ($table === 'users' && !in_array($_POST['level'] ?? '', ['Admin', 'Petugas', 'Peminjam'], true)) {
        die('Level pengguna tidak valid.');
    }

    if ($table === 'alat') {
        $categoryId = filter_input(INPUT_POST, 'kategori_id', FILTER_VALIDATE_INT);
        $namaAlat = trim($_POST['nama_alat'] ?? '');
        $merk = trim($_POST['merk'] ?? '');
        $stok = filter_input(INPUT_POST, 'stok', FILTER_VALIDATE_INT);
        $kondisi = $_POST['kondisi'] ?? '';

        if ($categoryId === false || $categoryId === null || !in_array($categoryId, $categoryIds, true)) {
            $error = 'Pilih kategori yang tersedia.';
        } elseif ($namaAlat === '') {
            $error = 'Nama alat wajib diisi.';
        } elseif ($stok === false || $stok === null || $stok < 0) {
            $error = 'Stok harus berupa angka nol atau lebih.';
        } elseif (!in_array($kondisi, ['Baik', 'Rusak Ringan', 'Rusak Berat'], true)) {
            $error = 'Pilih kondisi alat yang tersedia.';
        } else {
            $stmt = mysqli_prepare($conn, 'INSERT INTO alat (kategori_id, nama_alat, merk, stok, kondisi) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'issis', $categoryId, $namaAlat, $merk, $stok, $kondisi);
                $redirect = 'tampil1.php';
            } else {
                $error = 'Query tambah alat gagal disiapkan: ' . mysqli_error($conn);
            }
        }
    } elseif ($table === 'kategori') {
        $stmt = mysqli_prepare($conn, 'INSERT INTO kategori (nama_kategori, deskripsi) VALUES (?, ?)');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ss', $_POST['nama_kategori'], $_POST['deskripsi']);
        } else {
            $error = 'Query tambah kategori gagal disiapkan: ' . mysqli_error($conn);
        }
        $redirect = 'kategori.php';
    } else {
        $stmt = mysqli_prepare($conn, 'INSERT INTO users (nama, username, password, level) VALUES (?, ?, ?, ?)');
        if ($stmt) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            mysqli_stmt_bind_param($stmt, 'ssss', $_POST['nama'], $_POST['username'], $password, $_POST['level']);
        } else {
            $error = 'Query tambah pengguna gagal disiapkan: ' . mysqli_error($conn);
        }
        $redirect = 'user.php';
    }

    if ($error === '') {
        if (mysqli_stmt_execute($stmt)) {
            header('Location: ' . $redirect);
            exit;
        }
        $error = 'Data gagal disimpan: ' . mysqli_stmt_error($stmt);
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Tambah Data</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
    <nav><a href="dashboard.php"><span class="nav-icon">▦</span>Dashboard</a><a href="tampil1.php"><span class="nav-icon">▣</span>Data Alat</a><a href="kategori.php"><span class="nav-icon">◈</span>Kategori</a><a href="user.php"><span class="nav-icon">♙</span>Pengguna</a><a href="logaktvs.php"><span class="nav-icon">◷</span>Log Aktivitas</a></nav>
    <div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Data baru</p><h1>Tambah <?= htmlspecialchars(ucfirst($table)) ?></h1><p class="welcome">Isi informasi berikut dengan lengkap.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<?php if ($error !== ''): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($table === 'alat' && !$categories): ?><div class="alert">Belum ada kategori. <a href="tambah.php?table=kategori">Tambah kategori</a> terlebih dahulu.</div><?php endif; ?>
<section class="page-section form-card"><form class="form-grid" method="post">

<?php if ($table === 'alat'): ?>
<div class="field"><label>Kategori</label><select name="kategori_id" required>
    <option value="">-- Pilih kategori --</option>
    <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= (string) ($_POST['kategori_id'] ?? '') === (string) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['nama_kategori']) ?></option>
    <?php endforeach; ?>
</select></div>

<div class="field"><label>Nama Alat</label><input type="text" name="nama_alat" value="<?= htmlspecialchars($_POST['nama_alat'] ?? '') ?>" required></div>

<div class="field"><label>Merk</label><input type="text" name="merk" value="<?= htmlspecialchars($_POST['merk'] ?? '') ?>"></div>

<div class="field"><label>Stok</label><input type="number" name="stok" value="<?= htmlspecialchars($_POST['stok'] ?? '') ?>" min="0" required></div>

<div class="field"><label>Kondisi</label><select name="kondisi">
    <?php foreach (['Baik', 'Rusak Ringan', 'Rusak Berat'] as $kondisi): ?>
        <option value="<?= $kondisi ?>" <?= ($_POST['kondisi'] ?? 'Baik') === $kondisi ? 'selected' : '' ?>><?= $kondisi ?></option>
    <?php endforeach; ?>
</select>
</div>
<?php elseif ($table === 'kategori'): ?>
<div class="field"><label>Nama Kategori</label><input type="text" name="nama_kategori" required></div>

<div class="field full"><label>Deskripsi</label><textarea name="deskripsi"></textarea></div>
<?php else: ?>
<div class="field"><label>Nama Lengkap</label><input type="text" name="nama" required></div>

<div class="field"><label>Username</label><input type="text" name="username" required></div>

<div class="field"><label>Password</label><input type="password" name="password" required></div>

<div class="field"><label>Level</label><select name="level" required>
    <option value="Peminjam">Peminjam</option>
    <option value="Petugas">Petugas</option>
    <option value="Admin">Admin</option>
</select></div>
<?php endif; ?>

<div class="field full form-actions"><button class="button" type="submit" name="simpan">Simpan data</button><a class="button secondary" href="<?= $table === 'alat' ? 'tampil1.php' : ($table === 'kategori' ? 'kategori.php' : 'user.php') ?>">Kembali</a></div>

</form></section></main></div>

</body>
</html>