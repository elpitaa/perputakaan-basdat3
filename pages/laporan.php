<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireStaff();

$pdo = getDatabaseConnection();
$user = currentUser();
$isAdmin = $user['role'] === 'ADMIN';
$requestedTab = (string) ($_GET['tab'] ?? 'riwayat');
$tab = in_array($requestedTab, ['riwayat', 'terlambat', 'stok', 'denda'], true) ? $requestedTab : 'riwayat';
$branchWhere = $isAdmin ? '' : ' WHERE id_cabang = :branch';
$params = $isAdmin ? [] : ['branch' => $user['id_cabang']];

if ($tab === 'riwayat') {
    $sql = 'SELECT * FROM v_riwayat_peminjaman';
    if (!$isAdmin) { $sql .= ' WHERE id_cabang = :branch'; }
    $sql .= ' ORDER BY tanggal_pinjam DESC, no_transaksi DESC OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY';
} elseif ($tab === 'terlambat') {
    $sql = 'SELECT t.* FROM v_buku_terlambat t';
    if (!$isAdmin) { $sql .= ' INNER JOIN peminjaman p ON p.no_transaksi=t.no_transaksi WHERE p.id_cabang=:branch'; }
    $sql .= ' ORDER BY hari_terlambat DESC OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY';
} elseif ($tab === 'stok') {
    $sql = 'SELECT * FROM v_stok_cabang';
    if (!$isAdmin) { $sql .= ' WHERE id_cabang=:branch'; }
    $sql .= ' ORDER BY nama_cabang, judul OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY';
} else {
    $sql = 'SELECT r.id_anggota,r.nomor_anggota,r.nama_anggota,r.total_denda,r.denda_lunas,r.denda_belum_lunas
            FROM v_ringkasan_denda_anggota r';
    if (!$isAdmin) {
        $sql .= ' INNER JOIN (
                    SELECT DISTINCT p.id_anggota
                    FROM peminjaman p
                    WHERE p.id_cabang=:branch
                  ) cabang_transaksi ON cabang_transaksi.id_anggota=r.id_anggota';
    }
    $sql .= ' ORDER BY r.denda_belum_lunas DESC, r.nama_anggota OFFSET 0 ROWS FETCH NEXT 500 ROWS ONLY';
}
$statement = $pdo->prepare($sql);
$statement->execute($params);
$rows = $statement->fetchAll();

$pageTitle = 'Laporan';
require __DIR__ . '/../includes/header.php';
?>
<div class="btn-group mb-3" role="group">
    <?php foreach (['riwayat' => 'Riwayat Peminjaman', 'terlambat' => 'Buku Terlambat', 'stok' => 'Stok', 'denda' => 'Denda Anggota'] as $key => $label): ?>
        <a class="btn btn-<?= $tab === $key ? 'primary' : 'outline-primary' ?>" href="?tab=<?= $key ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a>
    <?php endforeach; ?>
</div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<?php if ($tab === 'riwayat'): ?>
    <thead><tr><th>Transaksi</th><th>Anggota</th><th>Buku</th><th>Pinjam</th><th>Kembali</th><th>Status</th><th>Denda</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['nomor_anggota'] . ' - ' . $row['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $row['tanggal_pinjam'] ?></td><td><?= htmlspecialchars((string) ($row['tanggal_kembali'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['status_buku'], ENT_QUOTES, 'UTF-8') ?></td><td>Rp <?= number_format((float) $row['total_denda'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'terlambat'): ?>
    <thead><tr><th>Transaksi</th><th>Cabang</th><th>Anggota</th><th>Buku</th><th>Jatuh Tempo</th><th>Hari</th><th>Estimasi Denda</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['nomor_anggota'] . ' - ' . $row['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= $row['tanggal_jatuh_tempo'] ?></td><td class="text-danger fw-semibold"><?= (int) $row['hari_terlambat'] ?></td><td>Rp <?= number_format((float) $row['estimasi_denda'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
<?php elseif ($tab === 'stok'): ?>
    <thead><tr><th>Cabang</th><th>Buku</th><th>Kategori</th><th>Total</th><th>Tersedia</th><th>Dipinjam</th><th>Rak</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $row['jumlah_total'] ?></td><td><?= (int) $row['jumlah_tersedia'] ?></td><td><?= (int) $row['jumlah_dipinjam'] ?></td><td><?= htmlspecialchars((string) ($row['lokasi_rak'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
<?php else: ?>
    <thead><tr><th>Nomor</th><th>Nama Anggota</th><th>Total Denda</th><th>Lunas</th><th>Belum Lunas</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= htmlspecialchars($row['nomor_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($row['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td>Rp <?= number_format((float) $row['total_denda'], 0, ',', '.') ?></td><td class="text-success">Rp <?= number_format((float) $row['denda_lunas'], 0, ',', '.') ?></td><td class="text-danger">Rp <?= number_format((float) $row['denda_belum_lunas'], 0, ',', '.') ?></td></tr><?php endforeach; ?>
<?php endif; ?>
<?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Belum ada data laporan.</td></tr><?php endif; ?></tbody>
</table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
