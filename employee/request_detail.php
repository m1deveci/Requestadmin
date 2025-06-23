<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

$database = new Database();
$db = $database->getConnection();
$userId = $_SESSION['user_id'];
$requestId = $_GET['id'] ?? '';

if (empty($requestId)) {
    header('Location: requests.php');
    exit;
}

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
          WHERE r.id = ? AND r.employee_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$request) {
    header('Location: requests.php');
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

$categoriesQuery = "SELECT * FROM request_categories ORDER BY category_name";
$categoriesStmt = $db->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_request':
            if (in_array($request['status'], ['pending', 'assigned'])) {
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category_id = $_POST['category_id'] ?? '';
                $priority = $_POST['priority'] ?? 'medium';
                
                if (!empty($title) && !empty($description) && !empty($category_id)) {
                    $updateQuery = "UPDATE requests SET title = ?, description = ?, category_id = ?, priority = ?, updated_at = NOW() WHERE id = ? AND employee_id = ?";
                    $updateStmt = $db->prepare($updateQuery);
                    
                    if ($updateStmt->execute([$title, $description, $category_id, $priority, $requestId, $userId])) {
                        $logQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) VALUES (?, ?, ?, ?, ?)";
                        $logStmt = $db->prepare($logQuery);
                        $logStmt->execute([$requestId, $request['status'], $request['status'], $userId, 'Talep güncellendi']);
                        
                        $success_message = "Talep başarıyla güncellendi.";
                        $stmt->execute([$requestId, $userId]);
                        $request = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error_message = "Talep güncellenirken hata oluştu.";
                    }
                } else {
                    $error_message = "Lütfen tüm gerekli alanları doldurun.";
                }
            }
            break;
            
        case 'cancel_request':
            $reason = trim($_POST['cancel_reason'] ?? '');
            if (!empty($reason) && in_array($request['status'], ['pending', 'assigned'])) {
                $cancelQuery = "UPDATE requests SET status = 'cancelled', updated_at = NOW() WHERE id = ? AND employee_id = ?";
                $cancelStmt = $db->prepare($cancelQuery);
                
                if ($cancelStmt->execute([$requestId, $userId])) {
                    $logQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) VALUES (?, ?, ?, ?, ?)";
                    $logStmt = $db->prepare($logQuery);
                    $logStmt->execute([$requestId, $request['status'], 'cancelled', $userId, 'İptal sebebi: ' . $reason]);
                    
                    header('Location: requests.php?message=cancelled');
                    exit;
                }
            }
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talep Detayı - Request Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Çalışan Paneli</h5>
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
                            <a class="nav-link active" href="requests.php">
                                <i class="fas fa-tasks"></i> Taleplerim
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="new_request.php">
                                <i class="fas fa-plus"></i> Yeni Talep
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <h1>Talep Detayı</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="requests.php">Taleplerim</a></li>
                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($request['request_number']); ?></li>
                        </ol>
                    </nav>
                </div>

                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Talep Bilgileri</h5>
                                <div>
                                    <?php if (in_array($request['status'], ['pending', 'assigned'])): ?>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editRequestModal">
                                            <i class="fas fa-edit"></i> Düzenle
                                        </button>
                                        <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#cancelRequestModal">
                                            <i class="fas fa-times"></i> İptal Et
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <td><strong>Talep No:</strong></td>
                                        <td><?php echo htmlspecialchars($request['request_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Başlık:</strong></td>
                                        <td><?php echo htmlspecialchars($request['title']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Kategori:</strong></td>
                                        <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Öncelik:</strong></td>
                                        <td>
                                            <span class="badge <?php echo getPriorityBadgeClass($request['priority']); ?>">
                                                <?php echo getPriorityText($request['priority']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Durum:</strong></td>
                                        <td>
                                            <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                                <?php echo getStatusText($request['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Oluşturma Tarihi:</strong></td>
                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                    </tr>
                                    <?php if ($request['assigned_to']): ?>
                                    <tr>
                                        <td><strong>Atanan:</strong></td>
                                        <td><?php echo htmlspecialchars($request['assigned_first_name'] . ' ' . $request['assigned_last_name']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>

                                <h6 class="mt-4">Açıklama</h6>
                                <p class="border p-3 rounded bg-light"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>

                                <?php if ($request['image']): ?>
                                <h6 class="mt-4">Ek Dosya</h6>
                                <a href="../uploads/requests/<?php echo htmlspecialchars($request['image']); ?>" target="_blank" class="btn btn-outline-primary">
                                    <i class="fas fa-download"></i> Dosyayı İndir
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>

                        <?php if (!empty($history)): ?>
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Durum Geçmişi</h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <?php foreach ($history as $item): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div class="timeline-content">
                                            <h6 class="timeline-title"><?php echo getStatusText($item['new_status']); ?></h6>
                                            <p class="timeline-text">
                                                <?php if ($item['old_status']): ?>
                                                    Durum <strong><?php echo getStatusText($item['old_status']); ?></strong> 'den <strong><?php echo getStatusText($item['new_status']); ?></strong> 'e değiştirildi.
                                                <?php else: ?>
                                                    Talep oluşturuldu.
                                                <?php endif; ?>
                                                <br><small class="text-muted">
                                                    Değiştiren: <?php echo htmlspecialchars($item['first_name'] . ' ' . $item['last_name']); ?> - 
                                                    <?php echo formatDate($item['created_at']); ?>
                                                </small>
                                                <?php if ($item['comments']): ?>
                                                    <br><em><?php echo htmlspecialchars($item['comments']); ?></em>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Çalışan Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless table-sm">
                                    <tr>
                                        <td><strong>Ad Soyad:</strong></td>
                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>E-posta:</strong></td>
                                        <td><?php echo htmlspecialchars($request['email']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Ünvan:</strong></td>
                                        <td><?php echo htmlspecialchars($request['user_title'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Departman:</strong></td>
                                        <td><?php echo htmlspecialchars($request['department'] ?? '-'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Firma:</strong></td>
                                        <td><?php echo htmlspecialchars($request['company_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Lokasyon:</strong></td>
                                        <td><?php echo htmlspecialchars($request['location_name'] ?? '-'); ?></td>
                                    </tr>
                                    <?php if ($request['manager_first_name']): ?>
                                    <tr>
                                        <td><strong>Yönetici:</strong></td>
                                        <td><?php echo htmlspecialchars($request['manager_first_name'] . ' ' . $request['manager_last_name']); ?></td>
                                    </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="editRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Talep Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_request">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Kategori *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>" <?php echo $category['id'] == $request['category_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="priority" class="form-label">Öncelik</label>
                                    <select class="form-select" id="priority" name="priority">
                                        <option value="low" <?php echo $request['priority'] == 'low' ? 'selected' : ''; ?>>Düşük</option>
                                        <option value="medium" <?php echo $request['priority'] == 'medium' ? 'selected' : ''; ?>>Orta</option>
                                        <option value="high" <?php echo $request['priority'] == 'high' ? 'selected' : ''; ?>>Yüksek</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Talep Başlığı *</label>
                            <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($request['title']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Detaylı Açıklama *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required><?php echo htmlspecialchars($request['description']); ?></textarea>
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

    <div class="modal fade" id="cancelRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Talep İptal Et</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="cancel_request">
                        
                        <div class="mb-3">
                            <label for="cancel_reason" class="form-label">İptal Sebebi *</label>
                            <textarea class="form-control" id="cancel_reason" name="cancel_reason" rows="3" required placeholder="Lütfen talep iptal sebebinizi açıklayın..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Bu işlem geri alınamaz. Talep iptal edildikten sonra tekrar aktif hale getirilemez.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                        <button type="submit" class="btn btn-danger">İptal Et</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
