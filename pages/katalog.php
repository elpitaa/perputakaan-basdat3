<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
requireMember();

$pdo = getDatabaseConnection();
$search = trim((string) ($_GET['q'] ?? ''));
$category = filter_input(INPUT_GET, 'kategori', FILTER_VALIDATE_INT);
$branch = filter_input(INPUT_GET, 'cabang', FILTER_VALIDATE_INT);
$detailId = filter_input(INPUT_GET, 'detail', FILTER_VALIDATE_INT);

$categories = $pdo->query('SELECT id_kategori, nama_kategori FROM kategori_buku ORDER BY nama_kategori')->fetchAll();
$branches = $pdo->query("SELECT id_cabang, kode_cabang, nama_cabang FROM cabang WHERE status='AKTIF' ORDER BY nama_cabang")->fetchAll();

$sql = "SELECT b.id_buku, b.isbn, b.judul, b.penulis, b.penerbit, b.tahun_terbit,
               k.nama_kategori,
               COALESCE(SUM(s.jumlah_total), 0) AS jumlah_total,
               COALESCE(SUM(s.jumlah_tersedia), 0) AS jumlah_tersedia,
               COUNT(s.id_cabang) AS jumlah_cabang
        FROM buku b
        INNER JOIN kategori_buku k ON k.id_kategori = b.id_kategori
        LEFT JOIN stok_buku_cabang s ON s.id_buku = b.id_buku
        LEFT JOIN cabang c ON c.id_cabang = s.id_cabang AND c.status = 'AKTIF'
        WHERE 1=1";
$params = [];
if ($search !== '') {
    $sql .= ' AND (b.judul LIKE :search OR b.penulis LIKE :search)';
    $params['search'] = '%' . $search . '%';
}
if ($category) {
    $sql .= ' AND b.id_kategori = :kategori';
    $params['kategori'] = $category;
}
if ($branch) {
    $sql .= ' AND s.id_cabang = :cabang';
    $params['cabang'] = $branch;
}
$sql .= ' GROUP BY b.id_buku, b.isbn, b.judul, b.penulis, b.penerbit, b.tahun_terbit, k.nama_kategori ORDER BY b.judul';
$statement = $pdo->prepare($sql);
$statement->execute($params);
$books = $statement->fetchAll();

$selectedBook = null;
$selectedStock = [];
if ($detailId) {
    $detailStatement = $pdo->prepare(
        'SELECT b.*, k.nama_kategori
         FROM buku b INNER JOIN kategori_buku k ON k.id_kategori = b.id_kategori
         WHERE b.id_buku = :id_buku'
    );
    $detailStatement->execute(['id_buku' => $detailId]);
    $selectedBook = $detailStatement->fetch() ?: null;
    if ($selectedBook) {
        $stockStatement = $pdo->prepare(
            "SELECT c.id_cabang, c.kode_cabang, c.nama_cabang, c.alamat, s.jumlah_total, s.jumlah_tersedia, s.lokasi_rak
             FROM stok_buku_cabang s
             INNER JOIN cabang c ON c.id_cabang = s.id_cabang
             WHERE s.id_buku = :id_buku AND c.status = 'AKTIF'
             ORDER BY c.nama_cabang"
        );
        $stockStatement->execute(['id_buku' => $detailId]);
        $selectedStock = $stockStatement->fetchAll();
    }
}

