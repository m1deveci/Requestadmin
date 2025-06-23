<?php
session_start();
require_once '../config/database.php';
require_once '../config/mail.php';
require_once '../includes/functions.php';

requireAuth(['employee']);

$database = new Database();
$db = $database->getConnection();

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = $_POST['category_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    
    $errors = [];
    
    if (empty($categoryId)) {
        $errors[] = 'Kategori seçimi zorunludur.';
    }
    
    if (empty($title)) {
        $errors[] = 'Başlık alanı zorunludur.';
    }
    
    if (empty($description)) {
        $errors[] = 'Açıklama alanı zorunludur.';
    }
    
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = '../uploads/requests/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($fileExtension, $allowedExtensions)) {
            $fileName = uniqid() . '.' . $fileExtension;
            $imagePath = $uploadDir . $fileName;
            
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $imagePath)) {
                $errors[] = 'Dosya yükleme sırasında hata oluştu.';
                $imagePath = null;
            } else {
                $imagePath = 'uploads/requests/' . $fileName;
            }
        } else {
            $errors[] = 'Sadece JPG, JPEG, PNG ve GIF dosyaları yüklenebilir.';
        }
    }
    
    if (empty($errors)) {
        $requestNumber = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $checkQuery = "SELECT COUNT(*) FROM requests WHERE request_number = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$requestNumber]);
        
        while ($checkStmt->fetchColumn() > 0) {
            $requestNumber = 'REQ-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $checkStmt->execute([$requestNumber]);
        }
        
        $query = "INSERT INTO requests (request_number, employee_id, category_id, title, description, image, priority) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$requestNumber, $userId, $categoryId, $title, $description, $imagePath, $priority])) {
            $requestId = $db->lastInsertId();
            
            logAdminAction($db, $userId, 'request_created', "Yeni talep oluşturuldu: $requestNumber");
            
            $categoryQuery = "SELECT requires_manager_approval FROM request_categories WHERE id = ?";
            $categoryStmt = $db->prepare($categoryQuery);
            $categoryStmt->execute([$categoryId]);
            $requiresApproval = $categoryStmt->fetchColumn();
            
            if ($requiresApproval) {
                $updateQuery = "UPDATE requests SET status = 'manager_approval' WHERE id = ?";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->execute([$requestId]);
                
                $mailService = new MailService();
                $mailService->sendManagerApprovalRequest($requestId);
            } else {
                $mailService = new MailService();
                $mailService->sendRequestNotification($requestId, 'new_request');
            }
            
            $_SESSION['success'] = 'Talep başarıyla oluşturuldu. Talep numaranız: ' . $requestNumber;
            header('Location: requests.php');
            exit;
        } else {
            $errors[] = 'Talep oluşturulurken hata oluştu.';
        }
    }
    
    $_SESSION['errors'] = $errors;
}

$categoriesQuery = "SELECT * FROM request_categories ORDER BY category_name";
$categoriesStmt = $db->prepare($categoriesQuery);
$categoriesStmt->execute();
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

$userQuery = "SELECT first_name, last_name FROM users WHERE id = ?";
$userStmt = $db->prepare($userQuery);
$userStmt->execute([$userId]);
$user = $userStmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Talep - Request Admin</title>
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

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Yeni Talep Oluştur</h1>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['errors'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <ul class="mb-0">
                            <?php foreach ($_SESSION['errors'] as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <?php unset($_SESSION['errors']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5>Talep Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Kategori <span class="text-danger">*</span></label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Kategori Seçiniz</option>
                                            <?php foreach ($categories as $category): ?>
                                                <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                                    <?php if ($category['requires_manager_approval']): ?>
                                                        <span class="text-warning">(Yönetici Onayı Gerekli)</span>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="title" class="form-label">Başlık <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" required placeholder="Talep başlığını giriniz">
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Açıklama <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="5" required placeholder="Talep detaylarını açıklayınız"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    </div>

                                    <div class="mb-3">
                                        <label for="priority" class="form-label">Öncelik</label>
                                        <select class="form-select" id="priority" name="priority">
                                            <option value="low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'low') ? 'selected' : ''; ?>>Düşük</option>
                                            <option value="medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'medium') ? 'selected' : ''; ?>>Orta</option>
                                            <option value="high" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'high') ? 'selected' : ''; ?>>Yüksek</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="image" class="form-label">Görsel (İsteğe Bağlı)</label>
                                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                                        <div class="form-text">Sadece JPG, JPEG, PNG ve GIF dosyaları kabul edilir. Maksimum dosya boyutu: 5MB</div>
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="dashboard.php" class="btn btn-secondary me-md-2">İptal</a>
                                        <button type="submit" class="btn btn-primary">Talep Oluştur</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6>Talep Oluşturma Rehberi</h6>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle"></i> Bilgi</h6>
                                    <ul class="mb-0">
                                        <li>Talep başlığını açık ve net yazınız</li>
                                        <li>Açıklama kısmında detaylı bilgi veriniz</li>
                                        <li>Gerekirse görsel ekleyiniz</li>
                                        <li>Öncelik seviyesini doğru seçiniz</li>
                                    </ul>
                                </div>

                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Dikkat</h6>
                                    <p class="mb-0">Bazı kategoriler yönetici onayı gerektirir. Bu durumda talebiniz önce yöneticinize gönderilecektir.</p>
                                </div>

                                <div class="card">
                                    <div class="card-header">
                                        <h6>Öncelik Seviyeleri</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-2">
                                            <span class="badge bg-success">Düşük</span>
                                            <small class="text-muted">Acil olmayan talepler</small>
                                        </div>
                                        <div class="mb-2">
                                            <span class="badge bg-warning">Orta</span>
                                            <small class="text-muted">Normal öncelikli talepler</small>
                                        </div>
                                        <div class="mb-0">
                                            <span class="badge bg-danger">Yüksek</span>
                                            <small class="text-muted">Acil talepler</small>
                                        </div>
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
    <script>
        document.getElementById('image').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    alert('Dosya boyutu 5MB\'dan büyük olamaz.');
                    e.target.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Sadece JPG, JPEG, PNG ve GIF dosyaları kabul edilir.');
                    e.target.value = '';
                    return;
                }
            }
        });

        document.getElementById('category_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const requiresApproval = selectedOption.text.includes('Yönetici Onayı Gerekli');
            
            if (requiresApproval) {
                if (!document.getElementById('approval-warning')) {
                    const warning = document.createElement('div');
                    warning.id = 'approval-warning';
                    warning.className = 'alert alert-warning mt-2';
                    warning.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Bu kategori için yönetici onayı gereklidir. Talebiniz önce yöneticinize gönderilecektir.';
                    this.parentNode.appendChild(warning);
                }
            } else {
                const warning = document.getElementById('approval-warning');
                if (warning) {
                    warning.remove();
                }
            }
        });
    </script>
</body>
</html>
