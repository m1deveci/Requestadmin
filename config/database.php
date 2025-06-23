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
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8");
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
        company_name VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        authorized_person VARCHAR(255) NOT NULL,
        tax_number VARCHAR(50) NOT NULL,
        address TEXT NOT NULL,
        logo VARCHAR(255),
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_name VARCHAR(255) NOT NULL,
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_id INT,
        first_name VARCHAR(100) NOT NULL,
        last_name VARCHAR(100) NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin', 'hr', 'employee') NOT NULL,
        title VARCHAR(255),
        photo VARCHAR(255),
        manager_id INT,
        department VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        last_login TIMESTAMP NULL,
        failed_login_attempts INT DEFAULT 0,
        locked_until TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL,
        FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS request_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL,
        requires_manager_approval BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_number VARCHAR(50) NOT NULL UNIQUE,
        employee_id INT NOT NULL,
        category_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        image VARCHAR(255),
        status ENUM('pending', 'assigned', 'in_progress', 'manager_approval', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
        assigned_to INT,
        manager_approval_status ENUM('pending', 'approved', 'rejected'),
        manager_approval_date TIMESTAMP NULL,
        manager_comments TEXT,
        priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (category_id) REFERENCES request_categories(id),
        FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS request_status_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        request_id INT NOT NULL,
        old_status VARCHAR(50),
        new_status VARCHAR(50) NOT NULL,
        changed_by INT NOT NULL,
        comments TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
        FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS login_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        email VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        device_fingerprint VARCHAR(255),
        login_status ENUM('success', 'failed') NOT NULL,
        failure_reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS blocked_access (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45),
        device_fingerprint VARCHAR(255),
        blocked_until TIMESTAMP NOT NULL,
        reason VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $db->exec($query);
    
    $query = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(255) NOT NULL UNIQUE,
        setting_value TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
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
    
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, first_name, last_name, email, password, role) VALUES (1, 'System', 'Admin', 'admin@system.com', ?, 'admin')");
    $stmt->execute([$admin_password]);
    
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
