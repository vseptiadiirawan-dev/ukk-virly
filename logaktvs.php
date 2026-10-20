<?php
include "auth.php";
restrict_to_roles(['Admin', 'Petugas']);
$query = mysqli_query($conn, "SELECT log_aktivitas.*, users.nama 
          FROM log_aktivitas
          LEFT JOIN users ON log_aktivitas.user_id = users.id");
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
    <title>Log Aktivitas</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell"><aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div><div class="menu-label">Menu utama</div>
    <nav>
        <?php foreach ($menu_items[$_SESSION['level'] ?? 'Peminjam'] as $item): ?>
            <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
            <a class="<?= $page === 'logaktvs.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer"><span>Masuk sebagai</span><strong><?= htmlspecialchars($_SESSION['nama']) ?></strong><a class="logout" href="logout.php">Keluar dari akun</a></div>
</aside><main>
<div class="topbar"><div><p class="eyebrow">Riwayat sistem</p><h1>Log Aktivitas</h1><p class="welcome">Pantau aktivitas yang terjadi di dalam sistem.</p></div><div class="date-chip"><?= date('d M Y') ?></div></div>
<section class="page-section"><div class="table-wrap"><table>
    <tr>
        <th>ID</th>
        <th>Pengguna</th>
        <th>Aktivitas</th>
        <th>Waktu</th>
    </tr>
    <?php foreach ($data as $l): ?>
    <tr>
        <td><?= htmlspecialchars($l['id']) ?></td>
        <td><?= htmlspecialchars($l['nama'] ?? '-') ?></td>
        <td><?= htmlspecialchars($l['aktivitas']) ?></td>
        <td><?= htmlspecialchars($l['waktu']) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
</div></section></main></div>
</body>
</html>