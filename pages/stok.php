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
        if (!$isAdmin) {
            throw new RuntimeException('Hanya ADMIN yang dapat menambah stok.');
        }
        $branch = filter_input(INPUT_POST, 'id_cabang', FILTER_VALIDATE_INT);
        $book = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);
        $amount = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_INT);
        $rack = trim((string) ($_POST['lokasi_rak'] ?? ''));
        if (!$branch || !$book || !$amount || $amount < 1) {
            throw new InvalidArgumentException('Cabang, buku, dan jumlah stok harus diisi dengan benar.');
        }
        $pdo->beginTransaction();
        $existing = $pdo->prepare('SELECT jumlah_total FROM stok_buku_cabang WHERE id_cabang=:branch AND id_buku=:book');
        $existing->execute(['branch' => $branch, 'book' => $book]);
        $isNewStock = $existing->fetchColumn() === false;
        if ($isNewStock) {
            $statement = $pdo->prepare(
                'INSERT INTO stok_buku_cabang (id_cabang,id_buku,jumlah_total,jumlah_tersedia,lokasi_rak)
                 VALUES (:branch,:book,:total,:available,:rack)'
            );
        } else {
            $statement = $pdo->prepare(
                'UPDATE stok_buku_cabang
                 SET jumlah_total=jumlah_total+:total_amount, jumlah_tersedia=jumlah_tersedia+:available_amount,
                     lokasi_rak=:rack
                 WHERE id_cabang=:branch AND id_buku=:book'
            );
        }
        $statement->execute($isNewStock
            ? ['branch' => $branch, 'book' => $book, 'total' => $amount, 'available' => $amount, 'rack' => $rack ?: null]
            : ['branch' => $branch, 'book' => $book, 'total_amount' => $amount, 'available_amount' => $amount, 'rack' => $rack ?: null]
        );
        $pdo->commit();
        $success = 'Stok berhasil ditambahkan.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = 'Stok gagal disimpan: ' . $exception->getMessage();
    }
}
$sql = 'SELECT * FROM v_stok_cabang';
$params = [];
if (!$isAdmin) {
    $sql .= ' WHERE id_cabang = :branch';
    $params['branch'] = $user['id_cabang'];
}
$sql .= ' ORDER BY nama_cabang, judul';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$stocks = $statement->fetchAll();
$branches = $pdo->query("SELECT id_cabang,nama_cabang FROM cabang WHERE status='AKTIF' ORDER BY nama_cabang")->fetchAll();
$books = $pdo->query('SELECT id_buku,judul FROM buku ORDER BY judul')->fetchAll();
$pageTitle = 'Stok Buku';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($isAdmin): ?><div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#stockModal"><i class="bi bi-plus-lg me-1"></i>Tambah Stok</button></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Cabang</th><th>Judul</th><th>Kategori</th><th>Total</th><th>Tersedia</th><th>Dipinjam</th><th>Rak</th></tr></thead><tbody>
<?php foreach ($stocks as $stock): ?><tr><td><?= htmlspecialchars($stock['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td class="fw-semibold"><?= htmlspecialchars($stock['judul'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($stock['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $stock['jumlah_total'] ?></td><td class="text-success fw-semibold"><?= (int) $stock['jumlah_tersedia'] ?></td><td><?= (int) $stock['jumlah_dipinjam'] ?></td><td><?= htmlspecialchars((string) ($stock['lokasi_rak'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if ($isAdmin): ?><div class="modal fade" id="stockModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Tambah Stok</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><label class="form-label">Cabang *</label><select class="form-select mb-3" name="id_cabang" required><option value="">Pilih</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id_cabang'] ?>"><?= htmlspecialchars($branch['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><label class="form-label">Buku *</label><select class="form-select mb-3" name="id_buku" required><option value="">Pilih</option><?php foreach ($books as $book): ?><option value="<?= (int) $book['id_buku'] ?>"><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><label class="form-label">Jumlah *</label><input class="form-control mb-3" name="jumlah" type="number" min="1" required><label class="form-label">Lokasi Rak</label><input class="form-control" name="lokasi_rak" maxlength="30"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Tambah Stok</button></div></form></div></div></div><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
