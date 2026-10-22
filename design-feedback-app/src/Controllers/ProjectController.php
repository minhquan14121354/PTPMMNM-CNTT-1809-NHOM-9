<?php
require_once __DIR__ . '/../Models/Project.php';
require_once __DIR__ . '/../Middlewares/AuthMiddleware.php';

class ProjectController {
    private $db;
    private $projectModel;

    public function __construct($db) {
        $this->db = $db;
        $this->projectModel = new Project($db);
    }

    // Hiển thị danh sách dự án của user
    public function index($userId) {
        $projects = $this->projectModel.getByUser($userId);
        include __DIR__ . '/../Views/project_detail.php';
    }
}
?>