$pageTitle = 'Katalog Buku';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><p class="text-muted mb-0">Cari buku dan cek ketersediaannya di setiap cabang.</p></div>
</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-md-5"><label class="visually-hidden" for="q">Cari buku</label><input class="form-control" id="q" name="q" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Cari judul atau penulis"></div>
        <div class="col-md-3"><label class="visually-hidden" for="kategori">Kategori</label><select class="form-select" id="kategori" name="kategori"><option value="">Semua kategori</option><?php foreach ($categories as $item): ?><option value="<?= (int) $item['id_kategori'] ?>" <?= $category === (int) $item['id_kategori'] ? 'selected' : '' ?>><?= htmlspecialchars($item['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><label class="visually-hidden" for="cabang">Cabang</label><select class="form-select" id="cabang" name="cabang"><option value="">Semua cabang</option><?php foreach ($branches as $item): ?><option value="<?= (int) $item['id_cabang'] ?>" <?= $branch === (int) $item['id_cabang'] ? 'selected' : '' ?>><?= htmlspecialchars($item['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></div>
        <div class="col-md-1 d-grid"><button class="btn btn-primary" type="submit"><i class="bi bi-search"></i></button></div>
    </form>
</div></div>
<?php if ($selectedBook): ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start gap-3"><div><h2 class="h5 mb-1"><?= htmlspecialchars($selectedBook['judul'], ENT_QUOTES, 'UTF-8') ?></h2><p class="text-muted mb-3"><?= htmlspecialchars($selectedBook['penulis'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($selectedBook['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></p></div><a class="btn btn-sm btn-outline-secondary" href="katalog.php">Tutup</a></div>
    <div class="row g-2 small mb-3"><div class="col-md-3"><strong>ISBN:</strong> <?= htmlspecialchars((string) ($selectedBook['isbn'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div><div class="col-md-3"><strong>Penerbit:</strong> <?= htmlspecialchars((string) ($selectedBook['penerbit'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div><div class="col-md-3"><strong>Tahun:</strong> <?= htmlspecialchars((string) ($selectedBook['tahun_terbit'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></div></div>
    <h3 class="h6">Ketersediaan per cabang</h3>
    <div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Cabang</th><th>Alamat</th><th>Rak</th><th>Total</th><th>Tersedia</th><th>Status</th><th></th></tr></thead><tbody>
    <?php foreach ($selectedStock as $stock): ?><tr><td><?= htmlspecialchars($stock['nama_cabang'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($stock['alamat'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars((string) ($stock['lokasi_rak'] ?: '-'), ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $stock['jumlah_total'] ?></td><td><?= (int) $stock['jumlah_tersedia'] ?></td><td><span class="badge text-bg-<?= (int) $stock['jumlah_tersedia'] > 0 ? 'success' : 'secondary' ?>"><?= (int) $stock['jumlah_tersedia'] > 0 ? 'Tersedia' : 'Tidak tersedia' ?></span></td><td class="text-end"><?php if ((int) $stock['jumlah_tersedia'] > 0): ?><a class="btn btn-sm btn-primary" href="ajukan_peminjaman.php?buku=<?= (int) $selectedBook['id_buku'] ?>&cabang=<?= (int) $stock['id_cabang'] ?>">Ajukan</a><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$selectedStock): ?><tr><td colspan="7" class="text-muted text-center">Belum ada stok tercatat.</td></tr><?php endif; ?>
    </tbody></table></div>
</div></div>
<?php elseif ($detailId): ?><div class="alert alert-warning">Detail buku tidak ditemukan.</div><?php endif; ?>
<div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Judul</th><th>Penulis</th><th>Kategori</th><th>Stok Total</th><th>Tersedia</th><th class="text-end">Detail</th></tr></thead><tbody>
    <?php foreach ($books as $book): ?><tr><td class="fw-semibold"><?= htmlspecialchars($book['judul'], ENT_QUOTES, 'UTF-8') ?><br><small class="text-muted"><?= htmlspecialchars((string) ($book['isbn'] ?: 'ISBN tidak tersedia'), ENT_QUOTES, 'UTF-8') ?></small></td><td><?= htmlspecialchars($book['penulis'], ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars($book['nama_kategori'], ENT_QUOTES, 'UTF-8') ?></td><td><?= (int) $book['jumlah_total'] ?></td><td><span class="badge text-bg-<?= (int) $book['jumlah_tersedia'] > 0 ? 'success' : 'secondary' ?>"><?= (int) $book['jumlah_tersedia'] ?></span></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="?<?= http_build_query(array_filter(['q' => $search, 'kategori' => $category, 'cabang' => $branch, 'detail' => $book['id_buku']])) ?>">Lihat detail</a></td></tr><?php endforeach; ?>
    <?php if (!$books): ?><tr><td colspan="6" class="text-center text-muted py-4">Buku tidak ditemukan.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
