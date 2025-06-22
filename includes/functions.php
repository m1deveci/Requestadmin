<?php
require_once 'config/database.php';

function generateDeviceFingerprint() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    
    return md5($userAgent . $acceptLanguage . $acceptEncoding);
}

function getClientIP() {
    $ipKeys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isBlocked($ip, $deviceFingerprint) {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT COUNT(*) FROM blocked_access 
              WHERE (ip_address = ? OR device_fingerprint = ?) 
              AND blocked_until > NOW()";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$ip, $deviceFingerprint]);
    
    return $stmt->fetchColumn() > 0;
}

function blockAccess($ip, $deviceFingerprint, $reason = 'Too many failed login attempts') {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'lockout_duration'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $lockoutMinutes = $stmt->fetchColumn() ?: 30;
    
    $blockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
    
    $query = "INSERT INTO blocked_access (ip_address, device_fingerprint, blocked_until, reason) 
              VALUES (?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    return $stmt->execute([$ip, $deviceFingerprint, $blockedUntil, $reason]);
}

function logLoginAttempt($email, $userId = null, $status = 'failed', $reason = '') {
    $database = new Database();
    $db = $database->getConnection();
    
    $ip = getClientIP();
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $deviceFingerprint = generateDeviceFingerprint();
    
    $query = "INSERT INTO login_logs (user_id, email, ip_address, user_agent, device_fingerprint, login_status, failure_reason) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $db->prepare($query);
    return $stmt->execute([$userId, $email, $ip, $userAgent, $deviceFingerprint, $status, $reason]);
}

function checkFailedAttempts($email) {
    $database = new Database();
    $db = $database->getConnection();
    
    $ip = getClientIP();
    $deviceFingerprint = generateDeviceFingerprint();
    
    $query = "SELECT COUNT(*) FROM login_logs 
              WHERE (email = ? OR ip_address = ? OR device_fingerprint = ?) 
              AND login_status = 'failed' 
              AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$email, $ip, $deviceFingerprint]);
    
    $failedAttempts = $stmt->fetchColumn();
    
    $query = "SELECT setting_value FROM system_settings WHERE setting_key = 'max_login_attempts'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $maxAttempts = $stmt->fetchColumn() ?: 3;
    
    if ($failedAttempts >= $maxAttempts) {
        blockAccess($ip, $deviceFingerprint);
        return true;
    }
    
    return false;
}

function authenticateUser($email, $password) {
    $database = new Database();
    $db = $database->getConnection();
    
    $ip = getClientIP();
    $deviceFingerprint = generateDeviceFingerprint();
    
    if (isBlocked($ip, $deviceFingerprint)) {
        logLoginAttempt($email, null, 'failed', 'IP/Device blocked');
        return ['success' => false, 'message' => 'Erişim engellendi. Lütfen daha sonra tekrar deneyin.'];
    }
    
    if (checkFailedAttempts($email)) {
        return ['success' => false, 'message' => 'Çok fazla başarısız deneme. Erişim engellendi.'];
    }
    
    $query = "SELECT u.*, c.status as company_status, c.company_name, l.location_name 
              FROM users u 
              JOIN companies c ON u.company_id = c.id 
              LEFT JOIN locations l ON u.location_id = l.id 
              WHERE u.email = ? AND u.status = 'active'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        logLoginAttempt($email, null, 'failed', 'User not found');
        return ['success' => false, 'message' => 'Geçersiz e-posta veya parola.'];
    }
    
    if ($user['company_status'] !== 'approved' && $user['role'] !== 'admin') {
        logLoginAttempt($email, $user['id'], 'failed', 'Company not approved');
        return ['success' => false, 'message' => 'Firma henüz onaylanmamış.'];
    }
    
    if (!password_verify($password, $user['password'])) {
        logLoginAttempt($email, $user['id'], 'failed', 'Wrong password');
        return ['success' => false, 'message' => 'Geçersiz e-posta veya parola.'];
    }
    
    $updateQuery = "UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([$user['id']]);
    
    logLoginAttempt($email, $user['id'], 'success');
    
    return ['success' => true, 'user' => $user];
}

function generateRequestNumber() {
    return 'REQ-' . date('Y') . '-' . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);
}

function uploadFile($file, $uploadDir = 'uploads/') {
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'];
    $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileExtension, $allowedTypes)) {
        return ['success' => false, 'message' => 'Geçersiz dosya türü.'];
    }
    
    $fileName = uniqid() . '.' . $fileExtension;
    $filePath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return ['success' => true, 'filename' => $fileName, 'path' => $filePath];
    }
    
    return ['success' => false, 'message' => 'Dosya yüklenemedi.'];
}

function requireAuth($allowedRoles = []) {
    if (!isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    
    if (!empty($allowedRoles) && !in_array($_SESSION['user_role'], $allowedRoles)) {
        header('Location: unauthorized.php');
        exit;
    }
}

function requireLocation($userLocationId, $targetLocationId) {
    if ($_SESSION['user_role'] !== 'admin' && $userLocationId != $targetLocationId) {
        header('Location: unauthorized.php');
        exit;
    }
}

function formatDate($date) {
    return date('d.m.Y H:i', strtotime($date));
}

function getRoleBadgeColor($role) {
    switch ($role) {
        case 'admin':
            return 'danger';
        case 'hr':
            return 'warning';
        case 'employee':
            return 'info';
        default:
            return 'secondary';
    }
}

function getRoleText($role) {
    switch ($role) {
        case 'admin':
            return 'Admin';
        case 'hr':
            return 'İdari İşler';
        case 'employee':
            return 'Çalışan';
        default:
            return 'Bilinmeyen';
    }
}

function getStatusBadgeColor($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'assigned':
            return 'info';
        case 'in_progress':
            return 'primary';
        case 'completed':
            return 'success';
        case 'cancelled':
            return 'danger';
        case 'waiting_manager_approval':
            return 'secondary';
        case 'manager_approved':
            return 'success';
        case 'manager_rejected':
            return 'danger';
        default:
            return 'secondary';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 'pending':
            return 'Bekleyen';
        case 'assigned':
            return 'Atanmış';
        case 'in_progress':
            return 'İşlemde';
        case 'completed':
            return 'Tamamlandı';
        case 'cancelled':
            return 'İptal Edildi';
        case 'waiting_manager_approval':
            return 'Yönetici Onayı Bekliyor';
        case 'manager_approved':
            return 'Yönetici Onayladı';
        case 'manager_rejected':
            return 'Yönetici Reddetti';
        default:
            return 'Bilinmeyen';
    }
}

function logAdminAction($db, $userId, $action, $description) {
    try {
        $query = "INSERT INTO admin_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $userId,
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Admin log error: " . $e->getMessage());
    }
}

function getStatusBadgeClass($status) {
    $classes = [
        'pending' => 'bg-warning',
        'assigned' => 'bg-info',
        'in_progress' => 'bg-primary',
        'manager_approval' => 'bg-secondary',
        'approved' => 'bg-success',
        'rejected' => 'bg-danger',
        'completed' => 'bg-success',
        'cancelled' => 'bg-dark'
    ];
    
    return $classes[$status] ?? 'bg-secondary';
}



function getPriorityBadgeClass($priority) {
    $classes = [
        'low' => 'bg-success',
        'medium' => 'bg-warning',
        'high' => 'bg-danger'
    ];
    
    return $classes[$priority] ?? 'bg-secondary';
}

function getPriorityText($priority) {
    $texts = [
        'low' => 'Düşük',
        'medium' => 'Orta',
        'high' => 'Yüksek'
    ];
    
    return $texts[$priority] ?? $priority;
}
?>
