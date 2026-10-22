<?php
class AuthMiddleware {
    public static function checkProjectAccess($pdo, $projectId, $userId, $requiredRole = 'viewer') {
        $stmt = $pdo->prepare("SELECT role FROM project_members WHERE project_id = ? AND user_id = ?");
        $stmt->execute([$projectId, $userId]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            // Chặn tuyệt đối nếu không thuộc dự án (Tránh truy cập trái phép qua URL - Yêu cầu bảo mật)
            header("HTTP/1.1 403 Forbidden");
            echo "Truy cập bị từ chối: Bạn không có quyền truy cập dự án này.";
            exit;
        }

        // Kiểm tra phân quyền chi tiết
        if ($requiredRole === 'editor' && $member['role'] === 'viewer') {
            header("HTTP/1.1 403 Forbidden");
            echo "Truy cập bị từ chối: Tài khoản chỉ xem không được phép tạo hoặc sửa góp ý.";
            exit;
        }
        return true;
    }
}
?>