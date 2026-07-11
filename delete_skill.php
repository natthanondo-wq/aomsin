<?php
require_once 'db.php';
require_once 'auth.php';

header('Content-Type: application/json; charset=utf-8');

$skill_id = null;
$csrf_token = null;

// ตรวจสอบว่าเป็น POST (AJAX) หรือ GET (traditional form) request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST: AJAX request with JSON body
    $input = json_decode(file_get_contents('php://input'), true);
    $skill_id = isset($input['id']) ? (int)$input['id'] : null;
    $csrf_token = $input['csrf_token'] ?? null;
} else {
    // GET: Traditional link (backward compatible)
    $skill_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $csrf_token = $_GET['csrf_token'] ?? null;
}

// ตรวจสอบ CSRF Token
if (!isset($csrf_token) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'คำขอไม่ถูกต้อง']);
    exit();
}

if ($skill_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID ประกาศไม่ถูกต้อง']);
    exit();
}

$user_id = (int) $_SESSION['user_id'];

$stmt = mysqli_prepare($conn, "DELETE FROM skills WHERE id = ? AND user_id = ?");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "ii", $skill_id, $user_id);

    if (mysqli_stmt_execute($stmt)) {
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            // สำเร็จ
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                // AJAX: return JSON
                echo json_encode(['success' => true, 'message' => 'ลบประกาศเรียบร้อยแล้ว']);
            } else {
                // GET: redirect แบบเดิม
                header("Location: dashboard.php");
            }
        } else {
            // ไม่พบหรือไม่มีสิทธิ์
            http_response_code(403);
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                echo json_encode(['success' => false, 'message' => 'ไม่พบประกาศนี้ หรือคุณไม่มีสิทธิ์ลบ']);
            } else {
                echo "<script>alert('ไม่พบประกาศนี้ หรือคุณไม่มีสิทธิ์ลบ'); window.location='dashboard.php';</script>";
            }
        }
    } else {
        error_log("Delete Skill Error: " . mysqli_error($conn));
        http_response_code(500);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            echo json_encode(['success' => false, 'message' => 'เกิดข้อผิดพลาดของระบบ']);
        } else {
            echo "<script>alert('เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง'); window.location='dashboard.php';</script>";
        }
    }
    mysqli_stmt_close($stmt);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'ข้อผิดพลาดฐานข้อมูล']);
}
?>

