<?php
include "auth.php";
restrict_to_roles(['Admin', 'Petugas', 'Peminjam']);
$query = mysqli_query($conn, "
    SELECT alat.*, kategori.nama_kategori,
        (SELECT p.id
         FROM peminjaman p
         WHERE p.alat_id = alat.id AND p.status = 'dipinjam'
         ORDER BY p.id DESC
         LIMIT 1) AS active_loan_id
    FROM alat
    LEFT JOIN kategori ON alat.kategori_id = kategori.id
");
$data_alat = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];
$menu_items = [
    'Admin' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['user.php', '♙', 'Pengguna'],
        ['peminjaman.php', '◍', 'Peminjaman'],
        ['pengembalian.php', '↺', 'Pengembalian'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ],
    'Petugas' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['peminjaman.php', '◍', 'Peminjaman'],
        ['pengembalian.php', '↺', 'Pengembalian'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ],
    'Peminjam' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['peminjaman.php', '◍', 'Peminjaman'],
        ['pengembalian.php', '↺', 'Pengembalian']
    ]
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daftar Alat</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
    <nav>
        <?php foreach ($menu_items[$_SESSION['level'] ?? 'Peminjam'] as $item): ?>
            <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
            <a class="<?= $page === 'tampil1.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Inventaris</p><h1>Data Alat</h1><p class="welcome">Kelola seluruh alat yang tersimpan.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<div class="action-bar"><a class="button" href="tambah.php">+ Tambah alat</a></div>
<section class="page-section"><div class="table-wrap"><table>
    <tr>
        <th>ID</th>
        <th>Nama Alat</th>
        <th>Kategori</th>
        <th>Merk</th>
        <th>Stok</th>
        <th>Kondisi</th>
        <th>Aksi</th>
    </tr>
    <?php foreach ($data_alat as $a): ?>
    <tr>
        <td><?= htmlspecialchars($a['id']) ?></td>
        <td><?= htmlspecialchars($a['nama_alat']) ?></td>
        <td><?= htmlspecialchars($a['nama_kategori'] ?? '-') ?></td>
        <td><?= htmlspecialchars($a['merk']) ?></td>
        <td><?= htmlspecialchars($a['stok']) ?></td>
        <td><?= htmlspecialchars($a['kondisi']) ?></td>
        <td class="table-actions">
            <?php if (!empty($a['active_loan_id'])): ?>
                <?php if (($_SESSION['level'] ?? '') === 'Peminjam'): ?>
                    <form method="post" action="peminjaman.php" style="display:inline;">
                        <input type="hidden" name="pinjam_id" value="<?= (int) $a['active_loan_id'] ?>">
                        <button class="button secondary" type="submit" name="ajukan_pengembalian">Ajukan pengembalian</button>
                    </form>
                <?php else: ?>
                    <a href="pengembalian.php">Pengembalian</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="peminjaman.php?alat_id=<?= (int) $a['id'] ?>">Pinjam</a>
            <?php endif; ?>
            <a href="edit.php?table=alat&id=<?= (int) $a['id'] ?>">Edit</a>
            <a href="hapus.php?table=alat&id=<?= (int) $a['id'] ?>">Hapus</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</div></section></main></div>
</body>
</html