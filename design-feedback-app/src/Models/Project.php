<?php
class Project {
    private $conn;
    private $table = "projects";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function getByUser($userId) {
        $query = "SELECT p.*, pm.role FROM " . $this->table . " p
                  JOIN project_members pm ON p.id = pm.project_id
                  WHERE pm.user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>