<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Ứng dụng Góp ý Thiết kế</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <header style="background: #333; color: #fff; padding: 10px 20px; display: flex; justify-content: space-between;">
        <h2>Hệ thống Góp ý Thiết kế Trực quan</h2>
        <div>
            <span>Xin chào, Người dùng #<?= $_SESSION['user_id'] ?? 1 ?></span>
            <a href="index.php?action=logout" style="color: #ffcccc; margin-left: 15px;">Đăng xuất</a>
        </div>
    </header>
    <main style="padding: 20px;">
        <!-- Nội dung động được nhúng vào đây -->
        <?php if (isset($contentView)) include $contentView; ?>
    </main>
    <script src="js/annotation.js"></script>
</body>
</html>