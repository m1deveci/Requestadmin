<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['hr']);

$database = new Database();
$db = $database->getConnection();

$locationId = $_SESSION['location_id'];
$companyId = $_SESSION['company_id'];

$message = '';
$messageType = '';

if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $managerId = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $message = 'Tüm zorunlu alanları doldurun.';
            $messageType = 'danger';
        } else {
            $checkQuery = "SELECT COUNT(*) FROM users WHERE email = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$email]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $message = 'Bu e-posta adresi zaten kullanılıyor.';
                $messageType = 'danger';
            } else {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $provinceId = !empty($_POST['province_id']) ? $_POST['province_id'] : null;
                
                $query = "INSERT INTO users (company_id, location_id, province_id, first_name, last_name, email, password, role, title, department, manager_id) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'employee', ?, ?, ?)";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$companyId, $locationId, $provinceId, $firstName, $lastName, $email, $hashedPassword, $title, $department, $managerId])) {
                    logAdminAction($db, $_SESSION['user_id'], 'employee_add', "Yeni çalışan eklendi: $firstName $lastName ($email)");
                    $message = 'Çalışan başarıyla eklendi.';
                    $messageType = 'success';
                } else {
                    $message = 'Çalışan eklenirken hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($action === 'edit') {
        $userId = $_POST['user_id'] ?? '';
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $managerId = !empty($_POST['manager_id']) ? $_POST['manager_id'] : null;
        $status = $_POST['status'] ?? 'active';
        
        if (empty($firstName) || empty($lastName) || empty($email)) {
            $message = 'Tüm zorunlu alanları doldurun.';
            $messageType = 'danger';
        } else {
            $checkQuery = "SELECT COUNT(*) FROM users WHERE email = ? AND id != ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$email, $userId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $message = 'Bu e-posta adresi zaten kullanılıyor.';
                $messageType = 'danger';
            } else {
                $provinceId = !empty($_POST['province_id']) ? $_POST['province_id'] : null;
                
                $query = "UPDATE users SET first_name = ?, last_name = ?, email = ?, title = ?, department = ?, manager_id = ?, status = ?, province_id = ? 
                         WHERE id = ? AND company_id = ? AND location_id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$firstName, $lastName, $email, $title, $department, $managerId, $status, $provinceId, $userId, $companyId, $locationId])) {
                    logAdminAction($db, $_SESSION['user_id'], 'employee_edit', "Çalışan güncellendi: $firstName $lastName ($email)");
                    $message = 'Çalışan bilgileri güncellendi.';
                    $messageType = 'success';
                } else {
                    $message = 'Güncelleme sırasında hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    } elseif ($action === 'delete') {
        $userId = $_POST['user_id'] ?? '';
        
        $userQuery = "SELECT first_name, last_name, email FROM users WHERE id = ? AND company_id = ? AND location_id = ?";
        $userStmt = $db->prepare($userQuery);
        $userStmt->execute([$userId, $companyId, $locationId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $requestQuery = "SELECT COUNT(*) FROM requests WHERE employee_id = ?";
            $requestStmt = $db->prepare($requestQuery);
            $requestStmt->execute([$userId]);
            $requestCount = $requestStmt->fetchColumn();
            
            if ($requestCount > 0) {
                $message = 'Bu çalışanın aktif talepleri bulunduğu için silinemez.';
                $messageType = 'danger';
            } else {
                $deleteQuery = "DELETE FROM users WHERE id = ? AND company_id = ? AND location_id = ?";
                $deleteStmt = $db->prepare($deleteQuery);
                
                if ($deleteStmt->execute([$userId, $companyId, $locationId])) {
                    logAdminAction($db, $_SESSION['user_id'], 'employee_delete', "Çalışan silindi: {$user['first_name']} {$user['last_name']} ({$user['email']})");
                    $message = 'Çalışan başarıyla silindi.';
                    $messageType = 'success';
                } else {
                    $message = 'Silme işlemi sırasında hata oluştu.';
                    $messageType = 'danger';
                }
            }
        }
    }
}

$search = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

$whereConditions = ["u.company_id = ? AND u.location_id = ? AND u.role = 'employee'"];
$params = [$companyId, $locationId];

