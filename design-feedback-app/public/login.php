<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $db = $database->getConnection();

        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && (password_verify($password, $user['password']) || $password === $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role'] = $user['role'];

            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $error = 'Tên đăng nhập hoặc mật khẩu không chính xác!';
        }
    } else {
        $error = 'Vui lòng điền đầy đủ thông tin!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - ArtFlow Studio</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #F0F2F5; color: #050505; margin: 0; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { background: #FFF; padding: 30px; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); width: 100%; max-width: 400px; box-sizing: border-box; text-align: center; }
        .app-logo { font-size: 20px; font-weight: 700; color: #1877F2; display: flex; align-items: center; justify-content: center; gap: 10px; margin-bottom: 24px; }
        .app-logo svg { width: 28px; height: 28px; fill: #1877F2; }
        .form-group { margin-bottom: 16px; text-align: left; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #4B4F56; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #CCD0D5; border-radius: 8px; font-size: 13px; box-sizing: border-box; outline: none; font-family: inherit; }
        .form-control:focus { border-color: #1877F2; box-shadow: 0 0 0 2px rgba(24,119,242,0.2); }
        .btn-submit { width: 100%; background: #1877F2; color: #FFF; border: none; padding: 10px; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s; margin-top: 10px; }
        .btn-submit:hover { background: #166fe5; }
        .error-msg { background: #FFEBEE; color: #C62828; padding: 10px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; font-weight: 500; }
        .register-link { margin-top: 18px; font-size: 13px; color: #65676B; }
        .register-link a { color: #1877F2; text-decoration: none; font-weight: 600; }
        .register-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="app-logo">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V5h14v14zM7 10h2v7H7zm4-3h2v10h-2zm4 5h2v5h-2z"/></svg>
            <span>ArtFlow Studio</span>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-msg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label>Tên đăng nhập</label>
                <input type="text" name="username" class="form-control" placeholder="Nhập username của bạn..." required>
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" class="form-control" placeholder="Nhập mật khẩu..." required>
            </div>
            <button type="submit" class="btn-submit">Đăng nhập</button>
        </form>

        <!-- ĐÃ BỔ SUNG LẠI PHẦN ĐĂNG KÝ TÀI KHOẢN -->
        <div class="register-link">
            Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a>
        </div>
    </div>
</body>
</html>