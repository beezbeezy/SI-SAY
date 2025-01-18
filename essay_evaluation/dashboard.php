<?php
// Koneksi ke database
try {
    $pdo = new PDO("mysql:host=localhost;dbname=essay_evaluation", "username", "password");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}

// Menghitung total esai
$stmt = $pdo->prepare("SELECT COUNT(*) FROM essays");
$stmt->execute();
$total_essays = $stmt->fetchColumn();

// Menghitung total pengguna
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$total_users = $stmt->fetchColumn();

// Menghitung rata-rata kata per esai
$stmt = $pdo->prepare("SELECT LENGTH(content) - LENGTH(REPLACE(content, ' ', '')) + 1 AS word_count FROM essays");
$stmt->execute();
$word_count_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_words = 0;
$total_essays_count = count($word_count_data);
foreach ($word_count_data as $row) {
    $total_words += $row['word_count'];
}
$average_words = $total_essays_count > 0 ? $total_words / $total_essays_count : 0;

// Menghitung total evaluasi selesai
$stmt = $pdo->prepare("SELECT COUNT(*) FROM evaluations WHERE status = 'selesai'");
$stmt->execute();
$completed_evaluations = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistem Penilaian Esai</title>
    <!-- Tambahkan link ke Bootstrap atau framework CSS lainnya -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .dashboard-stats {
            display: flex;
            gap: 20px;
            justify-content: space-between;
        }
        .stat-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 20px;
            border-radius: 5px;
            width: 22%;
        }
        .stat-card h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
        }
        .stat-card p {
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="text-center">Dashboard Sistem Penilaian Esai</h1>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Total Esai</h3>
                <p><?= $total_essays ?> esai</p>
            </div>
            <div class="stat-card">
                <h3>Total Pengguna</h3>
                <p><?= $total_users ?> pengguna</p>
            </div>
            <div class="stat-card">
                <h3>Rata-rata Kata per Esai</h3>
                <p><?= round($average_words, 2) ?> kata</p>
            </div>
            <div class="stat-card">
                <h3>Evaluasi Selesai</h3>
                <p><?= $completed_evaluations ?> evaluasi</p>
            </div>
        </div>
    </div>

    <!-- Tambahkan script Bootstrap atau library JS lainnya -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
