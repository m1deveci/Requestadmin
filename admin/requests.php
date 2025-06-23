<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

$filterStatus = $_GET['status'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$filterCategory = $_GET['category'] ?? '';

$whereConditions = [];
$params = [];

if ($filterStatus) {
    $whereConditions[] = "r.status = ?";
    $params[] = $filterStatus;
}
if ($filterCompany) {
    $whereConditions[] = "u.company_id = ?";
    $params[] = $filterCompany;
}
if ($filterCategory) {
    $whereConditions[] = "r.category_id = ?";
    $params[] = $filterCategory;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$query = "SELECT r.*, u.first_name, u.last_name, u.email, c.company_name, l.location_name, 
          cat.category_name, assigned.first_name as assigned_first_name, assigned.last_name as assigned_last_name
          FROM requests r
          JOIN users u ON r.employee_id = u.id
          JOIN companies c ON u.company_id = c.id
          LEFT JOIN locations l ON u.location_id = l.id
          JOIN request_categories cat ON r.category_id = cat.id
          LEFT JOIN users assigned ON r.assigned_to = assigned.id
          $whereClause
          ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT id, company_name FROM companies WHERE status = 'approved' ORDER BY company_name";
$stmt = $db->prepare($query);
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT id, category_name FROM request_categories ORDER BY category_name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stats = [];
$query = "SELECT status, COUNT(*) as count FROM requests GROUP BY status";
$stmt = $db->prepare($query);
$stmt->execute();
$statusCounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($statusCounts as $stat) {
    $stats[$stat['status']] = $stat['count'];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Talep Yönetimi - Request Admin</title>
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
                    <h1>Talep Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Talepler</li>
                        </ol>
                    </nav>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="dashboard-card">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['pending'] ?? 0; ?></div>
                                    <div>Bekleyen</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['in_progress'] ?? 0; ?></div>
                                    <div>İşlemde</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-cog"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo $stats['completed'] ?? 0; ?></div>
                                    <div>Tamamlanan</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="dashboard-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <div class="card-number"><?php echo array_sum($stats); ?></div>
                                    <div>Toplam</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="status" class="form-select">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="pending" <?php echo $filterStatus === 'pending' ? 'selected' : ''; ?>>Bekleyen</option>
                                    <option value="assigned" <?php echo $filterStatus === 'assigned' ? 'selected' : ''; ?>>Atanmış</option>
                                    <option value="in_progress" <?php echo $filterStatus === 'in_progress' ? 'selected' : ''; ?>>İşlemde</option>
                                    <option value="completed" <?php echo $filterStatus === 'completed' ? 'selected' : ''; ?>>Tamamlanan</option>
                                    <option value="cancelled" <?php echo $filterStatus === 'cancelled' ? 'selected' : ''; ?>>İptal</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="company" class="form-select">
                                    <option value="">Tüm Firmalar</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" <?php echo $filterCompany == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select name="category" class="form-select">
                                    <option value="">Tüm Kategoriler</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" <?php echo $filterCategory == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['category_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="requests.php" class="btn btn-secondary">Temizle</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Talepler (<?php echo count($requests); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Talep No</th>
                                        <th>Başlık</th>
                                        <th>Çalışan</th>
                                        <th>Firma</th>
                                        <th>Kategori</th>
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
                                                <strong><?php echo htmlspecialchars($request['request_number']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['title']); ?></td>
                                            <td>
                                                <?php echo htmlspecialchars($request['first_name'] . ' ' . $request['last_name']); ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($request['company_name']); ?>
                                                <?php if ($request['location_name']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($request['location_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getStatusBadgeColor($request['status']); ?>">
                                                    <?php echo getStatusText($request['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($request['assigned_to']): ?>
                                                    <?php echo htmlspecialchars($request['assigned_first_name'] . ' ' . $request['assigned_last_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Atanmamış</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatDate($request['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewRequestModal"
                                                        onclick="viewRequest(<?php echo $request['id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- View Request Modal -->
    <div class="modal fade" id="viewRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Talep Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetails">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Yükleniyor...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewRequest(requestId) {
            document.getElementById('requestDetails').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                </div>
            `;
            
            fetch('ajax/get_request_details.php?id=' + requestId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('requestDetails').innerHTML = data.html;
                    } else {
                        document.getElementById('requestDetails').innerHTML = '<div class="alert alert-danger">Talep detayları yüklenemedi.</div>';
                    }
                })
                .catch(error => {
                    document.getElementById('requestDetails').innerHTML = '<div class="alert alert-danger">Bir hata oluştu.</div>';
                });
        }
    </script>
</body>
</html>
