için <?php
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

    $query = "CREATE TABLE IF NOT EXISTS provinces (
        id INT AUTO_INCREMENT PRIMARY KEY,
        province_name VARCHAR(100) NOT NULL COLLATE utf8_turkish_ci,
        province_code VARCHAR(2) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);

    $query = "CREATE TABLE IF NOT EXISTS locations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_name VARCHAR(255) NOT NULL COLLATE utf8_turkish_ci,
        address TEXT COLLATE utf8_turkish_ci,
        province_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
        FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL
    ) COLLATE utf8_turkish_ci";
    $db->exec($query);

    $query = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        company_id INT NOT NULL,
        location_id INT,
        province_id INT,
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
        FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL,
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
    
    $query = "CREATE TABLE IF NOT EXISTS admin_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        description TEXT NOT NULL COLLATE utf8_turkish_ci,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT COLLATE utf8_turkish_ci,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
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

    $provinces = [
        ['Adana', '01'], ['Adıyaman', '02'], ['Afyonkarahisar', '03'], ['Ağrı', '04'], ['Amasya', '05'],
        ['Ankara', '06'], ['Antalya', '07'], ['Artvin', '08'], ['Aydın', '09'], ['Balıkesir', '10'],
        ['Bilecik', '11'], ['Bingöl', '12'], ['Bitlis', '13'], ['Bolu', '14'], ['Burdur', '15'],
        ['Bursa', '16'], ['Çanakkale', '17'], ['Çankırı', '18'], ['Çorum', '19'], ['Denizli', '20'],
        ['Diyarbakır', '21'], ['Edirne', '22'], ['Elazığ', '23'], ['Erzincan', '24'], ['Erzurum', '25'],
        ['Eskişehir', '26'], ['Gaziantep', '27'], ['Giresun', '28'], ['Gümüşhane', '29'], ['Hakkâri', '30'],
        ['Hatay', '31'], ['Isparta', '32'], ['Mersin', '33'], ['İstanbul', '34'], ['İzmir', '35'],
        ['Kars', '36'], ['Kastamonu', '37'], ['Kayseri', '38'], ['Kırklareli', '39'], ['Kırşehir', '40'],
        ['Kocaeli', '41'], ['Konya', '42'], ['Kütahya', '43'], ['Malatya', '44'], ['Manisa', '45'],
        ['Kahramanmaraş', '46'], ['Mardin', '47'], ['Muğla', '48'], ['Muş', '49'], ['Nevşehir', '50'],
        ['Niğde', '51'], ['Ordu', '52'], ['Rize', '53'], ['Sakarya', '54'], ['Samsun', '55'],
        ['Siirt', '56'], ['Sinop', '57'], ['Sivas', '58'], ['Tekirdağ', '59'], ['Tokat', '60'],
        ['Trabzon', '61'], ['Tunceli', '62'], ['Şanlıurfa', '63'], ['Uşak', '64'], ['Van', '65'],
        ['Yozgat', '66'], ['Zonguldak', '67'], ['Aksaray', '68'], ['Bayburt', '69'], ['Karaman', '70'],
        ['Kırıkkale', '71'], ['Batman', '72'], ['Şırnak', '73'], ['Bartın', '74'], ['Ardahan', '75'],
        ['Iğdır', '76'], ['Yalova', '77'], ['Karabük', '78'], ['Kilis', '79'], ['Osmaniye', '80'], ['Düzce', '81']
    ];

    $stmt = $db->prepare("INSERT IGNORE INTO provinces (province_name, province_code) VALUES (?, ?)");
    foreach ($provinces as $province) {
        $stmt->execute($province);
    }

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

    $istanbulQuery = $db->prepare("SELECT id FROM provinces WHERE province_name = 'İstanbul' LIMIT 1");
    $istanbulQuery->execute();
    $istanbul = $istanbulQuery->fetch(PDO::FETCH_ASSOC);
    $istanbulId = $istanbul ? $istanbul['id'] : 1;

    $stmt = $db->prepare("INSERT IGNORE INTO locations (company_id, location_name, address, province_id) VALUES (?, ?, ?, ?)");
    $stmt->execute([$companyId, 'Main Office', 'Main Office Address', $istanbulId]);

    $locationQuery = $db->prepare("SELECT id FROM locations WHERE company_id = ? LIMIT 1");
    $locationQuery->execute([$companyId]);
    $location = $locationQuery->fetch(PDO::FETCH_ASSOC);
    $locationId = $location ? $location['id'] : null;

    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, province_id, first_name, last_name, email, password, role) VALUES (?, ?, ?, 'System', 'Admin', 'admin@system.com', ?, 'admin')");
    $stmt->execute([$companyId, $locationId, $istanbulId, $admin_password]);

    $hr_password = password_hash('hr123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, province_id, first_name, last_name, email, password, role, title, department) VALUES (?, ?, ?, 'HR', 'Manager', 'hr@test.com', ?, 'hr', 'HR Manager', 'Human Resources')");
    $stmt->execute([$companyId, $locationId, $istanbulId, $hr_password]);

    $emp_password = password_hash('emp123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT IGNORE INTO users (company_id, location_id, province_id, first_name, last_name, email, password, role, title, department) VALUES (?, ?, ?, 'Test', 'Employee', 'employee@test.com', ?, 'employee', 'Employee', 'General')");
    $stmt->execute([$companyId, $locationId, $istanbulId, $emp_password]);

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

// if (!file_exists('database_initialized.flag')) {
//     initializeDatabase();
//     file_put_contents('database_initialized.flag', date('Y-m-d H:i:s'));
// }
?>
