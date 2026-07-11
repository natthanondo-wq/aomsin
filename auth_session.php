<?php
// ไฟล์นี้ใช้สำหรับหน้าที่ "ไม่บังคับ" ให้ล็อกอิน (เช่น login.php, register.php, index.php)
// จะแค่เริ่ม Session โดยไม่ redirect ออกไปไหน

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
