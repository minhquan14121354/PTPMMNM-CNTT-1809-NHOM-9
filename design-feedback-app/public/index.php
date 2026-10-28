<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

$currentUserId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'client';
$action = $_GET['action'] ?? 'home';
$selectedProjectId = $_GET['project_id'] ?? null;

if ($role === 'admin') {
    header("Location: admin.php");
    exit;
}

// Khởi tạo dữ liệu mẫu kèm theo tọa độ ghim và bình luận chuẩn xác
$chk = $db->query("SELECT COUNT(*) FROM projects WHERE name LIKE '%[Mẫu]%'")->fetchColumn();
if ($chk == 0) {
    $stmtU = $db->query("SELECT id FROM users WHERE role IN ('designer', 'admin') LIMIT 1");
    $designerId = $stmtU->fetchColumn() ?: 1;

    $db->prepare("INSERT INTO projects (name, client_id, status) VALUES (?, ?, 'approved')")->execute(['[Mẫu] Thi công kết cấu công trình ngoài trời', 1]);
    $p1Id = $db->lastInsertId();
    
    $db->prepare("INSERT INTO designs (project_id, title) VALUES (?, ?)")->execute([$p1Id, 'Mặt bằng tổng thể công trường']);
    $d1Id = $db->lastInsertId();

    $db->prepare("INSERT INTO image_versions (design_id, image_url, version_number) VALUES (?, './uploads/thiet ke 2.jfif', 1)")->execute([$d1Id]);
    $v1Id = $db->lastInsertId();

    $annotations1 = [
        [$v1Id, $designerId, 'Cần bổ sung thêm hàng rào tôn an toàn bao quanh khu vực móng cọc chính.', 35.5, 60.2, 'resolved'],
        [$v1Id, $designerId, 'Vị trí đặt cẩu tháp số 2 cần dịch chuyển sang hướng Tây Bắc để tối ưu tầm với vật liệu.', 52.0, 30.5, 'open'],
        [$v1Id, $designerId, 'Lối ra vào xe tải chở vật liệu xây dựng bị hẹp, cần mở rộng cổng chính.', 18.2, 75.8, 'open']
    ];
    foreach ($annotations1 as $ann) {
        $db->prepare("INSERT INTO annotations (image_version_id, user_id, content, coordinate_x, coordinate_y, status) VALUES (?, ?, ?, ?, ?, ?)")->execute($ann);
    }

    $db->prepare("INSERT INTO projects (name, client_id, status) VALUES (?, ?, 'approved')")->execute(['[Mẫu] Thiết kế nội thất căn hộ cao cấp 3D', 1]);
    $p2Id = $db->lastInsertId();

    $db->prepare("INSERT INTO designs (project_id, title) VALUES (?, ?)")->execute([$p2Id, 'Bản vẽ phối cảnh không gian phòng khách & phòng ngủ']);
    $d2Id = $db->lastInsertId();

    $db->prepare("INSERT INTO image_versions (design_id, image_url, version_number) VALUES (?, './uploads/thietke1.png', 1)")->execute([$d2Id]);
    $v2Id = $db->lastInsertId();

    $annotations2 = [
        [$v2Id, $designerId, 'Màu sắc tường phòng ngủ phụ nên đổi sang tông xanh mint dịu nhẹ thay vì màu ghi đậm.', 42.1, 55.4, 'resolved'],
        [$v2Id, $designerId, 'Khu vực bếp nấu thiếu hệ thống đèn LED âm tủ hắt sáng mặt bàn đá.', 75.3, 28.1, 'open'],
        [$v2Id, $designerId, 'Bố trí thêm ổ cắm điện thông minh cạnh bàn làm việc.', 25.4, 70.0, 'open']
    ];
    foreach ($annotations2 as $ann) {
        $db->prepare("INSERT INTO annotations (image_version_id, user_id, content, coordinate_x, coordinate_y, status) VALUES (?, ?, ?, ?, ?, ?)")->execute($ann);
    }
}

function checkProjectAccess($db, $userId, $userRole, $projectId) {
    if ($userRole === 'admin') return true;
    $stmtSt = $db->prepare("SELECT status FROM projects WHERE id = ?");
    $stmtSt->execute([$projectId]);
    $status = $stmtSt->fetchColumn();
    if ($status !== 'approved' && $userRole !== 'designer') return false;

    $stmt = $db->prepare("SELECT COUNT(*) FROM project_members WHERE project_id = ? AND user_id = ?");
    $stmt->execute([$projectId, $userId]);
    if ($stmt->fetchColumn() > 0) return true;

    $stmtOwn = $db->prepare("SELECT COUNT(*) FROM projects WHERE id = ? AND client_id = ?");
    $stmtOwn->execute([$projectId, $userId]);
    return $stmtOwn->fetchColumn() > 0;
}

$stmtUser = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$currentUserId]);
$currentUserData = $stmtUser->fetch(PDO::FETCH_ASSOC);
$fullname = $currentUserData['fullname'] ?? $_SESSION['fullname'];
$phone = $currentUserData['phone'] ?? '';
$email = $currentUserData['email'] ?? '';

