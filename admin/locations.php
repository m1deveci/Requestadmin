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
            $locationName = trim($_POST['location_name']);
            $address = trim($_POST['address']);
            
            if (!empty($companyId) && !empty($locationName)) {
                $query = "INSERT INTO locations (company_id, location_name, address) VALUES (?, ?, ?)";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$companyId, $locationName, $address])) {
                    $message = 'Lokasyon başarıyla eklendi.';
                } else {
                    $error = 'Lokasyon eklenirken bir hata oluştu.';
                }
            } else {
                $error = 'Firma ve lokasyon adı gereklidir.';
            }
        } elseif ($_POST['action'] === 'delete') {
            $locationId = $_POST['location_id'];
            $query = "DELETE FROM locations WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$locationId])) {
                $message = 'Lokasyon başarıyla silindi.';
            } else {
                $error = 'Lokasyon silinirken bir hata oluştu.';
            }
        }
    }
}

$query = "SELECT l.*, c.company_name, 
          (SELECT COUNT(*) FROM users WHERE location_id = l.id) as user_count
          FROM locations l 
          JOIN companies c ON l.company_id = c.id 
          ORDER BY c.company_name, l.location_name";
$stmt = $db->prepare($query);
$stmt->execute();
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Lokasyon Yönetimi - Request Admin</title>
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
                            <a class="nav-link active" href="locations.php">
                                <i class="fas fa-map-marker-alt"></i> Lokasyonlar
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users.php">
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
                    <h1>Lokasyon Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Lokasyonlar</li>
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

                <div class="row mb-4">
                    <div class="col-12">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLocationModal">
                            <i class="fas fa-plus"></i> Yeni Lokasyon Ekle
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Lokasyonlar</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Firma</th>
                                        <th>Lokasyon Adı</th>
                                        <th>Adres</th>
                                        <th>Kullanıcı Sayısı</th>
                                        <th>Oluşturma Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($locations as $location): ?>
                                        <tr>
                                            <td><?php echo $location['id']; ?></td>
                                            <td><?php echo htmlspecialchars($location['company_name']); ?></td>
                                            <td><?php echo htmlspecialchars($location['location_name']); ?></td>
                                            <td><?php echo htmlspecialchars($location['address'] ?? '-'); ?></td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $location['user_count']; ?></span>
                                            </td>
                                            <td><?php echo formatDate($location['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deleteLocation(<?php echo $location['id']; ?>, '<?php echo htmlspecialchars($location['location_name']); ?>')"
                                                        <?php echo $location['user_count'] > 0 ? 'disabled title="Bu lokasyonda kullanıcı bulunduğu için silinemez"' : ''; ?>>
                                                    <i class="fas fa-trash"></i>
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

    <!-- Add Location Modal -->
    <div class="modal fade" id="addLocationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Lokasyon Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="company_id" class="form-label">Firma</label>
                            <select class="form-select" id="company_id" name="company_id" required>
                                <option value="">Firma Seçin</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['id']; ?>">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="location_name" class="form-label">Lokasyon Adı</label>
                            <input type="text" class="form-control" id="location_name" name="location_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres</label>
                            <textarea class="form-control" id="address" name="address" rows="3"></textarea>
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

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="location_id" id="deleteLocationId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteLocation(id, name) {
            if (confirm('Bu lokasyonu silmek istediğinizden emin misiniz?\n\nLokasyon: ' + name)) {
                document.getElementById('deleteLocationId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
