<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add') {
            $companyId = $_POST['company_id'];
            $locationId = $_POST['location_id'] ?: null;
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $title = trim($_POST['title']) ?: null;
            $department = trim($_POST['department']) ?: null;
            $managerId = $_POST['manager_id'] ?: null;
            
            if (!empty($companyId) && !empty($firstName) && !empty($lastName) && !empty($email) && !empty($password) && !empty($role)) {
                $checkQuery = "SELECT id FROM users WHERE email = ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$email]);
                
                if ($checkStmt->fetch()) {
                    $error = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (company_id, location_id, first_name, last_name, email, password, role, title, department, manager_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$companyId, $locationId, $firstName, $lastName, $email, $hashedPassword, $role, $title, $department, $managerId])) {
                        $message = 'Kullanıcı başarıyla eklendi.';
                    } else {
                        $error = 'Kullanıcı eklenirken bir hata oluştu.';
                    }
                }
            } else {
                $error = 'Gerekli alanları doldurun.';
            }
        } elseif ($_POST['action'] === 'toggle_status') {
            $userId = $_POST['user_id'];
            $newStatus = $_POST['status'] === 'active' ? 'inactive' : 'active';
            
            $query = "UPDATE users SET status = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$newStatus, $userId])) {
                $message = 'Kullanıcı durumu güncellendi.';
            } else {
                $error = 'Durum güncellenirken bir hata oluştu.';
            }
        } elseif ($_POST['action'] === 'edit') {
            $userId = $_POST['user_id'];
            $companyId = $_POST['company_id'];
            $locationId = $_POST['location_id'] ?: null;
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $title = trim($_POST['title']) ?: null;
            $department = trim($_POST['department']) ?: null;
            $managerId = $_POST['manager_id'] ?: null;
            
            if (!empty($companyId) && !empty($firstName) && !empty($lastName) && !empty($email) && !empty($role)) {
                $checkQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$email, $userId]);
                
                if ($checkStmt->fetch()) {
                    $error = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
                } else {
                    $userQuery = "SELECT u.first_name, u.last_name, c.company_name FROM users u JOIN companies c ON u.company_id = c.id WHERE u.id = ?";
                    $userStmt = $db->prepare($userQuery);
                    $userStmt->execute([$userId]);
                    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $query = "UPDATE users SET company_id = ?, location_id = ?, first_name = ?, last_name = ?, email = ?, role = ?, title = ?, department = ?, manager_id = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$companyId, $locationId, $firstName, $lastName, $email, $role, $title, $department, $managerId, $userId])) {
                        if ($userInfo) {
                            logAdminAction($db, $_SESSION['user_id'], 'user_updated', "Kullanıcı güncellendi: {$firstName} {$lastName} ({$email}) (ID: $userId)");
                        }
                        $message = 'Kullanıcı başarıyla güncellendi.';
                    } else {
                        $error = 'Kullanıcı güncellenirken bir hata oluştu.';
                    }
                }
            } else {
                $error = 'Tüm zorunlu alanları doldurun.';
            }
        } elseif ($_POST['action'] === 'delete') {
            $userId = $_POST['user_id'];
            
            $userQuery = "SELECT u.first_name, u.last_name, u.email, c.company_name FROM users u JOIN companies c ON u.company_id = c.id WHERE u.id = ?";
            $userStmt = $db->prepare($userQuery);
            $userStmt->execute([$userId]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            $requestQuery = "SELECT COUNT(*) as count FROM requests WHERE user_id = ? AND status NOT IN ('completed', 'cancelled')";
            $requestStmt = $db->prepare($requestQuery);
            $requestStmt->execute([$userId]);
            $requestCount = $requestStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($requestCount > 0) {
                $error = 'Bu kullanıcının aktif talepleri bulunduğu için silinemez.';
            } else {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$userId])) {
                    if ($userInfo) {
                        logAdminAction($db, $_SESSION['user_id'], 'user_deleted', "Kullanıcı silindi: {$userInfo['first_name']} {$userInfo['last_name']} ({$userInfo['email']}) (ID: $userId)");
                    }
                    $message = 'Kullanıcı başarıyla silindi.';
                } else {
                    $error = 'Kullanıcı silinirken bir hata oluştu.';
                }
            }
        } elseif ($_POST['action'] === 'reset_password') {
            $userId = $_POST['user_id'];
            $newPassword = $_POST['new_password'] ?? '';
            $generateRandom = isset($_POST['generate_random']);
            
            if ($generateRandom) {
                $newPassword = bin2hex(random_bytes(8));
            }
            
            if (!empty($newPassword)) {
                $userQuery = "SELECT u.first_name, u.last_name, u.email, c.company_name FROM users u JOIN companies c ON u.company_id = c.id WHERE u.id = ?";
                $userStmt = $db->prepare($userQuery);
                $userStmt->execute([$userId]);
                $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$hashedPassword, $userId])) {
                    if ($userInfo) {
                        logAdminAction($db, $_SESSION['user_id'], 'user_password_reset', "Kullanıcı parolası sıfırlandı: {$userInfo['first_name']} {$userInfo['last_name']} ({$userInfo['email']}) (ID: $userId)");
                    }
                    $message = $generateRandom ? "Kullanıcı parolası sıfırlandı. Yeni parola: $newPassword" : 'Kullanıcı parolası başarıyla güncellendi.';
                } else {
                    $error = 'Parola güncellenirken bir hata oluştu.';
                }
            } else {
                $error = 'Yeni parola gereklidir.';
            }
        }
    }
}

