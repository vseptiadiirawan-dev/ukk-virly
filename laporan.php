<?php
include "auth.php";

restrict_to_roles(['Admin', 'Petugas']);

$userLevel = $_SESSION['level'] ?? 'Peminjam';

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
    ]
];

$summaryQuery = mysqli_query($conn, "
    SELECT
        COUNT(*) AS total_transaksi,
        SUM(CASE WHEN status = 'dikembalikan' THEN 1 ELSE 0 END) AS total_dikembalikan,
        SUM(CASE WHEN status IN ('dipinjam', 'menunggu_verifikasi') AND tanggal_jatuh_tempo IS NOT NULL AND tanggal_jatuh_tempo < NOW() THEN 1 ELSE 0 END) AS total_terlambat,
        SUM(CASE
            WHEN status IN ('dipinjam', 'menunggu_verifikasi') AND tanggal_jatuh_tempo IS NOT NULL AND tanggal_jatuh_tempo < NOW()
                THEN GREATEST(denda, CEIL(TIMESTAMPDIFF(SECOND, tanggal_jatuh_tempo, NOW()) / 86400) * 5000)
            ELSE denda
        END) AS total_denda
    FROM peminjaman
");
$summary = $summaryQuery ? mysqli_fetch_assoc($summaryQuery) : [
    'total_transaksi' => 0,
    'total_dikembalikan' => 0,
    'total_terlambat' => 0,
    'total_denda' => 0
];

$rowsQuery = mysqli_query($conn, "
    SELECT p.*, u.nama AS nama_user, a.nama_alat
    FROM peminjaman p
    LEFT JOIN users u ON u.id = p.user_id
    LEFT JOIN alat a ON a.id = p.alat_id
    ORDER BY p.id DESC
");
$rows = $rowsQuery ? mysqli_fetch_all($rowsQuery, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Laporan</title>
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
                <a class="<?= $page === 'laporan.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
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
                <p class="eyebrow">Ringkasan</p>
                <h1>Laporan Transaksi</h1>
                <p class="welcome">Semua peminjaman, pengembalian, dan denda yang tercatat.</p>
            </div>
            <div class="date-chip"><?= date('d M Y') ?></div>
        </div>

        <section class="cards">
            <div class="card">
                <div class="card-label">Total transaksi</div>
                <strong><?= (int) ($summary['total_transaksi'] ?? 0) ?></strong>
            </div>
            <div class="card">
                <div class="card-label">Sudah dikembalikan</div>
                <strong><?= (int) ($summary['total_dikembalikan'] ?? 0) ?></strong>
            </div>
            <div class="card">
                <div class="card-label">Terlambat</div>
                <strong><?= (int) ($summary['total_terlambat'] ?? 0) ?></strong>
            </div>
            <div class="card">
                <div class="card-label">Total denda</div>
                <strong>Rp <?= number_format((int) ($summary['total_denda'] ?? 0), 0, ',', '.') ?></strong>
            </div>
        </section>

        <section class="page-section">
            <div class="section-heading">
                <h2>Detail laporan</h2>
            </div>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Nama Peminjam</th>
                        <th>Alat</th>
                        <th>Jumlah</th>
                        <th>Pinjam</th>
                        <th>Jatuh Tempo</th>
                        <th>Kembali</th>
                        <th>Denda</th>
                        <th>Status</th>
                    </tr>
                    <?php if (!$rows): ?>
                        <tr><td class="empty" colspan="9">Belum ada data laporan.</td></tr>
                    <?php else: ?>
                        <?php foreach ($rows as $row): ?>
                            <?php
                                $status = $row['status'] ?? 'dipinjam';
                                $denda = (int) ($row['denda'] ?? 0);
                                if (in_array($status, ['dipinjam', 'menunggu_verifikasi'], true) && !empty($row['tanggal_jatuh_tempo'])) {
                                    $deadline = strtotime($row['tanggal_jatuh_tempo']);
                                    if ($deadline !== false && $deadline < time()) {
                                        if ($status === 'dipinjam') {
                                            $status = 'terlambat';
                                        }
                                        $lateDays = max(1, (int) ceil((time() - $deadline) / 86400));
                                        $denda = max($denda, $lateDays * 5000);
                                    }
                                }
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
                                <td><?= (int) $row['jumlah'] ?></td>
                                <td><?= htmlspecialchars($row['tanggal_pinjam'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['tanggal_jatuh_tempo'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($row['tanggal_kembali'] ?? '-') ?></td>
                                <td>Rp <?= number_format($denda, 0, ',', '.') ?></td>
                                <td>
                                    <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars(str_replace('_', ' ', $status)) ?></span>
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
