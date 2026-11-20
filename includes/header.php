<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Sistem Informasi Perpustakaan';
$user = currentUser();
$pathPrefix = strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/pages/') !== false ? '../' : '';
$currentPage = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - Perpustakaan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= $pathPrefix ?>assets/css/app.css" rel="stylesheet">
</head>
<body>
<div class="app-shell">
    <div class="sidebar-backdrop" data-sidebar-close></div>
    <aside class="sidebar">
        <div class="sidebar-heading">
            <a class="brand" href="<?= $pathPrefix ?>index.php"><i class="bi bi-bookshelf"></i><span>Perpustakaan</span></a>
            <button class="sidebar-close" type="button" aria-label="Tutup menu" data-sidebar-close><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="sidebar-context">
            <span class="sidebar-context-label"><?= $user['role'] === 'ADMIN' ? 'Administrator' : ($user['role'] === 'ANGGOTA' ? 'Anggota Perpustakaan' : 'Petugas Cabang') ?></span>
            <strong><?= htmlspecialchars((string) ($user['role'] === 'ANGGOTA' ? ($user['nama_cabang_anggota'] ?: 'Anggota') : ($user['nama_cabang'] ?: 'Semua Cabang')), ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
        <nav class="nav flex-column mt-4">
            <a class="nav-link <?= $currentPage === 'index' ? 'active' : '' ?>" href="<?= $pathPrefix ?>index.php"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
            <?php if ($user !== null && $user['role'] === 'ANGGOTA'): ?>
                <div class="nav-section">AREA ANGGOTA</div>
                <a class="nav-link <?= $currentPage === 'katalog' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/katalog.php"><i class="bi bi-book"></i> Katalog Buku</a>
                <a class="nav-link <?= $currentPage === 'riwayat_anggota' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/riwayat_anggota.php"><i class="bi bi-clock-history"></i> Riwayat Peminjaman</a>
                <a class="nav-link <?= $currentPage === 'pengajuan_anggota' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/pengajuan_anggota.php"><i class="bi bi-send"></i> Pengajuan Saya</a>
                <a class="nav-link <?= $currentPage === 'denda_anggota' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/denda_anggota.php"><i class="bi bi-cash-coin"></i> Denda Saya</a>
            <?php else: ?>
            <div class="nav-section">OPERASIONAL</div>
            <a class="nav-link <?= $currentPage === 'buku' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/buku.php"><i class="bi bi-book"></i> Buku</a>
            <a class="nav-link <?= $currentPage === 'kategori' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/kategori.php"><i class="bi bi-tags"></i> Kategori</a>
            <a class="nav-link <?= $currentPage === 'anggota' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/anggota.php"><i class="bi bi-people"></i> Anggota</a>
            <a class="nav-link <?= $currentPage === 'stok' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/stok.php"><i class="bi bi-boxes"></i> Stok</a>
            <a class="nav-link <?= $currentPage === 'peminjaman' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/peminjaman.php"><i class="bi bi-arrow-left-right"></i> Peminjaman</a>
            <a class="nav-link <?= $currentPage === 'pengajuan_petugas' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/pengajuan_petugas.php"><i class="bi bi-send-check"></i> Pengajuan Anggota</a>
            <a class="nav-link <?= $currentPage === 'pengembalian' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/pengembalian.php"><i class="bi bi-box-arrow-in-down"></i> Pengembalian</a>
            <a class="nav-link <?= $currentPage === 'denda' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/denda.php"><i class="bi bi-cash-coin"></i> Denda</a>
            <div class="nav-section">LAPORAN</div>
            <a class="nav-link <?= $currentPage === 'laporan' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/laporan.php"><i class="bi bi-bar-chart"></i> Laporan</a>
            <?php if ($user !== null && $user['role'] === 'ADMIN'): ?>
                <div class="nav-section">ADMINISTRASI</div>
                <a class="nav-link <?= $currentPage === 'cabang' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/cabang.php"><i class="bi bi-building"></i> Cabang</a>
                <a class="nav-link <?= $currentPage === 'petugas' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/petugas.php"><i class="bi bi-person-badge"></i> Petugas</a>
                <a class="nav-link <?= $currentPage === 'akun' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/akun.php"><i class="bi bi-person-gear"></i> Akun Pengguna</a>
                <a class="nav-link <?= $currentPage === 'pengaturan' ? 'active' : '' ?>" href="<?= $pathPrefix ?>pages/pengaturan.php"><i class="bi bi-sliders"></i> Pengaturan</a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>
    </aside>
    <main class="main-content">
        <header class="topbar">
            <div class="topbar-title">
                <button class="sidebar-toggle" type="button" aria-label="Buka menu" data-sidebar-toggle><i class="bi bi-list"></i></button>
                <div>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if ($user !== null && ($user['nama_cabang'] || $user['nama_cabang_anggota'])): ?>
                    <small class="text-muted"><?= htmlspecialchars((string) ($user['nama_cabang'] ?: $user['nama_cabang_anggota']), ENT_QUOTES, 'UTF-8') ?></small>
                <?php endif; ?>
                </div>
            </div>
            <div class="dropdown">
                <button class="btn user-menu dropdown-toggle" data-bs-toggle="dropdown" type="button">
                    <span class="avatar"><?= strtoupper(substr((string) $user['username'], 0, 1)) ?></span>
                    <span><?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text"><span class="badge text-bg-<?= $user['role'] === 'ADMIN' ? 'primary' : ($user['role'] === 'ANGGOTA' ? 'info' : 'success') ?>"><?= htmlspecialchars((string) $user['role'], ENT_QUOTES, 'UTF-8') ?></span></span></li>
                    <li><a class="dropdown-item" href="<?= $pathPrefix ?>pages/profil.php"><i class="bi bi-person-circle me-2"></i>Profil Saya</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= $pathPrefix ?>logout.php"><i class="bi bi-box-arrow-right me-2"></i>Keluar</a></li>
                </ul>
            </div>
        </header>
        <div class="content-area">
