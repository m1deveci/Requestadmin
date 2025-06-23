<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$provinceId = $_SESSION['province_id'];

$query = "SELECT COUNT(*) as total FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          WHERE u.province_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId]);
$stats['total_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          WHERE u.province_id = ? AND r.status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId]);
$stats['pending_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          WHERE u.province_id = ? AND r.assigned_to = ?";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId, $_SESSION['user_id']]);
$stats['my_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          WHERE u.province_id = ? AND r.status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId]);
$stats['completed_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          WHERE u.province_id = ? AND r.status NOT IN ('completed', 'cancelled') 
          AND r.created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId]);
$stats['old_requests'] = $stmt->fetchColumn();

$query = "SELECT r.*, u.first_name, u.last_name, c.category_name 
          FROM requests r 
          JOIN users u ON r.employee_id = u.id 
          JOIN request_categories c ON r.category_id = c.id 
          WHERE u.province_id = ? 
          ORDER BY r.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$provinceId]);
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İdari İşler Dashboard - Request Admin</title>
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
                            <a class="nav-link active" href="dashboard.php">
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
                    <h1>İdari İşler Dashboard</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item active">Dashboard</li>
                        </ol>
                    </nav>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['total_requests']; ?></div>
                                    <div>Toplam Talep</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['pending_requests']; ?></div>
                                    <div>Bekleyen Talep</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['my_requests']; ?></div>
                                    <div>Bana Atanan</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['completed_requests']; ?></div>
                                    <div>Tamamlanan</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert for old requests -->
                <?php if ($stats['old_requests'] > 0): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Dikkat!</strong> 15 günden uzun süredir açık olan <?php echo $stats['old_requests']; ?> talep bulunmaktadır.
                    <a href="requests.php?filter=old" class="alert-link">Görüntüle</a>
                </div>
                <?php endif; ?>

                <!-- Recent Requests -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Son Talepler</h5>
                                <a href="requests.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_requests)): ?>
                                    <p class="text-muted">Henüz talep bulunmuyor.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Talep No</th>
                                                    <th>Çalışan</th>
                                                    <th>Kategori</th>
                                                    <th>Durum</th>
                                                    <th>Tarih</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_requests as $request): ?>
                                                    <tr>
                                                        <td>
                                                            <a href="request_detail.php?id=<?php echo $request['id']; ?>" class="text-decoration-none">
                                                                <?php echo htmlspecialchars($request['request_number']); ?>
                                                            </a>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                                                <?php echo getStatusText($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Hızlı İşlemler</h5>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <a href="requests.php?status=pending" class="btn btn-outline-warning">
                                        <i class="fas fa-clock me-2"></i>Bekleyen Talepler
                                    </a>
                                    <a href="requests.php?assigned_to=me" class="btn btn-outline-primary">
                                        <i class="fas fa-user-check me-2"></i>Bana Atananlar
                                    </a>
                                    <a href="employees.php?action=add" class="btn btn-outline-success">
                                        <i class="fas fa-user-plus me-2"></i>Çalışan Ekle
                                    </a>
                                    <a href="reports.php" class="btn btn-outline-info">
                                        <i class="fas fa-chart-bar me-2"></i>Raporlar
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">İstatistikler</h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="border-end">
                                            <h4 class="text-danger"><?php echo $stats['old_requests']; ?></h4>
                                            <small class="text-muted">15+ Gün Açık</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo $stats['completed_requests']; ?></h4>
                                        <small class="text-muted">Tamamlanan</small>
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
</body>
</html>
