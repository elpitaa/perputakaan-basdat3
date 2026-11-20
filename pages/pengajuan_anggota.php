<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$user = currentUser();
$statement = $pdo->prepare(
    'SELECT pp.no_pengajuan, pp.tanggal_pengajuan, pp.status, pp.catatan_anggota,
            pp.catatan_petugas, c.nama_cabang, b.judul
     FROM pengajuan_peminjaman pp
     INNER JOIN detail_pengajuan_peminjaman dp ON dp.id_pengajuan = pp.id_pengajuan
     INNER JOIN buku b ON b.id_buku = dp.id_buku
     INNER JOIN cabang c ON c.id_cabang = pp.id_cabang
     WHERE pp.id_anggota = :id_anggota
     ORDER BY pp.tanggal_pengajuan DESC, pp.id_pengajuan DESC'
);
$statement->execute(['id_anggota' => (int) $user['id_anggota']]);
$requests = $statement->fetchAll();

$pageTitle = 'Pengajuan Peminjaman';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3"><p class="text-muted mb-0">Pantau status pengajuan peminjaman Anda.</p><a class="btn btn-primary btn-sm" href="katalog.php"><i class="bi bi-book me-1"></i>Cari Buku</a></div>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nomor</th><th>Buku</th><th>Cabang</th><th>Tanggal</th><th>Status</th><th>Catatan</th></tr></thead><tbody>
<?php foreach ($requests as $request): ?><tr><td class="fw-semibold"><?= htmlspecialchars($request['no_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($request['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($request['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($request['tanggal_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $request['status'] === 'DISETUJUI' ? 'success' : ($request['status'] === 'DITOLAK' ? 'danger' : ($request['status'] === 'DIBATALKAN' ? 'secondary' : 'warning')) ?>"><?= htmlspecialchars($request['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars((string) ($request['catatan_petugas'] ?: $request['catatan_anggota'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
<?php if (!$requests): ?><tr><td colspan="6" class="text-center text-muted py-4">Belum ada pengajuan peminjaman.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