$filterRole = $_GET['role'] ?? '';
$filterCompany = $_GET['company'] ?? '';
$filterStatus = $_GET['status'] ?? '';

$whereConditions = [];
$params = [];

if ($filterRole) {
    $whereConditions[] = "u.role = ?";
    $params[] = $filterRole;
}
if ($filterCompany) {
    $whereConditions[] = "u.company_id = ?";
    $params[] = $filterCompany;
}
if ($filterStatus) {
    $whereConditions[] = "u.status = ?";
    $params[] = $filterStatus;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$query = "SELECT u.*, c.company_name, l.location_name, m.first_name as manager_first_name, m.last_name as manager_last_name,
          (SELECT COUNT(*) FROM requests r WHERE r.user_id = u.id AND r.status NOT IN ('completed', 'cancelled')) as request_count
          FROM users u 
          JOIN companies c ON u.company_id = c.id 
          LEFT JOIN locations l ON u.location_id = l.id
          LEFT JOIN users m ON u.manager_id = m.id
          $whereClause
          ORDER BY c.company_name, u.first_name, u.last_name";
$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT id, company_name FROM companies WHERE status = 'approved' ORDER BY company_name";
$stmt = $db->prepare($query);
$stmt->execute();
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Request Admin</title>
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
                        <h5 class="text-white">Admin Panel</h5>
                        <small class="text-light"><?php echo htmlspecialchars($_SESSION['user_name']); ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="companies.php">
                                <i class="fas fa-building"></i> Firmalar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i> Lokasyonlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="users.php">
                                <i class="fas fa-users"></i> Kullanıcılar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="requests.php">
                                <i class="fas fa-tasks"></i> Talepler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags"></i> Kategoriler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Ayarlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                <i class="fas fa-file-alt"></i> Loglar
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
                    <h1>Kullanıcı Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Kullanıcılar</li>
                        </ol>
                    </nav>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <select name="role" class="form-select">
                                    <option value="">Tüm Roller</option>
                                    <option value="admin" <?php echo $filterRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="hr" <?php echo $filterRole === 'hr' ? 'selected' : ''; ?>>İdari İşler</option>
                                    <option value="employee" <?php echo $filterRole === 'employee' ? 'selected' : ''; ?>>Çalışan</option>
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
                                <select name="status" class="form-select">
                                    <option value="">Tüm Durumlar</option>
                                    <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Aktif</option>
                                    <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Pasif</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="users.php" class="btn btn-secondary">Temizle</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                            <i class="fas fa-plus"></i> Yeni Kullanıcı Ekle
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Kullanıcılar (<?php echo count($users); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Ad Soyad</th>
                                        <th>E-posta</th>
                                        <th>Rol</th>
                                        <th>Firma</th>
                                        <th>Lokasyon</th>
                                        <th>Ünvan</th>
                                        <th>Durum</th>
                                        <th>Son Giriş</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo $user['id']; ?></td>
                                            <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo getRoleBadgeColor($user['role']); ?>">
                                                    <?php echo getRoleText($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($user['location_name'] ?? '-'); ?></td>
                                            <td><?php echo htmlspecialchars($user['title'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                                    <?php echo $user['status'] === 'active' ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo $user['last_login'] ? formatDate($user['last_login']) : 'Hiç'; ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-outline-primary" 
                                                            data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-warning" 
                                                            onclick="resetUserPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')"
                                                            <?php echo $user['request_count'] > 0 ? 'disabled title="Bu kullanıcının aktif talepleri bulunduğu için silinemez"' : ''; ?>>
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-<?php echo $user['status'] === 'active' ? 'warning' : 'success'; ?>" 
                                                            onclick="toggleUserStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')">
                                                        <i class="fas fa-<?php echo $user['status'] === 'active' ? 'pause' : 'play'; ?>"></i>
                                                    </button>
                                                </div>
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

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kullanıcı Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="company_id" class="form-label">Firma</label>
                                    <select class="form-select" id="company_id" name="company_id" required onchange="loadLocations(this.value)">
                                        <option value="">Firma Seçin</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>">
                                                <?php echo htmlspecialchars($company['company_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location_id" class="form-label">Lokasyon</label>
                                    <select class="form-select" id="location_id" name="location_id">
                                        <option value="">Önce firma seçin</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="first_name" class="form-label">Ad</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="last_name" class="form-label">Soyad</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Parola</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Rol</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="">Rol Seçin</option>
                                        <option value="admin">Admin</option>
                                        <option value="hr">İdari İşler</option>
                                        <option value="employee">Çalışan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Ünvan</label>
                                    <input type="text" class="form-control" id="title" name="title">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="department" class="form-label">Departman</label>
                                    <input type="text" class="form-control" id="department" name="department">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="manager_id" class="form-label">Yönetici</label>
                                    <select class="form-select" id="manager_id" name="manager_id">
                                        <option value="">Yönetici Seçin (İsteğe Bağlı)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Ekle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modals -->
    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Kullanıcı Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_company_id<?php echo $user['id']; ?>" class="form-label">Firma</label>
                                        <select class="form-select" id="edit_company_id<?php echo $user['id']; ?>" name="company_id" required onchange="loadEditLocations(this.value, <?php echo $user['id']; ?>)">
                                            <option value="">Firma Seçin</option>
                                            <?php foreach ($companies as $company): ?>
                                                <option value="<?php echo $company['id']; ?>" <?php echo $company['id'] == $user['company_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_location_id<?php echo $user['id']; ?>" class="form-label">Lokasyon</label>
                                        <select class="form-select" id="edit_location_id<?php echo $user['id']; ?>" name="location_id">
                                            <option value="">Lokasyon Seçin (İsteğe Bağlı)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_first_name<?php echo $user['id']; ?>" class="form-label">Ad</label>
                                        <input type="text" class="form-control" id="edit_first_name<?php echo $user['id']; ?>" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_last_name<?php echo $user['id']; ?>" class="form-label">Soyad</label>
                                        <input type="text" class="form-control" id="edit_last_name<?php echo $user['id']; ?>" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_email<?php echo $user['id']; ?>" class="form-label">E-posta</label>
                                        <input type="email" class="form-control" id="edit_email<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_role<?php echo $user['id']; ?>" class="form-label">Rol</label>
                                        <select class="form-select" id="edit_role<?php echo $user['id']; ?>" name="role" required>
                                            <option value="">Rol Seçin</option>
                                            <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                            <option value="hr" <?php echo $user['role'] === 'hr' ? 'selected' : ''; ?>>İdari İşler</option>
                                            <option value="employee" <?php echo $user['role'] === 'employee' ? 'selected' : ''; ?>>Çalışan</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_title<?php echo $user['id']; ?>" class="form-label">Ünvan</label>
                                        <input type="text" class="form-control" id="edit_title<?php echo $user['id']; ?>" name="title" value="<?php echo htmlspecialchars($user['title'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_department<?php echo $user['id']; ?>" class="form-label">Departman</label>
                                        <input type="text" class="form-control" id="edit_department<?php echo $user['id']; ?>" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="edit_manager_id<?php echo $user['id']; ?>" class="form-label">Yönetici</label>
                                        <select class="form-select" id="edit_manager_id<?php echo $user['id']; ?>" name="manager_id">
                                            <option value="">Yönetici Seçin (İsteğe Bağlı)</option>
                                        </select>
                                    </div>
                                </div>
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
    <?php endforeach; ?>

    <!-- Password Reset Modal -->
    <div class="modal fade" id="passwordResetModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Parola Sıfırla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="user_id" id="resetUserId">
                        
                        <div class="mb-3">
                            <p>Kullanıcı: <strong id="resetUserName"></strong></p>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="generateRandom" name="generate_random" onchange="togglePasswordInput()">
                                <label class="form-check-label" for="generateRandom">
                                    Rastgele parola oluştur
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="passwordInputDiv">
                            <label for="newPassword" class="form-label">Yeni Parola</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-warning">Parolayı Sıfırla</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Form -->
    <form id="deleteUserForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">
    </form>

    <!-- Status Toggle Form -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="statusUserId">
        <input type="hidden" name="status" id="statusValue">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleUserStatus(userId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
            const action = newStatus === 'active' ? 'aktifleştirmek' : 'pasifleştirmek';
            
            if (confirm('Bu kullanıcıyı ' + action + ' istediğinizden emin misiniz?')) {
                document.getElementById('statusUserId').value = userId;
                document.getElementById('statusValue').value = currentStatus;
                document.getElementById('statusForm').submit();
            }
        }
        
        function loadLocations(companyId) {
            const locationSelect = document.getElementById('location_id');
            const managerSelect = document.getElementById('manager_id');
            
            locationSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            managerSelect.innerHTML = '<option value="">Yönetici Seçin (İsteğe Bağlı)</option>';
            
            if (!companyId) {
                locationSelect.innerHTML = '<option value="">Önce firma seçin</option>';
                return;
            }
            
            fetch('ajax/get_locations.php?company_id=' + companyId)
                .then(response => response.json())
                .then(data => {
                    locationSelect.innerHTML = '<option value="">Lokasyon Seçin (İsteğe Bağlı)</option>';
                    data.locations.forEach(location => {
                        locationSelect.innerHTML += `<option value="${location.id}">${location.location_name}</option>`;
                    });
                    
                    data.managers.forEach(manager => {
                        managerSelect.innerHTML += `<option value="${manager.id}">${manager.first_name} ${manager.last_name}</option>`;
                    });
                })
                .catch(error => {
                    locationSelect.innerHTML = '<option value="">Hata oluştu</option>';
                });
        }
        
        function loadEditLocations(companyId, userId) {
            const locationSelect = document.getElementById('edit_location_id' + userId);
            const managerSelect = document.getElementById('edit_manager_id' + userId);
            
            locationSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            managerSelect.innerHTML = '<option value="">Yönetici Seçin (İsteğe Bağlı)</option>';
            
            if (!companyId) {
                locationSelect.innerHTML = '<option value="">Önce firma seçin</option>';
                return;
            }
            
            fetch('ajax/get_locations.php?company_id=' + companyId)
                .then(response => response.json())
                .then(data => {
                    locationSelect.innerHTML = '<option value="">Lokasyon Seçin (İsteğe Bağlı)</option>';
                    data.locations.forEach(location => {
                        locationSelect.innerHTML += `<option value="${location.id}">${location.location_name}</option>`;
                    });
                    
                    data.managers.forEach(manager => {
                        managerSelect.innerHTML += `<option value="${manager.id}">${manager.first_name} ${manager.last_name}</option>`;
                    });
                })
                .catch(error => {
                    locationSelect.innerHTML = '<option value="">Hata oluştu</option>';
                });
        }
        
        function deleteUser(id, name) {
            if (confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?\n\nKullanıcı: ' + name + '\n\nDikkat: Bu işlem geri alınamaz!')) {
                document.getElementById('deleteUserId').value = id;
                document.getElementById('deleteUserForm').submit();
            }
        }
        
        function resetUserPassword(id, name) {
            document.getElementById('resetUserId').value = id;
            document.getElementById('resetUserName').textContent = name;
            document.getElementById('generateRandom').checked = false;
            document.getElementById('newPassword').value = '';
            document.getElementById('passwordInputDiv').style.display = 'block';
            document.getElementById('newPassword').required = true;
            
            const modal = new bootstrap.Modal(document.getElementById('passwordResetModal'));
            modal.show();
        }
        
        function togglePasswordInput() {
            const checkbox = document.getElementById('generateRandom');
            const passwordDiv = document.getElementById('passwordInputDiv');
            const passwordInput = document.getElementById('newPassword');
            
            if (checkbox.checked) {
                passwordDiv.style.display = 'none';
                passwordInput.required = false;
                passwordInput.value = '';
            } else {
                passwordDiv.style.display = 'block';
                passwordInput.required = true;
            }
        }
        
        <?php foreach ($users as $user): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const editModal<?php echo $user['id']; ?> = document.getElementById('editUserModal<?php echo $user['id']; ?>');
            editModal<?php echo $user['id']; ?>.addEventListener('shown.bs.modal', function() {
                loadEditLocations(<?php echo $user['company_id']; ?>, <?php echo $user['id']; ?>);
            });
        });
        <?php endforeach; ?>
    </script>
</body>
</html>
