-- ฐานข้อมูลระบบจัดการหอพัก
CREATE DATABASE IF NOT EXISTS dorm_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE dorm_management;

-- ตารางผู้ใช้งาน
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    role ENUM('owner', 'tenant', 'technician') NOT NULL,
    profile_image VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- ตารางห้องพัก
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(20) UNIQUE NOT NULL,
    floor INT NOT NULL,
    room_type VARCHAR(50),
    monthly_rent DECIMAL(10,2) NOT NULL,
    water_rate_per_unit DECIMAL(10,2) DEFAULT 18.00,
    electric_rate_per_unit DECIMAL(10,2) DEFAULT 8.00,
    status ENUM('available', 'occupied', 'maintenance') DEFAULT 'available',
    description TEXT,
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ตารางการจองห้อง
CREATE TABLE bookings (
    booking_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    user_id INT NOT NULL,
    booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    move_in_date DATE NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
    notes TEXT,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ตารางสัญญาเช่า
CREATE TABLE contracts (
    contract_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE,
    deposit_amount DECIMAL(10,2),
    status ENUM('active', 'expired', 'terminated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (tenant_id) REFERENCES users(user_id)
);

-- ตารางบิลรายเดือน
CREATE TABLE monthly_bills (
    bill_id INT PRIMARY KEY AUTO_INCREMENT,
    contract_id INT NOT NULL,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    billing_month DATE NOT NULL,
    room_rent DECIMAL(10,2) NOT NULL,
    water_previous_reading DECIMAL(10,2),
    water_current_reading DECIMAL(10,2),
    water_usage DECIMAL(10,2),
    water_cost DECIMAL(10,2),
    electric_previous_reading DECIMAL(10,2),
    electric_current_reading DECIMAL(10,2),
    electric_usage DECIMAL(10,2),
    electric_cost DECIMAL(10,2),
    other_charges DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    qr_code_path VARCHAR(255),
    payment_status ENUM('unpaid', 'paid', 'overdue') DEFAULT 'unpaid',
    payment_date TIMESTAMP NULL,
    due_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (contract_id) REFERENCES contracts(contract_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (tenant_id) REFERENCES users(user_id)
);

-- ตารางแจ้งซ่อม
CREATE TABLE maintenance_requests (
    request_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    tenant_id INT NOT NULL,
    technician_id INT,
    issue_title VARCHAR(200) NOT NULL,
    issue_description TEXT NOT NULL,
    category VARCHAR(50),
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('pending', 'assigned', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    image_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    assigned_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    notes TEXT,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (tenant_id) REFERENCES users(user_id),
    FOREIGN KEY (technician_id) REFERENCES users(user_id)
);

-- ตารางแชท
CREATE TABLE chat_messages (
    message_id INT PRIMARY KEY AUTO_INCREMENT,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(user_id),
    FOREIGN KEY (receiver_id) REFERENCES users(user_id)
);

-- ตารางห้องแชท
CREATE TABLE chat_rooms (
    chat_room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT,
    request_id INT,
    participant_1 INT NOT NULL,
    participant_2 INT NOT NULL,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (request_id) REFERENCES maintenance_requests(request_id),
    FOREIGN KEY (participant_1) REFERENCES users(user_id),
    FOREIGN KEY (participant_2) REFERENCES users(user_id)
);

-- ตารางการชำระเงิน
CREATE TABLE payments (
    payment_id INT PRIMARY KEY AUTO_INCREMENT,
    bill_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_ref VARCHAR(100),
    payment_slip_url VARCHAR(255),
    payment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_by INT,
    verified_at TIMESTAMP NULL,
    FOREIGN KEY (bill_id) REFERENCES monthly_bills(bill_id),
    FOREIGN KEY (verified_by) REFERENCES users(user_id)
);

-- ตารางการแจ้งเตือน
CREATE TABLE notifications (
    notification_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type VARCHAR(50),
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- ข้อมูลเริ่มต้น: เจ้าของหอพัก
INSERT INTO users (username, password, full_name, email, phone, role) VALUES
('owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'เจ้าของหอพัก', 'owner@dorm.com', '0812345678', 'owner');

-- ข้อมูลเริ่มต้น: ช่างซ่อม
INSERT INTO users (username, password, full_name, email, phone, role) VALUES
('tech01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ช่างสมชาย', 'tech01@dorm.com', '0823456789', 'technician');

-- ข้อมูลเริ่มต้น: ห้องพัก
INSERT INTO rooms (room_number, floor, room_type, monthly_rent, status, description) VALUES
('101', 1, 'Standard', 3500.00, 'available', 'ห้องพักขนาดกลาง พร้อมเฟอร์นิเจอร์'),
('102', 1, 'Standard', 3500.00, 'available', 'ห้องพักขนาดกลาง พร้อมเฟอร์นิเจอร์'),
('201', 2, 'Deluxe', 4500.00, 'available', 'ห้องพักขนาดใหญ่ พร้อมระเบียง'),
('202', 2, 'Deluxe', 4500.00, 'available', 'ห้องพักขนาดใหญ่ พร้อมระเบียง'),
('301', 3, 'Suite', 6000.00, 'available', 'ห้องพักหรู พร้อมครัว');

-- สร้าง Index เพื่อเพิ่มประสิทธิภาพ
CREATE INDEX idx_bookings_status ON bookings(status);
CREATE INDEX idx_contracts_status ON contracts(status);
CREATE INDEX idx_bills_payment_status ON monthly_bills(payment_status);
CREATE INDEX idx_maintenance_status ON maintenance_requests(status);
CREATE INDEX idx_chat_participants ON chat_messages(sender_id, receiver_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);