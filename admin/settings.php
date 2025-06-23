<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

requireAuth(['admin']);

$database = new Database();
$db = $database->getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'site_title' => $_POST['site_title'],
        'smtp_host' => $_POST['smtp_host'],
        'smtp_port' => $_POST['smtp_port'],
        'smtp_username' => $_POST['smtp_username'],
        'smtp_password' => $_POST['smtp_password'],
        'smtp_encryption' => $_POST['smtp_encryption'],
        'max_login_attempts' => $_POST['max_login_attempts'],
        'lockout_duration' => $_POST['lockout_duration']
    ];
    
    $success = true;
    foreach ($settings as $key => $value) {
        $query = "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
        $stmt = $db->prepare($query);
        if (!$stmt->execute([$key, $value])) {
            $success = false;
            break;
        }
    }
    
    if ($success) {
        $_SESSION['success'] = 'Ayarlar başarıyla güncellendi.';
    } else {
        $_SESSION['error'] = 'Ayarlar güncellenirken bir hata oluştu.';
    }
    
    header('Location: settings.php');
    exit;
}

$query = "SELECT setting_key, setting_value FROM system_settings";
$stmt = $db->prepare($query);
$stmt->execute();
$settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - Admin Panel</title>
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
                    <h1>Sistem Ayarları</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Ayarlar</li>
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

                <form method="POST">
                    <div class="row">
                        <!-- General Settings -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Genel Ayarlar</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="site_title" class="form-label">Site Başlığı</label>
                                        <input type="text" class="form-control" id="site_title" name="site_title" 
                                               value="<?php echo htmlspecialchars($settingsData['site_title'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Settings -->
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Güvenlik Ayarları</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="max_login_attempts" class="form-label">Maksimum Giriş Denemesi</label>
                                                <input type="number" class="form-control" id="max_login_attempts" name="max_login_attempts" 
                                                       value="<?php echo htmlspecialchars($settingsData['max_login_attempts'] ?? '3'); ?>" 
                                                       min="1" max="10" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="lockout_duration" class="form-label">Engelleme Süresi (dakika)</label>
                                                <input type="number" class="form-control" id="lockout_duration" name="lockout_duration" 
                                                       value="<?php echo htmlspecialchars($settingsData['lockout_duration'] ?? '30'); ?>" 
                                                       min="5" max="1440" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SMTP Settings -->
                        <div class="col-lg-6">
                            <div class="card mb-4">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="fas fa-envelope me-2"></i>E-posta Ayarları (SMTP)</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="smtp_host" class="form-label">SMTP Sunucu</label>
                                        <input type="text" class="form-control" id="smtp_host" name="smtp_host" 
                                               value="<?php echo htmlspecialchars($settingsData['smtp_host'] ?? ''); ?>" 
                                               placeholder="smtp.gmail.com">
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_port" class="form-label">Port</label>
                                                <input type="number" class="form-control" id="smtp_port" name="smtp_port" 
                                                       value="<?php echo htmlspecialchars($settingsData['smtp_port'] ?? '587'); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="smtp_encryption" class="form-label">Şifreleme</label>
                                                <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                                    <option value="tls" <?php echo ($settingsData['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                                    <option value="ssl" <?php echo ($settingsData['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                                    <option value="" <?php echo ($settingsData['smtp_encryption'] ?? '') === '' ? 'selected' : ''; ?>>Yok</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_username" class="form-label">Kullanıcı Adı</label>
                                        <input type="text" class="form-control" id="smtp_username" name="smtp_username" 
                                               value="<?php echo htmlspecialchars($settingsData['smtp_username'] ?? ''); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="smtp_password" class="form-label">Parola</label>
                                        <input type="password" class="form-control" id="smtp_password" name="smtp_password" 
                                               value="<?php echo htmlspecialchars($settingsData['smtp_password'] ?? ''); ?>">
                                        <div class="form-text">Güvenlik için parola gizlenmektedir.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System Information -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Sistem Bilgileri</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>PHP Sürümü:</strong><br>
                                    <span class="text-muted"><?php echo PHP_VERSION; ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Sunucu Yazılımı:</strong><br>
                                    <span class="text-muted"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor'; ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Maksimum Dosya Boyutu:</strong><br>
                                    <span class="text-muted"><?php echo ini_get('upload_max_filesize'); ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Bellek Limiti:</strong><br>
                                    <span class="text-muted"><?php echo ini_get('memory_limit'); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Ayarları Kaydet
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ms-2" onclick="location.reload()">
                                        <i class="fas fa-undo me-2"></i>Sıfırla
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-info" data-bs-toggle="modal" data-bs-target="#backupModal">
                                        <i class="fas fa-download me-2"></i>Yedek Al
                                    </button>
                                    <a href="logs.php" class="btn btn-outline-warning ms-2">
                                        <i class="fas fa-file-alt me-2"></i>Sistem Logları
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <!-- Backup Modal -->
    <div class="modal fade" id="backupModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Sistem Yedeği</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Sistem yedeği almak için aşağıdaki seçenekleri kullanabilirsiniz:</p>
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="downloadDatabaseBackup()">
                            <i class="fas fa-database me-2"></i>Veritabanı Yedeği
                        </button>
                        <button class="btn btn-outline-secondary" onclick="downloadFileBackup()">
                            <i class="fas fa-folder me-2"></i>Dosya Yedeği
                        </button>
                        <button class="btn btn-outline-success" onclick="downloadFullBackup()">
                            <i class="fas fa-archive me-2"></i>Tam Yedek
                        </button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function downloadDatabaseBackup() {
            alert('Veritabanı yedeği özelliği geliştirilme aşamasındadır.');
        }
        
        function downloadFileBackup() {
            alert('Dosya yedeği özelliği geliştirilme aşamasındadır.');
        }
        
        function downloadFullBackup() {
            alert('Tam yedek özelliği geliştirilme aşamasındadır.');
        }
    </script>
</body>
</html>
</code>
