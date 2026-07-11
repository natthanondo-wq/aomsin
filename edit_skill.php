<?php
require_once 'db.php';
require_once 'auth.php'; // auth.php จัดการ session และ redirect แทน

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (!isset($_GET['id'])) {
    header("Location: dashboard.php");
    exit();
}

$skill_id = (int) $_GET['id'];
$user_id  = (int) $_SESSION['user_id'];

// ดึงข้อมูลเดิมออกมาแสดง
$sql  = "SELECT * FROM skills WHERE id = ? AND user_id = ?";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $skill_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$row    = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$row) {
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <title>ไม่พบข้อมูล</title>
    <link rel="stylesheet" href="css/edit_skill.css">
</head>
</head>
<body>

    <div class="edit-card">
        <div class="edit-header">
            <i class="bi bi-pencil-square"></i>
            <h3 class="fw-bold mt-2" style="color: #333;">แก้ไขประกาศของคุณ</h3>
            <p class="text-muted small">ปรับปรุงข้อมูลทักษะของคุณให้เป็นปัจจุบัน</p>
        </div>

        <div class="edit-body">
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger py-2 small border-0 shadow-sm mb-3">
                    <i class="bi bi-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form method="post" id="editSkillForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">หัวข้อวิชา / ทักษะ</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-tag"></i></span>
                        <input type="text" name="skill_name" class="form-control"
                               value="<?php echo htmlspecialchars($row['skill_name']); ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ประเภท</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-layers"></i></span>
                        <select name="type" class="form-select">
                            <option value="teach" <?php echo ($row['type'] == 'teach') ? 'selected' : ''; ?>>🎓 รับสอน (ฉันมีความรู้)</option>
                            <option value="learn" <?php echo ($row['type'] == 'learn') ? 'selected' : ''; ?>>🙋‍♂️ อยากเรียน (ฉันหาคนสอน)</option>
                        </select>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">รายละเอียดเพิ่มเติม</label>
                    <textarea name="description" class="form-control" rows="5"
                              placeholder="ระบุรายละเอียดการสอนหรือสิ่งที่คุณต้องการเรียนรู้..."><?php echo htmlspecialchars($row['description']); ?></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" name="update_skill" id="editSkillBtn" class="btn btn-update w-100 mb-3 shadow-sm">
                        <i class="bi bi-check2-circle me-2"></i> บันทึกการเปลี่ยนแปลง
                    </button>
                    <a href="dashboard.php" class="btn btn-back">
                        <i class="bi bi-arrow-left me-1"></i> ยกเลิกและกลับหน้าหลัก
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="assets/app.js"></script>
    <script>
        initFormLoadingState('editSkillForm', 'editSkillBtn', 'กำลังบันทึก...');

        <?php if (isset($success_redirect)): ?>
        showToastAndRedirect('อัปเดตข้อมูลเรียบร้อยแล้ว!', 'success', 'dashboard.php');
        <?php endif; ?>
    </script>
</body>
</html>
