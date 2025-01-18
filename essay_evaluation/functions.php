<?php
// Konfigurasi koneksi database dengan prepared statements
function getConnection() {
    static $conn = null;

    if ($conn === null) {
        $servername = "localhost"; // Ganti dengan host database Anda
        $username = "root"; // Ganti dengan username database Anda
        $password = ""; // Ganti dengan password database Anda
        $dbname = "essay_evaluation"; // Ganti dengan nama database Anda

        // Membuat koneksi
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Validasi koneksi
        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }
    }

    return $conn;
}

// Fungsi untuk mengevaluasi esai
function evaluateEssay($essay) {
    $conn = getConnection();

    // Daftar query untuk mengambil data dari tabel
    $queries = [
        'keywords' => "SELECT word FROM keyword",
        'formal_words' => "SELECT word FROM formal_Word",
        'informal_words' => "SELECT word FROM informal_Word",
        'transition_words' => "SELECT word FROM transition_Word"
    ];

    $data = [];
    foreach ($queries as $key => $query) {
        $result = $conn->query($query);
        if (!$result) {
            throw new Exception("Query failed: " . $conn->error);
        }

        // Memasukkan hasil query ke dalam array
        while ($row = $result->fetch_assoc()) {
            $data[$key][] = $row['word'];
        }
    }

    // Proses evaluasi esai
    $words = str_word_count($essay, 1);
    $word_count = count($words);
    $keyword_count = array_sum(array_map(fn($kw) => substr_count(strtolower($essay), strtolower($kw)), $data['keywords'] ?? []));
    $unique_words = count(array_unique($words));
    $avg_word_length = array_sum(array_map('strlen', $words)) / max($word_count, 1);

    $sentences = preg_split('/[.!?]/', $essay, -1, PREG_SPLIT_NO_EMPTY);
    $avg_sentence_length = count($sentences) > 0 ? round($word_count / count($sentences), 2) : 0;

    // Menghitung kalimat kompleks
    $complex_connectives = ["karena", "meskipun", "walaupun", "sehingga", "namun", "dan", "atau", "tetapi", "agar", "supaya"];
    $complex_sentence_count = 0;
    foreach ($sentences as $sentence) {
        foreach ($complex_connectives as $conn) {
            if (stripos($sentence, $conn) !== false) {
                $complex_sentence_count++;
                break;
            }
        }
    }

    // Menilai formalitas
    $formal_count = array_sum(array_map(fn($fw) => substr_count(strtolower($essay), strtolower($fw)), $data['formal_words'] ?? []));
    $informal_count = array_sum(array_map(fn($iw) => substr_count(strtolower($essay), strtolower($iw)), $data['informal_words'] ?? []));
    
    $tone = "Netral";
    if ($formal_count > $informal_count) {
        $tone = "Formal";
    } elseif ($informal_count > $formal_count) {
        $tone = "Informal";
    }

    // Menandai kata transisi dalam esai
    $highlighted_essay = $essay;
    foreach ($data['transition_words'] ?? [] as $word) {
        $highlighted_essay = preg_replace("/\b(" . preg_quote($word, '/') . ")\b/i", "<mark>$1</mark>", $highlighted_essay);
    }

    // Mengembalikan hasil evaluasi
    return [
        'word_count' => $word_count,
        'keyword_count' => $keyword_count,
        'unique_words' => $unique_words,
        'avg_word_length' => round($avg_word_length, 2),
        'avg_sentence_length' => $avg_sentence_length,
        'complex_sentence_count' => $complex_sentence_count,
        'tone' => $tone,
        'highlighted_essay' => $highlighted_essay,
        'sentiment' => analyseSentiment($essay),
        'plagiarism' => checkPlagiarism($essay),
        'keyword_density' => keywordDensity($essay, $data['keywords']),
        'writing_style' => writingStyle($essay),
        'language_style' => languageStyleCheck($essay)
    ];
}

// Fungsi untuk mendapatkan total esai
function getTotalEssays() {
    $conn = getConnection();
    $query = "SELECT COUNT(*) AS total FROM essays";
    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return $total ?? 0;
    } else {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
}

