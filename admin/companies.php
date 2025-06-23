<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $companyId = $_POST['company_id'];
    $action = $_POST['action'];
    
    $query = "SELECT company_name, status FROM companies WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($action === 'approve') {
        $query = "UPDATE companies SET status = 'approved' WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$companyId])) {
            logAdminAction($db, $_SESSION['user_id'], 'company_approved', "Firma onaylandı: {$company['company_name']} (ID: $companyId)");
            $_SESSION['success'] = 'Firma başarıyla onaylandı.';
        }
    } elseif ($action === 'reject') {
        $query = "UPDATE companies SET status = 'rejected' WHERE id = ?";
        $stmt = $db->prepare($query);
        if ($stmt->execute([$companyId])) {
            logAdminAction($db, $_SESSION['user_id'], 'company_rejected', "Firma reddedildi: {$company['company_name']} (ID: $companyId)");
            $_SESSION['success'] = 'Firma reddedildi.';
        }
    } elseif ($action === 'edit') {
        $companyName = trim($_POST['company_name']);
        $authorizedPerson = trim($_POST['authorized_person']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $taxNumber = trim($_POST['tax_number']);
        $address = trim($_POST['address']);
        
        if (!empty($companyName) && !empty($authorizedPerson) && !empty($email)) {
            $query = "UPDATE companies SET company_name = ?, authorized_person = ?, email = ?, phone = ?, tax_number = ?, address = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$companyName, $authorizedPerson, $email, $phone, $taxNumber, $address, $companyId])) {
                logAdminAction($db, $_SESSION['user_id'], 'company_updated', "Firma güncellendi: {$companyName} (ID: $companyId)");
                $_SESSION['success'] = 'Firma bilgileri başarıyla güncellendi.';
            } else {
                $_SESSION['error'] = 'Firma güncellenirken bir hata oluştu.';
            }
        } else {
            $_SESSION['error'] = 'Firma adı, yetkili ve e-posta alanları gereklidir.';
        }
    } elseif ($action === 'delete') {
        $query = "SELECT COUNT(*) FROM users WHERE company_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$companyId]);
        $userCount = $stmt->fetchColumn();
        
        $query = "SELECT COUNT(*) FROM locations WHERE company_id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$companyId]);
        $locationCount = $stmt->fetchColumn();
        
        if ($userCount > 0 || $locationCount > 0) {
            $_SESSION['error'] = 'Bu firmaya ait kullanıcı veya lokasyon bulunduğu için silinemez.';
        } else {
            $query = "DELETE FROM companies WHERE id = ?";
            $stmt = $db->prepare($query);
            if ($stmt->execute([$companyId])) {
                logAdminAction($db, $_SESSION['user_id'], 'company_deleted', "Firma silindi: {$company['company_name']} (ID: $companyId)");
                $_SESSION['success'] = 'Firma başarıyla silindi.';
            } else {
                $_SESSION['error'] = 'Firma silinirken bir hata oluştu.';
            }
        }
    }
    
    header('Location: companies.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';

$whereClause = '';
$params = [];

if ($statusFilter !== 'all') {
    $whereClause = 'WHERE status = ?';
    $params[] = $statusFilter;
}

$query = "SELECT * FROM companies $whereClause ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute($params);
$companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmalar - Admin Panel</title>
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
                    <h1>Firma Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Firmalar</li>
                        </ol>
                    </nav>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?php echo htmlspecialchars($_SESSION['error']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <div class="btn-group" role="group">
                                    <a href="companies.php?status=all" class="btn <?php echo $statusFilter === 'all' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                                        Tümü
                                    </a>
                                    <a href="companies.php?status=pending" class="btn <?php echo $statusFilter === 'pending' ? 'btn-warning' : 'btn-outline-warning'; ?>">
                                        Bekleyen
                                    </a>
                                    <a href="companies.php?status=approved" class="btn <?php echo $statusFilter === 'approved' ? 'btn-success' : 'btn-outline-success'; ?>">
                                        Onaylanan
                                    </a>
                                    <a href="companies.php?status=rejected" class="btn <?php echo $statusFilter === 'rejected' ? 'btn-danger' : 'btn-outline-danger'; ?>">
                                        Reddedilen
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Companies Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Firmalar</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($companies)): ?>
                            <p class="text-muted text-center py-4">Firma bulunamadı.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Logo</th>
                                            <th>Firma Adı</th>
                                            <th>Yetkili</th>
                                            <th>E-posta</th>
                                            <th>Telefon</th>
                                            <th>Durum</th>
                                            <th>Kayıt Tarihi</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($companies as $company): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($company['logo']): ?>
                                                        <img src="../uploads/logos/<?php echo htmlspecialchars($company['logo']); ?>" 
                                                             alt="Logo" class="rounded" style="width: 40px; height: 40px; object-fit: cover;">
                                                    <?php else: ?>
                                                        <div class="bg-secondary rounded d-flex align-items-center justify-content-center" 
                                                             style="width: 40px; height: 40px;">
                                                            <i class="fas fa-building text-white"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">VN: <?php echo htmlspecialchars($company['tax_number']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($company['authorized_person']); ?></td>
                                                <td><?php echo htmlspecialchars($company['email']); ?></td>
                                                <td><?php echo htmlspecialchars($company['phone']); ?></td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger'
                                                    ];
                                                    $statusText = [
                                                        'pending' => 'Bekliyor',
                                                        'approved' => 'Onaylandı',
                                                        'rejected' => 'Reddedildi'
                                                    ];
                                                    ?>
                                                    <span class="badge <?php echo $statusClass[$company['status']]; ?>">
                                                        <?php echo $statusText[$company['status']]; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($company['created_at']); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-info" data-bs-toggle="modal" 
                                                                data-bs-target="#companyModal<?php echo $company['id']; ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="btn btn-outline-warning" data-bs-toggle="modal" 
                                                                data-bs-target="#editCompanyModal<?php echo $company['id']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <?php if ($company['status'] === 'pending'): ?>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                                <button type="submit" name="action" value="approve" 
                                                                        class="btn btn-outline-success" 
                                                                        onclick="return confirm('Bu firmayı onaylamak istediğinizden emin misiniz?')">
                                                                    <i class="fas fa-check"></i>
                                                                </button>
                                                            </form>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                                                                <button type="submit" name="action" value="reject" 
                                                                        class="btn btn-outline-danger"
                                                                        onclick="return confirm('Bu firmayı reddetmek istediğinizden emin misiniz?')">
                                                                    <i class="fas fa-times"></i>
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <button class="btn btn-outline-danger" 
                                                                onclick="deleteCompany(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
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
            </main>
        </div>
    </div>

    <!-- Company Detail Modals -->
    <?php foreach ($companies as $company): ?>
        <div class="modal fade" id="companyModal<?php echo $company['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Firma Detayları</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Firma Bilgileri</h6>
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Firma Adı:</strong></td>
                                        <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Yetkili:</strong></td>
                                        <td><?php echo htmlspecialchars($company['authorized_person']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Vergi No:</strong></td>
                                        <td><?php echo htmlspecialchars($company['tax_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Telefon:</strong></td>
                                        <td><?php echo htmlspecialchars($company['phone']); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>E-posta:</strong></td>
                                        <td><?php echo htmlspecialchars($company['email']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6>Adres</h6>
                                <p><?php echo nl2br(htmlspecialchars($company['address'])); ?></p>
                                
                                <?php if ($company['logo']): ?>
                                    <h6>Logo</h6>
                                    <img src="../uploads/logos/<?php echo htmlspecialchars($company['logo']); ?>" 
                                         alt="Logo" class="img-fluid rounded" style="max-width: 200px;">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Edit Company Modals -->
    <?php foreach ($companies as $company): ?>
        <div class="modal fade" id="editCompanyModal<?php echo $company['id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Firma Düzenle</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST">
                        <div class="modal-body">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="company_id" value="<?php echo $company['id']; ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="company_name<?php echo $company['id']; ?>" class="form-label">Firma Adı</label>
                                        <input type="text" class="form-control" id="company_name<?php echo $company['id']; ?>" 
                                               name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="authorized_person<?php echo $company['id']; ?>" class="form-label">Yetkili Kişi</label>
                                        <input type="text" class="form-control" id="authorized_person<?php echo $company['id']; ?>" 
                                               name="authorized_person" value="<?php echo htmlspecialchars($company['authorized_person']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email<?php echo $company['id']; ?>" class="form-label">E-posta</label>
                                        <input type="email" class="form-control" id="email<?php echo $company['id']; ?>" 
                                               name="email" value="<?php echo htmlspecialchars($company['email']); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="phone<?php echo $company['id']; ?>" class="form-label">Telefon</label>
                                        <input type="text" class="form-control" id="phone<?php echo $company['id']; ?>" 
                                               name="phone" value="<?php echo htmlspecialchars($company['phone']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="tax_number<?php echo $company['id']; ?>" class="form-label">Vergi Numarası</label>
                                        <input type="text" class="form-control" id="tax_number<?php echo $company['id']; ?>" 
                                               name="tax_number" value="<?php echo htmlspecialchars($company['tax_number']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="address<?php echo $company['id']; ?>" class="form-label">Adres</label>
                                        <textarea class="form-control" id="address<?php echo $company['id']; ?>" 
                                                  name="address" rows="3"><?php echo htmlspecialchars($company['address']); ?></textarea>
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

    <!-- Delete Form -->
    <form id="deleteCompanyForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="company_id" id="deleteCompanyId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteCompany(id, name) {
            if (confirm('Bu firmayı silmek istediğinizden emin misiniz?\n\nFirma: ' + name + '\n\nDikkat: Bu işlem geri alınamaz!')) {
                document.getElementById('deleteCompanyId').value = id;
                document.getElementById('deleteCompanyForm').submit();
            }
        }
    </script>
</body>
</html>
