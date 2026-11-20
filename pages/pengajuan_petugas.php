<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireStaff();

$pdo = getDatabaseConnection();
$user = currentUser();
$isAdmin = $user['role'] === 'ADMIN';
$error = null;
$success = null;

function approvalTransactionNumber(PDO $pdo): string
{
    do {
        $number = 'PJM-' . date('Ymd') . '-' . random_int(100, 999);
        $check = $pdo->prepare('SELECT COUNT(*) FROM peminjaman WHERE no_transaksi = :number');
        $check->execute(['number' => $number]);
    } while ((int) $check->fetchColumn() > 0);

    return $number;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $requestId = filter_input(INPUT_POST, 'id_pengajuan', FILTER_VALIDATE_INT);
        $action = (string) ($_POST['action'] ?? '');
        $note = trim((string) ($_POST['catatan_petugas'] ?? ''));
        if (!$requestId || !in_array($action, ['approve', 'reject'], true)) {
            throw new InvalidArgumentException('Pengajuan atau tindakan tidak valid.');
        }
        if (strlen($note) > 255) {
            throw new InvalidArgumentException('Catatan maksimal 255 karakter.');
        }
        if (empty($user['id_petugas'])) {
            throw new RuntimeException('Akun ini belum terhubung ke data petugas sehingga tidak dapat memproses pengajuan.');
        }

        $pdo->beginTransaction();
        $requestStatement = $pdo->prepare(
            'SELECT pp.id_pengajuan, pp.no_pengajuan, pp.id_anggota, pp.id_cabang,
                    dp.id_buku, pp.status, b.judul
             FROM pengajuan_peminjaman pp
             INNER JOIN detail_pengajuan_peminjaman dp ON dp.id_pengajuan = pp.id_pengajuan
             INNER JOIN buku b ON b.id_buku = dp.id_buku
             WHERE pp.id_pengajuan = :id'
        );
        $requestStatement->execute(['id' => $requestId]);
        $request = $requestStatement->fetch();
        if (!$request || $request['status'] !== 'MENUNGGU') {
            throw new InvalidArgumentException('Pengajuan tidak ditemukan atau sudah diproses.');
        }
        if (!$isAdmin && (int) $request['id_cabang'] !== (int) $user['id_cabang']) {
            throw new RuntimeException('Pengajuan ini bukan milik cabang Anda.');
        }

        if ($action === 'reject') {
            $update = $pdo->prepare(
                "UPDATE pengajuan_peminjaman
                 SET status='DITOLAK', id_petugas=:petugas, tanggal_diproses=SYSDATETIME(),
                     catatan_petugas=:catatan
                 WHERE id_pengajuan=:id AND status='MENUNGGU'"
            );
            $update->execute([
                'petugas' => (int) $user['id_petugas'],
                'catatan' => $note !== '' ? $note : null,
                'id' => $requestId,
            ]);
            $pdo->commit();
            $success = 'Pengajuan berhasil ditolak.';
        } else {
            $branch = $pdo->prepare(
                "SELECT jumlah_tersedia FROM stok_buku_cabang
                 WHERE id_cabang=:cabang AND id_buku=:buku"
            );
            $branch->execute(['cabang' => $request['id_cabang'], 'buku' => $request['id_buku']]);
            $stock = $branch->fetch();
            if (!$stock || (int) $stock['jumlah_tersedia'] < 1) {
                throw new InvalidArgumentException('Stok buku sudah tidak tersedia. Pengajuan tidak dapat disetujui.');
            }

            $dueSetting = $pdo->query("SELECT nilai FROM pengaturan WHERE nama_pengaturan='lama_pinjam_hari'")->fetchColumn();
            $days = max(1, (int) $dueSetting);
            $date = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime($date . ' +' . $days . ' days'));
            $loanId = insertAndGetId($pdo,
                'INSERT INTO peminjaman
                 (no_transaksi, id_anggota, id_petugas, id_cabang, tanggal_pinjam, tanggal_jatuh_tempo, catatan)
                 VALUES (:nomor, :anggota, :petugas, :cabang, :tanggal, :jatuh_tempo, :catatan)',
                [
                    '_identity_column' => 'id_peminjaman',
                    'nomor' => approvalTransactionNumber($pdo),
                    'anggota' => $request['id_anggota'],
                    'petugas' => (int) $user['id_petugas'],
                    'cabang' => $request['id_cabang'],
                    'tanggal' => $date,
                    'jatuh_tempo' => $dueDate,
                    'catatan' => $note !== '' ? $note : 'Dibuat dari pengajuan ' . $request['no_pengajuan'],
                ]
            );
            $detail = $pdo->prepare('INSERT INTO detail_peminjaman (id_peminjaman, id_buku) VALUES (:peminjaman, :buku)');
            $detail->execute(['peminjaman' => $loanId, 'buku' => $request['id_buku']]);

            $update = $pdo->prepare(
                "UPDATE pengajuan_peminjaman
                 SET status='DISETUJUI', id_petugas=:petugas, tanggal_diproses=SYSDATETIME(),
                     catatan_petugas=:catatan
                 WHERE id_pengajuan=:id AND status='MENUNGGU'"
            );
            $update->execute([
                'petugas' => (int) $user['id_petugas'],
                'catatan' => $note !== '' ? $note : null,
                'id' => $requestId,
            ]);
            $pdo->commit();
            $success = 'Pengajuan disetujui dan transaksi peminjaman resmi berhasil dibuat.';
        }
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($exception->getMessage());
        $error = $exception instanceof PDOException && ($exception->errorInfo[0] ?? '') === '45000'
            ? $exception->getMessage()
            : $exception->getMessage();
    }
}

