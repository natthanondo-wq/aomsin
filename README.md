# Skill Exchange Platform

แพลตฟอร์มแลกเปลี่ยนทักษะออนไลน์ — เชื่อมต่อผู้ที่มีความรู้กับผู้ที่ต้องการเรียนรู้

---

## 🚀 Quick Start (Local Development)

### ข้อกำหนด
- PHP 7.4+ (ที่สนับสนุน MySQLi, OpenSSL)
- MySQL 5.7+
- XAMPP / LAMP / LEMP (หรือเซิร์ฟเวอร์ localhost ที่สามารถรัน PHP)

### ขั้นตอนการติดตั้ง

#### 1. Clone/Extract โปรเจกต์
```bash
# ถ้าดาวน์โหลดเป็น ZIP
unzip skill_exchange_v5.zip
cd skill_exchange_final
```

#### 2. ตั้งค่าฐานข้อมูล
```bash
# สร้างฐานข้อมูล
mysql -u root -p < skill_exchange.sql
# ถ้า MySQL มีรหัสผ่าน ใส่หลังธง -p ตัวอักษรเดียว
# หรือใช้ phpmyadmin Import SQL file เลย
```

#### 3. ตั้งค่า Environment (สำคัญมาก!)
สร้างไฟล์ `.env.php` ในโฟลเดอร์หลัก และใส่ค่าต่อไปนี้:

```php
<?php
// .env.php - เก็บไว้เป็นความลับ ห้ามอัปโหลดขึ้น GitHub!

// Gmail SMTP Configuration (สำหรับฟีเจอร์ forgot password)
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'your-email@gmail.com');  // ✏️ เปลี่ยนเป็นอีเมล Gmail ของคุณ
define('MAIL_PASSWORD', 'xxxx xxxx xxxx xxxx');     // ✏️ App Password 16 หลัก (จาก myaccount.google.com/apppasswords)
define('MAIL_PORT', 587);
define('MAIL_FROM_NAME', 'Skill Exchange System');

// Application URL
define('APP_URL', 'http://localhost/skill_exchange');  // ✏️ เปลี่ยนเป็น URL เซิร์ฟเวอร์จริงในการ deploy
define('APP_DEBUG', true);  // ✏️ เปลี่ยนเป็น false ตอน production
?>
```

**วิธีสร้าง Gmail App Password:**
1. ไปที่ https://myaccount.google.com/apppasswords
2. เลือก Mail และ Windows Computer (หรือ device ที่ใช้)
3. คัดลอก password 16 ตัวอักษร ใส่ลงใน `MAIL_PASSWORD`

#### 4. รันเซิร์ฟเวอร์
```bash
# ใช้ PHP built-in server (development only)
php -S localhost:8000

# หรือใช้ XAMPP / Apache / Nginx ตามปกติ
```

#### 5. เข้าใช้งาน
- ไปที่ `http://localhost:8000` (หรือ `http://localhost/skill_exchange`)
- สมัครสมาชิกใหม่ หรือเข้าล็อกอินด้วยบัญชีทดสอบ

---

## 🔐 Security Notes

### ⚠️ สำคัญมาก
- **ไม่ต้องอัปโหลด `.env.php` ขึ้น GitHub/Public Repository**
  - มี `.gitignore` ป้องกันแล้ว
  - เก็บไฟล์นี้ในเซิร์ฟเวอร์เท่านั้น
  
- **ตั้ง `APP_DEBUG = false` ตอน Production**
  - หากตั้ง true จะเปิดเผย error messages ที่อาจมี sensitive info

