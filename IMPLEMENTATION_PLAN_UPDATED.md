# CẬP NHẬT KẾ HOẠCH - THEO CHUẨN DỰ ÁN

## 🎯 CÁC THAY ĐỔI QUAN TRỌNG

### 1. ✅ **Sửa Migration Trực Tiếp (Môi trường DEV)**
- **KHÔNG** tạo migration mới để add column
- **SỬA TRỰC TIẾP** vào file migration hiện có
- Ví dụ: Thêm columns vào users → Sửa file `0001_01_01_000000_create_users_table.php`

### 2. ✅ **Enum Labels từ Backend**
- Backend return labels tiếng Việt qua Resource
- FE **KHÔNG định nghĩa lại** enum constants
- Pattern: Model có methods `getXxxLabel()`, `getXxxColor()`

### 3. ✅ **CRUD Pattern theo Role Module**

#### **Backend Pattern:**
```php
// 1. Form Request với messages tiếng Việt
class StoreVendorReportRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'workflow_type' => ['required', 'in:NORMAL,SPECIAL_1,...'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề phiếu là bắt buộc',
            'workflow_type.required' => 'Loại quy trình là bắt buộc',
        ];
    }
}

// 2. Model có enum label methods
class VendorReport extends Model
{
    public function getWorkflowTypeLabel(): string
    {
        return match($this->workflow_type) {
            'NORMAL' => 'Quy trình thông thường',
            'SPECIAL_1' => 'Quy trình đặc biệt 1',
            // ...
        };
    }
    
    public function getStatusLabel(): string { ... }
    public function getStatusColor(): string { ... }
}

// 3. Resource return labels
class VendorReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(), // ← Labels từ Model
            'status_color' => $this->getStatusColor(),
            'workflow_type' => $this->workflow_type,
            'workflow_type_label' => $this->getWorkflowTypeLabel(),
            // ...
        ];
    }
}

// 4. Controller
class VendorReportController extends Controller
{
    public function index(Request $request)
    {
        $reports = VendorReport::with(['creator'])->latest()->paginate(10);
        
        return Inertia::render('VendorReports/Index', [
            'reports' => VendorReportResource::collection($reports),
        ]);
    }
    
    public function store(StoreVendorReportRequest $request)
    {
        $report = VendorReport::create($request->validated());
        
        return redirect()->route('vendor-reports.index')
            ->with('success', 'Tạo phiếu thành công'); // Flash message
    }
}
```

#### **Frontend Pattern:**

```javascript
// 1. Service file (services/VendorReportService.js)
import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';

export class VendorReportService {
    static index(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        
        router.get('/vendor-reports', {}, {
            onStart: () => onStart?.(),
            onFinish: () => onFinish?.(),
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                }
                onError?.(errors);
            },
            onSuccess: () => onSuccess?.()
        });
    }
    
    static store(data, options = {}) {
        router.post('/vendor-reports', data, {
            // Same pattern
            onError: (errors) => {
                // General error → Toast
                if (errors.message) {
                    ToastService.error(errors.message);
                }
                // Field validation errors → Display under form
                onError?.(errors);
            },
        });
    }
}
```

```vue
<!-- 2. Vue component (Pages/VendorReports/Index.vue) -->
<template>
    <div class="card">
        <DataTable :value="reports" :loading="loading">
            <Column field="code" header="Mã phiếu" />
            
            <!-- Hiển thị label từ BE, KHÔNG định nghĩa lại -->
            <Column field="status_label" header="Trạng thái">
                <template #body="{ data }">
                    <Tag :severity="data.status_color">
                        {{ data.status_label }}
                    </Tag>
                </template>
            </Column>
            
            <Column field="workflow_type_label" header="Quy trình" />
        </DataTable>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { VendorReportService } from '@/services';

// Props từ Inertia
const props = defineProps({
    reports: Object,
});

const loading = ref(false);

const loadData = () => {
    VendorReportService.index({
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};
</script>
```

---

## 📝 CẬP NHẬT CHI TIẾT CÁC PHASE

### **PHASE 1: DATABASE**

#### **Task 1.1.2: Update Users - SỬA TRỰC TIẾP**
```php
// File: database/migrations/0001_01_01_000000_create_users_table.php
// Thêm vào Schema::create('users', ...)
$table->foreignId('department_id')->nullable()->after('email')
    ->constrained('departments')->nullOnDelete();
$table->boolean('is_active')->default(true)->after('password');
$table->timestamp('last_login_at')->nullable()->after('is_active');
```

