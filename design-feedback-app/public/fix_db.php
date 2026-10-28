<?php
require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Tắt kiểm tra khóa ngoại để ép xóa bảng cũ
    $db->exec("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Xóa bảng cũ
    $db->exec("DROP TABLE IF EXISTS annotations;");
    
    // Tạo bảng mới chuẩn xác 100%
    $db->exec("CREATE TABLE annotations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        project_id INT DEFAULT 1,
        user_id INT NOT NULL,
        content TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
        coordinate_x FLOAT,
        coordinate_y FLOAT,
        cropped_image LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );");
    
    // Bật lại khóa ngoại
    $db->exec("SET FOREIGN_KEY_CHECKS = 1;");
    
    echo "<h1 style='color: green; font-family: sans-serif; text-align: center; margin-top: 50px;'>✅ SỬA DATABASE THÀNH CÔNG!</h1>";
    echo "<p style='text-align: center; font-family: sans-serif;'>Bảng annotations đã có đầy đủ cột. Bạn có thể quay lại trang index.php để dùng thử.</p>";
} catch (Exception $e) {
    echo "<h1 style='color: red; text-align: center;'>❌ LỖI: " . $e->getMessage() . "</h1>";
}
?>