<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$user = currentUser();
$memberId = (int) $user['id_anggota'];
$bookId = filter_input(INPUT_GET, 'buku', FILTER_VALIDATE_INT);
$branchId = filter_input(INPUT_GET, 'cabang', FILTER_VALIDATE_INT);
$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $bookId = filter_input(INPUT_POST, 'id_buku', FILTER_VALIDATE_INT);
        $branchId = filter_input(INPUT_POST, 'id_cabang', FILTER_VALIDATE_INT);
        $note = trim((string) ($_POST['catatan'] ?? ''));
        if (!$bookId || !$branchId) {
            throw new InvalidArgumentException('Buku dan cabang wajib dipilih.');
        }
        if (strlen($note) > 255) {
            throw new InvalidArgumentException('Catatan maksimal 255 karakter.');
        }

        $pdo->beginTransaction();
        $memberStatement = $pdo->prepare(
            "SELECT status FROM anggota WHERE id_anggota = :id AND status = 'AKTIF'"
        );
        $memberStatement->execute(['id' => $memberId]);
        if (!$memberStatement->fetch()) {
            throw new InvalidArgumentException('Status anggota tidak memungkinkan untuk mengajukan peminjaman.');
        }

        $fineStatement = $pdo->prepare(
            "SELECT COALESCE(denda_belum_lunas, 0) FROM v_ringkasan_denda_anggota WHERE id_anggota = :id"
        );
        $fineStatement->execute(['id' => $memberId]);
        if ((float) $fineStatement->fetchColumn() > 0) {
            throw new InvalidArgumentException('Pengajuan tidak dapat dibuat karena masih ada denda yang belum lunas.');
        }

        $settingStatement = $pdo->prepare(
            "SELECT TRY_CONVERT(INT, nilai) FROM pengaturan WHERE nama_pengaturan = 'maks_buku_per_anggota'"
        );
        $settingStatement->execute();
        $maximum = (int) $settingStatement->fetchColumn();
        $activeStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM detail_peminjaman dm
             INNER JOIN peminjaman p ON p.id_peminjaman = dm.id_peminjaman
             LEFT JOIN detail_pengembalian dk ON dk.id_detail_peminjaman = dm.id_detail_peminjaman
             WHERE p.id_anggota = :id AND dk.id_detail_pengembalian IS NULL"
        );
        $activeStatement->execute(['id' => $memberId]);
        $pendingStatement = $pdo->prepare(
            "SELECT COUNT(*)
             FROM detail_pengajuan_peminjaman dp
             INNER JOIN pengajuan_peminjaman pp ON pp.id_pengajuan = dp.id_pengajuan
             WHERE pp.id_anggota = :id AND pp.status = 'MENUNGGU'"
        );
        $pendingStatement->execute(['id' => $memberId]);
        if ((int) $activeStatement->fetchColumn() + (int) $pendingStatement->fetchColumn() >= $maximum) {
            throw new InvalidArgumentException('Batas maksimal peminjaman sudah tercapai, termasuk pengajuan yang sedang menunggu.');
        }

        $stockStatement = $pdo->prepare(
            "SELECT s.jumlah_tersedia
             FROM stok_buku_cabang s
             INNER JOIN cabang c ON c.id_cabang = s.id_cabang
             WHERE s.id_buku = :buku AND s.id_cabang = :cabang AND c.status = 'AKTIF'"
        );
        $stockStatement->execute(['buku' => $bookId, 'cabang' => $branchId]);
        $stock = $stockStatement->fetch();
        if (!$stock || (int) $stock['jumlah_tersedia'] < 1) {
            throw new InvalidArgumentException('Buku tidak tersedia di cabang yang dipilih.');
        }

        $number = 'PGJ-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(2)));
        $headerId = insertAndGetId($pdo,
            'INSERT INTO pengajuan_peminjaman
             (no_pengajuan, id_anggota, id_cabang, catatan_anggota)
             VALUES (:nomor, :anggota, :cabang, :catatan)',
            [
                '_identity_column' => 'id_pengajuan',
                'nomor' => $number,
                'anggota' => $memberId,
                'cabang' => $branchId,
                'catatan' => $note !== '' ? $note : null,
            ]
        );
        $detailStatement = $pdo->prepare(
            'INSERT INTO detail_pengajuan_peminjaman (id_pengajuan, id_buku)
             VALUES (:pengajuan, :buku)'
        );
        $detailStatement->execute([
            'pengajuan' => $headerId,
            'buku' => $bookId,
        ]);
        $pdo->commit();
        $success = 'Pengajuan berhasil dikirim dan menunggu verifikasi petugas.';
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = 'Pengajuan gagal disimpan. Pastikan migrasi pengajuan sudah dijalankan.';
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    }
}

$bookStatement = $pdo->prepare(
    'SELECT b.id_buku, b.judul, b.penulis
     FROM buku b WHERE b.id_buku = :id'
);
$bookStatement->execute(['id' => $bookId ?: 0]);
$book = $bookStatement->fetch() ?: null;
$branchStatement = $pdo->prepare(
    "SELECT c.id_cabang, c.nama_cabang, c.alamat, s.jumlah_tersedia
     FROM stok_buku_cabang s
     INNER JOIN cabang c ON c.id_cabang = s.id_cabang
     WHERE s.id_buku = :buku AND c.status = 'AKTIF'
     ORDER BY c.nama_cabang"
);
$branchStatement->execute(['buku' => $bookId ?: 0]);
$stockByBranch = $branchStatement->fetchAll();

$pageTitle = 'Ajukan Peminjaman';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?> <a href="pengajuan_anggota.php" class="alert-link">Lihat pengajuan</a></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if (!$book): ?>
    <div class="alert alert-warning">Buku yang dipilih tidak ditemukan.</div>
<?php else: ?>
<div class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5"><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?></h2>
    <p class="text-muted"><?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?></p>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="id_buku" value="<?= (int) $book['id_buku'] ?>">
        <div class="mb-3"><label class="form-label" for="id_cabang">Pilih cabang pengambilan *</label><select class="form-select" id="id_cabang" name="id_cabang" required><option value="">Pilih cabang</option><?php foreach ($stockByBranch as $stock): ?><option value="<?= (int) $stock['id_cabang'] ?>" <?= $branchId === (int) $stock['id_cabang'] ? 'selected' : '' ?> <?= (int) $stock['jumlah_tersedia'] < 1 ? 'disabled' : '' ?>><?= htmlspecialchars($stock['nama_cabang'], ENT_QUOTES, 'UTF-8') ?> — tersedia <?= (int) $stock['jumlah_tersedia'] ?></option><?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label" for="catatan">Catatan (opsional)</label><textarea class="form-control" id="catatan" name="catatan" maxlength="255" rows="3"></textarea></div>
        <div class="alert alert-info small">Pengajuan tidak langsung mengurangi stok. Petugas akan memeriksa dan mencatat transaksi resmi jika disetujui.</div>
        <button class="btn btn-primary" type="submit"><i class="bi bi-send me-1"></i>Kirim Pengajuan</button>
        <a class="btn btn-outline-secondary" href="katalog.php?detail=<?= (int) $book['id_buku'] ?>">Kembali ke katalog</a>
    </form>
</div></div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
