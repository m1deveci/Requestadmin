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
            $categoryName = trim($_POST['category_name']);
            $requiresManagerApproval = isset($_POST['requires_manager_approval']) ? 1 : 0;
            
            if (!empty($categoryName)) {
                $checkQuery = "SELECT id FROM request_categories WHERE category_name = ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$categoryName]);
                
                if ($checkStmt->fetch()) {
                    $error = 'Bu kategori adı zaten kullanılıyor.';
                } else {
                    $query = "INSERT INTO request_categories (category_name, requires_manager_approval) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$categoryName, $requiresManagerApproval])) {
                        $message = 'Kategori başarıyla eklendi.';
                    } else {
                        $error = 'Kategori eklenirken bir hata oluştu.';
                    }
                }
            } else {
                $error = 'Kategori adı gereklidir.';
            }
        } elseif ($_POST['action'] === 'edit') {
            $categoryId = $_POST['category_id'];
            $categoryName = trim($_POST['category_name']);
            $requiresManagerApproval = isset($_POST['requires_manager_approval']) ? 1 : 0;
            
            if (!empty($categoryName)) {
                $checkQuery = "SELECT id FROM request_categories WHERE category_name = ? AND id != ?";
                $checkStmt = $db->prepare($checkQuery);
                $checkStmt->execute([$categoryName, $categoryId]);
                
                if ($checkStmt->fetch()) {
                    $error = 'Bu kategori adı zaten kullanılıyor.';
                } else {
                    $query = "UPDATE request_categories SET category_name = ?, requires_manager_approval = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    if ($stmt->execute([$categoryName, $requiresManagerApproval, $categoryId])) {
                        $message = 'Kategori başarıyla güncellendi.';
                    } else {
                        $error = 'Kategori güncellenirken bir hata oluştu.';
                    }
                }
            } else {
                $error = 'Kategori adı gereklidir.';
            }
        } elseif ($_POST['action'] === 'delete') {
            $categoryId = $_POST['category_id'];
            
            $checkQuery = "SELECT COUNT(*) FROM requests WHERE category_id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$categoryId]);
            $requestCount = $checkStmt->fetchColumn();
            
            if ($requestCount > 0) {
                $error = 'Bu kategoriye ait talepler bulunduğu için kategori silinemez.';
            } else {
                $query = "DELETE FROM request_categories WHERE id = ?";
                $stmt = $db->prepare($query);
                if ($stmt->execute([$categoryId])) {
                    $message = 'Kategori başarıyla silindi.';
                } else {
                    $error = 'Kategori silinirken bir hata oluştu.';
                }
            }
        }
    }
}

$query = "SELECT rc.*, 
          (SELECT COUNT(*) FROM requests WHERE category_id = rc.id) as request_count
          FROM request_categories rc 
          ORDER BY rc.category_name";
$stmt = $db->prepare($query);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kategori Yönetimi - Request Admin</title>
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
                    <h1>Kategori Yönetimi</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Kategoriler</li>
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
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus"></i> Yeni Kategori Ekle
                        </button>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Talep Kategorileri</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kategori Adı</th>
                                        <th>Yönetici Onayı</th>
                                        <th>Talep Sayısı</th>
                                        <th>Oluşturma Tarihi</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?php echo $category['id']; ?></td>
                                            <td><?php echo htmlspecialchars($category['category_name']); ?></td>
                                            <td>
                                                <?php if ($category['requires_manager_approval']): ?>
                                                    <span class="badge bg-warning">Gerekli</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Gerekli Değil</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?php echo $category['request_count']; ?></span>
                                            </td>
                                            <td><?php echo formatDate($category['created_at']); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-warning" 
                                                        onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-sm btn-danger" 
                                                        onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>')"
                                                        <?php echo $category['request_count'] > 0 ? 'disabled title="Bu kategoriye ait talepler bulunduğu için silinemez"' : ''; ?>>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yeni Kategori Ekle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label for="category_name" class="form-label">Kategori Adı</label>
                            <input type="text" class="form-control" id="category_name" name="category_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires_manager_approval" name="requires_manager_approval">
                                <label class="form-check-label" for="requires_manager_approval">
                                    Yönetici onayı gerekli
                                </label>
                                <div class="form-text">Bu kategori için talepler yönetici onayı gerektirecek</div>
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

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Kategori Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" id="edit_category_id">
                        
                        <div class="mb-3">
                            <label for="edit_category_name" class="form-label">Kategori Adı</label>
                            <input type="text" class="form-control" id="edit_category_name" name="category_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_requires_manager_approval" name="requires_manager_approval">
                                <label class="form-check-label" for="edit_requires_manager_approval">
                                    Yönetici onayı gerekli
                                </label>
                                <div class="form-text">Bu kategori için talepler yönetici onayı gerektirecek</div>
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

    <!-- Delete Form -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="category_id" id="deleteCategoryId">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(category) {
            document.getElementById('edit_category_id').value = category.id;
            document.getElementById('edit_category_name').value = category.category_name;
            document.getElementById('edit_requires_manager_approval').checked = category.requires_manager_approval == 1;
            
            new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
        }
        
        function deleteCategory(id, name) {
            if (confirm('Bu kategoriyi silmek istediğinizden emin misiniz?\n\nKategori: ' + name)) {
                document.getElementById('deleteCategoryId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
