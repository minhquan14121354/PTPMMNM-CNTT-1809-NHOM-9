<div class="project-view">
    <h2>Chi tiết Dự án & Màn hình thiết kế</h2>
    <p>Chọn điểm trên ảnh thiết kế bên dưới để thêm góp ý mới (Tọa độ tự động chuẩn hóa độc lập kích thước màn hình).</p>
    
    <div id="image-container" style="position: relative; display: inline-block; margin-top: 20px; border: 1px solid #ccc;">
        <!-- Ảnh mẫu kích thước 1000x500 chứa điểm mẫu (750; 150) -->
        <img id="design-image" src="uploads/v1_sample_1000x500.png" alt="Bản thiết kế" style="max-width: 100%; display: block;" />
    </div>
</div>

<script>
    // Truyền quyền hạn và gọi load dữ liệu góp ý ban đầu
    window.userRole = "<?= $currentRole ?? 'viewer' ?>";
    
    // Gọi API lấy danh sách góp ý hiển thị lên ảnh
    fetch('index.php?action=get_annotations&image_version_id=1&project_id=1')
        .then(response => response.json())
        .then(data => {
            window.currentAnnotations = data;
            if (window.renderAnnotations) {
                window.renderAnnotations(data);
            }
        });
</script>