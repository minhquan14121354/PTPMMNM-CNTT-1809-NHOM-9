<?php
class AnnotationController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function store($projectId, $userId) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $content = $_POST['content'] ?? '';
            $cx = $_POST['coordinate_x'] ?? 0;
            $cy = $_POST['coordinate_y'] ?? 0;
            $crop = $_POST['cropped_image'] ?? '';

            $stmt = $this->db->prepare("INSERT INTO annotations (project_id, user_id, content, coordinate_x, coordinate_y, cropped_image) VALUES (?, ?, ?, ?, ?, ?)");
            $success = $stmt->execute([$projectId, $userId, $content, $cx, $cy, $crop]);
            
            if (ob_get_length()) ob_clean(); // Xóa sạch các rác HTML/cảnh báo PHP thừa
            
            if ($success) {
                echo json_encode(["status" => "success"]);
            } else {
                $error = $stmt->errorInfo();
                http_response_code(500);
                echo json_encode(["status" => "error", "message" => "Lỗi MySQL: " . ($error[2] ?? 'Không rõ nguyên nhân')]);
            }
            exit;
        }
    }

    public function fetchByVersion($imageVersionId, $projectId, $userId) {
        $stmt = $this->db->prepare("
            SELECT a.*, u.fullname 
            FROM annotations a 
            JOIN users u ON a.user_id = u.id 
            ORDER BY a.id DESC
        ");
        $success = $stmt->execute();
        
        if (ob_get_length()) ob_clean();
        header('Content-Type: application/json');
        
        if ($success) {
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
        } else {
            $error = $stmt->errorInfo();
            echo json_encode(['success' => false, 'message' => "Lỗi MySQL: " . ($error[2] ?? 'Không rõ')]);
        }
        exit;
    }
}
?>