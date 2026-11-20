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
        $loan = filter_input(INPUT_POST, 'id_peminjaman', FILTER_VALIDATE_INT);
        $staff = $isAdmin ? filter_input(INPUT_POST, 'id_petugas', FILTER_VALIDATE_INT) : (int) $user['id_petugas'];
        $date = (string) ($_POST['tanggal_kembali'] ?? date('Y-m-d'));
        $details = (array) ($_POST['detail'] ?? []);
        if (!$loan || !$staff || !$details || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            throw new InvalidArgumentException('Transaksi, petugas, buku, dan tanggal kembali wajib diisi dengan benar.');
        }
        $pdo->beginTransaction();
        do {
            $number = 'KMB-' . date('Ymd', strtotime($date)) . '-' . random_int(100, 999);
            $check = $pdo->prepare('SELECT COUNT(*) FROM pengembalian WHERE no_pengembalian=:number');
            $check->execute(['number' => $number]);
        } while ((int) $check->fetchColumn() > 0);
        $returnId = insertAndGetId($pdo,
            'INSERT INTO pengembalian (no_pengembalian,id_peminjaman,id_petugas,tanggal_kembali,catatan) VALUES (:number,:loan,:staff,:date,:note)',
            [
                '_identity_column' => 'id_pengembalian',
                'number' => $number,
                'loan' => $loan,
                'staff' => $staff,
                'date' => $date,
                'note' => trim((string) ($_POST['catatan'] ?? '')) ?: null,
            ]
        );
        $detailInsert = $pdo->prepare('INSERT INTO detail_pengembalian (id_pengembalian,id_detail_peminjaman,kondisi_buku) VALUES (:return,:detail,:condition)');
        foreach ($details as $detailId => $condition) {
            if (!in_array($condition, ['BAIK', 'RUSAK', 'HILANG'], true)) {
                throw new InvalidArgumentException('Kondisi buku tidak valid.');
            }
            $detailInsert->execute(['return' => $returnId, 'detail' => (int) $detailId, 'condition' => $condition]);
        }
        $pdo->commit();
        $success = 'Pengembalian berhasil disimpan. Denda dihitung otomatis oleh trigger database.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $error = $exception instanceof PDOException && ($exception->errorInfo[0] ?? '') === '45000' ? $exception->getMessage() : ($exception->getMessage() ?: 'Pengembalian gagal disimpan.');
    }
}
$sql = "SELECT p.id_peminjaman,p.no_transaksi,a.nomor_anggota,a.nama_anggota,p.tanggal_pinjam,p.tanggal_jatuh_tempo,c.nama_cabang
        FROM peminjaman p INNER JOIN anggota a ON a.id_anggota=p.id_anggota INNER JOIN cabang c ON c.id_cabang=p.id_cabang
        WHERE p.status='DIPINJAM'";
if (!$isAdmin) { $sql .= ' AND p.id_cabang=:branch'; }
$sql .= ' ORDER BY p.tanggal_jatuh_tempo';
$statement = $pdo->prepare($sql);
$statement->execute($isAdmin ? [] : ['branch' => $user['id_cabang']]);
$openLoans = $statement->fetchAll();
$details = [];
foreach ($openLoans as $loan) {
    $item = $pdo->prepare("SELECT dm.id_detail_peminjaman,b.judul FROM detail_peminjaman dm INNER JOIN buku b ON b.id_buku=dm.id_buku LEFT JOIN detail_pengembalian dk ON dk.id_detail_peminjaman=dm.id_detail_peminjaman WHERE dm.id_peminjaman=:loan AND dk.id_detail_pengembalian IS NULL ORDER BY b.judul");
    $item->execute(['loan' => $loan['id_peminjaman']]);
    $details[(int) $loan['id_peminjaman']] = $item->fetchAll();
}
$staff = $isAdmin ? $pdo->query("SELECT id_petugas,nama_petugas FROM petugas WHERE status='AKTIF' ORDER BY nama_petugas")->fetchAll() : [];
$pageTitle = 'Pengembalian';
require __DIR__ . '/../includes/header.php';
?>
<div class="alert alert-info">Pilih transaksi yang masih berstatus DIPINJAM. Satu transaksi dapat dikembalikan sebagian atau seluruhnya.</div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Transaksi</th><th>Anggota</th><th>Cabang</th><th>Pinjam</th><th>Jatuh Tempo</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($openLoans as $loan): ?><tr><td><?= htmlspecialchars($loan['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['nomor_anggota'] . ' - ' . $loan['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><button class="btn btn-sm btn-primary return-loan" data-bs-toggle="modal" data-bs-target="#returnModal" data-loan="<?= htmlspecialchars(json_encode(['loan' => $loan, 'details' => $details[(int) $loan['id_peminjaman']]]), ENT_QUOTES, 'UTF-8') ?>">Kembalikan</button></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="modal fade" id="returnModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Pengembalian</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_peminjaman" id="return_loan"><div class="row g-3"><div class="col-md-4"><label class="form-label">Tanggal Kembali *</label><input class="form-control" type="date" name="tanggal_kembali" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div><?php if ($isAdmin): ?><div class="col-md-8"><label class="form-label">Petugas *</label><select class="form-select" name="id_petugas" required><option value="">Pilih</option><?php foreach ($staff as $item): ?><option value="<?= (int) $item['id_petugas'] ?>"><?= htmlspecialchars($item['nama_petugas'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><?php endif; ?><div class="col-12"><label class="form-label">Buku yang dikembalikan</label><div id="return_items"></div></div><div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="255"></div></div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan Pengembalian</button></div></form></div></div></div>
<script>document.querySelectorAll('.return-loan').forEach(function (button) { button.addEventListener('click', function () { const data = JSON.parse(button.dataset.loan); document.getElementById('return_loan').value = data.loan.id_peminjaman; document.getElementById('return_items').innerHTML = data.details.map(function (item) { return '<div class="row align-items-center mb-2"><div class="col-7">' + item.judul.replace(/</g, '&lt;') + '</div><div class="col-5"><select class="form-select" name="detail[' + item.id_detail_peminjaman + ']"><option value="BAIK">BAIK</option><option value="RUSAK">RUSAK</option><option value="HILANG">HILANG</option></select></div></div>'; }).join(''); }); });</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
