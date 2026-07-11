<?php
// นำเข้าไฟล์เชื่อมต่อฐานข้อมูล และการจัดการ Session พื้นฐานของระบบ Skill Exchange
require_once 'db.php';
require_once 'auth_session.php';
require_once '.env.php';  // โหลด environment variables ที่เก็บ Gmail credentials อย่างปลอดภัย

// นำเข้า Class ของ PHPMailer มาเตรียมใช้งาน
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// ดึงไฟล์สคริปต์หลักของ PHPMailer เข้ามาทำงานตามโครงสร้างโฟลเดอร์ที่จัดไว้
require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

// กำหนดให้หน้าเว็บเริ่มต้นที่ขั้นตอนที่ 1 (กรอกอีเมล) เสมอหากเพิ่งเข้าหน้านี้มา
if (!isset($_SESSION['reset_step'])) {
    $_SESSION['reset_step'] = 1;
}

$error_msg = null;
$success_msg = null;

// กรณีผู้ใช้กดลิงก์ "ยกเลิก/กลับหน้าล็อกอิน" จะทำการล้างค่าทิ้งทั้งหมดเพื่อป้องกันสิทธิ์ค้างในระบบ
if (isset($_GET['action']) && $_GET['action'] == 'clear') {
    unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_user_id']);
    header("Location: login.php");
    exit();
}

// ==========================================
// ขั้นตอนที่ 1: ตรวจสอบอีเมล และส่งเมลจริงผ่าน Gmail SMTP
// ==========================================
if (isset($_POST['submit_email']) && $_SESSION['reset_step'] == 1) {
    // ป้องกันช่องโหว่ CSRF ด้วย Token ที่มีอยู่แล้วในระบบของคุณ
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error_msg = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } else {
        $email = trim($_POST['email'] ?? '');

        // ค้นหาว่าอีเมลที่กรอกมา มีบัญชีอยู่ในตาราง users ของ Skill Exchange หรือไม่
        $stmt = mysqli_prepare($conn, "SELECT id, fullname FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user) {
            // สุ่มตัวเลข 6 หลักเพื่อทำรหัส OTP กู้คืนรหัสผ่าน
            $code = rand(100000, 999999);

            // เรียกเปิดใช้งาน PHPMailer เพียงรอบเดียวเท่านั้น
            $mail = new PHPMailer(true);

            try {
                // --- ตั้งค่าการเชื่อมต่อเซิร์ฟเวอร์ SMTP ของ Gmail (จาก .env.php) ---
                $mail->isSMTP();
                $mail->Host       = MAIL_HOST;
                $mail->SMTPAuth   = true;

                // บังคับปิดโหมด Debug (ตั้งเป็น 0) เพื่อไม่ให้พ่น Log ตัวอักษรดักหน้าเว็บค้าง
                $mail->SMTPDebug  = 0;

                // ข้อมูลยืนยันตัวตน Gmail และรหัสผ่านแอป 16 หลักถูกเก็บไว้ใน .env.php เพื่อความปลอดภัย
                $mail->Username   = MAIL_USERNAME;
                $mail->Password   = MAIL_PASSWORD;                 

                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = MAIL_PORT;
                $mail->CharSet    = 'UTF-8'; // รองรับภาษาไทยไม่ให้กลายเป็นตัวต่างดาว

                // บังคับข้ามการตรวจสอบใบรับรอง SSL บนเครื่อง Localhost (XAMPP) เพื่อป้องกันปัญหา wrong version number
                $mail->SMTPOptions = array(
                    'ssl' => array(
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true
                    )
                );

                // --- ตั้งค่าผู้ส่งและผู้รับ ---
                $mail->setFrom('natthanon.do@psru.ac.th', 'Skill Exchange Support');
                $mail->addAddress($email, $user['fullname']);

                // --- ตั้งค่าเนื้อหาของอีเมล ---
                $mail->isHTML(true);
                $mail->Subject = 'รหัสยืนยันการกู้คืนรหัสผ่าน - Skill Exchange';

                // ออกแบบหน้าตาเนื้อหาอีเมลส่งเข้ากล่องข้อความให้สวยงาม
                $mail->Body    = "
                    <div style='font-family: Prompt, sans-serif; padding: 25px; border: 1px solid #e2e8f0; border-radius: 12px; max-width: 500px; background-color: #ffffff;'>
                        <h2 style='color: #0a2342; margin-bottom: 5px;'>สวัสดีคุณ {$user['fullname']}</h2>
                        <p style='color: #4a5568;'>ระบบได้รับคำขอกู้คืนรหัสผ่านสำหรับบัญชีผู้ใช้งานระบบ Skill Exchange ของคุณแล้ว</p>
                        <p style='font-weight: bold; color: #1a202c;'>รหัสยืนยันตัวตน OTP 6 หลักของคุณคือ:</p>
                        <div style='background: #f1f5f9; padding: 15px; text-align: center; font-size: 28px; font-weight: bold; letter-spacing: 6px; color: #0056b3; border-radius: 8px; margin: 20px 0;'>
                            {$code}
                        </div>
                        <p style='color: #e53e3e; font-size: 13px; margin-top: 15px;'>* รหัสยืนยันนี้มีอายุการใช้งานจำกัด และเป็นความลับเฉพาะบุคคล โปรดอย่าเปิดเผยรหัสนี้แก่ผู้อื่นเด็ดขาดเพื่อความปลอดภัยของบัญชีคุณ</p>
                    </div>
                ";

                // ยิงอีเมลออกไปจริง
                $mail->send();

                // บันทึกข้อมูลลง Session ชั่วคราวเมื่อส่งสำเร็จ เพื่อใช้ตรวจสอบในหน้าถัดไป
                $_SESSION['reset_email']   = $email;
                $_SESSION['reset_user_id'] = $user['id'];
                $_SESSION['reset_code']    = $code;
                $_SESSION['reset_step']    = 2; // เปลี่ยนสถานะพาข้ามไปขั้นตอนที่ 2 (กรอกรหัส)

                // บังคับรีเฟรชหน้าเว็บ เพื่อให้ PHP โหลดขึ้นฟอร์มขั้นตอนที่ 2 ทันทีโดยไม่ค้างหน้านี้
                header("Location: forgot_password.php");
                exit();

            } catch (Exception $e) {
                // หาก Google ตีกลับหรือส่งไม่สำเร็จจะโยน Error แจ้งเตือนสาเหตุทันที
                $error_msg = "ระบบส่งเมลขัดข้อง: {$mail->ErrorInfo} <br><small>คำแนะนำ: ตรวจสอบรหัสผ่านแอป 16 หลัก หรือสถานะการเชื่อมต่ออินเทอร์เน็ต</small>";
            }
        } else {
            $error_msg = "ไม่พบอีเมลนี้ในระบบการสมัครสมาชิก กรุณาตรวจสอบความถูกต้องอีกครั้ง";
        }
    }
}

