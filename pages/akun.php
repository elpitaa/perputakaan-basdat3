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
        $id = filter_input(INPUT_POST, 'id_akun', FILTER_VALIDATE_INT);
        if ($action === 'status') {
            $pdo->prepare("UPDATE akun_pengguna SET status=CASE WHEN status='AKTIF' THEN 'NONAKTIF' ELSE 'AKTIF' END WHERE id_akun=:id AND username <> 'superadmin'")->execute(['id' => $id]);
            $success = 'Status akun berhasil diubah.';
        } elseif ($action === 'reset') {
            $hash = password_hash('password123', PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE akun_pengguna SET password_hash=:hash WHERE id_akun=:id AND username <> 'superadmin'")->execute(['hash' => $hash, 'id' => $id]);
            $success = 'Password direset menjadi password123.';
        } elseif ($action === 'save') {
            $username = trim((string) ($_POST['username'] ?? ''));
            $role = in_array($_POST['role'] ?? '', ['ADMIN', 'PETUGAS', 'ANGGOTA'], true) ? $_POST['role'] : 'PETUGAS';
            $password = (string) ($_POST['password'] ?? '');
            $staffId = filter_input(INPUT_POST, 'id_petugas', FILTER_VALIDATE_INT);
            $memberId = filter_input(INPUT_POST, 'id_anggota', FILTER_VALIDATE_INT);
            if ($username === '' || strlen($password) < 8) {
                throw new InvalidArgumentException('Username wajib diisi dan password minimal 8 karakter.');
            }
            if ($role === 'PETUGAS' && !$staffId) {
                throw new InvalidArgumentException('Akun PETUGAS harus terhubung ke petugas.');
            }
            if ($role === 'ANGGOTA' && !$memberId) {
                throw new InvalidArgumentException('Akun ANGGOTA harus terhubung ke anggota.');
            }
            $statement = $pdo->prepare('INSERT INTO akun_pengguna (username,password_hash,role,id_petugas,id_anggota) VALUES (:username,:hash,:role,:staff,:member)');
            $statement->execute([
                'username' => $username,
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'role' => $role,
                'staff' => $role === 'PETUGAS' ? $staffId : null,
                'member' => $role === 'ANGGOTA' ? $memberId : null,
            ]);
            $success = 'Akun pengguna berhasil ditambahkan.';
        }
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $error = ($exception->errorInfo[0] ?? '') === '23000'
            ? 'Username atau anggota/petugas tersebut sudah digunakan atau tidak valid.'
            : 'Perubahan akun gagal disimpan. Periksa log PHP untuk detail kesalahan.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}
$accounts = $pdo->query('SELECT a.id_akun,a.username,a.role,a.id_petugas,a.id_anggota,a.status,a.terakhir_login,p.nama_petugas,m.nama_anggota FROM akun_pengguna a LEFT JOIN petugas p ON p.id_petugas=a.id_petugas LEFT JOIN anggota m ON m.id_anggota=a.id_anggota ORDER BY a.username')->fetchAll();
$staff = $pdo->query("SELECT p.id_petugas,p.nama_petugas FROM petugas p LEFT JOIN akun_pengguna a ON a.id_petugas=p.id_petugas WHERE p.status='AKTIF' AND a.id_akun IS NULL ORDER BY p.nama_petugas")->fetchAll();
$members = $pdo->query("SELECT m.id_anggota,m.nomor_anggota,m.nama_anggota FROM anggota m LEFT JOIN akun_pengguna a ON a.id_anggota=m.id_anggota WHERE m.status='AKTIF' AND a.id_akun IS NULL ORDER BY m.nama_anggota")->fetchAll();
$pageTitle = 'Akun Pengguna';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-end mb-3"><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#accountModal"><i class="bi bi-plus-lg me-1"></i>Tambah Akun</button></div>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Username</th><th>Role</th><th>Petugas</th><th>Status</th><th>Terakhir Login</th><th class="text-end">Aksi</th></tr></thead><tbody>
<?php foreach ($accounts as $account): ?><tr><td class="fw-semibold"><?= htmlspecialchars($account['username'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($account['role'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($account['nama_petugas'] ?: $account['nama_anggota'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td><td><span class="badge text-bg-<?= $account['status'] === 'AKTIF' ? 'success' : 'secondary' ?>"><?= htmlspecialchars($account['status'], ENT_QUOTES, 'UTF-8') ?></span></td><td><?= htmlspecialchars((string) ($account['terakhir_login'] ?? '-'), ENT_QUOTES, 'UTF-8') ?></td><td class="text-end"><?php if ($account['username'] !== 'superadmin'): ?><form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_akun" value="<?= (int) $account['id_akun'] ?>"><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-outline-secondary" title="Reset password"><i class="bi bi-key"></i></button></form><form class="d-inline" method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id_akun" value="<?= (int) $account['id_akun'] ?>"><input type="hidden" name="action" value="status"><button class="btn btn-sm btn-outline-warning"><i class="bi bi-power"></i></button></form><?php endif; ?></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<div class="modal fade" id="accountModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><form method="post"><div class="modal-header"><h5 class="modal-title">Tambah Akun</h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="save"><label class="form-label">Username *</label><input class="form-control mb-3" name="username" required><label class="form-label">Password *</label><input class="form-control mb-3" name="password" type="password" minlength="8" required><label class="form-label">Role *</label><select class="form-select mb-3" name="role" id="role"><option>ADMIN</option><option selected>PETUGAS</option><option>ANGGOTA</option></select><label class="form-label">Petugas</label><select class="form-select mb-3" name="id_petugas" id="id_petugas"><option value="">Tidak ada</option><?php foreach ($staff as $item): ?><option value="<?= (int) $item['id_petugas'] ?>"><?= htmlspecialchars($item['nama_petugas'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><label class="form-label">Anggota</label><select class="form-select" name="id_anggota" id="id_anggota"><option value="">Tidak ada</option><?php foreach ($members as $item): ?><option value="<?= (int) $item['id_anggota'] ?>"><?= htmlspecialchars($item['nomor_anggota'] . ' - ' . $item['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select><div class="form-text">Pilih relasi sesuai role akun.</div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal" type="button">Batal</button><button class="btn btn-primary">Simpan</button></div></form></div></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
