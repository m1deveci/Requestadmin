<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

$stats = [];

$query = "SELECT COUNT(*) as total FROM companies";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_companies'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM companies WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_companies'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM users";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_users'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['total_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests WHERE status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['pending_requests'] = $stmt->fetchColumn();

$query = "SELECT 'company_registration' as type, company_name as title, created_at 
          FROM companies 
          WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
          UNION ALL
          SELECT 'request' as type, CONCAT('REQ-', id, ': ', title) as title, created_at 
          FROM requests 
          WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
          ORDER BY created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Request Admin</title>
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
                    <h1>Admin Dashboard</h1>
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
                                    <div class="card-number"><?php echo $stats['total_companies']; ?></div>
                                    <div>Toplam Firma</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['pending_companies']; ?></div>
                                    <div>Bekleyen Firma</div>
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
                                    <div class="card-number"><?php echo $stats['total_users']; ?></div>
                                    <div>Toplam Kullanıcı</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
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
                </div>

                <!-- Recent Activities -->
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Son Aktiviteler</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_activities)): ?>
                                    <p class="text-muted">Henüz aktivite bulunmuyor.</p>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recent_activities as $activity): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas <?php echo $activity['type'] === 'company_registration' ? 'fa-building' : 'fa-tasks'; ?> me-2"></i>
                                                    <?php echo htmlspecialchars($activity['title']); ?>
                                                </div>
                                                <small class="text-muted"><?php echo formatDate($activity['created_at']); ?></small>
                                            </div>
                                        <?php endforeach; ?>
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
                                    <a href="companies.php?status=pending" class="btn btn-outline-primary">
                                        <i class="fas fa-check me-2"></i>Firma Onayları
                                    </a>
                                    <a href="users.php?action=add" class="btn btn-outline-success">
                                        <i class="fas fa-user-plus me-2"></i>Kullanıcı Ekle
                                    </a>
                                    <a href="locations.php?action=add" class="btn btn-outline-info">
                                        <i class="fas fa-map-marker-alt me-2"></i>Lokasyon Ekle
                                    </a>
                                    <a href="settings.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-cog me-2"></i>Sistem Ayarları
                                    </a>
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
