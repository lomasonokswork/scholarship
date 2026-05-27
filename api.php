<?php
require 'db.php';

header('Content-Type: application/json');

try {
    $configId = $_GET['config_id'] ?? null;

    if (!$configId) {
        throw new Exception('Config ID required');
    }

    // Get config and results
    $stmt = $pdo->prepare('SELECT * FROM scholarship_config WHERE id = ?');
    $stmt->execute([$configId]);
    $config = $stmt->fetch();

    if (!$config) {
        throw new Exception('Config not found');
    }

    $stmt = $pdo->prepare("
        SELECT 
            s.last_name,
            s.first_name,
            s.personal_code,
            s.group_name,
            sr.average_grade,
            sr.scholarship_amount,
            sr.scholarship_reason
        FROM scholarship_results sr
        JOIN students s ON sr.student_id = s.id
        WHERE sr.config_id = ?
        ORDER BY s.group_name, s.last_name, s.first_name
    ");
    $stmt->execute([$configId]);
    $results = $stmt->fetchAll();

    // Calculate totals
    $totalPayout = 0;
    foreach ($results as $r) {
        $totalPayout += floatval($r['scholarship_amount']);
    }

    echo json_encode([
        'success' => true,
        'results' => $results,
        'totalPayout' => $totalPayout,
        'totalBudget' => floatval($config['monthly_budget'])
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}