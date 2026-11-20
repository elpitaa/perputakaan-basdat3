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
            throw new RuntimeException('Anda tidak memiliki akses untuk mengubah data buku.');
        }

        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM buku WHERE id_buku = :id_buku');
            $statement->execute(['id_buku' => filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT)]);
            $success = 'Buku berhasil dihapus.';
        } elseif ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);
            $judul = trim((string) ($_POST['judul'] ?? ''));
            $penulis = trim((string) ($_POST['penulis'] ?? ''));
            $isbn = trim((string) ($_POST['isbn'] ?? ''));
            $penerbit = trim((string) ($_POST['penerbit'] ?? ''));
            $tahun = filter_input(INPUT_POST, 'tahun_terbit', FILTER_VALIDATE_INT);
            $harga = filter_input(INPUT_POST, 'harga', FILTER_VALIDATE_FLOAT);
            $kategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);

            if ($judul === '' || $penulis === '' || !$kategori || $harga === false || $harga < 0 || ($tahun !== null && $tahun !== false && ($tahun < 0 || $tahun > 9999))) {
                throw new InvalidArgumentException('Judul, penulis, kategori, dan harga harus diisi dengan benar.');
            }

            $values = [
                'isbn' => $isbn !== '' ? $isbn : null,
                'judul' => $judul,
                'penulis' => $penulis,
                'penerbit' => $penerbit !== '' ? $penerbit : null,
                'tahun_terbit' => $tahun !== false && $tahun !== null ? $tahun : null,
                'harga' => $harga,
                'id_kategori' => $kategori,
            ];
            if ($id) {
                $values['id_buku'] = $id;
                $statement = $pdo->prepare(
                    'UPDATE buku SET isbn = :isbn, judul = :judul, penulis = :penulis,
                     penerbit = :penerbit, tahun_terbit = :tahun_terbit, harga = :harga,
                     id_kategori = :id_kategori WHERE id_buku = :id_buku'
                );
            } else {
                $statement = $pdo->prepare(
                    'INSERT INTO buku (isbn, judul, penulis, penerbit, tahun_terbit, harga, id_kategori)
                     VALUES (:isbn, :judul, :penulis, :penerbit, :tahun_terbit, :harga, :id_kategori)'
                );
            }
            $statement->execute($values);
            $success = $id ? 'Buku berhasil diperbarui.' : 'Buku berhasil ditambahkan.';
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = $exception->errorInfo[1] === 1451
            ? 'Data tidak bisa dihapus karena sudah dipakai.'
            : ($exception->errorInfo[1] === 1062 ? 'ISBN sudah digunakan.' : 'Perubahan buku gagal disimpan.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$category = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT);
$categories = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori_buku ORDER BY nama_kategori')->fetchAll();
$sql = 'SELECT b.*, k.nama_kategori FROM buku b INNER JOIN kategori_buku k ON k.id_kategori = b.id_kategori WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (b.judul LIKE :search OR b.penulis LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($category) {
    $sql .= ' AND b.id_kategori = :kategori';
    $params['kategori'] = $category;
}
$sql .= ' ORDER BY b.judul';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$books = $statement->fetchAll();

$pageTitle = 'Buku';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
    <form class="d-flex gap-2" method="get">
        <input class="form-control" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari judul/penulis">
        <select class="form-select" name="kategori">
            <option value="">Semua kategori</option>
            <?php foreach ($categories as $item): ?>
                <option value="<?= (int) $item['id_kategori'] ?>" <?= $category === (int) $item['id_kategori'] ? 'selected' : '' ?>><?= htmlspecialchars($item['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-search"></i></button>
    </form>
    <?php if ($isAdmin): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bookModal"><i class="bi bi-plus-lg me-1"></i>Tambah Buku</button><?php endif; ?>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead><tr><th>ISBN</th><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Tahun</th><th>Harga</th><?php if ($isAdmin): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($books as $book): ?>
        <tr>
            <td><?= htmlspecialchars((string) ($book['isbn'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td class="fw-semibold"><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($book['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars((string) ($book['tahun_terbit'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td>
            <td>Rp <?= number_format((float) $book['harga'], 0, ',', '.') ?></td>
            <?php if ($isAdmin): ?><td class="text-end">
                <button class="btn btn-sm btn-outline-primary edit-book" data-bs-toggle="modal" data-bs-target="#bookModal" data-book="<?= htmlspecialchars(json_encode($book), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
                <form class="d-inline" method="post" onsubmit="return confirm('Hapus buku ini?')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="delete"><input type="hidden" name="id_buku" value="<?= (int) $book['id_buku'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit"><i class="bi bi-trash"></i></button>
                </form>
            </td><?php endif; ?>
        </tr>
    <?php endforeach; ?>
    <?php if (!$books): ?><tr><td class="text-center text-muted py-4" colspan="<?= $isAdmin ? 7 : 6 ?>">Belum ada data buku.</td></tr><?php endif; ?>
    </tbody>
</table>
</div></div>
<?php if ($isAdmin): ?>
<div class="modal fade" id="bookModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="post">
        <div class="modal-header"><h5 class="modal-title">Tambah Buku</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" id="id_buku" name="id_buku">
            <div class="row g-3">
                <div class="col-md-8"><label class="form-label">Judul *</label><input class="form-control" id="judul" name="judul" required></div>
                <div class="col-md-4"><label class="form-label">ISBN</label><input class="form-control" id="isbn" name="isbn"></div>
                <div class="col-md-6"><label class="form-label">Penulis *</label><input class="form-control" id="penulis" name="penulis" required></div>
                <div class="col-md-6"><label class="form-label">Penerbit</label><input class="form-control" id="penerbit" name="penerbit"></div>
                <div class="col-md-4"><label class="form-label">Tahun</label><input class="form-control" id="tahun_terbit" name="tahun_terbit" type="number" min="0" max="9999"></div>
                <div class="col-md-4"><label class="form-label">Harga *</label><input class="form-control" id="harga" name="harga" type="number" min="0" step="0.01" required></div>
                <div class="col-md-4"><label class="form-label">Kategori *</label><select class="form-select" id="id_kategori" name="id_kategori" required><option value="">Pilih</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id_kategori'] ?>"><?= htmlspecialchars($item['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
            </div>
        </div>
        <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary" type="submit">Simpan</button></div>
    </form>
</div></div></div>
<script>
document.querySelectorAll('.edit-book').forEach(function (button) {
    button.addEventListener('click', function () {
        const book = JSON.parse(button.dataset.book);
        document.querySelector('#bookModal .modal-title').textContent = 'Edit Buku';
        ['id_buku', 'isbn', 'judul', 'penulis', 'penerbit', 'tahun_terbit', 'harga', 'id_kategori'].forEach(function (field) {
            document.getElementById(field).value = book[field] ?? '';
        });
    });
});
document.getElementById('bookModal').addEventListener('hidden.bs.modal', function () {
    document.querySelector('#bookModal form').reset();
    document.querySelector('#bookModal .modal-title').textContent = 'Tambah Buku';
});
</script>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
