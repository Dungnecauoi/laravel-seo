<?php

declare(strict_types=1);

return [
    'errored' => 'Không chạy được kiểm tra này.',
    'skipped' => 'Không áp dụng cho trang này.',
    'keyword_in_title' => [
        'pass' => 'Từ khoá chính có trong tiêu đề.',
        'fail' => 'Tiêu đề thiếu từ khoá chính.',
        'hint' => 'Đưa từ khoá vào tiêu đề, càng gần đầu càng tốt nếu vẫn đọc tự nhiên.',
    ],
    'keyword_in_description' => [
        'pass' => 'Từ khoá chính có trong mô tả.',
        'fail' => 'Mô tả thiếu từ khoá chính.',
        'hint' => 'Đưa từ khoá vào mô tả — Google in đậm nó trong kết quả tìm kiếm.',
    ],
    'keyword_in_url' => [
        'pass' => 'Từ khoá chính có trong URL.',
        'fail' => 'URL thiếu từ khoá chính.',
        'hint' => 'Đưa từ khoá vào slug. Đổi URL đã xuất bản thì phải tạo redirect.',
    ],
    'keyword_in_opening' => [
        'pass' => 'Từ khoá chính xuất hiện sớm trong nội dung.',
        'fail' => 'Phần mở đầu không có từ khoá chính.',
        'hint' => 'Nhắc từ khoá ngay đoạn đầu tiên.',
    ],
    'keyword_in_headings' => [
        'pass' => 'Từ khoá chính có trong tiêu đề phụ.',
        'fail' => 'Không tiêu đề phụ nào trong :headings cái chứa từ khoá chính.',
        'none' => 'Nội dung không có tiêu đề phụ H2 hay H3.',
        'hint' => 'Thêm một tiêu đề phụ dùng từ khoá, để cấu trúc bài nói rõ chủ đề.',
    ],
    'keyword_in_image_alt' => [
        'pass' => 'Có ảnh với alt chứa từ khoá chính.',
        'fail' => 'Không ảnh nào có alt chứa từ khoá chính.',
        'hint' => 'Mô tả một ảnh bằng từ khoá, ở chỗ thật sự phù hợp.',
    ],
    'keyword_density' => [
        'pass' => 'Mật độ từ khoá :density% qua :occurrences lần xuất hiện.',
        'low' => 'Mật độ từ khoá chỉ :density%.',
        'high' => 'Mật độ từ khoá :density%, đọc như nhồi từ khoá.',
        'hint_low' => 'Nhắc từ khoá thêm vài lần, ở chỗ hợp lý.',
        'hint_high' => 'Bớt vài lần và dùng cách diễn đạt khác thay thế.',
    ],
    'title_length' => [
        'pass' => 'Tiêu đề rộng :pixels px.',
        'missing' => 'Trang chưa có tiêu đề.',
        'long' => 'Tiêu đề rộng :pixels px, sẽ bị cắt sau khoảng :max px.',
        'short' => 'Tiêu đề chỉ :pixels px, còn thừa chỗ.',
        'hint_missing' => 'Đặt tiêu đề cho trang.',
        'hint_long' => 'Rút ngắn tiêu đề để vừa kết quả tìm kiếm.',
        'hint_short' => 'Viết dài thêm để tận dụng chiều rộng.',
    ],
    'description_length' => [
        'pass' => 'Mô tả dài :length ký tự.',
        'missing' => 'Trang chưa có mô tả.',
        'short' => 'Mô tả :length ký tự, dưới mức :min khuyến nghị.',
        'long' => 'Mô tả :length ký tự, vượt mức :max khuyến nghị.',
        'hint_missing' => 'Viết mô tả, nếu không công cụ tìm kiếm sẽ tự bịa.',
        'hint_short' => 'Viết dài thêm để dùng hết chỗ.',
        'hint_long' => 'Rút gọn để không bị cắt.',
    ],
    'content_length' => [
        'pass' => 'Nội dung :count tiếng.',
        'short' => 'Nội dung :count tiếng, dưới mức :minimum khuyến nghị.',
        'hint' => 'Viết đầy đủ hơn, hoặc chấp nhận đây là trang ngắn.',
    ],
    'internal_links' => [
        'pass' => 'Có :count liên kết nội bộ.',
        'none' => 'Nội dung không có liên kết nội bộ.',
        'hint' => 'Liên kết tới bài liên quan để người đọc và bot đi tiếp được.',
    ],
    'external_links' => [
        'pass' => 'Có :count liên kết ngoài.',
        'none' => 'Nội dung không có liên kết ngoài.',
        'hint' => 'Dẫn nguồn ở chỗ có ích cho người đọc.',
    ],
    'images_have_alt' => [
        'pass' => 'Cả :total ảnh đều có alt.',
        'fail' => ':missing trong :total ảnh chưa có alt.',
        'hint' => 'Mô tả mọi ảnh. Trình đọc màn hình cần, và tìm kiếm ảnh dùng nó.',
    ],
    'single_h1' => [
        'pass' => 'Trang có đúng một thẻ H1.',
        'none' => 'Trang không có thẻ H1.',
        'many' => 'Trang có :count thẻ H1.',
        'hint' => 'Dùng đúng một H1 để nói rõ trang viết về gì.',
    ],
    'vi_readability' => [
        'easy' => 'Trung bình :average tiếng mỗi câu — dễ đọc.',
        'medium' => 'Trung bình :average tiếng mỗi câu — hơi nặng.',
        'hard' => 'Trung bình :average tiếng mỗi câu — khó đọc, có :long_sentences câu quá dài.',
        'hint' => 'Tách bớt các câu dài nhất. Đây là ngưỡng kinh nghiệm, không phải thang đo đã kiểm chứng.',
    ],
    'vi_passive' => [
        'pass' => ':ratio% số câu có dấu hiệu bị động.',
        'high' => ':ratio% số câu có dấu hiệu bị động (:passive trong :total).',
        'hint' => 'Cân nhắc viết lại vài câu ở thể chủ động. "được" và "bị" cũng xuất hiện trong câu chủ động, nên chỉ coi đây là gợi ý.',
    ],
    'en_flesch' => [
        'pass' => 'Flesch Reading Ease :score.',
        'hard' => 'Flesch Reading Ease :score — khá khó đọc.',
        'hint' => 'Dùng câu ngắn hơn và từ đơn giản hơn.',
    ],
];
