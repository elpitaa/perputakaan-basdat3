<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireStaff();

$pdo = getDatabaseConnection();
$isAdmin = currentUser()['role'] === 'ADMIN';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        if (!$isAdmin) {
            throw new RuntimeException('Anda tidak memiliki akses untuk mengubah kategori.');
        }
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'delete') {
            $statement = $pdo->prepare('DELETE FROM kategori_buku WHERE id_kategori = :id');
            $statement->execute(['id' => filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT)]);
            $success = 'Kategori berhasil dihapus.';
        } elseif ($action === 'save') {
            $id = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
            $nama = trim((string) ($_POST['nama_kategori'] ?? ''));
            $deskripsi = trim((string) ($_POST['deskripsi'] ?? ''));
            if ($nama === '') {
                throw new InvalidArgumentException('Nama kategori wajib diisi.');
            }
            if ($id) {
                $statement = $pdo->prepare('UPDATE kategori_buku SET nama_kategori = :nama, deskripsi = :deskripsi WHERE id_kategori = :id');
                $statement->execute(['nama' => $nama, 'deskripsi' => $deskripsi ?: null, 'id' => $id]);
                $success = 'Kategori berhasil diperbarui.';
            } else {
                $statement = $pdo->prepare('INSERT INTO kategori_buku (nama_kategori, deskripsi) VALUES (:nama, :deskripsi)');
                $statement->execute(['nama' => $nama, 'deskripsi' => $deskripsi ?: null]);
                $success = 'Kategori berhasil ditambahkan.';
            }
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = $exception->errorInfo[1] === 1451 ? 'Data tidak bisa dihapus karena sudah dipakai.' : ($exception->errorInfo[1] === 1062 ? 'Nama kategori sudah digunakan.' : 'Perubahan kategori gagal disimpan.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$categories = $pdo->query(
    'SELECT k.id_kategori, k.nama_kategori, k.deskripsi, COUNT(b.id_buku) AS jumlah_buku
     FROM kategori_buku k LEFT JOIN buku b ON b.id_kategori = k.id_kategori
     GROUP BY k.id_kategori, k.nama_kategori, k.deskripsi ORDER BY k.nama_kategori'
)->fetchAll();
$pageTitle = 'Kategori Buku';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><?php if ($isAdmin): ?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal"><i class="bi bi-plus-lg me-1"></i>Tambah Kategori</button><?php endif; ?></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Nama Kategori</th><th>Deskripsi</th><th>Jumlah Buku</th><?php if ($isAdmin): ?><th class="text-end">Aksi</th><?php endif; ?></tr></thead>
<tbody><?php foreach ($categories as $category): ?><tr>
    <td class="fw-semibold"><?= htmlspecialchars($category['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($category['deskripsi'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $category['jumlah_buku'] ?></td>
    <?php if ($isAdmin): ?><td class="text-end"><button class="btn btn-sm btn-outline-primary edit-category" data-bs-toggle="modal" data-bs-target="#categoryModal" data-category="<?= htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
    <form class="d-inline" method="post" onsubmit="return confirm('Hapus kategori ini?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id_kategori" value="<?= (int) $category['id_kategori'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td><?php endif; ?>
</tr><?php endforeach; ?></tbody>
</table></div></div>
<?php if ($isAdmin): ?><div class="modal fade" id="categoryModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Tambah Kategori</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" id="id_kategori" name="id_kategori">
<label class="form-label">Nama Kategori *</label><input class="form-control mb-3" id="nama_kategori" name="nama_kategori" required><label class="form-label">Deskripsi</label><textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div><script>
document.querySelectorAll('.edit-category').forEach(function (button) { button.addEventListener('click', function () { const item = JSON.parse(button.dataset.category); document.querySelector('#categoryModal .modal-title').textContent = 'Edit Kategori'; document.getElementById('id_kategori').value = item.id_kategori; document.getElementById('nama_kategori').value = item.nama_kategori; document.getElementById('deskripsi').value = item.deskripsi ?? ''; }); });
document.getElementById('categoryModal').addEventListener('hidden.bs.modal', function () { document.querySelector('#categoryModal form').reset(); document.querySelector('#categoryModal .modal-title').textContent = 'Tambah Kategori'; });
</script><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
