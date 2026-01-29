# 🔧 Cấu hình Google Drive Backup

Hệ thống auto backup này tương tự như **SqlBak** - cho phép tự động backup database và upload lên Google Drive theo lịch trình.

## 📋 Yêu cầu

- Laravel project đã setup
- Google Cloud Console account
- Google Drive account

## 🚀 Hướng dẫn setup Google Drive API

### Bước 1: Tạo Google Cloud Project

1. Truy cập [Google Cloud Console](https://console.cloud.google.com/)
2. Tạo project mới hoặc chọn project có sẵn
3. Ghi nhớ tên project để sử dụng

### Bước 2: Bật Google Drive API

1. Vào **APIs & Services** → **Library**
2. Tìm kiếm "Google Drive API"
3. Click **Enable** để bật API

### Bước 3: Tạo OAuth 2.0 Credentials

1. Vào **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth 2.0 Client ID**
3. Chọn Application type: **Web application**
4. Thêm **Authorized redirect URIs**:
   ```
   http://localhost:8000/auth/google-drive/callback
   https://yourdomain.com/auth/google-drive/callback
   ```
5. Copy **Client ID** và **Client Secret**

### Bước 4: Cấu hình Environment Variables

Thêm các biến sau vào file `.env`:

```bash
# Google Drive API Configuration
GOOGLE_DRIVE_CLIENT_ID="your_google_client_id_here"
GOOGLE_DRIVE_CLIENT_SECRET="your_google_client_secret_here"
GOOGLE_DRIVE_REDIRECT_URI="${APP_URL}/auth/google-drive/callback"

# Queue Configuration (cho email notifications)
QUEUE_CONNECTION=database
```

### Bước 5: Chạy Queue Worker

Để email notifications hoạt động:

```bash
# Development
php artisan queue:work

# Production (với supervisor)
php artisan queue:work --daemon
```

## 👤 Hướng dẫn cho User

Sau khi admin setup xong, user chỉ cần:

1. **Tạo backup configuration** trong hệ thống
2. **Click "Connect to Google Drive"**
3. **Authorize ứng dụng** (đăng nhập Google)
4. **Chọn folder** để lưu backup trên Google Drive
5. **Xong!** Backup sẽ tự động chạy theo lịch

## ✨ Tính năng

- ✅ **Auto backup** database theo lịch (Daily/Weekly/Monthly)
- ✅ **Upload lên Google Drive** tự động
- ✅ **Email notifications** khi backup thành công/thất bại
- ✅ **Dashboard monitoring** trạng thái backup
- ✅ **Multiple configurations** cho nhiều database/schedule khác nhau
- ✅ **Google Drive folder picker** - chọn folder lưu backup
- ✅ **File management** - xem, download, delete backup files
- ✅ **Responsive UI** - hỗ trợ mobile/tablet

## 🔒 Bảo mật

- OAuth 2.0 authentication với Google
- Không lưu trữ password Google Drive
- Session-based token management
- Automatic token refresh

## 🛠️ Troubleshooting

### Lỗi "redirect_uri_mismatch"
- Kiểm tra redirect URI trong Google Console khớp với `.env`
- Đảm bảo `APP_URL` trong `.env` chính xác

### Lỗi "Access denied"
- Kiểm tra Google Drive API đã được bật
- Verify OAuth consent screen đã được setup

### Backup không chạy tự động
- Kiểm tra Laravel Scheduler đã setup cron job:
  ```bash
  * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
  ```

### Email notifications không gửi
- Kiểm tra queue worker đang chạy
- Verify email configuration trong `.env`

## 📞 Support

Nếu gặp vấn đề, tạo issue trên GitHub repository này.
