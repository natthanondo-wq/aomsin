<?php
require_once 'db.php';
require_once 'auth.php'; // ตรวจสอบล็อกอิน

date_default_timezone_set('Asia/Bangkok');

$my_id = $_SESSION['user_id'];

// --- 1. ดึงรูปโปรไฟล์ของ "ฉัน" ---
$sql_me = "SELECT profile_image FROM users WHERE id = ?";
$stmt_me = mysqli_prepare($conn, $sql_me);
mysqli_stmt_bind_param($stmt_me, "i", $my_id);
mysqli_stmt_execute($stmt_me);
$res_me = mysqli_stmt_get_result($stmt_me);
$row_me = mysqli_fetch_assoc($res_me);
$my_image = !empty($row_me['profile_image']) ? "uploads/" . $row_me['profile_image'] : "uploads/default.png";
mysqli_stmt_close($stmt_me);

// --- 2. ดึงรายชื่อคู่สนทนา (Sidebar) ---
$sql_sidebar = "SELECT u.id, u.fullname, u.profile_image, MAX(m.created_at) as last_chat
                FROM messages m
                JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
                WHERE (m.sender_id = ? OR m.receiver_id = ?) AND u.id != ?
                GROUP BY u.id, u.fullname, u.profile_image
                ORDER BY last_chat DESC";
$stmt_sidebar = mysqli_prepare($conn, $sql_sidebar);
mysqli_stmt_bind_param($stmt_sidebar, "iii", $my_id, $my_id, $my_id);
mysqli_stmt_execute($stmt_sidebar);
$res_sidebar = mysqli_stmt_get_result($stmt_sidebar);

// --- 3. ดึงข้อมูลคู่สนทนาและประวัติแชท ---
$partner_id    = $_GET['user_id'] ?? null;
$partner_name  = "";
$partner_image = "";
$result_chat   = null;

