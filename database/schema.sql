-- Request Admin System - MySQL Database Schema
-- Supabase'den MySQL'e uyarlanmış versiyon

-- Veritabanı oluştur
CREATE DATABASE IF NOT EXISTS requestadmin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE requestadmin;

-- Companies tablosu
CREATE TABLE IF NOT EXISTS companies (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    company_name VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50) NOT NULL,
    authorized_person VARCHAR(255) NOT NULL,
    tax_number VARCHAR(50) UNIQUE NOT NULL,
    address TEXT NOT NULL,
    logo TEXT,
    email VARCHAR(255) UNIQUE NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Provinces tablosu
CREATE TABLE IF NOT EXISTS provinces (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    province_name VARCHAR(100) UNIQUE NOT NULL,
    province_code VARCHAR(10) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Locations tablosu
CREATE TABLE IF NOT EXISTS locations (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    company_id CHAR(36) NOT NULL,
    province_id CHAR(36),
    location_name VARCHAR(255) NOT NULL,
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (province_id) REFERENCES provinces(id)
);

-- Users tablosu
CREATE TABLE IF NOT EXISTS users (
    id CHAR(36) PRIMARY KEY,
    company_id CHAR(36) NOT NULL,
    location_id CHAR(36),
    province_id CHAR(36),
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'hr', 'employee') DEFAULT 'employee',
    title VARCHAR(100),
    department VARCHAR(100),
    manager_id CHAR(36),
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    FOREIGN KEY (location_id) REFERENCES locations(id),
    FOREIGN KEY (province_id) REFERENCES provinces(id),
    FOREIGN KEY (manager_id) REFERENCES users(id)
);

