<?php
require_once 'config/database.php';

echo "Starting database migration...\n";

try {
    $database = new Database();
    $db = $database->getConnection();
    
    $result = $db->query("SHOW TABLES LIKE 'provinces'");
    if ($result->rowCount() == 0) {
        echo "Creating provinces table...\n";
        $query = "CREATE TABLE provinces (
            id INT AUTO_INCREMENT PRIMARY KEY,
            province_name VARCHAR(100) NOT NULL COLLATE utf8_turkish_ci,
            province_code VARCHAR(2) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) COLLATE utf8_turkish_ci";
        $db->exec($query);
        echo "Provinces table created.\n";
    }
    
    $result = $db->query("SHOW COLUMNS FROM locations LIKE 'province_id'");
    if ($result->rowCount() == 0) {
        echo "Adding province_id column to locations table...\n";
        $db->exec("ALTER TABLE locations ADD COLUMN province_id INT");
        echo "Province_id column added to locations.\n";
    }
    
    $result = $db->query("SHOW COLUMNS FROM users LIKE 'province_id'");
    if ($result->rowCount() == 0) {
        echo "Adding province_id column to users table...\n";
        $db->exec("ALTER TABLE users ADD COLUMN province_id INT");
        echo "Province_id column added to users.\n";
    }
    
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
    
    echo "Inserting Turkish provinces...\n";
    $stmt = $db->prepare("INSERT IGNORE INTO provinces (province_name, province_code) VALUES (?, ?)");
    foreach ($provinces as $province) {
        $stmt->execute($province);
    }
    echo "Turkish provinces inserted.\n";
    
    try {
        $db->exec("ALTER TABLE locations ADD CONSTRAINT fk_locations_province FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL");
        echo "Foreign key constraint added to locations table.\n";
    } catch (Exception $e) {
        echo "Foreign key constraint for locations already exists or failed: " . $e->getMessage() . "\n";
    }
    
    try {
        $db->exec("ALTER TABLE users ADD CONSTRAINT fk_users_province FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE SET NULL");
        echo "Foreign key constraint added to users table.\n";
    } catch (Exception $e) {
        echo "Foreign key constraint for users already exists or failed: " . $e->getMessage() . "\n";
    }
    
    $istanbulQuery = $db->prepare("SELECT id FROM provinces WHERE province_name = 'İstanbul' LIMIT 1");
    $istanbulQuery->execute();
    $istanbul = $istanbulQuery->fetch(PDO::FETCH_ASSOC);
    $istanbulId = $istanbul ? $istanbul['id'] : 1;
    
    $db->exec("UPDATE users SET province_id = $istanbulId WHERE province_id IS NULL");
    $db->exec("UPDATE locations SET province_id = $istanbulId WHERE province_id IS NULL");
    
    echo "Database migration completed successfully!\n";
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
?>
