<?php
session_start();
require_once '../config/database.php';
require_once '../config/mail.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_request':
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $priority = $_POST['priority'] ?? 'medium';
            
            if (!empty($title) && !empty($description)) {
                $query = "UPDATE requests SET title = ?, description = ?, priority = ?, updated_at = NOW() 
                         WHERE id = ? AND employee_id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$title, $description, $priority, $requestId, $userId])) {
                    $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                                   VALUES (?, 'updated', 'updated', ?, 'Talep güncellendi')";
                    $historyStmt = $db->prepare($historyQuery);
                    $historyStmt->execute([$requestId, $userId]);
                    
                    $_SESSION['success'] = 'Talep başarıyla güncellendi.';
                } else {
                    $_SESSION['error'] = 'Güncelleme sırasında hata oluştu.';
                }
            } else {
                $_SESSION['error'] = 'Başlık ve açıklama alanları zorunludur.';
            }
            break;
            
        case 'cancel_request':
            $reason = trim($_POST['cancel_reason'] ?? '');
            
            if (!empty($reason)) {
                $currentQuery = "SELECT status FROM requests WHERE id = ? AND employee_id = ?";
                $currentStmt = $db->prepare($currentQuery);
                $currentStmt->execute([$requestId, $userId]);
                $oldStatus = $currentStmt->fetchColumn();
                
                if ($oldStatus && $oldStatus !== 'completed' && $oldStatus !== 'cancelled') {
                    $query = "UPDATE requests SET status = 'cancelled', updated_at = NOW() 
                             WHERE id = ? AND employee_id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$requestId, $userId])) {
                        $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                                       VALUES (?, ?, 'cancelled', ?, ?)";
                        $historyStmt = $db->prepare($historyQuery);
                        $historyStmt->execute([$requestId, $oldStatus, $userId, $reason]);
                        
                        $mailService = new MailService();
                        $mailService->sendRequestNotification($requestId, 'cancelled');
                        
                        $_SESSION['success'] = 'Talep başarıyla iptal edildi.';
                    } else {
                        $_SESSION['error'] = 'İptal işlemi sırasında hata oluştu.';
                    }
                } else {
                    $_SESSION['error'] = 'Bu talep iptal edilemez.';
                }
            } else {
                $_SESSION['error'] = 'İptal sebebi belirtilmelidir.';
            }
            break;
    }
    
    header('Location: requests.php');
    exit;
}

$query = "SELECT r.*, rc.category_name, rc.requires_manager_approval,
                 u.first_name as assigned_first_name, u.last_name as assigned_last_name
          FROM requests r 
          LEFT JOIN request_categories rc ON r.category_id = rc.id
          LEFT JOIN users u ON r.assigned_to = u.id
          WHERE r.employee_id = ?
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$userQuery = "SELECT first_name, last_name FROM users WHERE id = ?";
$userStmt = $db->prepare($userQuery);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);

$totalRequests = count($requests);
$pendingRequests = count(array_filter($requests, function($r) { return $r['status'] === 'pending'; }));
$inProgressRequests = count(array_filter($requests, function($r) { return in_array($r['status'], ['assigned', 'in_progress']); }));
$completedRequests = count(array_filter($requests, function($r) { return $r['status'] === 'completed'; }));
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Taleplerim - Request Admin</title>
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Taleplerim</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $totalRequests; ?></h4>
                                        <p>Toplam Talep</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-list fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $pendingRequests; ?></h4>
                                        <p>Beklemede</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $inProgressRequests; ?></h4>
                                        <p>İşlemde</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-cog fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4><?php echo $completedRequests; ?></h4>
                                        <p>Tamamlanan</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Talep Listesi</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Henüz talep oluşturmamışsınız.</p>
                                <a href="new_request.php" class="btn btn-primary">İlk Talebinizi Oluşturun</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Talep No</th>
                                            <th>Kategori</th>
                                            <th>Başlık</th>
                                            <th>Durum</th>
                                            <th>Öncelik</th>
                                            <th>Atanan</th>
                                            <th>Tarih</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
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
                                                        case 'manager_approval':
                                                            $statusClass = 'secondary';
                                                            $statusText = 'Yönetici Onayı';
                                                            break;
                                                        default:
                                                            $statusClass = 'secondary';
                                                            $statusText = ucfirst($request['status']);
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $priorityClass = '';
                                                    $priorityText = '';
                                                    switch ($request['priority']) {
                                                        case 'low':
                                                            $priorityClass = 'success';
                                                            $priorityText = 'Düşük';
                                                            break;
                                                        case 'medium':
                                                            $priorityClass = 'warning';
                                                            $priorityText = 'Orta';
                                                            break;
                                                        case 'high':
                                                            $priorityClass = 'danger';
                                                            $priorityText = 'Yüksek';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge bg-<?php echo $priorityClass; ?>"><?php echo $priorityText; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($request['assigned_first_name']): ?>
                                                        <?php echo htmlspecialchars($request['assigned_first_name'] . ' ' . $request['assigned_last_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Atanmadı</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewRequest(<?php echo $request['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <?php if (in_array($request['status'], ['pending', 'assigned'])): ?>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="editRequest(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <div class="modal fade" id="viewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Talep Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetails">
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editRequestModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Talep Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_request">
                        <input type="hidden" name="request_id" id="editRequestId">
                        
                        <div class="mb-3">
                            <label for="editTitle" class="form-label">Başlık</label>
                            <input type="text" class="form-control" id="editTitle" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescription" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="editDescription" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editPriority" class="form-label">Öncelik</label>
                            <select class="form-select" id="editPriority" name="priority">
                                <option value="low">Düşük</option>
                                <option value="medium">Orta</option>
                                <option value="high">Yüksek</option>
                            </select>
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
                        <input type="hidden" name="request_id" id="cancelRequestId">
                        
                        <div class="mb-3">
                            <label for="cancelReason" class="form-label">İptal Sebebi</label>
                            <textarea class="form-control" id="cancelReason" name="cancel_reason" rows="3" required placeholder="Lütfen iptal sebebinizi belirtiniz..."></textarea>
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
    <script>
        function viewRequest(requestId) {
            fetch('../admin/ajax/get_request_details.php?id=' + requestId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('requestDetails').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('viewRequestModal')).show();
                });
        }

        function editRequest(requestId) {
            <?php foreach ($requests as $request): ?>
                if (<?php echo $request['id']; ?> == requestId) {
                    document.getElementById('editRequestId').value = requestId;
                    document.getElementById('editTitle').value = '<?php echo addslashes($request['title']); ?>';
                    document.getElementById('editDescription').value = '<?php echo addslashes($request['description']); ?>';
                    document.getElementById('editPriority').value = '<?php echo $request['priority']; ?>';
                    new bootstrap.Modal(document.getElementById('editRequestModal')).show();
                    return;
                }
            <?php endforeach; ?>
        }

        function cancelRequest(requestId) {
            document.getElementById('cancelRequestId').value = requestId;
            new bootstrap.Modal(document.getElementById('cancelRequestModal')).show();
        }
    </script>
</body>
</html>
