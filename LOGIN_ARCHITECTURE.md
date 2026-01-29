# Hệ thống Đăng nhập với Template và Service Pattern

## Cấu trúc đã được tổ chức

### 1. Services (Logic Layer)
- **AuthService** (`resources/js/services/AuthService.js`): Xử lý logic đăng nhập, đăng xuất
- **ToastService** (`resources/js/services/ToastService.js`): Xử lý hiển thị thông báo toast

### 2. Composables (Reusable Logic)
- **useFlashMessages** (`resources/js/composables/useFlashMessages.js`): Xử lý flash messages từ BE
- **useFormValidation** (`resources/js/composables/useFormValidation.js`): Xử lý validation errors

### 3. Template (UI Layer)
- **Login.vue** (`resources/js/Pages/Auth/Login.vue`): Template hiển thị form đăng nhập

## Tính năng đã implement

### ✅ Gửi form input tới BE
- Form validation phía client
- Gửi dữ liệu email, password, remember thông qua AuthService
- Xử lý trạng thái loading khi đang gửi request

### ✅ Toast các flash message từ BE
- Tự động hiển thị toast success/error từ flash messages
- Khởi tạo ToastService khi component mount
- Watch changes trong flash messages để hiển thị real-time

### ✅ Hiển thị validation error messages
- Hiển thị error messages trực tiếp dưới các input field
- Style invalid inputs với class `p-invalid`
- Xử lý multiple validation errors từ BE

## Cách sử dụng

### 1. Đăng nhập
- Truy cập `/login`
- Nhập email: `nguyenvancuong@honhafeed.com.vn`
- Nhập password: `Hongha@123`
- Click "Đăng nhập"

### 2. Các tính năng đã có:
- **Form validation**: Kiểm tra email format, required fields
- **Error handling**: Hiển thị lỗi validation và lỗi đăng nhập
- **Success feedback**: Toast thông báo đăng nhập thành công
- **Loading state**: Button disabled và hiển thị loading khi đang xử lý
- **Remember me**: Checkbox để ghi nhớ đăng nhập
- **Auto redirect**: Chuyển hướng về trang chủ sau khi đăng nhập thành công

### 3. Kiến trúc Template và Service:

#### Template (Login.vue):
```vue
<template>
  <!-- Chỉ chứa UI và binding data -->
  <form @submit.prevent="handleLogin">
    <InputText v-model="email" :class="{ 'p-invalid': hasError('email') }" />
    <small v-if="hasError('email')">{{ getError('email') }}</small>
  </form>
</template>

<script setup>
// Import services và composables
import { AuthService } from '../../services';
import { useFormValidation } from '../../composables/useFormValidation';

// Logic xử lý đăng nhập
const handleLogin = () => {
  AuthService.login(credentials, options);
};
</script>
```

#### Service (AuthService.js):
```javascript
export class AuthService {
  static login(credentials, options = {}) {
    // Xử lý logic gọi API
    router.post('/login', credentials, {
      onError: (errors) => {
        // Xử lý errors và hiển thị toast
        ToastService.error(errors.message);
      }
    });
  }
}
```

## Lợi ích của kiến trúc này:

1. **Separation of Concerns**: Template chỉ lo hiển thị, Service lo logic
2. **Reusability**: AuthService có thể dùng ở nhiều component khác
3. **Maintainability**: Dễ bảo trì và mở rộng
4. **Testability**: Có thể test Service và Template riêng biệt
5. **Consistency**: Cách xử lý lỗi và thông báo thống nhất trong toàn app

## Cấu trúc file:

```
resources/js/
├── services/
│   ├── AuthService.js          # Logic đăng nhập/đăng xuất
│   ├── ToastService.js         # Logic hiển thị thông báo
│   └── index.js                # Export tất cả services
├── composables/
│   ├── useFlashMessages.js     # Xử lý flash messages
│   └── useFormValidation.js    # Xử lý form validation
└── Pages/
    ├── Auth/
    │   └── Login.vue           # Template đăng nhập
    └── Home.vue                # Trang chủ sau khi đăng nhập
```