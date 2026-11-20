<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
requireLogin();

$pdo = getDatabaseConnection();
$user = currentUser();
if ($user['role'] === 'ANGGOTA') {
    require __DIR__ . '/pages/dashboard_anggota.php';
    exit;
}
$isPetugas = $user['role'] === 'PETUGAS';
$branchFilter = $isPetugas ? ' WHERE id_cabang = :id_cabang' : '';
$branchJoinFilter = $isPetugas ? ' WHERE id_cabang = :id_cabang' : '';
$branchParams = $isPetugas ? ['id_cabang' => $user['id_cabang']] : [];

$totalTitles = (int) $pdo->query('SELECT COUNT(*) FROM buku')->fetchColumn();
$totalCopiesStatement = $pdo->prepare('SELECT COALESCE(SUM(jumlah_total), 0) FROM v_stok_cabang' . $branchFilter);
$totalCopiesStatement->execute($branchParams);
$totalCopies = (int) $totalCopiesStatement->fetchColumn();

$memberStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM anggota' . ($isPetugas ? ' WHERE id_cabang_daftar = :id_cabang' : '')
);
$memberStatement->execute($branchParams);
$totalMembers = (int) $memberStatement->fetchColumn();

$branchStatement = $pdo->prepare('SELECT COUNT(*) FROM cabang' . ($isPetugas ? ' WHERE id_cabang = :id_cabang' : ''));
$branchStatement->execute($branchParams);
$totalBranches = (int) $branchStatement->fetchColumn();

$activeLoansStatement = $pdo->prepare(
    'SELECT COUNT(*) FROM peminjaman WHERE status = :status' . ($isPetugas ? ' AND id_cabang = :id_cabang' : '')
);
$activeLoanParams = ['status' => 'DIPINJAM'] + $branchParams;
$activeLoansStatement->execute($activeLoanParams);
$activeLoans = (int) $activeLoansStatement->fetchColumn();

if ($isPetugas) {
    $overdueStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM v_buku_terlambat t
         INNER JOIN peminjaman p ON p.no_transaksi = t.no_transaksi
         WHERE p.id_cabang = :id_cabang'
    );
} else {
    $overdueStatement = $pdo->query('SELECT COUNT(*) FROM v_buku_terlambat');
}
$overdueStatement->execute($branchParams);
$overdueBooks = (int) $overdueStatement->fetchColumn();

$fineSql = "SELECT
                COALESCE(SUM(dn.jumlah_denda), 0) AS total_denda,
                COALESCE(SUM(CASE WHEN dn.status_bayar = 'BELUM_LUNAS' THEN dn.jumlah_denda ELSE 0 END), 0) AS belum_lunas
            FROM denda dn
            INNER JOIN detail_pengembalian dk ON dk.id_detail_pengembalian = dn.id_detail_pengembalian
            INNER JOIN detail_peminjaman dm ON dm.id_detail_peminjaman = dk.id_detail_peminjaman
            INNER JOIN peminjaman p ON p.id_peminjaman = dm.id_peminjaman";
if ($isPetugas) {
    $fineSql .= ' WHERE p.id_cabang = :id_cabang';
}
$fineStatement = $pdo->prepare($fineSql);
$fineStatement->execute($branchParams);
$fineSummary = $fineStatement->fetch() ?: ['total_denda' => 0, 'belum_lunas' => 0];

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="card welcome-card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="small text-white-50 mb-1"><?= $isPetugas ? 'Panel kerja petugas' : 'Panel administrasi' ?></div>
                <h2 class="h4">Selamat datang, <?= htmlspecialchars((string) $user['username'], ENT_QUOTES, 'UTF-8') ?>.</h2>
                <p class="mb-0">Berikut ringkasan kondisi perpustakaan<?= $isPetugas ? ' di cabang Anda' : '' ?>.</p>
            </div>
            <?php if ($isPetugas): ?><a class="btn btn-light" href="pages/peminjaman.php"><i class="bi bi-plus-lg me-1"></i>Peminjaman Baru</a><?php endif; ?>
        </div>
    </div>
    <?php if ($isPetugas): ?>
    <div class="quick-actions mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="h6 mb-0">Akses cepat</h3><span class="small text-muted">Operasional hari ini</span>
        </div>
        <div class="row g-3">
            <div class="col-6 col-md-3"><a class="quick-action-card" href="pages/peminjaman.php"><i class="bi bi-arrow-left-right text-primary"></i><span>Peminjaman</span><small>Buat transaksi</small></a></div>
            <div class="col-6 col-md-3"><a class="quick-action-card" href="pages/pengembalian.php"><i class="bi bi-box-arrow-in-down text-success"></i><span>Pengembalian</span><small>Proses buku kembali</small></a></div>
            <div class="col-6 col-md-3"><a class="quick-action-card" href="pages/denda.php"><i class="bi bi-cash-coin text-warning"></i><span>Bayar denda</span><small>Catat pembayaran</small></a></div>
            <div class="col-6 col-md-3"><a class="quick-action-card" href="pages/stok.php"><i class="bi bi-search text-info"></i><span>Cari stok</span><small>Cek ketersediaan buku</small></a></div>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="row g-4 mb-4">
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body">
            <div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-book"></i></div>
            <div class="stat-label">Total Judul</div><div class="stat-value"><?= number_format($totalTitles) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body">
            <div class="stat-icon bg-success-subtle text-success"><i class="bi bi-collection"></i></div>
            <div class="stat-label">Total Eksemplar</div><div class="stat-value"><?= number_format($totalCopies) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body">
            <div class="stat-icon bg-info-subtle text-info"><i class="bi bi-people"></i></div>
            <div class="stat-label">Anggota</div><div class="stat-value"><?= number_format($totalMembers) ?></div>
        </div></div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body">
            <div class="stat-icon bg-warning-subtle text-warning"><i class="bi bi-building"></i></div>
            <div class="stat-label">Cabang</div><div class="stat-value"><?= number_format($totalBranches) ?></div>
        </div></div>
    </div>
</div>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Peminjaman Aktif</h3><i class="bi bi-arrow-left-right text-primary fs-4"></i>
            </div>
            <div class="display-6 fw-bold"><?= number_format($activeLoans) ?></div>
            <p class="text-muted mb-0">Transaksi yang belum selesai</p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Buku Terlambat</h3><i class="bi bi-clock-history text-danger fs-4"></i>
            </div>
            <div class="display-6 fw-bold text-danger"><?= number_format($overdueBooks) ?></div>
            <p class="text-muted mb-0">Buku yang melewati jatuh tempo</p>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 class="h6 mb-0">Ringkasan Denda</h3><i class="bi bi-cash-coin text-warning fs-4"></i>
            </div>
            <div class="fw-bold fs-5">Rp <?= number_format((float) $fineSummary['total_denda'], 0, ',', '.') ?></div>
            <p class="text-danger mb-0">Belum lunas: Rp <?= number_format((float) $fineSummary['belum_lunas'], 0, ',', '.') ?></p>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
