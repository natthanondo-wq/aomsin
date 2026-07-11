<?php

/**
 * Dashboard Page - Skill Exchange (Clickable User Profile)
 */

require_once 'db.php';
require_once 'auth.php'; 

date_default_timezone_set('Asia/Bangkok'); 

$my_id = $_SESSION['user_id'];

// --- 1. ดึงข้อมูลผู้ใช้งานปัจจุบัน ---
$sql_me = "SELECT profile_image FROM users WHERE id = ?";
$stmt_me = mysqli_prepare($conn, $sql_me);
mysqli_stmt_bind_param($stmt_me, "i", $my_id);
mysqli_stmt_execute($stmt_me);
$result_me = mysqli_stmt_get_result($stmt_me);
$me = mysqli_fetch_assoc($result_me);
$my_image = !empty($me['profile_image']) ? "uploads/" . $me['profile_image'] : "uploads/default.png";
mysqli_stmt_close($stmt_me);

// --- 2. ระบบ Chat ---
$sql_inbox = "SELECT u.id, u.fullname, u.profile_image,
                     (SELECT COUNT(*) FROM messages WHERE sender_id = u.id AND receiver_id = ? AND is_read = 0) AS unread_from_user
              FROM messages m
              JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
              WHERE (m.receiver_id = ? OR m.sender_id = ?)
              AND u.id != ?
              GROUP BY u.id";
$stmt_inbox = mysqli_prepare($conn, $sql_inbox);
mysqli_stmt_bind_param($stmt_inbox, "iiii", $my_id, $my_id, $my_id, $my_id);
mysqli_stmt_execute($stmt_inbox);
$res_inbox = mysqli_stmt_get_result($stmt_inbox);
$count_inbox = mysqli_num_rows($res_inbox);

// นับข้อความที่ยังไม่ได้อ่านทั้งหมด (สำหรับปุ่มลอยสีแดงมุมจอ)
$sql_unread = "SELECT COUNT(*) as total_unread FROM messages WHERE receiver_id = ? AND is_read = 0";
$stmt_unread = mysqli_prepare($conn, $sql_unread);
mysqli_stmt_bind_param($stmt_unread, "i", $my_id);
mysqli_stmt_execute($stmt_unread);
$res_unread = mysqli_stmt_get_result($stmt_unread);
$row_unread = mysqli_fetch_assoc($res_unread);
$unread_count = $row_unread['total_unread'];
mysqli_stmt_close($stmt_unread);

// =========================================================================
// [แก้ไขตามขั้นตอนที่ 1] ระบบ Notification แบบใหม่ (ดึงจำนวน และ ดึงรายการ)
// =========================================================================

// ดึงจำนวนแจ้งเตือนที่ยังไม่ได้อ่าน
$stmt_unread_notif = mysqli_prepare($conn, "SELECT COUNT(id) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
mysqli_stmt_bind_param($stmt_unread_notif, "i", $my_id);
mysqli_stmt_execute($stmt_unread_notif);
$unread_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_unread_notif));
$notif_count = $unread_data['unread_count']; 
mysqli_stmt_close($stmt_unread_notif);

