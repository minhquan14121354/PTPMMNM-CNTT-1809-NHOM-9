<?php
class Annotation {
    private $conn;
    private $table = "annotations";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Lấy danh sách góp ý theo phiên bản ảnh
    public function getByVersion($imageVersionId) {
        $query = "SELECT a.*, u.name as author_name 
                  FROM " . $this->table . " a 
                  JOIN users u ON a.user_id = u.id 
                  WHERE a.image_version_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$imageVersionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Thêm mới một điểm hoặc vùng chữ nhật góp ý
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " (image_version_id, user_id, type, x, y, width, height, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, 'open')";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
            $data['image_version_id'],
            $data['user_id'],
            $data['type'],
            $data['x'],
            $data['y'],
            $data['width'] ?? 0,
            $data['height'] ?? 0
        ]);
    }

    // Cập nhật trạng thái (Resolved / Open)
    public function updateStatus($annotationId, $newStatus) {
        $query = "UPDATE " . $this->table . " SET status = ? WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([$newStatus, $annotationId]);
    }
}
?>