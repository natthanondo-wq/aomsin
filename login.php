<?php
require_once 'db.php';
require_once 'auth_session.php'; // เริ่ม session โดยไม่ redirect (เพราะหน้านี้ยังไม่ล็อกอิน)

// ถ้าผู้ใช้ล็อกอินอยู่แล้ว ให้ redirect ไปหน้า Dashboard ทันที
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Rate Limiting: ป้องกันการสุ่มรหัสผ่าน (Brute-force) ---
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_lockout_until'] = 0;
}

$is_locked_out = $_SESSION['login_lockout_until'] > time();

if (isset($_POST['login']) && $is_locked_out) {
    $wait_seconds = $_SESSION['login_lockout_until'] - time();
    $error_msg = "คุณพยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารออีก {$wait_seconds} วินาทีแล้วลองใหม่";
} elseif (isset($_POST['login'])) {

    // ตรวจสอบ CSRF Token ก่อนทุกครั้ง
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // ค้นหาผู้ใช้งานในฐานข้อมูล
        $stmt = mysqli_prepare($conn, "SELECT id, username, fullname, password FROM users WHERE username = ?");
        mysqli_stmt_bind_param($stmt, "s", $username);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row    = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // รวม error เป็นข้อความเดียวเพื่อป้องกันการเดา username (Username Enumeration)
        if ($row && password_verify($password, $row['password'])) {
            // ล็อกอินสำเร็จ - รีเซ็ตตัวนับ
            $_SESSION['login_attempts'] = 0;
            $_SESSION['login_lockout_until'] = 0;

            session_regenerate_id(true); // ป้องกัน Session Fixation Attack
            $_SESSION['user_id']  = $row['id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['fullname'] = $row['fullname'];

            // สร้าง CSRF Token ใหม่หลังล็อกอินสำเร็จ
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header("Location: dashboard.php");
            exit();
        } else {
            // ล็อกอินผิด - เพิ่มตัวนับและล็อกถ้าเกิน 5 ครั้ง
            $_SESSION['login_attempts']++;

            if ($_SESSION['login_attempts'] >= 5) {
                // ล็อก 30 วินาที คูณตามจำนวนครั้งที่เกิน (exponential backoff แบบง่าย)
                $lockout_multiplier = $_SESSION['login_attempts'] - 4;
                $_SESSION['login_lockout_until'] = time() + min(30 * $lockout_multiplier, 300); // สูงสุด 5 นาที
                $error_msg = "คุณพยายามเข้าสู่ระบบผิดหลายครั้งเกินไป กรุณารอสักครู่แล้วลองใหม่";
            } else {
                $remaining = 5 - $_SESSION['login_attempts'];
                $error_msg = "ชื่อผู้ใช้งานหรือรหัสผ่านไม่ถูกต้อง (เหลือโอกาสอีก {$remaining} ครั้ง)";
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
    <title>เข้าสู่ระบบ - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/login.css">
</head>

<body>
    <div class="login-card">
        <div class="login-header">
            <i class="bi bi-person-circle"></i>
            <h2 class="fw-bold mt-3" style="color: #333;">Welcome Back</h2>
            <p class="text-muted">เข้าสู่ระบบเพื่อใช้งาน Skill Exchange</p>
        </div>

        <div class="login-body">
            <?php if (isset($error_msg)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <div><?php echo htmlspecialchars($error_msg); ?></div>
                </div>
            <?php endif; ?>

            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                <div class="mb-3">
                    <label class="form-label small fw-bold text-muted">USERNAME</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-person text-primary"></i></span>
                        <input type="text" name="username" class="form-control border-0 bg-light"
                               placeholder="ระบุชื่อผู้ใช้งาน" required autocomplete="username" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold text-muted">PASSWORD</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-0"><i class="bi bi-lock text-primary"></i></span>
                        <input type="password" name="password" class="form-control border-0 bg-light"
                               placeholder="ระบุรหัสผ่าน" required autocomplete="current-password" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    </div>
                    
                    <div class="text-end mt-2">
                        <a href="forgot_password.php" class="small text-decoration-none text-muted">ลืมรหัสผ่านใช่หรือไม่?</a>
                    </div>
                </div>

                <button type="submit" name="login" class="btn btn-login w-100 mb-3" <?php echo $is_locked_out ? 'disabled' : ''; ?>>
                    เข้าสู่ระบบ <i class="bi bi-box-arrow-in-right ms-2"></i>
                </button>
            </form>

            <div class="register-link text-center">
                <span class="text-muted small">ยังไม่มีบัญชีใช่หรือไม่?</span>
                <a href="register.php" class="small">สมัครสมาชิกใหม่</a>
            </div>
        </div>
    </div>
</body>

</html>