if ($partner_id) {
    // ข้อมูลคู่สนทนา
    $stmt_partner = mysqli_prepare($conn, "SELECT fullname, profile_image FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt_partner, "i", $partner_id);
    mysqli_stmt_execute($stmt_partner);
    $res_partner = mysqli_stmt_get_result($stmt_partner);

    if (mysqli_num_rows($res_partner) > 0) {
        $partner_row   = mysqli_fetch_assoc($res_partner);
        $partner_name  = $partner_row['fullname'];
        $partner_image = !empty($partner_row['profile_image']) ? "uploads/" . $partner_row['profile_image'] : "uploads/default.png";

        // ==========================================
// [ปรับปรุง] เคลียร์แจ้งเตือนทิ้งทันทีเมื่อเปิดหน้านี้
// ==========================================
// 1. เคลียร์ในตาราง notifications
$sql_clear_notif = "UPDATE notifications SET is_read = 1 
                    WHERE user_id = ? AND sender_id = ? AND type = 'chat'";
$stmt_clear = mysqli_prepare($conn, $sql_clear_notif);
mysqli_stmt_bind_param($stmt_clear, "ii", $my_id, $partner_id);
mysqli_stmt_execute($stmt_clear);
mysqli_stmt_close($stmt_clear);

// 2. เคลียร์ในตาราง messages (เผื่อหน้า Dashboard นับจากตารางนี้โดยตรง)
// *หมายเหตุ: ถ้าในตาราง messages ของคุณไม่มีคอลัมน์ชื่อ is_read ให้ลบ 5 บรรทัดล่างนี้ออกได้ครับ
$sql_clear_msg = "UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?";
$stmt_clear_msg = mysqli_prepare($conn, $sql_clear_msg);
mysqli_stmt_bind_param($stmt_clear_msg, "ii", $my_id, $partner_id);
mysqli_stmt_execute($stmt_clear_msg);
mysqli_stmt_close($stmt_clear_msg);
// ==========================================

        // ประวัติแชท (โหลดครั้งแรก)
        $sql_chat = "SELECT * FROM messages 
                     WHERE (sender_id = ? AND receiver_id = ?) 
                     OR (sender_id = ? AND receiver_id = ?) 
                     ORDER BY created_at ASC";
        $stmt_chat = mysqli_prepare($conn, $sql_chat);
        mysqli_stmt_bind_param($stmt_chat, "iiii", $my_id, $partner_id, $partner_id, $my_id);
        mysqli_stmt_execute($stmt_chat);
        $result_chat = mysqli_stmt_get_result($stmt_chat);
    }
}

// --- 4. การส่งข้อความ (รองรับทั้ง Post ปกติ และ AJAX) ---
if (isset($_POST['send_msg']) && $partner_id) {
    $msg = trim($_POST['message']);

    if (!empty($msg)) {
        // บันทึกข้อความแชท
        $stmt_insert = mysqli_prepare($conn, "INSERT INTO messages (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt_insert, "iis", $my_id, $partner_id, $msg);
        mysqli_stmt_execute($stmt_insert);
        mysqli_stmt_close($stmt_insert);

        // ส่งแจ้งเตือนไปยังผู้รับข้อความ
        $sender_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : "มีผู้ใช้";
        $notif_msg = htmlspecialchars($sender_name) . " ได้ส่งข้อความใหม่ถึงคุณในแชท";
        
        $stmt_chat_notif = mysqli_prepare($conn, "INSERT INTO notifications (user_id, sender_id, type, message) VALUES (?, ?, 'chat', ?)");
        mysqli_stmt_bind_param($stmt_chat_notif, "iis", $partner_id, $my_id, $notif_msg);
        mysqli_stmt_execute($stmt_chat_notif);
        mysqli_stmt_close($stmt_chat_notif);

        // ตรวจสอบว่าเป็นการส่งผ่าน AJAX หรือไม่
        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            header("Location: chat.php?user_id=$partner_id");
            exit();
        }
        exit; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Room - Skill Exchange</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
</head>

<body class="page-chat">

    <div class="container-fluid h-100 p-0">
        
        <div class="d-flex align-items-center px-4 py-2 bg-white border-bottom shadow-sm" style="height: 60px;">
            <a href="dashboard.php" class="text-secondary fw-bold d-flex align-items-center text-decoration-none hover-highlight">
                <i class="bi bi-arrow-left-circle-fill fs-4 me-2 text-primary"></i> กลับหน้าหลัก
            </a>
        </div>

        <div class="container-xxl mt-3 h-100 pb-4">
            <div class="chat-container">
                
                <div class="sidebar d-flex" id="chatSidebar">
                    <div class="sidebar-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fs-5">ข้อความ <span class="badge bg-primary rounded-pill ms-1"><?php echo mysqli_num_rows($res_sidebar); ?></span></span>
                            <img src="<?php echo $my_image; ?>" class="user-avatar-common" style="width: 35px; height: 35px; border-radius: 50%;">
                        </div>
                    </div>
                    
                    <div class="user-list overflow-auto custom-scrollbar">
                        <?php while ($user = mysqli_fetch_assoc($res_sidebar)):
                            $u_img = !empty($user['profile_image']) ? "uploads/" . $user['profile_image'] : "uploads/default.png";
                            $active_class = ($partner_id == $user['id']) ? 'active' : '';
                        ?>
                            <a href="chat.php?user_id=<?php echo $user['id']; ?>" class="user-item <?php echo $active_class; ?>">
                                <img src="<?php echo $u_img; ?>" class="user-avatar-list">
                                <div class="w-100">
                                    <span class="fw-bold text-dark d-block"><?php echo htmlspecialchars(mb_strimwidth($user['fullname'], 0, 20, '...')); ?></span>
                                    <small class="text-muted">คลิกเพื่อสนทนา...</small>
                                </div>
                            </a>
                        <?php endwhile; ?>
                    </div>
                </div>

                <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

                <div class="chat-area">
                    <?php if ($partner_id): ?>
                        
                        <div class="chat-header">
                            <button type="button" class="chat-back-btn-mobile btn btn-light rounded-circle me-2" id="openSidebarBtn" style="width: 38px; height: 38px;">
                                <i class="bi bi-list"></i>
                            </button>
                            <a href="profile_view.php?user_id=<?php echo (int) $partner_id; ?>" class="d-flex align-items-center text-decoration-none text-dark flex-grow-1">
                                <img src="<?php echo $partner_image; ?>" class="user-avatar-list">
                                <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($partner_name); ?></h6>
                            </a>
                            <a href="profile_view.php?user_id=<?php echo (int) $partner_id; ?>" class="btn btn-light btn-sm rounded-pill px-3 d-none d-sm-inline-flex" title="ดูโปรไฟล์">
                                <i class="bi bi-person-circle me-1"></i> โปรไฟล์
                            </a>
                        </div>

                        <div class="messages-box custom-scrollbar" id="chatWindow">
                            <?php if ($result_chat && mysqli_num_rows($result_chat) > 0): ?>
                                <?php while ($chat = mysqli_fetch_assoc($result_chat)): ?>
                                    <div class="msg-row <?php echo ($chat['sender_id'] == $my_id) ? 'me' : 'partner'; ?>">
                                        <div class="msg-wrapper">
                                            <div class="msg-bubble"><?php echo htmlspecialchars($chat['message']); ?></div>
                                            <span class="chat-time"><?php echo date('H:i', strtotime($chat['created_at'])); ?></span>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <div class="chat-input-area">
                            <form id="chatForm" method="post" class="d-flex gap-2">
                                <input type="text" name="message" id="messageInput" class="form-control rounded-pill border-0 bg-light px-4" 
                                       placeholder="พิมพ์ข้อความ..." required autocomplete="off">
                                <button type="submit" name="send_msg" class="btn btn-primary rounded-circle" style="width: 45px; height: 45px;">
                                    <i class="bi bi-send-fill"></i>
                                </button>
                            </form>
                        </div>
                        
                    <?php else: ?>
                        <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted">
                            <button type="button" class="chat-back-btn-mobile btn btn-primary rounded-pill px-4 mb-4" id="openSidebarBtnEmpty">
                                <i class="bi bi-list me-2"></i>เลือกคู่สนทนา
                            </button>
                            <i class="bi bi-chat-quote-fill text-primary mb-3" style="font-size: 4rem;"></i>
                            <h3>ยินดีต้อนรับสู่แชท</h3>
                            <p>เลือกคู่สนทนาเพื่อเริ่มคุยกันเลย</p>
                        </div>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
    </div>

    <script>
        // --- เปิด/ปิด Sidebar บนมือถือ ---
        const chatSidebar = document.getElementById('chatSidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        function openSidebarMobile() {
            chatSidebar.classList.add('show-mobile');
            sidebarBackdrop.classList.add('show-mobile');
        }
        function closeSidebarMobile() {
            chatSidebar.classList.remove('show-mobile');
            sidebarBackdrop.classList.remove('show-mobile');
        }

        const openBtn = document.getElementById('openSidebarBtn');
        const openBtnEmpty = document.getElementById('openSidebarBtnEmpty');
        if (openBtn) openBtn.addEventListener('click', openSidebarMobile);
        if (openBtnEmpty) openBtnEmpty.addEventListener('click', openSidebarMobile);
        if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebarMobile);

        // ปิด sidebar อัตโนมัติเมื่อเลือกคู่สนทนาแล้ว (บนมือถือ)
        document.querySelectorAll('.user-item').forEach(item => {
            item.addEventListener('click', closeSidebarMobile);
        });

        const chatWindow = document.getElementById("chatWindow");
        const chatForm = document.getElementById("chatForm");
        const partnerId = "<?php echo $partner_id; ?>";
        let lastContent = chatWindow ? chatWindow.innerHTML : ""; 

        function scrollToBottom() {
            if (chatWindow) {
                chatWindow.scrollTop = chatWindow.scrollHeight;
            }
        }

        function fetchMessages() {
            if (!partnerId) return;
            
            fetch(`get_messages.php?user_id=${partnerId}`)
                .then(res => res.text())
                .then(data => {
                    if (data.trim() !== lastContent.trim()) {
                        const isBottom = chatWindow.scrollHeight - chatWindow.clientHeight <= chatWindow.scrollTop + 100;

                        chatWindow.innerHTML = data;
                        lastContent = data;

                        if (isBottom) scrollToBottom();
                    }
                });
        }

        if (chatForm) {
            chatForm.addEventListener('submit', function(e) {
                e.preventDefault(); 
                const msgInput = document.getElementById("messageInput");
                if (msgInput.value.trim() === "") return;

                const formData = new FormData(this);
                formData.append('send_msg', '1');

                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                }).then(() => {
                    msgInput.value = ""; 
                    fetchMessages(); 
                });
            });
        }

        window.onload = () => {
            scrollToBottom();
            fetchMessages(); 
        };

        setInterval(fetchMessages, 2000);
    </script>
</body>
</html>