- **ใช้ HTTPS ในเซิร์ฟเวอร์จริง**
  - ตั้ง SSL certificate จากผู้ให้บริการ (Let's Encrypt ฟรี)

### Features ที่มี Security
✅ CSRF Token ทั้งระบบ  
✅ SQL Injection Protection (Prepared Statements)  
✅ Password Hashing (bcrypt)  
✅ Rate Limiting (Brute-force Protection)  
✅ File Upload Validation  
✅ XSS Protection (htmlspecialchars)  
✅ Security Headers (.htaccess)  
✅ Session Security (session_regenerate_id)

---

## 📁 Project Structure

```
skill_exchange_final/
├── .env.php                    ← ⚠️ SECRETS (อย่าอัปโหลด Git)
├── .gitignore                  ← Prevent committing secrets
├── .htaccess                   ← Security headers
├── PHPMailer/                  ← Email library
├── assets/
│   └── app.js                  ← Shared UI helpers (SweetAlert2)
├── uploads/                    ← User profile images
│   └── .htaccess              ← Block PHP execution in uploads
├── skill_exchange.sql          ← Database schema
├── db.php                      ← Database connection
├── auth.php, auth_session.php  ← Session management
├── index.php                   ← Homepage
├── login.php, register.php     ← Authentication
├── forgot_password.php         ← Password recovery
├── dashboard.php               ← Main feed
├── chat.php                    ← Messaging
├── profile.php, profile_view.php ← User profiles
├── add_skill.php, edit_skill.php, delete_skill.php ← Skill management
├── edit_profile.php            ← Profile settings
├── style.css                   ← Global styles
├── README.md                   ← This file
└── migration_add_review_constraint.sql ← DB migration
```

---

## 🗄️ Database Migration (สำหรับ Existing Database)

ถ้าคุณมีฐานข้อมูลเดิมแล้วและต้องการเพิ่ม unique constraint บน reviews:

```bash
mysql -u root -p skill_exchange < migration_add_review_constraint.sql
```

---

## 🎯 Main Features

### For Users
- 📝 **Skill Listing** — โพสต์ทักษะที่ต้องการสอน/เรียน
- 🔍 **Search & Filter** — ค้นหาด้วย keyword, ประเภท, เรียงลำดับ
- 💬 **Direct Messaging** — แชทกับผู้ใช้อื่น
- ⭐ **Reviews** — ให้คะแนน & ความเห็น
- 🔔 **Notifications** — real-time alerts (polling ทุก 5 วินาที)
- 📱 **Responsive** — ใช้งานได้บนมือถือ

### Security Features
- Rate limiting (5 attempts → 30-300 sec lockout)
- Password policy (min 8 chars, mix of letters+numbers)
- CSRF protection on all forms
- File upload validation (MIME type, size, dimension)
- XSS protection

---

## 🛠️ Development

### Adding New Features
1. ใช้ Prepared Statements สำหรับ queries ทั้งหมด
2. Validate + Sanitize input (whitelist ค่าที่ยอมรับ)
3. ใช้ `htmlspecialchars()` เมื่อแสดงข้อมูล user
4. เพิ่ม CSRF tokens ในฟอร์ม
5. ใช้ SweetAlert2 แทน `alert()` เพื่อ UX ที่ดีขึ้น

### Using SweetAlert2 in New Forms

```html
<!-- Add form ID -->
<form method="post" id="myForm">
    <!-- form fields -->
    <button type="submit" id="myBtn" name="submit">Submit</button>
</form>

<!-- Load SweetAlert2 and app.js -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="assets/app.js"></script>
<script>
    // Protect against double submit
    initFormLoadingState('myForm', 'myBtn', 'กำลังส่ง...');
    
    // Show success toast + redirect
    <?php if ($success): ?>
    showToastAndRedirect('สำเร็จ!', 'success', 'dashboard.php');
    <?php endif; ?>
</script>
```

---

## 📞 Support / Troubleshooting

### "Cannot find /PHPMailer classes"
✓ ตรวจสอบว่า PHPMailer folder มีใน root directory

### "Gmail App Password ไม่ทำงาน"
✓ ตรวจสอบ:
- 2-Factor Authentication เปิดไว้บน Gmail
- ใช้ App Password แบบ 16 หลัก (ไม่ใช่รหัสผ่านปกติ)
- Email ในโค้ด = Email ที่ได้สร้าง App Password

### "Database connection error"
✓ ตรวจสอบ:
- MySQL service running
- `db.php` ตั้งค่า host/user/password ถูก
- Database `skill_exchange` สร้างแล้ว

---

## 📄 License
MIT License — Free to use for personal/commercial projects

---

## ✨ Credits
Built with PHP, MySQL, Bootstrap 5, SweetAlert2, and PHPMailer

---

**Happy Skill Sharing!** 🚀
