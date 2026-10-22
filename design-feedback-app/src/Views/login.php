<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng nhập - Ứng dụng Góp ý</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh; background: #f4f4f4;">
    <div style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 350px;">
        <h2 style="text-align: center; margin-bottom: 20px;">Đăng nhập hệ thống</h2>
        <?php if (isset($error)): ?>
            <div style="color: red; margin-bottom: 15px; font-size: 14px;"><?= $error ?></div>
        <?php endif; ?>
        <form method="POST" action="index.php?action=login">
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px;">Email tài khoản:</label>
                <input type="email" name="email" value="viewer@test.com" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px;">Mật khẩu:</label>
                <input type="password" name="password" value="password" required style="width: 100%; padding: 8px; box-sizing: border-box;">
            </div>
            <button type="submit" style="width: 100%; padding: 10px; background: #007bff; color: #fff; border: none; border-radius: 4px; cursor: pointer;">Đăng nhập</button>
        </form>
    </div>
</body>
</html>