-- Request Categories tablosu
CREATE TABLE IF NOT EXISTS request_categories (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    category_name VARCHAR(100) UNIQUE NOT NULL,
    requires_manager_approval BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Requests tablosu
CREATE TABLE IF NOT EXISTS requests (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    request_number VARCHAR(20) UNIQUE NOT NULL,
    employee_id CHAR(36) NOT NULL,
    category_id CHAR(36) NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    attachment TEXT,
    status ENUM('pending', 'assigned', 'in_progress', 'manager_approval', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    assigned_to CHAR(36),
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (category_id) REFERENCES request_categories(id),
    FOREIGN KEY (assigned_to) REFERENCES users(id)
);

-- Request Status History tablosu
CREATE TABLE IF NOT EXISTS request_status_history (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    request_id CHAR(36) NOT NULL,
    old_status VARCHAR(50),
    new_status VARCHAR(50) NOT NULL,
    changed_by CHAR(36) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id)
);

-- Indexler
CREATE INDEX idx_users_company_id ON users(company_id);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_requests_employee_id ON requests(employee_id);
CREATE INDEX idx_requests_status ON requests(status);
CREATE INDEX idx_requests_created_at ON requests(created_at);
CREATE INDEX idx_request_status_history_request_id ON request_status_history(request_id);

-- Türkiye illeri verilerini ekle
INSERT INTO provinces (province_name, province_code) VALUES
('Adana', '01'), ('Adıyaman', '02'), ('Afyonkarahisar', '03'), ('Ağrı', '04'),
('Amasya', '05'), ('Ankara', '06'), ('Antalya', '07'), ('Artvin', '08'),
('Aydın', '09'), ('Balıkesir', '10'), ('Bilecik', '11'), ('Bingöl', '12'),
('Bitlis', '13'), ('Bolu', '14'), ('Burdur', '15'), ('Bursa', '16'),
('Çanakkale', '17'), ('Çankırı', '18'), ('Çorum', '19'), ('Denizli', '20'),
('Diyarbakır', '21'), ('Edirne', '22'), ('Elazığ', '23'), ('Erzincan', '24'),
('Erzurum', '25'), ('Eskişehir', '26'), ('Gaziantep', '27'), ('Giresun', '28'),
('Gümüşhane', '29'), ('Hakkâri', '30'), ('Hatay', '31'), ('Isparta', '32'),
('Mersin', '33'), ('İstanbul', '34'), ('İzmir', '35'), ('Kars', '36'),
('Kastamonu', '37'), ('Kayseri', '38'), ('Kırklareli', '39'), ('Kırşehir', '40'),
('Kocaeli', '41'), ('Konya', '42'), ('Kütahya', '43'), ('Malatya', '44'),
('Manisa', '45'), ('Kahramanmaraş', '46'), ('Mardin', '47'), ('Muğla', '48'),
('Muş', '49'), ('Nevşehir', '50'), ('Niğde', '51'), ('Ordu', '52'),
('Rize', '53'), ('Sakarya', '54'), ('Samsun', '55'), ('Siirt', '56'),
('Sinop', '57'), ('Sivas', '58'), ('Tekirdağ', '59'), ('Tokat', '60'),
('Trabzon', '61'), ('Tunceli', '62'), ('Şanlıurfa', '63'), ('Uşak', '64'),
('Van', '65'), ('Yozgat', '66'), ('Zonguldak', '67'), ('Aksaray', '68'),
('Bayburt', '69'), ('Karaman', '70'), ('Kırıkkale', '71'), ('Batman', '72'),
('Şırnak', '73'), ('Bartın', '74'), ('Ardahan', '75'), ('Iğdır', '76'),
('Yalova', '77'), ('Karabük', '78'), ('Kilis', '79'), ('Osmaniye', '80'),
('Düzce', '81')
ON DUPLICATE KEY UPDATE province_name = VALUES(province_name);

-- Varsayılan talep kategorileri
INSERT INTO request_categories (category_name, requires_manager_approval) VALUES
('İzin Talebi', TRUE),
('Avans Talebi', TRUE),
('Malzeme Talebi', FALSE),
('IT Destek', FALSE),
('İnsan Kaynakları', FALSE),
('Muhasebe', FALSE),
('Genel Talep', FALSE),
('Şikayet', FALSE),
('Öneri', FALSE),
('Eğitim Talebi', TRUE)
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- Test kullanıcıları için varsayılan şirket
INSERT INTO companies (id, company_name, phone, authorized_person, tax_number, address, email, status) VALUES
('550e8400-e29b-41d4-a716-446655440000', 'Test Şirketi', '0212 555 0123', 'Test Yönetici', '1234567890', 'Test Adres', 'test@company.com', 'approved')
ON DUPLICATE KEY UPDATE company_name = VALUES(company_name);

-- Test kullanıcıları
INSERT INTO users (id, company_id, first_name, last_name, email, password_hash, role, status) VALUES
('550e8400-e29b-41d4-a716-446655440001', '550e8400-e29b-41d4-a716-446655440000', 'Admin', 'User', 'admin@system.com', '$2b$10$rQZ8K9vL2mN3pO4qR5sT6uV7wX8yZ9aB0cD1eF2gH3iJ4kL5mN6oP7qR8sT9uV', 'admin', 'active'),
('550e8400-e29b-41d4-a716-446655440002', '550e8400-e29b-41d4-a716-446655440000', 'HR', 'User', 'hr@test.com', '$2b$10$rQZ8K9vL2mN3pO4qR5sT6uV7wX8yZ9aB0cD1eF2gH3iJ4kL5mN6oP7qR8sT9uV', 'hr', 'active'),
('550e8400-e29b-41d4-a716-446655440003', '550e8400-e29b-41d4-a716-446655440000', 'Employee', 'User', 'employee@test.com', '$2b$10$rQZ8K9vL2mN3pO4qR5sT6uV7wX8yZ9aB0cD1eF2gH3iJ4kL5mN6oP7qR8sT9uV', 'employee', 'active')
ON DUPLICATE KEY UPDATE first_name = VALUES(first_name);


