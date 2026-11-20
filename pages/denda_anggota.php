<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$user = currentUser();
$memberId = (int) $user['id_anggota'];

$summaryStatement = $pdo->prepare(
    'SELECT total_denda, denda_lunas, denda_belum_lunas
     FROM v_ringkasan_denda_anggota
     WHERE id_anggota = :id_anggota'
);
$summaryStatement->execute(['id_anggota' => $memberId]);
$summary = $summaryStatement->fetch() ?: [
    'total_denda' => 0,
    'denda_lunas' => 0,
    'denda_belum_lunas' => 0,
];

$statement = $pdo->prepare(
    'SELECT dn.id_denda, dn.jenis_denda, dn.jumlah_hari_terlambat,
            dn.jumlah_denda, dn.status_bayar, dn.tanggal_bayar,
            p.no_transaksi, b.judul, c.nama_cabang,
            pg.tanggal_kembali AS tanggal_denda
     FROM denda dn
     INNER JOIN detail_pengembalian dk ON dk.id_detail_pengembalian = dn.id_detail_pengembalian
     INNER JOIN pengembalian pg ON pg.id_pengembalian = dk.id_pengembalian
     INNER JOIN detail_peminjaman dm ON dm.id_detail_peminjaman = dk.id_detail_peminjaman
     INNER JOIN peminjaman p ON p.id_peminjaman = dm.id_peminjaman
     INNER JOIN buku b ON b.id_buku = dm.id_buku
     INNER JOIN cabang c ON c.id_cabang = p.id_cabang
     WHERE p.id_anggota = :id_anggota
     ORDER BY dn.status_bayar, pg.tanggal_kembali DESC, dn.id_denda DESC'
);
$statement->execute(['id_anggota' => $memberId]);
$fines = $statement->fetchAll();

$pageTitle = 'Denda Saya';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-4 mb-4">
    <div class="col-md-4"><div class="card stat-card shadow-sm h-100"><div class="card-body"><div class="stat-icon bg-primary-subtle text-primary"><i class="bi bi-cash-stack"></i></div><div class="stat-label">Total Denda</div><div class="stat-value">Rp <?= number_format((float) $summary['total_denda'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card shadow-sm h-100"><div class="card-body"><div class="stat-icon bg-success-subtle text-success"><i class="bi bi-check-circle"></i></div><div class="stat-label">Sudah Lunas</div><div class="stat-value">Rp <?= number_format((float) $summary['denda_lunas'], 0, ',', '.') ?></div></div></div></div>
    <div class="col-md-4"><div class="card stat-card shadow-sm h-100"><div class="card-body"><div class="stat-icon bg-danger-subtle text-danger"><i class="bi bi-exclamation-circle"></i></div><div class="stat-label">Belum Lunas</div><div class="stat-value text-danger">Rp <?= number_format((float) $summary['denda_belum_lunas'], 0, ',', '.') ?></div></div></div></div>
</div>
<div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Pembayaran dan perubahan status denda dilakukan oleh petugas perpustakaan.</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Transaksi</th><th>Buku</th><th>Cabang</th><th>Jenis</th><th>Tanggal</th><th>Nominal</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($fines as $fine): ?>
        <tr>
            <td class="fw-semibold"><?= htmlspecialchars($fine['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($fine['judul'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($fine['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($fine['jenis_denda'], ENT_QUOTES, 'UTF-8') ?><?php if ((int) $fine['jumlah_hari_terlambat'] > 0): ?><br><small class="text-muted"><?= (int) $fine['jumlah_hari_terlambat'] ?> hari</small><?php endif; ?></td>
            <td><?= htmlspecialchars((string) $fine['tanggal_denda'], ENT_QUOTES, 'UTF-8') ?></td>
            <td>Rp <?= number_format((float) $fine['jumlah_denda'], 0, ',', '.') ?></td>
            <td><span class="badge text-bg-<?= $fine['status_bayar'] === 'LUNAS' ? 'success' : 'danger' ?>"><?= htmlspecialchars($fine['status_bayar'], ENT_QUOTES, 'UTF-8') ?></span><?php if ($fine['status_bayar'] === 'LUNAS' && $fine['tanggal_bayar']): ?><br><small class="text-muted"><?= htmlspecialchars($fine['tanggal_bayar'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?></td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$fines): ?><tr><td colspan="7" class="text-center text-muted py-4">Belum ada data denda.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
