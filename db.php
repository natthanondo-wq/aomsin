<?php
$servername = "localhost";
$username   = "root";
$password   = ""; // ปกติ XAMPP จะไม่มีรหัสผ่าน
$dbname     = "skill_exchange";

// สร้างการเชื่อมต่อ
$conn = mysqli_connect($servername, $username, $password, $dbname);

// เช็คการเชื่อมต่อ
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// ตั้งค่าภาษาไทยให้แสดงผลถูกต้อง
mysqli_set_charset($conn, "utf8");

// *** หมายเหตุ: ย้าย session_start() ออกจากไฟล์นี้แล้ว
// *** ให้ auth.php เป็นผู้จัดการ Session แต่เพียงผู้เดียว
?>
