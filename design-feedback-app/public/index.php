<?php
session_start();

// Nhúng các tệp cấu hình và thành phần
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Controllers/AnnotationController.php';

// Khởi tạo kết nối CSDL
$database = new Database();
$db = $database->getConnection();

// Giả lập phiên đăng nhập (Dùng user_id = 3 tương ứng Le Viewer hoặc 1: Manager, 2: Designer)
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 3; 
}
$currentUserId = $_SESSION['user_id'];

// Lấy hành động (action) từ URL query string
$action = $_GET['action'] ?? 'home';
$projectId = $_GET['project_id'] ?? 1; // Mặc định dự án ID = 1 để test

$annotationController = new AnnotationController($db);

switch ($action) {
    case 'get_annotations':
        $imageVersionId = $_GET['image_version_id'] ?? 1;
        $annotationController->fetchByVersion($imageVersionId, $projectId, $currentUserId);
        break;

    case 'add_annotation':
        $annotationController->store($projectId, $currentUserId);
        break;

    case 'home':
    default:
        // Hiển thị trực tiếp giao diện màn hình thiết kế
        ?>
        <!DOCTYPE html>
        <html lang="vi">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Ứng dụng Góp ý Thiết kế</title>
            <!-- Nhúng file CSS giao diện -->
            <link rel="stylesheet" href="/design-feedback-app/public/css/style.css">
        </head>
        <body>
            <div class="container">
                <h2>Chi tiết Dự án & Màn hình thiết kế</h2>
                <p>Chọn điểm trên ảnh thiết kế bên dưới để thêm góp ý mới (Tọa độ tự động chuẩn hóa độc lập kích thước màn hình).</p>

                <div class="project-layout">
                    <!-- Khu vực hiển thị ảnh và điểm ghim -->
                    <div class="design-viewer-pane">
                        <div id="image-container" class="image-container">
                            <!-- Thẻ img với id="design-image" bắt buộc để JS hoạt động -->
                           <img id="design-image" src="/design-feedback-app/public/uploads/v1_sample_1000x500.png" alt="Bản thiết kế mẫu">
                        </div>
                    </div>

                    <!-- Khu vực hiển thị danh sách góp ý -->
                    <div class="feedback-sidebar">
                        <h3>Danh sách góp ý</h3>
                        <div id="comments-list">
                            <p style="color: #66c; font-style: italic;">Chưa có điểm góp ý nào. Hãy click lên ảnh để tạo mới!</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Truyền biến role xuống JavaScript (Ví dụ: 'viewer' hoặc 'designer') -->
            <script>
                window.userRole = 'designer'; // Đổi thành 'viewer' nếu muốn test phân quyền xem
            </script>
            <!-- Nhúng file xử lý JavaScript tương tác -->
            <script src="js/annotation.js"></script>
        </body>
        </html>
        <?php
        break;
}
?>