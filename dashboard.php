<?php
include "auth.php";

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

function jumlah_data($conn, $table) {
    $allowedTables = ['alat', 'kategori', 'users', 'log_aktivitas'];
    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");
    if (!$result) {
        return 0;
    }
    $row = mysqli_fetch_assoc($result);
    return (int) ($row['total'] ?? 0);
}

$userLevel = $_SESSION['level'] ?? 'Peminjam';
$userId = (int) $_SESSION['user_id'];
$total_alat = jumlah_data($conn, 'alat');
$total_kategori = jumlah_data($conn, 'kategori');
$loanFilter = $userLevel === 'Peminjam' ? " WHERE user_id = $userId" : '';
$loanStatsQuery = mysqli_query($conn, "SELECT COUNT(*) AS total, SUM(status IN ('menunggu_persetujuan', 'menunggu_verifikasi')) AS pending, SUM(status = 'dipinjam') AS active, SUM(status = 'dikembalikan') AS returned FROM peminjaman$loanFilter");
$loanStats = $loanStatsQuery ? mysqli_fetch_assoc($loanStatsQuery) : [];

if ($userLevel === 'Peminjam') {
    $dashboardCards = [
        ['Total peminjaman', (int) ($loanStats['total'] ?? 0), 'metric-teal'],
        ['Menunggu proses', (int) ($loanStats['pending'] ?? 0), 'metric-amber'],
        ['Sedang dipinjam', (int) ($loanStats['active'] ?? 0), 'metric-coral'],
        ['Sudah dikembalikan', (int) ($loanStats['returned'] ?? 0), 'metric-green']
    ];
    $activityQuery = mysqli_query($conn, "SELECT p.id, p.status, p.tanggal_pinjam, a.nama_alat FROM peminjaman p LEFT JOIN alat a ON a.id = p.alat_id WHERE p.user_id = $userId ORDER BY p.id DESC LIMIT 5");
    $activities = $activityQuery ? mysqli_fetch_all($activityQuery, MYSQLI_ASSOC) : [];
} else {
    $dashboardCards = [
        ['Total alat', $total_alat, 'metric-teal'],
        ['Kategori', $total_kategori, 'metric-green'],
        ['Menunggu proses', (int) ($loanStats['pending'] ?? 0), 'metric-amber'],
        ['Sedang dipinjam', (int) ($loanStats['active'] ?? 0), 'metric-coral']
    ];
    $log_query = mysqli_query($conn, "SELECT log_aktivitas.*, users.nama FROM log_aktivitas LEFT JOIN users ON log_aktivitas.user_id = users.id ORDER BY log_aktivitas.id DESC LIMIT 5");
    $activities = $log_query ? mysqli_fetch_all($log_query, MYSQLI_ASSOC) : [];
}

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
$allowedPages = array_map(fn($item) => $item[0], $menu_items[$_SESSION['level'] ?? 'Peminjam']);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="app-shell">
<aside class="sidebar">
    <div class="brand"><small>Inventaris</small><h2>AlatKita</h2></div>
    <div class="menu-label">Menu utama</div>
    <nav>
        <?php foreach ($menu_items[$_SESSION['level'] ?? 'Peminjam'] as $item): ?>
            <?php $page = $item[0]; $icon = $item[1]; $label = $item[2]; ?>
            <a class="<?= $page === 'dashboard.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
        <?php endforeach; ?>
    </nav>
    <div class="sidebar-footer">
        <span>Masuk sebagai</span>
        <strong><?= htmlspecialchars($_SESSION['nama']) ?></strong>
        <a class="logout" href="logout.php">Keluar dari akun</a>
    </div>
</aside>

<main class="dashboard-main">
<div class="topbar">
    <div><p class="eyebrow"><?= htmlspecialchars($userLevel) ?> · Ringkasan</p><h1>Dashboard</h1><p class="welcome">Selamat datang kembali, <?= htmlspecialchars($_SESSION['nama']) ?>.</p></div>
    <div class="date-chip"><?= date('d M Y') ?></div>
</div>

