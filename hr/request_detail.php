<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../config/mail.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$requestId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];
$provinceId = $_SESSION['province_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'assign_to_me':
            $query = "UPDATE requests r 
                     JOIN users u ON r.employee_id = u.id 
                     SET r.assigned_to = ?, r.status = 'assigned', r.updated_at = NOW() 
                     WHERE r.id = ? AND u.province_id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$userId, $requestId, $provinceId])) {
                $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                               VALUES (?, 'pending', 'assigned', ?, 'Talep atandı')";
                $historyStmt = $db->prepare($historyQuery);
                $historyStmt->execute([$requestId, $userId]);
                
                $_SESSION['success'] = 'Talep size atandı.';
            }
            break;
            
        case 'update_status':
            $newStatus = $_POST['status'] ?? $_POST['new_status'] ?? '';
            $comments = $_POST['comments'] ?? '';
            
            if (empty($newStatus)) {
                $_SESSION['error'] = 'Lütfen geçerli bir durum seçin.';
                header('Location: request_detail.php?id=' . $requestId);
                exit;
            }
            
            $currentQuery = "SELECT r.status FROM requests r 
                           JOIN users u ON r.employee_id = u.id 
                           WHERE r.id = ? AND u.province_id = ?";
            $currentStmt = $db->prepare($currentQuery);
            $currentStmt->execute([$requestId, $provinceId]);
            $oldStatus = $currentStmt->fetchColumn();
            
            if ($oldStatus) {
                $query = "UPDATE requests r 
                         JOIN users u ON r.employee_id = u.id 
                         SET r.status = ?, r.updated_at = NOW() 
                         WHERE r.id = ? AND u.province_id = ?";
                if ($newStatus === 'completed') {
                    $query = "UPDATE requests r 
                             JOIN users u ON r.employee_id = u.id 
                             SET r.status = ?, r.completed_at = NOW(), r.updated_at = NOW() 
                             WHERE r.id = ? AND u.province_id = ?";
                }
                
                $stmt = $db->prepare($query);
                if ($stmt->execute([$newStatus, $requestId, $provinceId])) {
                    $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                                   VALUES (?, ?, ?, ?, ?)";
                    $historyStmt = $db->prepare($historyQuery);
                    $historyStmt->execute([$requestId, $oldStatus, $newStatus, $userId, $comments]);
                    
                    $mailService = new MailService();
                    $mailService->sendRequestNotification($requestId, 'status_update');
                    
                    $_SESSION['success'] = 'Talep durumu güncellendi.';
                }
            }
            break;
    }
    
    header('Location: request_detail.php?id=' . $requestId);
    exit;
}

$query = "SELECT r.*, u.first_name, u.last_name, u.email as employee_email, u.department,
                 c.category_name, comp.company_name, loc.location_name, prov.province_name,
                 CASE WHEN r.assigned_to IS NOT NULL THEN CONCAT(a.first_name, ' ', a.last_name) ELSE NULL END as assigned_to_name
          FROM requests r
          JOIN users u ON r.employee_id = u.id 
          JOIN request_categories c ON r.category_id = c.id 
          JOIN companies comp ON u.company_id = comp.id
          LEFT JOIN locations loc ON u.location_id = loc.id
          LEFT JOIN provinces prov ON u.province_id = prov.id
          LEFT JOIN users a ON r.assigned_to = a.id
          WHERE r.id = ? AND u.province_id = ?";

