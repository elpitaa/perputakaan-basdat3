<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$user = currentUser();
$status = (string) ($_GET['status'] ?? '');
$allowedStatuses = ['DIPINJAM', 'DIKEMBALIKAN', 'TERLAMBAT'];
if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$sql = 'SELECT no_transaksi, judul, nama_cabang, tanggal_pinjam,
               tanggal_jatuh_tempo, tanggal_kembali, status_buku,
               hari_terlambat, total_denda
        FROM v_riwayat_peminjaman
        WHERE id_anggota = :id_anggota';
$params = ['id_anggota' => (int) $user['id_anggota']];
if ($status === 'TERLAMBAT') {
    $sql .= " AND status_buku = 'DIPINJAM' AND hari_terlambat > 0";
} elseif ($status !== '') {
    $sql .= ' AND status_buku = :status';
    $params['status'] = $status;
}
$sql .= ' ORDER BY tanggal_pinjam DESC, no_transaksi DESC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$loans = $statement->fetchAll();

$pageTitle = 'Riwayat Peminjaman';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="text-muted mb-0">Daftar seluruh buku yang pernah Anda pinjam.</p>
    <form method="get" class="d-flex gap-2">
        <label class="visually-hidden" for="status">Filter status</label>
        <select class="form-select" id="status" name="status">
            <option value="">Semua status</option>
            <option value="DIPINJAM" <?= $status === 'DIPINJAM' ? 'selected' : '' ?>>Sedang dipinjam</option>
            <option value="TERLAMBAT" <?= $status === 'TERLAMBAT' ? 'selected' : '' ?>>Terlambat</option>
            <option value="DIKEMBALIKAN" <?= $status === 'DIKEMBALIKAN' ? 'selected' : '' ?>>Sudah dikembalikan</option>
        </select>
        <button class="btn btn-outline-primary" type="submit">Filter</button>
    </form>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Transaksi</th><th>Buku</th><th>Cabang</th><th>Tanggal Pinjam</th><th>Jatuh Tempo</th><th>Dikembalikan</th><th>Status</th><th>Denda</th></tr></thead>
        <tbody>
        <?php foreach ($loans as $loan): ?>
            <?php $isLate = $loan['status_buku'] === 'DIPINJAM' && (int) $loan['hari_terlambat'] > 0; ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($loan['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($loan['judul'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($loan['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($loan['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?>
                    <?php if ($isLate): ?><br><small class="text-danger"><?= (int) $loan['hari_terlambat'] ?> hari terlambat</small><?php endif; ?>
                </td>
                <td><?= htmlspecialchars((string) ($loan['tanggal_kembali'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge text-bg-<?= $isLate ? 'danger' : ($loan['status_buku'] === 'DIPINJAM' ? 'warning' : 'success') ?>"><?= $isLate ? 'Terlambat' : ($loan['status_buku'] === 'DIPINJAM' ? 'Dipinjam' : 'Dikembalikan') ?></span></td>
                <td>Rp <?= number_format((float) $loan['total_denda'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$loans): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada data sesuai filter.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
