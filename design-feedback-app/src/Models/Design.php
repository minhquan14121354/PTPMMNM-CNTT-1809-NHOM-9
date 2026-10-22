<?php
class Design {
    private $conn;
    private $table = "designs";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByProject($projectId) {
        $query = "SELECT d.*, iv.id as version_id, iv.version_number, iv.file_path 
                  FROM " . $this->table . " d
                  LEFT JOIN image_versions iv ON d.id = iv.design_id
                  WHERE d.project_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$projectId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>