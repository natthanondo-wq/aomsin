<?php
require_once 'auth_session.php'; // เริ่ม session อย่างเดียว ไม่ redirect
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Skill Exchange - แลกเปลี่ยนความรู้</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/index.css">
</head>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-mortarboard-fill me-2"></i>Skill Exchange
            </a>
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2 py-2 py-lg-0">
                <?php if(isset($_SESSION['user_id'])): ?>
                    <li class="nav-item">
                        <a class="nav-link px-3 fw-bold text-primary" href="dashboard.php">
                            <i class="bi bi-grid-fill me-1"></i> ไปที่ Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="btn btn-outline-danger rounded-pill px-4" style="font-weight: 500;" href="logout.php">
                            ออกจากระบบ
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link px-3" href="login.php">เข้าสู่ระบบ</a></li>
                    <li class="nav-item">
                        <a class="btn btn-nav-primary" href="register.php">สมัครสมาชิกฟรี</a>
                    </li>
                <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-section">
        <div class="hero-glow"></div>
        <div class="container position-relative z-1">
            <div class="row justify-content-center">
                <div class="col-lg-9 col-xl-8">
                    <div class="hero-badge">
                        ✨ สังคมแห่งการแบ่งปันความรู้ 100%
                    </div>
                    <h1 class="hero-title">
                        เปลี่ยนทักษะของคุณ<br>
                        ให้เป็นโอกาสที่ยิ่งใหญ่
                    </h1>
                    <p class="hero-subtitle">
                        พื้นที่กลางสำหรับคนรักการเรียนรู้ ค้นหาเพื่อนติว แลกเปลี่ยนวิชา หรือลงประกาศสอนในสิ่งที่คุณถนัด 
                        เชื่อมต่อกับผู้คนที่มีความสนใจเดียวกันได้ง่ายๆ ที่นี่
                    </p>
                    <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                        <a href="register.php" class="btn btn-hero btn-hero-main">
                            🚀 เริ่มต้นใช้งานทันที
                        </a>
                        <a href="login.php" class="btn btn-hero btn-hero-sub">
                            เข้าสู่ระบบ
                        </a>
                    </div>
                    
                    <div class="mt-4 text-white-50 small opacity-75">
                        <i class="bi bi-check-circle-fill me-1"></i> สมัครฟรีไม่มีค่าใช้จ่าย
                        <i class="bi bi-dot mx-2"></i>
                        <i class="bi bi-shield-check-fill me-1"></i> ปลอดภัยเชื่อถือได้
                    </div>
                </div>
            </div>
        </div>
    </header>

    <section class="container features-wrapper px-4">
        <div class="row g-3 g-md-4">
            <div class="col-12 col-md-4">
                <div class="feature-card">
                    <div class="icon-circle">
                        <i class="bi bi-search-heart"></i>
                    </div>
                    <h5 class="fw-bold mb-3">ค้นหาความรู้</h5>
                    <p class="text-secondary mb-0 small">
                        กำลังมองหาคนสอนเขียนโค้ด? หรืออยากฝึกภาษา? ค้นหาผู้เชี่ยวชาญที่พร้อมแบ่งปันได้ทันที
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="feature-card">
                    <div class="icon-circle">
                        <i class="bi bi-megaphone-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-3">ลงประกาศสอน</h5>
                    <p class="text-secondary mb-0 small">
                        อย่าเก็บความเก่งไว้คนเดียว ลงประกาศรับสอนเพื่อสร้างรายได้เสริม หรือแลกเปลี่ยนเป็นวิชาที่คุณอยากเรียน
                    </p>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="feature-card">
                    <div class="icon-circle">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <h5 class="fw-bold mb-3">พูดคุยปลอดภัย</h5>
                    <p class="text-secondary mb-0 small">
                        ระบบแชทภายในเว็บ ช่วยให้คุณสอบถามรายละเอียดและนัดหมายเวลาเรียนได้อย่างสะดวกและเป็นส่วนตัว
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="stats-section text-center">
        <div class="container">
            <div class="row g-4">
                <div class="col-4">
                    <div class="stat-item">
                        <h3>500+</h3>
                        <p class="small">ผู้ใช้งาน</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-item">
                        <h3>120+</h3>
                        <p class="small">ทักษะที่มีให้เรียน</p>
                    </div>
                </div>
                <div class="col-4">
                    <div class="stat-item">
                        <h3>Free</h3>
                        <p class="small">ไม่มีค่าธรรมเนียม</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="container px-4 mb-5">
        <div class="cta-box">
            <i class="bi bi-mortarboard-fill cta-bg-icon"></i>
            <div class="position-relative z-1">
                <h2 class="fw-bold mb-3">พร้อมจะเก่งขึ้นหรือยัง?</h2>
                <p class="lead mb-4 opacity-90 fs-6">เข้าร่วมชุมชนแห่งการเรียนรู้วันนี้ เพื่ออนาคตที่ดีกว่า</p>
                <a href="register.php" class="btn btn-light text-primary fw-bold px-4 py-2 rounded-pill shadow-sm" style="min-width: 180px;">
                    สมัครสมาชิกเลย
                </a>
            </div>
        </div>
    </section>

    <footer>
        <div class="container text-center">
            <span class="fw-bold text-white fs-5"><i class="bi bi-mortarboard-fill me-2"></i>Skill Exchange</span>
            <p class="mb-0 mt-3 small opacity-50">&copy; <?php echo date('Y'); ?> Skill Exchange. Professional Knowledge Sharing.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>