$sql = "SELECT pp.id_pengajuan, pp.no_pengajuan, pp.tanggal_pengajuan, pp.status,
               pp.catatan_anggota, pp.catatan_petugas, a.nomor_anggota,
               a.nama_anggota, b.judul, c.nama_cabang
        FROM pengajuan_peminjaman pp
        INNER JOIN detail_pengajuan_peminjaman dp ON dp.id_pengajuan = pp.id_pengajuan
        INNER JOIN anggota a ON a.id_anggota = pp.id_anggota
        INNER JOIN buku b ON b.id_buku = dp.id_buku
        INNER JOIN cabang c ON c.id_cabang = pp.id_cabang
        WHERE pp.status = 'MENUNGGU'";
$params = [];
if (!$isAdmin) {
    $sql .= ' AND pp.id_cabang = :cabang';
    $params['cabang'] = (int) $user['id_cabang'];
}
$sql .= ' ORDER BY pp.tanggal_pengajuan ASC, pp.id_pengajuan ASC';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$requests = $statement->fetchAll();

$pageTitle = 'Pengajuan Anggota';
require __DIR__ . '/../includes/header.php';
?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="card-body">
    <h2 class="h5 mb-1">Pengajuan Menunggu Verifikasi</h2>
    <p class="text-muted small">Setujui hanya jika buku tersedia dan anggota memenuhi aturan peminjaman.</p>
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
        <thead><tr><th>Nomor</th><th>Anggota</th><th>Buku</th><th>Cabang</th><th>Diajukan</th><th>Catatan</th><th class="text-end">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
            <tr>
                <td class="fw-semibold"><?= htmlspecialchars($request['no_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($request['nomor_anggota'] . ' - ' . $request['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($request['judul'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($request['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($request['tanggal_pengajuan'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($request['catatan_anggota'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td>
                <td class="text-end">
                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#request-<?= (int) $request['id_pengajuan'] ?>">Proses</button>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?><tr><td colspan="7" class="text-center text-muted py-4">Tidak ada pengajuan yang menunggu.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
</div></div>
<?php foreach ($requests as $request): ?>
<div class="modal fade" id="request-<?= (int) $request['id_pengajuan'] ?>" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <div class="modal-header"><h5 class="modal-title">Proses <?= htmlspecialchars($request['no_pengajuan'], ENT_QUOTES, 'UTF-8') ?></h5><button class="btn-close" data-bs-dismiss="modal" type="button"></button></div>
        <div class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id_pengajuan" value="<?= (int) $request['id_pengajuan'] ?>">
            <p class="mb-1"><strong>Anggota:</strong> <?= htmlspecialchars($request['nama_anggota'], ENT_QUOTES, 'UTF-8') ?></p>
            <p><strong>Buku:</strong> <?= htmlspecialchars($request['judul'], ENT_QUOTES, 'UTF-8') ?></p>
            <label class="form-label" for="catatan-<?= (int) $request['id_pengajuan'] ?>">Catatan petugas</label>
            <textarea class="form-control" id="catatan-<?= (int) $request['id_pengajuan'] ?>" name="catatan_petugas" maxlength="255" rows="3"></textarea>
        </div>
        <div class="modal-footer">
            <button class="btn btn-outline-danger" name="action" value="reject" type="submit">Tolak</button>
            <button class="btn btn-success" name="action" value="approve" type="submit">Setujui &amp; Buat Transaksi</button>
        </div>
    </form></div></div>
</div>
<?php endforeach; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
