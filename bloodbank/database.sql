-- ============================================================
-- LUGGA CLINIC BLOOD BANK MANAGEMENT SYSTEM
-- Database Schema v1.0 (Fixed)
-- ============================================================

CREATE DATABASE IF NOT EXISTS lugga_bloodbank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lugga_bloodbank;

-- ============================================================
-- USERS & AUTHENTICATION
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    role ENUM('admin','doctor','nurse','lab_technician','receptionist') NOT NULL DEFAULT 'receptionist',
    department VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    last_login DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- DONORS
-- ============================================================
CREATE TABLE donors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_code VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE NOT NULL,
    gender ENUM('Male','Female','Other') NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    occupation VARCHAR(100),
    weight DECIMAL(5,2),
    height DECIMAL(5,2),
    medical_history TEXT,
    allergies TEXT,
    medications TEXT,
    is_eligible TINYINT(1) DEFAULT 1,
    ineligibility_reason TEXT,
    next_eligible_date DATE,
    total_donations INT DEFAULT 0,
    registered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id)
);

-- ============================================================
-- BLOOD DONATIONS
-- ============================================================
CREATE TABLE donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_code VARCHAR(20) UNIQUE NOT NULL,
    donor_id INT NOT NULL,
    donation_date DATE NOT NULL,
    donation_time TIME NOT NULL,
    donation_type ENUM('Whole Blood','Platelets','Plasma','Double Red Cells') NOT NULL DEFAULT 'Whole Blood',
    volume_ml INT NOT NULL DEFAULT 450,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    systolic_bp INT,
    diastolic_bp INT,
    pulse_rate INT,
    hemoglobin DECIMAL(4,1),
    temperature DECIMAL(4,1),
    pre_donation_remarks TEXT,
    post_donation_remarks TEXT,
    adverse_reactions TEXT,
    status ENUM('Collected','Processing','Quarantine','Approved','Rejected','Discarded') DEFAULT 'Collected',
    collected_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donor_id) REFERENCES donors(id),
    FOREIGN KEY (collected_by) REFERENCES users(id)
);

-- ============================================================
-- BLOOD INVENTORY (BLOOD UNITS)
-- ============================================================
CREATE TABLE blood_units (
    id INT AUTO_INCREMENT PRIMARY KEY,
    unit_code VARCHAR(30) UNIQUE NOT NULL,
    donation_id INT,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    component_type ENUM('Whole Blood','Packed Red Cells','Fresh Frozen Plasma','Platelets','Cryoprecipitate','Buffy Coat') NOT NULL,
    volume_ml INT NOT NULL,
    collection_date DATE NOT NULL,
    expiry_date DATE NOT NULL,
    storage_temperature DECIMAL(4,1),
    bag_number VARCHAR(50),
    status ENUM('Available','Reserved','Issued','Expired','Discarded','Quarantine') DEFAULT 'Available',
    storage_location VARCHAR(50),
    rhesus_factor ENUM('Positive','Negative') NOT NULL,
    screening_status ENUM('Pending','Cleared','Reactive') DEFAULT 'Pending',
    hiv_status ENUM('Pending','Non-Reactive','Reactive') DEFAULT 'Pending',
    hbsag_status ENUM('Pending','Non-Reactive','Reactive') DEFAULT 'Pending',
    hcv_status ENUM('Pending','Non-Reactive','Reactive') DEFAULT 'Pending',
    syphilis_status ENUM('Pending','Non-Reactive','Reactive') DEFAULT 'Pending',
    malaria_status ENUM('Pending','Non-Reactive','Reactive') DEFAULT 'Pending',
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- ============================================================
-- PATIENTS / RECIPIENTS
-- ============================================================
CREATE TABLE patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    gender ENUM('Male','Female','Other') NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-'),
    phone VARCHAR(20),
    email VARCHAR(100),
    address TEXT,
    ward VARCHAR(100),
    bed_number VARCHAR(20),
    diagnosis TEXT,
    attending_doctor VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    registered_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (registered_by) REFERENCES users(id)
);

