# Tích hợp Role & Permission vào CRUD User

## Tổng quan

Tài liệu này mô tả chi tiết quá trình tích hợp tính năng Role & Permission vào module CRUD User, cho phép quản lý vai trò của người dùng một cách linh hoạt.

## Yêu cầu được triển khai

✅ **MultiSelect cho Roles**: Mỗi user có thể có nhiều vai trò
✅ **Roles bắt buộc**: Phải chọn ít nhất 1 vai trò khi tạo/sửa user
✅ **Manual Selection**: Admin tự chọn vai trò cho user (không tự động)
✅ **Backend Validation**: Validation đầy đủ ở cả StoreUserRequest và UpdateUserRequest
✅ **Frontend UI**: Hiển thị và chọn vai trò trong DataTable và Dialog

## Thay đổi Backend

### 1. UserResource.php

**File**: `app/Http/Resources/UserResource.php`

**Thêm relationship roles**:
```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'name' => $this->name,
        'email' => $this->email,
        'email_verified_at' => $this->email_verified_at,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
        
        // Relationships
        'roles' => RoleResource::collection($this->whenLoaded('roles'))->resolve(),
        'roles_count' => $this->whenCounted('roles'),
    ];
}
```

**Chú ý quan trọng**: 
- Sử dụng `RoleResource::collection()` để transform roles
- **BẮT BUỘC** gọi `->resolve()` sau collection để tránh lỗi mapping
- `whenLoaded('roles')` để eager loading tự động
- `whenCounted('roles_count')` để lấy số lượng roles

### 2. UserController.php

**File**: `app/Http/Controllers/UserController.php`

#### 2.1. Method index()

Load users với relationships:
```php
public function index()
{
    $users = User::with('roles')
        ->withCount('roles')
        ->latest()
        ->get();

    $roles = Role::select('id', 'name')->get();

    return Inertia::render('UserIndex', [
        'users' => UserResource::collection($users),
        'roles' => RoleResource::collection($roles)->resolve(),
    ]);
}
```

**Giải thích**:
- `User::with('roles')`: Eager load roles relationship
- `->withCount('roles')`: Load số lượng roles của mỗi user
- Load tất cả roles để gửi cho frontend (cho MultiSelect)
- Gửi cả `users` và `roles` cho component

#### 2.2. Method store()

Sync roles sau khi tạo user:
```php
public function store(StoreUserRequest $request)
{
    $validated = $request->validated();

    $validated['password'] = Hash::make($validated['password']);

    $user = User::create($validated);

    // Sync roles
    if (isset($validated['roles'])) {
        $user->syncRoles($validated['roles']);
    }

    return redirect()->route('users.index')
        ->with('flash', [
            'type' => 'success',
            'message' => 'users.createSuccess'
        ]);
}
```

**Chú ý**:
- `syncRoles()` là method của Spatie Laravel Permission
- Tự động xóa vai trò cũ và gán vai trò mới
- Nhận array of role IDs

#### 2.3. Method update()

Sync roles khi cập nhật user:
```php
public function update(UpdateUserRequest $request, User $user)
{
    $validated = $request->validated();

    $user->update($validated);

    // Sync roles
    if (isset($validated['roles'])) {
        $user->syncRoles($validated['roles']);
    }

    return redirect()->route('users.index')
        ->with('flash', [
            'type' => 'success',
            'message' => 'users.updateSuccess'
        ]);
}
```

### 3. StoreUserRequest.php

**File**: `app/Http/Requests/StoreUserRequest.php`

Thêm validation cho roles:
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'roles' => ['required', 'array'],
        'roles.*' => ['exists:roles,id'],
    ];
}

public function messages(): array
{
    return [
        // ... existing messages
        'roles.required' => 'Vai trò là bắt buộc',
        'roles.array' => 'Vai trò phải là một mảng',
        'roles.*.exists' => 'Vai trò được chọn không hợp lệ',
    ];
}
```

**Validation rules giải thích**:
- `'roles' => ['required', 'array']`: Roles bắt buộc và phải là mảng
- `'roles.*' => ['exists:roles,id']`: Mỗi role ID phải tồn tại trong bảng roles

### 4. UpdateUserRequest.php

**File**: `app/Http/Requests/UpdateUserRequest.php`

Thêm validation tương tự StoreUserRequest:
```php
public function rules(): array
{
    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => [
            'required',
            'string',
            'email',
            'max:255',
            Rule::unique('users')->ignore($this->user)
        ],
        'roles' => ['required', 'array'],
        'roles.*' => ['exists:roles,id'],
    ];
}

