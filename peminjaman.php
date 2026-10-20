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
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_peminjaman'])) {
    $pinjamId = (int) ($_POST['pinjam_id'] ?? 0);
    $decision = $_POST['decision'] ?? '';

    if (!in_array($userLevel, ['Admin', 'Petugas'], true) || !in_array($decision, ['approve', 'reject'], true) || $pinjamId <= 0) {
        $error = 'Aksi persetujuan tidak valid.';
    } else {
        mysqli_begin_transaction($conn);
        $stmt = mysqli_prepare($conn, 'SELECT id, alat_id, jumlah, `status`, persetujuan_admin, persetujuan_petugas FROM peminjaman WHERE id = ? FOR UPDATE');
        if (!$stmt) {
            mysqli_rollback($conn);
            $error = 'Gagal menyiapkan persetujuan peminjaman.';
        } else {
            mysqli_stmt_bind_param($stmt, 'i', $pinjamId);
            mysqli_stmt_execute($stmt);
            $request = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

            if (!$request || $request['status'] !== 'menunggu_persetujuan') {
                mysqli_rollback($conn);
                $error = 'Permintaan ini tidak ditemukan atau sudah diproses.';
            } elseif ($decision === 'reject') {
                $restoreStock = mysqli_prepare($conn, 'UPDATE alat SET stok = stok + ? WHERE id = ?');
                $rejectRequest = mysqli_prepare($conn, 'UPDATE peminjaman SET `status` = ? WHERE id = ?');
                $rejectedStatus = 'ditolak';

                if (!$restoreStock || !$rejectRequest) {
                    mysqli_rollback($conn);
                    $error = 'Gagal menyiapkan penolakan peminjaman.';
                } else {
                    mysqli_stmt_bind_param($restoreStock, 'ii', $request['jumlah'], $request['alat_id']);
                    mysqli_stmt_bind_param($rejectRequest, 'si', $rejectedStatus, $pinjamId);
                    if (mysqli_stmt_execute($restoreStock) && mysqli_stmt_execute($rejectRequest)) {
                        mysqli_commit($conn);
                        $success = 'Permintaan peminjaman ditolak dan stok dikembalikan.';
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Gagal menolak permintaan peminjaman.';
                    }
                }
            } else {
                $approvalColumn = $userLevel === 'Admin' ? 'persetujuan_admin' : 'persetujuan_petugas';
                if ((int) $request[$approvalColumn] === 1) {
                    mysqli_rollback($conn);
                    $error = 'Persetujuan dari peran Anda sudah tercatat.';
                } else {
                    $approveRequest = mysqli_prepare($conn, "UPDATE peminjaman SET `$approvalColumn` = 1 WHERE id = ?");
                    if (!$approveRequest) {
                        mysqli_rollback($conn);
                        $error = 'Gagal menyiapkan persetujuan peminjaman.';
                    } else {
                        mysqli_stmt_bind_param($approveRequest, 'i', $pinjamId);
                        $approvedByAdmin = $userLevel === 'Admin' || (int) $request['persetujuan_admin'] === 1;
                        $approvedByPetugas = $userLevel === 'Petugas' || (int) $request['persetujuan_petugas'] === 1;
                        $approved = mysqli_stmt_execute($approveRequest);

                        if ($approved && $approvedByAdmin && $approvedByPetugas) {
                            $activateRequest = mysqli_prepare($conn, "UPDATE peminjaman SET `status` = 'dipinjam', tanggal_pinjam = NOW(), tanggal_jatuh_tempo = DATE_ADD(NOW(), INTERVAL 7 DAY) WHERE id = ?");
                            if ($activateRequest) {
                                mysqli_stmt_bind_param($activateRequest, 'i', $pinjamId);
                                $approved = mysqli_stmt_execute($activateRequest);
                            } else {
                                $approved = false;
                            }
                        }

                        if ($approved) {
                            mysqli_commit($conn);
                            $success = $approvedByAdmin && $approvedByPetugas
                                ? 'Peminjaman disetujui Admin dan Petugas. Masa pinjam telah dimulai.'
                                : 'Persetujuan tercatat. Menunggu persetujuan peran lainnya.';
                        } else {
                            mysqli_rollback($conn);
                            $error = 'Gagal menyimpan persetujuan peminjaman.';
                        }
                    }
                }
            }
        }
    }
}

