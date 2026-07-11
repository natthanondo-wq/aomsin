<?php
require_once 'db.php';
require_once 'auth.php'; // auth.php จัดการ session และ redirect แทน

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id  = (int) $_SESSION['user_id'];
$msg      = "";
$msg_type = "";

// --- บันทึกข้อมูล ---
if (isset($_POST['save_profile'])) {

    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $msg      = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
        $msg_type = "danger";
    } else {
        $fullname        = trim($_POST['fullname'] ?? '');
        $email           = trim($_POST['email'] ?? '');
        $bio             = trim($_POST['bio'] ?? '');
        $personal_skills = trim($_POST['personal_skills'] ?? '');
        $experience      = trim($_POST['experience'] ?? '');
        $expertise_level = $_POST['expertise_level'] ?? 'Beginner';
        $portfolio_link  = trim($_POST['portfolio_link'] ?? '');

        // ตรวจสอบค่า expertise_level
        $allowed_levels = ['Beginner', 'Intermediate', 'Expert'];
        if (!in_array($expertise_level, $allowed_levels)) {
            $expertise_level = 'Beginner';
        }

        // ตรวจสอบ email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg      = "รูปแบบอีเมลไม่ถูกต้อง";
            $msg_type = "danger";
        } else {
            // อัปเดตข้อมูล Text
            $sql_update = "UPDATE users SET fullname = ?, email = ?, bio = ?, personal_skills = ?, experience = ?, expertise_level = ?, portfolio_link = ? WHERE id = ?";
            $stmt       = mysqli_prepare($conn, $sql_update);
            mysqli_stmt_bind_param($stmt, "sssssssi", $fullname, $email, $bio, $personal_skills, $experience, $expertise_level, $portfolio_link, $user_id);

            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['fullname'] = $fullname;
                $msg                  = "บันทึกข้อมูลเรียบร้อยแล้ว";
                $msg_type             = "success";
            } else {
                $msg      = "เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง";
                $msg_type = "danger";
                error_log("Edit Profile Error: " . mysqli_error($conn));
            }
            mysqli_stmt_close($stmt);

            // จัดการอัปโหลดรูปภาพ
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == UPLOAD_ERR_OK) {
                $allowed_ext  = ['jpg', 'jpeg', 'png', 'gif'];
                $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];
                $filesize     = $_FILES['profile_image']['size'];

                // ตรวจสอบชื่อไฟล์ดิบ ป้องกัน null byte และ double extension (เช่น shell.php.jpg)
                $raw_name = $_FILES['profile_image']['name'];
                $raw_name = str_replace(chr(0), '', $raw_name); // ตัด null byte
                $ext      = strtolower(pathinfo($raw_name, PATHINFO_EXTENSION));

                // ตรวจสอบ MIME type จากเนื้อไฟล์จริง ไม่ใช่จากชื่อไฟล์
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime  = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
                finfo_close($finfo);

                // ตรวจสอบขนาดภาพจริงด้วย getimagesize เพื่อยืนยันว่าเป็นไฟล์รูปภาพจริง ไม่ใช่ไฟล์อันตรายที่ปลอมนามสกุล
                $image_info = @getimagesize($_FILES['profile_image']['tmp_name']);

                if (!in_array($ext, $allowed_ext) || !in_array($mime, $allowed_mime) || $image_info === false) {
                    $msg      = "ประเภทไฟล์ไม่ถูกต้อง รองรับ JPG, PNG, GIF เท่านั้น";
                    $msg_type = "warning";
                } elseif ($filesize > 5 * 1024 * 1024) { // 5MB
                    $msg      = "ไฟล์รูปภาพใหญ่เกินไป (ต้องไม่เกิน 5MB)";
                    $msg_type = "warning";
                } else {
                    // สร้างชื่อไฟล์ใหม่ที่ปลอดภัยทั้งหมด ไม่ใช้ชื่อต้นฉบับเลย
                    $new_filename = "profile_" . $user_id . "_" . bin2hex(random_bytes(8)) . "." . $ext;
                    $upload_dir   = "uploads/";
                    $upload_path  = $upload_dir . $new_filename;

                    // ตรวจสอบว่าโฟลเดอร์ uploads มีอยู่จริง
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path)) {
                        // ลบไฟล์ profile รูปเก่าทิ้ง (ถ้าไม่ใช่ default.png) เพื่อไม่ให้ไฟล์สะสม
                        if (!empty($user['profile_image']) && $user['profile_image'] !== 'default.png') {
                            $old_path = $upload_dir . $user['profile_image'];
                            if (is_file($old_path)) {
                                @unlink($old_path);
                            }
                        }

                        $sql_img  = "UPDATE users SET profile_image = ? WHERE id = ?";
                        $stmt_img = mysqli_prepare($conn, $sql_img);
                        mysqli_stmt_bind_param($stmt_img, "si", $new_filename, $user_id);
                        mysqli_stmt_execute($stmt_img);
                        mysqli_stmt_close($stmt_img);

                        if ($msg_type == "success") {
                            $msg = "บันทึกข้อมูลและอัปโหลดรูปสำเร็จ!";
                        }
                    } else {
                        $msg      = "ไม่สามารถอัปโหลดไฟล์ได้";
                        $msg_type = "danger";
                    }
                }
            }
        }
    }
}

