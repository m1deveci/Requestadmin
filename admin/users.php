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
            $provinceId = $_POST['province_id'] ?: null;
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $title = trim($_POST['title']);
            $department = trim($_POST['department']);
            $managerId = $_POST['manager_id'] ?: null;
            
            if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($role)) {
                $error = 'Tüm zorunlu alanları doldurun.';
            } else {
                $checkQuery = "SELECT id FROM users WHERE email = ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$email]);
                
                if ($checkStmt->rowCount() > 0) {
                    $error = 'Bu e-posta adresi zaten kullanılıyor.';
                } else {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (company_id, location_id, province_id, first_name, last_name, email, password, role, title, department, manager_id) 
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$companyId, $locationId, $provinceId, $firstName, $lastName, $email, $hashedPassword, $role, $title, $department, $managerId])) {
                        logAdminAction($db, $_SESSION['user_id'], 'CREATE_USER', "Yeni kullanıcı eklendi: $firstName $lastName ($email)");
                        $message = 'Kullanıcı başarıyla eklendi.';
                    } else {
                        $error = 'Kullanıcı eklenirken bir hata oluştu.';
                    }
                }
            }
        } elseif ($_POST['action'] === 'edit') {
            $userId = $_POST['user_id'];
            $companyId = $_POST['company_id'];
            $locationId = $_POST['location_id'] ?: null;
            $provinceId = $_POST['province_id'] ?: null;
            $firstName = trim($_POST['first_name']);
            $lastName = trim($_POST['last_name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $title = trim($_POST['title']);
            $department = trim($_POST['department']);
            $managerId = $_POST['manager_id'] ?: null;
            
            if (empty($firstName) || empty($lastName) || empty($email) || empty($role)) {
                $error = 'Tüm zorunlu alanları doldurun.';
            } else {
                $checkQuery = "SELECT id FROM users WHERE email = ? AND id != ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$email, $userId]);
                
                if ($checkStmt->rowCount() > 0) {
                    $error = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
                } else {
                    $userStmt = $db->prepare("SELECT first_name, last_name, email, role FROM users WHERE id = ?");
                    $userStmt->execute([$userId]);
                    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $query = "UPDATE users SET company_id = ?, location_id = ?, province_id = ?, first_name = ?, last_name = ?, email = ?, role = ?, title = ?, department = ?, manager_id = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$companyId, $locationId, $provinceId, $firstName, $lastName, $email, $role, $title, $department, $managerId, $userId])) {
                        if ($userInfo) {
                            logAdminAction($db, $_SESSION['user_id'], 'UPDATE_USER', "Kullanıcı güncellendi: {$userInfo['first_name']} {$userInfo['last_name']} -> $firstName $lastName");
                        }
                        $message = 'Kullanıcı başarıyla güncellendi.';
                    } else {
                        $error = 'Kullanıcı güncellenirken bir hata oluştu.';
                    }
                }
            }
        } elseif ($_POST['action'] === 'delete') {
            $userId = $_POST['user_id'];
            
            $userStmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            $requestsStmt = $db->prepare("SELECT COUNT(*) as count FROM requests WHERE employee_id = ?");
            $requestsStmt->execute([$userId]);
            $requestsCount = $requestsStmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($requestsCount > 0) {
                $error = 'Bu kullanıcının aktif talepleri bulunduğu için silinemez.';
            } else {
                $query = "DELETE FROM users WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$userId])) {
                    if ($userInfo) {
                        logAdminAction($db, $_SESSION['user_id'], 'DELETE_USER', "Kullanıcı silindi: {$userInfo['first_name']} {$userInfo['last_name']} ({$userInfo['email']})");
                    }
                    $message = 'Kullanıcı başarıyla silindi.';
                } else {
                    $error = 'Kullanıcı silinirken bir hata oluştu.';
                }
            }
        } elseif ($_POST['action'] === 'reset_password') {
            $userId = $_POST['user_id'];
            $newPassword = $_POST['new_password'];
            
            if (empty($newPassword)) {
                $error = 'Yeni parola boş olamaz.';
            } else {
                $userStmt = $db->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);
                
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $query = "UPDATE users SET password = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$hashedPassword, $userId])) {
                    if ($userInfo) {
                        logAdminAction($db, $_SESSION['user_id'], 'RESET_PASSWORD', "Parola sıfırlandı: {$userInfo['first_name']} {$userInfo['last_name']} ({$userInfo['email']})");
                    }
                    $message = 'Parola başarıyla sıfırlandı.';
                } else {
                    $error = 'Parola sıfırlanırken bir hata oluştu.';
                }
            }
        }
    }
}

$searchTerm = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
$companyFilter = $_GET['company'] ?? '';
$provinceFilter = $_GET['province'] ?? '';

$whereConditions = [];
$params = [];

