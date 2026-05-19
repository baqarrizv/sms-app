# 📨 SMS Manager — Setup Guide

## Requirements / Zarooriyaat
- PHP 8.0+
- MySQL 8.0+
- ZipArchive PHP extension (Excel files ke liye — usually enabled hota hai)

---

## Installation Steps / Setup Kaise Karein

### Step 1 — Files Copy Karein
Is `sms-app` folder ko apne server ke web root mein copy karein:
- XAMPP: `C:/xampp/htdocs/sms-app/`
- WAMP: `C:/wamp64/www/sms-app/`
- Linux: `/var/www/html/sms-app/`

### Step 2 — Database Config
`includes/config.php` file mein apni DB settings dalein:
```php
define('DB_HOST', 'localhost');  // aapka DB host
define('DB_USER', 'root');       // DB username
define('DB_PASS', '');           // DB password
define('DB_NAME', 'sms_app');    // DB naam
```

### Step 3 — Uploads Folder Permission
```bash
chmod 755 uploads/
```

### Step 4 — Database Setup
Browser mein yeh URL open karein:
```
http://localhost/sms-app/setup.php
```
Yeh automatically:
- `sms_app` database banayega
- `users` aur `sms` tables banayega
- Default admin user insert karega

### Step 5 — Login
```
URL:      http://localhost/sms-app/
Email:    admin@gmail.com
Password: admin123
```

---

## File Structure / Folders
```
sms-app/
├── index.php         → Redirect (login/dashboard)
├── login.php         → Login page
├── logout.php        → Logout
├── dashboard.php     → File upload + records list
├── insert-sms.php    → Selected records insert handler
├── sms-list.php      → SMS table records list
├── clear-file.php    → Uploaded file clear
├── setup.php         → Database setup (1 baar chalao)
├── includes/
│   └── config.php    → DB config + helper functions
└── uploads/          → Temporary uploaded files
```

---

## Google Drive CSV/Excel Format
Aapki file mein yeh columns hone chahiye (case-insensitive):

| phone / number / mobile | msg / message / sms / text |
|--------------------------|----------------------------|
| 03001234567              | Aapka salam!               |
| 03007654321              | Test message hai            |

---

## SMS Table Fields
| Column           | Type                              | Description              |
|------------------|-----------------------------------|--------------------------|
| id               | INT AUTO_INCREMENT                | Primary key              |
| msg              | TEXT                              | SMS message              |
| number           | VARCHAR(20)                       | Phone number             |
| current_status   | ENUM(queued, processing, done)    | Processing status        |
| sms_reference_id | VARCHAR(100)                      | External SMS API ref ID  |
| status           | ENUM(pending, sent, failed)       | Delivery status          |
| activity_by      | INT (FK → users.id)               | Kis user ne insert kiya  |
| activity_at      | TIMESTAMP                         | Kab insert hua           |

---

## Future SMS API Integration
`insert-sms.php` mein yeh code add karein apni SMS API ke saath:
```php
// After insert, send SMS via API
$smsResponse = sendSMS($phone, $msg);
$db->prepare("UPDATE sms SET sms_reference_id=?, status='sent' WHERE id=?")
   ->execute([$smsResponse['ref_id'], $lastId]);
```
