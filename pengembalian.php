<?php
include "auth.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$ensurePeminjamanTable = function () use ($conn) {
    $result = mysqli_query($conn, "SHOW COLUMNS FROM peminjaman");
    if (!$result) {
        return;
    }

    $columns = mysqli_fetch_all($result, MYSQLI_ASSOC);
    $fieldNames = [];
    foreach ($columns as $column) {
        $fieldNames[] = $column['Field'];
    }

    $requiredColumns = [
        'user_id' => 'INT NOT NULL',
        'alat_id' => 'INT NOT NULL',
        'jumlah' => 'INT NOT NULL DEFAULT 1',
        'tanggal_pinjam' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'tanggal_jatuh_tempo' => 'DATETIME NULL',
        'tanggal_kembali' => 'DATETIME NULL',
        'status' => 'VARCHAR(20) NOT NULL DEFAULT "dipinjam"',
        'keterangan' => 'TEXT NULL',
        'denda' => 'INT NOT NULL DEFAULT 0',
        'persetujuan_admin' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'persetujuan_petugas' => 'TINYINT(1) NOT NULL DEFAULT 0'
    ];

    foreach ($requiredColumns as $field => $definition) {
        if (!in_array($field, $fieldNames, true)) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD COLUMN `$field` $definition");
        }
    }

    $tableResult = mysqli_query($conn, "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('users', 'alat')");
    $existingTables = $tableResult ? array_column(mysqli_fetch_all($tableResult, MYSQLI_ASSOC), 'TABLE_NAME') : [];
    $foreignKeys = [
        'fk_peminjaman_user' => ['user_id', 'users'],
        'fk_peminjaman_alat' => ['alat_id', 'alat']
    ];

    foreach ($foreignKeys as $constraint => [$column, $parentTable]) {
        if (!in_array($parentTable, $existingTables, true)) {
            continue;
        }

        $existingKey = mysqli_query($conn, "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'peminjaman' AND COLUMN_NAME = '$column' AND REFERENCED_TABLE_NAME = '$parentTable' LIMIT 1");
        if ($existingKey && mysqli_num_rows($existingKey) === 0) {
            mysqli_query($conn, "ALTER TABLE peminjaman ADD CONSTRAINT `$constraint` FOREIGN KEY (`$column`) REFERENCES `$parentTable` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT");
        }
    }
};

$ensurePeminjamanTable();

$userLevel = $_SESSION['level'] ?? 'Peminjam';
if ($userLevel === 'Peminjam') {
    $query = mysqli_query($conn, "SELECT p.*, u.nama AS nama_user, a.nama_alat, a.kondisi AS kondisi_alat FROM peminjaman p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN alat a ON a.id = p.alat_id WHERE p.user_id = " . (int) $_SESSION['user_id'] . " ORDER BY p.id DESC");
} else {
    $query = mysqli_query($conn, "SELECT p.*, u.nama AS nama_user, a.nama_alat, a.kondisi AS kondisi_alat FROM peminjaman p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN alat a ON a.id = p.alat_id ORDER BY p.id DESC");
}
$rows = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];

