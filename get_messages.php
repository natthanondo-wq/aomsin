<?php
require_once 'db.php';
require_once 'auth.php'; // จัดการ session และ redirect ถ้ายังไม่ล็อกอิน

date_default_timezone_set('Asia/Bangkok');

$my_id      = (int) $_SESSION['user_id'];
$partner_id = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;

if ($partner_id <= 0) {
    exit; // ไม่มี partner_id ที่ถูกต้อง ไม่ต้องทำงานต่อ
}

// --- เคลียร์แจ้งเตือนเมื่อดึงแชทผ่าน AJAX (ใช้ Prepared Statement แก้ SQL Injection) ---

// 1. อัปเดตสถานะในตาราง notifications
$sql_clear_notif = "UPDATE notifications SET is_read = 1 
                    WHERE user_id = ? AND sender_id = ? AND type = 'chat'";
$stmt_cn = mysqli_prepare($conn, $sql_clear_notif);
mysqli_stmt_bind_param($stmt_cn, "ii", $my_id, $partner_id);
mysqli_stmt_execute($stmt_cn);
mysqli_stmt_close($stmt_cn);

// 2. อัปเดตสถานะในตาราง messages
$sql_clear_msg = "UPDATE messages SET is_read = 1 
                  WHERE receiver_id = ? AND sender_id = ?";
$stmt_cm = mysqli_prepare($conn, $sql_clear_msg);
mysqli_stmt_bind_param($stmt_cm, "ii", $my_id, $partner_id);
mysqli_stmt_execute($stmt_cm);
mysqli_stmt_close($stmt_cm);

// --- ดึงประวัติข้อความ (ใช้ Prepared Statement) ---
$sql = "SELECT * FROM messages 
        WHERE (sender_id = ? AND receiver_id = ?) 
           OR (sender_id = ? AND receiver_id = ?) 
        ORDER BY created_at ASC";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "iiii", $my_id, $partner_id, $partner_id, $my_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// สร้าง HTML ตอบกลับ
$html = "";
while ($chat = mysqli_fetch_assoc($result)) {
    $class   = ($chat['sender_id'] == $my_id) ? "me" : "partner";
    $time    = date('H:i', strtotime($chat['created_at']));
    $message = htmlspecialchars($chat['message']);
    $html   .= '<div class="msg-row ' . $class . '">
                  <div class="msg-wrapper">
                    <div class="msg-bubble">' . $message . '</div>
                    <span class="chat-time">' . $time . '</span>
                  </div>
                </div>';
}

mysqli_stmt_close($stmt);
echo $html;
?>
