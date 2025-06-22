<?php
require_once 'database.php';

class MailService {
    private $db;
    private $settings;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->loadSettings();
    }
    
    private function loadSettings() {
        $query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        $this->settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    public function sendMail($to, $subject, $body, $isHTML = true) {
        $logEntry = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        $logFile = 'logs/email_log.json';
        if (!file_exists('logs')) {
            mkdir('logs', 0755, true);
        }
        
        $existingLogs = [];
        if (file_exists($logFile)) {
            $existingLogs = json_decode(file_get_contents($logFile), true) ?: [];
        }
        
        $existingLogs[] = $logEntry;
        file_put_contents($logFile, json_encode($existingLogs, JSON_PRETTY_PRINT));
        
        return true; // Simulate successful sending
    }
    
    public function sendRequestNotification($requestId, $type = 'new') {
        $query = "SELECT r.*, u.first_name, u.last_name, u.email as employee_email, 
                         c.category_name, comp.company_name, loc.location_name
                  FROM requests r
                  JOIN users u ON r.employee_id = u.id
                  JOIN request_categories c ON r.category_id = c.id
                  JOIN companies comp ON u.company_id = comp.id
                  LEFT JOIN locations loc ON u.location_id = loc.id
                  WHERE r.id = ?";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$requestId]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$request) return false;
        
        switch ($type) {
            case 'new':
                return $this->sendNewRequestNotification($request);
            case 'status_update':
                return $this->sendStatusUpdateNotification($request);
            case 'manager_approval':
                return $this->sendManagerApprovalNotification($request);
            case 'cancelled':
                return $this->sendCancelledRequestNotification($request);
        }
        
        return false;
    }
    
    private function sendNewRequestNotification($request) {
        $query = "SELECT email FROM users WHERE role = 'hr' AND location_id = ? AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$request['location_id'] ?? 0]);
        
        $subject = "Yeni Talep: " . $request['title'];
        $body = "
        <h3>Yeni Talep Bildirimi</h3>
        <p><strong>Talep No:</strong> {$request['request_number']}</p>
        <p><strong>Çalışan:</strong> {$request['first_name']} {$request['last_name']}</p>
        <p><strong>Kategori:</strong> {$request['category_name']}</p>
        <p><strong>Başlık:</strong> {$request['title']}</p>
        <p><strong>Açıklama:</strong> {$request['description']}</p>
        <p><strong>Tarih:</strong> {$request['created_at']}</p>
        ";
        
        $success = true;
        while ($hr = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $success &= $this->sendMail($hr['email'], $subject, $body);
        }
        
        return $success;
    }
    
    private function sendStatusUpdateNotification($request) {
        $subject = "Talep Durumu Güncellendi: " . $request['title'];
        $body = "
        <h3>Talep Durumu Güncellendi</h3>
        <p><strong>Talep No:</strong> {$request['request_number']}</p>
        <p><strong>Başlık:</strong> {$request['title']}</p>
        <p><strong>Yeni Durum:</strong> " . $this->getStatusText($request['status']) . "</p>
        <p><strong>Güncelleme Tarihi:</strong> {$request['updated_at']}</p>
        ";
        
        return $this->sendMail($request['employee_email'], $subject, $body);
    }
    
    private function sendManagerApprovalNotification($request) {
        $query = "SELECT m.email FROM users u JOIN users m ON u.manager_id = m.id WHERE u.id = ?";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$request['employee_id']]);
        $manager = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$manager) return false;
        
        $subject = "Yönetici Onayı Gerekli: " . $request['title'];
        $body = "
        <h3>Yönetici Onayı Gerekli</h3>
        <p><strong>Talep No:</strong> {$request['request_number']}</p>
        <p><strong>Çalışan:</strong> {$request['first_name']} {$request['last_name']}</p>
        <p><strong>Kategori:</strong> {$request['category_name']}</p>
        <p><strong>Başlık:</strong> {$request['title']}</p>
        <p><strong>Açıklama:</strong> {$request['description']}</p>
        <p><strong>Tarih:</strong> {$request['created_at']}</p>
        <p><a href='manager_approval.php?request_id={$request['id']}&token=" . md5($request['id'] . 'approval_token') . "'>Onaylamak için tıklayın</a></p>
        ";
        
        return $this->sendMail($manager['email'], $subject, $body);
    }
    
    private function sendCancelledRequestNotification($request) {
        $query = "SELECT email FROM users WHERE role = 'hr' AND location_id = ? AND status = 'active'";
        $stmt = $this->db->prepare($query);
        $stmt->execute([$request['location_id'] ?? 0]);
        
        $subject = "Talep İptal Edildi: " . $request['title'];
        $body = "
        <h3>Talep İptal Edildi</h3>
        <p><strong>Talep No:</strong> {$request['request_number']}</p>
        <p><strong>Çalışan:</strong> {$request['first_name']} {$request['last_name']}</p>
        <p><strong>Başlık:</strong> {$request['title']}</p>
        <p><strong>İptal Tarihi:</strong> " . date('Y-m-d H:i:s') . "</p>
        ";
        
        $success = true;
        while ($hr = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $success &= $this->sendMail($hr['email'], $subject, $body);
        }
        
        return $success;
    }
    
    private function getStatusText($status) {
        $statusTexts = [
            'pending' => 'Beklemede',
            'assigned' => 'Atandı',
            'in_progress' => 'İşlemde',
            'manager_approval' => 'Yönetici Onayı Bekliyor',
            'approved' => 'Onaylandı',
            'rejected' => 'Reddedildi',
            'completed' => 'Tamamlandı',
            'cancelled' => 'İptal Edildi'
        ];
        
        return $statusTexts[$status] ?? $status;
    }
}
?>
