<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/functions.php';

requireAuth(['admin']);

header('Content-Type: application/json');

$requestId = $_GET['id'] ?? '';

if (empty($requestId)) {
    echo json_encode(['success' => false, 'message' => 'Request ID is required']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT r.*, u.first_name, u.last_name, u.email, u.title as user_title, u.department,
              c.company_name, l.location_name, cat.category_name, cat.requires_manager_approval,
              assigned.first_name as assigned_first_name, assigned.last_name as assigned_last_name,
              manager.first_name as manager_first_name, manager.last_name as manager_last_name
              FROM requests r
              JOIN users u ON r.employee_id = u.id
              JOIN companies c ON u.company_id = c.id
              LEFT JOIN locations l ON u.location_id = l.id
              JOIN request_categories cat ON r.category_id = cat.id
              LEFT JOIN users assigned ON r.assigned_to = assigned.id
              LEFT JOIN users manager ON u.manager_id = manager.id
              WHERE r.id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$requestId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$request) {
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }
    
    $historyQuery = "SELECT rsh.*, u.first_name, u.last_name
                     FROM request_status_history rsh
                     JOIN users u ON rsh.changed_by = u.id
                     WHERE rsh.request_id = ?
                     ORDER BY rsh.created_at DESC";
    $historyStmt = $db->prepare($historyQuery);
    $historyStmt->execute([$requestId]);
    $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = '<div class="row">';
    $html .= '<div class="col-md-8">';
    $html .= '<h6>Talep Bilgileri</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>Talep No:</strong></td><td>' . htmlspecialchars($request['request_number']) . '</td></tr>';
    $html .= '<tr><td><strong>Başlık:</strong></td><td>' . htmlspecialchars($request['title']) . '</td></tr>';
    $html .= '<tr><td><strong>Kategori:</strong></td><td>' . htmlspecialchars($request['category_name']) . '</td></tr>';
    $html .= '<tr><td><strong>Öncelik:</strong></td><td><span class="badge bg-' . getPriorityBadgeColor($request['priority']) . '">' . getPriorityText($request['priority']) . '</span></td></tr>';
    $html .= '<tr><td><strong>Durum:</strong></td><td><span class="badge bg-' . getStatusBadgeColor($request['status']) . '">' . getStatusText($request['status']) . '</span></td></tr>';
    $html .= '<tr><td><strong>Oluşturma Tarihi:</strong></td><td>' . formatDate($request['created_at']) . '</td></tr>';
    if ($request['assigned_to']) {
        $html .= '<tr><td><strong>Atanan:</strong></td><td>' . htmlspecialchars($request['assigned_first_name'] . ' ' . $request['assigned_last_name']) . '</td></tr>';
    }
    $html .= '</table>';
    
    $html .= '<h6 class="mt-3">Açıklama</h6>';
    $html .= '<p>' . nl2br(htmlspecialchars($request['description'])) . '</p>';
    
    if ($request['attachment']) {
        $html .= '<h6 class="mt-3">Ek Dosya</h6>';
        $html .= '<a href="../uploads/requests/' . htmlspecialchars($request['attachment']) . '" target="_blank" class="btn btn-sm btn-outline-primary">';
        $html .= '<i class="fas fa-download"></i> Dosyayı İndir</a>';
    }
    
    $html .= '</div>';
    $html .= '<div class="col-md-4">';
    $html .= '<h6>Çalışan Bilgileri</h6>';
    $html .= '<table class="table table-sm">';
    $html .= '<tr><td><strong>Ad Soyad:</strong></td><td>' . htmlspecialchars($request['first_name'] . ' ' . $request['last_name']) . '</td></tr>';
    $html .= '<tr><td><strong>E-posta:</strong></td><td>' . htmlspecialchars($request['email']) . '</td></tr>';
    $html .= '<tr><td><strong>Ünvan:</strong></td><td>' . htmlspecialchars($request['user_title'] ?? '-') . '</td></tr>';
    $html .= '<tr><td><strong>Departman:</strong></td><td>' . htmlspecialchars($request['department'] ?? '-') . '</td></tr>';
    $html .= '<tr><td><strong>Firma:</strong></td><td>' . htmlspecialchars($request['company_name']) . '</td></tr>';
    $html .= '<tr><td><strong>Lokasyon:</strong></td><td>' . htmlspecialchars($request['location_name'] ?? '-') . '</td></tr>';
    if ($request['manager_first_name']) {
        $html .= '<tr><td><strong>Yönetici:</strong></td><td>' . htmlspecialchars($request['manager_first_name'] . ' ' . $request['manager_last_name']) . '</td></tr>';
    }
    $html .= '</table>';
    $html .= '</div>';
    $html .= '</div>';
    
    if (!empty($history)) {
        $html .= '<hr>';
        $html .= '<h6>Durum Geçmişi</h6>';
        $html .= '<div class="timeline">';
        foreach ($history as $item) {
            $html .= '<div class="timeline-item">';
            $html .= '<div class="timeline-marker"></div>';
            $html .= '<div class="timeline-content">';
            $html .= '<h6 class="timeline-title">' . getStatusText($item['new_status']) . '</h6>';
            $html .= '<p class="timeline-text">';
            $html .= 'Durum <strong>' . getStatusText($item['old_status']) . '</strong> \'den <strong>' . getStatusText($item['new_status']) . '</strong> \'e değiştirildi.';
            $html .= '<br><small class="text-muted">Değiştiren: ' . htmlspecialchars($item['first_name'] . ' ' . $item['last_name']) . ' - ' . formatDate($item['created_at']) . '</small>';
            if ($item['notes']) {
                $html .= '<br><em>' . htmlspecialchars($item['notes']) . '</em>';
            }
            $html .= '</p>';
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';
    }
    
    echo json_encode(['success' => true, 'html' => $html]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
