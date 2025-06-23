<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];
$message = '';
$messageType = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $department = trim($_POST['department'] ?? '');
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $message = 'Ad, soyad ve e-posta alanları zorunludur.';
            $messageType = 'danger';
        } else {
            $checkQuery = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$email, $userId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $message = 'Bu e-posta adresi zaten kullanılıyor.';
                $messageType = 'danger';
            } else {
                $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, title = ?, department = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$firstName, $lastName, $email, $title, $department, $userId])) {
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_email'] = $email;
                    
                    logAdminAction($db, $userId, 'profile_update', "Profil bilgileri güncellendi");
                    $message = 'Profil bilgileriniz başarıyla güncellendi.';
                    $messageType = 'success';
                } else {
                    $message = 'Güncelleme sırasında hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'Tüm parola alanları zorunludur.';
            $messageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Yeni parolalar eşleşmiyor.';
            $messageType = 'danger';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Yeni parola en az 6 karakter olmalıdır.';
            $messageType = 'danger';
        } else {
            $query = "SELECT password FROM users WHERE id = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$userId]);
            $currentHash = $stmt->fetchColumn();
            
            if (!password_verify($currentPassword, $currentHash)) {
                $message = 'Mevcut parola yanlış.';
                $messageType = 'danger';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                $updateQuery = "UPDATE users SET password = ? WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                
                if ($updateStmt->execute([$newHash, $userId])) {
                    logAdminAction($db, $userId, 'password_change', "Parola değiştirildi");
                    $message = 'Parolanız başarıyla değiştirildi.';
                    $messageType = 'success';
                } else {
                    $message = 'Parola değiştirme sırasında hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$query = "SELECT u.*, c.company_name, l.location_name 
          FROM users u 
          JOIN companies c ON u.company_id = c.id 
          LEFT JOIN locations l ON u.location_id = l.id 
          WHERE u.id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$statsQuery = "SELECT 
    COUNT(*) as total_assigned,
    SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN r.status IN ('pending', 'assigned', 'in_progress') THEN 1 ELSE 0 END) as active
    FROM requests r 
    JOIN users u ON r.employee_id = u.id 
    WHERE r.assigned_to = ? AND u.location_id = ?";
$statsStmt = $db->prepare($statsQuery);
$statsStmt->execute([$userId, $_SESSION['location_id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

$recentQuery = "SELECT r.*, u.first_name, u.last_name, c.category_name 
                FROM requests r 
                JOIN users u ON r.employee_id = u.id 
                JOIN request_categories c ON r.category_id = c.id 
                WHERE r.assigned_to = ? AND u.location_id = ? 
                ORDER BY r.updated_at DESC 
                LIMIT 5";
$recentStmt = $db->prepare($recentQuery);
$recentStmt->execute([$userId, $_SESSION['location_id']]);
$recentRequests = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Request Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">İdari İşler</h5>
                        <small class="text-light"><?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                        <small class="text-light d-block"><?php echo htmlspecialchars($_SESSION['location_name'] ?? 'Merkez'); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="requests.php">
                                <i class="fas fa-tasks"></i> Talepler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="employees.php">
                                <i class="fas fa-users"></i> Çalışanlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Raporlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profile.php">
                                <i class="fas fa-user"></i> Profil
                            </a>
                        </li>
                        <li class="nav-item mt-3">
                            <a class="nav-link text-danger" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <h1>Profil</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Profil</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Profile Info -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <?php if ($user['photo']): ?>
                                        <img src="../uploads/<?php echo htmlspecialchars($user['photo']); ?>" class="rounded-circle" width="100" height="100">
                                    <?php else: ?>
                                        <div class="bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 100px; height: 100px; font-size: 2rem;">
                                            <?php echo strtoupper(substr($user['first_name'], 0, 1)); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($user['title'] ?? 'İdari İşler Sorumlusu'); ?></p>
                                <p class="text-muted small">
                                    <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($user['company_name']); ?><br>
                                    <i class="fas fa-map-marker-alt me-1"></i><?php echo htmlspecialchars($user['location_name'] ?? 'Merkez'); ?>
                                </p>
                            </div>
                        </div>

                        <!-- Stats Card -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">İstatistiklerim</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo $stats['total_assigned']; ?></h4>
                                        <small class="text-muted">Toplam Atanan</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo $stats['completed']; ?></h4>
                                        <small class="text-muted">Tamamlanan</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-warning"><?php echo $stats['active']; ?></h4>
                                        <small class="text-muted">Aktif</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Requests -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">Son Atanan Talepler</h6>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recentRequests)): ?>
                                    <p class="text-muted small">Henüz atanan talep yok.</p>
                                <?php else: ?>
                                    <?php foreach ($recentRequests as $request): ?>
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                                <div class="text-muted small"><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></div>
                                            </div>
                                            <span class="badge <?php echo getStatusBadgeClass($request['status']); ?> small">
                                                <?php echo getStatusText($request['status']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Forms -->
                    <div class="col-lg-8">
                        <!-- Profile Information -->
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Profil Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="update_profile">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Ad *</label>
                                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Soyad *</label>
                                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">E-posta *</label>
                                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Ünvan</label>
                                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Departman</label>
                                        <input type="text" class="form-control" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-primary">Güncelle</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- Change Password -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Parola Değiştir</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="action" value="change_password">
                                    <div class="mb-3">
                                        <label class="form-label">Mevcut Parola *</label>
                                        <input type="password" class="form-control" name="current_password" required>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Yeni Parola *</label>
                                                <input type="password" class="form-control" name="new_password" required>
                                                <div class="form-text">En az 6 karakter olmalıdır.</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Yeni Parola (Tekrar) *</label>
                                                <input type="password" class="form-control" name="confirm_password" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-warning">Parola Değiştir</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
