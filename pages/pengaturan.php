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
$rules = [
    'denda_per_hari' => ['label' => 'Denda per hari', 'min' => 0, 'max' => null, 'integer' => false],
    'lama_pinjam_hari' => ['label' => 'Lama pinjam (hari)', 'min' => 1, 'max' => null, 'integer' => true],
    'maks_buku_per_anggota' => ['label' => 'Maksimal buku per anggota', 'min' => 1, 'max' => null, 'integer' => true],
    'persen_denda_hilang' => ['label' => 'Persentase denda hilang', 'min' => 0, 'max' => 100, 'integer' => false],
    'persen_denda_rusak' => ['label' => 'Persentase denda rusak', 'min' => 0, 'max' => 100, 'integer' => false],
];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $pdo->beginTransaction();
        $update = $pdo->prepare('UPDATE pengaturan SET nilai=:value WHERE nama_pengaturan=:name');
        foreach ($rules as $name => $rule) {
            $raw = trim((string) ($_POST[$name] ?? ''));
            if ($raw === '' || !is_numeric($raw) || ($rule['integer'] && filter_var($raw, FILTER_VALIDATE_INT) === false)) {
                throw new InvalidArgumentException($rule['label'] . ' harus berupa angka yang valid.');
            }
            $value = (float) $raw;
            if ($value < $rule['min'] || ($rule['max'] !== null && $value > $rule['max'])) {
                throw new InvalidArgumentException($rule['label'] . ' berada di luar batas yang diizinkan.');
            }
            $update->execute(['value' => $rule['integer'] ? (string) (int) $value : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.'), 'name' => $name]);
        }
        $pdo->commit();
        $success = 'Pengaturan berhasil disimpan.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $error = $exception->getMessage();
    }
}
$settings = $pdo->query('SELECT nama_pengaturan,nilai,keterangan FROM pengaturan ORDER BY nama_pengaturan')->fetchAll();
$settingMap = [];
foreach ($settings as $setting) { $settingMap[$setting['nama_pengaturan']] = $setting; }
$pageTitle = 'Pengaturan';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="card-body"><form method="post">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
<?php foreach ($rules as $name => $rule): ?><div class="row align-items-center mb-3"><label class="col-md-4 col-form-label" for="<?= $name ?>"><?= htmlspecialchars($rule['label'], ENT_QUOTES, 'UTF-8') ?></label><div class="col-md-4"><input class="form-control" id="<?= $name ?>" name="<?= $name ?>" type="number" min="<?= $rule['min'] ?>"<?= $rule['max'] !== null ? ' max="' . $rule['max'] . '"' : '' ?><?= !$rule['integer'] ? ' step="0.01"' : ' step="1"' ?> value="<?= htmlspecialchars((string) ($settingMap[$name]['nilai'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div><div class="col-md-4 small text-muted"><?= htmlspecialchars((string) ($settingMap[$name]['keterangan'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div></div><?php endforeach; ?>
<button class="btn btn-primary" type="submit"><i class="bi bi-save me-1"></i>Simpan Pengaturan</button>
</form></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
