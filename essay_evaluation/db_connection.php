<?php
$host = 'localhost'; // Host database, biasanya localhost jika Anda menggunakan XAMPP
$db = 'essay_evaluation'; // Nama database yang Anda gunakan
$user = 'root'; // Nama pengguna MySQL Anda
$pass = ''; // Kata sandi pengguna MySQL Anda
$charset = 'utf8mb4'; // Menggunakan charset utf8mb4 untuk mendukung karakter internasional

// Menyusun DSN (Data Source Name) untuk koneksi PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opsi untuk pengaturan PDO
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // Mengaktifkan mode error untuk PDO
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // Mengambil hasil dalam bentuk array asosiatif
    PDO::ATTR_EMULATE_PREPARES => false, // Menonaktifkan emulasi prepared statement
];

try {
    // Membuat koneksi PDO dengan menggunakan $dsn
    $pdo = new PDO($dsn, $user, $pass, $options); // Perbaiki di sini, gunakan $dsn bukan $db
    // echo "Koneksi berhasil!"; // Bisa ditambahkan untuk testing koneksi
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (\PDOException $e) {
    // Menangani error jika terjadi masalah dengan koneksi
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>