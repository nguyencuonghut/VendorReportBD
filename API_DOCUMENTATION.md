# API Documentation

## Flash Messages

### Backend Response Format

```php
// Success message
return redirect()->back()->with('flash', [
    'type' => 'success',
    'message' => 'Operation completed successfully!'
]);

// Error message
return redirect()->back()->with('flash', [
    'type' => 'error',
    'message' => 'An error occurred!'
]);

// Warning message
return redirect()->back()->with('flash', [
    'type' => 'warning',
    'message' => 'Warning message'
]);

// Info message
return redirect()->back()->with('flash', [
    'type' => 'info',
    'message' => 'Information message'
]);
```

### Available Toast Types

- `success` - Green toast for successful operations
- `error` - Red toast for errors
- `warning` - Orange toast for warnings
- `info` - Blue toast for information

---

## Role & Permission Helpers

### Backend Helpers

```php
// Check if user has role
if (hasRole('Super Admin')) {
    // Logic
}

// Check if user has any role
if (hasAnyRole(['Admin', 'Manager'])) {
    // Logic
}

// Check if user has all roles
if (hasAllRoles(['Admin', 'Manager'])) {
    // Logic
}

// Check if user has permission
if (hasPermission('edit users')) {
    // Logic
}

// Abort if user doesn't have role
abortUnlessHasRole('Super Admin');

// Abort if user doesn't have permission
abortUnlessHasPermission('delete users');

// Get current user roles
$roles = currentUserRoles(); // ['Super Admin', 'Admin']

// Get current user permissions
$permissions = currentUserPermissions(); // ['view users', 'edit users', ...]
```

### Frontend Composable

```javascript
import { usePermission } from '@/composables/usePermission';

const {
    user,
    userRoles,
    userPermissions,
    hasRole,
    hasAnyRole,
    hasAllRoles,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    can,
    isSuperAdmin,
    isAdmin,
    canManageUsers,
    canManageRoles,
    canManageBackups
} = usePermission();

// Usage
if (hasRole('Super Admin')) { }
if (can('edit users')) { }
if (isSuperAdmin()) { }
```

---

## Activity Log

### Log Activities

```php
// Simple log
activity()
    ->causedBy(Auth::user())
    ->log('User logged in');

// Log with subject
activity()
    ->causedBy(Auth::user())
    ->performedOn($user)
    ->log('Updated user profile');

// Log with properties
activity()
    ->causedBy(Auth::user())
    ->performedOn($product)
    ->withProperties([
        'old' => $oldAttributes,
        'new' => $newAttributes
    ])
    ->log('Updated product');

// Using helper
logActivity('Created new product', $product, [
    'category' => $product->category,
    'price' => $product->price
]);
```

### Retrieve Activities

```php
// Get recent activities (default 10)
$activities = getRecentActivities();

// Get recent activities with custom limit
$activities = getRecentActivities(50);

// Get user-specific activities
$activities = getUserActivities($userId, 20);

// Advanced query
$activities = Activity::with('causer', 'subject')
    ->where('log_name', 'user_management')
    ->latest()
    ->paginate(15);
```

---

## Form Validation

### Backend Validation (Form Requests)

```php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'category_id' => ['required', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Product name is required',
            'price.required' => 'Price is required',
            'price.numeric' => 'Price must be a number',
        ];
    }
}
```

### Frontend Validation (Composable)

```javascript
import { useFormValidation } from '@/composables/useFormValidation';

const { errors, setErrors, clearErrors, hasError, getError } = useFormValidation();

// Set errors from backend response
setErrors(response.errors);

// Check if field has error
if (hasError('email')) {
    // Show error
}

// Get error message for field
const errorMessage = getError('email');

// Clear all errors
clearErrors();

// Clear specific field error
clearErrors('email');
```

---

## Internationalization (i18n)

### Backend Translation

```php
// In controller
return redirect()->back()->with('flash', [
    'type' => 'success',
    'message' => __('users.created') // Translatable key
]);
```

### Frontend Translation

```javascript
import { useI18n } from '@/composables/useI18n';

const { t, locale, setLocale, availableLocales } = useI18n();

// Translate
const message = t('users.created');

// Change locale
setLocale('vi');
```

### Language Files

```
lang/
├── en/
│   ├── auth.php
│   ├── users.php
│   └── validation.php
└── vi/
    ├── auth.php
    ├── users.php
    └── validation.php
```