if (!empty($searchTerm)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if (!empty($roleFilter)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $roleFilter;
}

if (!empty($companyFilter)) {
    $whereConditions[] = "u.company_id = ?";
    $params[] = $companyFilter;
}

if (!empty($provinceFilter)) {
    $whereConditions[] = "u.province_id = ?";
    $params[] = $provinceFilter;
}

$whereClause = '';
if (!empty($whereConditions)) {
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
}

$query = "SELECT u.*, c.company_name, l.location_name, p.province_name,
                 CASE WHEN u.manager_id IS NOT NULL THEN CONCAT(m.first_name, ' ', m.last_name) ELSE NULL END as manager_name
          FROM users u 
          LEFT JOIN companies c ON u.company_id = c.id 
          LEFT JOIN locations l ON u.location_id = l.id 
          LEFT JOIN provinces p ON u.province_id = p.id
          LEFT JOIN users m ON u.manager_id = m.id 
          $whereClause
          ORDER BY u.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$companiesQuery = "SELECT id, company_name FROM companies WHERE status = 'approved' ORDER BY company_name";
$companiesStmt = $db->prepare($companiesQuery);
$companiesStmt->execute();
$companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

$provincesQuery = "SELECT id, province_name FROM provinces ORDER BY province_name";
$provincesStmt = $db->prepare($provincesQuery);
$provincesStmt->execute();
$provinces = $provincesStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block bg-dark sidebar">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">Admin Panel</h5>
                        <small class="text-muted"><?php echo $_SESSION['user_name']; ?></small>
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
                                <i class="fas fa-clipboard-list"></i> Talepler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="categories.php">
                                <i class="fas fa-tags"></i> Kategoriler
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logs.php">
                                <i class="fas fa-history"></i> Loglar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog"></i> Ayarlar
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Kullanıcı Yönetimi</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus"></i> Yeni Kullanıcı Ekle
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Filtrele ve Ara</h5>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <input type="text" class="form-control" name="search" placeholder="Ad, soyad veya e-posta..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="role">
                                    <option value="">Tüm Roller</option>
                                    <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="hr" <?php echo $roleFilter === 'hr' ? 'selected' : ''; ?>>İdari İşler</option>
                                    <option value="employee" <?php echo $roleFilter === 'employee' ? 'selected' : ''; ?>>Çalışan</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="company">
                                    <option value="">Tüm Firmalar</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" <?php echo $companyFilter == $company['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['company_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select class="form-select" name="province">
                                    <option value="">Tüm İller</option>
                                    <?php foreach ($provinces as $province): ?>
                                        <option value="<?php echo $province['id']; ?>" <?php echo $provinceFilter == $province['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($province['province_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary">Filtrele</button>
                                <a href="users.php" class="btn btn-secondary">Temizle</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Kullanıcılar (<?php echo count($users); ?>)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($users)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Kullanıcı bulunamadı.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Ad Soyad</th>
                                            <th>E-posta</th>
                                            <th>Rol</th>
                                            <th>Firma</th>
                                            <th>İl</th>
                                            <th>Lokasyon</th>
                                            <th>Ünvan</th>
                                            <th>Departman</th>
                                            <th>Yönetici</th>
                                            <th>Durum</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td>
                                                    <?php
                                                    $roleLabels = [
                                                        'admin' => '<span class="badge bg-danger">Admin</span>',
                                                        'hr' => '<span class="badge bg-warning">İdari İşler</span>',
                                                        'employee' => '<span class="badge bg-info">Çalışan</span>'
                                                    ];
                                                    echo $roleLabels[$user['role']] ?? $user['role'];
                                                    ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['company_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['province_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['location_name'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['title'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['department'] ?? '-'); ?></td>
                                                <td><?php echo htmlspecialchars($user['manager_name'] ?? '-'); ?></td>
                                                <td>
                                                    <?php if ($user['is_active']): ?>
                                                        <span class="badge bg-success">Aktif</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Pasif</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                                    <label class="form-label">Rol *</label>
                                    <select class="form-select" name="role" required>
                                        <option value="">Rol Seçin</option>
                                        <option value="admin">Admin</option>
                                        <option value="hr">İdari İşler</option>
                                        <option value="employee">Çalışan</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Firma *</label>
                                    <select class="form-select" name="company_id" id="addCompanySelect" required onchange="loadLocations(this.value, 'addLocationSelect')">
                                        <option value="">Firma Seçin</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">İl</label>
                                    <select class="form-select" name="province_id" id="addProvinceSelect" onchange="loadLocationsByProvince(this.value, 'addLocationSelect')">
                                        <option value="">İl Seçin</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo $province['id']; ?>"><?php echo htmlspecialchars($province['province_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasyon</label>
                                    <select class="form-select" name="location_id" id="addLocationSelect">
                                        <option value="">İl seçin veya firma seçin</option>
                                    </select>
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
                            <label class="form-label">Yönetici</label>
                            <select class="form-select" name="manager_id" id="addManagerSelect">
                                <option value="">Yönetici Seçin</option>
                            </select>
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

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ad *</label>
                                    <input type="text" class="form-control" name="first_name" id="editFirstName" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" name="last_name" id="editLastName" required>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">E-posta *</label>
                                    <input type="email" class="form-control" name="email" id="editEmail" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Rol *</label>
                                    <select class="form-select" name="role" id="editRole" required>
                                        <option value="">Rol Seçin</option>
                                        <option value="admin">Admin</option>
                                        <option value="hr">İdari İşler</option>
                                        <option value="employee">Çalışan</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Firma *</label>
                                    <select class="form-select" name="company_id" id="editCompanySelect" required onchange="loadLocations(this.value, 'editLocationSelect')">
                                        <option value="">Firma Seçin</option>
                                        <?php foreach ($companies as $company): ?>
                                            <option value="<?php echo $company['id']; ?>"><?php echo htmlspecialchars($company['company_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">İl</label>
                                    <select class="form-select" name="province_id" id="editProvinceSelect" onchange="loadLocationsByProvince(this.value, 'editLocationSelect')">
                                        <option value="">İl Seçin</option>
                                        <?php foreach ($provinces as $province): ?>
                                            <option value="<?php echo $province['id']; ?>"><?php echo htmlspecialchars($province['province_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Lokasyon</label>
                                    <select class="form-select" name="location_id" id="editLocationSelect">
                                        <option value="">İl seçin veya firma seçin</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Ünvan</label>
                                    <input type="text" class="form-control" name="title" id="editTitle">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Departman</label>
                                    <input type="text" class="form-control" name="department" id="editDepartment">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Yönetici</label>
                                    <select class="form-select" name="manager_id" id="editManagerSelect">
                                        <option value="">Yönetici Seçin</option>
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

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetPasswordModal" tabindex="-1">
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
                        <p>Kullanıcı: <strong id="resetUserName"></strong></p>
                        <div class="mb-3">
                            <label class="form-label">Yeni Parola *</label>
                            <input type="password" class="form-control" name="new_password" required>
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

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kullanıcı Sil</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <p>Bu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                        <p><strong id="deleteUserName"></strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Bu işlem geri alınamaz!
                        </div>
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
        function loadLocations(companyId, targetSelectId) {
            const locationSelect = document.getElementById(targetSelectId);
            locationSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            if (!companyId) {
                locationSelect.innerHTML = '<option value="">Önce firma seçin</option>';
                return;
            }
            
            fetch('ajax/get_locations.php?company_id=' + companyId)
                .then(response => response.json())
                .then(data => {
                    locationSelect.innerHTML = '<option value="">Lokasyon Seçin</option>';
                    data.forEach(location => {
                        locationSelect.innerHTML += `<option value="${location.id}">${location.location_name}</option>`;
                    });
                })
                .catch(error => {
                    console.error('Error:', error);
                    locationSelect.innerHTML = '<option value="">Hata oluştu</option>';
                });
        }

        function loadLocationsByProvince(provinceId, targetSelectId) {
            const locationSelect = document.getElementById(targetSelectId);
            locationSelect.innerHTML = '<option value="">Yükleniyor...</option>';
            
            if (!provinceId) {
                locationSelect.innerHTML = '<option value="">Önce il seçin</option>';
                return;
            }
            
            fetch('ajax/get_locations_by_province.php?province_id=' + provinceId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        locationSelect.innerHTML = '<option value="">Lokasyon Seçin</option>';
                        data.locations.forEach(location => {
                            locationSelect.innerHTML += `<option value="${location.id}">${location.location_name}</option>`;
                        });
                    } else {
                        locationSelect.innerHTML = '<option value="">Hata oluştu</option>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    locationSelect.innerHTML = '<option value="">Hata oluştu</option>';
                });
        }

        function editUser(user) {
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editFirstName').value = user.first_name;
            document.getElementById('editLastName').value = user.last_name;
            document.getElementById('editEmail').value = user.email;
            document.getElementById('editRole').value = user.role;
            document.getElementById('editCompanySelect').value = user.company_id;
            document.getElementById('editProvinceSelect').value = user.province_id || '';
            document.getElementById('editTitle').value = user.title || '';
            document.getElementById('editDepartment').value = user.department || '';
            
            if (user.company_id) {
                loadLocations(user.company_id, 'editLocationSelect');
                setTimeout(() => {
                    document.getElementById('editLocationSelect').value = user.location_id || '';
                }, 500);
            }
            
            if (user.province_id) {
                setTimeout(() => {
                    loadLocationsByProvince(user.province_id, 'editLocationSelect');
                    setTimeout(() => {
                        document.getElementById('editLocationSelect').value = user.location_id || '';
                    }, 500);
                }, 100);
            }
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        function resetPassword(userId, userName) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('resetPasswordModal')).show();
        }

        function deleteUser(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
    </script>
</body>
</html>
