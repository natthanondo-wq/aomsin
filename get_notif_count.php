<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$my_id = (int) $_SESSION['user_id'];

// จำนวนแจ้งเตือนที่ยังไม่ได้อ่าน
$stmt1 = mysqli_prepare($conn, "SELECT COUNT(id) as c FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt1, "i", $my_id);
mysqli_stmt_execute($stmt1);
$notif_count = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt1))['c'];
mysqli_stmt_close($stmt1);

// จำนวนข้อความที่ยังไม่ได้อ่าน
$stmt2 = mysqli_prepare($conn, "SELECT COUNT(id) as c FROM messages WHERE receiver_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt2, "i", $my_id);
mysqli_stmt_execute($stmt2);
$unread_chat = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2))['c'];
mysqli_stmt_close($stmt2);

echo json_encode([
    'notif_count'  => (int) $notif_count,
    'unread_chat'  => (int) $unread_chat,
]);
?>