<section class="dashboard-intro">
    <div class="dashboard-intro-content"><span class="dashboard-kicker">AlatKita <span>/</span> <?= date('d.m.Y') ?></span><h2><?= $userLevel === 'Peminjam' ? 'Aktivitas peminjaman Anda' : 'Inventaris, dalam kendali.' ?></h2>
        <p><?= $userLevel === 'Peminjam' ? 'Pantau status persetujuan dan barang yang sedang Anda gunakan.' : 'Satu tempat untuk memantau aset dan permintaan yang perlu ditindaklanjuti.' ?></p>
        <div class="dashboard-intro-meta"><span class="live-indicator"></span><?= $userLevel === 'Peminjam' ? 'Akun peminjam aktif' : 'Sistem inventaris aktif' ?></div>
    </div>
    <a class="dashboard-link" href="<?= $userLevel === 'Peminjam' ? 'peminjaman.php' : 'laporan.php' ?>"><span><?= $userLevel === 'Peminjam' ? 'Buka peminjaman' : 'Lihat laporan' ?></span><span class="dashboard-arrow" aria-hidden="true">↗</span></a>
</section>

<div class="cards dashboard-metrics">
    <?php foreach ($dashboardCards as $index => [$label, $value, $tone]): ?>
        <div class="card metric-card <?= $tone ?>"><span class="metric-index">0<?= $index + 1 ?></span><span class="card-label"><?= htmlspecialchars($label) ?></span><strong><?= (int) $value ?></strong></div>
    <?php endforeach; ?>
</div>

<nav class="dashboard-shortcuts" aria-label="Akses cepat">
    <a href="tampil1.php"><span class="shortcut-index">01</span><span class="shortcut-copy"><strong>Data alat</strong><small>Jelajahi inventaris</small></span><span class="shortcut-arrow" aria-hidden="true">↗</span></a>
    <a href="peminjaman.php"><span class="shortcut-index">02</span><span class="shortcut-copy"><strong><?= $userLevel === 'Peminjam' ? 'Ajukan pinjaman' : 'Persetujuan' ?></strong><small><?= $userLevel === 'Peminjam' ? 'Ajukan alat yang dibutuhkan' : 'Tinjau permintaan baru' ?></small></span><span class="shortcut-arrow" aria-hidden="true">↗</span></a>
    <a href="<?= $userLevel === 'Peminjam' ? 'pengembalian.php' : 'laporan.php' ?>"><span class="shortcut-index">03</span><span class="shortcut-copy"><strong><?= $userLevel === 'Peminjam' ? 'Pengembalian' : 'Laporan' ?></strong><small><?= $userLevel === 'Peminjam' ? 'Lihat riwayat barang' : 'Ringkasan transaksi' ?></small></span><span class="shortcut-arrow" aria-hidden="true">↗</span></a>
</nav>

<section class="page-section dashboard-activity">
    <div class="section-heading"><h2><?= $userLevel === 'Peminjam' ? 'Peminjaman terbaru' : 'Aktivitas terbaru' ?></h2><a href="<?= $userLevel === 'Peminjam' ? 'peminjaman.php' : 'logaktvs.php' ?>">Lihat semua <span aria-hidden="true">→</span></a></div>
    <div class="table-wrap"><table class="dashboard-table">
        <?php if ($userLevel === 'Peminjam'): ?>
            <tr><th>Alat</th><th>Tanggal pinjam</th><th>Status</th></tr>
            <?php if (!$activities): ?><tr><td class="empty" colspan="3">Belum ada peminjaman.</td></tr><?php endif; ?>
            <?php foreach ($activities as $activity): ?>
                <tr><td><?= htmlspecialchars($activity['nama_alat'] ?? '-') ?></td><td><?= htmlspecialchars($activity['tanggal_pinjam'] ?? '-') ?></td><td><span class="status-badge <?= in_array($activity['status'] ?? '', ['menunggu_persetujuan', 'menunggu_verifikasi'], true) ? 'status-pending' : (($activity['status'] ?? '') === 'ditolak' ? 'status-danger' : 'status-admin') ?>"><?= htmlspecialchars(str_replace('_', ' ', $activity['status'] ?? '')) ?></span></td></tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><th>Pengguna</th><th>Aktivitas</th><th>Waktu</th></tr>
            <?php if (!$activities): ?><tr><td class="empty" colspan="3">Belum ada aktivitas.</td></tr><?php endif; ?>
            <?php foreach ($activities as $activity): ?>
                <tr><td><?= htmlspecialchars($activity['nama'] ?? '-') ?></td><td><?= htmlspecialchars($activity['aktivitas']) ?></td><td><?= htmlspecialchars($activity['waktu']) ?></td></tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </table></div>
</section>
</main>
</div>
</body>
</html>
