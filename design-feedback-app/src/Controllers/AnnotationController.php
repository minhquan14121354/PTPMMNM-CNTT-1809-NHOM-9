<?php
require_once __DIR__ . '/../Models/Annotation.php';
require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';

class AnnotationController {
    private $db;
    private $annotationModel;

    public function __construct($db) {
        $this->db = $db;
        $this->annotationModel = new Annotation($db);
    }

    // Xử lý API tạo góp ý mới
    public function store($projectId, $userId) {
        // Kiểm tra quyền: Người dùng phải có ít nhất quyền 'editor' (hoặc manager) mới được tạo góp ý
        AuthMiddleware::checkProjectAccess($this->db, $projectId, $userId, 'editor');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = [
                'image_version_id' => $_POST['image_version_id'] ?? null,
                'user_id' => $userId,
                'type' => $_POST['type'] ?? 'point', // 'point' hoặc 'rectangle'
                'x' => $_POST['x'] ?? 0,
                'y' => $_POST['y'] ?? 0,
                'width' => $_POST['width'] ?? 0,
                'height' => $_POST['height'] ?? 0
            ];

            if ($this->annotationModel->create($data)) {
                echo json_encode(["status" => "success", "message" => "Tạo góp ý thành công!"]);
            } else {
                header("HTTP/1.1 500 Internal Server Error");
                echo json_encode(["status" => "error", "message" => "Không thể lưu góp ý vào CSDL."]);
            }
            exit;
        }
    }

    // Lấy danh sách JSON các góp ý để Frontend render
    public function fetchByVersion($imageVersionId, $projectId, $userId) {
        AuthMiddleware::checkProjectAccess($this->db, $projectId, $userId, 'viewer');
        
        $annotations = $this->annotationModel->getByVersion($imageVersionId);
        header('Content-Type: application/json');
        echo json_encode($annotations);
        exit;
    }
}
?>