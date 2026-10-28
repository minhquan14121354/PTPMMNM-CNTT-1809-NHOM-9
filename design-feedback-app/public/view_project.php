<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

$projectId = $_GET['project_id'] ?? null;
if (!$projectId) {
    header("Location: admin.php");
    exit;
}

// Lấy thông tin dự án và khách hàng tạo
$stmtProj = $db->prepare("SELECT p.*, u.fullname as client_name FROM projects p JOIN users u ON p.client_id = u.id WHERE p.id = ?");
$stmtProj->execute([$projectId]);
$project = $stmtProj->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    echo "Không tìm thấy dự án!";
    exit;
}

// Lấy tên nhà thiết kế phụ trách (nếu có)
$stmtDes = $db->prepare("
    SELECT u.fullname FROM project_members pm 
    JOIN users u ON pm.user_id = u.id 
    WHERE pm.project_id = ? AND (pm.role = 'designer' OR u.role = 'designer') LIMIT 1
");
$stmtDes->execute([$projectId]);
$designerName = $stmtDes->fetchColumn() ?: 'Chưa phân công';

// Lấy danh sách thiết kế và phiên bản ảnh của dự án
$stmtDesigns = $db->prepare("SELECT * FROM designs WHERE project_id = ? ORDER BY id DESC");
$stmtDesigns->execute([$projectId]);
$designs = $stmtDesigns->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xem chi tiết dự án - Admin Center</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; background-color: #F0F2F5; color: #050505; margin: 0; padding: 0; }
        .top-header { background: #FFF; padding: 12px 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 4px rgba(0,0,0,0.08); position: sticky; top: 0; z-index: 1000; }
        .app-logo { font-size: 20px; font-weight: 800; color: #1877F2; display: flex; align-items: center; gap: 10px; text-decoration: none; }
        .app-logo svg { width: 26px; height: 26px; fill: #1877F2; }
        
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .card { background: #FFF; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 25px; }
        .project-title { font-size: 20px; font-weight: 700; color: #050505; margin-bottom: 8px; }
        .project-meta { font-size: 13px; color: #65676B; display: flex; gap: 20px; margin-bottom: 15px; }
        
        .design-section { border-top: 1px solid #E4E6EB; padding-top: 20px; margin-top: 20px; }
        .design-title { font-size: 16px; font-weight: 600; color: #1877F2; margin-bottom: 12px; }
        
        .image-box { position: relative; width: 100%; max-height: 500px; background: #1a1a1a; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; margin-bottom: 15px; }
        .image-box img { max-width: 100%; max-height: 500px; object-fit: contain; display: block; }

        .pin-marker { position: absolute; width: 24px; height: 24px; background: #FF3B30; color: white; border-radius: 50%; font-size: 11px; font-weight: bold; display: flex; align-items: center; justify-content: center; transform: translate(-50%, -50%); border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.3); }

        .feedback-list { display: flex; flex-direction: column; gap: 10px; }
        .feedback-item { background: #F9FAFB; border: 1px solid #E4E6EB; border-radius: 8px; padding: 12px; font-size: 13px; }
        .feedback-author { font-weight: 700; color: #050505; margin-bottom: 4px; display: flex; justify-content: space-between; }
        .feedback-time { font-size: 11px; font-weight: normal; color: #65676B; }
        .badge-status { padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .status-open { background: #FFEBEE; color: #C62828; }
        .status-resolved { background: #E8F5E9; color: #2E7D32; }
        
        .btn-back { background: #E4E6EB; color: #050505; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 600; font-size: 13px; display: inline-flex; align-items: center; gap: 6px; }
        .btn-back:hover { background: #D8DADF; }
    </style>
</head>
<body>
    <div class="top-header">
        <a href="admin.php" class="app-logo">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 5h2v5h-2z"/></svg>
            <span>ReviewArt Pro - Chi tiết Dự án</span>
        </a>
        <a href="admin.php" class="btn-back">⬅ Quay lại trang Quản trị</a>
    </div>

    <div class="container">
        <div class="card">
            <div class="project-title"><?= htmlspecialchars($project['name']) ?></div>
            <div class="project-meta">
                <span>Khách hàng tạo: <b><?= htmlspecialchars($project['client_name']) ?></b></span>
                <span>Nhà thiết kế phụ trách: <b style="color: #1877F2;"><?= htmlspecialchars($designerName) ?></b></span>
                <span>Trạng thái: <b><?= $project['status'] === 'approved' ? 'Đã duyệt' : 'Đang chờ duyệt' ?></b></span>
            </div>

            <?php foreach($designs as $index => $design): ?>
                <?php
                    // Lấy tất cả phiên bản ảnh của thiết kế này
                    $stmtVer = $db->prepare("SELECT * FROM image_versions WHERE design_id = ? ORDER BY version_number DESC");
                    $stmtVer->execute([$design['id']]);
                    versions: $versions = $stmtVer->fetchAll(PDO::FETCH_ASSOC);
                ?>
                <div class="design-section">
                    <div class="design-title">Màn hình / Mẫu thiết kế: <?= htmlspecialchars($design['title']) ?></div>
                    
                    <?php foreach($versions as $ver): ?>
                        <?php 
                            $imgUrl = './uploads/' . rawurlencode(basename($ver['image_url']));
                            // Lấy danh sách góp ý/annotation cho phiên bản ảnh này
                            $stmtAnn = $db->prepare("SELECT a.*, u.fullname FROM annotations a JOIN users u ON a.user_id = u.id WHERE a.image_version_id = ? ORDER BY a.id DESC");
                            $stmtAnn->execute([$ver['id']]);
                            $annotations = $stmtAnn->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <div style="margin-bottom: 20px;">
                            <p style="font-size: 13px; font-weight: 600; color: #4B4F56;">Phiên bản V<?= $ver['version_number'] ?> (<?= date('d/m/Y H:i', strtotime($ver['created_at'])) ?>)</p>
                            
                            <div class="image-box">
                                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Design" onerror="this.src='./uploads/meo.png'">
                                <?php foreach($annotations as $idx => $ann): ?>
                                    <div class="pin-marker" style="left: <?= $ann['coordinate_x'] ?>%; top: <?= $ann['coordinate_y'] ?>%;" title="#<?= $idx+1 ?>: <?= htmlspecialchars($ann['content']) ?>">
                                        <?= $idx+1 ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <h4 style="font-size: 14px; margin: 10px 0 6px;">Danh sách góp ý trên phiên bản này (<?= count($annotations) ?>):</h4>
                            <?php if(empty($annotations)): ?>
                                <p style="font-size: 12px; color: #65676B; font-style: italic;">Chưa có góp ý nào trên phiên bản này.</p>
                            <?php else: ?>
                                <div class="feedback-list">
                                    <?php foreach($annotations as $idx => $ann): ?>
                                        <div class="feedback-item">
                                            <div class="feedback-author">
                                                <span>#<?= $idx+1 ?>. <b><?= htmlspecialchars($ann['fullname']) ?></b></span>
                                                <div>
                                                    <span class="badge-status <?= $ann['status'] === 'resolved' ? 'status-resolved' : 'status-open' ?>">
                                                        <?= $ann['status'] === 'resolved' ? 'Đã giải quyết' : 'Chưa xử lý' ?>
                                                    </span>
                                                    <span class="feedback-time"><?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?></span>
                                                </div>
                                            </div>
                                            <div style="margin-top: 4px; color: #333;"><?= nl2br(htmlspecialchars($ann['content'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>