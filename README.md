# DesignFeed App - Hệ thống Góp ý Thiết kế & Quản lý Phiên bản (Đề tài 15)

## 📌 Giới thiệu
Ứng dụng web hỗ trợ Designer và Khách hàng tương tác trực tiếp bằng cách ghim góp ý theo tọa độ trên hình ảnh giao diện, quản lý nhiều phiên bản thiết kế (V1, V2...) và so sánh side-by-side.

## 🚀 Hướng dẫn Cài đặt
1. Mở XAMPP và bật dịch vụ **Apache** & **MySQL**.
2. Sao chép thư mục dự án vào `C:/xampp/htdocs/`.
3. Truy cập phpMyAdmin (`http://localhost/phpmyadmin`), tạo cơ sở dữ liệu tên `design_feedback_db`.
4. Mở trình duyệt và truy cập đường dẫn:  
   `http://localhost/PTPMMNM-CNTT-1809-NHOM-9/design-feedback-app/public/index.php`

## 🧪 Ca kiểm thử (Testing Suite)
- **Test 1**: Co giãn kích thước màn hình responsive $\rightarrow$ Tọa độ ghim (%) giữ nguyên chuẩn xác.
- **Test 2**: Truy cập ngoài Dự án $\rightarrow$ Máy chủ chặn truy cập và trả về cảnh báo an toàn.
- **Test 3**: Nút So sánh Side-by-side $\rightarrow$ Hiển thị V1 và phiên bản hiện tại song song.
- **Test 4**: Lọc góp ý Open/Resolved và Xuất báo cáo nghiệm thu qua `window.print()`.
