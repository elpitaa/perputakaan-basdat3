<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireStaff();
$pdo = getDatabaseConnection();
$user = currentUser();
$isAdmin = $user['role'] === 'ADMIN';
$error = null;
$success = null;

function transactionNumber(PDO $pdo, string $prefix, string $date): string
{
    do {
        $number = $prefix . date('Ymd', strtotime($date)) . '-' . random_int(100, 999);
        $check = $pdo->prepare('SELECT COUNT(*) FROM peminjaman WHERE no_transaksi = :number');
        $check->execute(['number' => $number]);
    } while ((int) $check->fetchColumn() > 0);
    return $number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $member = filter_input(INPUT_POST, 'id_anggota', FILTER_VALIDATE_INT);
        $branch = $isAdmin ? filter_input(INPUT_POST, 'id_cabang', FILTER_VALIDATE_INT) : (int) $user['id_cabang'];
        $staff = $isAdmin ? filter_input(INPUT_POST, 'id_petugas', FILTER_VALIDATE_INT) : (int) $user['id_petugas'];
        $date = (string) ($_POST['tanggal_pinjam'] ?? date('Y-m-d'));
        $books = array_values(array_filter(array_map('intval', (array) ($_POST['id_buku'] ?? []))));
        if (!$member || !$branch || !$staff || !$books || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date > date('Y-m-d')) {
            throw new InvalidArgumentException('Anggota, petugas, buku, dan tanggal pinjam wajib diisi dengan benar.');
        }
        if (count($books) !== count(array_unique($books))) {
            throw new InvalidArgumentException('Buku yang sama tidak boleh dipilih dua kali.');
        }
        $setting = $pdo->prepare("SELECT nilai FROM pengaturan WHERE nama_pengaturan='lama_pinjam_hari'");
        $setting->execute();
        $days = max(1, (int) $setting->fetchColumn());
        $dueDate = date('Y-m-d', strtotime($date . ' +' . $days . ' days'));
        $pdo->beginTransaction();
        $loanId = insertAndGetId($pdo,
            'INSERT INTO peminjaman (no_transaksi,id_anggota,id_petugas,id_cabang,tanggal_pinjam,tanggal_jatuh_tempo,catatan) VALUES (:number,:member,:staff,:branch,:date,:due,:note)',
            [
                '_identity_column' => 'id_peminjaman',
                'number' => transactionNumber($pdo, 'PJM-', $date),
                'member' => $member,
                'staff' => $staff,
                'branch' => $branch,
                'date' => $date,
                'due' => $dueDate,
                'note' => trim((string) ($_POST['catatan'] ?? '')) ?: null,
            ]
        );
        $detail = $pdo->prepare('INSERT INTO detail_peminjaman (id_peminjaman,id_buku) VALUES (:loan,:book)');
        foreach ($books as $book) {
            $detail->execute(['loan' => $loanId, 'book' => $book]);
        }
        $pdo->commit();
        $success = 'Peminjaman berhasil disimpan.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = $exception instanceof PDOException && ($exception->errorInfo[0] ?? '') === '45000'
            ? $exception->getMessage()
            : ($exception->getMessage() ?: 'Peminjaman gagal disimpan.');
    }
}

$branchCondition = $isAdmin ? '' : ' AND s.id_cabang = :branch';
$memberSql = "SELECT id_anggota,nomor_anggota,nama_anggota FROM anggota WHERE status='AKTIF'" . ($isAdmin ? '' : ' AND id_cabang_daftar=:branch') . ' ORDER BY nama_anggota';
$memberStatement = $pdo->prepare($memberSql);
$memberStatement->execute($isAdmin ? [] : ['branch' => $user['id_cabang']]);
$members = $memberStatement->fetchAll();
$bookSql = "SELECT id_buku,judul,nama_cabang,jumlah_tersedia FROM v_stok_cabang s WHERE jumlah_tersedia > 0" . $branchCondition . ' ORDER BY judul';
$bookStatement = $pdo->prepare($bookSql);
$bookStatement->execute($isAdmin ? [] : ['branch' => $user['id_cabang']]);
$availableBooks = $bookStatement->fetchAll();
$branches = $isAdmin ? $pdo->query("SELECT id_cabang,nama_cabang FROM cabang WHERE status='AKTIF' ORDER BY nama_cabang")->fetchAll() : [];
$staff = $isAdmin ? $pdo->query("SELECT id_petugas,nama_petugas,id_cabang FROM petugas WHERE status='AKTIF' ORDER BY nama_petugas")->fetchAll() : [];
$sql = 'SELECT p.*,a.nomor_anggota,a.nama_anggota,c.nama_cabang FROM peminjaman p INNER JOIN anggota a ON a.id_anggota=p.id_anggota INNER JOIN cabang c ON c.id_cabang=p.id_cabang';
if (!$isAdmin) { $sql .= ' WHERE p.id_cabang=:branch'; }
$sql .= ' ORDER BY p.id_peminjaman DESC OFFSET 0 ROWS FETCH NEXT 50 ROWS ONLY';
$statement = $pdo->prepare($sql);
$statement->execute($isAdmin ? [] : ['branch' => $user['id_cabang']]);
$loans = $statement->fetchAll();
$pageTitle = 'Peminjaman';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#loanModal"><i class="bi bi-plus-lg me-1"></i>Peminjaman Baru</button></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Transaksi</th><th>Anggota</th><th>Cabang</th><th>Tanggal</th><th>Jatuh Tempo</th><th>Status</th></tr></thead><tbody>
<?php foreach ($loans as $loan): ?><tr><td class="fw-semibold"><?= htmlspecialchars($loan['no_transaksi'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['nomor_anggota'] . ' - ' . $loan['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['tanggal_pinjam'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($loan['tanggal_jatuh_tempo'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $loan['status'] === 'DIPINJAM' ? 'primary' : 'success' ?>"><?= htmlspecialchars($loan['status'], ENT_QUOTES, 'UTF-8') ?></span></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="modal fade" id="loanModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Peminjaman Baru</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<div class="row g-3"><div class="col-md-6"><label class="form-label">Anggota *</label><select class="form-select" name="id_anggota" required><option value="">Pilih anggota</option><?php foreach ($members as $member): ?><option value="<?= (int) $member['id_anggota'] ?>"><?= htmlspecialchars($member['nomor_anggota'] . ' - ' . $member['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-3"><label class="form-label">Tanggal *</label><input class="form-control" type="date" name="tanggal_pinjam" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required></div>
<?php if ($isAdmin): ?><div class="col-md-3"><label class="form-label">Cabang *</label><select class="form-select" name="id_cabang" required><option value="">Pilih</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id_cabang'] ?>"><?= htmlspecialchars($branch['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-md-6"><label class="form-label">Petugas *</label><select class="form-select" name="id_petugas" required><option value="">Pilih</option><?php foreach ($staff as $item): ?><option value="<?= (int) $item['id_petugas'] ?>"><?= htmlspecialchars($item['nama_petugas'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><?php endif; ?>
<div class="col-12"><label class="form-label">Buku * <small class="text-muted">(pilih satu atau lebih)</small></label><select class="form-select" name="id_buku[]" multiple size="8" required><?php foreach ($availableBooks as $book): ?><option value="<?= (int) $book['id_buku'] ?>"><?= htmlspecialchars($book['judul'] . ' - ' . $book['nama_cabang'] . ' (tersedia: ' . $book['jumlah_tersedia'] . ')', ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div><div class="col-12"><label class="form-label">Catatan</label><input class="form-control" name="catatan" maxlength="255"></div></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan Peminjaman</button></div></form></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
