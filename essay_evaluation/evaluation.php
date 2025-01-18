<?php
// Mengimpor koneksi database
include 'db_connection.php';

// Mengimpor konfigurasi API Key
require 'config.php';

    // Setup permintaan ke OpenAI API
    if (isset($essay)) {
        $api_key = OPENAI_API_KEY;  // Menggunakan API Key dari file config.php
        $url = "https://api.openai.com/v1/completions";
        $data = [
            "model" => "text-davinci-003",  // Pilih model OpenAI
            "prompt" => "Analyze this essay: " . $essay,  // Prompt untuk analisis
            "max_tokens" => 150,  // Jumlah token maksimum untuk respons
            "temperature" => 0.7  // Variasi output
        ];

        $headers = [
            "Content-Type: application/json",
            "Authorization: Bearer $api_key"
        ];

        // Inisialisasi cURL untuk mengirim permintaan ke API
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Kirim permintaan dan dapatkan respon
        $response = curl_exec($ch);
        if(curl_errno($ch)) {
            echo "cURL Error: " . curl_error($ch);
        }
        curl_close($ch);

        // Proses dan tampilkan hasil dari API
        $result = json_decode($response, true);
        $hasil_evaluasi = $result['choices'][0]['text'];  // Ambil hasil analisis
    } else {
        $error_message = "Selamat! Essay yang dikirim tidak terdapat kesamaan dengan Essay yang ada dalam Database!.";
    }

    // Fungsi untuk mengambil data korpus dari database
    function getCorpora() {
        global $pdo;
        $stmt = $pdo->prepare("SELECT * FROM corpora");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Fungsi untuk membandingkan esai dengan korpus
    function compareWithCorpora($essay) {
        $corpora = getCorpora();  // Ambil data korpus dari database
        $keywords = preg_split('/\s+/', strtolower($essay));  // Tokenisasi esai

        $matches = [];
        $total_words = count($keywords);  // Hitung jumlah total kata dalam esai

        foreach ($corpora as $corpus) {
            $corpus_content = strtolower($corpus['content']);
            $match_score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($corpus_content, $keyword) !== false) {
                    $match_score++;
                }
            }

            // Jika ada kecocokan, tambahkan ke hasil dengan persentase kecocokan
            if ($match_score > 0) {
                $match_percentage = ($match_score / $total_words) * 100;  // Hitung persentase kecocokan
                $matches[] = [
                    'corpus_id' => $corpus['id'],
                    'match_score' => $match_score,
                    'match_percentage' => round($match_percentage, 2)  // Pembulatan ke 2 angka desimal
                ];
            }
        }

        return $matches;
    }

    // Fungsi untuk menggunakan deteksi grammar (API dari luar)
    function detectGrammarErrors($text) {
        $apiKey = 'YOUR_GRAMMARBOT_API_KEY';
        $url = 'https://api.grammarbot.io/v2/check';

        $data = http_build_query([
            'text' => $text,
            'language' => 'en',
            'api_key' => $apiKey
        ]);

        $options = [
            'http' => [
                'header' => "Content-type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => $data,
            ],
        ];

        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        $response = json_decode($result, true);

        $errors = [];
        if (!empty($response['matches'])) {
            foreach ($response['matches'] as $match) {
                $errors[] = $match['message'];
            }
        }
        return $errors;
    }

    // Fungsi untuk kesamaan paragraf
    function discourseAnalysis($essay) {
        $paragraphs = preg_split('/\n+/', trim($essay));
        $coherence_score = 0;

        for ($i = 0; $i < count($paragraphs) - 1; $i++) {
            similar_text($paragraphs[$i], $paragraphs[$i + 1], $similarity);
            $coherence_score += $similarity;
        }

        return $coherence_score / (count($paragraphs) - 1);
    }

    // Fungsi untuk melakukan preprocessing pada esai
    function preprocessEssay($essay) {
        // Data Cleaning: Menghapus karakter yang tidak perlu
        $essay = preg_replace('/[^a-zA-Z\s]/', '', $essay); // Menghapus karakter selain huruf dan spasi
        
        // Case Folding: Mengubah semua teks menjadi huruf kecil
        $essay = strtolower($essay);
        
        // Tokenization: Memecah teks menjadi kata-kata
        $tokens = preg_split('/\s+/', $essay);

        // Stop Word Removal: Menghapus stopwords umum
        $stopwords = ['dan', 'atau', 'yang', 'di', 'ke', 'dengan', 'untuk', 'pada', 'adalah', 'sebagai', 'juga'];
        $tokens = array_diff($tokens, $stopwords);
        
        // Negation Handling: Mengonversi negasi
        $negations = ['tidak', 'bukan', 'tanpa'];
        foreach ($tokens as $key => $word) {
            if (in_array($word, $negations)) {
                $tokens[$key] = 'not_' . $word;  // Misalnya mengubah 'tidak' menjadi 'not_tidak'
            }
        }

        // Stemming: Mengubah kata menjadi bentuk dasar (penggunaan library eksternal bisa diperlukan untuk stemming yang lebih kompleks)
        // Di sini kita menggunakan pendekatan sederhana, misalnya menghapus sufiks '-kan' atau '-i' pada kata dasar
        foreach ($tokens as $key => $word) {
            if (substr($word, -3) === 'kan') {
                $tokens[$key] = substr($word, 0, -3);  // Menghapus sufiks '-kan'
            } elseif (substr($word, -1) === 'i') {
                $tokens[$key] = substr($word, 0, -1);  // Menghapus sufiks '-i'
            }
        }

        // Menggabungkan kembali token menjadi esai yang telah diproses
        return implode(' ', $tokens);
    }

    function detectCollocations($essay) {
        $tokens = preg_split('/\s+/', strtolower($essay));
        $bigrams = [];
        
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $bigrams[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }

        $bigram_counts = array_count_values($bigrams);
        arsort($bigram_counts);
        return $bigram_counts;
    }

    function computeTFIDF($essay, $corpus) {
        $all_docs = array_merge([$essay], $corpus);
        $term_frequency = [];
        $doc_frequency = [];
        $tfidf = [];

        foreach ($all_docs as $doc) {
            $tokens = array_count_values(preg_split('/\s+/', strtolower($doc)));
            $term_frequency[] = $tokens;

            foreach ($tokens as $term => $count) {
                $doc_frequency[$term] = ($doc_frequency[$term] ?? 0) + 1;
            }
        }

        $total_docs = count($all_docs);
        foreach ($term_frequency[0] as $term => $count) {
            $idf = log($total_docs / ($doc_frequency[$term] ?: 1));
            $tfidf[$term] = $count * $idf;
        }

        return $tfidf;
    }

    function trainERaterModel($trainingData) {
        // Misalnya: Melatih model menggunakan regresi linear sederhana
        $model = [];
        foreach ($trainingData as $data) {
            $model[] = [
                'word_count' => str_word_count($data['essay']),
                'grammar_errors' => countGrammarErrors($data['essay']),
                'score' => $data['score']
            ];
        }
        return $model;
    }

    function evaluateWithERater($essay, $model) {
        $word_count = str_word_count($essay);
        $grammar_errors = countGrammarErrors($essay);

        // Contoh sederhana: Model regresi
        $predicted_score = 0;
        foreach ($model as $feature) {
            $predicted_score += ($feature['word_count'] * $word_count) + ($feature['grammar_errors'] * $grammar_errors);
        }

        return $predicted_score;
    }

