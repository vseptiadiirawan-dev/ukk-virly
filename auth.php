<?php
session_start();
include "koneksi.php";

function user_has_role($allowedRoles = []) {
    $level = $_SESSION['level'] ?? '';
    return $allowedRoles === [] || in_array($level, $allowedRoles, true);
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$role = $_SESSION['level'] ?? 'Peminjam';
$roleMenus = [
    'Admin' => ['dashboard.php', 'tampil1.php', 'kategori.php', 'user.php', 'logaktvs.php'],
    'Petugas' => ['dashboard.php', 'tampil1.php', 'kategori.php', 'logaktvs.php'],
    'Peminjam' => ['dashboard.php', 'tampil1.php']
];

function restrict_to_roles($allowedRoles) {
    if (!user_has_role($allowedRoles)) {
        header('Location: dashboard.php');
        exit;
    }
}

