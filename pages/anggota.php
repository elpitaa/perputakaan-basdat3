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
        $action = (string) ($_POST['action'] ?? '');
        $id = filter_input(INPUT_POST, 'id_anggota', FILTER_VALIDATE_INT);
        if ($action === 'status') {
            if (!$isAdmin) {
                $existing = $pdo->prepare('SELECT id_cabang_daftar FROM anggota WHERE id_anggota = :id');
                $existing->execute(['id' => $id]);
                if ((int) $existing->fetchColumn() !== (int) $user['id_cabang']) {
                    throw new RuntimeException('Anda hanya dapat mengubah status anggota di cabang sendiri.');
                }
            }
            $statement = $pdo->prepare("UPDATE anggota SET status = CASE WHEN status = 'AKTIF' THEN 'NONAKTIF' ELSE 'AKTIF' END WHERE id_anggota = :id");
            $statement->execute(['id' => $id]);
            $success = 'Status anggota berhasil diubah.';
        } elseif ($action === 'save') {
            if (!$isAdmin && $id) {
                $existing = $pdo->prepare('SELECT id_cabang_daftar FROM anggota WHERE id_anggota = :id');
                $existing->execute(['id' => $id]);
                $existingBranch = $existing->fetchColumn();
                if ((int) $existingBranch !== (int) $user['id_cabang']) {
                    throw new RuntimeException('Anda hanya dapat mengubah anggota di cabang sendiri.');
                }
            } elseif (!$isAdmin && !$id) {
                $_POST['id_cabang_daftar'] = (string) $user['id_cabang'];
            }
            $nama = trim((string) ($_POST['nama_anggota'] ?? ''));
            $nomor = trim((string) ($_POST['nomor_anggota'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $telepon = trim((string) ($_POST['telepon'] ?? ''));
            $alamat = trim((string) ($_POST['alamat'] ?? ''));
            $gender = in_array($_POST['jenis_kelamin'] ?? '', ['L', 'P'], true) ? $_POST['jenis_kelamin'] : null;
            $branch = $isAdmin ? filter_input(INPUT_POST, 'id_cabang_daftar', FILTER_VALIDATE_INT) : (int) $user['id_cabang'];
            $date = (string) ($_POST['tanggal_daftar'] ?? date('Y-m-d'));
            if ($nama === '' || $nomor === '' || !$branch || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                throw new InvalidArgumentException('Nomor, nama, cabang, dan tanggal daftar wajib diisi dengan benar.');
            }
            $values = ['nomor' => $nomor, 'nama' => $nama, 'gender' => $gender, 'email' => $email ?: null, 'telepon' => $telepon ?: null, 'alamat' => $alamat ?: null, 'tanggal' => $date, 'branch' => $branch];
            if ($id) {
                $values['id'] = $id;
                $sql = 'UPDATE anggota SET nomor_anggota=:nomor, nama_anggota=:nama, jenis_kelamin=:gender, email=:email, telepon=:telepon, alamat=:alamat, tanggal_daftar=:tanggal, id_cabang_daftar=:branch WHERE id_anggota=:id';
            } else {
                $sql = 'INSERT INTO anggota (nomor_anggota,nama_anggota,jenis_kelamin,email,telepon,alamat,tanggal_daftar,id_cabang_daftar) VALUES (:nomor,:nama,:gender,:email,:telepon,:alamat,:tanggal,:branch)';
            }
            $pdo->prepare($sql)->execute($values);
            $success = $id ? 'Anggota berhasil diperbarui.' : 'Anggota berhasil ditambahkan.';
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = $exception->errorInfo[1] === 1062 ? 'Nomor anggota atau email sudah digunakan.' : 'Perubahan anggota gagal disimpan.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = in_array($_GET['status'] ?? '', ['AKTIF', 'NONAKTIF', 'DIBLOKIR'], true) ? (string) $_GET['status'] : '';
$sql = 'SELECT a.*, c.nama_cabang FROM anggota a INNER JOIN cabang c ON c.id_cabang=a.id_cabang_daftar WHERE 1=1';
$params = [];
if ($search !== '') {
    $sql .= ' AND (a.nomor_anggota LIKE :q OR a.nama_anggota LIKE :q OR a.email LIKE :q)';
    $params['q'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $sql .= ' AND a.status = :status';
    $params['status'] = $statusFilter;
}
if (!$isAdmin) {
    $sql .= ' AND a.id_cabang_daftar = :branch';
    $params['branch'] = $user['id_cabang'];
}
$sql .= ' ORDER BY a.nama_anggota';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$members = $statement->fetchAll();
$summarySql = 'SELECT status, COUNT(*) AS jumlah FROM anggota WHERE 1=1';
$summaryParams = [];
if (!$isAdmin) {
    $summarySql .= ' AND id_cabang_daftar = :branch';
    $summaryParams['branch'] = $user['id_cabang'];
}
$summarySql .= ' GROUP BY status';
$summaryStatement = $pdo->prepare($summarySql);
$summaryStatement->execute($summaryParams);
$memberSummary = ['AKTIF' => 0, 'NONAKTIF' => 0, 'DIBLOKIR' => 0];
foreach ($summaryStatement->fetchAll() as $summary) {
    $memberSummary[$summary['status']] = (int) $summary['jumlah'];
}
$branches = $pdo->query("SELECT id_cabang,nama_cabang FROM cabang WHERE status='AKTIF' ORDER BY nama_cabang")->fetchAll();

$pageTitle = 'Anggota';
require __DIR__ . '/../includes/header.php';
?>
<div class="row g-3 mb-4">
    <div class="col-4"><div class="mini-stat-card"><span class="mini-stat-icon text-primary bg-primary-subtle"><i class="bi bi-people"></i></span><div><small>Total anggota</small><strong><?= number_format(array_sum($memberSummary)) ?></strong></div></div></div>
    <div class="col-4"><div class="mini-stat-card"><span class="mini-stat-icon text-success bg-success-subtle"><i class="bi bi-person-check"></i></span><div><small>Aktif</small><strong><?= number_format($memberSummary['AKTIF']) ?></strong></div></div></div>
    <div class="col-4"><div class="mini-stat-card"><span class="mini-stat-icon text-secondary bg-secondary-subtle"><i class="bi bi-person-dash"></i></span><div><small>Nonaktif</small><strong><?= number_format($memberSummary['NONAKTIF']) ?></strong></div></div></div>
</div>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <form class="member-toolbar" method="get"><div class="input-group"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input class="form-control" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari nomor, nama, email"><select class="form-select" name="status"><option value="">Semua status</option><option value="AKTIF" <?= $statusFilter === 'AKTIF' ? 'selected' : '' ?>>Aktif</option><option value="NONAKTIF" <?= $statusFilter === 'NONAKTIF' ? 'selected' : '' ?>>Nonaktif</option><option value="DIBLOKIR" <?= $statusFilter === 'DIBLOKIR' ? 'selected' : '' ?>>Diblokir</option></select><button class="btn btn-outline-primary" type="submit">Cari</button></div></form>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#memberModal"><i class="bi bi-plus-lg me-1"></i>Tambah Anggota</button>
</div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
<thead><tr><th>Nomor</th><th>Nama</th><th>Email/Telepon</th><th>Cabang</th><th>Status</th><th class="text-end">Aksi</th></tr></thead>
<tbody><?php foreach ($members as $member): ?><tr>
<td><span class="member-number"><?= htmlspecialchars($member['nomor_anggota'], ENT_QUOTES, 'UTF-8') ?></span></td><td><div class="fw-semibold"><?= htmlspecialchars($member['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></div><small class="text-muted"><?= $member['jenis_kelamin'] === 'L' ? 'Laki-laki' : ($member['jenis_kelamin'] === 'P' ? 'Perempuan' : 'Jenis kelamin belum diisi') ?></small></td>
<td><?= htmlspecialchars((string) ($member['email'] ?? '-'), ENT_QUOTES, 'UTF-8') ?><br><small class="text-muted"><?= htmlspecialchars((string) ($member['telepon'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></small></td>
<td><?= htmlspecialchars($member['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $member['status'] === 'AKTIF' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($member['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
<td class="text-end"><button class="btn btn-sm btn-outline-primary edit-member" data-bs-toggle="modal" data-bs-target="#memberModal" data-member="<?= htmlspecialchars(json_encode($member), ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
<form class="d-inline" method="post" onsubmit="return confirm('Ubah status anggota ini?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="status"><input type="hidden" name="id_anggota" value="<?= (int) $member['id_anggota'] ?>"><button class="btn btn-sm btn-outline-<?= $member['status'] === 'AKTIF' ? 'warning' : 'success' ?>" title="<?= $member['status'] === 'AKTIF' ? 'Nonaktifkan anggota' : 'Aktifkan anggota' ?>"><i class="bi bi-person-<?= $member['status'] === 'AKTIF' ? 'dash' : 'check' ?>"></i></button></form></td>
</tr><?php endforeach; ?><?php if (!$members): ?><tr><td class="text-center text-muted py-4" colspan="6">Belum ada data anggota.</td></tr><?php endif; ?></tbody>
</table></div></div>
<div class="modal fade" id="memberModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post">
<div class="modal-header"><h5 class="modal-title">Tambah Anggota</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><input type="hidden" id="id_anggota" name="id_anggota">
<div class="row g-3"><div class="col-md-4"><label class="form-label">Nomor Anggota *</label><input class="form-control" id="nomor_anggota" name="nomor_anggota" required></div><div class="col-md-8"><label class="form-label">Nama *</label><input class="form-control" id="nama_anggota" name="nama_anggota" required></div>
<div class="col-md-4"><label class="form-label">Jenis Kelamin</label><select class="form-select" id="jenis_kelamin" name="jenis_kelamin"><option value="">Pilih</option><option value="L">Laki-laki</option><option value="P">Perempuan</option></select></div><div class="col-md-4"><label class="form-label">Email</label><input class="form-control" id="email" name="email" type="email"></div><div class="col-md-4"><label class="form-label">Telepon</label><input class="form-control" id="telepon" name="telepon"></div>
<div class="col-md-8"><label class="form-label">Alamat</label><input class="form-control" id="alamat" name="alamat"></div><div class="col-md-4"><label class="form-label">Tanggal Daftar *</label><input class="form-control" id="tanggal_daftar" name="tanggal_daftar" type="date" value="<?= date('Y-m-d') ?>" required></div>
<div class="col-md-6"><label class="form-label">Cabang *</label><select class="form-select" id="id_cabang_daftar" name="id_cabang_daftar" required <?php if (!$isAdmin): ?>disabled<?php endif; ?>><option value="">Pilih</option><?php foreach ($branches as $branch): ?><option value="<?= (int) $branch['id_cabang'] ?>"><?= htmlspecialchars($branch['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div></div>
</div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan</button></div>
</form></div></div></div>
<script>
document.querySelectorAll('.edit-member').forEach(function (button) { button.addEventListener('click', function () { const item = JSON.parse(button.dataset.member); document.querySelector('#memberModal .modal-title').textContent = 'Edit Anggota'; ['id_anggota','nomor_anggota','nama_anggota','jenis_kelamin','email','telepon','alamat','tanggal_daftar','id_cabang_daftar'].forEach(function (field) { document.getElementById(field).value = item[field] ?? ''; }); }); });
document.getElementById('memberModal').addEventListener('hidden.bs.modal', function () { document.querySelector('#memberModal form').reset(); document.querySelector('#memberModal .modal-title').textContent = 'Tambah Anggota'; });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
