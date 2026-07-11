<?php
/**
 * Environment Configuration - Skill Exchange
 * 
 * ⚠️ IMPORTANT: ไฟล์นี้เก็บข้อมูลลับ (secrets) เช่น Gmail credentials
 * - ไม่ต้องอัปโหลดขึ้น GitHub หรือ public repository
 * - ใส่ .env.php ลงใน .gitignore
 * - เก็บในเซิร์ฟเวอร์เท่านั้น
 * 
 * วิธีตั้ง Gmail App Password:
 * 1. ไปที่ https://myaccount.google.com/apppasswords
 * 2. เลือก Mail และ Windows Computer
 * 3. ก็จะได้ password 16 ตัวอักษร ใส่ลงเว้นวรรค
 */

// ============================================================
// GMAIL SMTP Configuration
// ============================================================
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USERNAME', 'natthanon.do@psru.ac.th');  // ✏️ เปลี่ยนเป็นอีเมล Gmail ของคุณ
define('MAIL_PASSWORD', 'obxzlijghfllbgyt');     // ✏️ เปลี่ยนเป็น App Password 16 หลัก
define('MAIL_PORT', 587);
define('MAIL_FROM_NAME', 'Skill Exchange System');

// ============================================================
// Database Configuration (ถ้าต้องการให้ปลอดภัยมากขึ้น)
// ============================================================
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', '');
// define('DB_NAME', 'skill_exchange');

// ============================================================
// Application Settings
// ============================================================
define('APP_URL', 'http://localhost/skill_exchange');  // ✏️ เปลี่ยนเป็น URL เซิร์ฟเวอร์จริง
define('APP_DEBUG', false);  // ตั้งเป็น true เฉพาะ development เท่านั้น
?>