// ==========================================
// ขั้นตอนที่ 2: ตรวจสอบรหัส OTP 6 หลัก
// ==========================================
if (isset($_POST['submit_code']) && $_SESSION['reset_step'] == 2) {
    $input_code = trim($_POST['code'] ?? '');

    // ถ้ารหัสที่กรอก ตรงกับตัวเลขที่ระบบสุ่มส่งไปในเมลจริง
    if ($input_code == $_SESSION['reset_code']) {
        $_SESSION['reset_step'] = 3; // ผ่านฉลุย พาไปขั้นตอนที่ 3 ตั้งรหัสใหม่
    } else {
        $error_msg = "รหัสยืนยัน 6 หลักไม่ถูกต้อง กรุณาเช็คในกล่องข้อความอีเมลของคุณอีกครั้ง";
    }
}

// ==========================================
// ขั้นตอนที่ 3: บันทึกรหัสผ่านใหม่ลงฐานข้อมูล
// ==========================================
if (isset($_POST['submit_password']) && $_SESSION['reset_step'] == 3) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // ตรวจสอบความปลอดภัยและความถูกต้องของรหัสผ่านตามเงื่อนไขระบบของคุณ (จากหน้า register.php)
    if (strlen($password) < 8) {
        $error_msg = "รหัสผ่านใหม่ต้องมีอย่างน้อย 8 ตัวอักษร";
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error_msg = "รหัสผ่านต้องมีทั้งตัวอักษรภาษาอังกฤษและตัวเลขผสมกัน";
    } elseif ($password !== $confirm_password) {
        $error_msg = "การยืนยันรหัสผ่านไม่ตรงกัน กรุณาตรวจสอบและกรอกใหม่อีกครั้ง";
    } else {
        // แฮชเข้ารหัสความปลอดภัย (PASSWORD_BCRYPT) แบบเดียวกับตอนสมัครสมาชิกก่อนลงฐานข้อมูล
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);
        $user_id = $_SESSION['reset_user_id'];

        // ใช้ Prepared Statement เพื่ออัปเดตรหัสผ่านใหม่ ป้องกัน SQL Injection 100%
        $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $user_id);

        if (mysqli_stmt_execute($stmt)) {
            // ล้างค่า Session เกี่ยวกับการกู้รหัสทิ้งทั้งหมดทันทีเพื่อความปลอดภัย
            unset($_SESSION['reset_step'], $_SESSION['reset_email'], $_SESSION['reset_code'], $_SESSION['reset_user_id']);

            echo "<script>
                alert('เปลี่ยนรหัสผ่านใหม่เรียบร้อยแล้ว! ระบบกำลังนำคุณกลับไปหน้าเข้าสู่ระบบ');
                window.location.href = 'login.php';
            </script>";
            exit();
        } else {
            $error_msg = "เกิดข้อผิดพลาดภายในระบบฐานข้อมูล ไม่สามารถบันทึกรหัสผ่านได้";
        }
        mysqli_stmt_close($stmt);
    }
}

