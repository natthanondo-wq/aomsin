<?php
require_once 'db.php';
require_once 'auth_session.php';

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_POST['register'])) {

    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $fullname = trim($_POST['fullname'] ?? '');
        $email    = trim($_POST['email'] ?? '');

        if (empty($username) || empty($password) || empty($fullname) || empty($email)) {
            $error_msg = "กรุณากรอกข้อมูลให้ครบถ้วน";
        } elseif (!preg_match('/^[a-zA-Z0-9_]{4,20}$/', $username)) {
            $error_msg = "Username ต้องเป็นภาษาอังกฤษ/ตัวเลข/ขีดล่าง ความยาว 4-20 ตัวอักษร";
        } elseif (strlen($password) < 8) {
            $error_msg = "รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร";
        } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
            $error_msg = "รหัสผ่านต้องมีทั้งตัวอักษรและตัวเลขผสมกัน";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "รูปแบบอีเมลไม่ถูกต้อง";
        } else {
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // เช็ค Username ซ้ำ
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE username = ?");
            mysqli_stmt_bind_param($stmt_check, "s", $username);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);

            if (mysqli_stmt_num_rows($stmt_check) > 0) {
                $error_msg = "Username นี้ถูกใช้งานแล้ว";
            } else {
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (username, password, fullname, email) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_insert, "ssss", $username, $password_hash, $fullname, $email);

                if (mysqli_stmt_execute($stmt_insert)) {
                    $register_success = true;
                } else {
                    error_log("Register Error: " . mysqli_error($conn));
                    $error_msg = "เกิดข้อผิดพลาดของระบบ กรุณาลองใหม่อีกครั้ง";
                }
                mysqli_stmt_close($stmt_insert);
            }
            mysqli_stmt_close($stmt_check);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างบัญชีใหม่ - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/register.css">
</head>
</head>

<body>
    <div class="reg-card">
        <div class="reg-header">
            <i class="bi bi-person-plus-fill"></i>
            <h3 class="fw-bold" style="color: #2c3e50;">สร้างบัญชีผู้ใช้</h3>
            <p class="text-muted small">ร่วมเป็นส่วนหนึ่งของสังคมการแลกเปลี่ยนทักษะ</p>
        </div>

        <div class="reg-body">
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger border-0 small py-2 mb-3">
                    <i class="bi bi-x-circle-fill me-2"></i> <?php echo htmlspecialchars($error_msg); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label">ชื่อผู้ใช้งาน (USERNAME)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-at"></i></span>
                        <input type="text" name="username" class="form-control"
                               placeholder="ภาษาอังกฤษเท่านั้น" required autocomplete="username">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">รหัสผ่าน (PASSWORD)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" name="password" class="form-control"
                               placeholder="อย่างน้อย 8 ตัวอักษร มีตัวอักษร+เลข" required minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="fullname" class="form-control" placeholder="ชื่อจริงของคุณ" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">อีเมล (EMAIL)</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" class="form-control"
                               placeholder="example@email.com" required autocomplete="email">
                    </div>
                </div>

                <button type="submit" name="register" class="btn btn-register w-100 mb-3">
                    ยืนยันการสมัครสมาชิก <i class="bi bi-arrow-right-short fs-5"></i>
                </button>
            </form>

            <div class="login-link text-center">
                <p class="small text-muted mb-0">มีบัญชีผู้ใช้งานอยู่แล้ว? <a href="login.php">เข้าสู่ระบบที่นี่</a></p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if (isset($register_success) && $register_success === true): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สมัครสมาชิกสำเร็จ!',
            text: 'ระบบกำลังพาท่านไปยังหน้าเข้าสู่ระบบ...',
            showConfirmButton: false,
            timer: 2500,
            allowOutsideClick: false
        }).then(() => {
            window.location.href = 'login.php';
        });
    </script>
    <?php endif; ?>
</body>

</html>
