# 🚀 Laravel Admin Boilerplate

> **Boilerplate chuẩn cho các dự án Laravel + Inertia.js + Vue 3 + PrimeVue 4**

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red.svg)](https://laravel.com)
[![Inertia.js](https://img.shields.io/badge/Inertia.js-2.x-purple.svg)](https://inertiajs.com)
[![Vue](https://img.shields.io/badge/Vue-3.x-green.svg)](https://vuejs.org)
[![PrimeVue](https://img.shields.io/badge/PrimeVue-4.x-blue.svg)](https://primevue.org)

## ✨ Tính năng

- ✅ **Quản lý Admin Dashboard** với SakaiVue template
- ✅ **CRUD cơ bản** với validation, toast messages từ BE
- ✅ **Role & Permission** (Spatie Laravel Permission v6.21)
- ✅ **Activity Log** (Spatie Laravel Activity Log v4.10)
- ✅ **Backup System**:
  - Backup thủ công (download file)
  - Auto backup lên Google Drive
  - Cấu hình linh hoạt (database, env, uploaded files)
- ✅ **Authentication**: Login, Logout, Password Reset
- ✅ **Toast Notifications**: Hỗ trợ từ Backend & Frontend
- ✅ **Multi-language**: i18n ready
- ✅ **Dark/Light Theme**: PrimeVue theme system

## 📋 Yêu cầu hệ thống

- PHP >= 8.2
- Composer >= 2.x
- Node.js >= 18.x
- NPM >= 9.x
- MySQL >= 8.0 hoặc SQLite (mặc định)

## 🛠️ Cài đặt

### Quick Setup (Recommended)

```bash
# Clone repository
git clone <your-repo-url>
cd language-center-v2

# Setup tự động (install, migrate, seed)
composer run setup

# Hoặc chạy dev mode với concurrently
composer run dev
```

### Manual Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment setup
cp .env.example .env
php artisan key:generate

# 3. Database setup
touch database/database.sqlite  # Nếu dùng SQLite
php artisan migrate --seed

# 4. Storage link
php artisan storage:link

# 5. Build assets
npm run build

# 6. Start development servers
# Terminal 1: Laravel server
php artisan serve

# Terminal 2: Vite dev server
npm run dev

# Terminal 3: Queue worker (cho backup tự động)
php artisan queue:listen

# Terminal 4: Logs
php artisan pail
```

## 👤 Tài khoản mặc định

Sau khi seed database:

| Email | Password | Role |
|-------|----------|------|
| tony@example.com | password | Super Admin |
| admin@example.com | password | Admin |
| manager@example.com | password | Manager |
| user@example.com | password | User |

## 🗂️ Cấu trúc thư mục

```
app/
├── Http/
│   ├── Controllers/        # Controllers
│   ├── Middleware/         # Custom middleware
│   ├── Requests/           # Form Request validation
│   └── Resources/          # API Resources (nếu có)
├── Models/                 # Eloquent models
├── Services/               # Business logic services
│   ├── AutoBackupService.php
│   └── GoogleDriveService.php
├── Notifications/          # Laravel notifications
└── Helpers/                # Global helper functions
    └── RolePermissionHelpers.php

resources/
├── js/
│   ├── Components/         # Shared Vue components
│   ├── Pages/              # Inertia pages
│   ├── SakaiVue/          # Admin template
│   ├── composables/        # Vue composables
│   │   ├── usePermission.js
│   │   ├── useFlashMessages.js
│   │   ├── useI18n.js
│   │   └── useFormValidation.js
│   ├── services/           # Frontend services
│   │   ├── ToastService.js
│   │   ├── RoleService.js
│   │   └── UserService.js
│   └── plugins/            # Vue plugins
├── views/                  # Blade views (minimal)
└── lang/                   # Language files

database/
├── migrations/             # Database migrations
├── seeders/                # Database seeders
│   ├── DatabaseSeeder.php
│   ├── RolesAndPermissionsSeeder.php
│   └── UserSeeder.php
└── factories/              # Model factories
```

## 📚 Hướng dẫn sử dụng

### 1. Tạo CRUD mới

#### Backend

```bash
# 1. Tạo model, migration, controller, request
php artisan make:model Product -mcr
php artisan make:request StoreProductRequest
php artisan make:request UpdateProductRequest

# 2. Định nghĩa migration
# database/migrations/xxxx_create_products_table.php

# 3. Định nghĩa validation trong Request
# app/Http/Requests/StoreProductRequest.php
# app/Http/Requests/UpdateProductRequest.php

# 4. Implement controller với Inertia
# app/Http/Controllers/ProductController.php
```

**Example Controller:**

```php
use Inertia\Inertia;

public function index()
{
    $products = Product::paginate(10);
    return Inertia::render('ProductIndex', [
        'products' => $products
    ]);
}

public function store(StoreProductRequest $request)
{
    Product::create($request->validated());
    
    return redirect()->route('products.index')->with('flash', [
        'type' => 'success',
        'message' => 'Sản phẩm đã được tạo thành công!'
    ]);
}
```

#### Frontend

```bash
# Tạo page mới trong resources/js/Pages/
touch resources/js/Pages/ProductIndex.vue
touch resources/js/Pages/ProductCreate.vue
```

**Example Page:**

```vue
<template>
    <Head><title>Quản lý sản phẩm</title></Head>
    
    <div>
        <div class="card">
            <DataTable :value="products" paginator :rows="10">
                <Column field="name" header="Tên"></Column>
                <Column field="price" header="Giá"></Column>
                <Column header="Thao tác">
                    <template #body="slotProps">
                        <Button icon="pi pi-pencil" @click="edit(slotProps.data)" />
                        <Button icon="pi pi-trash" @click="confirmDelete(slotProps.data)" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';

defineProps({
    products: Object
});
</script>
```

### 2. Thêm Permission mới

```php
// database/seeders/RolesAndPermissionsSeeder.php

$permissions = [
    'view products',
    'create products',
    'edit products',
    'delete products',
];

foreach ($permissions as $permission) {
    Permission::create(['name' => $permission]);
}

// Gán cho role
$superAdmin->givePermissionTo('view products', 'create products', ...);
```

### 3. Sử dụng Permission trong Code

#### Backend (Middleware)

```php
// routes/web.php
Route::middleware(['auth', 'role:Super Admin|Admin'])->group(function () {
    Route::resource('products', ProductController::class);
});

// Hoặc dùng permission
Route::middleware(['auth', 'can:manage products'])->group(function () {
    // ...
});
```

#### Backend (Helper Functions)

```php
// Sử dụng helper functions
if (hasRole('Super Admin')) {
    // Logic cho Super Admin
}

if (hasPermission('edit products')) {
    // Logic cho user có permission
}

// Abort nếu không có quyền
abortUnlessHasRole('Admin');
abortUnlessHasPermission('delete products');
```

#### Frontend (Composable)

```vue
<script setup>
import { usePermission } from '@/composables/usePermission';

const { hasRole, can, isSuperAdmin } = usePermission();
</script>

<template>
    <Button v-if="isSuperAdmin()" label="Xóa" />
    <div v-if="can('edit products')">
        <!-- Edit form -->
    </div>
</template>
```

### 4. Toast Messages

#### Từ Backend

```php
// Success
return redirect()->back()->with('flash', [
    'type' => 'success',
    'message' => 'Thao tác thành công!'
]);

// Error
return redirect()->back()->with('flash', [
    'type' => 'error',
    'message' => 'Có lỗi xảy ra!'
]);

// Warning
return redirect()->back()->with('flash', [
    'type' => 'warning',
    'message' => 'Cảnh báo!'
]);
```

#### Từ Frontend

```javascript
import { ToastService } from '@/services/ToastService';

ToastService.success('Thành công!');
ToastService.error('Lỗi!');
ToastService.warn('Cảnh báo!');
ToastService.info('Thông tin!');
```

### 5. Backup System

#### Cấu hình Google Drive

1. Tạo OAuth 2.0 credentials tại Google Cloud Console
2. Thêm vào `.env`:

```env
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REDIRECT_URI=http://localhost:8000/auth/google-drive/callback
```

3. Vào `/backup/configurations` để cấu hình auto backup

#### Chạy Auto Backup

```bash
# Queue worker phải chạy
php artisan queue:listen

# Hoặc dùng cron job
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### 6. Activity Log

```php
// Log activity
activity()
    ->causedBy(Auth::user())
    ->performedOn($product)
    ->log('Đã tạo sản phẩm mới');

// Hoặc dùng helper
logActivity('Đã cập nhật sản phẩm', $product, [
    'old' => $oldData,
    'new' => $newData
]);
```

## 🎨 Theming

### Thay đổi theme

```javascript
// resources/js/app.js
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';

app.use(PrimeVue, {
    theme: {
        preset: Aura,
        options: {
            darkModeSelector: '.app-dark'
        }
    }
});
```

### Custom theme colors

Edit `tailwind.config.js` để thay đổi màu sắc theme.

## 🧪 Testing

```bash
# Run all tests
composer test

# Run specific test
php artisan test --filter=UserTest
```

## 📦 Deployment

### Production Build

```bash
# Build assets
npm run build

# Optimize Laravel
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Migrate database
php artisan migrate --force
```

### Environment Variables

Đảm bảo cấu hình đúng trong `.env` cho production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=your-db-host
DB_DATABASE=your-db-name
DB_USERNAME=your-db-user
DB_PASSWORD=your-db-password

# Mail
MAIL_MAILER=smtp
MAIL_HOST=your-mail-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-username
MAIL_PASSWORD=your-mail-password

# Queue (nên dùng redis hoặc database cho production)
QUEUE_CONNECTION=redis
```

## 🤝 Contributing

Đây là boilerplate nội bộ. Nếu có cải tiến, vui lòng tạo pull request.

## 📝 License

MIT License

## 🙏 Credits

- [Laravel](https://laravel.com)
- [Inertia.js](https://inertiajs.com)
- [Vue.js](https://vuejs.org)
- [PrimeVue](https://primevue.org)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission)
- [Spatie Laravel Activity Log](https://spatie.be/docs/laravel-activitylog)

---

**Developed with ❤️ for internal projects**