---

## Backup Configuration

### Manual Backup

```php
// Controller method
public function backup(Request $request)
{
    $backupPath = null;
    $zipPath = null;

    try {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $backupName = "backup_{$timestamp}";
        
        // Create backup
        // ... (see BackupController for full implementation)
        
        return response()->download($zipPath, $backupName . '.zip')
            ->deleteFileAfterSend(true);
    } catch (\Exception $e) {
        return redirect()->back()->with('flash', [
            'type' => 'error',
            'message' => 'Backup failed: ' . $e->getMessage()
        ]);
    }
}
```

### Auto Backup Configuration

```php
BackupConfiguration::create([
    'name' => 'Daily Database Backup',
    'schedule' => [
        'frequency' => 'daily',
        'time' => '02:00'
    ],
    'backup_options' => [
        'database' => true,
        'env_file' => true,
        'uploaded_files' => false
    ],
    'google_drive_enabled' => true,
    'google_drive_config' => [
        'folder_id' => 'xxxxxxxx',
        'tokens' => [...],
    ],
    'notification_emails' => ['admin@example.com'],
    'retention_days' => 30,
    'created_by' => Auth::id()
]);
```

### Google Drive Integration

```bash
# Environment variables
GOOGLE_DRIVE_CLIENT_ID=your-client-id
GOOGLE_DRIVE_CLIENT_SECRET=your-client-secret
GOOGLE_DRIVE_REDIRECT_URI=http://localhost:8000/auth/google-drive/callback
```

```php
// GoogleDriveService usage
$service = app(GoogleDriveService::class);
$service->setTokens($tokens);

// Upload file
$fileId = $service->uploadFile($filePath, $fileName, $folderId);

// Create folder
$folderId = $service->createFolder('Backup Folder', $parentFolderId);

// Test connection
$isConnected = $service->testConnection();
```

---

## Common Patterns

### CRUD Controller Pattern

```php
public function index()
{
    $items = Model::paginate(10);
    return Inertia::render('ModelIndex', ['items' => $items]);
}

public function store(StoreRequest $request)
{
    Model::create($request->validated());
    
    return redirect()->route('models.index')->with('flash', [
        'type' => 'success',
        'message' => 'Created successfully!'
    ]);
}

public function update(UpdateRequest $request, Model $model)
{
    $model->update($request->validated());
    
    return redirect()->route('models.index')->with('flash', [
        'type' => 'success',
        'message' => 'Updated successfully!'
    ]);
}

public function destroy(Model $model)
{
    $model->delete();
    
    return redirect()->route('models.index')->with('flash', [
        'type' => 'success',
        'message' => 'Deleted successfully!'
    ]);
}
```

### Inertia Page Pattern

```vue
<template>
    <Head><title>{{ title }}</title></Head>
    
    <div>
        <div class="card">
            <!-- Content -->
        </div>
    </div>
</template>

<script setup>
import { Head } from '@inertiajs/vue3';
import { usePermission } from '@/composables/usePermission';
import { useFlashMessages } from '@/composables/useFlashMessages';

const { isSuperAdmin } = usePermission();
const { handleFlashMessages } = useFlashMessages();

defineProps({
    items: Object
});

// Handle flash messages on mount
onMounted(() => {
    handleFlashMessages();
});
</script>
```

---

## Environment Variables

### Required Variables

```env
# App
APP_NAME=
APP_ENV=
APP_KEY=
APP_URL=

# Database
DB_CONNECTION=
DB_HOST=
DB_PORT=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

# Mail
MAIL_MAILER=
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=

# Google Drive (Optional)
GOOGLE_DRIVE_CLIENT_ID=
GOOGLE_DRIVE_CLIENT_SECRET=
GOOGLE_DRIVE_REDIRECT_URI=
```

---

## Best Practices

1. **Always use Form Requests** for validation
2. **Use Policies** for complex authorization logic
3. **Log activities** for audit trail
4. **Use transactions** for multiple database operations
5. **Return proper flash messages** for user feedback
6. **Use composables** for reusable logic in Vue
7. **Keep controllers thin**, move business logic to Services
8. **Write tests** for critical features
9. **Use TypeScript** for better type safety (optional enhancement)
10. **Document your code** with PHPDoc and JSDoc