#### **Task 1.2.3: VendorReport Model - Thêm Enum Label Methods**
```php
class VendorReport extends Model
{
    // ... fillable, casts ...
    
    // ⭐ Methods for Resource
    public function getWorkflowTypeLabel(): string
    {
        return match($this->workflow_type) {
            'NORMAL' => 'Quy trình thông thường',
            'SPECIAL_1' => 'Quy trình đặc biệt 1',
            'SPECIAL_2' => 'Quy trình đặc biệt 2',
            'SPECIAL_3' => 'Quy trình đặc biệt 3',
            'URGENT' => 'Quy trình khẩn cấp',
            default => $this->workflow_type,
        };
    }
    
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'DRAFT' => 'Nháp',
            'SUBMITTED' => 'Đã gửi',
            'IN_APPROVAL' => 'Đang duyệt',
            'APPROVED' => 'Đã duyệt',
            'REJECTED' => 'Từ chối',
            default => $this->status,
        };
    }
    
    public function getStatusColor(): string
    {
        return match($this->status) {
            'DRAFT' => 'info',
            'SUBMITTED' => 'primary',
            'IN_APPROVAL' => 'warning',
            'APPROVED' => 'success',
            'REJECTED' => 'danger',
            default => 'secondary',
        };
    }
    
    // Relationships
    public function creator() { ... }
    public function purchasingAdmin() { ... }
    // ... other relationships
}
```

---

### **PHASE 2: BACKEND**

#### **Task 2.5: Form Requests - Pattern StoreRoleRequest**
```php
class StoreVendorReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy handles authorization
    }
    
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'workflow_type' => ['required', 'in:NORMAL,SPECIAL_1,SPECIAL_2,SPECIAL_3,URGENT'],
            'purchasing_admin_id' => ['nullable', 'exists:users,id'],
        ];
    }
    
    public function messages(): array
    {
        return [
            'title.required' => 'Tiêu đề phiếu là bắt buộc',
            'title.max' => 'Tiêu đề không được vượt quá 255 ký tự',
            'workflow_type.required' => 'Loại quy trình là bắt buộc',
            'workflow_type.in' => 'Loại quy trình không hợp lệ',
            'purchasing_admin_id.exists' => 'Người quản lý mua sắm không tồn tại',
        ];
    }
}
```

---

### **PHASE 3: CONTROLLERS & RESOURCES**

#### **Task 3.0: Resources - QUAN TRỌNG**
```php
class VendorReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            
            // Enum raw values
            'workflow_type' => $this->workflow_type,
            'status' => $this->status,
            
            // ⭐ LABELS từ Model methods
            'workflow_type_label' => $this->getWorkflowTypeLabel(),
            'status_label' => $this->getStatusLabel(),
            'status_color' => $this->getStatusColor(),
            
            // Relationships
            'creator' => new UserResource($this->whenLoaded('creator')),
            'purchasing_admin' => new UserResource($this->whenLoaded('purchasingAdmin')),
            'current_step' => new VendorReportApprovalStepResource($this->whenLoaded('currentStep')),
            
            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'submitted_at' => $this->submitted_at,
            'approved_at' => $this->approved_at,
            'rejected_at' => $this->rejected_at,
        ];
    }
}
```

#### **Task 3.1: Controllers - Pattern RoleController**
```php
class VendorReportController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', VendorReport::class);
        
        $reports = VendorReport::query()
            ->with(['creator', 'purchasingAdmin'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->workflow_type, fn($q, $type) => $q->where('workflow_type', $type))
            ->latest()
            ->paginate(10);
        
        return Inertia::render('VendorReports/Index', [
            'reports' => VendorReportResource::collection($reports),
            'filters' => $request->only(['status', 'workflow_type']),
        ]);
    }
    
    public function store(StoreVendorReportRequest $request)
    {
        $this->authorize('create', VendorReport::class);
        
        $validated = $request->validated();
        $validated['created_by'] = auth()->id();
        $validated['status'] = 'DRAFT';
        
        $report = VendorReport::create($validated);
        
        return redirect()->route('vendor-reports.index')
            ->with('success', 'Tạo phiếu thành công');
    }
    
    public function update(UpdateVendorReportRequest $request, VendorReport $report)
    {
        $this->authorize('update', $report);
        
        $report->update($request->validated());
        
        return redirect()->route('vendor-reports.index')
            ->with('success', 'Cập nhật phiếu thành công');
    }
}
```

---

### **PHASE 4: FRONTEND**

