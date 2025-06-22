<?php
session_start();
require_once '../config/database.php';
require_once '../config/mail.php';
require_once '../includes/functions.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$locationId = $_SESSION['location_id'];
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $requestId = $_POST['request_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'assign_to_me':
            $query = "UPDATE requests SET assigned_to = ?, status = 'assigned' WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$userId, $requestId])) {
                $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                               VALUES (?, 'pending', 'assigned', ?, 'Talep atandı')";
                $historyStmt = $db->prepare($historyQuery);
                $historyStmt->execute([$requestId, $userId]);
                
                $mailService = new MailService();
                $mailService->sendRequestNotification($requestId, 'status_update');
                
                $_SESSION['success'] = 'Talep size atandı.';
            }
            break;
            
        case 'update_status':
            $newStatus = $_POST['new_status'];
            $comments = $_POST['comments'] ?? '';
            
            $currentQuery = "SELECT status FROM requests WHERE id = ?";
            $currentStmt = $db->prepare($currentQuery);
            $currentStmt->execute([$requestId]);
            $oldStatus = $currentStmt->fetchColumn();
            
            $query = "UPDATE requests SET status = ?, updated_at = NOW() WHERE id = ?";
            if ($newStatus === 'completed') {
                $query = "UPDATE requests SET status = ?, completed_at = NOW(), updated_at = NOW() WHERE id = ?";
            }
            
            $stmt = $db->prepare($query);
            if ($stmt->execute([$newStatus, $requestId])) {
                $historyQuery = "INSERT INTO request_status_history (request_id, old_status, new_status, changed_by, comments) 
                               VALUES (?, ?, ?, ?, ?)";
                $historyStmt = $db->prepare($historyQuery);
                $historyStmt->execute([$requestId, $oldStatus, $newStatus, $userId, $comments]);
                
                $mailService = new MailService();
                $mailService->sendRequestNotification($requestId, 'status_update');
                
                $_SESSION['success'] = 'Talep durumu güncellendi.';
            }
            break;
    }
    
    header('Location: requests.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$assignedFilter = $_GET['assigned_to'] ?? 'all';

$whereClause = 'WHERE u.location_id = ?';
$params = [$locationId];

if ($statusFilter !== 'all') {
    $whereClause .= ' AND r.status = ?';
    $params[] = $statusFilter;
}

if ($assignedFilter === 'me') {
    $whereClause .= ' AND r.assigned_to = ?';
    $params[] = $userId;
} elseif ($assignedFilter === 'unassigned') {
    $whereClause .= ' AND r.assigned_to IS NULL';
}

$query = "SELECT r.*, u.first_name, u.last_name, c.category_name,
                 CASE WHEN r.assigned_to IS NOT NULL THEN CONCAT(a.first_name, ' ', a.last_name) ELSE NULL END as assigned_to_name
          FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          JOIN request_categories c ON r.category_id = c.id 
          LEFT JOIN users a ON r.assigned_to = a.id
          $whereClause 
          ORDER BY r.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talepler - İdari İşler</title>
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
                            <a class="nav-link active" href="requests.php">
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

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="page-header">
                    <h1>Talep Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Talepler</li>
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

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="btn-group me-3" role="group">
                                    <a href="requests.php?status=all&assigned_to=<?php echo $assignedFilter; ?>" 
                                       class="btn <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Tümü
                                    </a>
                                    <a href="requests.php?status=pending&assigned_to=<?php echo $assignedFilter; ?>" 
                                       class="btn <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                        Bekleyen
                                    </a>
                                    <a href="requests.php?status=assigned&assigned_to=<?php echo $assignedFilter; ?>" 
                                       class="btn <?php echo $statusFilter === 'assigned' ? 'btn-info' : 'btn-outline-info'; ?>">
                                        Atanan
                                    </a>
                                    <a href="requests.php?status=in_progress&assigned_to=<?php echo $assignedFilter; ?>" 
                                       class="btn <?php echo $statusFilter === 'in_progress' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        İşlemde
                                    </a>
                                    <a href="requests.php?status=completed&assigned_to=<?php echo $assignedFilter; ?>" 
                                       class="btn <?php echo $statusFilter === 'completed' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        Tamamlanan
                                    </a>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <a href="requests.php?status=<?php echo $statusFilter; ?>&assigned_to=all" 
                                       class="btn <?php echo $assignedFilter === 'all' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                                        Tüm Talepler
                                    </a>
                                    <a href="requests.php?status=<?php echo $statusFilter; ?>&assigned_to=me" 
                                       class="btn <?php echo $assignedFilter === 'me' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                                        Bana Atanan
                                    </a>
                                    <a href="requests.php?status=<?php echo $statusFilter; ?>&assigned_to=unassigned" 
                                       class="btn <?php echo $assignedFilter === 'unassigned' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                                        Atanmamış
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Talepler (<?php echo count($requests); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <p class="text-muted text-center py-4">Talep bulunamadı.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Talep No</th>
                                            <th>Çalışan</th>
                                            <th>Başlık</th>
                                            <th>Kategori</th>
                                            <th>Öncelik</th>
                                            <th>Durum</th>
                                            <th>Atanan</th>
                                            <th>Tarih</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <a href="request_detail.php?id=<?php echo $request['id']; ?>" class="text-decoration-none">
                                                        <?php echo htmlspecialchars($request['request_number']); ?>
                                                    </a>
                                                </td>
                                                <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($request['title']); ?></td>
                                                <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                                <td>
                                                    <span class="badge <?php echo getPriorityBadgeClass($request['priority']); ?>">
                                                        <?php echo getPriorityText($request['priority']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                                        <?php echo getStatusText($request['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $request['assigned_to_name'] ? htmlspecialchars($request['assigned_to_name']) : '-'; ?></td>
                                                <td><?php echo formatDate($request['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <a href="request_detail.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-info">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($request['status'] === 'pending' && !$request['assigned_to']): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                                <button type="submit" name="action" value="assign_to_me" 
                                                                        class="btn btn-outline-primary" title="Bana Ata">
                                                                    <i class="fas fa-user-check"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <?php if ($request['assigned_to'] == $userId): ?>
                                                            <button class="btn btn-outline-success" data-bs-toggle="modal" 
                                                                    data-bs-target="#statusModal<?php echo $request['id']; ?>" title="Durum Güncelle">
                                                                <i class="fas fa-edit"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
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

    <!-- Status Update Modals -->
    <?php foreach ($requests as $request): ?>
        <?php if ($request['assigned_to'] == $userId): ?>
            <div class="modal fade" id="statusModal<?php echo $request['id']; ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Durum Güncelle - <?php echo htmlspecialchars($request['request_number']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <div class="modal-body">
                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                <input type="hidden" name="action" value="update_status">
                                
                                <div class="mb-3">
                                    <label for="new_status<?php echo $request['id']; ?>" class="form-label">Yeni Durum</label>
                                    <select class="form-select" id="new_status<?php echo $request['id']; ?>" name="new_status" required>
                                        <option value="in_progress" <?php echo $request['status'] === 'in_progress' ? 'selected' : ''; ?>>İşlemde</option>
                                        <option value="completed" <?php echo $request['status'] === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                        <option value="rejected" <?php echo $request['status'] === 'rejected' ? 'selected' : ''; ?>>Reddedildi</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="comments<?php echo $request['id']; ?>" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="comments<?php echo $request['id']; ?>" name="comments" rows="3"></textarea>
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
        <?php endif; ?>
    <?php endforeach; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
</code>
