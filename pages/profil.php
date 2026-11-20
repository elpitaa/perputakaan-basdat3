<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$pdo = getDatabaseConnection();
$user = currentUser();
$isMember = $user['role'] === 'ANGGOTA';
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $username = trim((string) ($_POST['username'] ?? ''));
        $name = trim((string) ($_POST['nama_petugas'] ?? $_POST['nama_anggota'] ?? ''));
        $phone = trim((string) ($_POST['telepon'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $address = trim((string) ($_POST['alamat'] ?? ''));
        $newPassword = (string) ($_POST['password_baru'] ?? '');
        $confirmation = (string) ($_POST['konfirmasi_password'] ?? '');

        if ($username === '' || !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username)) {
            throw new InvalidArgumentException('Username wajib 3-50 karakter dan hanya boleh berisi huruf, angka, titik, garis bawah, atau tanda hubung.');
        }
        if ($newPassword !== '' && (strlen($newPassword) < 8 || $newPassword !== $confirmation)) {
            throw new InvalidArgumentException('Password baru minimal 8 karakter dan konfirmasi harus sama.');
        }
        if ($isMember && ($name === '' || strlen($name) > 100)) {
            throw new InvalidArgumentException('Nama anggota wajib diisi dan maksimal 100 karakter.');
        }
        if ($isMember && $email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Format email tidak valid.');
        }
        if ($isMember && strlen($phone) > 20) {
            throw new InvalidArgumentException('Nomor telepon maksimal 20 karakter.');
        }
        if ($isMember && strlen($address) > 255) {
            throw new InvalidArgumentException('Alamat maksimal 255 karakter.');
        }

        $pdo->beginTransaction();
        $accountUpdate = $pdo->prepare('UPDATE akun_pengguna SET username=:username' . ($newPassword !== '' ? ', password_hash=:password_hash' : '') . ' WHERE id_akun=:id_akun');
        $values = ['username' => $username, 'id_akun' => $user['id_akun']];
        if ($newPassword !== '') {
            $values['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $accountUpdate->execute($values);

        if ($isMember) {
            $memberUpdate = $pdo->prepare(
                'UPDATE anggota SET nama_anggota=:name, email=:email, telepon=:phone, alamat=:alamat
                 WHERE id_anggota=:id_anggota'
            );
            $memberUpdate->execute([
                'name' => $name,
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'alamat' => $address !== '' ? $address : null,
                'id_anggota' => $user['id_anggota'],
            ]);
        } elseif ($user['id_petugas'] !== null) {
            if ($name === '') {
                throw new InvalidArgumentException('Nama petugas wajib diisi.');
            }
            $staffUpdate = $pdo->prepare('UPDATE petugas SET nama_petugas=:name, telepon=:phone WHERE id_petugas=:id');
            $staffUpdate->execute(['name' => $name, 'phone' => $phone !== '' ? $phone : null, 'id' => $user['id_petugas']]);
        }
        $pdo->commit();

        $_SESSION['user']['username'] = $username;
        if ($isMember) {
            $_SESSION['user']['nama_anggota'] = $name;
        } elseif ($user['id_petugas'] !== null) {
            $_SESSION['user']['nama_petugas'] = $name;
        }
        $success = 'Profil berhasil diperbarui.';
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = ($exception->errorInfo[1] ?? null) === 1062 ? 'Username sudah digunakan.' : 'Profil gagal diperbarui.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$statement = $pdo->prepare(
    'SELECT a.username, a.role, p.nama_petugas, p.telepon, c.nama_cabang,
            m.nama_anggota, m.email, m.alamat, m.telepon AS telepon_anggota,
            cm.nama_cabang AS nama_cabang_anggota
     FROM akun_pengguna a
     LEFT JOIN petugas p ON p.id_petugas=a.id_petugas
     LEFT JOIN cabang c ON c.id_cabang=p.id_cabang
     LEFT JOIN anggota m ON m.id_anggota=a.id_anggota
     LEFT JOIN cabang cm ON cm.id_cabang=m.id_cabang_daftar
     WHERE a.id_akun=:id'
);
$statement->execute(['id' => $user['id_akun']]);
$profile = $statement->fetch() ?: [];
$pageTitle = 'Profil Saya';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm profile-summary">
            <div class="card-body text-center p-4">
                <div class="profile-avatar"><?= strtoupper(substr((string) $profile['username'], 0, 1)) ?></div>
                <h2 class="h5 mt-3 mb-1"><?= htmlspecialchars((string) ($isMember ? ($profile['nama_anggota'] ?: $profile['username']) : ($profile['nama_petugas'] ?: $profile['username'])), ENT_QUOTES, 'UTF-8') ?></h2>
                <span class="badge text-bg-<?= $profile['role'] === 'ADMIN' ? 'primary' : ($profile['role'] === 'ANGGOTA' ? 'info' : 'success') ?>"><?= htmlspecialchars($profile['role'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php $profileBranch = $isMember ? $profile['nama_cabang_anggota'] : $profile['nama_cabang']; ?>
                <?php if (!empty($profileBranch)): ?><p class="text-muted small mt-3 mb-0"><i class="bi bi-building me-1"></i><?= htmlspecialchars($profileBranch, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm"><div class="card-body p-4">
            <h2 class="h5 mb-4">Pengaturan Profil</h2>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">Username *</label><input class="form-control" name="username" value="<?= htmlspecialchars((string) $profile['username'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Role</label><input class="form-control" value="<?= htmlspecialchars($profile['role'], ENT_QUOTES, 'UTF-8') ?>" disabled></div>
                    <?php if ($isMember): ?>
                        <div class="col-md-8"><label class="form-label">Nama Anggota *</label><input class="form-control" name="nama_anggota" value="<?= htmlspecialchars((string) $profile['nama_anggota'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Email</label><input class="form-control" name="email" type="email" value="<?= htmlspecialchars((string) ($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="col-md-5"><label class="form-label">Telepon</label><input class="form-control" name="telepon" value="<?= htmlspecialchars((string) ($profile['telepon_anggota'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                        <div class="col-md-7"><label class="form-label">Alamat</label><input class="form-control" name="alamat" value="<?= htmlspecialchars((string) ($profile['alamat'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <?php elseif ($user['id_petugas'] !== null): ?>
                        <div class="col-md-8"><label class="form-label">Nama *</label><input class="form-control" name="nama_petugas" value="<?= htmlspecialchars((string) $profile['nama_petugas'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                        <div class="col-md-4"><label class="form-label">Telepon</label><input class="form-control" name="telepon" value="<?= htmlspecialchars((string) ($profile['telepon'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <?php else: ?>
                        <div class="col-12"><div class="form-text">Akun ADMIN ini tidak terhubung ke data petugas. Username digunakan sebagai nama tampilan.</div></div>
                    <?php endif; ?>
                    <div class="col-12"><hr><h3 class="h6">Ganti Password <small class="text-muted">(kosongkan jika tidak ingin mengganti)</small></h3></div>
                    <div class="col-md-6"><label class="form-label">Password Baru</label><input class="form-control" name="password_baru" type="password" minlength="8" autocomplete="new-password"></div>
                    <div class="col-md-6"><label class="form-label">Konfirmasi Password</label><input class="form-control" name="konfirmasi_password" type="password" minlength="8" autocomplete="new-password"></div>
                </div>
                <button class="btn btn-primary mt-4" type="submit"><i class="bi bi-save me-1"></i>Simpan Perubahan</button>
            </form>
        </div></div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
