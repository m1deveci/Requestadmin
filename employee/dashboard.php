<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];

$stats = [];

$query = "SELECT COUNT(*) as total FROM requests WHERE employee_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['total_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests WHERE employee_id = ? AND status = 'pending'";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['pending_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests WHERE employee_id = ? AND status IN ('assigned', 'in_progress')";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['in_progress_requests'] = $stmt->fetchColumn();

$query = "SELECT COUNT(*) as total FROM requests WHERE employee_id = ? AND status = 'completed'";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$stats['completed_requests'] = $stmt->fetchColumn();

$query = "SELECT r.*, c.category_name, 
                 CASE WHEN r.assigned_to IS NOT NULL THEN CONCAT(u.first_name, ' ', u.last_name) ELSE NULL END as assigned_to_name
          FROM requests r 
          JOIN request_categories c ON r.category_id = c.id 
          LEFT JOIN users u ON r.assigned_to = u.id
          WHERE r.employee_id = ? 
          ORDER BY r.created_at DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute([$userId]);
$recent_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM request_categories ORDER BY category_name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışan Dashboard - Request Admin</title>
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
                    <h1>Çalışan Dashboard</h1>
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
                                    <div>Beklemede</div>
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
                                    <div class="card-number"><?php echo $stats['in_progress_requests']; ?></div>
                                    <div>İşlemde</div>
                                </div>
                                <div class="card-icon">
                                    <i class="fas fa-cog"></i>
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

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Hızlı Talep Oluştur</h5>
                            </div>
                            <div class="card-body">
                                <form action="create_request.php" method="POST" enctype="multipart/form-data">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <select class="form-select" name="category_id" required>
                                                <option value="">Kategori Seçin</option>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?php echo $category['id']; ?>">
                                                        <?php echo htmlspecialchars($category['category_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <input type="text" class="form-control" name="title" placeholder="Talep Başlığı" required>
                                        </div>
                                        <div class="col-md-4">
                                            <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                                <i class="fas fa-plus me-2"></i>Detaylı Talep Oluştur
                                            </button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Requests -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Son Taleplerim</h5>
                                <a href="requests.php" class="btn btn-sm btn-outline-primary">Tümünü Gör</a>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_requests)): ?>
                                    <div class="text-center py-4">
                                        <i class="fas fa-tasks fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Henüz talep oluşturmamışsınız.</p>
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRequestModal">
                                            <i class="fas fa-plus me-2"></i>İlk Talebinizi Oluşturun
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Talep No</th>
                                                    <th>Başlık</th>
                                                    <th>Kategori</th>
                                                    <th>Durum</th>
                                                    <th>Atanan</th>
                                                    <th>Tarih</th>
                                                    <th>İşlem</th>
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
                                                        <td><?php echo htmlspecialchars($request['title']); ?></td>
                                                        <td><?php echo htmlspecialchars($request['category_name']); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo getStatusBadgeClass($request['status']); ?>">
                                                                <?php echo getStatusText($request['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo $request['assigned_to_name'] ? htmlspecialchars($request['assigned_to_name']) : '-'; ?></td>
                                                        <td><?php echo formatDate($request['created_at']); ?></td>
                                                        <td>
                                                            <div class="btn-group btn-group-sm">
                                                                <a href="request_detail.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-primary">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if (in_array($request['status'], ['pending', 'assigned'])): ?>
                                                                    <a href="edit_request.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-secondary">
                                                                        <i class="fas fa-edit"></i>
                                                                    </a>
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
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- New Request Modal -->
    <div class="modal fade" id="newRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Talep Oluştur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="create_request.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">Kategori *</label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">Kategori Seçin</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo $category['id']; ?>">
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
                                        <option value="medium">Orta</option>
                                        <option value="low">Düşük</option>
                                        <option value="high">Yüksek</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Talep Başlığı *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Detaylı Açıklama *</label>
                            <textarea class="form-control" id="description" name="description" rows="4" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="image" class="form-label">Görsel Ekle</label>
                            <input type="file" class="form-control" id="image" name="image" accept="image/*">
                            <div class="form-text">JPG, PNG veya GIF formatında, maksimum 5MB</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Talep Oluştur</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
