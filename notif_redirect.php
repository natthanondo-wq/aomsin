<?php
require_once 'db.php';
require_once 'auth.php';

$my_id = (int) $_SESSION['user_id'];
$notif_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($notif_id <= 0) {
    header("Location: dashboard.php");
    exit();
}

// ดึงข้อมูลแจ้งเตือน พร้อมตรวจสอบว่าเป็นของผู้ใช้คนนี้จริง (ป้องกันดูแจ้งเตือนคนอื่น)
$stmt = mysqli_prepare($conn, "SELECT * FROM notifications WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $notif_id, $my_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$notif = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$notif) {
    header("Location: dashboard.php");
    exit();
}

// อัปเดตว่าอ่านแล้ว
$stmt_update = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
mysqli_stmt_bind_param($stmt_update, "ii", $notif_id, $my_id);
mysqli_stmt_execute($stmt_update);
mysqli_stmt_close($stmt_update);

// Redirect ไปยังหน้าที่เกี่ยวข้องตามประเภทการแจ้งเตือน
switch ($notif['type']) {
    case 'chat':
        $target = !empty($notif['sender_id']) ? "chat.php?user_id=" . (int) $notif['sender_id'] : "chat.php";
        header("Location: " . $target);
        break;

    case 'review':
        header("Location: profile.php");
        break;

    default:
        header("Location: dashboard.php");
        break;
}
exit();
?>