#### **Task 4.1: Service Pattern - Theo RoleService**
```javascript
// File: resources/js/services/VendorReportService.js
import { router } from '@inertiajs/vue3';
import { ToastService } from './ToastService';

export class VendorReportService {
    static index(options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        
        router.get('/vendor-reports', {}, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                if (errors.message) {
                    ToastService.error(errors.message);
                } else {
                    ToastService.error('Có lỗi xảy ra khi tải danh sách phiếu!');
                }
                if (onError) onError(errors);
            },
            onSuccess: () => {
                if (onSuccess) onSuccess();
            }
        });
    }
    
    static store(data, options = {}) {
        const { onStart, onFinish, onError, onSuccess } = options;
        
        router.post('/vendor-reports', data, {
            onStart: () => {
                if (onStart) onStart();
            },
            onFinish: () => {
                if (onFinish) onFinish();
            },
            onError: (errors) => {
                // General errors → Toast
                if (errors.message) {
                    ToastService.error(errors.message);
                } else if (Object.keys(errors).length === 0) {
                    ToastService.error('Có lỗi xảy ra khi tạo phiếu!');
                }
                // Field validation errors → Display under form
                if (onError) onError(errors);
            },
            onSuccess: (page) => {
                // Success từ flash message (backend)
                if (onSuccess) onSuccess(page);
            }
        });
    }
    
    static update(id, data, options = {}) {
        // Same pattern
    }
    
    static destroy(id, options = {}) {
        // Same pattern
    }
    
    // Custom actions
    static submit(id, options = {}) {
        router.post(`/vendor-reports/${id}/submit`, {}, {
            // Same pattern
        });
    }
}

// Export
export { VendorReportService };
```

```javascript
// File: resources/js/services/index.js
export { AuthService } from './AuthService';
export { RoleService } from './RoleService';
export { UserService } from './UserService';
export { VendorReportService } from './VendorReportService'; // ← Add this
export { ToastService } from './ToastService';
```

#### **Task 4.3: Vue Components - KHÔNG có composables**
```vue
<!-- File: resources/js/Pages/VendorReports/Index.vue -->
<template>
    <Head>
        <title>Quản Lý Phiếu</title>
    </Head>

    <div class="card">
        <Toolbar class="mb-6">
            <template #start>
                <Button label="Thêm phiếu" icon="pi pi-plus" @click="openNew" />
            </template>
        </Toolbar>

        <DataTable
            :value="reports.data || []"
            :loading="loading"
            dataKey="id"
        >
            <Column field="code" header="Mã phiếu" sortable />
            <Column field="title" header="Tiêu đề" sortable />
            
            <!-- ⭐ Hiển thị label từ BE, KHÔNG định nghĩa lại enum -->
            <Column field="status_label" header="Trạng thái">
                <template #body="{ data }">
                    <Tag :severity="data.status_color">
                        {{ data.status_label }}
                    </Tag>
                </template>
            </Column>
            
            <Column field="workflow_type_label" header="Quy trình" />
            <Column field="creator.name" header="Người tạo" />
            <Column field="created_at" header="Ngày tạo" />
            
            <Column header="Thao tác">
                <template #body="{ data }">
                    <Button icon="pi pi-eye" @click="view(data.id)" />
                    <Button v-if="data.status === 'DRAFT'" 
                            icon="pi pi-pencil" 
                            @click="edit(data.id)" />
                </template>
            </Column>
        </DataTable>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { VendorReportService } from '@/services'; // ← Import Service
import { Head } from '@inertiajs/vue3';
import DataTable from 'primevue/datatable';
import Column from 'primevue/column';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Toolbar from 'primevue/toolbar';

// Props từ Inertia
const props = defineProps({
    reports: Object,
});

// Local state
const loading = ref(false);

// Actions sử dụng Service
const openNew = () => {
    VendorReportService.create({
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};

const view = (id) => {
    VendorReportService.show(id, {
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};

const edit = (id) => {
    VendorReportService.edit(id, {
        onStart: () => loading.value = true,
        onFinish: () => loading.value = false,
    });
};
</script>
```

---

## 🎯 CHECKLIST TỔNG HỢP

### Backend:
- [ ] Sửa trực tiếp migration users (không tạo migration mới)
- [ ] Model có methods: `getXxxLabel()`, `getXxxColor()`
- [ ] FormRequest với `messages()` tiếng Việt
- [ ] Resource return labels từ Model methods
- [ ] Controller return VendorReportResource
- [ ] Flash messages tiếng Việt trong Controller

### Frontend:
- [ ] Service pattern theo RoleService
- [ ] KHÔNG định nghĩa enum constants/labels
- [ ] Vue component chỉ có template + import Service
- [ ] Hiển thị `xxx_label`, `xxx_color` từ BE
- [ ] Toast/validation messages từ BE
- [ ] Inertia + Vue 3 Composition API + PrimeVue v4

---

**LƯU Ý QUAN TRỌNG:**
1. **Migration:** Sửa trực tiếp trong DEV
2. **Enum Labels:** BE trả về qua Resource, FE chỉ hiển thị
3. **CRUD Pattern:** Học theo RoleController + RoleService + RoleIndex.vue