// Xử lý các Action AJAX / POST
if ($action === 'update_profile' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $newFullname = trim($_POST['fullname'] ?? '');
    $newPhone = trim($_POST['phone'] ?? '');
    $newEmail = trim($_POST['email'] ?? '');
    $oldPasswordInput = $_POST['old_password'] ?? '';
    $newPasswordInput = $_POST['new_password'] ?? '';

    $requiresPasswordVerification = (!empty($newPasswordInput) || ($newPhone !== $phone && !empty($phone)) || ($newEmail !== $email && !empty($email)));

    if ($requiresPasswordVerification) {
        if (empty($oldPasswordInput) || (!password_verify($oldPasswordInput, $currentUserData['password']) && $oldPasswordInput !== $currentUserData['password'])) {
            echo "<script>alert('Mật khẩu cũ không chính xác!'); window.location.href='index.php" . ($selectedProjectId ? "?project_id=" . $selectedProjectId : "") . "';</script>";
            exit;
        }
    }

    if (!empty($newFullname)) {
        if (!empty($newPasswordInput)) {
            $hashed = password_hash($newPasswordInput, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET fullname = ?, phone = ?, email = ?, password = ? WHERE id = ?")->execute([$newFullname, $newPhone, $newEmail, $hashed, $currentUserId]);
        } else {
            $db->prepare("UPDATE users SET fullname = ?, phone = ?, email = ? WHERE id = ?")->execute([$newFullname, $newPhone, $newEmail, $currentUserId]);
        }
        $_SESSION['fullname'] = $newFullname;
        header("Location: index.php" . ($selectedProjectId ? "?project_id=" . $selectedProjectId : ""));
        exit;
    }
}

if ($action === 'create_project' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pName = trim($_POST['project_name'] ?? '');
    $status = ($role === 'designer') ? 'approved' : 'pending';
    
    if (!empty($pName)) {
        $stmtP = $db->prepare("INSERT INTO projects (name, client_id, status) VALUES (?, ?, ?)");
        $stmtP->execute([$pName, $currentUserId, $status]);
        $newProjId = $db->lastInsertId();

        $memberRole = ($role === 'designer') ? 'designer' : 'client';
        $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, ?)")->execute([$newProjId, $currentUserId, $memberRole]);

        $imgUrl = './uploads/meo.png';
        if (isset($_FILES['init_image']) && $_FILES['init_image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['init_image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
            $fileName = time() . '_' . $cleanName . '.' . $ext;
            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
                $imgUrl = './uploads/' . $fileName;
            }
        }

        $stmtD = $db->prepare("INSERT INTO designs (project_id, title) VALUES (?, ?)");
        $stmtD->execute([$newProjId, 'Màn hình khởi tạo']);
        $newDesignId = $db->lastInsertId();
        
        $db->prepare("INSERT INTO image_versions (design_id, image_url, version_number) VALUES (?, ?, 1)")->execute([$newDesignId, $imgUrl]);
        
        header("Location: index.php?project_id=" . $newProjId);
        exit;
    }
}

if ($action === 'leave_project' && isset($_GET['project_id'])) {
    $pId = $_GET['project_id'];
    $db->prepare("DELETE FROM project_members WHERE project_id = ? AND user_id = ?")->execute([$pId, $currentUserId]);
    setcookie('left_proj_' . $pId . '_' . $currentUserId, '1', time() + (86400 * 30), "/");
    header("Location: index.php");
    exit;
}

if ($action === 'delete_project' && $role === 'designer' && isset($_GET['project_id'])) {
    $pId = $_GET['project_id'];
    if (checkProjectAccess($db, $currentUserId, $role, $pId)) {
        $db->prepare("DELETE a FROM annotations a JOIN image_versions iv ON a.image_version_id = iv.id JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
        $db->prepare("DELETE al FROM annotation_likes al JOIN annotations a ON al.annotation_id = a.id JOIN image_versions iv ON a.image_version_id = iv.id JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
        $db->prepare("DELETE iv FROM image_versions iv JOIN designs d ON iv.design_id = d.id WHERE d.project_id = ?")->execute([$pId]);
        $db->prepare("DELETE FROM designs WHERE project_id = ?")->execute([$pId]);
        $db->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$pId]);
        $db->prepare("DELETE FROM projects WHERE id = ?")->execute([$pId]);
    }
    header("Location: index.php");
    exit;
}

if ($action === 'designer_take_project' && $role === 'designer' && isset($_GET['project_id'])) {
    $pId = $_GET['project_id'];
    $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, 'designer')")->execute([$pId, $currentUserId]);
    header("Location: index.php?project_id=" . $pId);
    exit;
}

