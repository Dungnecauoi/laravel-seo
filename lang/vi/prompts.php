<?php

declare(strict_types=1);

return [

    'system' => 'Bạn viết thẻ meta cho công cụ tìm kiếm. Chỉ trả lời đúng cấu trúc được yêu cầu. '
        .'Viết bằng đúng ngôn ngữ của nội dung được đưa. Không bịa thông tin không có trong nội dung.',

    'meta' => <<<'TEXT'
    Viết tiêu đề trang và mô tả meta cho nội dung dưới đây.

    Từ khoá chính: :keyword

    Tiêu đề phải đọc tự nhiên và chứa từ khoá chính ở chỗ hợp lý.
    Mô tả tối đa :max ký tự, nói rõ trang này có gì, và kết thúc bằng một câu trọn vẹn.

    Nội dung:
    :content
    TEXT,

    'keywords' => <<<'TEXT'
    Đọc nội dung dưới đây và đề xuất từ ba đến tám cụm từ mà người dùng thật sự
    sẽ gõ để tìm ra nó. Ưu tiên cụm từ cụ thể hơn là từ đơn chung chung.
    Dùng đúng ngôn ngữ của nội dung.

    Nội dung:
    :content
    TEXT,

];