$currentUserId = (int) $_SESSION['user_id'];
$requestReturn = function ($pinjamId) use ($conn, $userLevel, $currentUserId, &$error, &$success) {
    if ($userLevel !== 'Peminjam' || (int) $pinjamId <= 0) {
        $error = 'Pengajuan pengembalian tidak valid.';
        return;
    }

    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, 'SELECT user_id, `status` FROM peminjaman WHERE id = ? FOR UPDATE');
    if (!$stmt) {
        mysqli_rollback($conn);
        $error = 'Gagal menyiapkan pengajuan pengembalian.';
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $pinjamId);
    mysqli_stmt_execute($stmt);
    $pinjamData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$pinjamData || (int) $pinjamData['user_id'] !== $currentUserId || $pinjamData['status'] !== 'dipinjam') {
        mysqli_rollback($conn);
        $error = 'Peminjaman tidak ditemukan, bukan milik Anda, atau tidak sedang dipinjam.';
        return;
    }

    $update = mysqli_prepare($conn, "UPDATE peminjaman SET `status` = 'menunggu_verifikasi' WHERE id = ?");
    if (!$update) {
        mysqli_rollback($conn);
        $error = 'Gagal menyiapkan status pengembalian.';
        return;
    }
    mysqli_stmt_bind_param($update, 'i', $pinjamId);
    if (mysqli_stmt_execute($update)) {
        mysqli_commit($conn);
        $success = 'Pengembalian diajukan. Tunggu verifikasi Admin atau Petugas.';
    } else {
        mysqli_rollback($conn);
        $error = 'Pengajuan pengembalian gagal disimpan.';
    }
};

