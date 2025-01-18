<?php
session_start();

// Menangani halaman yang dipilih
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Daftar halaman yang valid
$valid_pages = ['home', 'evaluation', 'settings'];

// Jika halaman tidak valid, redirect ke halaman utama
if (!in_array($page, $valid_pages)) {
    $page = 'home';
}

// Impor fungsi database
require_once 'functions.php'; // Pastikan file ini berisi semua fungsi yang dibutuhkan.

// Fungsi untuk mendapatkan total evaluasi yang selesai
function getTotalEvaluations() {
    $conn = getConnection(); // Pastikan fungsi getConnection() ada di file functions.php
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM evaluations WHERE status = 'completed'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

// Fungsi untuk mendapatkan evaluasi terbaru
function getRecentEvaluations($limit) {
    $conn = getConnection(); // Pastikan fungsi getConnection() ada di file functions.php
    $stmt = $conn->prepare("SELECT e.essay_id, u.username, e.score, e.date_evaluated 
                            FROM evaluations e
                            JOIN users u ON e.user_id = u.id
                            ORDER BY e.date_evaluated DESC
                            LIMIT ?");
    $stmt->bind_param("i", $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat+Alternates:wght@400;600&display=swap" rel="stylesheet">
    <title>Dashboard Penilaian Esai</title>
    <style>
        body {
            font-family: 'Montserrat Alternates', sans-serif;
            margin: 0;
            background: #f0f8ff;
            color: #333;
        }

        #sidebar {
            width: 280px;
            height: 100vh;
            background-color: #007bff;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            overflow-y: auto;
            transition: transform 0.3s ease;
            border-radius: 0 20px 20px 0;
        }

        #sidebar.hidden {
            transform: translateX(-100%);
        }

        #sidebar a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
            transition: background-color 0.3s ease;
            font-size: 1.1em;
        }

        #sidebar a:hover {
            background-color: #0056b3;
        }

        #main-content {
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        #main-content.sidebar-hidden {
            margin-left: 0;
        }

        .toggle-sidebar-btn {
            position: fixed;
            top: 10px;
            left: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            z-index: 100;
            transition: background-color 0.3s ease;
        }

        .toggle-sidebar-btn:hover {
            background-color: #0056b3;
        }

        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 15px;
            background-color: #ffffff;
            padding: 20px;
            box-shadow: 0px 10px 15px rgba(0, 0, 0, 0.1);
        }

        .card:hover {
            transform: scale(1.05);
            box-shadow: 0px 15px 20px rgba(0, 0, 0, 0.2);
        }

        .card-header {
            background-color: #28a745;
            color: white;
            font-size: 1.3em;
            text-align: center;
            border-radius: 10px 10px 0 0;
            padding: 10px;
        }

        .card-body {
            padding: 20px;
        }

        .card-footer {
            text-align: center;
            margin-top: 20px;
        }

        .btn-primary {
            background-color: #007bff;
            border-color: #007bff;
            font-weight: bold;
            border-radius: 20px;
            padding: 15px 30px;
            font-size: 1.2em;
            transition: background-color 0.3s ease;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .section-header {
            font-size: 2.5em;
            margin-bottom: 20px;
            font-weight: bold;
            color: #007bff;
        }

        .highlight {
            color: #ff6f61;
            font-weight: bold;
        }

        .icon {
            font-size: 30px;
            margin-right: 20px;
        }

        .stats-card {
            background: #e9ecef;
            border: 1px solid #ced4da;
            margin-bottom: 20px;
            border-radius: 15px;
            box-shadow: 0px 5px 10px rgba(0, 0, 0, 0.1);
        }

        .stats-card .card-header {
            background-color: #343a40;
            color: white;
        }

        .stats-card .card-body {
            font-size: 1.2em;
            text-align: center;
        }

        .stats-card .card-footer {
            text-align: center;
            font-size: 1.1em;
            color: #007bff;
        }

        .textarea-style {
            width: 100%;
            padding: 15px;
            font-size: 1.2em;
            border-radius: 10px;
            border: 1px solid #007bff;
            resize: none;
            margin-bottom: 20px;
            background-color: #f7f7f7;
            transition: border-color 0.3s ease;
        }

        .textarea-style:focus {
            border-color: #28a745;
            background-color: #ffffff;
        }

        .evaluation-container {
            background-color: #f4f4f9;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0px 10px 20px rgba(0, 0, 0, 0.1);
            margin-top: 30px;
        }

        .evaluation-container h3 {
            font-size: 2.5em;
            color: #28a745;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- Tombol Toggle Sidebar -->
    <button class="toggle-sidebar-btn" id="toggleSidebarBtn">☰</button>

    <!-- Sidebar -->
    <div id="sidebar">
        <a href="?page=home"><i class="fas fa-tachometer-alt icon"></i> <strong>Utama<strong></a>
        <a href="?page=evaluation"><i class="fas fa-clipboard-list icon"></i> <strong>Sistem Evaluasi<strong></a>
        <a href="?page=settings"><i class="fas fa-cogs icon"></i> <strong>Pengaturan<strong></a>
    </div>

    <!-- Konten Utama -->
    <div id="main-content">
        <?php
        switch ($page) {
            case 'home':
                echo "<h1 class='section-header animation'>Selamat Datang di, <span class='highlight'>SI - SAY!</span></h1>";
                echo "<p>Selamat Datang di Website Sistem Penilaian Essay kami.</p>";
                echo "<p>Perkenalkan, Kami dari Kelas PBN B Kelompok 4 yang beranggotakan:</p>";
                echo "<p>1. Eny Lailatul Zulaikah (10211028)</p>";
                echo "<p>2. Meyfa Putri (10211050)</p>";
                echo "<p>3. Muhammad Abidzaar Rifqi Rabbani (10211052)</p>";
                echo "<p>4. Yulia Al-Zahrah Putri (10211090).</p>";
                echo "<p>Berikut adalah Project Tugas Besar dari Mata Kuliah Pemrosesan Bahasa Natural atau yang biasanya disebut NLP.</p>";
                echo "<p>Pada kesempatan ini, kami mengimplementasikan NLP ke dalam sebuah sistem yang dapat menilai sebuah essay berbasis website.</p>";
            
                // Tambahkan Riwayat Evaluasi
                echo "<div class='row mt-5'>";
                echo "<div class='col-md-12'>";
                echo "<h3>Riwayat Evaluasi Terbaru</h3>";
                $evaluations = getRecentEvaluations(5); // Ambil 5 evaluasi terakhir
                if (!empty($evaluations)) {
                    echo "<ul class='list-group'>";
                    foreach ($evaluations as $evaluation) {
                        echo "<li class='list-group-item'>";
                        echo "Esai ID: " . $evaluation['essay_id'] . " - Pengguna: " . $evaluation['username'] . 
                             " - Skor: " . $evaluation['score'] . 
                             " - Tanggal: " . date('d-m-Y', strtotime($evaluation['date_evaluated']));
                        echo "</li>";
                    }
                    echo "</ul>";
                } else {
                    echo "<p>Belum ada evaluasi yang dilakukan.</p>";
                }
                echo "</div>";
                echo "</div>";
                break;            

                case 'evaluation':
                    echo "<div class='evaluation-container'>";
                    echo "<h3><strong>Lakukan Penilaian pada Essay!</strong></h3>";
                    echo "<form id='evaluation-form' action='evaluation.php' method='POST'>";
                    echo "<textarea name='essay' rows='10' class='textarea-style' placeholder='Silahkan masukkan atau ketikkan Essay anda disini...'></textarea><br>";
                    echo "<input type='submit' value='Evaluasi' class='btn btn-primary mt-2'>";
                    echo "</form>";
                    // Animasi loading
                    echo "<div id='loading' style='display:none;'><img src='img/loading.gif' alt='loading...' /></div>";
                    echo "</div>";
                    break;                             
                    
                    case 'settings':
                        echo "<div class='settings-container'>";
                        echo "<h1 class='section-header'>Pengaturan</h1>";
                        echo "<p>Sesuaikan konfigurasi aplikasi sesuai kebutuhan Anda.</p>";
                    
                        // Tombol yang mengarahkan ke halaman pengaturan
                        echo "<button type='button' class='btn btn-primary' onclick=\"window.location.href='settings.php'\">Pergi ke Halaman Pengaturan</button>";
                        echo "</div>";                    
                    
                        echo "</div>";
                    
                        // Tambahkan pustaka eksternal jika diperlukan
                        echo "<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>";
                    
                        // Tambahkan skrip untuk tema dinamis (opsional)
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                const themeSelector = document.getElementById('theme');
                                themeSelector.addEventListener('change', function() {
                                    document.body.className = themeSelector.value === 'dark' ? 'dark-theme' : '';
                                });
                            });
                        </script>";
                    
                        // Gaya tema dinamis
                        echo "<style>
                            .dark-theme {
                                background-color: #121212;
                                color: #f5f5f5;
                            }
                            .dark-theme .form-control, 
                            .dark-theme .form-select {
                                background-color: #1e1e1e;
                                color: #f5f5f5;
                                border: 1px solid #333;
                            }
                        </style>";
                        break;                    

            default:
                echo "<h1>Halaman Tidak Ditemukan</h1>";
        }
        ?>
    </div>

    <!-- JavaScript -->
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const sidebar = document.getElementById('sidebar');
                    const mainContent = document.getElementById('main-content');
                    const toggleSidebarBtn = document.getElementById('toggleSidebarBtn');

                    toggleSidebarBtn.addEventListener('click', function () {
                        sidebar.classList.toggle('hidden');
                        mainContent.classList.toggle('sidebar-hidden');

                document.getElementById('evaluation-form').addEventListener('submit', function() {
                    document.getElementById('loading').style.display = 'block';  // Tampilkan animasi loading
                });
            });
        });
    </script>
</body>
</html>