// Fungsi evaluasi esai
function evaluateEssay($essay) {
    $word_count = str_word_count($essay);
    $char_count = strlen($essay);

    $words = str_word_count($essay, 1);
    $word_lengths = array_map('strlen', $words);
    $avg_word_length = array_sum($word_lengths) / count($word_lengths);
    $variance_word_length = variance($word_lengths);

    $sentences = preg_split('/[.!?]+/', $essay, -1, PREG_SPLIT_NO_EMPTY);
    $sentence_lengths = array_map(function($sentence) {
        return str_word_count($sentence);
    }, $sentences);
    $avg_sentence_length = array_sum($sentence_lengths) / count($sentence_lengths);
    $variance_sentence_length = variance($sentence_lengths);

    $clauses_count = count_clauses($essay);
    $grammar_errors = count_grammar_errors($essay);
    $unique_words_count = count(array_unique($words));

    // Penerapan highlight kata kunci
    $highlighted_text = highlightKeywords($essay, ['test', 'important', 'essay']);

    return [
        'word_count' => $word_count,
        'char_count' => $char_count,
        'avg_word_length' => round($avg_word_length, 2),
        'variance_word_length' => round($variance_word_length, 2),
        'avg_sentence_length' => round($avg_sentence_length, 2),
        'variance_sentence_length' => round($variance_sentence_length, 2),
        'clauses_count' => $clauses_count,
        'grammar_errors' => $grammar_errors,
        'unique_words_count' => $unique_words_count,
        'highlighted_text' => $highlighted_text
    ];
}

