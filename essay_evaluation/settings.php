<?php
// Koneksi ke database
function getConnection() {
    static $conn = null;
    
    if ($conn === null) {
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "essay_evaluation";

        $conn = new mysqli($servername, $username, $password, $dbname);

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }

    return $conn;
}

// Fungsi menambahkan kata ke tabel
function addWord($table, $word) {
    $conn = getConnection();
    $stmt = $conn->prepare("INSERT INTO $table (word) VALUES (?)");
    $escaped_word = htmlspecialchars($word, ENT_QUOTES, 'UTF-8');
    $stmt->bind_param("s", $escaped_word);
    return $stmt->execute();
}

// Fungsi mengambil kata dari tabel
function getWords($table) {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT * FROM $table ORDER BY id DESC");
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fungsi menghapus kata berdasarkan ID
function deleteWord($table, $id) {
    $conn = getConnection();
    $stmt = $conn->prepare("DELETE FROM $table WHERE id = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Menangani request form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['keyword'])) {
        addWord('keyword', $_POST['keyword']);
    } elseif (!empty($_POST['formal_word'])) {
        addWord('formal_Word', $_POST['formal_word']);
    } elseif (!empty($_POST['informal_word'])) {
        addWord('informal_Word', $_POST['informal_word']);
    }
}

// Menangani penghapusan data
if (isset($_GET['delete_keyword'])) {
    deleteWord('keyword', $_GET['delete_keyword']);
} elseif (isset($_GET['delete_formal'])) {
    deleteWord('formal_Word', $_GET['delete_formal']);
} elseif (isset($_GET['delete_informal'])) {
    deleteWord('informal_Word', $_GET['delete_informal']);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Penilaian Esai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #f4f6f9;
        }
        .main-content {
            max-width: 1200px;
            margin: 40px auto;
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        .btn-custom {
            background-color: #007bff;
            color: white;
        }
        .btn-custom:hover {
            background-color: #0056b3;
        }
        .form-control {
            border-radius: 8px;
        }
        .list-group-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
        }
        .list-group-item a {
            text-decoration: none;
        }
        .section-title {
            border-bottom: 2px solid #007bff;
            padding-bottom: 8px;
            margin-bottom: 20px;
            color: #007bff;
        }
        .footer {
            text-align: center;
            padding: 10px 20px;
            background: #f4f6f9;
            border-top: 1px solid #e5e5e5;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <h2 class="section-title">Pengaturan Penilaian Esai</h2>

        <!-- Form Input -->
        <form action="settings.php" method="post" class="mb-4">
            <div class="row">
                <div class="col-md-4">
                    <label for="keyword" class="form-label">Tambah Kata Kunci</label>
                    <input type="text" class="form-control" id="keyword" name="keyword" placeholder="Masukkan kata kunci...">
                </div>
                <div class="col-md-4">
                    <label for="formal_word" class="form-label">Tambah Kata Formal</label>
                    <input type="text" class="form-control" id="formal_word" name="formal_word" placeholder="Masukkan kata formal...">
                </div>
                <div class="col-md-4">
                    <label for="informal_word" class="form-label">Tambah Kata Informal</label>
                    <input type="text" class="form-control" id="informal_word" name="informal_word" placeholder="Masukkan kata informal...">
                </div>
            </div>
            <button type="submit" class="btn btn-custom mt-3">Simpan</button>
        </form>

        <!-- Daftar Kata -->
        <div>
            <h4>Daftar Kata Kunci</h4>
            <ul class="list-group mb-4">
                <?php
                $keywords = getWords('keyword');
                foreach ($keywords as $keyword) {
                    echo "<li class='list-group-item'>
                            <span>{$keyword['word']}</span>
                            <a href='?delete_keyword={$keyword['id']}' class='btn btn-sm btn-danger'>Hapus</a>
                          </li>";
                }
                ?>
            </ul>

            <h4>Daftar Kata Formal</h4>
            <ul class="list-group mb-4">
                <?php
                $formal_words = getWords('formal_Word');
                foreach ($formal_words as $word) {
                    echo "<li class='list-group-item'>
                            <span>{$word['word']}</span>
                            <a href='?delete_formal={$word['id']}' class='btn btn-sm btn-danger'>Hapus</a>
                          </li>";
                }
                ?>
            </ul>

            <h4>Daftar Kata Informal</h4>
            <ul class="list-group">
                <?php
                $informal_words = getWords('informal_Word');
                foreach ($informal_words as $word) {
                    echo "<li class='list-group-item'>
                            <span>{$word['word']}</span>
                            <a href='?delete_informal={$word['id']}' class='btn btn-sm btn-danger'>Hapus</a>
                          </li>";
                }
                ?>
            </ul>
        </div>
    </div>

    <div class="footer">
        <p>&copy; Kelompok 4 PBN B - 2024.</p>
    </div>
</body>
</html>