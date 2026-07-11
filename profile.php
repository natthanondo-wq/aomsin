<?php
require_once 'db.php';
require_once 'auth.php';

date_default_timezone_set('Asia/Bangkok');
$my_id = $_SESSION['user_id'];

// --- 1. ดึงข้อมูลโปรไฟล์ของตัวเอง ---
$sql_user = "SELECT * FROM users WHERE id = ?";
$stmt = mysqli_prepare($conn, $sql_user);
mysqli_stmt_bind_param($stmt, "i", $my_id);
mysqli_stmt_execute($stmt);
$result_user = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result_user);
mysqli_stmt_close($stmt);

$profile_img = !empty($user['profile_image']) ? "uploads/" . $user['profile_image'] : "uploads/default.png";

// --- 2. ดึงประวัติการลงประกาศของตัวเอง ---
$sql_skills = "SELECT * FROM skills WHERE user_id = ? ORDER BY created_at DESC";
$stmt_skills = mysqli_prepare($conn, $sql_skills);
mysqli_stmt_bind_param($stmt_skills, "i", $my_id);
mysqli_stmt_execute($stmt_skills);
$result_skills = mysqli_stmt_get_result($stmt_skills);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>โปรไฟล์ของฉัน - Skill Exchange</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-clean sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold fs-4" href="dashboard.php"><i class="bi bi-mortarboard-fill me-2"></i>Skill Exchange</a>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-lg-3">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link">หน้าหลัก</a>
                    </li>
                    <li class="nav-item">
                        <a href="edit_profile.php" class="btn btn-outline-primary rounded-pill px-3 py-1">
                            <i class="bi bi-pencil-square me-1"></i> แก้ไขโปรไฟล์
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="profile-header-bg"></div>

    <main class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="profile-card text-center text-md-start">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <img src="<?php echo htmlspecialchars($profile_img); ?>" class="profile-img-large">
                        </div>
                        <div class="col-md-9 mt-3 mt-md-0 pt-md-3">
                            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center align-items-md-start">
                                <div>
                                    <h2 class="fw-bold mb-1"><?php echo htmlspecialchars($user['fullname']); ?></h2>
                                    <p class="text-muted mb-2"><i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?></p>
                                    
                                    <?php if(!empty($user['expertise_level'])): ?>
                                        <span class="expertise-badge mt-1 d-inline-block">
                                            <i class="bi bi-award-fill me-1"></i> ระดับ: <?php echo htmlspecialchars($user['expertise_level']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <a href="edit_profile.php" class="btn btn-primary mt-3 mt-md-0 rounded-pill px-4 shadow-sm">
                                    <i class="bi bi-pencil-fill me-1"></i> แก้ไขข้อมูล
                                </a>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted">

                    <div class="row g-4">
                        <div class="col-md-7">
                            <h5 class="fw-bold"><i class="bi bi-person-lines-fill text-primary me-2"></i> แนะนำตัว</h5>
                            <p class="text-secondary" style="line-height: 1.7;">
                                <?php echo !empty($user['bio']) ? nl2br(htmlspecialchars($user['bio'])) : '<span class="text-muted fst-italic">ยังไม่มีการแนะนำตัว</span>'; ?>
                            </p>

                            <h5 class="fw-bold mt-4"><i class="bi bi-briefcase-fill text-primary me-2"></i> ประสบการณ์</h5>
                            <p class="text-secondary" style="line-height: 1.7;">
                                <?php echo !empty($user['experience']) ? nl2br(htmlspecialchars($user['experience'])) : '<span class="text-muted fst-italic">ยังไม่ได้ระบุประสบการณ์</span>'; ?>
                            </p>
                        </div>
                        <div class="col-md-5 border-start-md ps-md-4">
                            <h5 class="fw-bold"><i class="bi bi-tools text-primary me-2"></i> ทักษะความสามารถ</h5>
                            <div class="mb-4">
                                <?php 
                                if (!empty($user['personal_skills'])) {
                                    $skills_array = explode(',', $user['personal_skills']);
                                    foreach ($skills_array as $skill) {
                                        echo '<span class="skill-badge">' . htmlspecialchars(trim($skill)) . '</span>';
                                    }
                                } else {
                                    echo '<span class="text-muted fst-italic small">ยังไม่ได้เพิ่มทักษะ</span>';
                                }
                                ?>
                            </div>

                            <h5 class="fw-bold"><i class="bi bi-link-45deg text-primary me-2"></i> ผลงาน (Portfolio)</h5>
                            <?php if (!empty($user['portfolio_link'])): ?>
                                <a href="<?php echo htmlspecialchars($user['portfolio_link']); ?>" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill mt-1">
                                    <i class="bi bi-box-arrow-up-right me-1"></i> ดูผลงาน
                                </a>
                            <?php else: ?>
                                <span class="text-muted fst-italic small">ไม่มีลิงก์ผลงาน</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row justify-content-center mt-5">
            <div class="col-lg-10">
                <h4 class="fw-bold mb-4"><i class="bi bi-megaphone-fill text-primary me-2"></i>ประกาศของฉัน</h4>
                <div class="row g-4">
                    <?php if (mysqli_num_rows($result_skills) > 0): ?>
                        <?php while ($row = mysqli_fetch_assoc($result_skills)): ?>
                            <?php
                            $badge_class = ($row['type'] == 'teach') ? 'badge-teach' : 'badge-learn';
                            $badge_text = ($row['type'] == 'teach') ? '<i class="bi bi-mortarboard-fill me-1"></i> รับสอน' : '<i class="bi bi-hand-index-thumb-fill me-1"></i> อยากเรียน';
                            ?>
                            <div class="col-12 col-md-6 text-start">
                                <div class="card-announcement p-4 d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="status-badge <?php echo $badge_class; ?>"><?php echo $badge_text; ?></span>
                                        <small class="text-muted"><?php echo date('d M Y', strtotime($row['created_at'])); ?></small>
                                    </div>
                                    <h5 class="fw-bold text-dark mt-2 mb-2 text-truncate"><?php echo htmlspecialchars($row['skill_name']); ?></h5>
                                    <p class="text-secondary small flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </p>
                                    <div class="mt-3 pt-3 border-top d-flex gap-2">
                                        <a href="edit_skill.php?id=<?php echo $row['id']; ?>" class="btn btn-light text-primary btn-sm flex-grow-1 rounded-3 fw-bold">
                                            <i class="bi bi-pencil-fill me-1"></i> แก้ไข
                                        </a>
                                        <a href="delete_skill.php?id=<?php echo $row['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-light text-danger btn-sm rounded-3 px-3" onclick="return confirm('ต้องการลบประกาศนี้ใช่หรือไม่?');">
                                            <i class="bi bi-trash-fill"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-4 bg-white rounded-4 border">
                            <p class="text-muted mb-0">คุณยังไม่ได้ลงประกาศใดๆ</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>