public function messages(): array
{
    return [
        // ... existing messages
        'roles.required' => 'Vai trò là bắt buộc',
        'roles.array' => 'Vai trò phải là một mảng',
        'roles.*.exists' => 'Vai trò được chọn không hợp lệ',
    ];
}
```

## Thay đổi Frontend

### 1. UserIndex.vue

**File**: `resources/js/Pages/UserIndex.vue`

#### 1.1. Props

Thêm prop roles:
```vue
const props = defineProps({
    users: {
        type: Array,
        default: () => []
    },
    roles: {
        type: Array,
        default: () => []
    }
});
```

#### 1.2. DataTable - Thêm cột Roles

```vue
<Column field="roles" :header="t('users.roles')" style="min-width: 12rem">
    <template #body="slotProps">
        <Tag 
            v-for="role in slotProps.data.roles" 
            :key="role.id" 
            :value="role.name" 
            severity="info" 
            class="mr-1" 
        />
    </template>
</Column>
```

**Giải thích**:
- Hiển thị vai trò dưới dạng Tag chips
- `severity="info"`: Màu xanh dương cho tags
- `class="mr-1"`: Margin phải giữa các tags

#### 1.3. Dialog - Thêm MultiSelect

```vue
<div>
    <label for="roles" class="block font-bold mb-3">{{ t('users.roles') }}</label>
    <MultiSelect
        id="roles"
        v-model="user.roles"
        :options="props.roles"
        optionLabel="name"
        optionValue="id"
        :placeholder="t('users.selectRoles')"
        :invalid="submitted && (!user.roles || user.roles.length === 0) || hasError('roles')"
        fluid
        display="chip"
    />
    <small v-if="submitted && (!user.roles || user.roles.length === 0)" class="text-red-500">
        Vai trò là bắt buộc
    </small>
    <small v-if="hasError('roles')" class="p-error block mt-1">
        {{ t(getError('roles')) }}
    </small>
</div>
```

**Props của MultiSelect**:
- `v-model="user.roles"`: Bind với mảng role IDs
- `:options="props.roles"`: Danh sách roles từ backend
- `optionLabel="name"`: Hiển thị tên role
- `optionValue="id"`: Giá trị là ID của role
- `display="chip"`: Hiển thị dạng chips khi chọn nhiều
- `fluid`: Full width
- `:invalid`: Hiển thị error state

#### 1.4. Validation trong saveUser()

```javascript
const saveUser = () => {
    submitted.value = true;

    // Basic client-side validation
    if (!user.value.name || !user.value.email || !user.value.roles || user.value.roles.length === 0) {
        return;
    }
    
    // ... rest of the code
    
    const userData = {
        name: user.value.name,
        email: user.value.email,
        roles: user.value.roles,  // Thêm roles vào payload
    };
    
    // ... rest of the code
};
```

#### 1.5. Edit User - Map role IDs

```javascript
const editUser = (userData) => {
    resetForm();
    user.value = { 
        ...userData,
        roles: userData.roles ? userData.roles.map(role => role.id) : []
    };
    userDialog.value = true;
};
```

**Giải thích quan trọng**:
- Backend trả về roles dưới dạng objects: `[{id: 1, name: 'Admin'}, ...]`
- MultiSelect cần array of IDs: `[1, 2, 3]`
- `userData.roles.map(role => role.id)`: Convert từ objects sang IDs
- Điều này đảm bảo MultiSelect hiển thị đúng roles đã chọn

### 2. useI18n.js

**File**: `resources/js/composables/useI18n.js`

Thêm translation keys cho roles:

```javascript
// Vietnamese
users: {
    // ... existing keys
    roles: 'Vai trò',
    selectRoles: 'Chọn vai trò',
}

// English
users: {
    // ... existing keys
    roles: 'Roles',
    selectRoles: 'Select Roles',
}
```

## Workflow hoàn chỉnh

### 1. Tạo User mới

```
1. User click "Thêm" button
2. Dialog hiển thị form với:
   - Name (required)
   - Email (required)
   - Password (required)
   - Confirm Password (required)
   - Roles MultiSelect (required)
3. Admin chọn vai trò từ MultiSelect
4. Click "Lưu"
5. Frontend validation:
   - Check all required fields
   - Check roles.length > 0
6. Submit to backend:
   POST /users
   {
     name: "...",
     email: "...",
     password: "...",
     password_confirmation: "...",
     roles: [1, 2]  // Array of role IDs
   }
7. Backend validation:
   - StoreUserRequest validates data
   - roles.required, roles.array
   - roles.*.exists:roles,id
8. Backend creates user:
   - User::create()
   - syncRoles([1, 2])
9. Redirect với flash message
10. Frontend update users list
11. Toast hiển thị "Tạo người dùng thành công!"
```

### 2. Sửa User

```
1. User click icon "Sửa" (pencil)
2. editUser() được gọi:
   - Copy userData
   - Map roles: [{id: 1, name: 'Admin'}] => [1]
3. Dialog hiển thị với data đã có:
   - Name filled
   - Email filled
   - Roles MultiSelect selected [1]
   - Password fields HIDDEN (not required when editing)
4. Admin thay đổi roles
5. Click "Lưu"
6. Frontend validation
7. Submit to backend:
   PUT /users/{id}
   {
     name: "...",
     email: "...",
     roles: [2, 3]  // New roles
   }
