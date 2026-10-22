CREATE DATABASE IF NOT EXISTS design_feedback_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE design_feedback_db;

-- 1. Bảng Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL
);

-- 2. Bảng Projects
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    created_by INT,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- 3. Bảng phân quyền thành viên
CREATE TABLE project_members (
    project_id INT,
    user_id INT,
    role ENUM('manager', 'editor', 'viewer') NOT NULL,
    PRIMARY KEY (project_id, user_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 4. Bảng Màn hình thiết kế (Designs)
CREATE TABLE designs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT,
    name VARCHAR(150) NOT NULL,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
);

-- 5. Bảng Phiên bản ảnh (Image Versions)
CREATE TABLE image_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    design_id INT,
    version_number INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (design_id) REFERENCES designs(id) ON DELETE CASCADE
);

-- 6. Bảng Nhận xét (Annotations - Điểm hoặc Vùng chữ nhật)
CREATE TABLE annotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    image_version_id INT,
    user_id INT,
    type ENUM('point', 'rectangle') NOT NULL,
    x FLOAT NOT NULL, -- Tọa độ chuẩn hóa (0.0 - 1.0)
    y FLOAT NOT NULL,
    width FLOAT DEFAULT 0,
    height FLOAT DEFAULT 0,
    status ENUM('open', 'resolved') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (image_version_id) REFERENCES image_versions(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 7. Bảng Bình luận / Trao đổi (Comments)
CREATE TABLE comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annotation_id INT,
    user_id INT,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annotation_id) REFERENCES annotations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 8. Lịch sử trạng thái (Status History)
CREATE TABLE status_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    annotation_id INT,
    user_id INT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (annotation_id) REFERENCES annotations(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Dữ liệu mẫu (Seed Data)
INSERT INTO users (id, name, email, password) VALUES 
(1, 'Nguyen Manager', 'manager@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(2, 'Tran Designer', 'editor@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi'),
(3, 'Le Viewer', 'viewer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

INSERT INTO projects (id, name, description, created_by) VALUES 
(1, 'Dự án Alpha - Mobile App', 'Thiết kế giao diện ứng dụng di động', 1),
(2, 'Dự án Beta - E-commerce', 'Thiết kế website thương mại điện tử', 1);

INSERT INTO project_members (project_id, user_id, role) VALUES 
(1, 1, 'manager'), (1, 2, 'editor'), (1, 3, 'viewer'),
(2, 1, 'manager'), (2, 2, 'viewer');

INSERT INTO designs (id, project_id, name) VALUES (1, 1, 'Màn hình Trang chủ (Home)');
INSERT INTO image_versions (id, design_id, version_number, file_path) VALUES 
(1, 1, 1, 'uploads/v1_sample_1000x500.png'),
(2, 1, 2, 'uploads/v2_sample_1000x500.png');

-- Điểm mẫu yêu cầu (750; 150) trên ảnh 1000x500 (Tương ứng normX = 0.75, normY = 0.30)
INSERT INTO annotations (id, image_version_id, user_id, type, x, y, width, height, status) VALUES 
(1, 1, 3, 'point', 0.75, 0.30, 0, 0, 'open');

INSERT INTO comments (annotation_id, user_id, content) VALUES 
(1, 3, 'Nút đăng nhập này cần phóng to thêm và đổi sang màu xanh chủ đạo.');