// Fungsi untuk menghitung varian
function variance($array) {
    $mean = array_sum($array) / count($array);
    $squared_diffs = array_map(function($x) use ($mean) {
        return pow($x - $mean, 2);
    }, $array);
    return array_sum($squared_diffs) / count($squared_diffs);
}

function posTagging($tokens) {
    $pos_tags = []; // Array untuk menyimpan hasil penandaan
    foreach ($tokens as $kata) {
        if (in_array($kata, ['adalah', 'merupakan', 'ialah'])) {
            $pos_tags[$kata] = 'KATA KERJA'; // Tag untuk kata kerja
        } elseif (in_array($kata, ['dan', 'tetapi', 'atau'])) {
            $pos_tags[$kata] = 'KATA KONJUNGSI'; // Tag untuk kata konjungsi
        } else {
            $pos_tags[$kata] = 'KATA BENDA'; // Tag default sebagai kata benda
        }
    }
    return $pos_tags;
}

// Fungsi untuk menghitung jumlah klausa
function count_clauses($text) {
    $connectives = ['dan', 'atau', 'karena', 'meskipun', 'namun'];
    $clauses_count = 0;
    foreach ($connectives as $connective) {
        $clauses_count += substr_count(strtolower($text), strtolower($connective));
    }
    return $clauses_count;
}

// Fungsi untuk menghitung kesalahan tata bahasa
function count_grammar_errors($text) {
    // Implementasi sederhana bisa meliputi pemeriksaan untuk kesalahan umum seperti penggunaan kata yang salah eja, dll.
    $errors = 0;
    $common_errors = ['salah', 'kesalahan', 'koreksi']; // Misalnya, kita bisa memperhatikan kata-kata umum yang menunjukkan kesalahan
    foreach ($common_errors as $error) {
        $errors += substr_count(strtolower($text), strtolower($error));
    }
    return $errors;
}

// Fungsi untuk memberikan highlight pada kata kunci
function highlightKeywords($text, $keywords) {
    foreach ($keywords as $keyword) {
        $text = preg_replace('/\b' . preg_quote($keyword, '/') . '\b/i', '<span style="background-color: yellow;">$0</span>', $text);
    }
    return $text;
}

// Fungsi untuk analisis sentimen
function analyzeSentiment($essay) {
    $positive_words = ['baik', 'positif', 'bagus', 'mendukung', 'terbaik'];
    $negative_words = ['buruk', 'negatif', 'gagal', 'kesalahan', 'tidak baik'];
    
    $positive_score = 0;
    $negative_score = 0;
    
    $words = preg_split('/\s+/', $essay);
    
    foreach ($words as $word) {
        if (in_array(strtolower($word), $positive_words)) {
            $positive_score++;
        } elseif (in_array(strtolower($word), $negative_words)) {
            $negative_score++;
        }
    }

    if ($positive_score > $negative_score) {
        return 'Positif';
    } elseif ($negative_score > $positive_score) {
        return 'Negatif';
    } else {
        return 'Netral';
    }
}

// Fungsi untuk menghitung Flesch Reading Ease Score
function fleschReadingEase($essay) {
    $sentence_count = count(preg_split('/[.!?]+/', $essay));
    $word_count = str_word_count($essay);
    $syllable_count = countSyllables($essay);
    
    $score = 206.835 - (1.015 * ($word_count / $sentence_count)) - (84.6 * ($syllable_count / $word_count));
    return $score;
}

function countSyllables($text) {
    $syllables = 0;
    $words = preg_split('/\s+/', $text);
    foreach ($words as $word) {
        $syllables += preg_match_all('/[aeiou]/i', $word);
    }
    return $syllables;
}

