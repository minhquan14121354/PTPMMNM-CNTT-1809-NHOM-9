<div class="compare-container" style="display: flex; gap: 20px;">
    <div class="version-box" style="flex: 1;">
        <h3>Phiên bản cũ (v1)</h3>
        <div style="position: relative;" id="container-v1">
            <img src="<?= $version1['file_path'] ?>" id="img-v1" style="width: 100%;" />
            <!-- Các góp ý cũ hiển thị cố định ở đây, không bị mất -->
        </div>
    </div>
    
    <div class="version-box" style="flex: 1;">
        <h3>Phiên bản mới (v2)</h3>
        <div style="position: relative;" id="container-v2">
            <img src="<?= $version2['file_path'] ?>" id="img-v2" style="width: 100%;" />
            <!-- Phiên bản mới sạch sẽ, sẵn sàng nhận góp ý mới -->
        </div>
    </div>
</div>