$stmt = $db->prepare($query);
$stmt->execute([$requestId, $provinceId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    $_SESSION['error'] = 'Talep bulunamadı veya erişim yetkiniz yok.';
    header('Location: requests.php');
    exit;
}

$historyQuery = "SELECT rsh.*, u.first_name, u.last_name 
                FROM request_status_history rsh
                LEFT JOIN users u ON rsh.changed_by = u.id
                WHERE rsh.request_id = ?
                ORDER BY rsh.created_at DESC";
$historyStmt = $db->prepare($historyQuery);
$historyStmt->execute([$requestId]);
$statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talep Detayı - İdari İşler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php 
            require_once '../includes/sidebar.php';
            renderSidebar();
            ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <h1>Talep Detayı</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="requests.php">Talepler</a></li>
                            <li class="breadcrumb-item active">Talep Detayı</li>
                        </ol>
                    </nav>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Request Details -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Talep Bilgileri</h5>
                                <span class="badge <?php echo getStatusBadgeClass($request['status']); ?> fs-6">
                                    <?php echo getStatusText($request['status']); ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Talep No:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($request['request_number']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Başlık:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($request['title']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Kategori:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($request['category_name']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Öncelik:</strong></div>
                                    <div class="col-sm-9">
                                        <span class="badge <?php echo getPriorityBadgeClass($request['priority']); ?>">
                                            <?php echo getPriorityText($request['priority']); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Açıklama:</strong></div>
                                    <div class="col-sm-9"><?php echo nl2br(htmlspecialchars($request['description'])); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Oluşturma Tarihi:</strong></div>
                                    <div class="col-sm-9"><?php echo formatDate($request['created_at']); ?></div>
                                </div>
                                <?php if ($request['updated_at']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Güncelleme Tarihi:</strong></div>
                                    <div class="col-sm-9"><?php echo formatDate($request['updated_at']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if ($request['completed_at']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Tamamlanma Tarihi:</strong></div>
                                    <div class="col-sm-9"><?php echo formatDate($request['completed_at']); ?></div>
                                </div>
                                <?php endif; ?>
                                <?php if (isset($request['attachment']) && $request['attachment']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Ek Dosya:</strong></div>
                                    <div class="col-sm-9">
                                        <a href="../uploads/<?php echo htmlspecialchars($request['attachment']); ?>" 
                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> Dosyayı İndir
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Status History -->
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Durum Geçmişi</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($statusHistory)): ?>
                                    <p class="text-muted">Henüz durum değişikliği yok.</p>
                                <?php else: ?>
                                    <div class="timeline">
                                        <?php foreach ($statusHistory as $history): ?>
                                            <div class="timeline-item">
                                                <div class="timeline-marker"></div>
                                                <div class="timeline-content">
                                                    <h6 class="mb-1">
                                                        <span class="badge <?php echo getStatusBadgeClass($history['old_status']); ?>">
                                                            <?php echo getStatusText($history['old_status']); ?>
                                                        </span>
                                                        <i class="fas fa-arrow-right mx-2"></i>
                                                        <span class="badge <?php echo getStatusBadgeClass($history['new_status']); ?>">
                                                            <?php echo getStatusText($history['new_status']); ?>
                                                        </span>
                                                    </h6>
                                                    <p class="mb-1">
                                                        <strong><?php echo htmlspecialchars($history['first_name'] . ' ' . $history['last_name']); ?></strong>
                                                        <small class="text-muted"><?php echo formatDate($history['created_at']); ?></small>
                                                    </p>
                                                    <?php if ($history['comments']): ?>
                                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($history['comments']); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Employee Info & Actions -->
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Çalışan Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Ad Soyad:</strong><br>
                                    <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>E-posta:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($request['employee_email']); ?>">
                                        <?php echo htmlspecialchars($request['employee_email']); ?>
                                    </a>
                                </div>
                                <?php if (isset($request['phone']) && $request['phone']): ?>
                                <div class="mb-3">
                                    <strong>Telefon:</strong><br>
                                    <?php echo htmlspecialchars($request['phone']); ?>
                                </div>
                                <?php endif; ?>
                                <div class="mb-3">
                                    <strong>Departman:</strong><br>
                                    <?php echo htmlspecialchars($request['department'] ?? 'Belirtilmemiş'); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Şirket:</strong><br>
                                    <?php echo htmlspecialchars($request['company_name']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Lokasyon:</strong><br>
                                    <?php echo htmlspecialchars($request['location_name'] ?? 'Belirtilmemiş'); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>İl:</strong><br>
                                    <?php echo htmlspecialchars($request['province_name'] ?? 'Belirtilmemiş'); ?>
                                </div>
                                <?php if ($request['assigned_to_name']): ?>
                                <div class="mb-3">
                                    <strong>Atanan:</strong><br>
                                    <?php echo htmlspecialchars($request['assigned_to_name']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">İşlemler</h5>
                            </div>
                            <div class="card-body">
                                <?php if ($request['status'] === 'pending' && !$request['assigned_to']): ?>
                                    <form method="POST" class="mb-3">
                                        <input type="hidden" name="action" value="assign_to_me">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-user-check"></i> Bana Ata
                                        </button>
                                    </form>
                                <?php endif; ?>
                                
                                <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#statusModal">
                                    <i class="fas fa-edit"></i> Durum Güncelle
                                </button>
                                <button class="btn btn-danger w-100" onclick="rejectRequest()">
                                    <i class="fas fa-times"></i> Reddet
                                </button>
                                
                                <a href="requests.php" class="btn btn-secondary w-100 mt-2">
                                    <i class="fas fa-arrow-left"></i> Taleplere Dön
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Durum Güncelle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Yeni Durum</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Durum Seçin</option>
                                <option value="assigned" <?php echo $request['status'] == 'assigned' ? 'selected' : ''; ?>>Atandı</option>
                                <option value="in_progress" <?php echo $request['status'] == 'in_progress' ? 'selected' : ''; ?>>İşlemde</option>
                                <option value="completed" <?php echo $request['status'] == 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                <option value="cancelled" <?php echo $request['status'] == 'cancelled' ? 'selected' : ''; ?>>İptal Edildi</option>
                                <option value="rejected" <?php echo $request['status'] == 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assigned_to" class="form-label">Atanan Kişi</label>
                            <select class="form-select" id="assigned_to" name="assigned_to">
                                <option value="">Atama Yapılmadı</option>
                                <?php 
                                $hr_query = "SELECT id, first_name, last_name FROM users WHERE role = 'hr' AND province_id = ?";
                                $hr_stmt = $db->prepare($hr_query);
                                $hr_stmt->execute([$_SESSION['province_id']]);
                                $hr_staff = $hr_stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($hr_staff as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" <?php echo $request['assigned_to'] == $staff['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="comments" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="comments" name="comments" rows="3" 
                                      placeholder="Durum değişikliği hakkında açıklama..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function rejectRequest() {
            const reason = prompt('Lütfen red sebebini belirtiniz:');
            if (reason && reason.trim()) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="status" value="rejected">
                    <input type="hidden" name="comments" value="${reason}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
