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
        $id = filter_input(INPUT_POST, 'id_cabang', FILTER_VALIDATE_INT);
        if ($action === 'status') {
            $pdo->prepare("UPDATE cabang SET status=CASE WHEN status='AKTIF' THEN 'NONAKTIF' ELSE 'AKTIF' END WHERE id_cabang=:id")->execute(['id' => $id]);
            $success = 'Status cabang berhasil diubah.';
        } elseif ($action === 'save') {
            $kode = trim((string) ($_POST['kode_cabang'] ?? ''));
            $nama = trim((string) ($_POST['nama_cabang'] ?? ''));
            $jenis = in_array($_POST['jenis'] ?? '', ['PUSAT', 'CABANG'], true) ? $_POST['jenis'] : 'CABANG';
            $alamat = trim((string) ($_POST['alamat'] ?? ''));
            $telepon = trim((string) ($_POST['telepon'] ?? ''));
            if ($kode === '' || $nama === '') {
                throw new InvalidArgumentException('Kode dan nama cabang wajib diisi.');
            }
            $values = ['kode' => $kode, 'nama' => $nama, 'jenis' => $jenis, 'alamat' => $alamat ?: null, 'telepon' => $telepon ?: null];
            if ($id) {
                $values['id'] = $id;
                $sql = 'UPDATE cabang SET kode_cabang=:kode,nama_cabang=:nama,jenis=:jenis,alamat=:alamat,telepon=:telepon WHERE id_cabang=:id';
            } else {
                $sql = 'INSERT INTO cabang (kode_cabang,nama_cabang,jenis,alamat,telepon) VALUES (:kode,:nama,:jenis,:alamat,:telepon)';
            }
            $pdo->prepare($sql)->execute($values);
            $success = $id ? 'Cabang berhasil diperbarui.' : 'Cabang berhasil ditambahkan.';
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = $exception->errorInfo[1] === 1062 ? 'Kode cabang sudah digunakan.' : ($exception->errorInfo[1] === 1451 ? 'Data tidak bisa dihapus karena sudah dipakai.' : 'Perubahan cabang gagal disimpan.');
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$branches = $pdo->query('SELECT * FROM cabang ORDER BY nama_cabang')->fetchAll();
$pageTitle = 'Cabang';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#branchModal"><i class="bi bi-plus-lg me-1"></i>Tambah Cabang</button></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Kode</th><th>Nama</th><th>Jenis</th><th>Alamat</th><th>Status</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($branches as $branch): ?><tr><td><?= htmlspecialchars($branch['kode_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td class="fw-semibold"><?= htmlspecialchars($branch['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($branch['jenis'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($branch['alamat'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $branch['status'] === 'AKTIF' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($branch['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-primary edit-branch" data-bs-toggle="modal" data-bs-target="#branchModal" data-branch="<?= htmlspecialchars(json_encode($branch), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button><form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id_cabang" value="<?= (int) $branch['id_cabang'] ?>"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="modal fade" id="branchModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Tambah Cabang</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id_cabang" id="id_cabang"><label class="form-label">Kode *</label><input class="form-control mb-3" name="kode_cabang" id="kode_cabang" required><label class="form-label">Nama *</label><input class="form-control mb-3" name="nama_cabang" id="nama_cabang" required><label class="form-label">Jenis</label><select class="form-select mb-3" name="jenis" id="jenis"><option value="CABANG">CABANG</option><option value="PUSAT">PUSAT</option></select><label class="form-label">Alamat</label><input class="form-control mb-3" name="alamat" id="alamat"><label class="form-label">Telepon</label><input class="form-control" name="telepon" id="telepon"></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan</button></div></form></div></div></div>
<script>document.querySelectorAll('.edit-branch').forEach(function (button) { button.addEventListener('click', function () { const item = JSON.parse(button.dataset.branch); document.querySelector('#branchModal .modal-title').textContent = 'Edit Cabang'; ['id_cabang','kode_cabang','nama_cabang','jenis','alamat','telepon'].forEach(function (field) { document.getElementById(field).value = item[field] ?? ''; }); }); }); document.getElementById('branchModal').addEventListener('hidden.bs.modal', function () { document.querySelector('#branchModal form').reset(); document.querySelector('#branchModal .modal-title').textContent = 'Tambah Cabang'; });</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
