<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireAuth(['admin', 'hr']);

$database = new Database();
$db = $database->getConnection();

$query = "SELECT id, province_name FROM provinces ORDER BY province_name";
$stmt = $db->prepare($query);
$stmt->execute();
$provinces = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($provinces);
?>
