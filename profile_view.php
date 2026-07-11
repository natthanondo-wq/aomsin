<?php

/**
 * Profile View Page - Skill Exchange (With Notification System Integrated)
 */

require_once 'db.php';

// ตรวจสอบการล็อกอิน (auth.php จัดการ session และ redirect)
require_once 'auth.php';

// ตรวจสอบว่ามีการส่ง user_id มาหรือไม่
if (!isset($_GET['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$target_user_id = (int) $_GET['user_id'];
$my_id = (int) $_SESSION['user_id'];

// สร้าง CSRF Token ถ้ายังไม่มี
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- ระบบบันทึกรีวิว + เพิ่มการส่งแจ้งเตือน ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {

    // ตรวจสอบ CSRF Token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $review_error = "คำขอไม่ถูกต้อง กรุณาลองใหม่อีกครั้ง";
    } else {
        $rating    = intval($_POST['rating']);
        $comment   = trim($_POST['comment']);
        $target_id = intval($_POST['target_user_id']);

        // ป้องกันการรีวิวตัวเอง และตรวจสอบค่าคะแนนให้อยู่ในช่วงที่กำหนด
        if ($rating < 1 || $rating > 5) {
            $review_error = "กรุณาเลือกคะแนนระหว่าง 1-5 ดาว";
        } elseif ($target_id === $my_id) {
            $review_error = "คุณไม่สามารถรีวิวตัวเองได้";
        } elseif (empty($comment)) {
            $review_error = "กรุณาเขียนความคิดเห็น";
        } else {
            // ตรวจสอบว่าเคยรีวิวคนนี้ไปแล้วหรือยัง (1 คนรีวิวอีกคนได้แค่ 1 ครั้ง)
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM reviews WHERE reviewer_id = ? AND target_user_id = ?");
            mysqli_stmt_bind_param($stmt_check, "ii", $my_id, $target_id);
            mysqli_stmt_execute($stmt_check);
            mysqli_stmt_store_result($stmt_check);
            $already_reviewed = mysqli_stmt_num_rows($stmt_check) > 0;
            mysqli_stmt_close($stmt_check);

            if ($already_reviewed) {
                // ถ้ารีวิวไปแล้ว ให้อัปเดตรีวิวเดิมแทนการสร้างใหม่ซ้ำ
                $stmt_update_review = mysqli_prepare($conn, "UPDATE reviews SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP WHERE reviewer_id = ? AND target_user_id = ?");
                mysqli_stmt_bind_param($stmt_update_review, "isii", $rating, $comment, $my_id, $target_id);

                if (mysqli_stmt_execute($stmt_update_review)) {
                    echo "<script>alert('อัปเดตรีวิวของคุณเรียบร้อยแล้ว!'); window.location='profile_view.php?user_id=$target_id';</script>";
                    exit();
                } else {
                    $review_error = "เกิดข้อผิดพลาดในการอัปเดตรีวิว กรุณาลองใหม่อีกครั้ง";
                }
                mysqli_stmt_close($stmt_update_review);
            } else {
                $stmt_insert_review = mysqli_prepare($conn, "INSERT INTO reviews (reviewer_id, target_user_id, rating, comment) VALUES (?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_insert_review, "iiis", $my_id, $target_id, $rating, $comment);

                if (mysqli_stmt_execute($stmt_insert_review)) {

                    // ส่งการแจ้งเตือนไปยังผู้รับรีวิว
                    $notif_msg = htmlspecialchars($_SESSION['fullname']) . " ได้เขียนรีวิวให้โปรไฟล์ของคุณ";
                    $stmt_notif = mysqli_prepare($conn, "INSERT INTO notifications (user_id, sender_id, type, message) VALUES (?, ?, 'review', ?)");
                    mysqli_stmt_bind_param($stmt_notif, "iis", $target_id, $my_id, $notif_msg);
                    mysqli_stmt_execute($stmt_notif);
                    mysqli_stmt_close($stmt_notif);

                    echo "<script>alert('ขอบคุณสำหรับรีวิวครับ!'); window.location='profile_view.php?user_id=$target_id';</script>";
                    exit();
                } else {
                    $review_error = "เกิดข้อผิดพลาดในการส่งรีวิว กรุณาลองใหม่อีกครั้ง";
                }
                mysqli_stmt_close($stmt_insert_review);
            }
        }
    }
}
// ------------------------------------------

// 1. ดึงข้อมูล "ฉัน" (สำหรับ Navbar)
$stmt_me = mysqli_prepare($conn, "SELECT fullname, profile_image FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_me, "i", $my_id);
mysqli_stmt_execute($stmt_me);
$me_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_me));
$my_image = !empty($me_data['profile_image']) ? "uploads/" . htmlspecialchars($me_data['profile_image']) : "uploads/default.png";
mysqli_stmt_close($stmt_me);

// [เพิ่มใหม่] ดึงจำนวนแจ้งเตือนที่ยังไม่ได้อ่านของ "ฉัน"
$stmt_unread = mysqli_prepare($conn, "SELECT COUNT(id) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt_unread, "i", $my_id);
mysqli_stmt_execute($stmt_unread);
$unread_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_unread));
$unread_count = $unread_data['unread_count'];
mysqli_stmt_close($stmt_unread);

// [เพิ่มใหม่] ดึงรายการแจ้งเตือนล่าสุด 5 รายการเพื่อแสดงใน Dropdown กระดิ่ง
$stmt_list_notif = mysqli_prepare($conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_bind_param($stmt_list_notif, "i", $my_id);
mysqli_stmt_execute($stmt_list_notif);
$res_list_notif = mysqli_stmt_get_result($stmt_list_notif);

// 2. ดึงข้อมูล "เจ้าของโปรไฟล์นี้"
$stmt_user = mysqli_prepare($conn, "SELECT * FROM users WHERE id = ?");
mysqli_stmt_bind_param($stmt_user, "i", $target_user_id);
mysqli_stmt_execute($stmt_user);
$res_user = mysqli_stmt_get_result($stmt_user);

if (mysqli_num_rows($res_user) == 0) {
    echo "<script>alert('ไม่พบผู้ใช้นี้'); window.location='dashboard.php';</script>";
    exit();
}

$user_data = mysqli_fetch_assoc($res_user);
$target_img = !empty($user_data['profile_image']) ? "uploads/" . htmlspecialchars($user_data['profile_image']) : "uploads/default.png";
mysqli_stmt_close($stmt_user);

// 3. ดึงประกาศทั้งหมดของผู้ใช้นี้
$stmt_skills = mysqli_prepare($conn, "SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC");
mysqli_stmt_bind_param($stmt_skills, "i", $target_user_id);
mysqli_stmt_execute($stmt_skills);
$res_skills = mysqli_stmt_get_result($stmt_skills);

// 4. ดึงข้อมูลคะแนนรีวิวเฉลี่ยและจำนวนรีวิว
$stmt_rating = mysqli_prepare($conn, "SELECT AVG(rating) as avg_rating, COUNT(id) as total_reviews FROM reviews WHERE target_user_id = ?");
mysqli_stmt_bind_param($stmt_rating, "i", $target_user_id);
mysqli_stmt_execute($stmt_rating);
$res_rating = mysqli_stmt_get_result($stmt_rating);
$rating_data = mysqli_fetch_assoc($res_rating);

$avg_rating = $rating_data['avg_rating'] ? round($rating_data['avg_rating'], 1) : 0;
$total_reviews = $rating_data['total_reviews'] ? $rating_data['total_reviews'] : 0;
mysqli_stmt_close($stmt_rating);

// 5. ดึงรายการรีวิวทั้งหมดของผู้ใช้นี้ พร้อมข้อมูลคนรีวิว
$stmt_reviews = mysqli_prepare($conn, "
    SELECT r.*, u.fullname, u.profile_image 
    FROM reviews r 
    JOIN users u ON r.reviewer_id = u.id 
    WHERE r.target_user_id = ? 
    ORDER BY r.created_at DESC
");
mysqli_stmt_bind_param($stmt_reviews, "i", $target_user_id);
mysqli_stmt_execute($stmt_reviews);
$res_reviews = mysqli_stmt_get_result($stmt_reviews);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของ <?php echo htmlspecialchars($user_data['fullname']); ?> - Skill Exchange</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/profile_view.css">
</head>
</head>

<body>

    <nav class="navbar navbar-expand-lg navbar-clean sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="dashboard.php">
                <i class="bi bi-mortarboard-fill me-2"></i>Skill Exchange
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-lg-3">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">
                            <i class="bi bi-house-door me-1"></i> หน้าหลัก
                        </a>
                    </li>

                    <li class="nav-item dropdown me-lg-2">
                        <a class="nav-link position-relative dropdown-toggle no-caret p-2" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill fs-5 text-secondary"></i>
                            <?php if ($unread_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 0.35em 0.5em;">
                                    <?php echo $unread_count; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-3 mt-2" aria-labelledby="notifDropdown" style="width: 300px;">
                            <li class="mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">การแจ้งเตือน</span>
                                <?php if ($unread_count > 0): ?>
                                    <span class="badge bg-light text-primary small">ใหม่ <?php echo $unread_count; ?> รายการ</span>
                                <?php endif; ?>
                            </li>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if (mysqli_num_rows($res_list_notif) > 0): ?>
                                    <?php while ($notif = mysqli_fetch_assoc($res_list_notif)): ?>
                                        <li>
                                            <a class="dropdown-item dropdown-item-notif p-2 text-wrap d-block text-decoration-none <?php echo $notif['is_read'] == 0 ? 'bg-light fw-bold text-dark' : 'text-secondary'; ?>" href="#">
                                                <div class="mb-1"><?php echo htmlspecialchars($notif['message']); ?></div>
                                                <small class="text-muted text-end d-block" style="font-size: 0.75rem;">
                                                    <i class="bi bi-clock me-1"></i><?php echo date('d M H:i', strtotime($notif['created_at'])); ?>
                                                </small>
                                            </a>
                                        </li>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <li class="text-center py-4 text-muted small">
                                        <i class="bi bi-bell-slash d-block fs-3 opacity-25 mb-2"></i>
                                        ยังไม่มีการแจ้งเตือนในขณะนี้
                                    </li>
                                <?php endif; ?>
                            </div>
                        </ul>
                    </li>

                    <li class="nav-item">
                        <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1">
                            <img src="<?php echo $my_image; ?>" class="avatar-header me-2">
                            <span class="text-dark small fw-bold"><?php echo mb_strimwidth($_SESSION['fullname'], 0, 15, '...'); ?></span>
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="profile-cover">
        <div class="container position-relative h-100"></div>
    </div>

    <main class="container mb-5 flex-grow-1">
        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="profile-card-floating">
                    
                    <div class="text-center">
                        <img src="<?php echo $target_img; ?>" class="profile-big-img">
                        <h2 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($user_data['fullname']); ?></h2>
                        
                        <div class="d-flex justify-content-center align-items-center gap-2 mb-3">
                            <p class="text-muted mb-0"><i class="bi bi-patch-check-fill text-primary me-1"></i>สมาชิก Skill Exchange</p>
                            <?php if(!empty($user_data['expertise_level'])): ?>
                                <span class="expertise-badge"><i class="bi bi-award-fill me-1"></i> <?php echo htmlspecialchars($user_data['expertise_level']); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <span class="text-warning fs-5">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $avg_rating) {
                                        echo '<i class="bi bi-star-fill"></i> ';
                                    } elseif ($i - 0.5 <= $avg_rating) {
                                        echo '<i class="bi bi-star-half"></i> ';
                                    } else {
                                        echo '<i class="bi bi-star"></i> ';
                                    }
                                }
                                ?>
                            </span>
                            <span class="text-muted small ms-2 fw-medium">
                                (<?php echo $avg_rating; ?>/5 จาก <?php echo $total_reviews; ?> รีวิว)
                            </span>
                        </div>

                        <div class="d-flex justify-content-center gap-3">
                            <?php if ($target_user_id != $my_id): ?>
                                <a href="chat.php?user_id=<?php echo $target_user_id; ?>" class="btn btn-primary-custom text-decoration-none">
                                    <i class="bi bi-chat-dots-fill me-2"></i>ทักแชทพูดคุย
                                </a>
                                <button type="button" class="btn btn-outline-warning rounded-pill px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#reviewModal">
                                    <i class="bi bi-star-fill me-1"></i> เขียนรีวิว
                                </button>
                            <?php else: ?>
                                <a href="edit_profile.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                                    <i class="bi bi-pencil me-2"></i>แก้ไขโปรไฟล์
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr class="my-5 text-muted opacity-25">

                    <div class="row g-4 text-start">
                        <div class="col-md-7">
                            <h6 class="fw-bold text-dark"><i class="bi bi-person-lines-fill text-primary me-2"></i> แนะนำตัว</h6>
                            <p class="text-secondary small mb-4" style="line-height: 1.7;">
                                <?php echo !empty($user_data['bio']) ? nl2br(htmlspecialchars($user_data['bio'])) : '<span class="text-muted fst-italic">ผู้ใช้นี้ยังไม่ได้เขียนแนะนำตัว</span>'; ?>
                            </p>

                            <h6 class="fw-bold text-dark"><i class="bi bi-briefcase-fill text-primary me-2"></i> ประสบการณ์</h6>
                            <p class="text-secondary small mb-0" style="line-height: 1.7;">
                                <?php echo !empty($user_data['experience']) ? nl2br(htmlspecialchars($user_data['experience'])) : '<span class="text-muted fst-italic">ยังไม่ได้ระบุประสบการณ์</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-5 border-start-md ps-md-4">
                            <h6 class="fw-bold text-dark"><i class="bi bi-tools text-primary me-2"></i> ทักษะความสามารถ</h6>
                            <div class="mb-4">
                                <?php 
                                if (!empty($user_data['personal_skills'])) {
                                    $skills_array = explode(',', $user_data['personal_skills']);
                                    foreach ($skills_array as $skill) {
                                        echo '<span class="skill-badge-custom">' . htmlspecialchars(trim($skill)) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted fst-italic small">ยังไม่ได้เพิ่มทักษะ</span>';
                                }
                                ?>
                            </div>

                            <h6 class="fw-bold text-dark"><i class="bi bi-link-45deg text-primary me-2"></i> ผลงาน (Portfolio)</h6>
                            <?php if (!empty($user_data['portfolio_link'])): ?>
                                <a href="<?php echo htmlspecialchars($user_data['portfolio_link']); ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill mt-1 fw-bold">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> ดูผลงานคลิกที่นี่
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic small d-block mt-1">ไม่มีลิงก์ผลงาน</span>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="d-flex align-items-center mb-4 mt-5 justify-content-center">
            <h4 class="fw-bold m-0 text-dark text-center">
                <i class="bi bi-collection text-primary me-2"></i>ประกาศของ <?php echo htmlspecialchars(explode(' ', $user_data['fullname'])[0]); ?>
            </h4>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-9">
                <div class="row g-4">
                    <?php if (mysqli_num_rows($res_skills) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($res_skills)): ?>
                            <?php
                            $row['fullname'] = $user_data['fullname'];

                            if ($row['type'] == 'teach') {
                                $badge_class = 'badge-teach';
                                $badge_text = '<i class="bi bi-mortarboard-fill me-1"></i> รับสอน';
                            } else {
                                $badge_class = 'badge-learn';
                                $badge_text = '<i class="bi bi-hand-index-thumb-fill me-1"></i> อยากเรียน';
                            }
                            ?>
                            <div class="col-12 col-md-6">
                                <div class="card-announcement p-4 d-flex flex-column">

                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <span class="status-badge <?php echo $badge_class; ?>">
                                            <?php echo $badge_text; ?>
                                        </span>
                                        <small class="text-muted fw-bold">
                                            <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                                        </small>
                                    </div>

                                    <h5 class="card-title-custom text-truncate">
                                        <?php echo htmlspecialchars($row['skill_name'], ENT_QUOTES, 'UTF-8'); ?>
                                    </h5>

                                    <p class="card-desc-custom flex-grow-1">
                                        <?php echo htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8'); ?>
                                    </p>

                                    <div class="card-actions">
                                        <button class="btn btn-action-ghost"
                                            data-bs-toggle="modal" data-bs-target="#detailModal"
                                            data-info='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>
                                            <i class="bi bi-eye me-1"></i> ดูรายละเอียด
                                        </button>
                                        <?php if ($target_user_id == $my_id): ?>
                                            <a href="edit_skill.php?id=<?php echo $row['id']; ?>" class="btn btn-light text-secondary border px-3"><i class="bi bi-pencil-fill"></i></a>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-5">
                            <div class="bg-white p-5 rounded-4 shadow-sm border border-light">
                                <i class="bi bi-inbox text-muted opacity-25" style="font-size: 3rem;"></i>
                                <h6 class="text-muted mt-3">ยังไม่มีประกาศ</h6>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center mb-4 mt-5 justify-content-center">
            <h4 class="fw-bold m-0 text-dark text-center">
                <i class="bi bi-chat-right-quote-fill text-primary me-2"></i>รีวิวจากผู้ใช้งาน
            </h4>
        </div>

        <div class="row justify-content-center mb-5">
            <div class="col-lg-9">
                <div class="bg-white p-4 p-md-5 rounded-4 shadow-sm border border-light">
                    <?php if (mysqli_num_rows($res_reviews) > 0): ?>
                        <?php 
                        while ($review = mysqli_fetch_assoc($res_reviews)): 
                            $reviewer_img = !empty($review['profile_image']) ? "uploads/" . htmlspecialchars($review['profile_image']) : "uploads/default.png";
                        ?>
                            <div class="d-flex mb-4 border-bottom pb-4">
                                <img src="<?php echo $reviewer_img; ?>" class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover; border: 2px solid #f1f5f9;">
                                <div class="w-100">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <h6 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($review['fullname']); ?></h6>
                                        <small class="text-muted fw-medium"><?php echo date('d M Y', strtotime($review['created_at'])); ?></small>
                                    </div>
                                    <div class="mb-2">
                                        <span class="text-warning" style="font-size: 0.85rem;">
                                            <?php 
                                            for ($i = 1; $i <= 5; $i++) {
                                                echo ($i <= $review['rating']) ? '<i class="bi bi-star-fill"></i> ' : '<i class="bi bi-star text-muted opacity-25"></i> ';
                                            }
                                            ?>
                                        </span>
                                    </div>
                                    <p class="text-secondary mb-0" style="line-height: 1.6; font-size: 0.95rem;">
                                        <?php echo nl2br(htmlspecialchars($review['comment'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-square-text text-muted opacity-25" style="font-size: 3rem;"></i>
                            <h6 class="text-muted mt-3 mb-0">ยังไม่มีรีวิวสำหรับผู้ใช้นี้</h6>
                            <p class="text-muted small mt-1">มาเป็นคนแรกที่รีวิวให้ <?php echo htmlspecialchars(explode(' ', $user_data['fullname'])[0]); ?> สิ!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <div class="modal fade" id="detailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 pt-2">
                    <div id="mTypeBadge" class="status-badge d-inline-block mb-3"></div>
                    <h4 class="fw-bold text-dark mb-2" id="mTitle"></h4>
                    <div class="text-muted small mb-4"><i class="bi bi-calendar3 me-2"></i><span id="mDate"></span></div>
                    <div class="bg-light p-4 rounded-4 mb-4">
                        <h6 class="fw-bold text-primary mb-2"><i class="bi bi-info-circle me-2"></i>รายละเอียด</h6>
                        <p id="mDesc" style="white-space: pre-wrap;" class="mb-0 text-secondary"></p>
                    </div>
                    <div class="d-flex align-items-center justify-content-end">
                        <button type="button" class="btn btn-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-content-modern shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-star-fill text-warning me-2"></i>เขียนรีวิวให้ <?php echo htmlspecialchars(explode(' ', $user_data['fullname'])[0]); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body p-4 pt-3">
                        <input type="hidden" name="target_user_id" value="<?php echo $target_user_id; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <?php if (isset($review_error)): ?>
                            <div class="alert alert-danger py-2 small border-0 mb-3">
                                <i class="bi bi-exclamation-circle me-2"></i><?php echo htmlspecialchars($review_error); ?>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">ให้คะแนน (ดาว) <span class="text-danger">*</span></label>
                            <select name="rating" class="form-select rounded-3 bg-light border-0" required>
                                <option value="" disabled selected>-- เลือกคะแนน --</option>
                                <option value="5">⭐⭐⭐⭐⭐ (5) ยอดเยี่ยม</option>
                                <option value="4">⭐⭐⭐⭐ (4) ดีมาก</option>
                                <option value="3">⭐⭐⭐ (3) ปานกลาง</option>
                                <option value="2">⭐⭐ (2) พอใช้</option>
                                <option value="1">⭐ (1) ต้องปรับปรุง</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">ความคิดเห็น <span class="text-danger">*</span></label>
                            <textarea name="comment" class="form-control rounded-3 bg-light border-0" rows="4" placeholder="แบ่งปันประสบการณ์ของคุณ หลังจากแลกเปลี่ยนทักษะกับผู้ใช้นี้..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-0 pt-0 pb-4 pe-4">
                        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                        <button type="submit" name="submit_review" class="btn btn-warning rounded-pill px-4 fw-bold shadow-sm">
                            <i class="bi bi-send-fill me-1"></i> ส่งรีวิว
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer>
        <div class="container text-center">
            <span class="fw-bold text-white fs-5"><i class="bi bi-mortarboard-fill me-2"></i>Skill Exchange</span>
            <p class="mb-0 mt-3 small opacity-50">&copy; <?php echo date('Y'); ?> Skill Exchange. Professional Knowledge Sharing.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (isset($review_error)): ?>
        // เปิด Modal รีวิวอัตโนมัติถ้ามีข้อผิดพลาดจากการ submit ก่อนหน้า
        window.addEventListener('DOMContentLoaded', function() {
            const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
            reviewModal.show();
        });
        <?php endif; ?>

        const detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const data = JSON.parse(button.getAttribute('data-info'));

            document.getElementById('mTitle').textContent = data.skill_name;
            document.getElementById('mDesc').textContent = data.description;
            document.getElementById('mDate').textContent = new Date(data.created_at).toLocaleDateString('th-TH');

            const badge = document.getElementById('mTypeBadge');
            if (data.type === 'teach') {
                badge.innerHTML = '<i class="bi bi-mortarboard-fill me-1"></i> รับสอน';
                badge.className = 'status-badge d-inline-block mb-3 badge-teach';
            } else {
                badge.innerHTML = '<i class="bi bi-hand-index-thumb-fill me-1"></i> อยากเรียน';
                badge.className = 'status-badge d-inline-block mb-3 badge-learn';
            }
        });
    </script>
</body>

</html>