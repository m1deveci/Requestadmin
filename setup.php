<?php
require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Request Admin - Kurulum</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css' rel='stylesheet'>
</head>
<body>
<div class='container mt-5'>
    <div class='row justify-content-center'>
        <div class='col-md-8'>
            <div class='card'>
                <div class='card-header bg-primary text-white'>
                    <h4 class='mb-0'>Request Admin - Sistem Kurulumu</h4>
                </div>
                <div class='card-body'>";

try {
    echo "<h5>Veritabanı Bağlantısı Test Ediliyor...</h5>";
    
    $database = new Database();
    $db = $database->getConnection();
    
    if ($db) {
        echo "<div class='alert alert-success'>✓ Veritabanı bağlantısı başarılı!</div>";
        
        echo "<h5>Tablolar Oluşturuluyor...</h5>";
        
        if (file_exists('database_initialized.flag')) {
            unlink('database_initialized.flag');
        }
        
        initializeDatabase();
        
        echo "<div class='alert alert-success'>✓ Veritabanı tabloları oluşturuldu!</div>";
        
        echo "<h5>Varsayılan Veriler Ekleniyor...</h5>";
        
        $query = "INSERT IGNORE INTO companies (id, company_name, phone, authorized_person, tax_number, address, email, password, status) 
                 VALUES (1, 'Test Şirketi', '+90 212 555 0123', 'Test Yetkilisi', '1234567890', 'Test Adres', 'test@company.com', ?, 'approved')";
        $stmt = $db->prepare($query);
        $stmt->execute([password_hash('test123', PASSWORD_DEFAULT)]);
        
        $query = "INSERT IGNORE INTO locations (id, company_id, location_name, address) 
                 VALUES (1, 1, 'Merkez Ofis', 'Ana Merkez Lokasyonu')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $query = "UPDATE users SET company_id = 1, location_id = 1 WHERE email = 'admin@system.com'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $query = "INSERT IGNORE INTO users (company_id, location_id, first_name, last_name, email, password, role) 
                 VALUES (1, 1, 'İdari', 'Sorumlu', 'hr@test.com', ?, 'hr')";
        $stmt = $db->prepare($query);
        $stmt->execute([password_hash('hr123', PASSWORD_DEFAULT)]);
        
        $query = "INSERT IGNORE INTO users (company_id, location_id, first_name, last_name, email, password, role, title, department) 
                 VALUES (1, 1, 'Test', 'Çalışan', 'employee@test.com', ?, 'employee', 'Yazılım Geliştirici', 'IT')";
        $stmt = $db->prepare($query);
        $stmt->execute([password_hash('emp123', PASSWORD_DEFAULT)]);
        
        echo "<div class='alert alert-success'>✓ Varsayılan veriler eklendi!</div>";
        
        echo "<h5>Kurulum Tamamlandı!</h5>";
        echo "<div class='alert alert-info'>
                <h6>Test Kullanıcıları:</h6>
                <ul>
                    <li><strong>Admin:</strong> admin@system.com / admin123</li>
                    <li><strong>İdari İşler:</strong> hr@test.com / hr123</li>
                    <li><strong>Çalışan:</strong> employee@test.com / emp123</li>
                    <li><strong>Firma:</strong> test@company.com / test123</li>
                </ul>
              </div>";
        
        echo "<div class='d-grid gap-2'>
                <a href='index.php' class='btn btn-primary btn-lg'>Ana Sayfaya Git</a>
              </div>";
        
    } else {
        echo "<div class='alert alert-danger'>✗ Veritabanı bağlantısı başarısız!</div>";
        echo "<p>Lütfen config/database.php dosyasındaki veritabanı ayarlarını kontrol edin.</p>";
    }
    
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>✗ Hata: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "        </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>";
?>
