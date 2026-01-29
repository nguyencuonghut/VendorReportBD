<?php

return [
    'passwordReset' => [
        'subject' => 'Đặt lại mật khẩu - ' . config('app.name', 'Language Center'),
        'header' => 'Hệ thống quản lý trung tâm ngoại ngữ',
        'greeting' => 'Xin chào :name,',
        'message1' => 'Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.',
        'message2' => 'Vui lòng nhấp vào nút bên dưới để đặt lại mật khẩu của bạn:',
        'button' => 'Đặt lại mật khẩu',
        'alternativeText' => 'Nếu bạn gặp sự cố khi nhấp vào nút "Đặt lại mật khẩu", hãy sao chép và dán URL bên dưới vào trình duyệt web của bạn:',
        'warningTitle' => '⚠️ Lưu ý bảo mật:',
        'warningText' => 'Nếu bạn không yêu cầu đặt lại mật khẩu, vui lòng bỏ qua email này. Không chia sẻ liên kết này với bất kỳ ai.',
        'expiryTitle' => '⏰ Thời hạn:',
        'expiryText' => 'Liên kết đặt lại mật khẩu này sẽ hết hạn sau 60 phút kể từ khi được gửi.',
        'footer1' => 'Nếu bạn có bất kỳ câu hỏi nào, vui lòng liên hệ với đội ngũ hỗ trợ của chúng tôi.',
        'footer2' => 'Cảm ơn bạn đã sử dụng dịch vụ của chúng tôi!',
    ],

    'passwordResetSuccess' => [
        'subject' => 'Mật khẩu đã được đặt lại thành công - ' . config('app.name', 'Language Center'),
        'greeting' => 'Xin chào :name,',
        'message1' => 'Mật khẩu của bạn đã được đặt lại thành công vào lúc ' . now()->format('d/m/Y H:i:s') . '.',
        'message2' => 'Bây giờ bạn có thể đăng nhập bằng mật khẩu mới của mình.',
        'loginButton' => 'Đăng nhập ngay',
        'securityNote' => 'Nếu bạn không thực hiện thay đổi này, vui lòng liên hệ với đội ngũ hỗ trợ của chúng tôi ngay lập tức.',
        'signature' => 'Trân trọng,<br>Đội ngũ ' . config('app.name', 'Language Center'),
    ],
];
