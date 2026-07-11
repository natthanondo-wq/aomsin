<?php
require_once 'db.php';
require_once 'auth.php'; // ตรวจสอบการ Login (auth.php จัดการ session ด้วย)

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['submit'])) {

    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } else {
        $user_id     = (int) $_SESSION['user_id'];
        $skill_name  = trim($_POST['skill_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $type        = $_POST['type'] ?? '';

        // ตรวจสอบค่า type ให้อยู่ในช่วงที่กำหนด
        $allowed_types = ['teach', 'learn'];
        if (!in_array($type, $allowed_types)) {
            $error_msg = "ประเภทการประกาศไม่ถูกต้อง";
        } elseif (empty($skill_name)) {
            $error_msg = "กรุณากรอกชื่อวิชา / ทักษะ";
        } else {
            $sql  = "INSERT INTO skills (user_id, skill_name, description, type) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "isss", $user_id, $skill_name, $description, $type);

                if (mysqli_stmt_execute($stmt)) {
                    $success_redirect = true;
                } else {
                    error_log("Add Skill Error: " . mysqli_error($conn));
                    $error_msg = "เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง";
                }
                mysqli_stmt_close($stmt);
            } else {
                $error_msg = "เกิดข้อผิดพลาดในการเตรียมฐานข้อมูล";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลงประกาศใหม่ - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/add_skill.css">
</head>
</head>
<body>

    <div class="skill-card">
        <div class="skill-header">
            <i class="bi bi-lightbulb-fill"></i>
            <h3 class="fw-bold mt-2" style="color: #333;">ลงประกาศใหม่</h3>
            <p class="text-muted small">แบ่งปันหรือค้นหาทักษะใหม่ ๆ ในชุมชนของคุณ</p>
        </div>

        <div class="skill-body">
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger py-2 small"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>

            <form method="post" id="addSkillForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">ชื่อวิชา / ทักษะ</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-book"></i></span>
                        <input type="text" name="skill_name" class="form-control"
                               placeholder="เช่น กีตาร์, Excel, ทำอาหาร" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ประเภทการประกาศ</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-arrow-left-right"></i></span>
                        <select name="type" class="form-select" required>
                            <option value="teach">ฉันถนัด อยากสอน (รับสอน)</option>
                            <option value="learn">ฉันไม่เป็น อยากเรียน (หาคนสอน)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">รายละเอียดเพิ่มเติม</label>
                    <textarea name="description" class="form-control" rows="4"
                              placeholder="อธิบายเพิ่มเติม เช่น ระดับพื้นฐาน หรือวันเวลาที่สะดวก..."></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" name="submit" id="addSkillBtn" class="btn btn-post w-100 mb-3">
                        <i class="bi bi-megaphone-fill me-2"></i> โพสต์ประกาศทันที
                    </button>
                    <a href="dashboard.php" class="btn btn-cancel">
                        <i class="bi bi-x-circle me-1"></i> ยกเลิกและกลับหน้าหลัก
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/app.js"></script>
    <script>
        initFormLoadingState('addSkillForm', 'addSkillBtn', 'กำลังโพสต์...');

        <?php if (isset($success_redirect)): ?>
        showToastAndRedirect('ลงประกาศเรียบร้อย!', 'success', 'dashboard.php');
        <?php endif; ?>
    </script>
</body>
</html>