$processReturn = function ($pinjamId, $decision) use ($conn, $userLevel, &$error, &$success) {
    if ((int) $pinjamId <= 0) {
        $error = 'Data peminjaman tidak valid.';
        return;
    }
    if (!in_array($userLevel, ['Admin', 'Petugas'], true) || !in_array($decision, ['confirm', 'reject'], true)) {
        $error = 'Hanya Admin atau Petugas yang dapat memverifikasi pengembalian.';
        return;
    }

    mysqli_begin_transaction($conn);
    $stmt = mysqli_prepare($conn, 'SELECT * FROM peminjaman WHERE id = ? FOR UPDATE');
    if (!$stmt) {
        mysqli_rollback($conn);
        $error = 'Gagal menyiapkan data pengembalian.';
        return;
    }
    mysqli_stmt_bind_param($stmt, 'i', $pinjamId);
    mysqli_stmt_execute($stmt);
    $pinjamData = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if (!$pinjamData) {
        mysqli_rollback($conn);
        $error = 'Data peminjaman tidak ditemukan.';
        return;
    }

    if ($pinjamData['status'] !== 'menunggu_verifikasi') {
        mysqli_rollback($conn);
        $error = 'Pengajuan ini tidak ditemukan atau sudah diverifikasi.';
        return;
    }

    if ($decision === 'reject') {
        $reject = mysqli_prepare($conn, "UPDATE peminjaman SET `status` = 'dipinjam' WHERE id = ?");
        if ($reject) {
            mysqli_stmt_bind_param($reject, 'i', $pinjamId);
        }
        if ($reject && mysqli_stmt_execute($reject)) {
            mysqli_commit($conn);
            $success = 'Pengajuan pengembalian ditolak. Status pinjaman dikembalikan menjadi aktif.';
        } else {
            mysqli_rollback($conn);
            $error = 'Pengajuan pengembalian gagal ditolak.';
        }
        return;
    }

    $newQty = (int) $pinjamData['jumlah'];
    $denda = 0;
    if (!empty($pinjamData['tanggal_jatuh_tempo'])) {
        $deadline = strtotime($pinjamData['tanggal_jatuh_tempo']);
        $now = time();
        if ($deadline !== false && $deadline < $now) {
            $daysLate = max(1, (int) ceil(($now - $deadline) / 86400));
            $denda = $daysLate * 5000;
        }
    }

    $updateStok = mysqli_prepare($conn, 'UPDATE alat SET stok = stok + ? WHERE id = ?');
    $status = 'dikembalikan';
    $updatePinjam = mysqli_prepare($conn, 'UPDATE peminjaman SET `status` = ?, tanggal_kembali = NOW(), denda = ? WHERE id = ?');
    if (!$updateStok || !$updatePinjam) {
        mysqli_rollback($conn);
        $error = 'Gagal menyiapkan transaksi pengembalian.';
        return;
    }

    mysqli_stmt_bind_param($updateStok, 'ii', $newQty, $pinjamData['alat_id']);
    mysqli_stmt_bind_param($updatePinjam, 'sii', $status, $denda, $pinjamId);
    if (!mysqli_stmt_execute($updateStok) || !mysqli_stmt_execute($updatePinjam)) {
        mysqli_rollback($conn);
        $error = 'Pengembalian gagal disimpan. Silakan coba lagi.';
        return;
    }

    mysqli_commit($conn);
    $success = $denda > 0 ? 'Pengembalian berhasil diproses. Denda keterlambatan: Rp ' . number_format($denda, 0, ',', '.') : 'Pengembalian berhasil diproses.';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pinjam'])) {
    $alatId = (int) ($_POST['alat_id'] ?? 0);
    $jumlah = (int) ($_POST['jumlah'] ?? 1);
    $keterangan = trim($_POST['keterangan'] ?? '');

    if ($alatId <= 0 || $jumlah <= 0) {
        $error = 'Pilih alat dan jumlah pinjaman yang valid.';
    } else {
        $alat = mysqli_query($conn, "SELECT * FROM alat WHERE id = $alatId LIMIT 1");
        $alatData = $alat ? mysqli_fetch_assoc($alat) : null;

        if (!$alatData) {
            $error = 'Alat tidak ditemukan.';
        } elseif ($jumlah > (int) $alatData['stok']) {
            $error = 'Stok alat tidak mencukupi. Tersedia ' . (int) $alatData['stok'] . ' unit.';
        } else {
            $userId = (int) $_SESSION['user_id'];
            $status = 'menunggu_persetujuan';
            mysqli_begin_transaction($conn);

            $reserveStock = mysqli_prepare($conn, 'UPDATE alat SET stok = stok - ? WHERE id = ? AND stok >= ?');
            $stmt = mysqli_prepare($conn, 'INSERT INTO peminjaman (user_id, alat_id, jumlah, tanggal_pinjam, `status`, `keterangan`, persetujuan_admin, persetujuan_petugas) VALUES (?, ?, ?, NOW(), ?, ?, 0, 0)');

            if (!$reserveStock || !$stmt) {
                mysqli_rollback($conn);
                $error = 'Gagal menyiapkan permintaan peminjaman.';
            } else {
                mysqli_stmt_bind_param($reserveStock, 'iii', $jumlah, $alatId, $jumlah);
                mysqli_stmt_execute($reserveStock);

                if (mysqli_stmt_affected_rows($reserveStock) !== 1) {
                    mysqli_rollback($conn);
                    $error = 'Stok alat baru saja berubah atau tidak mencukupi. Silakan coba lagi.';
                } else {
                    mysqli_stmt_bind_param($stmt, 'iiiss', $userId, $alatId, $jumlah, $status, $keterangan);
                    if (mysqli_stmt_execute($stmt)) {
                        mysqli_commit($conn);
                        $success = 'Permintaan peminjaman dicatat. Menunggu persetujuan Admin dan Petugas.';
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Gagal memproses peminjaman: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajukan_pengembalian'])) {
    $requestReturn((int) ($_POST['pinjam_id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifikasi_pengembalian'])) {
    $processReturn((int) ($_POST['pinjam_id'] ?? 0), $_POST['decision'] ?? '');
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

if ($userLevel === 'Admin' || $userLevel === 'Petugas') {
    $query = mysqli_query($conn, "SELECT p.*, u.nama AS nama_user, a.nama_alat, a.kondisi AS kondisi_alat FROM peminjaman p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN alat a ON a.id = p.alat_id ORDER BY p.id DESC");
} else {
    $userId = (int) $_SESSION['user_id'];
    $query = mysqli_query($conn, "SELECT p.*, u.nama AS nama_user, a.nama_alat, a.kondisi AS kondisi_alat FROM peminjaman p LEFT JOIN users u ON u.id = p.user_id LEFT JOIN alat a ON a.id = p.alat_id WHERE p.user_id = $userId ORDER BY p.id DESC");
}
$transactions = $query ? mysqli_fetch_all($query, MYSQLI_ASSOC) : [];

$alatListQuery = mysqli_query($conn, "SELECT * FROM alat WHERE stok > 0 ORDER BY nama_alat ASC");
$alatList = $alatListQuery ? mysqli_fetch_all($alatListQuery, MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Peminjaman</title>
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
                <a class="<?= $page === 'peminjaman.php' ? 'active' : '' ?>" href="<?= $page ?>"><span class="nav-icon"><?= $icon ?></span><?= $label ?></a>
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
                <h1>Peminjaman</h1>
                <p class="welcome">Kelola peminjaman alat dan pengembalian barang.</p>
            </div>
            <div class="date-chip"><?= date('d M Y') ?></div>
        </div>

        <?php if ($error): ?><div class="alert"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

        <section class="page-section form-card">
            <div class="section-heading"><h2>Pinjam alat</h2></div>
            <form method="post" class="form-grid">
                <div class="field">
                    <label>Nama alat</label>
                    <select name="alat_id" required>
                        <option value="">-- Pilih alat --</option>
                        <?php foreach ($alatList as $alat): ?>
                            <option value="<?= (int) $alat['id'] ?>"><?= htmlspecialchars($alat['nama_alat']) ?> (stok: <?= (int) $alat['stok'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label>Jumlah</label>
                    <input type="number" name="jumlah" min="1" value="1" required>
                </div>
                <div class="field full">
                    <label>Keterangan</label>
                    <textarea name="keterangan" placeholder="Contoh: dipakai untuk kegiatan lab, kelas, dll."></textarea>
                </div>
                <div class="field full form-actions">
                    <button class="button" type="submit" name="pinjam">Pinjam</button>
                </div>
            </form>
        </section>

        <section class="page-section">
            <div class="section-heading"><h2>Daftar peminjaman</h2></div>
            <div class="table-wrap">
                <table class="loan-table">
                    <tr>
                        <th>ID</th>
                        <th>Nama</th>
                        <th>Alat</th>
                        <th>Kondisi</th>
                        <th>Jumlah</th>
                        <th>Pinjam</th>
                        <th>Jatuh tempo</th>
                        <th>Kembali</th>
                        <th>Denda</th>
                        <th>Status denda</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                    <?php if (!$transactions): ?>
                        <tr><td class="empty" colspan="12">Belum ada data peminjaman.</td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $row): ?>
                            <?php
                                $status = $row['status'] ?? 'dipinjam';
                                $denda = (int) ($row['denda'] ?? 0);
                                if (in_array($status, ['dipinjam', 'menunggu_verifikasi'], true) && !empty($row['tanggal_jatuh_tempo'])) {
                                    $deadline = strtotime($row['tanggal_jatuh_tempo']);
                                    if ($deadline !== false && $deadline < time()) {
                                        if ($status === 'dipinjam') {
                                            $status = 'terlambat';
                                        }
                                        $daysLate = max(1, (int) ceil((time() - $deadline) / 86400));
                                        $denda = max($denda, $daysLate * 5000);
                                    }
                                }
                                $dendaStatus = $denda > 0 ? 'Denda dikenakan' : 'Tidak ada denda';
                                $dendaStatusClass = $denda > 0 ? 'status-danger' : 'status-clear';
                                $statusClass = 'status-admin';
                                if ($status === 'menunggu_persetujuan') {
                                    $statusClass = 'status-pending';
                                } elseif ($status === 'menunggu_verifikasi') {
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
                                    <?php if (($row['status'] ?? '') === 'menunggu_persetujuan'): ?>
                                        <div class="approval-track">
                                            <span class="approval-item"><strong>Admin</strong><span class="approval-state <?= (int) $row['persetujuan_admin'] === 1 ? 'approved' : '' ?>"><?= (int) $row['persetujuan_admin'] === 1 ? 'Disetujui' : 'Menunggu' ?></span></span>
                                            <span class="approval-item"><strong>Petugas</strong><span class="approval-state <?= (int) $row['persetujuan_petugas'] === 1 ? 'approved' : '' ?>"><?= (int) $row['persetujuan_petugas'] === 1 ? 'Disetujui' : 'Menunggu' ?></span></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="table-actions">
                                    <?php if (($row['status'] ?? '') === 'menunggu_persetujuan' && in_array($userLevel, ['Admin', 'Petugas'], true) && (int) $row[$userLevel === 'Admin' ? 'persetujuan_admin' : 'persetujuan_petugas'] === 0): ?>
                                        <form method="post" class="approval-actions">
                                            <input type="hidden" name="pinjam_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="review_peminjaman" value="1">
                                            <button class="button" type="submit" name="decision" value="approve">Setujui (<?= $userLevel ?>)</button>
                                            <button class="button danger" type="submit" name="decision" value="reject" onclick="return confirm('Tolak permintaan dan kembalikan stok?')">Tolak</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($row['status'] ?? '') === 'dipinjam' && $userLevel === 'Peminjam'): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="pinjam_id" value="<?= (int) $row['id'] ?>">
                                            <button class="button secondary" type="submit" name="ajukan_pengembalian">Ajukan pengembalian</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (($row['status'] ?? '') === 'menunggu_verifikasi' && $userLevel === 'Peminjam'): ?>
                                        <span class="approval-state">Menunggu verifikasi</span>
                                    <?php endif; ?>
                                    <?php if (($row['status'] ?? '') === 'menunggu_verifikasi' && in_array($userLevel, ['Admin', 'Petugas'], true)): ?>
                                        <form method="post" class="approval-actions">
                                            <input type="hidden" name="pinjam_id" value="<?= (int) $row['id'] ?>">
                                            <input type="hidden" name="verifikasi_pengembalian" value="1">
                                            <button class="button" type="submit" name="decision" value="confirm">Konfirmasi diterima</button>
                                            <button class="button danger" type="submit" name="decision" value="reject" onclick="return confirm('Tolak pengajuan dan biarkan pinjaman tetap aktif?')">Tolak</button>
                                        </form>
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
