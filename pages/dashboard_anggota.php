<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$user = currentUser();
$memberId = (int) $user['id_anggota'];

$summaryStatement = $pdo->prepare(
    "SELECT
        COUNT(DISTINCT id_peminjaman) AS total_peminjaman,
        COUNT(DISTINCT CASE WHEN status_buku = 'DIPINJAM' THEN id_detail_peminjaman END) AS sedang_dipinjam,
        COUNT(DISTINCT CASE WHEN status_buku = 'DIKEMBALIKAN' THEN id_detail_peminjaman END) AS sudah_dikembalikan,
        COUNT(DISTINCT CASE WHEN status_buku = 'DIPINJAM' AND hari_terlambat > 0 THEN id_detail_peminjaman END) AS terlambat
     FROM v_riwayat_peminjaman
     WHERE id_anggota = :id_anggota"
);
$summaryStatement->execute(['id_anggota' => $memberId]);
$summary = $summaryStatement->fetch() ?: [
    'total_peminjaman' => 0,
    'sedang_dipinjam' => 0,
    'sudah_dikembalikan' => 0,
    'terlambat' => 0,
];

$fineStatement = $pdo->prepare(
    'SELECT denda_belum_lunas
     FROM v_ringkasan_denda_anggota
     WHERE id_anggota = :id_anggota'
);
$fineStatement->execute(['id_anggota' => $memberId]);
$unpaidFine = (float) ($fineStatement->fetchColumn() ?: 0);

$recentStatement = $pdo->prepare(
    'SELECT no_transaksi, judul, nama_cabang, tanggal_pinjam, tanggal_jatuh_tempo,
            tanggal_kembali, status_buku, hari_terlambat
     FROM v_riwayat_peminjaman
     WHERE id_anggota = :id_anggota
     ORDER BY tanggal_pinjam DESC, id_detail_peminjaman DESC
     OFFSET 0 ROWS FETCH NEXT 5 ROWS ONLY'
);
$recentStatement->execute(['id_anggota' => $memberId]);
$recentLoans = $recentStatement->fetchAll();

$pageTitle = 'Dashboard Anggota';
require __DIR__ . '/../includes/header.php';
?>
<div class="card welcome-card shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="small text-white-50 mb-1">Area anggota</div>
        <h2 class="h4">Selamat datang, <?= htmlspecialchars((string) $user['nama_anggota'], ENT_QUOTES, 'UTF-8') ?>.</h2>
        <p class="mb-0">Pantau peminjaman, jatuh tempo, dan denda Anda dari satu halaman.</p>
    </div>
</div>
<div class="row g-4 mb-4">
    <?php
    $memberStats = [
        ['Total Peminjaman', (int) $summary['total_peminjaman'], 'bi-arrow-left-right', 'primary'],
        ['Sedang Dipinjam', (int) $summary['sedang_dipinjam'], 'bi-book-half', 'success'],
        ['Sudah Dikembalikan', (int) $summary['sudah_dikembalikan'], 'bi-check2-circle', 'info'],
        ['Buku Terlambat', (int) $summary['terlambat'], 'bi-clock-history', 'danger'],
    ];
    foreach ($memberStats as $stat):
    ?>
    <div class="col-6 col-xl-3">
        <div class="card stat-card shadow-sm h-100"><div class="card-body">
            <div class="stat-icon bg-<?= $stat[3] ?>-subtle text-<?= $stat[3] ?>"><i class="bi <?= $stat[2] ?>"></i></div>
            <div class="stat-label"><?= $stat[0] ?></div>
            <div class="stat-value"><?= number_format($stat[1]) ?></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h5 mb-0">Riwayat Terbaru</h2>
            </div>
            <?php if (!$recentLoans): ?>
                <p class="text-muted mb-0">Belum ada riwayat peminjaman.</p>
            <?php else: ?>
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Buku</th><th>Transaksi</th><th>Jatuh Tempo</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($recentLoans as $loan): ?>
                        <?php $isLate = $loan['status_buku'] === 'DIPINJAM' && (int) $loan['hari_terlambat'] > 0; ?>
                        <tr>
                            <td><div class="fw-semibold"><?= htmlspecialchars($loan['judul'], ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted"><?= htmlspecialchars($loan['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td><?= htmlspecialchars($loan['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><span class="badge text-bg-<?= $isLate ? 'danger' : ($loan['status_buku'] === 'DIPINJAM' ? 'warning' : 'success') ?>"><?= $isLate ? 'Terlambat' : ($loan['status_buku'] === 'DIPINJAM' ? 'Dipinjam' : 'Dikembalikan') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100"><div class="card-body">
            <h2 class="h5 mb-3">Denda Belum Lunas</h2>
            <div class="display-6 fw-bold text-danger">Rp <?= number_format($unpaidFine, 0, ',', '.') ?></div>
            <?php if ((int) $summary['terlambat'] > 0): ?>
                <div class="alert alert-warning mt-4 mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Ada buku yang melewati tanggal jatuh tempo.</div>
            <?php else: ?>
                <p class="text-muted mt-3 mb-0">Tidak ada keterlambatan aktif.</p>
            <?php endif; ?>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