// Menangani proses evaluasi esai jika ada input POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['essay'])) {
    $essay = trim($_POST['essay']);

    if (!empty($essay)) {
        try {
            // Panggil fungsi untuk mengevaluasi esai setelah preprocessing
            $processed_essay = preprocessEssay($essay); // Proses preprocessing
            $result = evaluateEssay($processed_essay);  // Evaluasi setelah preprocessing

            // Panggil fungsi untuk membandingkan esai dengan korpus
            $matches = compareWithCorpora($essay);  // Perbandingan dengan korpus dari database
        } catch (Exception $e) {
            $error_message = "Terjadi kesalahan: " . $e->getMessage();
        }
    } else {
        $error_message = "Esai tidak boleh kosong.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluasi Esai</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500&family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Animasi background bergerak */
        @keyframes gradientAnimation {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #a8c0ff, #3f2b96);
            background-size: 400% 400%;
            animation: gradientAnimation 15s ease infinite;
            color: #333;
        }
        .container {
            max-width: 900px;
            margin: 50px auto;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-10px);
        }
        textarea {
            resize: none;
            border-radius: 10px;
            padding: 12px;
            width: 100%;
            border: 1px solid #ddd;
        }
        .highlighted {
            background: #f8f9fa;
            padding: 15px;
            border: 1px solid #ced4da;
            border-radius: 10px;
            margin-top: 20px;
        }
        .form-label {
            font-weight: bold;
            font-size: 1.1em;
            color: #007bff;
        }
        .list-group-item {
            padding: 12px;
        }
        .btn-block {
            width: 100%;
            background-color: #007bff;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            padding: 15px;
            transition: background-color 0.3s ease;
        }
        .btn-block:hover {
            background-color: #0056b3;
        }
        .card-header {
            background-color: #007bff;
            color: white;
            text-align: center;
            border-radius: 20px 20px 0 0;
            padding: 20px;
            font-size: 1.5em;
        }
        .card-body {
            padding: 25px;
        }
        .result-section {
            margin-top: 30px;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            padding: 15px;
        }
        .highlighted-text {
            background-color: #fffbcc;
            padding: 2px 6px;
            border-radius: 5px;
        }
        .btn-back {
            width: 150px;
            background-color: #6c757d;
            color: white;
            font-weight: bold;
            border: none;
            border-radius: 10px;
            padding: 10px;
            transition: background-color 0.3s ease;
            margin-top: 20px;
        }
        .btn-back:hover {
            background-color: #5a6268;
        }

        /* Tambahkan gaya scrollbar */
        .highlighted-text {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ccc;
            padding: 10px;
            background-color: #f9f9f9;
            line-height: 1.5;
        }

        .highlighted-text::-webkit-scrollbar {
            width: 8px;
        }

        .highlighted-text::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }

        .highlighted-text::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .highlighted-keyword {
            background-color: #ffecb3;
            color: #d35400;
            font-weight: bold;
            border-radius: 4px;
            padding: 0 4px;
        }

        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card p-4">
            <div class="card-header">
                <h1>Evaluasi Esai</h1>
            </div>
            <div class="card-body">
                <form method="POST" action="evaluation.php">
                    <div class="mb-4">
                        <label for="essay" class="form-label">Masukkan Esai Anda:</label>
                        <textarea class="form-control" id="essay" name="essay" rows="10" required><?= isset($essay) ? htmlspecialchars($essay) : '' ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg btn-block">Evaluasi Esai</button>
                </form>

                <!-- Tampilkan pesan error jika ada -->
                <?php if ($error_message): ?>
                    <div class="error-message mt-4">
                        <?= htmlspecialchars($error_message) ?>
                    </div>
                <?php endif; ?>

                <!-- Progress Bar untuk Skor Keseluruhan -->
                <div class="progress mt-3">
                    <div class="progress-bar bg-success" role="progressbar" 
                        style="width: <?= isset($overall_score) ? $overall_score : 100 ?>%;" 
                        aria-valuenow="<?= isset($overall_score) ? $overall_score : 0 ?>" 
                        aria-valuemin="0" aria-valuemax="100">
                        <?= isset($overall_score) ? $overall_score : 100 ?>%
                    </div>
                </div>

                <!-- Tampilkan hasil evaluasi jika ada -->
                <?php if ($result): ?>
                    <div class="result-section">
                        <h4><i class="fas fa-chart-bar"></i> Hasil Evaluasi:</h4>
                        <ul class="list-group">
                            <li class="list-group-item"><strong>Jumlah Kata:</strong> <?= $result['word_count'] ?></li>
                            <li class="list-group-item"><strong>Panjang Esai (Karakter):</strong> <?= $result['char_count'] ?></li>
                            <li class="list-group-item"><strong>Rata-rata Panjang Kata (Karakter):</strong> <?= $result['avg_word_length'] ?></li>
                            <li class="list-group-item"><strong>Variasi Panjang Kata:</strong> <?= $result['variance_word_length'] ?></li>
                            <li class="list-group-item"><strong>Rata-rata Panjang Kalimat (Kata):</strong> <?= $result['avg_sentence_length'] ?></li>
                            <li class="list-group-item"><strong>Variasi Panjang Kalimat:</strong> <?= $result['variance_sentence_length'] ?></li>
                            <li class="list-group-item"><strong>Jumlah Klausa:</strong> <?= $result['clauses_count'] ?></li>
                            <li class="list-group-item"><strong>Jumlah Kesalahan Tata Bahasa:</strong> <?= $result['grammar_errors'] ?></li>
                            <li class="list-group-item"><strong>Ukuran Kosa Kata:</strong> <?= $result['unique_words_count'] ?></li>
                        </ul>

                        <h5 class="mt-4"><i class="fas fa-highlighter"></i> Esai yang diolah:</h5>
                        <div class="highlighted-text">
                            <?= $result['highlighted_text'] ?>
                        </div>

                        <style>
                            .highlighted-text {
                                max-height: 300px; /* Atur tinggi maksimal */
                                overflow-y: auto; /* Tambahkan scrollbar vertikal jika konten melebihi tinggi */
                                border: 1px solid #ccc; /* Opsional: Tambahkan border untuk estetika */
                                padding: 10px; /* Opsional: Tambahkan padding untuk spasi */
                                background-color: #f9f9f9; /* Opsional: Tambahkan warna latar belakang */
                                line-height: 1.5; /* Opsional: Tingkatkan keterbacaan */
                            }
                            /* Tambahkan gaya scrollbar (opsional) untuk browser modern */
                            .highlighted-text::-webkit-scrollbar {
                                width: 8px;
                            }
                            .highlighted-text::-webkit-scrollbar-thumb {
                                background: #888; /* Warna scrollbar */
                                border-radius: 4px;
                            }
                            .highlighted-text::-webkit-scrollbar-thumb:hover {
                                background: #555; /* Warna scrollbar saat hover */
                            }
                        </style>

                    <!-- Menampilkan hasil kecocokan dengan korpus -->
                    <h5 class="mt-4"><i class="fas fa-database"></i> Hasil Pencocokan dengan Korpus:</h5>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <ul class="list-group">
                            <?php if (empty($matches)): ?>
                                <li class="list-group-item">Tidak ada kecocokan ditemukan dengan korpus.</li>
                            <?php else: ?>
                                <?php foreach ($matches as $match): ?>
                                    <li class="list-group-item">
                                        <strong>Korpus ID:</strong> <?= $match['corpus_id'] ?> - 
                                        <strong>Skor Kecocokan:</strong> <?= $match['match_score'] ?> - 
                                        <strong>Persentase Kecocokan:</strong> <?= $match['match_percentage'] ?>%
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <!-- Bagian Highlight Kata Kunci -->
                <div class="highlighted mt-4">
                    <h5><i class="fas fa-search"></i> Teks dengan Kata Kunci Disorot:</h5>
                    <p>
                        <?php 
                        // Penyorotan kata kunci
                        if (isset($highlighted_text)) {
                            $keywords = ['kata1', 'kata2']; // Daftar kata kunci
                            $highlighted_text = preg_replace_callback(
                                '/\b(' . implode('|', $keywords) . ')\b/i',
                                function ($matches) {
                                    return '<span class="highlighted-keyword">' . $matches[0] . '</span>';
                                },
                                $highlighted_text
                            );
                            echo $highlighted_text;
                        } else {
                            echo 'Tidak ada teks yang disorot.';
                        }
                        ?>
                    </p>
                </div>

                <!-- Bagian Feedback / Penilaian Keseluruhan -->
                <div class="mt-4">
                    <h5><i class="fas fa-thumbs-up"></i> Penilaian Keseluruhan:</h5>
                    <p>
                        <strong>Penilaian:</strong> 
                        <?= isset($overall_score) ? $overall_score : 'Formal' ?>
                    </p>
                    <p>
                        <strong>Umpan Balik:</strong> 
                        <?= isset($feedback) ? $feedback : 'Penulisan Kata; Pengulangan Isi;' ?>
                    </p>

                <!-- Tombol Kembali ke Dashboard -->
                <div class="mt-4">
                    <a href="index.php" class="btn btn-back"><i class="fas fa-arrow-left"></i> Kembali ke Dashboard</a>
                    <?php if (isset($result)): ?>
                        <a href="index.php?page=evaluation" class="btn btn-warning"><i class="fas fa-redo"></i> Evaluasi Ulang</a>
                    <?php endif; ?>
                </div>

                <!-- Tambahkan CSS untuk gaya sorotan -->
                <style>
                    .highlighted-keyword {
                        background-color: #ffecb3;
                        color: #d35400;
                        font-weight: bold;
                        border-radius: 4px;
                        padding: 0 4px;
                    }
                    .btn-back {
                        background-color: #007bff;
                        color: #fff;
                    }
                    .btn-warning {
                        background-color: #ffc107;
                        color: #212529;
                    }
                </style>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>