// ดึงรายการแจ้งเตือนล่าสุด 5 รายการเพื่อแสดงใน Dropdown
$stmt_list_notif = mysqli_prepare($conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
mysqli_stmt_bind_param($stmt_list_notif, "i", $my_id);
mysqli_stmt_execute($stmt_list_notif);
$res_list_notif = mysqli_stmt_get_result($stmt_list_notif);


// --- 3. ระบบค้นหา, กรอง และดึง Feed ---
$search = trim($_GET['search'] ?? "");
$filter_type = $_GET['filter_type'] ?? "all"; // all, teach, learn
$sort = $_GET['sort'] ?? "newest"; // newest, oldest

// ตรวจสอบค่าที่อนุญาตเท่านั้น (whitelist) ป้องกันการฉีดค่าแปลกปลอม
if (!in_array($filter_type, ['all', 'teach', 'learn'])) {
    $filter_type = 'all';
}
if (!in_array($sort, ['newest', 'oldest'])) {
    $sort = 'newest';
}

$order_sql = ($sort === 'oldest') ? "ORDER BY s.created_at ASC" : "ORDER BY s.created_at DESC";

$conditions = [];
$params     = [];
$param_types = "";

if (!empty($search)) {
    $conditions[] = "s.skill_name LIKE ?";
    $params[]     = "%" . $search . "%";
    $param_types .= "s";
}
if ($filter_type !== 'all') {
    $conditions[] = "s.type = ?";
    $params[]     = $filter_type;
    $param_types .= "s";
}

$where_sql = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$sql_feed = "SELECT s.*, u.fullname, u.profile_image as poster_image 
             FROM skills s JOIN users u ON s.user_id = u.id 
             $where_sql
             $order_sql";

$stmt_feed = mysqli_prepare($conn, $sql_feed);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt_feed, $param_types, ...$params);
}
mysqli_stmt_execute($stmt_feed);
$result_feed = mysqli_stmt_get_result($stmt_feed);
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Skill Exchange</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
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
                    
                    <li class="nav-item dropdown me-lg-2">
                        <a class="nav-link position-relative dropdown-toggle no-caret p-2 text-dark fs-5" href="#" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell-fill"></i>
                            <span id="notifBellBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.65rem; <?php echo $notif_count > 0 ? '' : 'display:none;'; ?>">
                                <?php echo $notif_count; ?>
                            </span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-4 p-3 mt-2" aria-labelledby="notifDropdown" style="width: 320px;">
                            <li class="mb-2 pb-2 border-bottom d-flex justify-content-between align-items-center">
                                <span class="fw-bold text-dark">การแจ้งเตือน</span>
                                <?php if ($notif_count > 0): ?>
                                    <span class="badge bg-light text-primary small">ใหม่ <?php echo $notif_count; ?></span>
                                <?php endif; ?>
                            </li>
                            <div style="max-height: 300px; overflow-y: auto;">
                                <?php if (isset($res_list_notif) && mysqli_num_rows($res_list_notif) > 0): ?>
                                    <?php while ($notif = mysqli_fetch_assoc($res_list_notif)): ?>
                                        <li>
                                            <a class="dropdown-item dropdown-item-notif p-2 text-wrap d-block rounded mb-1 <?php echo ($notif['is_read'] == 0) ? 'bg-light' : ''; ?>" href="notif_redirect.php?id=<?php echo $notif['id']; ?>">
                                                <div class="mb-1 text-dark" style="font-size: 0.9rem;"><?php echo htmlspecialchars($notif['message'] ?? ''); ?></div>
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
                        <a href="profile.php" class="nav-link d-flex align-items-center bg-light rounded-pill px-3 py-1 my-2 my-lg-0 hover-opacity text-decoration-none">
                            <img src="<?php echo htmlspecialchars($my_image); ?>" class="avatar-header me-2">
                            <span class="text-dark fw-bold"><?php echo htmlspecialchars(mb_strimwidth($_SESSION['fullname'], 0, 15, '...')); ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="add_skill.php" class="btn btn-primary-custom d-flex align-items-center my-1 my-lg-0">
                            <i class="bi bi-plus-lg me-1"></i> ลงประกาศ
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link text-danger" onclick="return confirm('ยืนยันออกจากระบบ?');">
                            <i class="bi bi-box-arrow-right fs-5"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <header class="hero-modern">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3 text-white">พื้นที่แห่งการแบ่งปันความรู้</h1>
            <p class="lead mb-0 opacity-75">ค้นหาทักษะที่คุณอยากเรียน หรือแบ่งปันสิ่งที่คุณเชี่ยวชาญ</p>
        </div>
    </header>

    <main class="container mb-5 flex-grow-1 position-relative">

        <div class="row justify-content-center search-container-floating mb-4">
            <div class="col-12 col-lg-8">
                <form method="get" class="search-box-modern">
                    <i class="bi bi-search text-muted ms-2 fs-5"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent shadow-none px-3"
                        placeholder="ค้นหาวิชาที่สนใจ..." value="<?php echo htmlspecialchars($search); ?>">
                    <input type="hidden" name="filter_type" value="<?php echo htmlspecialchars($filter_type); ?>">
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                    <button class="btn-search-blue">ค้นหา</button>
                    <?php if (!empty($search)): ?>
                        <a href="dashboard.php?filter_type=<?php echo htmlspecialchars($filter_type); ?>&sort=<?php echo htmlspecialchars($sort); ?>" class="btn btn-light text-secondary rounded-circle ms-2 d-flex align-items-center justify-content-center" style="width:40px; height:40px;">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-12 col-lg-8 d-flex flex-wrap gap-2 justify-content-center justify-content-lg-between align-items-center">
                <div class="filter-tabs d-flex gap-2">
                    <a href="?search=<?php echo urlencode($search); ?>&filter_type=all&sort=<?php echo htmlspecialchars($sort); ?>"
                       class="filter-tab <?php echo $filter_type === 'all' ? 'active' : ''; ?>">
                        ทั้งหมด
                    </a>
                    <a href="?search=<?php echo urlencode($search); ?>&filter_type=teach&sort=<?php echo htmlspecialchars($sort); ?>"
                       class="filter-tab <?php echo $filter_type === 'teach' ? 'active' : ''; ?>">
                        <i class="bi bi-mortarboard-fill me-1"></i>รับสอน
                    </a>
                    <a href="?search=<?php echo urlencode($search); ?>&filter_type=learn&sort=<?php echo htmlspecialchars($sort); ?>"
                       class="filter-tab <?php echo $filter_type === 'learn' ? 'active' : ''; ?>">
                        <i class="bi bi-hand-index-thumb-fill me-1"></i>อยากเรียน
                    </a>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sort-modern dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-sort-down me-1"></i>
                        <?php echo $sort === 'oldest' ? 'เก่าสุดก่อน' : 'ใหม่สุดก่อน'; ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item <?php echo $sort === 'newest' ? 'active' : ''; ?>" href="?search=<?php echo urlencode($search); ?>&filter_type=<?php echo htmlspecialchars($filter_type); ?>&sort=newest">ใหม่สุดก่อน</a></li>
                        <li><a class="dropdown-item <?php echo $sort === 'oldest' ? 'active' : ''; ?>" href="?search=<?php echo urlencode($search); ?>&filter_type=<?php echo htmlspecialchars($filter_type); ?>&sort=oldest">เก่าสุดก่อน</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center mb-4">
            <h4 class="fw-bold m-0 text-dark">
                <i class="bi bi-stars text-primary me-2"></i>
                <?php
                if (!empty($search)) {
                    echo 'ผลการค้นหา "' . htmlspecialchars($search) . '"';
                } else {
                    echo 'ประกาศล่าสุด';
                }
                ?>
                <span class="text-muted small fw-normal ms-2"><?php echo mysqli_num_rows($result_feed); ?> รายการ</span>
            </h4>
        </div>

        <div class="row g-4">
            <?php if (mysqli_num_rows($result_feed) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result_feed)): ?>
                    <?php
                    $poster_img = !empty($row['poster_image']) ? "uploads/" . $row['poster_image'] : "uploads/default.png";

                    if ($row['type'] == 'teach') {
                        $badge_class = 'badge-teach';
                        $badge_text = '<i class="bi bi-mortarboard-fill me-1"></i> รับสอน';
                    } else {
                        $badge_class = 'badge-learn';
                        $badge_text = '<i class="bi bi-hand-index-thumb-fill me-1"></i> อยากเรียน';
                    }
                    ?>
                    <div class="col-12 col-md-6 col-xl-4">
                        <div class="card-announcement p-4 d-flex flex-column">

                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <a href="profile_view.php?user_id=<?php echo htmlspecialchars($row['user_id']); ?>" class="user-link mb-0" title="ดูโปรไฟล์ของ <?php echo htmlspecialchars($row['fullname']); ?>">
                                    <img src="<?php echo htmlspecialchars($poster_img); ?>" class="user-meta-img">
                                    <div class="user-meta-info">
                                        <div class="user-name text-truncate" style="max-width: 130px;">
                                            <?php echo htmlspecialchars($row['fullname']); ?>
                                        </div>
                                        <div class="post-date">
                                            <?php echo date('d M', strtotime($row['created_at'])); ?>
                                        </div>
                                    </div>
                                </a>
                                <span class="status-badge <?php echo $badge_class; ?>">
                                    <?php echo $badge_text; ?>
                                </span>
                            </div>

                            <h5 class="card-title-custom text-truncate">
                                <?php echo htmlspecialchars($row['skill_name']); ?>
                            </h5>
                            <p class="card-desc-custom flex-grow-1">
                                <?php echo htmlspecialchars($row['description']); ?>
                            </p>

                            <div class="card-actions">
                                <button class="btn btn-action-ghost"
                                    data-bs-toggle="modal" data-bs-target="#detailModal"
                                    data-info='<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>'>
                                    <i class="bi bi-eye me-1"></i> ดูรายละเอียด
                                </button>

                                <?php if ($row['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="chat.php?user_id=<?php echo htmlspecialchars($row['user_id']); ?>" class="btn btn-action-primary text-decoration-none d-flex align-items-center justify-content-center">
                                        <i class="bi bi-chat-fill me-2"></i> ทักแชท
                                    </a>
                                <?php else: ?>
                                    <a href="edit_skill.php?id=<?php echo htmlspecialchars($row['id']); ?>" class="btn btn-action-ghost flex-grow-0" style="width: 50px;"><i class="bi bi-pencil-fill"></i></a>
                                    <a href="delete_skill.php?id=<?php echo htmlspecialchars($row['id']); ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="btn btn-light text-danger border flex-grow-0" style="width: 50px;" onclick="return confirm('ลบประกาศ?');"><i class="bi bi-trash-fill"></i></a>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5">
                    <i class="bi bi-inbox text-muted opacity-25" style="font-size: 4rem;"></i>
                    <h5 class="text-dark fw-bold mt-3">ยังไม่มีประกาศ</h5>
                    <p class="text-muted">มาเริ่มแบ่งปันทักษะของคุณกันเถอะ</p>
                    <a href="add_skill.php" class="btn btn-primary-custom px-4 mt-2">ลงประกาศใหม่</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <button class="chat-float-btn" onclick="toggleChat()">
        <i class="bi bi-chat-dots-fill"></i>
        <span id="chatFloatBadge" class="chat-badge-modern" style="<?php echo $unread_count > 0 ? '' : 'display:none;'; ?>"><?php echo $unread_count; ?></span>
    </button>

    <div class="chat-popup-modern" id="chatPopup">
        <div class="chat-popup-header d-flex justify-content-between align-items-center">
            <h6 class="m-0 fw-bold"><i class="bi bi-chat-square-text me-2"></i>ข้อความของคุณ</h6>
            <button type="button" class="btn-close btn-close-white small" onclick="toggleChat()"></button>
        </div>
        <div class="bg-white" style="max-height: 350px; overflow-y: auto;">
            <?php if ($count_inbox > 0): ?>
                <?php mysqli_data_seek($res_inbox, 0); ?>
                <?php while ($chat = mysqli_fetch_assoc($res_inbox)): ?>
                    <?php $c_img = !empty($chat['profile_image']) ? "uploads/" . $chat['profile_image'] : "uploads/default.png"; ?>
                    <a href="chat.php?user_id=<?php echo htmlspecialchars($chat['id']); ?>" class="d-flex align-items-center p-3 border-bottom text-decoration-none text-dark hover-bg-light">
                        <img src="<?php echo htmlspecialchars($c_img); ?>" class="rounded-circle me-3" style="width: 45px; height: 45px; object-fit:cover;">
                        <div class="flex-grow-1 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-bold small <?php echo ($chat['unread_from_user'] > 0) ? 'text-primary' : ''; ?>">
                                    <?php echo htmlspecialchars($chat['fullname']); ?>
                                </div>
                                <small class="text-muted" style="font-size: 0.75rem;">
                                    <?php echo ($chat['unread_from_user'] > 0) ? 'มีข้อความใหม่ที่ยังไม่ได้อ่าน' : 'คลิกเพื่อเริ่มสนทนา'; ?>
                                </small>
                            </div>
                            <?php if ($chat['unread_from_user'] > 0): ?>
                                <span class="badge bg-danger rounded-pill small">ใหม่ <?php echo $chat['unread_from_user']; ?></span>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="p-4 text-center text-muted small">ไม่มีการสนทนา</div>
            <?php endif; ?>
        </div>
    </div>

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
                    <div class="d-flex align-items-center">
                        <span class="text-muted small me-2">ประกาศโดย:</span>
                        <span class="fw-bold text-dark" id="mPoster"></span>
                    </div>
                </div>
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
        function toggleChat() {
            const popup = document.getElementById('chatPopup');
            popup.style.display = (popup.style.display === 'none' || popup.style.display === '') ? 'block' : 'none';
        }

        // --- อัปเดตจำนวนแจ้งเตือน/แชทแบบ Real-time (Polling ทุก 5 วินาที) ---
        function updateNotifBadges() {
            fetch('get_notif_count.php')
                .then(res => res.json())
                .then(data => {
                    // อัปเดต Badge กระดิ่งแจ้งเตือน
                    const bellBadge = document.getElementById('notifBellBadge');
                    if (data.notif_count > 0) {
                        if (bellBadge) {
                            bellBadge.textContent = data.notif_count;
                            bellBadge.style.display = 'inline-block';
                        }
                    } else if (bellBadge) {
                        bellBadge.style.display = 'none';
                    }

                    // อัปเดต Badge ปุ่มแชทลอย
                    const chatBadge = document.getElementById('chatFloatBadge');
                    if (data.unread_chat > 0) {
                        if (chatBadge) {
                            chatBadge.textContent = data.unread_chat;
                            chatBadge.style.display = 'flex';
                        }
                    } else if (chatBadge) {
                        chatBadge.style.display = 'none';
                    }
                })
                .catch(() => { /* เงียบไว้ ไม่ต้องรบกวนผู้ใช้ถ้า request ล้มเหลวชั่วคราว */ });
        }

        setInterval(updateNotifBadges, 5000);

        const detailModal = document.getElementById('detailModal');
        detailModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const data = JSON.parse(button.getAttribute('data-info'));

            document.getElementById('mTitle').textContent = data.skill_name;
            document.getElementById('mDesc').textContent = data.description;

            const posterLink = document.createElement('a');
            posterLink.href = `profile_view.php?user_id=${data.user_id}`;
            posterLink.className = 'text-dark text-decoration-none fw-bold';
            posterLink.textContent = data.fullname;

            const posterContainer = document.getElementById('mPoster');
            posterContainer.innerHTML = '';
            posterContainer.appendChild(posterLink);

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