-- ============================================================
-- BLOOD REQUESTS
-- ============================================================
CREATE TABLE blood_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_code VARCHAR(20) UNIQUE NOT NULL,
    patient_id INT NOT NULL,
    requested_by INT NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    component_type ENUM('Whole Blood','Packed Red Cells','Fresh Frozen Plasma','Platelets','Cryoprecipitate','Buffy Coat') NOT NULL,
    units_requested INT NOT NULL DEFAULT 1,
    units_approved INT DEFAULT 0,
    urgency ENUM('Emergency','Urgent','Routine') NOT NULL DEFAULT 'Routine',
    reason TEXT NOT NULL,
    required_date DATE,
    crossmatch_required TINYINT(1) DEFAULT 1,
    status ENUM('Pending','Processing','Approved','Partially Fulfilled','Fulfilled','Cancelled','Rejected') DEFAULT 'Pending',
    approved_by INT,
    approval_date DATETIME,
    rejection_reason TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (requested_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- ============================================================
-- BLOOD DISTRIBUTIONS / ISSUANCES
-- ============================================================
CREATE TABLE blood_distributions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    distribution_code VARCHAR(20) UNIQUE NOT NULL,
    request_id INT NOT NULL,
    unit_id INT NOT NULL,
    patient_id INT NOT NULL,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    component_type VARCHAR(100) NOT NULL,
    issue_date DATETIME NOT NULL,
    issued_by INT NOT NULL,
    received_by VARCHAR(100),
    ward VARCHAR(100),
    crossmatch_result ENUM('Compatible','Incompatible','Pending') DEFAULT 'Pending',
    transfusion_status ENUM('Pending','Transfused','Returned','Discarded') DEFAULT 'Pending',
    transfusion_date DATETIME,
    transfusion_outcome TEXT,
    adverse_reaction TINYINT(1) DEFAULT 0,
    adverse_reaction_details TEXT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES blood_requests(id),
    FOREIGN KEY (unit_id) REFERENCES blood_units(id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (issued_by) REFERENCES users(id)
);

-- ============================================================
-- COMPATIBILITY MATRIX (for Distribution Algorithm)
-- FIX: Removed invalid `PRIMARY KEY_LABEL VARCHAR(10)` column.
--      "PRIMARY KEY" is a reserved keyword — it cannot be used as
--      the start of a column name. Renamed to `pair_label`.
-- ============================================================
CREATE TABLE blood_compatibility (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    donor_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    is_compatible TINYINT(1) NOT NULL DEFAULT 0,
    compatibility_score INT NOT NULL DEFAULT 0 COMMENT '10=exact, 8=same type diff Rh, 5=compatible, 3=emergency only',
    pair_label VARCHAR(10)
);

-- Populate compatibility matrix
INSERT INTO blood_compatibility (recipient_group, donor_group, is_compatible, compatibility_score) VALUES
-- O- recipient
('O-','O-',1,10), ('O-','O+',0,0), ('O-','A+',0,0), ('O-','A-',0,0),
('O-','B+',0,0), ('O-','B-',0,0), ('O-','AB+',0,0), ('O-','AB-',0,0),

-- O+ recipient
('O+','O+',1,10), ('O+','O-',1,8), ('O+','A+',0,0), ('O+','A-',0,0),
('O+','B+',0,0), ('O+','B-',0,0), ('O+','AB+',0,0), ('O+','AB-',0,0),

-- A- recipient
('A-','A-',1,10), ('A-','O-',1,8), ('A-','A+',0,0), ('A-','O+',0,0),
('A-','B+',0,0), ('A-','B-',0,0), ('A-','AB+',0,0), ('A-','AB-',0,0),

-- A+ recipient
('A+','A+',1,10), ('A+','A-',1,8), ('A+','O+',1,7), ('A+','O-',1,6),
('A+','B+',0,0), ('A+','B-',0,0), ('A+','AB+',0,0), ('A+','AB-',0,0),

-- B- recipient
('B-','B-',1,10), ('B-','O-',1,8), ('B-','B+',0,0), ('B-','O+',0,0),
('B-','A+',0,0), ('B-','A-',0,0), ('B-','AB+',0,0), ('B-','AB-',0,0),

-- B+ recipient
('B+','B+',1,10), ('B+','B-',1,8), ('B+','O+',1,7), ('B+','O-',1,6),
('B+','A+',0,0), ('B+','A-',0,0), ('B+','AB+',0,0), ('B+','AB-',0,0),

-- AB- recipient
('AB-','AB-',1,10), ('AB-','A-',1,8), ('AB-','B-',1,8), ('AB-','O-',1,7),
('AB-','AB+',0,0), ('AB-','A+',0,0), ('AB-','B+',0,0), ('AB-','O+',0,0),

-- AB+ recipient (universal recipient)
('AB+','AB+',1,10), ('AB+','AB-',1,9), ('AB+','A+',1,8), ('AB+','A-',1,7),
('AB+','B+',1,8), ('AB+','B-',1,7), ('AB+','O+',1,6), ('AB+','O-',1,5);

-- ============================================================
-- LABORATORY TESTS
-- ============================================================
CREATE TABLE lab_tests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    test_code VARCHAR(20) UNIQUE NOT NULL,
    unit_id INT,
    donation_id INT,
    test_type ENUM('Blood Grouping','Crossmatch','HIV','HBsAg','HCV','Syphilis','Malaria','Hemoglobin','Full Blood Count','Coagulation') NOT NULL,
    test_date DATETIME NOT NULL,
    result VARCHAR(255),
    result_value DECIMAL(10,3),
    unit VARCHAR(50),
    reference_range VARCHAR(100),
    status ENUM('Pending','In Progress','Completed','Cancelled') DEFAULT 'Pending',
    performed_by INT,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (unit_id) REFERENCES blood_units(id),
    FOREIGN KEY (donation_id) REFERENCES donations(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- ============================================================
-- STOCK ALERTS
-- ============================================================
CREATE TABLE stock_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    blood_group ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-') NOT NULL,
    component_type VARCHAR(100) NOT NULL,
    minimum_units INT NOT NULL DEFAULT 10,
    critical_units INT NOT NULL DEFAULT 5,
    current_units INT DEFAULT 0,
    alert_level ENUM('Normal','Low','Critical') DEFAULT 'Normal',
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Initialize stock alerts for all blood groups
INSERT INTO stock_alerts (blood_group, component_type, minimum_units, critical_units) VALUES
('A+','Whole Blood',15,5), ('A-','Whole Blood',10,3), ('B+','Whole Blood',15,5),
('B-','Whole Blood',10,3), ('AB+','Whole Blood',10,3), ('AB-','Whole Blood',8,2),
('O+','Whole Blood',20,8), ('O-','Whole Blood',15,5),
('A+','Packed Red Cells',10,3), ('A-','Packed Red Cells',8,2), ('B+','Packed Red Cells',10,3),
('B-','Packed Red Cells',8,2), ('AB+','Packed Red Cells',8,2), ('AB-','Packed Red Cells',5,2),
('O+','Packed Red Cells',15,5), ('O-','Packed Red Cells',12,4);

-- ============================================================
-- NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('low_stock','expiry_warning','request_pending','donation_due','test_result','system') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    blood_group VARCHAR(10),
    reference_id INT,
    reference_type VARCHAR(50),
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- ACTIVITY LOGS / AUDIT TRAIL
-- ============================================================
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    old_values TEXT,
    new_values TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ============================================================
-- CLINIC SETTINGS
-- ============================================================
CREATE TABLE clinic_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO clinic_settings (setting_key, setting_value, setting_group, description) VALUES
('clinic_name', 'Lugga Clinic', 'general', 'Clinic name'),
('clinic_address', '12 Hospital Road, Ibadan, Oyo State', 'general', 'Clinic address'),
('clinic_phone', '+234 800 000 0000', 'general', 'Contact phone'),
('clinic_email', 'bloodbank@luggaclinic.ng', 'general', 'Contact email'),
('clinic_logo', '', 'general', 'Logo path'),
('blood_expiry_whole_blood', '35', 'inventory', 'Whole blood shelf life (days)'),
('blood_expiry_packed_rbc', '42', 'inventory', 'Packed RBC shelf life (days)'),
('blood_expiry_ffp', '365', 'inventory', 'FFP shelf life (days)'),
('blood_expiry_platelets', '5', 'inventory', 'Platelet shelf life (days)'),
('blood_expiry_cryo', '365', 'inventory', 'Cryoprecipitate shelf life (days)'),
('donation_interval_days', '56', 'donation', 'Days between whole blood donations'),
('low_stock_alert_email', '1', 'alerts', 'Send email on low stock'),
('expiry_alert_days', '7', 'alerts', 'Alert days before expiry'),
('storage_temp_rbc', '4', 'storage', 'RBC storage temperature (°C)'),
('storage_temp_ffp', '-18', 'storage', 'FFP storage temperature (°C)'),
('storage_temp_platelets', '22', 'storage', 'Platelet storage temperature (°C)');

-- ============================================================
-- DEFAULT ADMIN USER (password: Admin@123)
-- NOTE: Replace this hash with a proper bcrypt hash of 'Admin@123'
--       before going to production. The hash below is for 'password'.
-- ============================================================
INSERT INTO users (username, password_hash, full_name, email, role, department) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin@luggaclinic.ng', 'admin', 'Administration'),
('dr.lugga', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Dr. Emmanuel Lugga', 'doctor@luggaclinic.ng', 'doctor', 'Haematology'),
('lab.tech', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Amaka Okonkwo', 'lab@luggaclinic.ng', 'lab_technician', 'Laboratory'),
('nurse.joy', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Joy Adeleke', 'nurse@luggaclinic.ng', 'nurse', 'Blood Bank');

-- ============================================================
-- SAMPLE DONOR DATA
-- ============================================================
INSERT INTO donors (donor_code, full_name, date_of_birth, gender, blood_group, phone, email, address, city, total_donations, registered_by) VALUES
('DON-2024-001', 'Chukwuemeka Obi', '1990-03-15', 'Male', 'O+', '08012345678', 'chukwu@email.com', '5 Agodi Road', 'Ibadan', 3, 1),
('DON-2024-002', 'Fatima Al-Hassan', '1995-07-22', 'Female', 'A+', '08023456789', 'fatima@email.com', '10 Lagos Road', 'Ibadan', 2, 1),
('DON-2024-003', 'Taiwo Adeyemi', '1988-11-10', 'Male', 'B-', '08034567890', NULL, '22 Bodija', 'Ibadan', 5, 1),
('DON-2024-004', 'Ngozi Eze', '1992-05-18', 'Female', 'AB+', '08045678901', NULL, '7 Ring Road', 'Ibadan', 1, 1),
('DON-2024-005', 'Biodun Fasanya', '1985-09-30', 'Male', 'O-', '08056789012', NULL, '15 Mokola', 'Ibadan', 8, 1);