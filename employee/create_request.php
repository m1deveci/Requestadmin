<?php
session_start();
require_once '../config/database.php';
require_once '../config/mail.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $priority = $_POST['priority'] ?? 'medium';
    $employeeId = $_SESSION['user_id'];
    
    $errors = [];
    
    if (empty($categoryId)) $errors[] = 'Kategori seçimi gereklidir.';
    if (empty($title)) $errors[] = 'Talep başlığı gereklidir.';
    if (empty($description)) $errors[] = 'Detaylı açıklama gereklidir.';
    
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['image'], 'uploads/requests/');
            if ($uploadResult['success']) {
                $imagePath = $uploadResult['filename'];
            } else {
                $errors[] = $uploadResult['message'];
            }
        }
        
        if (empty($errors)) {
            do {
                $requestNumber = generateRequestNumber();
                $checkQuery = "SELECT id FROM requests WHERE request_number = ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$requestNumber]);
            } while ($checkStmt->fetch());
            
            $categoryQuery = "SELECT requires_manager_approval FROM request_categories WHERE id = ?";
            $categoryStmt = $db->prepare($categoryQuery);
            $categoryStmt->execute([$categoryId]);
            $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
            
            $initialStatus = $category['requires_manager_approval'] ? 'manager_approval' : 'pending';
            
            $query = "INSERT INTO requests (request_number, employee_id, category_id, title, description, image, priority, status) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $db->prepare($query);
            if ($stmt->execute([$requestNumber, $employeeId, $categoryId, $title, $description, $imagePath, $priority, $initialStatus])) {
                $requestId = $db->lastInsertId();
                
                $historyQuery = "INSERT INTO request_status_history (request_id, new_status, changed_by, comments) 
                               VALUES (?, ?, ?, ?)";
                $historyStmt = $db->prepare($historyQuery);
                $historyStmt->execute([$requestId, $initialStatus, $employeeId, 'Talep oluşturuldu']);
                
                $mailService = new MailService();
                
                if ($initialStatus === 'manager_approval') {
                    $mailService->sendRequestNotification($requestId, 'manager_approval');
                } else {
                    $mailService->sendRequestNotification($requestId, 'new');
                }
                
                $_SESSION['success'] = 'Talebiniz başarıyla oluşturuldu. Talep numaranız: ' . $requestNumber;
                header('Location: dashboard.php');
                exit;
            } else {
                $errors[] = 'Talep oluşturulurken bir hata oluştu.';
            }
        }
    }
    
    $_SESSION['errors'] = $errors;
    $_SESSION['form_data'] = $_POST;
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: dashboard.php');
    exit;
}
?>
