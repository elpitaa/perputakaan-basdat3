<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireStaff();
$pdo = getDatabaseConnection();
$user = currentUser();
$isAdmin = $user['role'] === 'ADMIN';
$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $id = filter_input(INPUT_POST, 'id_denda', FILTER_VALIDATE_INT);
        if (!$id) { throw new InvalidArgumentException('Denda tidak valid.'); }
        $checkSql = 'SELECT dn.id_denda FROM denda dn INNER JOIN detail_pengembalian dk ON dk.id_detail_pengembalian=dn.id_detail_pengembalian INNER JOIN detail_peminjaman dm ON dm.id_detail_peminjaman=dk.id_detail_peminjaman INNER JOIN peminjaman p ON p.id_peminjaman=dm.id_peminjaman WHERE dn.id_denda=:id';
        if (!$isAdmin) { $checkSql .= ' AND p.id_cabang=:branch'; }
        $check = $pdo->prepare($checkSql);
        $check->execute($isAdmin ? ['id' => $id] : ['id' => $id, 'branch' => $user['id_cabang']]);
        if (!$check->fetchColumn()) { throw new RuntimeException('Denda tidak ditemukan atau bukan denda cabang Anda.'); }
        $update = $pdo->prepare("UPDATE denda SET status_bayar='LUNAS',tanggal_bayar=:date WHERE id_denda=:id AND status_bayar='BELUM_LUNAS'");
        $update->execute(['date' => date('Y-m-d'), 'id' => $id]);
        $success = 'Denda berhasil dibayar.';
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception->getMessage();
    }
}
$sql = 'SELECT dn.*,p.no_transaksi,a.nomor_anggota,a.nama_anggota,b.judul,c.nama_cabang FROM denda dn INNER JOIN detail_pengembalian dk ON dk.id_detail_pengembalian=dn.id_detail_pengembalian INNER JOIN detail_peminjaman dm ON dm.id_detail_peminjaman=dk.id_detail_peminjaman INNER JOIN peminjaman p ON p.id_peminjaman=dm.id_peminjaman INNER JOIN anggota a ON a.id_anggota=p.id_anggota INNER JOIN buku b ON b.id_buku=dm.id_buku INNER JOIN cabang c ON c.id_cabang=p.id_cabang';
if (!$isAdmin) { $sql .= ' WHERE p.id_cabang=:branch'; }
$sql .= ' ORDER BY dn.status_bayar, dn.id_denda DESC';
$statement = $pdo->prepare($sql);
$statement->execute($isAdmin ? [] : ['branch' => $user['id_cabang']]);
$fines = $statement->fetchAll();
$pageTitle = 'Denda';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Anggota</th><th>Buku</th><th>Jenis</th><th>Nominal</th><th>Status</th><th>Tanggal Bayar</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($fines as $fine): ?><tr><td><?= htmlspecialchars($fine['nomor_anggota'] . ' - ' . $fine['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fine['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($fine['jenis_denda'], ENT_QUOTES, 'UTF-8') ?></td><td>Rp <?= number_format((float) $fine['jumlah_denda'], 0, ',', '.') ?></td><td><span class="badge text-bg-<?= $fine['status_bayar'] === 'LUNAS' ? 'success' : 'danger' ?>"><?= htmlspecialchars($fine['status_bayar'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars((string) ($fine['tanggal_bayar'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?php if ($fine['status_bayar'] === 'BELUM_LUNAS'): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_denda" value="<?= (int) $fine['id_denda'] ?>"><button class="btn btn-sm btn-success">Bayar</button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
