<?php
session_start();
require_once __DIR__ . '/../config/database.php';
$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $fullname = trim($_POST['fullname'] ?? '');
    $role = $_POST['role'] ?? 'client';

    // Chặn tuyệt đối nếu cố tình gửi request đăng ký quyền admin qua mạng
    if ($role === 'admin') {
        $role = 'client';
    }

    if (empty($username) || empty($password) || empty($fullname)) {
        $error = 'Vui lòng điền đầy đủ thông tin các trường!';
    } else {
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE username = ?");
        $stmtCheck->execute([$username]);
        if ($stmtCheck->rowCount() > 0) {
            $error = 'Tên đăng nhập này đã có người sử dụng!';
        } else {
            $stmtIns = $db->prepare("INSERT INTO users (username, password, fullname, role) VALUES (?, ?, ?, ?)");
            if ($stmtIns->execute([$username, $password, $fullname, $role])) {
                $success = 'Đăng ký tài khoản thành công! Bạn có thể chuyển sang trang Đăng nhập.';
            } else {
                $error = 'Đã có lỗi xảy ra, vui lòng thử lại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng ký tài khoản - ArtFlow Studio</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #F0F2F5; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .register-card { background: #FFF; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 380px; box-sizing: border-box; }
        .app-logo { font-size: 20px; font-weight: 700; color: #1877F2; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 20px; }
        .app-logo svg { width: 28px; height: 28px; fill: #1877F2; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 5px; color: #4B4F56; }
        input, select { width: 100%; padding: 10px 14px; border: 1px solid #CCD0D5; border-radius: 8px; box-sizing: border-box; font-size: 13px; outline: none; font-family: inherit; }
        input:focus, select:focus { border-color: #1877F2; box-shadow: 0 0 0 2px rgba(24,119,242,0.2); }
        button { width: 100%; background: #1877F2; color: white; border: none; padding: 11px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; margin-top: 10px; transition: 0.2s; }
        button:hover { background: #166fe5; }
        .alert-error { background: #FFEBEE; color: #C62828; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .alert-success { background: #E8F5E9; color: #2E7D32; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; text-align: center; font-weight: 500; }
        .login-link { text-align: center; margin-top: 15px; font-size: 13px; color: #65676B; }
        .login-link a { color: #1877F2; text-decoration: none; font-weight: 600; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="register-card">
        <div class="app-logo">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 5h2v5h-2z"/></svg>
            <span>ArtFlow Studio</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Họ và tên hiển thị:</label>
                <input type="text" name="fullname" placeholder="Ví dụ: Nguyễn Văn A" required>
            </div>
            <div class="form-group">
                <label>Tên đăng nhập:</label>
                <input type="text" name="username" placeholder="Nhập tên tài khoản" required>
            </div>
            <div class="form-group">
                <label>Mật khẩu:</label>
                <input type="password" name="password" placeholder="Nhập mật khẩu" required>
            </div>
            <div class="form-group">
                <label>Vai trò hệ thống:</label>
                <select name="role">
                    <option value="client">Khách hàng (Client)</option>
                    <option value="designer">Nhà thiết kế (Designer)</option>
                </select>
            </div>
            <button type="submit">Đăng ký tài khoản</button>
        </form>
        <div class="login-link">
            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
        </div>
    </div>
</body>
</html>