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

    'content_fix' => <<<'TEXT'
    Viết tiêu đề trang và mô tả meta cho nội dung dưới đây, sửa đúng những vấn
    đề được liệt kê trong phần "Bối cảnh bổ sung" — đừng viết meta chung chung
    từ đầu, hãy xử lý đúng điều đang bị gắn cờ.

    Từ khoá chính: :keyword

    Tiêu đề phải đọc tự nhiên và chứa từ khoá chính ở chỗ hợp lý.
    Mô tả tối đa :max ký tự, nói rõ trang này có gì, và kết thúc bằng một câu trọn vẹn.

    Nội dung:
    :content
    TEXT,

    'redirect_target' => <<<'TEXT'
    Một khách truy cập vào đường dẫn này, hiện không còn tồn tại:
    :path

    Chọn trong các trang thật sau đây của trang web trang nào thay thế hợp lý
    nhất. Nếu không có trang nào hợp, chọn trang ít tệ nhất và nói rõ điều đó
    trong phần giải thích — không được bịa ra URL không có trong danh sách.

    Các lựa chọn:
    :candidates
    TEXT,

    'internal_link' => <<<'TEXT'
    Trang này không có trang nào khác trên web liên kết đến nó:
    :orphan_url (":orphan_title")

    Trong các trang dưới đây, chọn từ một đến ba trang có chủ đề đủ liên quan
    để một liên kết tự nhiên, thật sự đến trang mồ côi này là hợp lý, kèm mô
    tả anchor text ngắn gọn, cụ thể cho mỗi trang — không được dùng URL không
    có trong danh sách, và không dùng anchor text chung chung như "xem thêm".

    Các lựa chọn:
    :candidates
    TEXT,

    'context_heading' => 'Bối cảnh bổ sung:',

    'context_site' => 'Trang web này tên là ":brand".',

    'context_current' => 'Đang lưu — tiêu đề: ":title", mô tả: ":description"',

];