// ดึงข้อมูลล่าสุดมาแสดง
$sql    = "SELECT * FROM users WHERE id = ?";
$stmt   = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user   = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

$current_img = !empty($user['profile_image']) ? "uploads/" . $user['profile_image'] : "uploads/default.png";
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แก้ไขโปรไฟล์ - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/edit_profile.css">
</head>
</head>

<body>
    <div class="edit-card">
        <div class="card-header-custom">
            <h4 class="fw-bold text-dark">✏️ แก้ไขโปรไฟล์ส่วนตัว</h4>
            <p class="text-muted small">ข้อมูลเหล่านี้จะช่วยให้การแลกเปลี่ยนทักษะของคุณง่ายขึ้น</p>
        </div>

        <div class="p-4 pt-0">
            <?php if ($msg): ?>
                <div class="alert alert-<?php echo $msg_type; ?> text-center py-2 mb-4 border-0 shadow-sm">
                    <?php echo htmlspecialchars($msg); ?>
                </div>
            <?php endif; ?>

            <form method="post" enctype="multipart/form-data">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="profile-pic-wrapper">
                    <img src="<?php echo htmlspecialchars($current_img); ?>" id="preview" class="profile-pic">
                    <label for="fileInput" class="upload-btn">
                        <i class="bi bi-camera-fill"></i>
                    </label>
                    <input type="file" id="fileInput" name="profile_image" class="d-none"
                           accept="image/jpeg,image/png,image/gif" onchange="loadFile(event)">
                </div>

                <div class="section-title">ข้อมูลพื้นฐาน</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted small">ชื่อ-นามสกุล</label>
                        <input type="text" name="fullname" class="form-control"
                               value="<?php echo htmlspecialchars($user['fullname']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted small">อีเมล</label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                </div>

                <div class="section-title">ข้อมูลความสามารถ & แนะนำตัว</div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">แนะนำตัว (Bio)</label>
                    <textarea name="bio" class="form-control" rows="2"
                              placeholder="บอกความเป็นตัวคุณสั้นๆ..."><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-muted small">ทักษะที่มี (Skills)</label>
                    <input type="text" name="personal_skills" class="form-control"
                           placeholder="เช่น PHP, ร้องเพลง, ทำอาหาร (คั่นด้วยจุลภาค ,)"
                           value="<?php echo htmlspecialchars($user['personal_skills'] ?? ''); ?>">
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted small">ระดับความเชี่ยวชาญ</label>
                        <select name="expertise_level" class="form-select">
                            <option value="Beginner"     <?php echo ($user['expertise_level'] == 'Beginner')     ? 'selected' : ''; ?>>Beginner (มือใหม่)</option>
                            <option value="Intermediate" <?php echo ($user['expertise_level'] == 'Intermediate') ? 'selected' : ''; ?>>Intermediate (ระดับกลาง)</option>
                            <option value="Expert"       <?php echo ($user['expertise_level'] == 'Expert')       ? 'selected' : ''; ?>>Expert (ผู้เชี่ยวชาญ)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-muted small">ลิงก์ผลงาน (Portfolio)</label>
                        <input type="url" name="portfolio_link" class="form-control"
                               placeholder="https://..."
                               value="<?php echo htmlspecialchars($user['portfolio_link'] ?? ''); ?>">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold text-muted small">ประสบการณ์สอน / การทำงาน</label>
                    <textarea name="experience" class="form-control" rows="2"
                              placeholder="คุณเคยสอนหรือทำอะไรมาบ้าง..."><?php echo htmlspecialchars($user['experience'] ?? ''); ?></textarea>
                </div>

                <div class="text-center">
                    <button type="submit" name="save_profile" class="btn btn-save shadow-sm">
                        บันทึกการเปลี่ยนแปลงทั้งหมด
                    </button>
                    <br>
                    <a href="dashboard.php" class="text-decoration-none text-muted small mt-2 d-inline-block">
                        <i class="bi bi-arrow-left"></i> ยกเลิกและกลับหน้าหลัก
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        var loadFile = function(event) {
            var reader = new FileReader();
            reader.onload = function() {
                document.getElementById('preview').src = reader.result;
            };
            if (event.target.files[0]) {
                reader.readAsDataURL(event.target.files[0]);
            }
        };
    </script>
</body>
</html>
