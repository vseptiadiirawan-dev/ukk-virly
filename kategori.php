<?php
include "auth.php";
restrict_to_roles(['Admin', 'Petugas']);
$query = mysqli_query($conn, "SELECT * FROM kategori");
$data = mysqli_fetch_all($query, MYSQLI_ASSOC);
$menu_items = [
    'Admin' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['user.php', '♙', 'Pengguna'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ],
    'Petugas' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ]
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Data Kategori</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
    <nav>
        <?php foreach ($menu_items[$_SESSION['level'] ?? 'Peminjam'] as $item): ?>
            <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
            <a class="<?= $page === 'kategori.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Inventaris</p><h1>Data Kategori</h1><p class="welcome">Kelompokkan alat agar mudah ditemukan.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<div class="action-bar"><a class="button" href="tambah.php?table=kategori">+ Tambah kategori</a></div>
<section class="page-section"><div class="table-wrap"><table>
    <tr>
        <th>ID</th>
        <th>Nama Kategori</th>
        <th>Deskripsi</th>
        <th>Aksi</th>
    </tr>
    <?php foreach ($data as $k): ?>
    <tr>
        <td><?= htmlspecialchars($k['id']) ?></td>
        <td><?= htmlspecialchars($k['nama_kategori']) ?></td>
        <td><?= htmlspecialchars($k['deskripsi']) ?></td>
        <td class="table-actions">
            <a href="edit.php?table=kategori&id=<?= (int) $k['id'] ?>">Edit</a>
            <a href="hapus.php?table=kategori&id=<?= (int) $k['id'] ?>">Hapus</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div></section></main></div>
</body>
</html>