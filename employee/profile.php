<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

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
            
            if (password_verify($currentPassword, $currentHash)) {
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
            } else {
                $message = 'Mevcut parola yanlış.';
                $messageType = 'danger';
            }
        }
    }
}

$userQuery = "SELECT u.*, c.company_name, l.location_name 
              FROM users u 
              LEFT JOIN companies c ON u.company_id = c.id 
              LEFT JOIN locations l ON u.location_id = l.id 
              WHERE u.id = ?";
$userStmt = $db->prepare($userQuery);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$requestsQuery = "SELECT COUNT(*) as total,
                         SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                         SUM(CASE WHEN status IN ('assigned', 'in_progress') THEN 1 ELSE 0 END) as in_progress,
                         SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
                  FROM requests WHERE employee_id = ?";
$requestsStmt = $db->prepare($requestsQuery);
$requestsStmt->execute([$userId]);
$requestStats = $requestsStmt->fetch(PDO::FETCH_ASSOC);

$recentRequestsQuery = "SELECT r.*, rc.category_name 
                        FROM requests r 
                        LEFT JOIN request_categories rc ON r.category_id = rc.id 
                        WHERE r.employee_id = ? 
                        ORDER BY r.created_at DESC 
                        LIMIT 5";
$recentRequestsStmt = $db->prepare($recentRequestsQuery);
$recentRequestsStmt->execute([$userId]);
$recentRequests = $recentRequestsStmt->fetchAll(PDO::FETCH_ASSOC);
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
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Çalışan Paneli</h5>
                        <small class="text-muted"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></small>
                        <small class="text-muted d-block"><?php echo htmlspecialchars($user['location_name'] ?? 'Lokasyon'); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link text-white" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="requests.php">
                                <i class="fas fa-list"></i> Taleplerim
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="new_request.php">
                                <i class="fas fa-plus"></i> Yeni Talep
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white active" href="profile.php">
                                <i class="fas fa-user"></i> Profil
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-white" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Çıkış
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Profil</h1>
                </div>

                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5>Profil Bilgileri</h5>
                            </div>
                            <div class="card-body text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                                </div>
                                <h5><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                <p class="text-muted"><?php echo htmlspecialchars($user['title'] ?? 'Çalışan'); ?></p>
                                <p class="text-muted"><?php echo htmlspecialchars($user['department'] ?? 'Departman'); ?></p>
                                <hr>
                                <div class="row text-center">
                                    <div class="col">
                                        <strong><?php echo $requestStats['total']; ?></strong>
                                        <p class="text-muted">Toplam Talep</p>
                                    </div>
                                    <div class="col">
                                        <strong><?php echo $requestStats['completed']; ?></strong>
                                        <p class="text-muted">Tamamlanan</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mt-3">
                            <div class="card-header">
                                <h6>Şirket Bilgileri</h6>
                            </div>
                            <div class="card-body">
                                <p><strong>Şirket:</strong> <?php echo htmlspecialchars($user['company_name']); ?></p>
                                <p><strong>Lokasyon:</strong> <?php echo htmlspecialchars($user['location_name']); ?></p>
                                <p><strong>E-posta:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                <p><strong>Kayıt Tarihi:</strong> <?php echo date('d.m.Y', strtotime($user['created_at'])); ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" id="profileTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button" role="tab">
                                            Profil Düzenle
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="password-tab" data-bs-toggle="tab" data-bs-target="#password" type="button" role="tab">
                                            Parola Değiştir
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab">
                                            Son Talepler
                                        </button>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content" id="profileTabsContent">
                                    <div class="tab-pane fade show active" id="profile" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="update_profile">
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="first_name" class="form-label">Ad <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="last_name" class="form-label">Soyad <span class="text-danger">*</span></label>
                                                        <input type="text" class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="email" class="form-label">E-posta <span class="text-danger">*</span></label>
                                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="title" class="form-label">Ünvan</label>
                                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label for="department" class="form-label">Departman</label>
                                                        <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-primary">Profili Güncelle</button>
                                        </form>
                                    </div>

                                    <div class="tab-pane fade" id="password" role="tabpanel">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="change_password">
                                            
                                            <div class="mb-3">
                                                <label for="current_password" class="form-label">Mevcut Parola <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="new_password" class="form-label">Yeni Parola <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                                <div class="form-text">Parola en az 6 karakter olmalıdır.</div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label for="confirm_password" class="form-label">Yeni Parola (Tekrar) <span class="text-danger">*</span></label>
                                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                            </div>
                                            
                                            <button type="submit" class="btn btn-warning">Parolayı Değiştir</button>
                                        </form>
                                    </div>

                                    <div class="tab-pane fade" id="requests" role="tabpanel">
                                        <?php if (empty($recentRequests)): ?>
                                            <div class="text-center py-4">
                                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                                <p class="text-muted">Henüz talep oluşturmamışsınız.</p>
                                                <a href="new_request.php" class="btn btn-primary">İlk Talebinizi Oluşturun</a>
                                            </div>
                                        <?php else: ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th>Talep No</th>
                                                            <th>Kategori</th>
                                                            <th>Başlık</th>
                                                            <th>Durum</th>
                                                            <th>Tarih</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($recentRequests as $request): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                                                                <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                                                <td><?php echo htmlspecialchars($request['title']); ?></td>
                                                                <td>
                                                                    <?php
                                                                    $statusClass = '';
                                                                    $statusText = '';
                                                                    switch ($request['status']) {
                                                                        case 'pending':
                                                                            $statusClass = 'warning';
                                                                            $statusText = 'Beklemede';
                                                                            break;
                                                                        case 'assigned':
                                                                            $statusClass = 'info';
                                                                            $statusText = 'Atandı';
                                                                            break;
                                                                        case 'in_progress':
                                                                            $statusClass = 'primary';
                                                                            $statusText = 'İşlemde';
                                                                            break;
                                                                        case 'completed':
                                                                            $statusClass = 'success';
                                                                            $statusText = 'Tamamlandı';
                                                                            break;
                                                                        case 'cancelled':
                                                                            $statusClass = 'danger';
                                                                            $statusText = 'İptal Edildi';
                                                                            break;
                                                                        default:
                                                                            $statusClass = 'secondary';
                                                                            $statusText = ucfirst($request['status']);
                                                                    }
                                                                    ?>
                                                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                                </td>
                                                                <td><?php echo date('d.m.Y', strtotime($request['created_at'])); ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="text-center">
                                                <a href="requests.php" class="btn btn-outline-primary">Tüm Talepleri Görüntüle</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Parolalar eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('new_password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    </script>
</body>
</html>