if ($action === 'get_annotations') {
    $versionId = $_GET['version_id'] ?? 1;
    $stmtProj = $db->prepare("SELECT d.project_id FROM image_versions iv JOIN designs d ON iv.design_id = d.id WHERE iv.id = ?");
    $stmtProj->execute([$versionId]);
    $projectId = $stmtProj->fetchColumn();

    if ($projectId && !checkProjectAccess($db, $currentUserId, $role, $projectId)) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền truy cập dự án này!']);
        exit;
    }

    $stmt = $db->prepare("SELECT a.*, u.fullname, 
        (SELECT COUNT(*) FROM annotation_likes al WHERE al.annotation_id = a.id) as like_count,
        (SELECT COUNT(*) FROM annotation_likes al2 WHERE al2.annotation_id = a.id AND al2.user_id = ?) as is_liked
        FROM annotations a JOIN users u ON a.user_id = u.id WHERE a.image_version_id = ? ORDER BY a.id DESC");
    $stmt->execute([$currentUserId, $versionId]);
    echo json_encode(['success' => true, 'current_user_id' => $currentUserId, 'user_role' => $role, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    exit;
}

if ($action === 'toggle_status') {
    $annotationId = $_POST['annotation_id'] ?? 0;
    $stmt = $db->prepare("SELECT status FROM annotations WHERE id = ?");
    $stmt->execute([$annotationId]);
    $currStatus = $stmt->fetchColumn();
    $newStatus = ($currStatus === 'resolved') ? 'open' : 'resolved';
    $db->prepare("UPDATE annotations SET status = ? WHERE id = ?")->execute([$newStatus, $annotationId]);
    echo json_encode(['success' => true, 'status' => $newStatus]);
    exit;
}

if ($action === 'add_annotation' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $versionId = $_POST['version_id'] ?? 1;
    $content = $_POST['content'] ?? '';
    $cx = $_POST['coordinate_x'] ?? 0;
    $cy = $_POST['coordinate_y'] ?? 0;

    $stmtProj = $db->prepare("SELECT d.project_id FROM image_versions iv JOIN designs d ON iv.design_id = d.id WHERE iv.id = ?");
    $stmtProj->execute([$versionId]);
    $projectId = $stmtProj->fetchColumn();

    if ($projectId && checkProjectAccess($db, $currentUserId, $role, $projectId)) {
        $stmt = $db->prepare("INSERT INTO annotations (image_version_id, user_id, content, coordinate_x, coordinate_y) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$versionId, $currentUserId, $content, $cx, $cy]);
    }
    header("Location: index.php" . ($selectedProjectId ? "?project_id=" . $selectedProjectId : ""));
    exit;
}

if ($action === 'delete_annotation') {
    $annotationId = $_POST['annotation_id'] ?? 0;
    if ($role === 'designer') {
        $db->prepare("DELETE FROM annotations WHERE id = ?")->execute([$annotationId]);
    } else {
        $db->prepare("DELETE FROM annotations WHERE id = ? AND user_id = ?")->execute([$annotationId, $currentUserId]);
    }
    $db->prepare("DELETE FROM annotation_likes WHERE annotation_id = ?")->execute([$annotationId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_annotation_like') {
    $annotationId = $_POST['annotation_id'] ?? 0;
    $stmt = $db->prepare("SELECT * FROM annotation_likes WHERE annotation_id = ? AND user_id = ?");
    $stmt->execute([$annotationId, $currentUserId]);
    if ($stmt->rowCount() > 0) {
        $db->prepare("DELETE FROM annotation_likes WHERE annotation_id = ? AND user_id = ?")->execute([$annotationId, $currentUserId]);
    } else {
        $db->prepare("INSERT INTO annotation_likes (annotation_id, user_id) VALUES (?, ?)")->execute([$annotationId, $currentUserId]);
    }
    $total = $db->prepare("SELECT COUNT(*) FROM annotation_likes WHERE annotation_id = ?"); 
    $total->execute([$annotationId]);
    echo json_encode(['status' => $stmt->rowCount() > 0 ? 'unliked' : 'liked', 'total' => $total->fetchColumn()]);
    exit;
}

if ($action === 'upload_image' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['design_image'])) {
    if ($role !== 'designer') {
        echo json_encode(['success' => false, 'message' => 'Chỉ Designer mới có quyền tải lên thiết kế!']);
        exit;
    }
    $designId = $_POST['design_id'] ?? 1;
    
    $stmtPr = $db->prepare("SELECT project_id FROM designs WHERE id = ?");
    $stmtPr->execute([$designId]);
    $pId = $stmtPr->fetchColumn();
    if (!checkProjectAccess($db, $currentUserId, $role, $pId)) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền trong dự án này!']);
        exit;
    }

    $file = $_FILES['design_image'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $cleanName = preg_replace('/[^a-zA-Z0-9_\-]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
    if (empty($cleanName)) $cleanName = 'design_ver';
    $fileName = time() . '_' . $cleanName . '.' . $ext;
    
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
    
    if (move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) {
        $imgUrl = './uploads/' . $fileName;
        $stmtMax = $db->prepare("SELECT IFNULL(MAX(version_number), 0) + 1 FROM image_versions WHERE design_id = ?");
        $stmtMax->execute([$designId]);
        $nextVersion = $stmtMax->fetchColumn();

        $stmtIns = $db->prepare("INSERT INTO image_versions (design_id, image_url, version_number) VALUES (?, ?, ?)");
        $stmtIns->execute([$designId, $imgUrl, $nextVersion]);
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi lưu tệp.']);
    }
    exit;
}

// Lấy danh sách dự án hiển thị trang chủ
if ($role === 'designer') {
    $stmtMyProjs = $db->prepare("
        SELECT DISTINCT p.* FROM projects p 
        LEFT JOIN project_members pm ON p.id = pm.project_id 
        WHERE p.client_id = ? OR pm.user_id = ?
        ORDER BY p.id DESC
    ");
    $stmtMyProjs->execute([$currentUserId, $currentUserId]);
    $myProjects = $stmtMyProjs->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmtMyProjs = $db->prepare("
        SELECT DISTINCT p.* FROM projects p 
        WHERE p.status = 'approved' OR p.client_id = ?
    ");
    $stmtMyProjs->execute([$currentUserId]);
    $allApproved = $stmtMyProjs->fetchAll(PDO::FETCH_ASSOC);

    $myProjects = [];
    foreach ($allApproved as $mp) {
        if ($mp['client_id'] == $currentUserId) {
            $myProjects[] = $mp;
        } else {
            if (!isset($_COOKIE['left_proj_' . $mp['id'] . '_' . $currentUserId])) {
                $db->prepare("INSERT IGNORE INTO project_members (project_id, user_id, role) VALUES (?, ?, 'client')")->execute([$mp['id'], $currentUserId]);
                $myProjects[] = $mp;
            }
        }
    }
}

if (!$selectedProjectId && !empty($myProjects)) {
    $selectedProjectId = $myProjects[0]['id'];
}

$designs = [];
$projectDesignerName = 'Chưa phân công';
$totalAnnotationsCount = 0;

if ($selectedProjectId) {
    $stmtDesName = $db->prepare("
        SELECT u.fullname FROM project_members pm 
        JOIN users u ON pm.user_id = u.id 
        WHERE pm.project_id = ? AND (pm.role = 'designer' OR u.role = 'designer') LIMIT 1
    ");
    $stmtDesName->execute([$selectedProjectId]);
    $foundDesigner = $stmtDesName->fetchColumn();
    
    if ($foundDesigner) {
        $projectDesignerName = $foundDesigner;
    } else {
        $stmtCreator = $db->prepare("SELECT u.fullname FROM projects p JOIN users u ON p.client_id = u.id WHERE p.id = ? AND u.role = 'designer'");
        $stmtCreator->execute([$selectedProjectId]);
        $creatorName = $stmtCreator->fetchColumn();
        if ($creatorName) {
            $projectDesignerName = $creatorName;
        }
    }

    $stmtDesigns = $db->prepare("
        SELECT d.*, p.name as project_name 
        FROM designs d 
        JOIN projects p ON d.project_id = p.id
        WHERE d.project_id = ? 
        ORDER BY d.id DESC
    ");
    $stmtDesigns->execute([$selectedProjectId]);

    while ($d = $stmtDesigns->fetch(PDO::FETCH_ASSOC)) {
        $stmtVer = $db->prepare("SELECT id, design_id, image_url, version_number, created_at FROM image_versions WHERE design_id = ? ORDER BY version_number DESC");
        $stmtVer->execute([$d['id']]);
        $d['versions'] = $stmtVer->fetchAll(PDO::FETCH_ASSOC);
        $designs[] = $d;

        foreach ($d['versions'] as $ver) {
            $stmtCountAn = $db->prepare("SELECT COUNT(*) FROM annotations WHERE image_version_id = ?");
            $stmtCountAn->execute([$ver['id']]);
            $totalAnnotationsCount += $stmtCountAn->fetchColumn();
        }
    }
}

$availableProjects = [];
if ($role === 'designer') {
    $stmtAvail = $db->prepare("
        SELECT p.* FROM projects p 
        WHERE p.status = 'approved' AND p.id NOT IN (
            SELECT project_id FROM project_members WHERE user_id = ?
        )
    ");
    $stmtAvail->execute([$currentUserId]);
    $availableProjects = $stmtAvail->fetchAll(PDO::FETCH_ASSOC);
}

$totalDesigns = count($designs);
$roleText = ($role === 'designer') ? 'Nhà thiết kế (Designer)' : 'Khách hàng / Người xem (Client)';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ArtFlow Studio - Giao diện Dự án</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #F0F2F5; color: #050505; margin: 0; padding: 0; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        
        .top-header { flex-shrink: 0; background: #FFF; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.08); z-index: 1000; }
        .app-logo { font-size: 20px; font-weight: 700; color: #1877F2; display: flex; align-items: center; gap: 10px; }
        .app-logo svg { width: 24px; height: 24px; fill: #1877F2; }

        .icon-svg { width: 16px; height: 16px; fill: currentColor; display: inline-block; vertical-align: middle; flex-shrink: 0; }
        .icon-svg-lg { width: 20px; height: 20px; fill: currentColor; display: inline-block; vertical-align: middle; flex-shrink: 0; }

        .user-nav-dropdown { position: relative; display: inline-block; }
        .user-nav-btn { background: #F0F2F5; border: 1px solid #CCD0D5; padding: 6px 14px; border-radius: 20px; display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; font-size: 13px; color: #050505; transition: 0.2s; }
        .user-nav-btn:hover { background: #E4E6EB; }

        .dropdown-menu { display: none; position: absolute; right: 0; top: 100%; margin-top: 8px; background: #FFF; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); width: 220px; border: 1px solid #E4E6EB; z-index: 2000; overflow: hidden; animation: fadeIn 0.15s ease-out; }
        .dropdown-menu.show { display: block; }
        .dropdown-header { padding: 12px 16px; background: #F8F9FA; border-bottom: 1px solid #E4E6EB; }
        .dropdown-header .name { font-weight: 700; font-size: 13px; color: #050505; }
        .dropdown-header .role { font-size: 11px; color: #65676B; margin-top: 2px; }
        .dropdown-item { display: flex; align-items: center; gap: 10px; padding: 10px 16px; color: #050505; text-decoration: none; font-size: 13px; font-weight: 500; transition: 0.15s; cursor: pointer; }
        .dropdown-item:hover { background: #EBF5FF; color: #1877F2; }
        .dropdown-item.logout { color: #E41E3F; border-top: 1px solid #E4E6EB; }
        .dropdown-item.logout:hover { background: #FFEBEE; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; animation: fadeIn 0.2s ease-out; }
        .modal-overlay.show { display: flex; }
        .modal-card { background: #FFF; border-radius: 16px; width: 100%; max-width: 450px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; box-sizing: border-box; }
        .modal-header { padding: 16px 20px; background: #F8F9FA; border-bottom: 1px solid #E4E6EB; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { margin: 0; font-size: 16px; font-weight: 700; color: #050505; display: flex; align-items: center; gap: 8px; }
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

        .app-container { flex: 1; display: grid; grid-template-columns: 280px minmax(500px, 1fr) 280px; justify-content: center; gap: 20px; max-width: 1400px; margin: 0 auto; padding: 15px 15px 0; overflow: hidden; }
        .sidebar { display: flex; flex-direction: column; gap: 15px; height: 100%; overflow-y: auto; padding-bottom: 30px; }
        .widget { background: #FFF; border-radius: 12px; padding: 15px; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .main-feed { display: flex; flex-direction: column; height: 100%; overflow-y: auto; padding-bottom: 100px; scroll-behavior: smooth; }
        
        .proj-btn { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 10px 12px; border-radius: 8px; border: 1px solid #E4E6EB; background: #F8F9FA; margin-bottom: 6px; font-size: 13px; font-weight: 600; color: #050505; text-decoration: none; box-sizing: border-box; transition: 0.2s; }
        .proj-btn:hover { background: #EBF5FF; border-color: #1877F2; color: #1877F2; }
        .proj-btn.active { background: #1877F2; color: #FFF; border-color: #1877F2; }

        .status-tag { font-size: 11px; padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 4px; font-weight: normal; }
        .tag-pending { background: #FFF3CD; color: #856404; }
        .tag-approved { background: #D4EDDA; color: #155724; }

        .design-post { background: #FFF; border-radius: 12px; padding-top: 15px; box-shadow: 0 1px 3px rgba(0,0,0,0.15); margin-bottom: 25px; flex-shrink: 0; }
        .post-header { padding: 0 15px 10px; font-size: 14px; font-weight: 600; color: #65676B; display: flex; justify-content: space-between; align-items: center; }
        .version-bar { padding: 8px 15px; background: #F8F9FA; border-top: 1px solid #E4E6EB; border-bottom: 1px solid #E4E6EB; display: flex; align-items: center; justify-content: space-between; font-size: 13px; flex-wrap: wrap; gap: 8px; }
        .version-select { padding: 4px 8px; border-radius: 6px; border: 1px solid #CCD0D5; font-weight: 600; outline: none; background: #FFF; cursor: pointer; font-family: inherit; }
        
        .image-container { position: relative; width: 100%; height: 450px; display: flex; align-items: center; justify-content: center; user-select: none; background: #1a1a1a; overflow: hidden; }
        .design-image { max-width: 100%; max-height: 100%; object-fit: contain; cursor: crosshair; display: block; }

        .compare-wrapper { display: none; grid-template-columns: 1fr 1fr; gap: 10px; padding: 10px; background: #E9ECEF; border-bottom: 1px solid #DDD; }
        .compare-box { background: #FFF; padding: 5px; border-radius: 6px; text-align: center; height: 320px; display: flex; flex-direction: column; align-items: center; justify-content: center; }
        .compare-box img { max-width: 100%; max-height: 280px; object-fit: contain; border-radius: 4px; display: block; }
        .compare-title { font-size: 12px; font-weight: bold; margin-bottom: 5px; color: #1877F2; }

        .pin-marker { position: absolute; width: 26px; height: 26px; background: #FF3B30; color: white; border-radius: 50%; font-size: 12px; font-weight: bold; display: flex; align-items: center; justify-content: center; transform: translate(-50%, -50%); border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); pointer-events: none; z-index: 10; transition: transform 0.2s; }
        .pin-marker.highlight { transform: translate(-50%, -50%) scale(1.4); background: #28A745; z-index: 20; }
        
        .annotation-popup { position: absolute; background: white; padding: 12px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.25); width: 270px; z-index: 100; display: none; border: 1px solid #CCD0D5; }
        .annotation-popup textarea { width: 100%; height: 60px; border: 1px solid #CCD0D5; border-radius: 6px; padding: 8px; box-sizing: border-box; resize: none; font-family: inherit; font-size: 13px; outline: none; }
        .popup-actions { display: flex; gap: 8px; margin-top: 8px; }
        .annotation-popup button { flex: 1; border: none; padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; }
        .btn-submit-form { background: #1877F2; color: white; }
        .btn-close-popup { background: #E4E6EB; color: #4B4F56; }
        .comments-section-wrapper { display: none; padding: 15px; border-top: 1px solid #E4E6EB; }
        .filter-bar { display: flex; gap: 8px; margin-bottom: 12px; font-size: 12px; }
        .filter-btn { padding: 4px 10px; border-radius: 12px; border: 1px solid #CCD0D5; background: #FFF; cursor: pointer; font-weight: 600; }
        .filter-btn.active { background: #1877F2; color: #FFF; border-color: #1877F2; }
        .feedback-card { display: flex; gap: 10px; margin-bottom: 12px; background: #F9FAFB; padding: 10px; border-radius: 8px; border: 1px solid #F0F2F5; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background-image: url('https://ui-avatars.com/api/?name=User&background=1877F2&color=fff'); background-size: cover; flex-shrink: 0; }
        .comment-content { background: #FFF; padding: 10px 14px; border-radius: 12px; width: 100%; box-sizing: border-box; border: 1px solid #E4E6EB; }
        .comment-author { font-weight: 700; font-size: 13px; margin-bottom: 4px; color: #050505; }
        .comment-actions { display: flex; gap: 12px; margin-top: 6px; margin-left: 5px; align-items: center; }
        .comment-action-btn { cursor: pointer; border: none; background: none; padding: 0; font-family: inherit; font-size: 12px; font-weight: 700; color: #65676B; }
        .comment-action-btn.resolved { color: #2E7D32; background: #E8F5E9; padding: 2px 8px; border-radius: 4px; }
        .comment-action-btn.open { color: #C62828; background: #FFEBEE; padding: 2px 8px; border-radius: 4px; }
        .btn-delete-comment { color: #E41E3F; }
        .btn-export-pdf { background: #28A745; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-toggle-compare { background: #6C757D; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
        .btn-delete-project { background: #DC3545; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-delete-project:hover { background: #c82333; }
        .btn-leave-project { background: #FFC107; color: #212529; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 12px; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-leave-project:hover { background: #e0a800; }
        #toast { position: fixed; bottom: -100px; left: 50%; transform: translateX(-50%); background: #333; color: white; padding: 12px 24px; border-radius: 30px; font-weight: 600; font-size: 14px; transition: bottom 0.3s; z-index: 9999; }
        #toast.show { bottom: 30px; }
    </style>
</head>
<body>
    <div id="toast"></div>
    
    <div class="modal-overlay" id="profile-modal">
        <div class="modal-card">
            <div class="modal-header">
                <h3>
                    <svg class="icon-svg-lg" style="fill:#1877F2;" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c-.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c-.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    Cài đặt Tài khoản
                </h3>
                <button class="modal-close-btn" id="modal-close">&times;</button>
            </div>
            <form action="index.php?action=update_profile<?= $selectedProjectId ? '&project_id=' . $selectedProjectId : '' ?>" method="POST">
                <div class="modal-body">
                    <div class="modal-form-group">
                        <label>Họ tên hiển thị</label>
                        <input type="text" name="fullname" class="modal-form-control" value="<?= htmlspecialchars($fullname) ?>" placeholder="Họ tên..." required>
                    </div>
                    <div class="modal-form-group">
                        <label>Số điện thoại</label>
                        <input type="text" name="phone" class="modal-form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="Nhập số điện thoại...">
                    </div>
                    <div class="modal-form-group">
                        <label>Email</label>
                        <input type="email" name="email" class="modal-form-control" value="<?= htmlspecialchars($email) ?>" placeholder="Nhập địa chỉ email...">
                    </div>

                    <hr class="modal-divider">

                    <div class="modal-form-group">
                        <label>Mật khẩu cũ <span style="font-weight:normal; color:#65676B; font-size:11px;">(Bắt buộc khi đổi SĐT, Email có sẵn hoặc đổi mật khẩu mới)</span></label>
                        <input type="password" name="old_password" class="modal-form-control" placeholder="Nhập mật khẩu cũ nếu thay đổi thông tin trên...">
                    </div>
                    <div class="modal-form-group">
                        <label>Mật khẩu mới</label>
                        <input type="password" name="new_password" class="modal-form-control" placeholder="Bỏ trống nếu giữ nguyên mật khẩu cũ">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-modal-cancel" id="modal-cancel">Hủy</button>
                    <button type="submit" class="btn-modal-submit">Lưu thay đổi</button>
                </div>
            </form>
        </div>
    </div>

    <div class="top-header">
        <div class="app-logo">
            <svg class="icon-svg-lg" viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 5h2v5h-2z"/></svg>
            <span>ArtFlow Studio</span>
        </div>
        
        <div class="user-nav-dropdown">
            <div class="user-nav-btn" id="user-menu-toggle">
                <svg class="icon-svg" style="fill:#65676B;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                <span><?= htmlspecialchars($fullname) ?></span>
                <svg class="icon-svg" style="width:12px; height:12px; fill:#65676B;" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>
            </div>
            
            <div class="dropdown-menu" id="user-dropdown-menu">
                <div class="dropdown-header">
                    <div class="name"><?= htmlspecialchars($fullname) ?></div>
                    <div class="role"><?= $roleText ?></div>
                </div>
                <div class="dropdown-item" id="open-profile-modal">
                    <svg class="icon-svg" style="fill:#1877F2;" viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c-.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c-.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    Cài đặt tài khoản
                </div>
                <a href="logout.php" class="dropdown-item logout">
                    <svg class="icon-svg" style="fill:#E41E3F;" viewBox="0 0 24 24"><path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/></svg>
                    Đăng xuất
                </a>
            </div>
        </div>
    </div>

    <div class="app-container">
        <div class="sidebar left-sidebar">
            <div class="widget">
                <h3>Điều hướng</h3>
                <p style="font-size: 13px; color: #65676B;">Vai trò: <b style="color:#1877F2;"><?= $roleText ?></b></p>

                <hr style="border:0.5px solid #eee; margin: 10px 0;">
                <h4 style="margin: 5px 0 10px; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                    <svg class="icon-svg" style="fill:#1877F2;" viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    <?= ($role === 'designer') ? 'Tạo Dự án Thiết kế mới' : 'Tạo Yêu cầu Dự án mới' ?>
                </h4>
                <form action="index.php?action=create_project" method="POST" enctype="multipart/form-data">
                    <input type="text" name="project_name" placeholder="<?= ($role === 'designer') ? 'Tên dự án mới...' : 'Yêu cầu / Tên dự án...' ?>" required style="width: 100%; padding: 8px; margin-bottom: 8px; border: 1px solid #ccd0d5; border-radius: 6px; box-sizing: border-box; font-size: 13px; font-family: inherit;">
                    
                    <label style="font-size: 12px; color: #65676B; display: block; margin-bottom: 4px;">Ảnh minh họa / Thiết kế ban đầu:</label>
                    <input type="file" name="init_image" accept="image/png, image/jpeg, image/jfif" style="width: 100%; margin-bottom: 8px; font-size: 12px; font-family: inherit;">
                    
                    <button type="submit" style="width: 100%; background: #1877F2; color: white; border: none; padding: 8px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px; font-family: inherit;">
                        <?= ($role === 'designer') ? 'Tạo Dự án' : 'Gửi yêu cầu cho Admin' ?>
                    </button>
                </form>

                <hr style="border:0.5px solid #eee; margin: 15px 0 10px;">
                <h4 style="margin: 0 0 10px; font-size: 14px; color: #1877F2; display: flex; align-items: center; gap: 6px;">
                    <svg class="icon-svg" style="fill:#1877F2;" viewBox="0 0 24 24"><path d="M10 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2h-8l-2-2z"/></svg>
                    <?= ($role === 'designer') ? 'Dự án của bạn' : 'Danh sách Dự án' ?>
                </h4>
                <?php if (empty($myProjects)): ?>
                    <p style="font-size: 12px; color: #65676B;">Chưa có dự án nào.</p>
                <?php else: ?>
                    <?php foreach($myProjects as $mp): ?>
                        <?php 
                            $isActive = ($mp['id'] == $selectedProjectId);
                            $st = $mp['status'];
                            $stText = ($st === 'approved') ? 'Đã duyệt' : 'Đang chờ Admin duyệt';
                            $stClass = ($st === 'approved') ? 'tag-approved' : 'tag-pending';
                        ?>
                        <a href="index.php?project_id=<?= $mp['id'] ?>" class="proj-btn <?= $isActive ? 'active' : '' ?>">
                            <svg class="icon-svg" style="fill: <?= $isActive ? '#FFF' : '#65676B' ?>;" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                            <div style="flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <?= htmlspecialchars($mp['name']) ?><br>
                                <?php if($role === 'client'): ?>
                                    <span class="status-tag <?= $stClass ?>"><?= $stText ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($role === 'designer' && !empty($availableProjects)): ?>
                    <hr style="border:0.5px solid #eee; margin: 15px 0 10px;">
                    <h4 style="margin: 0 0 10px; font-size: 14px; color: #28A745; display: flex; align-items: center; gap: 6px;">
                        <svg class="icon-svg" style="fill:#28A745;" viewBox="0 0 24 24"><path d="M12 2l-5.5 9h11z"/><circle cx="12" cy="17" r="3"/></svg>
                        Dự án Chờ Nhận
                    </h4>
                    <?php foreach($availableProjects as $ap): ?>
                        <div style="background: #F0FFF4; border: 1px solid #C3E6CB; padding: 8px; border-radius: 8px; margin-bottom: 6px;">
                            <b style="font-size: 12px; color: #155724;"><?= htmlspecialchars($ap['name']) ?></b><br>
                            <a href="index.php?action=designer_take_project&project_id=<?= $ap['id'] ?>" style="color: #fff; background: #28a745; padding: 3px 6px; border-radius: 4px; text-decoration: none; font-size: 11px; display: inline-block; margin-top: 4px; font-weight: 600;">Nhận dự án</a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-feed" id="main-feed">
            <?php if ($selectedProjectId): ?>
            <div class="widget" style="margin-bottom: 15px; padding: 12px 15px; background: #EBF5FF; border: 1px solid #B2D5FF; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <span style="font-size: 13px; color: #1877F2; display: flex; align-items: center; gap: 6px;">
                    <svg class="icon-svg" style="fill:#1877F2;" viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9 0 2.12.74 4.07 1.97 5.61L4.25 19.5l1.89.72c1.54 1.23 3.49 1.97 5.61 1.97 4.97 0 9-4.03 9-9s-4.03-9-9-9zm0 16c-3.87 0-7-3.13-7-7s3.13-7 7-7 7 3.13 7 7-3.13 7-7 7zm1-11h-2v6h6v-2h-4z"/></svg>
                    <?php if ($role === 'designer'): ?>
                        <b>Quản lý phiên bản thiết kế của bạn</b>
                    <?php else: ?>
                        <b>Nhà thiết kế phụ trách:</b> <?= htmlspecialchars($projectDesignerName) ?>
                    <?php endif; ?>
                </span>

                <div style="display: flex; gap: 10px; align-items: center;">
                    <?php if ($role === 'designer'): ?>
                        <form id="upload-form" enctype="multipart/form-data" style="display: flex; gap: 8px; align-items: center;">
                            <input type="hidden" name="design_id" value="<?= $designs[0]['id'] ?? 1 ?>">
                            <input type="file" name="design_image" accept="image/png, image/jpeg, image/jfif" required style="font-size: 12px; font-family: inherit;">
                            <button type="submit" style="background: #1877F2; color: white; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 12px; font-family: inherit;">Tải bản mới (V2, V3)</button>
                        </form>
                        <a href="index.php?action=delete_project&project_id=<?= $selectedProjectId ?>" onclick="return confirm('Bạn có chắc chắn muốn xóa vĩnh viễn dự án này không?')" class="btn-delete-project">🗑️ Xóa dự án</a>
                    <?php else: ?>
                        <a href="index.php?action=leave_project&project_id=<?= $selectedProjectId ?>" onclick="return confirm('Bạn có muốn ẩn/thoát khỏi dự án này không? Các bình luận và góp ý của bạn vẫn được lưu giữ trên hệ thống.')" class="btn-leave-project">🚪 Thoát khỏi dự án</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($designs)): ?>
                <div class="widget" style="text-align: center; padding: 40px; color: #65676B;">
                    <h3>Chưa có dữ liệu thiết kế khả dụng</h3>
                    <p style="font-size: 13px;">Hãy chọn một dự án hợp lệ ở cột bên trái.</p>
                </div>
            <?php endif; ?>

            <?php foreach($designs as $design): ?>
            <?php 
                $latestVersion = $design['versions'][0] ?? null;
                if (!$latestVersion) continue;
                
                $rawUrl = $latestVersion['image_url'] ?? 'meo.png';
                $imageUrl = './uploads/' . rawurlencode(basename($rawUrl));

                $v1Version = end($design['versions']);
                $v1RawUrl = $v1Version['image_url'] ?? 'meo.png';
                $v1ImageUrl = './uploads/' . rawurlencode(basename($v1RawUrl));
            ?>
            <div class="design-post" data-design-id="<?= $design['id'] ?>">
                <div class="post-header">
                    <span>[<?= htmlspecialchars($design['project_name']) ?>] - <?= htmlspecialchars($design['title']) ?></span>
                    <button class="btn-export-pdf" onclick="window.print()">Xuất Báo cáo</button>
                </div>
                
                <div class="version-bar">
                    <div>
                        <span>Phiên bản xem:</span>
                        <select class="version-select" data-design-id="<?= $design['id'] ?>">
                            <?php foreach($design['versions'] as $ver): ?>
                                <?php 
                                    $vRaw = $ver['image_url'] ?? '';
                                    $vUrl = './uploads/' . rawurlencode(basename($vRaw));
                                ?>
                                <option value="<?= $ver['id'] ?>" data-url="<?= htmlspecialchars($vUrl) ?>" data-ver-num="<?= $ver['version_number'] ?>">
                                    Phiên bản V<?= $ver['version_number'] ?> (<?= date('d/m/Y H:i', strtotime($ver['created_at'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn-toggle-compare">So sánh V1</button>
                </div>

                <div class="compare-wrapper">
                    <div class="compare-box">
                        <div class="compare-title">Phiên bản V1 (Gốc)</div>
                        <img src="<?= htmlspecialchars($v1ImageUrl) ?>" alt="V1" onerror="this.src='./uploads/meo.png'">
                    </div>
                    <div class="compare-box">
                        <div class="compare-title">Phiên bản Đang chọn</div>
                        <img class="compare-current-img" src="<?= htmlspecialchars($imageUrl) ?>" alt="Current" onerror="this.src='./uploads/meo.png'">
                    </div>
                </div>

                <div class="image-container" data-version-id="<?= $latestVersion['id'] ?>">
                    <img class="design-image" src="<?= htmlspecialchars($imageUrl) ?>" alt="Thiết kế" onerror="this.src='./uploads/meo.png'">
                    
                    <form action="index.php?action=add_annotation" method="POST" class="annotation-popup">
                        <input type="hidden" name="version_id" class="input-version-id" value="<?= $latestVersion['id'] ?>">
                        <input type="hidden" name="coordinate_x" class="input-coord-x" value="0">
                        <input type="hidden" name="coordinate_y" class="input-coord-y" value="0">
                        <textarea name="content" placeholder="Nhập nội dung góp ý..." required></textarea>
                        <div class="popup-actions">
                            <button type="submit" class="btn-submit-form">Gửi góp ý</button>
                            <button type="button" class="btn-close-popup">Hủy</button>
                        </div>
                    </form>
                </div>

                <div style="padding: 10px 15px; border-top: 1px solid #E4E6EB; display: flex; justify-content: space-between; align-items: center;">
                    <button class="btn-toggle-comments" style="background: none; border: none; font-weight: 600; color: #1877F2; cursor: pointer; font-family: inherit;">Xem / Góp ý trên ảnh</button>
                    <span class="comment-count-text" style="font-size: 13px; color: #65676B;">0 bình luận</span>
                </div>

                <div class="comments-section-wrapper">
                    <div class="filter-bar">
                        <span>Lọc theo:</span>
                        <button class="filter-btn active" data-filter="all">Tất cả</button>
                        <button class="filter-btn" data-filter="open">Chưa xử lý</button>
                        <button class="filter-btn" data-filter="resolved">Đã giải quyết</button>
                    </div>
                    <div class="comments-list"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="sidebar right-sidebar">
            <div class="widget">
                <h3>Thông tin nhanh</h3>
                <p style="font-size: 13px; color: #65676B; margin-bottom: 6px;">Tổng số màn hình: <b><?= $totalDesigns ?></b></p>
                <p style="font-size: 13px; color: #65676B; margin: 0;">Tổng số góp ý: <b><?= $totalAnnotationsCount ?></b></p>
            </div>
        </div>
    </div>

    <script>
        function showToast(msg) {
            const t = document.getElementById('toast');
            if(!t) return;
            t.innerText = msg; t.className = 'show';
            setTimeout(() => t.className = '', 3000);
        }

        function loadAnnotationsForVersion(postElement, versionId) {
            const imgContainer = postElement.querySelector('.image-container');
            imgContainer.dataset.versionId = versionId;
            
            imgContainer.querySelectorAll('.pin-marker').forEach(el => el.remove());

            fetch('index.php?action=get_annotations&version_id=' + versionId)
            .then(res => res.json())
            .then(res => {
                if (!res.success) return;
                
                const commentsList = postElement.querySelector('.comments-list');
                const countText = postElement.querySelector('.comment-count-text');
                const annotations = res.data;
                
                countText.innerText = annotations.length + ' bình luận';
                commentsList.innerHTML = '';

                if (annotations.length === 0) {
                    commentsList.innerHTML = '<p style="font-size:12px; color:#65676B; text-align:center; padding:10px;">Chưa có góp ý nào cho phiên bản này.</p>';
                    return;
                }

                annotations.forEach((ann, index) => {
                    const pinNumber = annotations.length - index;
                    
                    const pin = document.createElement('div');
                    pin.className = 'pin-marker';
                    pin.style.left = ann.coordinate_x + '%';
                    pin.style.top = ann.coordinate_y + '%';
                    pin.innerText = pinNumber;
                    pin.dataset.annotationId = ann.id;
                    imgContainer.appendChild(pin);

                    const isResolved = ann.status === 'resolved';
                    const statusText = isResolved ? 'Đã giải quyết' : 'Chưa xử lý';
                    const statusClass = isResolved ? 'resolved' : 'open';

                    const card = document.createElement('div');
                    card.className = 'feedback-card';
                    card.dataset.status = ann.status;

                    let deleteButtonHtml = '';
                    if (res.user_role === 'designer' || res.current_user_id == ann.user_id) {
                        deleteButtonHtml = '<button class="comment-action-btn btn-delete-comment" data-id="' + ann.id + '">Xóa</button>';
                    }

                    card.innerHTML = 
                        '<div class="avatar"></div>' +
                        '<div class="comment-content">' +
                            '<div class="comment-author">' + escapeHtml(ann.fullname) + ' <span style="font-weight:normal; font-size:11px; color:#65676B;">(#' + pinNumber + ')</span></div>' +
                            '<div style="font-size:13px; color:#050505; word-break:break-word;">' + escapeHtml(ann.content) + '</div>' +
                            '<div style="font-size:11px; color:#65676B; margin-top:4px;">' + ann.created_at + '</div>' +
                            '<div class="comment-actions">' +
                                '<button class="comment-action-btn ' + statusClass + ' btn-toggle-status" data-id="' + ann.id + '">' + statusText + '</button>' +
                                '<button class="comment-action-btn btn-like ' + (ann.is_liked > 0 ? 'liked' : '') + '" data-id="' + ann.id + '">👍 Thích (' + ann.like_count + ')</button>' +
                                deleteButtonHtml +
                            '</div>' +
                        '</div>';

                    card.addEventListener('mouseenter', () => pin.classList.add('highlight'));
                    card.addEventListener('mouseleave', () => pin.classList.remove('highlight'));

                    commentsList.appendChild(card);
                });
            });
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

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

            if (modalClose && profileModal) {
                modalClose.addEventListener('click', () => {
                    profileModal.classList.remove('show');
                });
            }

            if (modalCancel && profileModal) {
                modalCancel.addEventListener('click', () => {
                    profileModal.classList.remove('show');
                });
            }

            if (profileModal) {
                profileModal.addEventListener('click', (e) => {
                    if (e.target === profileModal) {
                        profileModal.classList.remove('show');
                    }
                });
            }

            document.querySelectorAll('.design-post').forEach(post => {
                const select = post.querySelector('.version-select');
                const imgContainer = post.querySelector('.image-container');
                const imgElement = post.querySelector('.design-image');
                const popup = post.querySelector('.annotation-popup');
                const compareWrapper = post.querySelector('.compare-wrapper');
                const compareCurrentImg = post.querySelector('.compare-current-img');

                const initialOpt = select.selectedOptions[0];
                if (initialOpt && initialOpt.dataset.url) {
                    compareCurrentImg.src = initialOpt.dataset.url;
                }

                imgElement.addEventListener('click', (e) => {
                    const rect = imgElement.getBoundingClientRect();
                    const x = e.clientX - rect.left;
                    const y = e.clientY - rect.top;
                    const cx = (x / rect.width) * 100;
                    const cy = (y / rect.height) * 100;

                    popup.style.left = Math.min(x, rect.width - 290) + 'px';
                    popup.style.top = Math.min(y, rect.height - 130) + 'px';
                    popup.style.display = 'block';

                    popup.querySelector('.input-coord-x').value = cx;
                    popup.querySelector('.input-coord-y').value = cy;
                    popup.querySelector('.input-version-id').value = imgContainer.dataset.versionId;
                    popup.querySelector('textarea').value = '';
                    popup.querySelector('textarea').focus();
                });

                popup.querySelector('.btn-close-popup').addEventListener('click', () => {
                    popup.style.display = 'none';
                });

                post.querySelector('.btn-toggle-compare').addEventListener('click', () => {
                    compareWrapper.style.display = (compareWrapper.style.display === 'grid') ? 'none' : 'grid';
                });

                loadAnnotationsForVersion(post, select.value);

                select.addEventListener('change', (e) => {
                    const opt = e.target.selectedOptions[0];
                    imgElement.src = opt.dataset.url;
                    compareCurrentImg.src = opt.dataset.url;
                    loadAnnotationsForVersion(post, opt.value);
                });

                post.querySelectorAll('.filter-btn').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        post.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                        const filter = btn.dataset.filter;
                        post.querySelectorAll('.feedback-card').forEach(card => {
                            if (filter === 'all' || card.dataset.status === filter) {
                                card.style.display = 'flex';
                            } else {
                                card.style.display = 'none';
                            }
                        });
                    });
                });
            });

            document.addEventListener('click', (e) => {
                if (e.target.closest('.btn-toggle-comments')) {
                    const wrapper = e.target.closest('.design-post').querySelector('.comments-section-wrapper');
                    wrapper.style.display = (wrapper.style.display === 'block') ? 'none' : 'block';
                }

                if (e.target.classList.contains('btn-toggle-status')) {
                    const annId = e.target.dataset.id;
                    const post = e.target.closest('.design-post');
                    const select = post.querySelector('.version-select');
                    fetch('index.php?action=toggle_status', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'annotation_id=' + annId
                    }).then(res => res.json()).then(data => {
                        if(data.success) loadAnnotationsForVersion(post, select.value);
                    });
                }

                if (e.target.classList.contains('btn-delete-comment')) {
                    if(confirm('Bạn có chắc muốn xóa góp ý này?')) {
                        const annId = e.target.dataset.id;
                        const post = e.target.closest('.design-post');
                        const select = post.querySelector('.version-select');
                        fetch('index.php?action=delete_annotation', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                            body: 'annotation_id=' + annId
                        }).then(res => res.json()).then(data => {
                            if(data.success) loadAnnotationsForVersion(post, select.value);
                        });
                    }
                }

                if (e.target.classList.contains('btn-like')) {
                    const annId = e.target.dataset.id;
                    const post = e.target.closest('.design-post');
                    const select = post.querySelector('.version-select');
                    fetch('index.php?action=toggle_annotation_like', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'annotation_id=' + annId
                    }).then(res => res.json()).then(data => {
                        loadAnnotationsForVersion(post, select.value);
                    });
                }
            });

            const uploadForm = document.getElementById('upload-form');
            if (uploadForm) {
                uploadForm.addEventListener('submit', (e) => {
                    e.preventDefault();
                    const formData = new FormData(uploadForm);
                    fetch('index.php?action=upload_image', { method: 'POST', body: formData })
                    .then(res => res.json()).then(data => {
                        if (data.success) {
                            showToast('Tải lên thành công!');
                            setTimeout(() => window.location.reload(), 1500);
                        } else {
                            showToast('Lỗi: ' + data.message);
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>