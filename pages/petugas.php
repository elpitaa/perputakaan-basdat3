<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireStaff();
if (currentUser()['role'] !== 'ADMIN') {
    http_response_code(403);
    exit('Akses ditolak.');
}

$pdo = getDatabaseConnection();
$error = null;
$success = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $action = (string) ($_POST['action'] ?? '');
        $id = filter_input(INPUT_POST, 'id_petugas', FILTER_VALIDATE_INT);
        if ($action === 'status') {
            $pdo->prepare("UPDATE petugas SET status=CASE WHEN status='AKTIF' THEN 'NONAKTIF' ELSE 'AKTIF' END WHERE id_petugas=:id")->execute(['id' => $id]);
            $success = 'Status petugas berhasil diubah.';
        } elseif ($action === 'save') {
            $branch = filter_input(INPUT_POST, 'id_cabang', FILTER_VALIDATE_INT);
            $name = trim((string) ($_POST['nama_petugas'] ?? ''));
            $phone = trim((string) ($_POST['telepon'] ?? ''));
            if (!$branch || $name === '') {
                throw new InvalidArgumentException('Cabang dan nama petugas wajib diisi.');
            }
            $values = ['branch' => $branch, 'name' => $name, 'phone' => $phone ?: null];
            if ($id) {
                $values['id'] = $id;
                $sql = 'UPDATE petugas SET id_cabang=:branch,nama_petugas=:name,telepon=:phone WHERE id_petugas=:id';
            } else {
                $sql = 'INSERT INTO petugas (id_cabang,nama_petugas,telepon) VALUES (:branch,:name,:phone)';
            }
            $pdo->prepare($sql)->execute($values);
            $success = $id ? 'Petugas berhasil diperbarui.' : 'Petugas berhasil ditambahkan.';
        }
    } catch (Throwable $exception) {
        error_log($exception->getMessage());
        $error = $exception instanceof PDOException && $exception->errorInfo[1] === 1451
            ? 'Data tidak bisa dihapus karena sudah dipakai.'
            : $exception->getMessage();
    }
}
$staff = $pdo->query('SELECT p.*, c.nama_cabang FROM petugas p INNER JOIN cabang c ON c.id_cabang=p.id_cabang ORDER BY p.nama_petugas')->fetchAll();
$branches = $pdo->query("SELECT id_cabang,nama_cabang FROM cabang WHERE status='AKTIF' ORDER BY nama_cabang")->fetchAll();
$pageTitle = 'Petugas';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#staffModal"><i class="bi bi-plus-lg me-1"></i>Tambah Petugas</button></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Nama</th><th>Cabang</th><th>Telepon</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($staff as $item): ?><tr><td class="fw-semibold"><?= htmlspecialchars($item['nama_petugas'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($item['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($item['telepon'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $item['status'] === 'AKTIF' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary edit-staff" data-bs-toggle="modal" data-bs-target="#staffModal" data-staff="<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button><form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id_petugas" value="<?= (int) $item['id_petugas'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="modal fade" id="staffModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Tambah Petugas</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id_petugas" id="id_petugas"><label class="form-label">Nama *</label><input class="form-control mb-3" name="nama_petugas" id="nama_petugas" required><label class="form-label">Cabang *</label><select class="form-select mb-3" name="id_cabang" id="id_cabang" required><option value="">Pilih</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id_cabang'] ?>"><?= htmlspecialchars($branch['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><label class="form-label">Telepon</label><input class="form-control" name="telepon" id="telepon"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan</button></div></form></div></div></div>
<script>document.querySelectorAll('.edit-staff').forEach(function (button) { button.addEventListener('click', function () { const item = JSON.parse(button.dataset.staff); document.querySelector('#staffModal .modal-title').textContent = 'Edit Petugas'; ['id_petugas','nama_petugas','id_cabang','telepon'].forEach(function (field) { document.getElementById(field).value = item[field] ?? ''; }); }); }); document.getElementById('staffModal').addEventListener('hidden.bs.modal', function () { document.querySelector('#staffModal form').reset(); document.querySelector('#staffModal .modal-title').textContent = 'Tambah Petugas'; });</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