8. Backend validation:
   - UpdateUserRequest validates data
9. Backend updates user:
   - User::update()
   - syncRoles([2, 3])  // Removes old roles, assigns new ones
10. Redirect với flash message
11. Frontend update users list
12. Toast hiển thị "Cập nhật người dùng thành công!"
```

### 3. Hiển thị danh sách Users

```
1. Route GET /users
2. UserController@index():
   - Load User::with('roles')->withCount('roles')
   - Load all roles for MultiSelect
3. Return Inertia:
   - users: UserResource::collection
   - roles: RoleResource::collection->resolve()
4. Frontend receives props
5. DataTable renders:
   - Each row shows user info
   - Roles column shows Tags for each role
6. MultiSelect có sẵn options từ props.roles
```

## Database Structure

### users_roles (pivot table)

Được tạo tự động bởi Spatie Laravel Permission:

```
| user_id | role_id |
|---------|---------|
| 1       | 1       |
| 1       | 2       |
| 2       | 3       |
```

**Giải thích**:
- User ID 1 có 2 vai trò (role 1 và 2)
- User ID 2 có 1 vai trò (role 3)
- `syncRoles()` tự động quản lý bảng này

## Lưu ý quan trọng

### 1. Về Resource

⚠️ **BẮT BUỘC gọi `->resolve()`** khi return collection trong collection khác:

```php
// ĐÚNG
'roles' => RoleResource::collection($this->whenLoaded('roles'))->resolve(),

// SAI - Sẽ gây lỗi "roles.map is not a function"
'roles' => RoleResource::collection($this->whenLoaded('roles')),
```

### 2. Về MultiSelect

⚠️ **Phải map role objects thành IDs** khi edit:

```javascript
// ĐÚNG
roles: userData.roles ? userData.roles.map(role => role.id) : []

// SAI - MultiSelect sẽ không hiển thị selection
roles: userData.roles
```

### 3. Về Validation

⚠️ **Roles bắt buộc** ở cả create và update:

```php
'roles' => ['required', 'array'],  // Không để empty array
```

### 4. Về syncRoles()

✅ **syncRoles() tự động**:
- Xóa tất cả vai trò cũ
- Gán vai trò mới từ array
- Không cần xóa thủ công

```php
// Code này đã đủ
$user->syncRoles($validated['roles']);

// KHÔNG CẦN làm thế này
$user->roles()->detach();  // Không cần
$user->syncRoles($validated['roles']);
```

## Kiểm tra Integration

### Test Cases đã pass:

✅ **Create User với roles**
- Tạo user mới với 1 role
- Tạo user mới với nhiều roles
- Validation khi không chọn role
- Validation khi chọn role không tồn tại

✅ **Update User roles**
- Thay đổi roles của user
- Thêm roles mới
- Xóa roles cũ
- Validation tương tự create

✅ **Display Users với roles**
- Hiển thị đúng roles trong DataTable
- Tag hiển thị đẹp với màu info
- Spacing giữa các tags hợp lý

✅ **MultiSelect UI**
- Hiển thị đủ options
- Selected roles hiển thị đúng khi edit
- Chip display đẹp
- Placeholder text
- Error state

✅ **No Errors**
- Frontend compile thành công
- Backend không có PHP errors
- Toast messages hiển thị đúng
- Flash messages được centralized trong AppLayout

## Bước tiếp theo

Sau khi hoàn thành tích hợp User & Role, các bước tiếp theo:

### Phase 3: Route Protection & Permission Checking

1. **Backend Protection**:
   - Thêm middleware `role` cho routes users, roles
   - Thêm middleware `permission` cho backup routes
   - Ví dụ: `Route::middleware('role:Super Admin|Admin')->group()`

2. **Frontend Permission Helper**:
   - Tạo composable `usePermission.js`
   - Implement `can()` function
   - Sử dụng để ẩn/hiện UI elements

3. **Conditional Rendering**:
   - Ẩn nút "Xóa" nếu không có permission
   - Ẩn nút "Sửa" nếu không có permission
   - Ẩn menu items dựa trên roles/permissions

4. **API Protection**:
   - Protect controllers với `authorize()`
   - Return 403 nếu không có permission
   - Log activity cho các action quan trọng

## Kết luận

✅ **Backend Integration**: Hoàn thành 100%
- UserResource với roles relationship
- UserController sync roles on create/update
- Validation đầy đủ trong Request classes

✅ **Frontend Integration**: Hoàn thành 100%
- MultiSelect component
- Tags display in DataTable
- Proper role ID mapping
- Client-side validation

✅ **No Errors**: Clean compilation
- Backend PHP check passed
- Frontend build successful
- Dev server running on port 5175

🎯 **Kết quả**: User CRUD đã được tích hợp đầy đủ với Role & Permission system. Admin có thể quản lý vai trò của users một cách dễ dàng thông qua giao diện MultiSelect, với validation đầy đủ ở cả backend và frontend.
