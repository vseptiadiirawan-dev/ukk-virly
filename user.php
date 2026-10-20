<?php
include "auth.php";
restrict_to_roles(['Admin']);
$query = mysqli_query($conn, "SELECT * FROM users ORDER BY id ASC");
$users = mysqli_fetch_all($query, MYSQLI_ASSOC);
$menu_items = [
    'Admin' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['user.php', '♙', 'Pengguna'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ]
];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Pengguna</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div>
        <div class="menu-label">Menu utama</div>
        <nav>
            <?php foreach ($menu_items[$_SESSION['level'] ?? 'Admin'] as $item): ?>
                <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
                <a class="<?= $page === 'user.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <span>Masuk sebagai</span>
            <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>
            <a class="logout" href="logout.php">Keluar dari akun</a>
        </div>
    </aside>

    <main>
        <div class="topbar">
            <div>
                <p class="eyebrow">Manajemen akses</p>
                <h1>Data Pengguna</h1>
                <p class="welcome">Kelola akun pengguna dan level akses sistem.</p>
            </div>
            <div class="date-chip"><?= date('d M Y') ?></div>
        </div>

        <div class="action-bar">
            <a class="button" href="tambah.php?table=users">+ Tambah pengguna</a>
        </div>

        <section class="page-section">
            <div class="table-wrap">
                <table class="user-table">
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Username</th>
                        <th>Password</th>
                        <th>Level</th>
                        <th>Aksi</th>
                    </tr>

                    <?php if (!$users): ?>
                        <tr><td class="empty" colspan="6">Belum ada data pengguna.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $row): ?>
                            <?php
                                $level = htmlspecialchars($row['level'] ?? 'Peminjam');
                                $badgeClass = 'status-peminjam';
                                if ($level === 'Admin') $badgeClass = 'status-admin';
                                elseif ($level === 'Petugas') $badgeClass = 'status-petugas';
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($row['id']) ?></td>
                                <td><?= htmlspecialchars($row['nama']) ?></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td class="password-cell">••••••••</td>
                                <td><span class="status-badge <?= $badgeClass ?>"><?= $level ?></span></td>
                                <td class="table-actions">
                                    <a class="btn-edit" href="edit.php?table=users&id=<?= (int) $row['id'] ?>">Edit</a>
                                    <a class="btn-delete" href="hapus.php?table=users&id=<?= (int) $row['id'] ?>">Hapus</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>