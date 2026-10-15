<?php
declare(strict_types=1);

function canComplete(array $session, int $eventId, int $now, int $ttl): bool {
    // Kiểm tra thời gian sống (phải lớn hơn 0)
    if ($ttl <= 0) return false;
    
    // Kiểm tra xem bản nháp có tồn tại không
    if (!isset($session['draft']['data']['event_id']) || !isset($session['draft']['created_at'])) {
        return false;
    }
    
    $draftData = $session['draft']['data'];
    $createdAt = $session['draft']['created_at'];
    
    // Kiểm tra đúng sự kiện
    if ((int)$draftData['event_id'] !== $eventId) {
        return false;
    }
    
    // Kiểm tra thời điểm tạo không ở tương lai
    if ($createdAt > $now) {
        return false;
    }
    
    // Kiểm tra thời hạn (tuổi nháp phải nhỏ hơn ttl)
    if ($now - $createdAt >= $ttl) {
        return false;
    }
    
    return true;
}