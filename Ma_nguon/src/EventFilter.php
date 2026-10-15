<?php
declare(strict_types=1);

function filterEvents(array $events, string $query, string $category = '', bool $availableOnly = false): array
{
    $result = [];
    foreach ($events as $event) {
        // 1. Kiểm tra từ khóa
        $matchesQuery = $query === '' || stripos($event['title'], $query) !== false;
        
        // 2. Kiểm tra loại sự kiện (nếu category rỗng nghĩa là lấy tất cả)
        $matchesCategory = $category === '' || $event['category'] === $category;
        
        // 3. Kiểm tra tình trạng còn chỗ (capacity - registered > 0)
        $matchesAvailable = !$availableOnly || ($event['capacity'] - $event['registered'] > 0);

        // Kết hợp cả 3 điều kiện bằng toán tử AND (&&)
        if ($matchesQuery && $matchesCategory && $matchesAvailable) {
            $result[] = $event;
        }
    }
    return $result;
}