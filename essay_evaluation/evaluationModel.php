// Mengambil total evaluasi yang selesai
function getTotalEvaluations() {
    $conn = getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM evaluations WHERE status = 'completed'");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    return $result['total'] ?? 0;
}

// Mengambil evaluasi terbaru
function getRecentEvaluations($limit) {
    $conn = getConnection();
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