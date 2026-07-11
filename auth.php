<?php
// ไฟล์นี้ใช้ตรวจสอบว่าผู้ใช้งาน Login แล้วหรือยัง
// และเป็นที่เดียวที่เรียก session_start()

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ถ้ายังไม่มี Session 'user_id' ให้ redirect กลับไปหน้า Login ทันที
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
?>
