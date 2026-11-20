<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): void
{
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new RuntimeException('Sesi formulir tidak valid. Silakan coba lagi.');
    }
}

function requireLogin(): void
{
    if (empty($_SESSION['user'])) {
        $loginPath = strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/pages/') !== false ? '../login.php' : 'login.php';
        header('Location: ' . $loginPath);
        exit;
    }
}

function requireStaff(): void
{
    requireLogin();

    if (!in_array((string) ($_SESSION['user']['role'] ?? ''), ['ADMIN', 'PETUGAS'], true)) {
        header('Location: ../index.php');
        exit;
    }
}

function requireMember(): void
{
    requireLogin();

    if (($_SESSION['user']['role'] ?? '') !== 'ANGGOTA') {
        $prefix = strpos((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/pages/') !== false ? '../' : '';
        header('Location: ' . $prefix . 'index.php');
        exit;
    }
}

function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

function loginUser(string $username, string $password): bool
{
    $pdo = getDatabaseConnection();
    $statement = $pdo->prepare(
        'SELECT a.id_akun, a.username, a.password_hash, a.role, a.id_petugas, a.status,
                p.nama_petugas, p.id_cabang, p.status AS status_petugas,
                c.nama_cabang,
                a.id_anggota, m.nama_anggota, m.nomor_anggota, m.status AS status_anggota,
                m.id_cabang_daftar, cm.nama_cabang AS nama_cabang_anggota
         FROM akun_pengguna a
         LEFT JOIN petugas p ON p.id_petugas = a.id_petugas
         LEFT JOIN cabang c ON c.id_cabang = p.id_cabang
         LEFT JOIN anggota m ON m.id_anggota = a.id_anggota
         LEFT JOIN cabang cm ON cm.id_cabang = m.id_cabang_daftar
         WHERE a.username = :username'
    );
    $statement->execute(['username' => $username]);
    $account = $statement->fetch();

    if (!$account || $account['status'] !== 'AKTIF') {
        return false;
    }

    if ($account['role'] === 'PETUGAS' && $account['status_petugas'] !== 'AKTIF') {
        return false;
    }

    if ($account['role'] === 'ANGGOTA' && ($account['id_anggota'] === null || $account['status_anggota'] !== 'AKTIF')) {
        return false;
    }

    $storedHash = (string) $account['password_hash'];
    $isValid = false;

    if (str_starts_with($storedHash, '$2y$') || str_starts_with($storedHash, '$argon')) {
        $isValid = password_verify($password, $storedHash);
    } elseif (strlen($storedHash) === 64 && ctype_xdigit($storedHash)) {
        $isValid = hash_equals(strtolower($storedHash), hash('sha256', $password));
    }

    if (!$isValid) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id_akun' => (int) $account['id_akun'],
        'username' => $account['username'],
        'role' => $account['role'],
        'id_petugas' => $account['id_petugas'] !== null ? (int) $account['id_petugas'] : null,
        'nama_petugas' => $account['nama_petugas'],
        'id_cabang' => $account['id_cabang'] !== null ? (int) $account['id_cabang'] : null,
        'nama_cabang' => $account['nama_cabang'],
        'id_anggota' => $account['id_anggota'] !== null ? (int) $account['id_anggota'] : null,
        'nama_anggota' => $account['nama_anggota'],
        'nomor_anggota' => $account['nomor_anggota'],
        'status_anggota' => $account['status_anggota'],
        'id_cabang_anggota' => $account['id_cabang_daftar'] !== null ? (int) $account['id_cabang_daftar'] : null,
        'nama_cabang_anggota' => $account['nama_cabang_anggota'],
    ];

    $update = $pdo->prepare('UPDATE akun_pengguna SET terakhir_login = SYSDATETIME() WHERE id_akun = :id_akun');
    $update->execute(['id_akun' => $account['id_akun']]);

    return true;
}

function logoutUser(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
