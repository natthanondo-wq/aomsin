<?php
require_once 'auth_session.php'; // เริ่ม session
session_destroy(); // ล้างข้อมูล Session ทั้งหมด
header("Location: index.php");
exit();
?>
