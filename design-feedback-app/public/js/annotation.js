document.addEventListener("DOMContentLoaded", function () {
    const imageContainer = document.getElementById("image-container");
    const designImage = document.getElementById("design-image");

    if (!designImage) return;

    // Khi click lên ảnh để tạo điểm góp ý (chỉ áp dụng nếu có quyền)
    designImage.addEventListener("click", function (e) {
        if (window.userRole === 'viewer') {
            alert("Bạn chỉ có quyền xem, không thể tạo góp ý!");
            return;
        }

        const rect = designImage.getBoundingClientRect();
        
        // Lấy tọa độ pixel trên kích thước hiển thị thực tế
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;

        // Chuẩn hóa tọa độ về khoảng [0.0 - 1.0] dựa trên kích thước thật hiển thị của ảnh
        const normX = clickX / rect.width;
        const normY = clickY / rect.height;

        // Hiển thị form tạo nhận xét tạm thời tại vị trí click
        openAnnotationFormModal(normX, normY, 'point');
    });

    // Hàm render lại các điểm/vùng khi ảnh thay đổi kích thước hoặc load xong
    window.renderAnnotations = function(annotations) {
        // Xóa marker cũ tránh trùng lặp
        document.querySelectorAll('.annotation-marker').forEach(el => el.remove());

        const rect = designImage.getBoundingClientRect();

        annotations.forEach(ann => {
            const marker = document.createElement('div');
            marker.className = `annotation-marker ${ann.status}`;
            
            // Tính ngược tọa độ chuẩn hóa ra pixel trên màn hình hiện tại
            marker.style.left = `${ann.x * rect.width}px`;
            marker.style.top = `${ann.y * rect.height}px`;
            
            if (ann.type === 'rectangle') {
                marker.style.width = `${ann.width * rect.width}px`;
                marker.style.height = `${ann.height * rect.height}px`;
            }

            marker.innerHTML = `<span>${ann.id}</span>`;
            marker.addEventListener('click', () => loadAnnotationDetails(ann.id));
            
            imageContainer.appendChild(marker);
        });
    };

    // Tự động render lại khi resize cửa sổ trình duyệt
    window.addEventListener('resize', () => {
        if (window.currentAnnotations) {
            window.renderAnnotations(window.currentAnnotations);
        }
    });
});