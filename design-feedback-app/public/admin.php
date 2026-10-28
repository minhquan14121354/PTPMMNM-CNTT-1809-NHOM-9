<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

$currentUserId = $_SESSION['user_id'];
$action = $_GET['action'] ?? 'home';

$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$currentUserId]);
$currentUserData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$fullname = $currentUserData['fullname'] ?? $_SESSION['fullname'];
$phone = $currentUserData['phone'] ?? '';
$email = $currentUserData['email'] ?? '';

switch ($action) {
    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $newFullname = trim($_POST['fullname'] ?? '');
            $newPhone = trim($_POST['phone'] ?? '');
            $newEmail = trim($_POST['email'] ?? '');
            $oldPasswordInput = $_POST['old_password'] ?? '';
            $newPasswordInput = $_POST['new_password'] ?? '';

            $isChangingPhone = ($newPhone !== $phone);
            $isChangingEmail = ($newEmail !== $email);
            $isChangingPassword = !empty($newPasswordInput);

            $requiresPasswordVerification = $isChangingPassword || ($isChangingPhone && !empty($phone)) || ($isChangingEmail && !empty($email));

            if ($requiresPasswordVerification) {
                if (empty($oldPasswordInput) || (!password_verify($oldPasswordInput, $currentUserData['password']) && $oldPasswordInput !== $currentUserData['password'])) {
                    echo "<script>alert('Mật khẩu cũ không chính xác!'); window.location.href='admin.php';</script>";
                    exit;
                }
            }

            if (!empty($newFullname)) {
                if ($isChangingPassword) {
                    $hashed = password_hash($newPasswordInput, PASSWORD_DEFAULT);
                    $stmtUp = $db->prepare("UPDATE users SET fullname = ?, phone = ?, email = ?, password = ? WHERE id = ?");
                    $stmtUp->execute([$newFullname, $newPhone, $newEmail, $hashed, $currentUserId]);
                } else {
                    $stmtUp = $db->prepare("UPDATE users SET fullname = ?, phone = ?, email = ? WHERE id = ?");
                    $stmtUp->execute([$newFullname, $newPhone, $newEmail, $currentUserId]);
                }
                $_SESSION['fullname'] = $newFullname;
                header("Location: admin.php");
                exit;
            }
        }
        header("Location: admin.php");
        exit;

    case 'approve_project':
        if (isset($_GET['id'])) {
            $db->prepare("UPDATE projects SET status = 'approved' WHERE id = ?")->execute([$_GET['id']]);
        }
        header("Location: admin.php");
        exit;

    case 'delete_project':
        if (isset($_GET['id'])) {
            $pId = $_GET['id'];
            $db->prepare("DELETE a FROM annotations a JOIN image_versions iv ON a.image_version_id = iv.id JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
            $db->prepare("DELETE al FROM annotation_likes al JOIN annotations a ON al.annotation_id = a.id JOIN image_versions iv ON a.image_version_id = iv.id JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
            $db->prepare("DELETE iv FROM image_versions iv JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
            $db->prepare("DELETE FROM designs WHERE project_id = ?")->execute([$pId]);
            $db->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$pId]);
            $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$pId]);
        }
        header("Location: admin.php");
        exit;

    case 'delete_user':
        if (isset($_GET['id'])) {
            $uId = $_GET['id'];
            $db->prepare("DELETE FROM users WHERE id = ?")->execute([$uId]);
        }
        header("Location: admin.php");
        exit;

    case 'get_project_details':
        $pId = $_GET['id'] ?? 0;
        
        $stmtProj = $db->prepare("SELECT p.*, u.fullname as client_name FROM projects p JOIN users u ON p.client_id = u.id WHERE p.id = ?");
        $stmtProj->execute([$pId]);
        $project = $stmtProj->fetch(PDO::FETCH_ASSOC);

        if (!$project) {
            echo json_encode(['success' => false, 'message' => 'Không tìm thấy dự án!']);
            exit;
        }

        $stmtDes = $db->prepare("
            SELECT u.fullname FROM project_members pm 
            JOIN users u ON pm.user_id = u.id 
            WHERE pm.project_id = ? AND (pm.role = 'designer' OR u.role = 'designer') LIMIT 1
        ");
        $stmtDes->execute([$pId]);
        $designerName = $stmtDes->fetchColumn() ?: 'Chưa phân công';

        $stmtDesigns = $db->prepare("SELECT * FROM designs WHERE project_id = ? ORDER BY id DESC");
        $stmtDesigns->execute([$pId]);
        $designs = $stmtDesigns->fetchAll(PDO::FETCH_ASSOC);

        $designsData = [];
        foreach($designs as $d) {
            $stmtVer = $db->prepare("SELECT * FROM image_versions WHERE design_id = ? ORDER BY version_number DESC");
            $stmtVer->execute([$d['id']]);
            $versions = $stmtVer->fetchAll(PDO::FETCH_ASSOC);
            
            $verList = [];
            foreach($versions as $ver) {
                $stmtAnn = $db->prepare("SELECT a.*, u.fullname FROM annotations a JOIN users u ON a.user_id = u.id WHERE a.image_version_id = ? ORDER BY a.id DESC");
                $stmtAnn->execute([$ver['id']]);
                $annotations = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);
                
                $verList[] = [
                    'version_number' => $ver['version_number'],
                    'created_at' => $ver['created_at'],
                    'image_url' => './uploads/' . rawurlencode(basename($ver['image_url'])),
                    'annotations' => $annotations
                ];
            }
            
            $designsData[] = [
                'title' => $d['title'],
                'versions' => $verList
            ];
        }

        echo json_encode([
            'success' => true,
            'project_name' => $project['name'],
            'client_name' => $project['client_name'],
            'designer_name' => $designerName,
            'status' => $project['status'] === 'approved' ? 'Đã duyệt' : 'Đang chờ duyệt',
            'designs' => $designsData
        ]);
        exit;

    case 'home':
    default:
        $projects = $db->query("SELECT p.*, u.fullname as client_name FROM projects p JOIN users u ON p.client_id = u.id ORDER BY p.id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $users = $db->query("SELECT * FROM users ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        $totalProjects = count($projects);
        $totalUsers = count($users);
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>ArtFlow Studio - Trang Quản trị (Admin)</title>
            <style>
                @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
                body { font-family: 'Montserrat', sans-serif; background-color: #F0F2F5; color: #050505; margin: 0; padding: 0; }
                
                .top-header { background: #FFF; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
                .app-logo { font-size: 20px; font-weight: 700; color: #1877F2; display: flex; align-items: center; gap: 10px; }
                .app-logo svg { width: 26px; height: 26px; fill: #1877F2; }
                
                .user-nav-dropdown { position: relative; display: inline-block; }
                .user-nav-btn { background: #F0F2F5; border: 1px solid #CCD0D5; padding: 6px 14px; border-radius: 20px; display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: #050505; transition: 0.2s; }
                .user-nav-btn:hover { background: #E4E6EB; }
                .user-nav-btn svg { width: 16px; height: 16px; fill: #65676B; }

                .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; background: #FFF; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 220px; border: 1px solid #E4E6EB; z-index: 2000; overflow: hidden; }
                .dropdown-menu.show { display: block; }
                .dropdown-header { padding: 12px 16px; background: #F8F9FA; border-bottom: 1px solid #E4E6EB; }
                .dropdown-header .name { font-weight: 700; font-size: 13px; color: #050505; }
                .dropdown-header .role { font-size: 11px; color: #65676B; margin-top: 2px; }
                .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: #050505; text-decoration: none; font-size: 13px; font-weight: 500; transition: 0.15s; cursor: pointer; }
                .dropdown-item:hover { background: #EBF5FF; color: #1877F2; }
                .dropdown-item svg { width: 16px; height: 16px; fill: currentColor; }
                .dropdown-item.logout { color: #E41E3F; border-top: 1px solid #E4E6EB; }
                .dropdown-item.logout:hover { background: #FFEBEE; }

                .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; }
                .modal-overlay.show { display: flex; }
                .modal-card { background: #FFF; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; box-sizing: border-box; }
                .modal-header { padding: 16px 20px; background: #F8F9FA; border-bottom: 1px solid #E4E6EB; display: flex; justify-content: space-between; align-items: center; }
                .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #050505; display: flex; align-items: center; gap: 8px; }
                .modal-header h3 svg { width: 20px; height: 20px; fill: #1877F2; }
                .modal-close-btn { background: none; border: none; font-size: 20px; font-weight: bold; color: #65676B; cursor: pointer; }
                .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
                .modal-form-group { margin-bottom: 14px; }
                .modal-form-group label { display: block; font-size: 13px; font-weight: 600; color: #4B4F56; margin-bottom: 5px; }
                .modal-form-control { width: 100%; padding: 9px 12px; border: 1px solid #CCD0D5; border-radius: 8px; font-size: 13px; box-sizing: border-box; outline: none; font-family: inherit; }
                .modal-form-control:focus { border-color: #1877F2; box-shadow: 0 0 0 2px rgba(24,119,242,0.2); }
                .modal-divider { border: 0; border-top: 1px solid #E4E6EB; margin: 15px 0; }
                .modal-footer { padding: 12px 20px; background: #F8F9FA; border-top: 1px solid #E4E6EB; display: flex; justify-content: flex-end; gap: 10px; }
                .btn-modal-cancel { background: #E4E6EB; color: #4B4F56; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; }
                .btn-modal-submit { background: #1877F2; color: #FFF; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; }

                .project-modal-card { background: #FFF; border-radius: 16px; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 30px rgba(0,0,0,0.3); overflow: hidden; }
                .project-modal-body { padding: 20px; overflow-y: auto; flex: 1; background: #F0F2F5; }
                .project-meta-box { background: #FFF; padding: 15px; border-radius: 10px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 15px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 10px; font-size: 13px; color: #65676B; }
                .project-meta-box b { color: #050505; }
                .design-box-card { background: #FFF; border-radius: 10px; padding: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); margin-bottom: 15px; }
                .image-container-preview { position: relative; width: 100%; max-height: 400px; background: #1a1a1a; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 12px; }
                .image-container-preview img { max-width: 100%; max-height: 400px; object-fit: contain; display: block; }
                .pin-marker { position: absolute; width: 24px; height: 24px; background: #FF3B30; color: white; border-radius: 50%; font-size: 11px; font-weight: bold; display: flex; align-items: center; justify-content: center; transform: translate(-50%, -50%); border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); }
                .feedback-card-item { background: #F9FAFB; border: 1px solid #E4E6EB; border-radius: 8px; padding: 10px 12px; font-size: 13px; margin-bottom: 8px; }
                .feedback-header { display: flex; justify-content: space-between; font-weight: 700; color: #050505; margin-bottom: 4px; }
                .badge-status { padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
                .status-open { background: #FFEBEE; color: #C62828; }
                .status-resolved { background: #E8F5E9; color: #2E7D32; }

                .admin-container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
                .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px; }
                .stat-card { background: #FFF; padding: 20px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 16px; }
                .stat-icon { width: 48px; height: 48px; border-radius: 12px; background: #EBF5FF; display: flex; align-items: center; justify-content: center; }
                .stat-icon svg { width: 24px; height: 24px; fill: #1877F2; }
                .stat-info h4 { margin: 0 0 4px; font-size: 13px; color: #65676B; font-weight: 500; }
                .stat-info .num { font-size: 22px; font-weight: 700; color: #050505; margin: 0; }

                .section-card { background: #FFF; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 30px; }
                .section-title { font-size: 16px; font-weight: 700; color: #050505; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
                .section-title svg { width: 20px; height: 20px; fill: #1877F2; }

                table { width: 100%; border-collapse: collapse; font-size: 13px; }
                th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid #E4E6EB; }
                th { background: #F8F9FA; font-weight: 600; color: #4B4F56; }
                tr:hover { background: #F8F9FA; }

                .badge { padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; display: inline-block; }
                .badge-approved { background: #D4EDDA; color: #155724; }
                .badge-pending { background: #FFF3CD; color: #856404; }
                .badge-role { background: #E4E6EB; color: #050505; }

                .btn { padding: 6px 12px; border-radius: 6px; font-size: 12px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; border: none; cursor: pointer; }
                .btn-success { background: #28A745; color: #FFF; }
                .btn-danger { background: #DC3545; color: #FFF; }
                .btn-view { background: #1877F2; color: #FFF; }
            </style>
        </head>
        <body>
            <div class="modal-overlay" id="profile-modal">
                <div class="modal-card">
                    <div class="modal-header">
                        <h3>
                            <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c-.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c-.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                            Cài đặt Tài khoản Admin
                        </h3>
                        <button class="modal-close-btn" id="modal-close">&times;</button>
                    </div>
                    <form action="admin.php?action=update_profile" method="POST">
                        <div class="modal-body">
                            <div class="modal-form-group">
                                <label>Họ tên hiển thị</label>
                                <input type="text" name="fullname" class="modal-form-control" value="<?= htmlspecialchars($fullname) ?>" required>
                            </div>
                            <div class="modal-form-group">
                                <label>Số điện thoại</label>
                                <input type="text" name="phone" class="modal-form-control" value="<?= htmlspecialchars($phone) ?>">
                            </div>
                            <div class="modal-form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="modal-form-control" value="<?= htmlspecialchars($email) ?>">
                            </div>
                            <hr class="modal-divider">
                            <div class="modal-form-group">
                                <label>Mật khẩu cũ</label>
                                <input type="password" name="old_password" class="modal-form-control" placeholder="Nhập mật khẩu cũ...">
                            </div>
                            <div class="modal-form-group">
                                <label>Mật khẩu mới</label>
                                <input type="password" name="new_password" class="modal-form-control" placeholder="Bỏ trống nếu giữ nguyên">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-modal-cancel" id="modal-cancel">Hủy</button>
                            <button type="submit" class="btn-modal-submit">Lưu thay đổi</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="modal-overlay" id="project-modal">
                <div class="project-modal-card">
                    <div class="modal-header">
                        <h3 id="modal-project-title">Chi tiết dự án</h3>
                        <button class="modal-close-btn" id="project-modal-close">&times;</button>
                    </div>
                    <div class="project-modal-body" id="modal-project-content">
                        <p style="text-align: center; color: #65676B;">Đang tải dữ liệu...</p>
                    </div>
                </div>
            </div>

            <div class="top-header">
                <div class="app-logo">
                    <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 5h2v5h-2z"/></svg>
                    <span>ArtFlow Studio - Admin Center</span>
                </div>
                
                <div class="user-nav-dropdown">
                    <div class="user-nav-btn" id="user-menu-toggle">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                        <span><?= htmlspecialchars($_SESSION['fullname']) ?></span>
                        <svg viewBox="0 0 24 24" style="width: 12px; height: 12px;"><path d="M7 10l5 5 5-5z"/></svg>
                    </div>
                    
                    <div class="dropdown-menu" id="user-dropdown-menu">
                        <div class="dropdown-header">
                            <div class="name"><?= htmlspecialchars($_SESSION['fullname']) ?></div>
                            <div class="role">Quản trị viên (Admin)</div>
                        </div>
                        <div class="dropdown-item" id="open-profile-modal">
                            <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c-.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c-.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                            Cài đặt tài khoản
                        </div>
                        <a href="logout.php" class="dropdown-item logout">
                            <svg viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                            Đăng xuất
                        </a>
                    </div>
                </div>
            </div>

            <div class="admin-container">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                        </div>
                        <div class="stat-info">
                            <h4>Tổng số dự án</h4>
                            <p class="num"><?= $totalProjects ?></p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        </div>
                        <div class="stat-info">
                            <h4>Tổng số tài khoản</h4>
                            <p class="num"><?= $totalUsers ?></p>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14z"/></svg>
                        Quản lý Dự án & Phê duyệt yêu cầu
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tên dự án</th>
                                <th>Khách hàng tạo</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($projects as $p): ?>
                            <tr>
                                <td>#<?= $p['id'] ?></td>
                                <td><b><?= htmlspecialchars($p['name']) ?></b></td>
                                <td><?= htmlspecialchars($p['client_name']) ?></td>
                                <td>
                                    <?php if($p['status'] === 'approved'): ?>
                                        <span class="badge badge-approved">Đã duyệt</span>
                                    <?php else: ?>
                                        <span class="badge badge-pending">Đang chờ duyệt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($p['status'] !== 'approved'): ?>
                                        <a href="admin.php?action=approve_project&id=<?= $p['id'] ?>" class="btn btn-success">Phê duyệt</a>
                                    <?php endif; ?>
                                    <a href="admin.php?action=delete_project&id=<?= $p['id'] ?>" onclick="return confirm('Xóa vĩnh viễn dự án này?')" class="btn btn-danger">Xóa</a>
                                    <button class="btn btn-view btn-open-project-modal" data-id="<?= $p['id'] ?>">Xem</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="section-card">
                    <div class="section-title">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                        Quản lý Thành viên hệ thống
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Họ tên</th>
                                <th>Tên đăng nhập (Username)</th>
                                <th>Vai trò</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($users as $u): ?>
                            <tr>
                                <td>#<?= $u['id'] ?></td>
                                <td><b><?= htmlspecialchars($u['fullname']) ?></b></td>
                                <td><?= htmlspecialchars($u['username']) ?></td>
                                <td><span class="badge badge-role"><?= ucfirst($u['role']) ?></span></td>
                                <td>
                                    <?php if($u['role'] !== 'admin'): ?>
                                        <a href="admin.php?action=delete_user&id=<?= $u['id'] ?>" onclick="return confirm('Xóa tài khoản này?')" class="btn btn-danger">Xóa</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    const userMenuToggle = document.getElementById('user-menu-toggle');
                    const userDropdownMenu = document.getElementById('user-dropdown-menu');
                    if (userMenuToggle && userDropdownMenu) {
                        userMenuToggle.addEventListener('click', (e) => {
                            e.stopPropagation();
                            userDropdownMenu.classList.toggle('show');
                        });
                        document.addEventListener('click', () => {
                            userDropdownMenu.classList.remove('show');
                        });
                    }

                    const profileModal = document.getElementById('profile-modal');
                    const openProfileModalBtn = document.getElementById('open-profile-modal');
                    const modalClose = document.getElementById('modal-close');
                    const modalCancel = document.getElementById('modal-cancel');

                    if (openProfileModalBtn && profileModal) {
                        openProfileModalBtn.addEventListener('click', (e) => {
                            e.preventDefault();
                            userDropdownMenu.classList.remove('show');
                            profileModal.classList.add('show');
                        });
                    }
                    if (modalClose && profileModal) modalClose.addEventListener('click', () => profileModal.classList.remove('show'));
                    if (modalCancel && profileModal) modalCancel.addEventListener('click', () => profileModal.classList.remove('show'));

                    const projectModal = document.getElementById('project-modal');
                    const projectModalClose = document.getElementById('project-modal-close');
                    const modalTitle = document.getElementById('modal-project-title');
                    const modalContent = document.getElementById('modal-project-content');

                    document.querySelectorAll('.btn-open-project-modal').forEach(btn => {
                        btn.addEventListener('click', () => {
                            const pId = btn.dataset.id;
                            projectModal.classList.add('show');
                            modalContent.innerHTML = '<p style="text-align: center; color: #65676B;">Đang tải dữ liệu...</p>';

                            fetch(`admin.php?action=get_project_details&id=${pId}`)
                            .then(res => res.json())
                            .then(data => {
                                if (!data.success) {
                                    modalContent.innerHTML = `<p style="color:red; text-align:center;">${data.message}</p>`;
                                    return;
                                }

                                modalTitle.innerText = data.project_name;
                                let html = `
                                    <div class="project-meta-box">
                                        <span>Khách hàng tạo: <b>${data.client_name}</b></span>
                                        <span>Nhà thiết kế phụ trách: <b style="color: #1877F2;">${data.designer_name}</b></span>
                                        <span>Trạng thái: <b>${data.status}</b></span>
                                    </div>
                                `;

                                if (data.designs.length === 0) {
                                    html += `<p style="text-align:center; color:#65676B;">Chưa có thiết kế nào trong dự án này.</p>`;
                                } else {
                                    data.designs.forEach(des => {
                                        html += `<div class="design-box-card"><h4 style="margin:0 0 10px; color:#1877F2; font-size:14px;">Màn hình: ${des.title}</h4>`;
                                        des.versions.forEach(ver => {
                                            let pinsHtml = '', feedbackHtml = '';
                                            if (ver.annotations.length === 0) {
                                                feedbackHtml = `<p style="font-size:12px; color:#65676B; font-style:italic;">Chưa có góp ý nào.</p>`;
                                            } else {
                                                ver.annotations.forEach((ann, idx) => {
                                                    pinsHtml += `<div class="pin-marker" style="left: ${ann.coordinate_x}%; top: ${ann.coordinate_y}%;" title="#${idx+1}: ${ann.content}">${idx+1}</div>`;
                                                    let statusBadge = ann.status === 'resolved' ? `<span class="badge-status status-resolved">Đã giải quyết</span>` : `<span class="badge-status status-open">Chưa xử lý</span>`;
                                                    feedbackHtml += `
                                                        <div class="feedback-card-item">
                                                            <div class="feedback-header">
                                                                <span>#${idx+1}. <b>${ann.fullname}</b></span>
                                                                <div>${statusBadge} <span style="font-size:11px; color:#65676B; margin-left:6px;">${ann.created_at}</span></div>
                                                            </div>
                                                            <div style="margin-top:4px; color:#333; white-space: pre-wrap;">${ann.content}</div>
                                                        </div>
                                                    `;
                                                });
                                            }
                                            html += `
                                                <div style="margin-bottom:15px;">
                                                    <p style="font-size:12px; font-weight:600; color:#4B4F56; margin-bottom:6px;">Phiên bản V${ver.version_number} (${ver.created_at})</p>
                                                    <div class="image-container-preview">
                                                        <img src="${ver.image_url}" alt="Design" onerror="this.src='./uploads/meo.png'">
                                                        ${pinsHtml}
                                                    </div>
                                                    <p style="font-size:13px; font-weight:600; margin:8px 0 4px;">Danh sách góp ý (${ver.annotations.length}):</p>
                                                    ${feedbackHtml}
                                                </div>
                                            `;
                                        });
                                        html += `</div>`;
                                    });
                                }
                                modalContent.innerHTML = html;
                            });
                        });
                    });

                    if (projectModalClose && projectModal) projectModalClose.addEventListener('click', () => projectModal.classList.remove('show'));
                });
            </script>
        </body>
        </html>
        <?>
        break;
}
?>