$menu_items = [
    'Admin' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['user.php', '♙', 'Pengguna'],
        ['peminjaman.php', '◍', 'Peminjaman'],
        ['pengembalian.php', '↺', 'Pengembalian'],
        ['laporan.php', '◫', 'Laporan'],
        ['logaktvs.php', '◷', 'Log Aktivitas']
    ],
    'Petugas' => [
        ['dashboard.php', '▦', 'Dashboard'],
        ['tampil1.php', '▣', 'Data Alat'],
        ['kategori.php', '◈', 'Kategori'],
        ['peminjaman.php', '◍', 'Peminjaman'],
        ['pengembalian.php', '↺', 'Pengembalian'],
        ['laporan.php', '◫', 'Laporan'],
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
    <title>Pengembalian</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div>
        <div class="menu-label">Menu utama</div>
        <nav>
            <?php foreach ($menu_items[$userLevel] as $item): ?>
                <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
                <a class="<?= $page === 'pengembalian.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
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
                <p class="eyebrow">Transaksi</p>
                <h1>Pengembalian</h1>
                <p class="welcome">Riwayat pengembalian alat dan status barang.</p>
            </div>
            <div class="date-chip"><?= date('d M Y') ?></div>
        </div>

        <section class="page-section">
            <div class="section-heading"><h2>Data pengembalian</h2></div>
            <div class="table-wrap">
                <table class="loan-table">
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Alat</th>
                        <th>Kondisi</th>
                        <th>Jumlah</th>
                        <th>Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Kembali</th>
                        <th>Denda</th>
                        <th>Status denda</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                    <?php if (!$rows): ?>
                        <tr><td class="empty" colspan="12">Belum ada data pengembalian.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $status = $row['status'] ?? 'dipinjam';
                                $denda = (int) ($row['denda'] ?? 0);
                                if (in_array($status, ['dipinjam', 'menunggu_verifikasi'], true) && !empty($row['tanggal_jatuh_tempo'])) {
                                    $deadline = strtotime($row['tanggal_jatuh_tempo']);
                                    if ($deadline !== false && $deadline < time()) {
                                        $lateDays = max(1, (int) ceil((time() - $deadline) / 86400));
                                        $denda = max($denda, $lateDays * 5000);
                                        if ($status === 'dipinjam') {
                                            $status = 'terlambat';
                                        }
                                    }
                                }
                                $dendaStatus = $denda > 0 ? 'Denda dikenakan' : 'Tidak ada denda';
                                $dendaStatusClass = $denda > 0 ? 'status-danger' : 'status-clear';
                                $statusClass = 'status-admin';
                                if (in_array($status, ['menunggu_persetujuan', 'menunggu_verifikasi'], true)) {
                                    $statusClass = 'status-pending';
                                } elseif ($status === 'dipinjam') {
                                    $statusClass = 'status-petugas';
                                } elseif ($status === 'terlambat' || $status === 'ditolak') {
                                    $statusClass = 'status-danger';
                                }
                            ?>
                            <tr>
                                <td><?= (int) $row['id'] ?></td>
                                <td><?= htmlspecialchars($row['nama_user'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['nama_alat'] ?? '-') ?></td>
                                <td><span class="condition-badge"><?= htmlspecialchars($row['kondisi_alat'] ?? '-') ?></span></td>
                                <td><?= (int) $row['jumlah'] ?></td>
                                <td><?= htmlspecialchars($row['tanggal_pinjam'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['tanggal_kembali'] ?? '-') ?></td>
                                <td>Rp <?= number_format($denda, 0, ',', '.') ?></td>
                                <td><span class="status-badge <?= $dendaStatusClass ?>"><?= $dendaStatus ?></span></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
                                </td>
                                <td class="table-actions">
                                    <?php if (($row['status'] ?? '') === 'dipinjam' && $userLevel === 'Peminjam'): ?>
                                        <form method="post" action="peminjaman.php">
                                            <input type="hidden" name="pinjam_id" value="<?= (int) $row['id'] ?>">
                                            <button class="button secondary" type="submit" name="ajukan_pengembalian">Ajukan pengembalian</button>
                                        </form>
                                    <?php elseif (($row['status'] ?? '') === 'menunggu_verifikasi' && $userLevel === 'Peminjam'): ?>
                                        <span class="approval-state">Menunggu verifikasi</span>
                                    <?php elseif (($row['status'] ?? '') === 'menunggu_verifikasi' && in_array($userLevel, ['Admin', 'Petugas'], true)): ?>
                                        <form method="post" action="peminjaman.php" class="approval-actions">
                                            <input type="hidden" name="pinjam_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="verifikasi_pengembalian" value="1">
                                            <button class="button" type="submit" name="decision" value="confirm">Konfirmasi diterima</button>
                                            <button class="button danger" type="submit" name="decision" value="reject" onclick="return confirm('Tolak pengajuan dan biarkan pinjaman tetap aktif?')">Tolak</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="muted-action">-</span>
                                    <?php endif; ?>
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
