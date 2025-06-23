<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

$logType = $_GET['type'] ?? 'login';
$filterDate = $_GET['date'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

if ($logType === 'login') {
    $whereConditions = [];
    $params = [];
    
    if ($filterDate) {
        $whereConditions[] = "DATE(created_at) = ?";
        $params[] = $filterDate;
    }
    if ($filterStatus) {
        $whereConditions[] = "login_status = ?";
        $params[] = $filterStatus;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "SELECT ll.*, u.first_name, u.last_name, c.company_name
              FROM login_logs ll
              LEFT JOIN users u ON ll.user_id = u.id
              LEFT JOIN companies c ON u.company_id = c.id
              $whereClause
              ORDER BY ll.created_at DESC
              LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countQuery = "SELECT COUNT(*) FROM login_logs ll
                   LEFT JOIN users u ON ll.user_id = u.id
                   $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
    
} elseif ($logType === 'request') {
    $whereConditions = [];
    $params = [];
    
    if ($filterDate) {
        $whereConditions[] = "DATE(rsh.created_at) = ?";
        $params[] = $filterDate;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    $query = "SELECT rsh.*, r.request_number, r.title, 
              u.first_name, u.last_name, c.company_name,
              changed_by_user.first_name as changed_by_first_name, 
              changed_by_user.last_name as changed_by_last_name
              FROM request_status_history rsh
              JOIN requests r ON rsh.request_id = r.id
              JOIN users u ON r.employee_id = u.id
              JOIN companies c ON u.company_id = c.id
              JOIN users changed_by_user ON rsh.changed_by = changed_by_user.id
              $whereClause
              ORDER BY rsh.created_at DESC
              LIMIT $limit OFFSET $offset";
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $countQuery = "SELECT COUNT(*) FROM request_status_history rsh
                   JOIN requests r ON rsh.request_id = r.id
                   JOIN users u ON r.employee_id = u.id
                   $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalLogs = $countStmt->fetchColumn();
} elseif ($logType === 'admin') {
    $whereConditions = [];
    $params = [];
    
    if ($filterDate) {
        $whereConditions[] = "DATE(al.created_at) = ?";
        $params[] = $filterDate;
    }
    
    $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
    try {
        $query = "SELECT al.*, u.first_name, u.last_name, c.company_name
                  FROM admin_logs al
                  JOIN users u ON al.user_id = u.id
                  LEFT JOIN companies c ON u.company_id = c.id
                  $whereClause
                  ORDER BY al.created_at DESC
                  LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $countQuery = "SELECT COUNT(*) FROM admin_logs al
                       JOIN users u ON al.user_id = u.id
                       $whereClause";
        $countStmt = $db->prepare($countQuery);
        $countStmt->execute($params);
        $totalLogs = $countStmt->fetchColumn();
    } catch (Exception $e) {
        $logs = [];
        $totalLogs = 0;
    }
}

$totalPages = ceil($totalLogs / $limit);

$stats = [];
if ($logType === 'login') {
    $query = "SELECT login_status, COUNT(*) as count FROM login_logs GROUP BY login_status";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statusCounts as $stat) {
        $stats[$stat['login_status']] = $stat['count'];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Logları - Request Admin</title>
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
                    <h1>Sistem Logları</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Loglar</li>
                        </ol>
                    </nav>
                </div>

                <!-- Log Type Tabs -->
                <ul class="nav nav-tabs mb-4">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $logType === 'login' ? 'active' : ''; ?>" href="?type=login">
                            <i class="fas fa-sign-in-alt"></i> Giriş Logları
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $logType === 'request' ? 'active' : ''; ?>" href="?type=request">
                            <i class="fas fa-tasks"></i> Talep Logları
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo $logType === 'admin' ? 'active' : ''; ?>" href="?type=admin">
                            <i class="fas fa-user-shield"></i> Admin İşlemleri
                        </a>
                    </li>
                </ul>

                <?php if ($logType === 'login' && !empty($stats)): ?>
                <!-- Statistics Cards for Login Logs -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['success'] ?? 0; ?></div>
                                    <div>Başarılı Giriş</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['failed'] ?? 0; ?></div>
                                    <div>Başarısız Giriş</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-times"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($logType); ?>">
                            
                            <div class="col-md-3">
                                <label for="date" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="date" name="date" value="<?php echo htmlspecialchars($filterDate); ?>">
                            </div>
                            
                            <?php if ($logType === 'login'): ?>
                            <div class="col-md-3">
                                <label for="status" class="form-label">Durum</label>
                                <select name="status" class="form-select">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="success" <?php echo $filterStatus === 'success' ? 'selected' : ''; ?>>Başarılı</option>
                                    <option value="failed" <?php echo $filterStatus === 'failed' ? 'selected' : ''; ?>>Başarısız</option>
                                </select>
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="logs.php?type=<?php echo htmlspecialchars($logType); ?>" class="btn btn-secondary">Temizle</a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Logs Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <?php 
                            $titles = [
                                'login' => 'Giriş Logları',
                                'request' => 'Talep Logları',
                                'admin' => 'Admin İşlem Logları'
                            ];
                            echo $titles[$logType] ?? 'Loglar'; 
                            ?> 
                            (<?php echo $totalLogs; ?> kayıt)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <?php if ($logType === 'login'): ?>
                                            <th>Tarih</th>
                                            <th>Kullanıcı</th>
                                            <th>Firma</th>
                                            <th>Email</th>
                                            <th>IP Adresi</th>
                                            <th>User Agent</th>
                                            <th>Durum</th>
                                        <?php elseif ($logType === 'request'): ?>
                                            <th>Tarih</th>
                                            <th>Talep No</th>
                                            <th>Talep Başlığı</th>
                                            <th>Çalışan</th>
                                            <th>Firma</th>
                                            <th>Eski Durum</th>
                                            <th>Yeni Durum</th>
                                            <th>Değiştiren</th>
                                        <?php else: // admin ?>
                                            <th>Tarih</th>
                                            <th>Kullanıcı</th>
                                            <th>Firma</th>
                                            <th>İşlem</th>
                                            <th>Açıklama</th>
                                            <th>IP Adresi</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <?php if ($logType === 'admin'): ?>
                                                    Admin işlem logları henüz mevcut değil. Veritabanı tablosu oluşturulduktan sonra loglar burada görünecektir.
                                                <?php else: ?>
                                                    Henüz log kaydı bulunmuyor.
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($logs as $log): ?>
                                            <tr>
                                                <?php if ($logType === 'login'): ?>
                                                    <td><?php echo formatDate($log['created_at']); ?></td>
                                                    <td>
                                                        <?php if ($log['first_name']): ?>
                                                            <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">Bilinmeyen</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($log['company_name']): ?>
                                                            <?php echo htmlspecialchars($log['company_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['email']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                    <td>
                                                        <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                            <?php echo htmlspecialchars(substr($log['user_agent'], 0, 50)); ?>...
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo $log['login_status'] === 'success' ? 'success' : 'danger'; ?>">
                                                            <?php echo $log['login_status'] === 'success' ? 'Başarılı' : 'Başarısız'; ?>
                                                        </span>
                                                    </td>
                                                <?php elseif ($logType === 'request'): ?>
                                                    <td><?php echo formatDate($log['created_at']); ?></td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($log['request_number']); ?></strong>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['title']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['company_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeColor($log['old_status']); ?>">
                                                            <?php echo getStatusText($log['old_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php echo getStatusBadgeColor($log['new_status']); ?>">
                                                            <?php echo getStatusText($log['new_status']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['changed_by_first_name'] . ' ' . $log['changed_by_last_name']); ?>
                                                    </td>
                                                <?php else: // admin ?>
                                                    <td><?php echo formatDate($log['created_at']); ?></td>
                                                    <td>
                                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($log['company_name']): ?>
                                                            <?php echo htmlspecialchars($log['company_name']); ?>
                                                        <?php else: ?>
                                                            <span class="text-muted">System Admin</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($log['action']); ?></span>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($log['description']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                            <nav aria-label="Log pagination">
                                <ul class="pagination justify-content-center">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $page - 1; ?>&date=<?php echo $filterDate; ?>&status=<?php echo $filterStatus; ?>">Önceki</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $i; ?>&date=<?php echo $filterDate; ?>&status=<?php echo $filterStatus; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?type=<?php echo $logType; ?>&page=<?php echo $page + 1; ?>&date=<?php echo $filterDate; ?>&status=<?php echo $filterStatus; ?>">Sonraki</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
