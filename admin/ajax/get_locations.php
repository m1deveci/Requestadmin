<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireAuth(['admin']);

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

$companyId = $_GET['company_id'] ?? '';

if (empty($companyId)) {
    echo json_encode(['success' => false, 'message' => 'Company ID is required']);
    exit;
}

try {
    $query = "SELECT id, location_name FROM locations WHERE company_id = ? ORDER BY location_name";
    $stmt = $db->prepare($query);
    $stmt->execute([$companyId]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'locations' => $locations]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
