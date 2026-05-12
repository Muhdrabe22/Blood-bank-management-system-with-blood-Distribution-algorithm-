# 🩸 Lugga Clinic – Blood Bank Management System
## Version 2.0.0 | PHP + MySQL

---

## 📋 Overview

A comprehensive, full-featured Blood Bank Management System developed as a case study for **Lugga Clinic**, Ibadan, Nigeria. Built with PHP (OOP) and MySQL, featuring a modern dark UI and a proprietary **Blood Distribution Algorithm** that intelligently matches blood units to patient requests.

---

## 🚀 Features

### 🔴 Core Modules
| Module | Description |
|--------|-------------|
| **Dashboard** | Real-time stats, inventory overview, pending requests, expiry alerts |
| **Donor Management** | Registration, profiles, eligibility tracking, donation history |
| **Blood Donations** | Collection recording, vital signs, auto-inventory creation |
| **Blood Inventory** | Unit tracking, expiry management, status updates |
| **Lab Screening** | HIV, HBsAg, HCV, Syphilis, Malaria screening panel |
| **Patient Registry** | Patient records, ward/bed tracking, medical history |
| **Blood Requests** | Request workflow with urgency levels (Emergency/Urgent/Routine) |
| **Smart Distribution** | Algorithm-driven blood issuance with priority scoring |
| **Crossmatch** | Compatibility verification before transfusion |
| **Stock Status** | Visual inventory overview + compatibility matrix |
| **Reports & Analytics** | Charts, trends, fulfillment rates, top donors |
| **Audit Log** | Full activity trail for all system actions |
| **User Management** | Role-based access control (Admin/Doctor/Nurse/Lab Tech/Receptionist) |
| **Notifications** | Low stock, expiry warnings, emergency alerts |
| **Settings** | Clinic info, shelf life, donation rules, storage temps |

---

## 🧬 Blood Distribution Algorithm

The distribution engine uses a **multi-factor priority scoring system**:

```
Priority Score = 
  Compatibility Weight (0-50 pts)
  + Expiry Urgency / FIFO (0-30 pts)     ← Use units expiring soonest first
  + Exact Match Bonus (0-20 pts)         ← Same group = higher score
  + Freshness Score (0-10 pts)           ← Fresher blood preferred
  + Urgency Modifier (0-15 pts)          ← Emergency boosts O- priority
```

**Algorithm Flow:**
1. Receive blood request (patient blood group, component, quantity, urgency)
2. Query `blood_compatibility` table for all compatible donor groups (ranked by score)
3. Fetch available, screened, non-expired units for each compatible group
4. Score each candidate unit using the formula above
5. Sort all candidates by priority score (descending)
6. Return top N units matching the quantity requested
7. Issue and mark units as `Issued`, update request status

**Compatibility Matrix** (stored in DB, fully configurable):
- O- → Universal Donor (can donate to all)
- AB+ → Universal Recipient (can receive from all)
- Exact blood group match = highest score (10/10)

---

## 🛠️ Installation

### Requirements
- PHP 7.4+ (PDO, MySQLi extensions)
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite
- XAMPP / WAMP / LAMP stack (for local development)

### Step 1 – Clone / Copy Files
```bash
# Copy the bloodbank/ folder to your web root
cp -r bloodbank/ /var/www/html/bloodbank
# Or for XAMPP:
cp -r bloodbank/ C:/xampp/htdocs/bloodbank
```

### Step 2 – Create Database
```sql
-- In phpMyAdmin or MySQL CLI:
CREATE DATABASE lugga_bloodbank CHARACTER SET utf8mb4;
```

Then import the schema:
```bash
mysql -u root -p lugga_bloodbank < bloodbank/database.sql
```

### Step 3 – Configure Connection
Edit `bloodbank/config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');            // Your MySQL password
define('DB_NAME', 'lugga_bloodbank');
define('APP_URL', 'http://localhost/bloodbank');
```

### Step 4 – Set Permissions
```bash
chmod -R 755 bloodbank/
chmod -R 777 bloodbank/assets/   # if storing uploads
```

### Step 5 – Access the System
Open your browser and go to:
```
http://localhost/bloodbank/
```

---

## 🔐 Default Login Credentials

| Username | Password | Role |
|----------|----------|------|
| `admin` | `password` | System Administrator |
| `dr.lugga` | `password` | Doctor |
| `lab.tech` | `password` | Lab Technician |
| `nurse.joy` | `password` | Nurse |

> ⚠️ **Change all passwords immediately after first login in production!**

---

## 📁 File Structure

```
bloodbank/
├── index.php                  ← Login page
├── config.php                 ← DB config & constants
├── database.sql               ← Full schema + seed data
├── README.md
├── includes/
│   ├── functions.php          ← Database, Auth, Algorithm, InventoryManager classes
│   ├── header.php             ← Navigation sidebar + topbar template
│   └── footer.php             ← JS utilities + closing tags
└── pages/
    ├── dashboard.php          ← Main dashboard
    ├── donors.php             ← Donor management
    ├── donor_eligibility.php  ← Eligibility screening
    ├── donations.php          ← Donation recording
    ├── inventory.php          ← Blood unit inventory
    ├── screening.php          ← Lab screening panel
    ├── stock_status.php       ← Stock overview + compat matrix
    ├── patients.php           ← Patient registry
    ├── requests.php           ← Blood request workflow
    ├── distribution.php       ← 🧬 Smart distribution algorithm
    ├── crossmatch.php         ← Crossmatch testing
    ├── reports.php            ← Analytics & charts
    ├── audit_log.php          ← Activity audit trail
    ├── users.php              ← User & role management
    ├── notifications.php      ← System alerts
    ├── settings.php           ← System configuration
    └── logout.php             ← Session logout
```

---

## 🗄️ Database Tables

| Table | Purpose |
|-------|---------|
| `users` | Staff accounts & roles |
| `donors` | Donor registry |
| `donations` | Donation records |
| `blood_units` | Inventory units (with screening status) |
| `patients` | Patient/recipient registry |
| `blood_requests` | Request workflow |
| `blood_distributions` | Issuance & transfusion records |
| `blood_compatibility` | Compatibility matrix (donor × recipient) |
| `lab_tests` | Individual test results |
| `stock_alerts` | Min/critical thresholds per group |
| `notifications` | System notification queue |
| `activity_logs` | Full audit trail |
| `clinic_settings` | Key-value settings store |

---

## 🔒 Security Features

- Password hashing with `bcrypt` (PHP `password_hash`)
- PDO prepared statements (SQL injection prevention)
- Session-based authentication with configurable timeout
- Role-based access control (RBAC) for all pages
- Input sanitization via `htmlspecialchars` + `strip_tags`
- Full audit trail for all critical actions
- CSRF protection on all POST forms

---

## 📊 Blood Component Shelf Life

| Component | Storage Temp | Shelf Life |
|-----------|-------------|-----------|
| Whole Blood | 2–6°C | 35 days |
| Packed Red Cells | 2–6°C | 42 days |
| Fresh Frozen Plasma | ≤ −18°C | 12 months |
| Platelets | 20–24°C | 5 days |
| Cryoprecipitate | ≤ −18°C | 12 months |

---

## 📞 Support

**Lugga Clinic Blood Bank**  
12 Hospital Road, Ibadan, Oyo State, Nigeria  
📧 bloodbank@luggaclinic.ng  
📞 +234 800 000 0000

---

© 2024 Lugga Clinic. Blood Bank Management System v2.0.0
