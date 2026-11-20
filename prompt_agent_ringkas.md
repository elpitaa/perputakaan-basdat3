Bangun website **Sistem Informasi Perpustakaan Multi-Cabang** di folder proyek VS Code ini (langsung buat file, jangan hanya kirim potongan kode). Acuan utama: `db_perpustakaan.sql`.

## Teknologi
PHP Native 8.0.30 (jangan pakai fitur PHP 8.1+), MariaDB 10.4.32, PDO, Bootstrap 5, XAMPP.
Koneksi PDO pakai akun `perpus_app` (bukan root), `localhost`, `utf8mb4`, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`. Kredensial hanya di `config/database.php`.
Hak `perpus_app`: SELECT semua; INSERT/UPDATE/DELETE di tabel master (kategori_buku, buku, anggota, cabang, petugas, akun_pengguna); UPDATE pengaturan; hanya INSERT di 4 tabel transaksi; UPDATE denda hanya `status_bayar` dan `tanggal_bayar`; EXECUTE `sp_tambah_stok`. Jika fitur butuh hak lain, tanya dulu.

## Aturan database (wajib)
- Database `db_perpustakaan` sudah ada. Jangan buat DB baru, ubah nama tabel/kolom, atau hapus trigger/view/procedure/foreign key. Pakai view (`v_stok_cabang`, `v_riwayat_peminjaman`, `v_buku_terlambat`, `v_ringkasan_denda_anggota`) dan `sp_tambah_stok`.
- **Stok dan denda diurus trigger.** Jangan UPDATE stok, jangan INSERT denda, jangan UPDATE status peminjaman.
- **Login:** `password_hash` = SHA-256 hex 64 karakter (password contoh: `password123`). Verifikasi `hash_equals(hash('sha256',$input),$hashDb)`; jika diawali `$2y$`/`$argon` pakai `password_verify`. Setelah login sukses, ubah otomatis ke `password_hash()`. Akun harus AKTIF (dan petugas terkait AKTIF untuk role PETUGAS). Isi `terakhir_login`. Akun `superadmin` tidak punya petugas.
- **Peminjaman:** petugas dari akun login, cabang = cabang petugas (ADMIN tanpa petugas memilih petugas aktif). `no_transaksi` dibuat aplikasi, format `PJM-YYYYMMDD-NNN`, ulangi bila bentrok. Jatuh tempo = tanggal pinjam + `lama_pinjam_hari` dari tabel pengaturan. Header + semua detail dalam **satu transaksi PDO**. Jika trigger menolak (SQLSTATE 45000), rollback dan tampilkan pesan trigger secara ramah. Tampilkan hanya buku yang stoknya tersedia di cabang itu. Beri peringatan bila anggota punya denda BELUM_LUNAS.
- **Pengembalian:** `no_pengembalian` format `KMB-YYYYMMDD-NNN`. Satu header bisa berisi satu atau lebih buku dari satu transaksi (hanya yang belum kembali), kondisi BAIK/RUSAK/HILANG. Petugas harus dari cabang peminjaman; tanggal tidak boleh masa depan atau sebelum tanggal pinjam. Setelah simpan, tampilkan denda yang terbentuk.
- **Denda:** hanya boleh diubah menjadi `LUNAS` bersama `tanggal_bayar`.
- **Stok:** tambah hanya lewat `CALL sp_tambah_stok`. Tidak ada fitur kurangi/koreksi stok.
- **Hapus data:** tangkap error 1451 → "Data tidak bisa dihapus karena sudah dipakai". Anggota, petugas, cabang diberi tombol Nonaktifkan. Tabel transaksi dan denda tanpa tombol hapus.
- **Pengaturan:** validasi angka (denda ≥ 0, lama pinjam ≥ 1, maks buku ≥ 1, persen 0–100).

## Fitur
Dashboard (total judul, total eksemplar, anggota, cabang, peminjaman aktif, buku terlambat, ringkasan denda) · Buku (CRUD, cari judul/penulis, filter kategori) · Kategori · Anggota · Cabang · Stok (per cabang, total/tersedia/dipinjam, tambah stok) · Petugas · Akun Pengguna (ADMIN: tambah, role, aktif/nonaktif, reset password) · Peminjaman · Pengembalian · Denda (daftar, bayar) · Laporan (riwayat, terlambat, stok, denda anggota) · Pengaturan.

## Hak akses
- ADMIN: semua menu.
- PETUGAS: Dashboard, Laporan, Cari buku/stok, Peminjaman, Pengembalian, Bayar denda (hanya cabangnya), Anggota (tambah/ubah), Buku/Kategori/Stok (lihat saja). Tanpa akses: Cabang, Petugas, Akun Pengguna, Pengaturan.

## Keamanan
PDO prepared statements, validasi semua input, session login, wajib login di semua halaman, CSRF token di form, jangan tampilkan password hash.

## UI
Modern, bersih, responsif, tidak ramai. Sidebar kiri, navbar atas, kartu statistik di dashboard, tabel dengan pencarian dan pagination, form lewat modal atau halaman khusus. Warna navy, putih, biru; ikon konsisten.

## Struktur
`config/`, `assets/css`, `assets/js`, `includes/` (layout, auth), `pages/`, `index.php`, `login.php`, `logout.php`. Pisahkan koneksi, layout, dan logika halaman.

## Cara kerja
Kerjakan bertahap dan **berhenti setelah tiap tahap untuk saya uji**: (1) periksa SQL dan ringkas strukturnya, (2) koneksi, (3) login + layout, (4) dashboard, (5) CRUD, (6) peminjaman/pengembalian/denda, (7) laporan, (8) uji menyeluruh. Saya pemula: jelaskan sederhana dalam bahasa Indonesia dan beri cara menjalankan/menguji lewat XAMPP.

**Mulai dari Tahap 1. Periksa database dulu sebelum menulis kode. Jika ada yang tidak jelas, tanya.**