// เจนเนอเรตค่า CSRF Token ป้องกันภัยคุกคามให้กับหน้าเว็บ (หากยังไม่มีใน Session)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ลืมรหัสผ่าน - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/forgot_password.css">
</head>
</head>

<body>

    <div class="reset-card">
        <div class="card-header-custom">
            <i class="bi bi-shield-lock-fill"></i>
            <h3 class="fw-bold mt-2" style="color: #0a2342;">กู้คืนรหัสผ่าน</h3>
            <p class="text-muted small">ระบบยืนยันตัวตนแบบ 3 ขั้นตอนผ่านอีเมลจริง</p>
        </div>

        <div class="card-body-custom">
            <?php if ($error_msg): ?>
                <div class="alert alert-danger small d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <div><?php echo $error_msg; ?></div>
                </div>
            <?php endif; ?>

            <?php if ($_SESSION['reset_step'] == 1): ?>
                <div class="text-center"><span class="step-indicator">ขั้นตอนที่ 1 จาก 3: รับรหัส OTP</span></div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ระบุอีเมลที่คุณใช้สมัครสมาชิก</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-envelope text-primary"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="yourmail@example.com" required>
                        </div>
                    </div>
                    <button type="submit" name="submit_email" class="btn btn-custom w-100 mb-2">
                        ส่งรหัสยืนยันเข้าอีเมล <i class="bi bi-send-fill ms-1"></i>
                    </button>
                </form>

            <?php elseif ($_SESSION['reset_step'] == 2): ?>
                <div class="text-center"><span class="step-indicator">ขั้นตอนที่ 2 จาก 3: ยืนยันรหัส OTP</span></div>

                <p class="text-muted text-center small mb-4">
                    ระบบได้ส่งรหัสยืนยัน 6 หลักไปที่อีเมลของคุณเรียบร้อยแล้ว<br>
                    <strong>(<?php echo htmlspecialchars($_SESSION['reset_email']); ?>)</strong><br>
                    <span class="text-danger small">*โปรดเช็คทั้งในกล่องจดหมาย และจดหมายขยะ (Spam)</span>
                </p>

                <form method="post">
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted text-center d-block">กรอกรหัสยืนยันที่ได้รับ</label>
                        <input type="text" name="code" class="form-control text-center bg-light fw-bold fs-4" style="letter-spacing: 5px;" maxlength="6" placeholder="******" required autocomplete="off">
                    </div>
                    <button type="submit" name="submit_code" class="btn btn-custom w-100 mb-2">
                        ตรวจสอบรหัสตัวตน <i class="bi bi-shield-check ms-1"></i>
                    </button>
                </form>

            <?php elseif ($_SESSION['reset_step'] == 3): ?>
                <div class="text-center"><span class="step-indicator">ขั้นตอนที่ 3 จาก 3: ตั้งรหัสผ่านใหม่</span></div>
                <form method="post">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">รหัสผ่านใหม่ของคุณ</label>
                        <input type="password" name="password" class="form-control" placeholder="อังกฤษผสมตัวเลขอย่างน้อย 8 ตัว" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-bold text-muted">ยืนยันรหัสผ่านใหม่อีกครั้ง</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="กรอกรหัสให้ตรงกับด้านบน" required>
                    </div>
                    <button type="submit" name="submit_password" class="btn btn-custom w-100 mb-2">
                        อัปเดตรหัสผ่านใหม่เรียบร้อย <i class="bi bi-check-circle-fill ms-1"></i>
                    </button>
                </form>
            <?php endif; ?>

            <div class="text-center mt-3 pt-2 border-top">
                <a href="forgot_password.php?action=clear" class="text-decoration-none text-muted small"><i class="bi bi-box-arrow-left"></i> ยกเลิกกู้รหัสและกลับหน้าเข้าสู่ระบบ</a>
            </div>
        </div>
    </div>

</body>

</html>