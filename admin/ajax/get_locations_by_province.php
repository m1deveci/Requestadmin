<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireAuth(['admin']);

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$provinceId = $_GET['province_id'] ?? '';

if (empty($provinceId)) {
    echo json_encode(['success' => false, 'message' => 'Province ID is required']);
    exit;
}

try {
    $query = "SELECT id, location_name FROM locations WHERE province_id = ? ORDER BY location_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$provinceId]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true, 
        'locations' => $locations
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
