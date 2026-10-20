<?php
include "auth.php";

if ($_SESSION['level'] === 'Peminjam') {
    header('Location: dashboard.php');
    exit;
}

$table = $_GET['table'] ?? 'alat';
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$allowed = ['alat', 'kategori', 'users'];
if (!in_array($table, $allowed, true) || !$id) {
    die('Data tidak valid.');
}

if ($table === 'users' && $_SESSION['level'] !== 'Admin') {
    header('Location: dashboard.php');
    exit;
}

$categoriesQuery = mysqli_query($conn, 'SELECT id, nama_kategori FROM kategori ORDER BY nama_kategori ASC');
$categories = $categoriesQuery ? mysqli_fetch_all($categoriesQuery, MYSQLI_ASSOC) : [];
$categoryIds = array_map('intval', array_column($categories, 'id'));

$stmt = mysqli_prepare($conn, "SELECT * FROM $table WHERE id = ?");
if (!$stmt) {
    die('Query data gagal disiapkan: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $id);
if (!mysqli_stmt_execute($stmt)) {
    die('Query data gagal dijalankan: ' . mysqli_stmt_error($stmt));
}
$result = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($result);
if (!$data) {
    die('Data tidak ditemukan.');
}

if (isset($_POST['simpan'])) {
    if ($table === 'users' && !in_array($_POST['level'] ?? '', ['Admin', 'Petugas', 'Peminjam'], true)) {
        die('Level pengguna tidak valid.');
    }

    if ($table === 'alat') {
        $categoryId = filter_input(INPUT_POST, 'kategori_id', FILTER_VALIDATE_INT);
        if ($categoryId === false || $categoryId === null || !in_array($categoryId, $categoryIds, true)) {
            die('Pilih kategori yang tersedia.');
        }

        $stmt = mysqli_prepare($conn, 'UPDATE alat SET kategori_id = ?, nama_alat = ?, merk = ?, stok = ?, kondisi = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'issisi', $categoryId, $_POST['nama_alat'], $_POST['merk'], $_POST['stok'], $_POST['kondisi'], $id);
        }
        $redirect = 'tampil1.php';
    } elseif ($table === 'kategori') {
        $stmt = mysqli_prepare($conn, 'UPDATE kategori SET nama_kategori = ?, deskripsi = ? WHERE id = ?');
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, 'ssi', $_POST['nama_kategori'], $_POST['deskripsi'], $id);
        }
        $redirect = 'kategori.php';
    } else {
        if ($_POST['password'] !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama = ?, username = ?, password = ?, level = ? WHERE id = ?');
            if ($stmt) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                mysqli_stmt_bind_param($stmt, 'ssssi', $_POST['nama'], $_POST['username'], $password, $_POST['level'], $id);
            }
        } else {
            $stmt = mysqli_prepare($conn, 'UPDATE users SET nama = ?, username = ?, level = ? WHERE id = ?');
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'sssi', $_POST['nama'], $_POST['username'], $_POST['level'], $id);
            }
        }
        $redirect = 'user.php';
    }

    if (!$stmt) {
        die('Query pembaruan gagal disiapkan: ' . mysqli_error($conn));
    }
    if (!mysqli_stmt_execute($stmt)) {
        die('Data gagal diperbarui: ' . mysqli_stmt_error($stmt));
    }
    header('Location: ' . $redirect);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Data</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
    <nav><a href="dashboard.php"><span class="nav-icon">▦</span>Dashboard</a><a href="tampil1.php"><span class="nav-icon">▣</span>Data Alat</a><a href="kategori.php"><span class="nav-icon">◈</span>Kategori</a><a href="user.php"><span class="nav-icon">♙</span>Pengguna</a><a href="logaktvs.php"><span class="nav-icon">◷</span>Log Aktivitas</a></nav>
    <div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Perbarui data</p><h1>Edit <?= htmlspecialchars(ucfirst($table)) ?></h1><p class="welcome">Perbarui informasi yang diperlukan.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<section class="page-section form-card"><form class="form-grid" method="post">

<?php if ($table === 'alat'): ?>
<div class="field"><label>Kategori</label><select name="kategori_id" required>
    <option value="">-- Pilih kategori --</option>
    <?php foreach ($categories as $category): ?>
        <option value="<?= (int) $category['id'] ?>" <?= (int) $data['kategori_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= htmlspecialchars($category['nama_kategori']) ?></option>
    <?php endforeach; ?>
</select></div>

<div class="field"><label>Nama Alat</label><input type="text" name="nama_alat" value="<?= htmlspecialchars($data['nama_alat']) ?>" required></div>

<div class="field"><label>Merk</label><input type="text" name="merk" value="<?= htmlspecialchars($data['merk']) ?>"></div>

<div class="field"><label>Stok</label><input type="number" name="stok" value="<?= htmlspecialchars($data['stok']) ?>" min="0" required></div>

<div class="field"><label>Kondisi</label><select name="kondisi">
    <?php foreach (['Baik', 'Rusak Ringan', 'Rusak Berat'] as $kondisi): ?>
        <option value="<?= $kondisi ?>" <?= $data['kondisi'] === $kondisi ? 'selected' : '' ?>><?= $kondisi ?></option>
    <?php endforeach; ?>
</select>
</div>
<?php elseif ($table === 'kategori'): ?>
<div class="field"><label>Nama Kategori</label><input type="text" name="nama_kategori" value="<?= htmlspecialchars($data['nama_kategori']) ?>" required></div>

<div class="field full"><label>Deskripsi</label><textarea name="deskripsi"><?= htmlspecialchars($data['deskripsi']) ?></textarea></div>
<?php else: ?>
<div class="field"><label>Nama Lengkap</label><input type="text" name="nama" value="<?= htmlspecialchars($data['nama']) ?>" required></div>

<div class="field"><label>Username</label><input type="text" name="username" value="<?= htmlspecialchars($data['username']) ?>" required></div>

<div class="field"><label>Password Baru</label><input type="password" name="password"></div>

<div class="field"><label>Level</label><select name="level" required>
    <?php foreach (['Peminjam', 'Petugas', 'Admin'] as $level): ?>
        <option value="<?= $level ?>" <?= $data['level'] === $level ? 'selected' : '' ?>><?= $level ?></option>
    <?php endforeach; ?>
</select></div>
<?php endif; ?>

<div class="field full form-actions"><button class="button" type="submit" name="simpan">Simpan perubahan</button><a class="button secondary" href="<?= $table === 'alat' ? 'tampil1.php' : ($table === 'kategori' ? 'kategori.php' : 'user.php') ?>">Kembali</a></div>

</form></section></main></div>

</body>
</html>