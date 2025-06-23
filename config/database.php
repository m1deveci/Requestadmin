<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'requestadmin';
    private $username = 'requestadmin';
    private $password = 'requestpass';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8", $this->username, $this->password);
            $this->conn->exec("set names utf8 collate utf8_turkish_ci");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}

function initializeDatabase() {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "CREATE TABLE IF NOT EXISTS companies (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        phone VARCHAR(20) NOT NULL,
        authorized_person VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        tax_number VARCHAR(50) NOT NULL,
        address TEXT NOT NULL COLLATE utf8_turkish_ci,
        logo VARCHAR(255),
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_name VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        address TEXT COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_id INT,
        first_name VARCHAR(100) NOT NULL COLLATE utf8_turkish_ci,
        last_name VARCHAR(100) NOT NULL COLLATE utf8_turkish_ci,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'hr', 'employee') NOT NULL,
        title VARCHAR(255) COLLATE utf8_turkish_ci,
        photo VARCHAR(255),
        manager_id INT,
        department VARCHAR(255) COLLATE utf8_turkish_ci,
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS request_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        requires_manager_approval BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_number VARCHAR(50) NOT NULL UNIQUE,
        employee_id INT NOT NULL,
        category_id INT NOT NULL,
        title VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        description TEXT NOT NULL COLLATE utf8_turkish_ci,
        image VARCHAR(255),
        status ENUM('pending', 'assigned', 'in_progress', 'manager_approval', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
        assigned_to INT,
        manager_approval_status ENUM('pending', 'approved', 'rejected'),
        manager_approval_date TIMESTAMP NULL,
        manager_comments TEXT COLLATE utf8_turkish_ci,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES request_categories(id),
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS request_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        old_status VARCHAR(50),
        new_status VARCHAR(50) NOT NULL,
        changed_by INT NOT NULL,
        comments TEXT COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        email VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT COLLATE utf8_turkish_ci,
        device_fingerprint VARCHAR(255),
        login_status ENUM('success', 'failed') NOT NULL,
        failure_reason VARCHAR(255) COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS blocked_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        device_fingerprint VARCHAR(255),
        blocked_until TIMESTAMP NOT NULL,
        reason VARCHAR(255) COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) NOT NULL UNIQUE,
        setting_value TEXT COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);
    
    $categories = [
        ['Ofis Eşyası', false],
        ['Tamirat', true],
        ['Mobilya', true],
        ['Değişim', false],
        ['IT Destek', false],
        ['Temizlik', false],
        ['Güvenlik', true]
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO request_categories (category_name, requires_manager_approval) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute($category);
    }
    
    $stmt = $db->prepare("INSERT IGNORE INTO companies (company_name, phone, authorized_person, tax_number, address, email, password, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['System Company', '000-000-0000', 'System Admin', '0000000000', 'System Address', 'system@company.com', password_hash('system123', PASSWORD_DEFAULT), 'approved']);
    
    $companyQuery = $db->query("SELECT id FROM companies WHERE email = 'system@company.com' LIMIT 1");
    $company = $companyQuery->fetch(PDO::FETCH_ASSOC);
    $companyId = $company ? $company['id'] : 1;
    
    $stmt = $db->prepare("INSERT IGNORE INTO locations (company_id, location_name, address) VALUES (?, ?, ?)");
    $stmt->execute([$companyId, 'Main Office', 'Main Office Address']);
    
    $locationQuery = $db->prepare("SELECT id FROM locations WHERE company_id = ? LIMIT 1");
    $locationQuery->execute([$companyId]);
    $location = $locationQuery->fetch(PDO::FETCH_ASSOC);
    $locationId = $location ? $location['id'] : null;
    
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, first_name, last_name, email, password, role) VALUES (?, ?, 'System', 'Admin', 'admin@system.com', ?, 'admin')");
    $stmt->execute([$companyId, $locationId, $admin_password]);
    
    $hr_password = password_hash('hr123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, first_name, last_name, email, password, role, title, department) VALUES (?, ?, 'HR', 'Manager', 'hr@test.com', ?, 'hr', 'HR Manager', 'Human Resources')");
    $stmt->execute([$companyId, $locationId, $hr_password]);
    
    $emp_password = password_hash('emp123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, first_name, last_name, email, password, role, title, department) VALUES (?, ?, 'Test', 'Employee', 'employee@test.com', ?, 'employee', 'Employee', 'General')");
    $stmt->execute([$companyId, $locationId, $emp_password]);
    
    $settings = [
        ['site_title', 'Request Admin - İdari İşler Talep Yönetim Sistemi'],
        ['smtp_host', ''],
        ['smtp_port', '587'],
        ['smtp_username', ''],
        ['smtp_password', ''],
        ['smtp_encryption', 'tls'],
        ['max_login_attempts', '3'],
        ['lockout_duration', '30']
    ];
    
    $stmt = $db->prepare("INSERT IGNORE INTO system_settings (setting_key, setting_value) VALUES (?, ?)");
    foreach ($settings as $setting) {
        $stmt->execute($setting);
    }
}

if (!file_exists('database_initialized.flag')) {
    initializeDatabase();
    file_put_contents('database_initialized.flag', date('Y-m-d H:i:s'));
}
?>
