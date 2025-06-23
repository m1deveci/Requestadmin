<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$locationId = $_SESSION['location_id'];
$companyId = $_SESSION['company_id'];

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$categoryFilter = $_GET['category'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$whereConditions = ["u.location_id = ?"];
$params = [$locationId];

if (!empty($dateFrom)) {
    $whereConditions[] = "r.created_at >= ?";
    $params[] = $dateFrom . ' 00:00:00';
}

if (!empty($dateTo)) {
    $whereConditions[] = "r.created_at <= ?";
    $params[] = $dateTo . ' 23:59:59';
}

if (!empty($categoryFilter)) {
    $whereConditions[] = "r.category_id = ?";
    $params[] = $categoryFilter;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "r.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$totalQuery = "SELECT COUNT(*) FROM requests r JOIN users u ON r.employee_id = u.id WHERE $whereClause";
$totalStmt = $db->prepare($totalQuery);
$totalStmt->execute($params);
$totalRequests = $totalStmt->fetchColumn();

$statusQuery = "SELECT r.status, COUNT(*) as count 
                FROM requests r 
                JOIN users u ON r.employee_id = u.id 
                WHERE $whereClause 
                GROUP BY r.status 
                ORDER BY count DESC";
$statusStmt = $db->prepare($statusQuery);
$statusStmt->execute($params);
$statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);

$categoryQuery = "SELECT c.category_name, COUNT(*) as count 
                  FROM requests r 
                  JOIN users u ON r.employee_id = u.id 
                  JOIN request_categories c ON r.category_id = c.id 
                  WHERE $whereClause 
                  GROUP BY c.category_name 
                  ORDER BY count DESC";
$categoryStmt = $db->prepare($categoryQuery);
$categoryStmt->execute($params);
$categoryStats = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);

$monthlyQuery = "SELECT DATE_FORMAT(r.created_at, '%Y-%m') as month, COUNT(*) as count 
                 FROM requests r 
                 JOIN users u ON r.employee_id = u.id 
                 WHERE $whereClause 
                 GROUP BY DATE_FORMAT(r.created_at, '%Y-%m') 
                 ORDER BY month DESC 
                 LIMIT 12";
$monthlyStmt = $db->prepare($monthlyQuery);
$monthlyStmt->execute($params);
$monthlyStats = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

$avgQuery = "SELECT AVG(TIMESTAMPDIFF(DAY, r.created_at, r.completed_at)) as avg_days 
             FROM requests r 
             JOIN users u ON r.employee_id = u.id 
             WHERE $whereClause AND r.status = 'completed' AND r.completed_at IS NOT NULL";
$avgStmt = $db->prepare($avgQuery);
$avgStmt->execute($params);
$avgCompletionDays = round($avgStmt->fetchColumn() ?? 0, 1);

$employeeQuery = "SELECT u.first_name, u.last_name, COUNT(*) as request_count 
                  FROM requests r 
                  JOIN users u ON r.employee_id = u.id 
                  WHERE $whereClause 
                  GROUP BY u.id, u.first_name, u.last_name 
                  ORDER BY request_count DESC 
                  LIMIT 10";
$employeeStmt = $db->prepare($employeeQuery);
$employeeStmt->execute($params);
$employeeStats = $employeeStmt->fetchAll(PDO::FETCH_ASSOC);

$categoriesQuery = "SELECT id, category_name FROM request_categories ORDER BY category_name";
$categoriesStmt = $db->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Request Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                            <a class="nav-link active" href="reports.php">
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
                    <h1>Raporlar ve İstatistikler</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Raporlar</li>
                        </ol>
                    </nav>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Kategori</label>
                                <select class="form-select" name="category">
                                    <option value="">Tümü</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $categoryFilter == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Durum</label>
                                <select class="form-select" name="status">
                                    <option value="">Tümü</option>
                                    <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                                    <option value="assigned" <?php echo $statusFilter === 'assigned' ? 'selected' : ''; ?>>Atanmış</option>
                                    <option value="in_progress" <?php echo $statusFilter === 'in_progress' ? 'selected' : ''; ?>>İşlemde</option>
                                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Tamamlandı</option>
                                    <option value="cancelled" <?php echo $statusFilter === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-filter"></i> Filtrele
                                </button>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-success" onclick="exportReport()">
                                    <i class="fas fa-download"></i> Dışa Aktar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $totalRequests; ?></div>
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
                                    <div class="card-number"><?php echo $avgCompletionDays; ?></div>
                                    <div>Ort. Tamamlanma (Gün)</div>
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
                                    <div class="card-number"><?php echo count($categoryStats); ?></div>
                                    <div>Aktif Kategori</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tags"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-xl-3 col-md-6">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo count($employeeStats); ?></div>
                                    <div>Talep Yapan Çalışan</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row mb-4">
                    <!-- Status Distribution -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Durum Dağılımı</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Category Distribution -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Kategori Dağılımı</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="categoryChart" height="300"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Monthly Trend -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Aylık Talep Trendi</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="monthlyChart" height="100"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tables Row -->
                <div class="row">
                    <!-- Top Employees -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">En Çok Talep Yapan Çalışanlar</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($employeeStats)): ?>
                                    <p class="text-muted">Veri bulunamadı.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Çalışan</th>
                                                    <th class="text-end">Talep Sayısı</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($employeeStats as $employee): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></td>
                                                        <td class="text-end">
                                                            <span class="badge bg-primary"><?php echo $employee['request_count']; ?></span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Details -->
                    <div class="col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Durum Detayları</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($statusStats)): ?>
                                    <p class="text-muted">Veri bulunamadı.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Durum</th>
                                                    <th class="text-end">Sayı</th>
                                                    <th class="text-end">Yüzde</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($statusStats as $status): ?>
                                                    <tr>
                                                        <td>
                                                            <span class="badge <?php echo getStatusBadgeClass($status['status']); ?>">
                                                                <?php echo getStatusText($status['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end"><?php echo $status['count']; ?></td>
                                                        <td class="text-end"><?php echo round(($status['count'] / $totalRequests) * 100, 1); ?>%</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($s) { return '"' . getStatusText($s['status']) . '"'; }, $statusStats)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($statusStats, 'count')); ?>],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: [<?php echo implode(',', array_map(function($c) { return '"' . htmlspecialchars($c['category_name']) . '"'; }, $categoryStats)); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_column($categoryStats, 'count')); ?>],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                        '#FF6384',
                        '#36A2EB'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: [<?php echo implode(',', array_map(function($m) { return '"' . $m['month'] . '"'; }, array_reverse($monthlyStats))); ?>],
                datasets: [{
                    label: 'Talep Sayısı',
                    data: [<?php echo implode(',', array_column(array_reverse($monthlyStats), 'count')); ?>],
                    borderColor: '#36A2EB',
                    backgroundColor: 'rgba(54, 162, 235, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.open('export_report.php?' + params.toString(), '_blank');
        }
    </script>
</body>
</html>