if (!empty($search)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

if (!empty($statusFilter)) {
    $whereConditions[] = "u.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $whereConditions);

$countQuery = "SELECT COUNT(*) FROM users u WHERE $whereClause";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($params);
$totalRecords = $countStmt->fetchColumn();
$totalPages = ceil($totalRecords / $limit);

$query = "SELECT u.*, m.first_name as manager_first_name, m.last_name as manager_last_name 
          FROM users u 
          LEFT JOIN users m ON u.manager_id = m.id 
          WHERE $whereClause 
          ORDER BY u.first_name, u.last_name 
          LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

$managersQuery = "SELECT id, first_name, last_name FROM users WHERE company_id = ? AND location_id = ? AND role IN ('hr', 'admin') AND status = 'active' ORDER BY first_name, last_name";
$managersStmt = $db->prepare($managersQuery);
$managersStmt->execute([$companyId, $locationId]);
$managers = $managersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çalışanlar - Request Admin</title>
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
                            <a class="nav-link" href="requests.php">
                                <i class="fas fa-tasks"></i> Talepler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="employees.php">
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
                    <h1>Çalışan Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Çalışanlar</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters and Add Button -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <label class="form-label">Arama</label>
                                <input type="text" class="form-control" id="searchInput" placeholder="Ad, soyad veya e-posta..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Durum</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">Tümü</option>
                                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                            </div>
                            <div class="col-md-5 text-end">
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                    <i class="fas fa-plus"></i> Yeni Çalışan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Employees Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Çalışanlar (<?php echo $totalRecords; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($employees)): ?>
                            <p class="text-muted text-center py-4">Henüz çalışan bulunmuyor.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>E-posta</th>
                                            <th>Ünvan</th>
                                            <th>Departman</th>
                                            <th>Yönetici</th>
                                            <th>Durum</th>
                                            <th>Son Giriş</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($employees as $employee): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="avatar-sm me-2">
                                                            <?php if ($employee['photo']): ?>
                                                                <img src="../uploads/<?php echo htmlspecialchars($employee['photo']); ?>" class="rounded-circle" width="32" height="32">
                                                            <?php else: ?>
                                                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px;">
                                                                    <?php echo strtoupper(substr($employee['first_name'], 0, 1)); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div class="fw-bold"><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                                <td><?php echo htmlspecialchars($employee['title'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($employee['department'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($employee['manager_first_name']): ?>
                                                        <?php echo htmlspecialchars($employee['manager_first_name'] . ' ' . $employee['manager_last_name']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $employee['status'] === 'active' ? 'success' : 'secondary'; ?>">
                                                        <?php echo $employee['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php echo $employee['last_login'] ? formatDate($employee['last_login']) : '<span class="text-muted">Hiç</span>'; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button type="button" class="btn btn-outline-primary" onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger" onclick="deleteEmployee(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Pagination -->
                            <?php if ($totalPages > 1): ?>
                                <nav class="mt-4">
                                    <ul class="pagination justify-content-center">
                                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusFilter); ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Employee Modal -->
    <div class="modal fade" id="addEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Yeni Çalışan Ekle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" class="form-control" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Parola *</label>
                                    <input type="password" class="form-control" name="password" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ünvan</label>
                                    <input type="text" class="form-control" name="title">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Departman</label>
                                    <input type="text" class="form-control" name="department">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şehir *</label>
                            <select class="form-select" name="province_id" required>
                                <option value="">Şehir Seçin</option>
                                <?php
                                $provinceQuery = $db->prepare("SELECT id, province_name FROM provinces ORDER BY province_name");
                                $provinceQuery->execute();
                                while ($province = $provinceQuery->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$province['id']}'>{$province['province_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yönetici</label>
                            <select class="form-select" name="manager_id">
                                <option value="">Yönetici Seçin</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?php echo $manager['id']; ?>">
                                        <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div class="modal fade" id="editEmployeeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Çalışan Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" class="form-control" name="email" id="edit_email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Durum</label>
                                    <select class="form-select" name="status" id="edit_status">
                                        <option value="active">Aktif</option>
                                        <option value="inactive">Pasif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ünvan</label>
                                    <input type="text" class="form-control" name="title" id="edit_title">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Departman</label>
                                    <input type="text" class="form-control" name="department" id="edit_department">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Şehir *</label>
                            <select class="form-select" name="province_id" id="edit_province_id" required>
                                <option value="">Şehir Seçin</option>
                                <?php
                                $provinceQuery = $db->prepare("SELECT id, province_name FROM provinces ORDER BY province_name");
                                $provinceQuery->execute();
                                while ($province = $provinceQuery->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value='{$province['id']}'>{$province['province_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Yönetici</label>
                            <select class="form-select" name="manager_id" id="edit_manager_id">
                                <option value="">Yönetici Seçin</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?php echo $manager['id']; ?>">
                                        <?php echo htmlspecialchars($manager['first_name'] . ' ' . $manager['last_name']); ?>
                                    </option>
                                <?php endforeach; ?>
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

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Çalışan Sil</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="delete_user_id">
                        <p>Bu çalışanı silmek istediğinizden emin misiniz?</p>
                        <p class="text-danger"><strong id="delete_employee_name"></strong></p>
                        <p class="text-muted small">Bu işlem geri alınamaz.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">Sil</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function applyFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const url = new URL(window.location);
            url.searchParams.set('search', search);
            url.searchParams.set('status', status);
            url.searchParams.set('page', '1');
            window.location.href = url.toString();
        }

        function editEmployee(employee) {
            document.getElementById('edit_user_id').value = employee.id;
            document.getElementById('edit_first_name').value = employee.first_name;
            document.getElementById('edit_last_name').value = employee.last_name;
            document.getElementById('edit_email').value = employee.email;
            document.getElementById('edit_title').value = employee.title || '';
            document.getElementById('edit_department').value = employee.department || '';
            document.getElementById('edit_status').value = employee.status;
            document.getElementById('edit_manager_id').value = employee.manager_id || '';
            document.getElementById('edit_province_id').value = employee.province_id || '';
            
            new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
        }

        function deleteEmployee(userId, employeeName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_employee_name').textContent = employeeName;
            
            new bootstrap.Modal(document.getElementById('deleteEmployeeModal')).show();
        }

        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
    </script>
</body>
</html>