// Fungsi untuk mendapatkan total pengguna
function getTotalUsers() {
    $conn = getConnection();
    $query = "SELECT COUNT(*) AS total FROM users";
    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $stmt->bind_result($total);
        $stmt->fetch();
        $stmt->close();
        return $total ?? 0;
    } else {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
}

// Fungsi untuk mendapatkan rata-rata jumlah kata per esai
function getAverageWordCount() {
    $conn = getConnection();
    $query = "SELECT AVG(LENGTH(essay) - LENGTH(REPLACE(essay, ' ', '')) + 1) AS avg_word_count FROM essays";
    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $stmt->bind_result($avg_word_count);
        $stmt->fetch();
        $stmt->close();
        return round($avg_word_count ?? 0);
    } else {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
}

// Fungsi untuk mendapatkan statistik pengguna dan esai
function getStatistics() {
    try {
        return [
            'totalEssays' => getTotalEssays(),
            'totalUsers' => getTotalUsers(),
            'avgWordCount' => getAverageWordCount(),
        ];
    } catch (Exception $e) {
        // Menangani error jika terjadi masalah dengan query
        return [
            'error' => $e->getMessage()
        ];
    }
}

// Fungsi untuk mengambil daftar esai
function getEssays() {
    $conn = getConnection();
    $query = "SELECT id, essay_title, essay FROM essays ORDER BY created_at DESC";
    if ($stmt = $conn->prepare($query)) {
        $stmt->execute();
        $stmt->bind_result($id, $essay_title, $essay);
        $essays = [];
        while ($stmt->fetch()) {
            $essays[] = [
                'id' => $id,
                'essay_title' => $essay_title,
                'essay' => $essay
            ];
        }
        $stmt->close();
        return $essays;
    } else {
        throw new Exception("Query preparation failed: " . $conn->error);
    }
}

// Fungsi untuk menganalisis sentimen esai
function analyseSentiment($essay) {
    if (stripos($essay, 'baik') !== false || stripos($essay, 'positif') !== false) {
        return 'Positif';
    } elseif (stripos($essay, 'buruk') !== false || stripos($essay, 'negatif') !== false) {
        return 'Negatif';
    } else {
        return 'Netral';
    }
}

// Fungsi untuk memeriksa plagiarisme
function checkPlagiarism($essay) {
    // Misalnya menggunakan API atau algoritma pencocokan teks
    $similarity = 0; // Hasil similarity dari API atau perhitungan
    if ($similarity > 80) { // Jika kemiripan lebih dari 80%
        return 'Terdeteksi Plagiarisme';
    }
    return 'Tidak Terdeteksi Plagiarisme';
}

// Fungsi untuk menghitung kepadatan kata kunci
function keywordDensity($essay, $keywords) {
    $word_count = str_word_count($essay, 1);
    $total_words = count($word_count);
    $keyword_count = 0;
    
    foreach ($keywords as $keyword) {
        $keyword_count += substr_count(strtolower($essay), strtolower($keyword));
    }
    
    return ($keyword_count / $total_words) * 100;
}

// Fungsi untuk menganalisis gaya penulisan esai
function writingStyle($essay) {
    $sentences = preg_split('/[.!?]/', $essay, -1, PREG_SPLIT_NO_EMPTY);
    $avg_sentence_length = count($sentences) > 0 ? count(str_word_count($essay, 1)) / count($sentences) : 0;
    
    if ($avg_sentence_length > 20) {
        return 'Gaya Penulisan Formal';
    } else {
        return 'Gaya Penulisan Santai';
    }
}

// Fungsi untuk memeriksa gaya bahasa (formalisasi)
function languageStyleCheck($essay) {
    $formal_count = substr_count(strtolower($essay), 'kesimpulannya') + substr_count(strtolower($essay), 'oleh karena itu');
    $informal_count = substr_count(strtolower($essay), 'gimana') + substr_count(strtolower($essay), 'eh');

    return $formal_count > $informal_count ? 'Formal' : 'Informal';
}
?>