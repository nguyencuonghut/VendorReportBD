# KẾ HOẠCH TRIỂN KHAI HỆ THỐNG QUẢN LÝ BÁO CÁO LỰA CHỌN NHÀ CUNG CẤP

**Ngày tạo:** 29/01/2026  
**Dự án:** Vendor Report Management System  
**Framework:** Laravel 12 + Inertia.js + Vue 3 + PrimeVue 4

---

## 📊 TỔNG QUAN HIỆN TRẠNG

### ✅ Đã hoàn thành:
1. **Hạ tầng cơ bản**:
   - Laravel 12.49.0 đã cài đặt
   - Inertia.js + Vue 3 + PrimeVue 4 đã tích hợp
   - Spatie Laravel Permission (RBAC)
   - Spatie Activity Log
   - Authentication (Login/Logout/Password Reset)
   - Layout chính với SakaiVue

2. **Modules hiện có**:
   - User Management (CRUD + soft delete + restore)
   - Role Management (CRUD + bulk operations)
   - Activity Log (view + clear)
   - Backup System (Google Drive integration)

3. **Database**:
   - Users table (có soft delete)
   - Roles & Permissions tables (spatie)
   - Activity log tables
   - Backup tables
   - Queue tables

### ❌ Cần triển khai:
1. **Department Module** - Chưa có
2. **Vendor Report Module** - Chưa có
3. **Workflow Engine** - Chưa có
4. **Email Notifications** - Chưa cấu hình đầy đủ
5. **File Management** - Chưa có

---

## 📋 KẾ HOẠCH CHI TIẾT THEO PHASE

---

## **PHASE 1: CƠ SỞ DỮ LIỆU & MODELS** (2-3 ngày)

### 1.1. Migrations (Ưu tiên: CAO 🔴)

#### **Task 1.1.1: Department Migration**
```bash
php artisan make:migration create_departments_table
```

**Nội dung migration:**
```php
Schema::create('departments', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique(); // TM, KSNB, BGD, etc.
    $table->string('name');
    $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('parent_id')->nullable()->constrained('departments')->nullOnDelete();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index('code');
    $table->index('is_active');
});
```

**Checklist:**
- [ ] Tạo migration file
- [ ] Kiểm tra foreign key constraints
- [ ] Test migration up/down
- [ ] Thêm indexes phù hợp

---

#### **Task 1.1.2: Update Users Table**
```bash
php artisan make:migration add_department_fields_to_users_table
```

**Nội dung:**
```php
Schema::table('users', function (Blueprint $table) {
    $table->foreignId('department_id')->nullable()->after('email')->constrained('departments')->nullOnDelete();
    $table->boolean('is_active')->default(true)->after('password');
    $table->timestamp('last_login_at')->nullable()->after('is_active');
});
```

**Checklist:**
- [ ] Thêm department_id
- [ ] Thêm is_active flag
- [ ] Thêm last_login_at timestamp
- [ ] Test migration

---

#### **Task 1.1.3: Yearly Sequences Table**
```bash
php artisan make:migration create_yearly_sequences_table
```

**Nội dung:**
```php
Schema::create('yearly_sequences', function (Blueprint $table) {
    $table->id();
    $table->integer('year')->unique(); // Unique constraint: mỗi năm chỉ có 1 record
    $table->integer('current_seq')->default(0); // Sequence global cho tất cả phòng ban
    $table->timestamps();
});
```

**Logic:**
- Sequence là **GLOBAL** cho tất cả phòng ban
- Ví dụ: 2026/TM/024, 2026/BT/025, 2026/KSNB/026
- Năm mới → sequence reset về 1
- Không cần `department_id` vì sequence chung

**Checklist:**
- [ ] Tạo bảng với unique constraint trên year
- [ ] Test migration

---

#### **Task 1.1.4: Vendor Reports Table**
```bash
php artisan make:migration create_vendor_reports_table
```

**Nội dung:**
```php
Schema::create('vendor_reports', function (Blueprint $table) {
    $table->id();
    $table->string('code')->unique()->nullable(); // YYYY/DEPT/SEQ
    $table->string('title');
    $table->enum('workflow_type', ['NORMAL', 'SPECIAL_1', 'SPECIAL_2', 'SPECIAL_3', 'URGENT']);
    $table->foreignId('purchasing_admin_id')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
    $table->enum('status', ['DRAFT', 'SUBMITTED', 'IN_APPROVAL', 'APPROVED', 'REJECTED'])->default('DRAFT');
    $table->foreignId('current_step_id')->nullable()->constrained('vendor_report_approval_steps')->nullOnDelete();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('approved_at')->nullable();
    $table->timestamp('rejected_at')->nullable();
    $table->text('rejected_note')->nullable();
    $table->foreignId('parent_id')->nullable()->constrained('vendor_reports')->nullOnDelete();
    $table->foreignId('root_id')->nullable()->constrained('vendor_reports')->nullOnDelete();
    $table->timestamps();
    $table->softDeletes();
    
    $table->index('code');
    $table->index('status');
    $table->index('workflow_type');
    $table->index('created_by');
});
```

**Checklist:**
- [ ] Định nghĩa tất cả enums
- [ ] Thêm foreign keys
- [ ] Thêm indexes quan trọng
- [ ] Soft delete support
- [ ] Test migration

---

#### **Task 1.1.5: Vendor Report Files Table**
```bash
php artisan make:migration create_vendor_report_files_table
```

**Nội dung:**
```php
Schema::create('vendor_report_files', function (Blueprint $table) {
    $table->id();
    $table->foreignId('report_id')->constrained('vendor_reports')->cascadeOnDelete();
    $table->enum('type', ['REPORT_IMAGE', 'QUOTATION', 'BOQ']);
    $table->string('disk')->default('private');
    $table->string('path');
    $table->string('original_name');
    $table->string('mime')->nullable();
    $table->unsignedBigInteger('size')->nullable();
    $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
    $table->timestamps();
    
    $table->index('report_id');
    $table->index('type');
});
```

**Checklist:**
- [ ] Định nghĩa file types
- [ ] Foreign key cascades
- [ ] Indexes
- [ ] Test migration

---

#### **Task 1.1.6: Vendor Report Approval Steps Table**
```bash
php artisan make:migration create_vendor_report_approval_steps_table
```

**Nội dung:**
```php
Schema::create('vendor_report_approval_steps', function (Blueprint $table) {
    $table->id();
    $table->foreignId('report_id')->constrained('vendor_reports')->cascadeOnDelete();
    $table->string('step_key'); // DEPT_HEAD, PURCHASING_ADMIN, INTERNAL_CONTROL, etc.
    $table->integer('step_order');
    $table->enum('status', ['PENDING', 'APPROVED', 'REJECTED', 'SKIPPED'])->default('PENDING');
    $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
    $table->string('assignee_role')->nullable();
    $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('acted_at')->nullable();
    $table->text('note')->nullable();
    $table->boolean('requires_selection')->default(false);
    $table->string('selection_role')->nullable();
    $table->foreignId('selected_next_approver_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
    
    $table->index('report_id');
    $table->index('step_order');
    $table->index('status');
    $table->index('assignee_user_id');
});
```

**Checklist:**
- [ ] Tất cả fields theo thiết kế
- [ ] Foreign keys đầy đủ
- [ ] Indexes hiệu quả
- [ ] Test migration

---

### 1.2. Models (Ưu tiên: CAO 🔴)

#### **Task 1.2.1: Department Model**
```bash
php artisan make:model Department
```

**Nội dung:**
```php
class Department extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;
    
    protected $fillable = [
        'code', 'name', 'head_user_id', 'parent_id', 'is_active'
    ];
    
    protected $casts = [
        'is_active' => 'boolean',
    ];
    
    // Relationships
    public function headUser()
    public function parent()
    public function children()
    public function users()
    public function vendorReports()
    
    // Activity Log configuration
    public function getActivitylogOptions(): LogOptions
}
```

**Checklist:**
- [ ] Tạo model
- [ ] Định nghĩa fillable
- [ ] Định nghĩa relationships
- [ ] Configure activity log
- [ ] Thêm scopes (active, etc.)

---

#### **Task 1.2.2: YearlySequence Model**
```bash
php artisan make:model YearlySequence
```

**Nội dung:**
```php
class YearlySequence extends Model
{
    use HasFactory;
    
    protected $fillable = ['year', 'current_seq'];
    
    protected $casts = [
        'year' => 'integer',
        'current_seq' => 'integer',
    ];
}
```

**Checklist:**
- [ ] Tạo model
- [ ] Fillable fields
- [ ] Casts
- [ ] Unique constraint validation (year unique)

---

#### **Task 1.2.3: VendorReport Model**
```bash
php artisan make:model VendorReport
```

**Nội dung đầy đủ:**
```php
class VendorReport extends Model
{
    use HasFactory, LogsActivity, SoftDeletes;
    
    protected $fillable = [
        'code', 'title', 'workflow_type', 'purchasing_admin_id',
        'created_by', 'status', 'current_step_id',
        'submitted_at', 'approved_at', 'rejected_at', 'rejected_note',
        'parent_id', 'root_id'
    ];
    
    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];
    
    // Relationships
    public function creator()
    public function purchasingAdmin()
    public function currentStep()
    public function approvalSteps()
    public function files()
    public function parent()
    public function children()
    public function root()
    public function chain()
    
    // Scopes
    public function scopeDraft()
    public function scopeSubmitted()
    public function scopeInApproval()
    public function scopeApproved()
    public function scopeRejected()
    
    // Helper methods
    public function isDraft()
    public function isSubmitted()
    public function isRejected()
    public function canBeEdited()
    public function canBeSubmitted()
    public function canBeCloned()
}
```

**Checklist:**
- [ ] Tất cả fillable fields
- [ ] Casts cho datetime
- [ ] Enum casts (Laravel 12)
- [ ] Relationships đầy đủ
- [ ] Scopes
- [ ] Helper methods
- [ ] Activity log config

---

#### **Task 1.2.4: VendorReportFile Model**
```bash
php artisan make:model VendorReportFile
```

**Checklist:**
- [ ] Fillable fields
- [ ] Relationship với VendorReport, User
- [ ] Storage helpers

---

#### **Task 1.2.5: VendorReportApprovalStep Model**
```bash
php artisan make:model VendorReportApprovalStep
```

**Checklist:**
- [ ] Fillable fields
- [ ] Relationships (report, assignee, acted_by, selected_next_approver)
- [ ] Helper methods (isPending, isApproved, canActOn)
- [ ] Activity log

---

#### **Task 1.2.6: Update User Model**

**Thêm vào User.php:**
```php
// Add to fillable
'department_id', 'is_active', 'last_login_at'

// Add to casts
'is_active' => 'boolean',
'last_login_at' => 'datetime',

// Relationships
public function department()
public function createdReports()
public function assignedSteps()
public function actsOnSteps()
```

**Checklist:**
- [ ] Cập nhật fillable
- [ ] Cập nhật casts
- [ ] Thêm relationships
- [ ] Test relationships

---

### 1.3. Seeders (Ưu tiên: TRUNG 🟡)

#### **Task 1.3.1: Department Seeder**
```bash
php artisan make:seeder DepartmentSeeder
```

**Tạo departments mẫu:**
- BGD (Ban Giám Đốc)
- TM (Tài Chính)
- KSNB (Kỹ Sư Nội Bộ)
- MTTQ (Mua Sắm Tập Đoàn)

**Checklist:**
- [ ] Tạo seeder
- [ ] Insert departments mẫu
- [ ] Gán head_user_id sau khi có users
- [ ] Thêm vào DatabaseSeeder

---

#### **Task 1.3.2: Update User Seeder**

**Cập nhật UserSeeder để:**
- Gán department cho users
- Tạo users cho mỗi department
- Tạo trưởng phòng cho mỗi phòng ban

**Checklist:**
- [ ] Cập nhật seeder
- [ ] Tạo users với departments
- [ ] Assign roles phù hợp
- [ ] Test seed

---

#### **Task 1.3.3: Sample Data Seeder**
```bash
php artisan make:seeder VendorReportSeeder
```

**Tạo dữ liệu mẫu:**
- 2-3 phiếu DRAFT
- 1-2 phiếu IN_APPROVAL
- 1 phiếu APPROVED
- 1 phiếu REJECTED

**Checklist:**
- [ ] Tạo phiếu mẫu
- [ ] Tạo approval steps
- [ ] Test với workflow khác nhau

---

## **PHASE 2: BACKEND SERVICES** (3-4 ngày)

### 2.1. Config Files (Ưu tiên: CAO 🔴)

#### **Task 2.1.1: Workflow Configuration**
```bash
touch config/vendor_report_workflows.php
```

**Nội dung:**
```php
return [
    'NORMAL' => [
        [
            'key' => 'DEPT_HEAD',
            'label' => 'Trưởng phòng duyệt',
            'assignee_source' => 'department_head',
            'requires_selection' => false,
        ],
        [
            'key' => 'PURCHASING_ADMIN',
            'label' => 'Phòng mua sắm chọn người duyệt',
            'assignee_role' => 'purchasing_admin',
            'requires_selection' => true,
            'selection_role' => 'internal_control',
        ],
        [
            'key' => 'INTERNAL_CONTROL',
            'label' => 'Kiểm soát nội bộ duyệt',
            'assignee_source' => 'selected',
        ],
        [
            'key' => 'BOD',
            'label' => 'Ban giám đốc duyệt',
            'assignee_role' => 'bod',
        ],
    ],
    
    'SPECIAL_1' => [...],
    'SPECIAL_2' => [...],
    'SPECIAL_3' => [...],
    'URGENT' => [...],
];
```

**Checklist:**
- [ ] Định nghĩa tất cả 5 workflows
- [ ] Validate structure
- [ ] Document mỗi workflow

---

### 2.2. Service Classes (Ưu tiên: CAO 🔴)

#### **Task 2.2.1: VendorReportCodeGenerator Service**
```bash
php artisan make:service VendorReportCodeGenerator
```

**Chức năng:**
```php
class VendorReportCodeGenerator
{
    public function generate(Department $department): string
    {
        return DB::transaction(function() use ($department) {
            $year = date('Y');
            
            // Lock và lấy sequence của năm hiện tại
            $sequence = YearlySequence::where('year', $year)
                ->lockForUpdate() // SELECT FOR UPDATE - prevent race condition
                ->first();
            
            // Nếu chưa có record cho năm này, tạo mới
            if (!$sequence) {
                $sequence = YearlySequence::create([
                    'year' => $year,
                    'current_seq' => 0
                ]);
            }
            
            // Tăng sequence
            $sequence->increment('current_seq');
            
            // Format: YYYY/DEPT_CODE/SEQ (3 chữ số)
            // Ví dụ: 2026/TM/024
            return sprintf('%d/%s/%03d', 
                $year,
                strtoupper($department->code),
                $sequence->current_seq
            );
        });
    }
}
```

**Logic:**
- Sequence **GLOBAL** cho tất cả phòng ban
- 2026/TM/024 → 2026/BT/025 → 2026/KSNB/026
- Transaction + SELECT FOR UPDATE để tránh duplicate
- Năm mới tự động tạo record mới với seq = 0

**Checklist:**
- [ ] Tạo service
- [ ] Implement transaction logic
- [ ] Test concurrent requests (nhiều user cùng tạo phiếu)
- [ ] Handle errors
- [ ] Test year transition (chuyển năm)

---

#### **Task 2.2.2: VendorReportWorkflowBuilder Service**
```bash
php artisan make:service VendorReportWorkflowBuilder
```

**Chức năng:**
```php
class VendorReportWorkflowBuilder
{
    public function buildSteps(VendorReport $report): Collection
    {
        // Load workflow config
        // Create VendorReportApprovalStep records
        // Snapshot assignee_user_id where possible
        // Return collection of steps
    }
}
```

**Checklist:**
- [ ] Load config theo workflow_type
- [ ] Create approval steps
- [ ] Snapshot assignees
- [ ] Handle department head
- [ ] Return steps ordered

---

#### **Task 2.2.3: VendorReportSubmissionService**
```bash
php artisan make:service VendorReportSubmissionService
```

**Chức năng:**
```php
class VendorReportSubmissionService
{
    public function submit(VendorReport $report): void
    {
        // Validate: department has head
        // Validate: required fields
        // Generate code
        // Build approval steps
        // Set status = IN_APPROVAL
        // Set current_step_id
        // Log activity
        // Queue notification
    }
}
```

**Checklist:**
- [ ] Validation rules
- [ ] Generate code
- [ ] Build steps
- [ ] Update status
- [ ] Activity log
- [ ] Notification

---

#### **Task 2.2.4: VendorReportApprovalService**
```bash
php artisan make:service VendorReportApprovalService
```

**Chức năng:**
```php
class VendorReportApprovalService
{
    public function approve(VendorReportApprovalStep $step, User $user, ?string $note = null): void
    {
        // Validate: user is assignee
        // Update step: APPROVED, acted_by, acted_at, note
        // Move to next step or APPROVED
        // Log activity
        // Queue notification
    }
    
    public function reject(VendorReportApprovalStep $step, User $user, string $note): void
    {
        // Update step: REJECTED
        // Update report: REJECTED (terminal)
        // Log activity
        // Notify creator + purchasing_admin
    }
    
    public function selectNextApprover(VendorReportApprovalStep $step, User $selectedUser): void
    {
        // Validate: step requires_selection
        // Update step: selected_next_approver_id
        // Update next step: assignee_user_id
        // Log activity
    }
}
```

**Checklist:**
- [ ] Approve method
- [ ] Reject method
- [ ] Select approver method
- [ ] Validations
- [ ] Activity logs
- [ ] Notifications

---

#### **Task 2.2.5: VendorReportChainService**
```bash
php artisan make:service VendorReportChainService
```

**Chức năng:**
```php
class VendorReportChainService
{
    public function cloneFromRejected(VendorReport $rejectedReport): VendorReport
    {
        // Validate: status = REJECTED
        // Create new report
        // parent_id = old.id
        // root_id = old.root_id ?? old.id
        // status = DRAFT
        // Copy data (title, workflow_type, etc.)
        // Log: report_cloned_from_rejected
        // Return new report
    }
    
    public function getChain(VendorReport $report): Collection
    {
        // Get all reports in chain (root → children)
        // Return ordered collection
    }
    
    public function getChainLogs(VendorReport $report): Collection
    {
        // Get activity logs for entire chain
        // Return ordered by created_at
    }
}
```

**Checklist:**
- [ ] Clone method
- [ ] Get chain method
- [ ] Get chain logs method
- [ ] Tests

---

#### **Task 2.2.6: VendorReportFileService**
```bash
php artisan make:service VendorReportFileService
```

**Chức năng:**
```php
class VendorReportFileService
{
    public function upload(VendorReport $report, UploadedFile $file, string $type, User $user): VendorReportFile
    {
        // Store in private disk
        // Create VendorReportFile record
        // Log activity
        // Return file record
    }
    
    public function delete(VendorReportFile $file): void
    {
        // Delete from storage
        // Delete record
        // Log activity
    }
    
    public function download(VendorReportFile $file): StreamedResponse
    {
        // Authorize
        // Return file download response
    }
}
```

**Checklist:**
- [ ] Upload method
- [ ] Delete method
- [ ] Download method
- [ ] Private storage config
- [ ] Authorization

---

### 2.3. Notifications (Ưu tiên: TRUNG 🟡)

#### **Task 2.3.1: Report Submitted Notification**
```bash
php artisan make:notification VendorReportSubmitted
```

**Gửi đến:** Người duyệt bước đầu tiên

**Checklist:**
- [ ] Tạo notification
- [ ] Mail template
- [ ] Queue support
- [ ] Test

---

#### **Task 2.3.2: Step Approved Notification**
```bash
php artisan make:notification VendorReportStepApproved
```

**Gửi đến:** Người duyệt bước tiếp theo

**Checklist:**
- [ ] Tạo notification
- [ ] Mail template
- [ ] Include report details
- [ ] Test

---

#### **Task 2.3.3: Report Rejected Notification**
```bash
php artisan make:notification VendorReportRejected
```

**Gửi đến:** Creator + Purchasing Admin

**Checklist:**
- [ ] Tạo notification
- [ ] Mail template
- [ ] Include rejection reason
- [ ] Test

---

#### **Task 2.3.4: Final Approval Notification**
```bash
php artisan make:notification VendorReportFinallyApproved
```

**Gửi đến:** Creator + Purchasing Admin

**Checklist:**
- [ ] Tạo notification
- [ ] Mail template
- [ ] Test

---

#### **Task 2.3.5: Selection Required Notification**
```bash
php artisan make:notification NextApproverSelectionRequired
```

**Gửi đến:** Purchasing Admin

**Checklist:**
- [ ] Tạo notification
- [ ] Mail template
- [ ] Test

---

### 2.4. Policies (Ưu tiên: CAO 🔴)

#### **Task 2.4.1: VendorReportPolicy**
```bash
php artisan make:policy VendorReportPolicy --model=VendorReport
```

**Methods:**
```php
class VendorReportPolicy
{
    public function viewAny(User $user): bool
    public function view(User $user, VendorReport $report): bool
    public function create(User $user): bool
    public function update(User $user, VendorReport $report): bool
    public function delete(User $user, VendorReport $report): bool
    public function submit(User $user, VendorReport $report): bool
    public function approve(User $user, VendorReport $report): bool
    public function reject(User $user, VendorReport $report): bool
    public function clone(User $user, VendorReport $report): bool
    public function uploadFile(User $user, VendorReport $report): bool
    public function deleteFile(User $user, VendorReportFile $file): bool
}
```

**Rules:**
- view: creator | purchasing_admin | assignee | admin_system
- update: creator & DRAFT
- submit: creator & DRAFT & has department
- approve/reject: current step assignee
- clone: (creator | purchasing_admin) & REJECTED

**Checklist:**
- [ ] Tạo policy
- [ ] Implement tất cả methods
- [ ] Test từng permission
- [ ] Register policy

---

#### **Task 2.4.2: DepartmentPolicy**
```bash
php artisan make:policy DepartmentPolicy --model=Department
```

**Checklist:**
- [ ] CRUD permissions
- [ ] Admin only
- [ ] Test

---

### 2.5. Requests (Validation) (Ưu tiên: TRUNG 🟡)

#### **Task 2.5.1: Store/Update Department Request**
```bash
php artisan make:request StoreDepartmentRequest
php artisan make:request UpdateDepartmentRequest
```

**Checklist:**
- [ ] Validation rules
- [ ] Unique code
- [ ] Test

---

#### **Task 2.5.2: Store/Update VendorReport Request**
```bash
php artisan make:request StoreVendorReportRequest
php artisan make:request UpdateVendorReportRequest
```

**Checklist:**
- [ ] Required fields
- [ ] Enum validation
- [ ] Test

---

#### **Task 2.5.3: Submit VendorReport Request**
```bash
php artisan make:request SubmitVendorReportRequest
```

**Validation:**
- Title required
- Workflow type required
- Department has head_user_id

**Checklist:**
- [ ] Validation rules
- [ ] Custom messages
- [ ] Test

---

#### **Task 2.5.4: Approve/Reject Step Request**
```bash
php artisan make:request ApproveStepRequest
php artisan make:request RejectStepRequest
```

**Checklist:**
- [ ] Validation rules
- [ ] Required note for rejection
- [ ] Test

---

#### **Task 2.5.5: Upload File Request**
```bash
php artisan make:request UploadVendorReportFileRequest
```

**Validation:**
- File required
- Max size
- Allowed mimes
- Type enum

**Checklist:**
- [ ] File validation
- [ ] Size limits
- [ ] MIME types
- [ ] Test

---

## **PHASE 3: BACKEND CONTROLLERS & ROUTES** (2-3 ngày)

### 3.1. Controllers (Ưu tiên: CAO 🔴)

#### **Task 3.1.1: DepartmentController**
```bash
php artisan make:controller DepartmentController --resource
```

**Methods:**
- index() - List + search + pagination
- create() - Form data
- store() - Create
- show() - View detail
- edit() - Edit form
- update() - Update
- destroy() - Soft delete

**Checklist:**
- [ ] CRUD methods
- [ ] Authorization
- [ ] Inertia responses
- [ ] Test

---

#### **Task 3.1.2: VendorReportController**
```bash
php artisan make:controller VendorReportController --resource
```

**Methods:**
- index() - List với filters (status, workflow_type, created_by)
- create() - Form data (departments, purchasing_admins)
- store() - Create DRAFT
- show() - View detail + steps + files + chain logs
- edit() - Edit DRAFT
- update() - Update DRAFT
- destroy() - Soft delete DRAFT

**Checklist:**
- [ ] CRUD methods
- [ ] Authorization
- [ ] Inertia responses
- [ ] Include relationships
- [ ] Test

---

#### **Task 3.1.3: VendorReportSubmissionController**
```bash
php artisan make:controller VendorReportSubmissionController
```

**Method:**
- submit(VendorReport $report)

**Checklist:**
- [ ] Use SubmissionService
- [ ] Authorization
- [ ] Validation
- [ ] Return response
- [ ] Test

---

#### **Task 3.1.4: VendorReportApprovalController**
```bash
php artisan make:controller VendorReportApprovalController
```

**Methods:**
- approve(VendorReportApprovalStep $step)
- reject(VendorReportApprovalStep $step)
- selectNextApprover(VendorReportApprovalStep $step)

**Checklist:**
- [ ] Use ApprovalService
- [ ] Authorization
- [ ] Validation
- [ ] Return responses
- [ ] Test

---

#### **Task 3.1.5: VendorReportCloneController**
```bash
php artisan make:controller VendorReportCloneController
```

**Method:**
- clone(VendorReport $report)

**Checklist:**
- [ ] Use ChainService
- [ ] Authorization
- [ ] Return new report
- [ ] Test

---

#### **Task 3.1.6: VendorReportFileController**
```bash
php artisan make:controller VendorReportFileController --resource
```

**Methods:**
- store(VendorReport $report) - Upload
- destroy(VendorReportFile $file) - Delete
- download(VendorReportFile $file) - Download

**Checklist:**
- [ ] Use FileService
- [ ] Authorization
- [ ] Handle uploads
- [ ] Return responses
- [ ] Test

---

### 3.2. Routes (Ưu tiên: CAO 🔴)

#### **Task 3.2.1: Update web.php**

**Thêm routes:**
```php
Route::middleware('auth')->group(function () {
    
    // Admin Routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('departments', DepartmentController::class);
    });
    
    // Vendor Reports
    Route::resource('vendor-reports', VendorReportController::class);
    
    // Submit
    Route::post('vendor-reports/{vendorReport}/submit', 
        [VendorReportSubmissionController::class, 'submit'])
        ->name('vendor-reports.submit');
    
    // Approve/Reject
    Route::post('vendor-report-steps/{step}/approve', 
        [VendorReportApprovalController::class, 'approve'])
        ->name('vendor-report-steps.approve');
        
    Route::post('vendor-report-steps/{step}/reject', 
        [VendorReportApprovalController::class, 'reject'])
        ->name('vendor-report-steps.reject');
    
    Route::post('vendor-report-steps/{step}/select-approver', 
        [VendorReportApprovalController::class, 'selectNextApprover'])
        ->name('vendor-report-steps.select-approver');
    
    // Clone
    Route::post('vendor-reports/{vendorReport}/clone', 
        [VendorReportCloneController::class, 'clone'])
        ->name('vendor-reports.clone');
    
    // Files
    Route::post('vendor-reports/{vendorReport}/files', 
        [VendorReportFileController::class, 'store'])
        ->name('vendor-reports.files.store');
        
    Route::delete('vendor-report-files/{file}', 
        [VendorReportFileController::class, 'destroy'])
        ->name('vendor-report-files.destroy');
        
    Route::get('vendor-report-files/{file}/download', 
        [VendorReportFileController::class, 'download'])
        ->name('vendor-report-files.download');
});
```

**Checklist:**
- [ ] Thêm tất cả routes
- [ ] Route naming consistent
- [ ] Test route list
- [ ] Update API documentation

---

## **PHASE 4: FRONTEND COMPONENTS & PAGES** (4-5 ngày)

### 4.1. Setup & Utilities (Ưu tiên: CAO 🔴)

#### **Task 4.1.1: Enums & Constants**

**Tạo file:** `resources/js/constants/vendorReports.js`

```javascript
export const VENDOR_REPORT_STATUSES = {
    DRAFT: { value: 'DRAFT', label: 'Nháp', color: 'info' },
    SUBMITTED: { value: 'SUBMITTED', label: 'Đã gửi', color: 'primary' },
    IN_APPROVAL: { value: 'IN_APPROVAL', label: 'Đang duyệt', color: 'warning' },
    APPROVED: { value: 'APPROVED', label: 'Đã duyệt', color: 'success' },
    REJECTED: { value: 'REJECTED', label: 'Từ chối', color: 'danger' }
};

export const WORKFLOW_TYPES = {
    NORMAL: { value: 'NORMAL', label: 'Quy trình thông thường' },
    SPECIAL_1: { value: 'SPECIAL_1', label: 'Quy trình đặc biệt 1' },
    SPECIAL_2: { value: 'SPECIAL_2', label: 'Quy trình đặc biệt 2' },
    SPECIAL_3: { value: 'SPECIAL_3', label: 'Quy trình đặc biệt 3' },
    URGENT: { value: 'URGENT', label: 'Quy trình khẩn cấp' }
};

export const FILE_TYPES = {
    REPORT_IMAGE: { value: 'REPORT_IMAGE', label: 'Hình ảnh báo cáo' },
    QUOTATION: { value: 'QUOTATION', label: 'Báo giá' },
    BOQ: { value: 'BOQ', label: 'Bảng kê' }
};

export const STEP_STATUSES = {
    PENDING: { value: 'PENDING', label: 'Chờ duyệt', color: 'info' },
    APPROVED: { value: 'APPROVED', label: 'Đã duyệt', color: 'success' },
    REJECTED: { value: 'REJECTED', label: 'Từ chối', color: 'danger' },
    SKIPPED: { value: 'SKIPPED', label: 'Bỏ qua', color: 'secondary' }
};
```

**Checklist:**
- [ ] Tạo constants file
- [ ] Export all enums
- [ ] Match với backend enums

---

#### **Task 4.1.2: Composables**

**Tạo:** `resources/js/composables/useVendorReports.js`

```javascript
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

export function useVendorReports() {
    const loading = ref(false);
    
    const submitReport = async (reportId) => { ... };
    const approveStep = async (stepId, note) => { ... };
    const rejectStep = async (stepId, note) => { ... };
    const cloneReport = async (reportId) => { ... };
    const uploadFile = async (reportId, file, type) => { ... };
    const deleteFile = async (fileId) => { ... };
    
    return {
        loading,
        submitReport,
        approveStep,
        rejectStep,
        cloneReport,
        uploadFile,
        deleteFile
    };
}
```

**Checklist:**
- [ ] Tạo composable
- [ ] All CRUD operations
- [ ] Error handling
- [ ] Loading states
- [ ] Test

---

### 4.2. Department Pages (Ưu tiên: TRUNG 🟡)

#### **Task 4.2.1: Department Index Page**

**File:** `resources/js/Pages/Admin/DepartmentIndex.vue`

**Features:**
- DataTable với PrimeVue
- Search/Filter
- CRUD buttons
- Pagination
- Trưởng phòng display

**Checklist:**
- [ ] Tạo page
- [ ] DataTable setup
- [ ] CRUD buttons
- [ ] Search/Filter
- [ ] Test

---

#### **Task 4.2.2: Department Form Component**

**File:** `resources/js/Components/DepartmentForm.vue`

**Fields:**
- Code (required, uppercase)
- Name (required)
- Head User (dropdown)
- Parent Department (dropdown)
- Is Active (toggle)

**Checklist:**
- [ ] Form component
- [ ] Validation
- [ ] Submit handler
- [ ] Test

---

### 4.3. Vendor Report Pages (Ưu tiên: CAO 🔴)

#### **Task 4.3.1: VendorReport Index Page**

**File:** `resources/js/Pages/VendorReports/Index.vue`

**Features:**
- DataTable
- Filters: status, workflow_type, date range
- Status badges (colored)
- Actions: View, Edit (if DRAFT), Delete (if DRAFT)
- Create button
- Pagination

**Component structure:**
```vue
<template>
    <AppLayout>
        <div class="card">
            <DataTable
                :value="reports.data"
                :lazy="true"
                :paginator="true"
                :rows="reports.per_page"
                :totalRecords="reports.total"
                @page="onPage"
            >
                <Column field="code" header="Mã phiếu" />
                <Column field="title" header="Tiêu đề" />
                <Column field="status" header="Trạng thái">
                    <template #body="{ data }">
                        <Tag :severity="getStatusColor(data.status)">
                            {{ getStatusLabel(data.status) }}
                        </Tag>
                    </template>
                </Column>
                <Column field="workflow_type" header="Quy trình" />
                <Column field="created_by.name" header="Người tạo" />
                <Column field="created_at" header="Ngày tạo" />
                <Column header="Thao tác">
                    <template #body="{ data }">
                        <Button icon="pi pi-eye" @click="view(data.id)" />
                        <Button v-if="data.status === 'DRAFT'" icon="pi pi-pencil" @click="edit(data.id)" />
                        <Button v-if="data.status === 'DRAFT'" icon="pi pi-trash" @click="destroy(data.id)" />
                    </template>
                </Column>
            </DataTable>
        </div>
    </AppLayout>
</template>
```

**Checklist:**
- [ ] Tạo page
- [ ] DataTable setup
- [ ] Filters
- [ ] Status badges
- [ ] Actions
- [ ] Pagination
- [ ] Test

---

#### **Task 4.3.2: VendorReport Create/Edit Page**

**File:** `resources/js/Pages/VendorReports/Form.vue`

**Fields:**
- Title (required)
- Workflow Type (dropdown, required)
- Purchasing Admin (user dropdown)
- Description (textarea)

**Sections:**
1. Basic Info
2. Files Upload (if created)

**Checklist:**
- [ ] Form layout
- [ ] Validation
- [ ] File upload section
- [ ] Submit handler
- [ ] Test

---

#### **Task 4.3.3: VendorReport Show/Detail Page**

**File:** `resources/js/Pages/VendorReports/Show.vue`

**Sections:**

1. **Header Card:**
   - Code, Title, Status
   - Created by, Date
   - Action buttons: Submit, Clone (if rejected)

2. **Info Card:**
   - Workflow type
   - Purchasing admin
   - Current step
   - Dates (submitted, approved, rejected)
   - Rejection note (if rejected)

3. **Approval Steps Timeline:**
   - Timeline component (PrimeVue)
   - Each step shows:
     - Step name
     - Assignee
     - Status
     - Action buttons (if user can act)
     - Acted by, date, note

4. **Files Section:**
   - List of uploaded files
   - Upload button (if can upload)
   - Download/Delete buttons

5. **Activity Log Tab:**
   - Show chain logs
   - Timeline format

6. **Chain Info (if parent/children):**
   - Show parent report
   - Show children reports

**Component structure:**
```vue
<template>
    <AppLayout>
        <!-- Header -->
        <div class="card mb-3">
            <div class="flex justify-content-between align-items-center">
                <div>
                    <h2>{{ report.code }}</h2>
                    <Tag :severity="getStatusColor(report.status)">
                        {{ getStatusLabel(report.status) }}
                    </Tag>
                </div>
                <div>
                    <Button v-if="canSubmit" label="Gửi duyệt" @click="submit" />
                    <Button v-if="canClone" label="Tạo phiếu mới" @click="clone" />
                </div>
            </div>
        </div>
        
        <!-- Tabs -->
        <TabView>
            <TabPanel header="Thông tin">
                <!-- Report info -->
            </TabPanel>
            
            <TabPanel header="Quy trình duyệt">
                <Timeline :value="approvalSteps">
                    <template #content="{ item }">
                        <ApprovalStepCard :step="item" @approve="handleApprove" @reject="handleReject" />
                    </template>
                </Timeline>
            </TabPanel>
            
            <TabPanel header="Tài liệu">
                <FileList :files="report.files" @upload="handleUpload" @delete="handleDelete" />
            </TabPanel>
            
            <TabPanel header="Nhật ký">
                <ActivityLog :logs="chainLogs" />
            </TabPanel>
        </TabView>
    </AppLayout>
</template>
```

**Checklist:**
- [ ] Layout structure
- [ ] All sections
- [ ] Action buttons
- [ ] Timeline component
- [ ] Files section
- [ ] Activity log
- [ ] Chain display
- [ ] Test

---

### 4.4. Reusable Components (Ưu tiên: CAO 🔴)

#### **Task 4.4.1: StatusBadge Component**

**File:** `resources/js/Components/VendorReports/StatusBadge.vue`

```vue
<template>
    <Tag :severity="getSeverity(status)">
        {{ getLabel(status) }}
    </Tag>
</template>
```

**Checklist:**
- [ ] Tạo component
- [ ] Map status → color
- [ ] Map status → label
- [ ] Test

---

#### **Task 4.4.2: WorkflowTypeSelect Component**

**File:** `resources/js/Components/VendorReports/WorkflowTypeSelect.vue`

**Props:**
- modelValue
- disabled

**Features:**
- Dropdown với WORKFLOW_TYPES
- v-model support

**Checklist:**
- [ ] Dropdown component
- [ ] Options from constants
- [ ] v-model
- [ ] Test

---

#### **Task 4.4.3: ApprovalStepCard Component**

**File:** `resources/js/Components/VendorReports/ApprovalStepCard.vue`

**Props:**
- step
- canAct (boolean)

**Features:**
- Display step info
- Show assignee
- Show status badge
- Action buttons (Approve/Reject) if canAct
- Show note if acted

**Checklist:**
- [ ] Card layout
- [ ] Step info display
- [ ] Action buttons
- [ ] Approve/Reject dialogs
- [ ] Test

---

#### **Task 4.4.4: FileUploadCard Component**

**File:** `resources/js/Components/VendorReports/FileUploadCard.vue`

**Props:**
- reportId
- canUpload

**Features:**
- File upload (drag & drop)
- File type selection
- Progress bar
- File list with download/delete

**Checklist:**
- [ ] Upload UI
- [ ] File type selector
- [ ] Progress indicator
- [ ] File list
- [ ] Download/Delete
- [ ] Test

---

#### **Task 4.4.5: ApprovalTimeline Component**

**File:** `resources/js/Components/VendorReports/ApprovalTimeline.vue`

**Props:**
- steps (array)

**Features:**
- PrimeVue Timeline
- Step cards
- Status indicators
- Responsive

**Checklist:**
- [ ] Timeline setup
- [ ] Step cards
- [ ] Status colors
- [ ] Responsive
- [ ] Test

---

#### **Task 4.4.6: ReportChainInfo Component**

**File:** `resources/js/Components/VendorReports/ReportChainInfo.vue`

**Props:**
- report

**Features:**
- Show parent (if exists)
- Show children (if exists)
- Links to other reports
- Visual chain representation

**Checklist:**
- [ ] Parent display
- [ ] Children list
- [ ] Links
- [ ] Visual design
- [ ] Test

---

#### **Task 4.4.7: ActivityLogTimeline Component**

**File:** `resources/js/Components/VendorReports/ActivityLogTimeline.vue`

**Props:**
- logs (array)

**Features:**
- Timeline display
- Log details
- User info
- Timestamps

**Checklist:**
- [ ] Timeline setup
- [ ] Log display
- [ ] Format datetime
- [ ] Test

---

### 4.5. Dialogs & Modals (Ưu tiên: TRUNG 🟡)

#### **Task 4.5.1: ApproveDialog Component**

**File:** `resources/js/Components/VendorReports/ApproveDialog.vue`

**Features:**
- Confirmation dialog
- Optional note input
- Approve action

**Checklist:**
- [ ] Dialog component
- [ ] Form with note
- [ ] Submit handler
- [ ] Test

---

#### **Task 4.5.2: RejectDialog Component**

**File:** `resources/js/Components/VendorReports/RejectDialog.vue`

**Features:**
- Confirmation dialog
- Required note input
- Reject action

**Checklist:**
- [ ] Dialog component
- [ ] Form with required note
- [ ] Submit handler
- [ ] Validation
- [ ] Test

---

#### **Task 4.5.3: SelectNextApproverDialog Component**

**File:** `resources/js/Components/VendorReports/SelectNextApproverDialog.vue`

**Features:**
- User dropdown (filtered by role)
- Confirm selection

**Checklist:**
- [ ] Dialog component
- [ ] User dropdown
- [ ] Role filtering
- [ ] Submit handler
- [ ] Test

---

#### **Task 4.5.4: CloneReportDialog Component**

**File:** `resources/js/Components/VendorReports/CloneReportDialog.vue`

**Features:**
- Confirmation
- Explain cloning behavior
- Clone action

**Checklist:**
- [ ] Dialog component
- [ ] Explanation text
- [ ] Confirm handler
- [ ] Test

---

### 4.6. Navigation & Menu (Ưu tiên: TRUNG 🟡)

#### **Task 4.6.1: Update Main Menu**

**File:** `resources/js/SakaiVue/layout/AppMenu.vue`

**Thêm menu items:**
```javascript
{
    label: 'Quản lý phiếu',
    icon: 'pi pi-file-edit',
    items: [
        {
            label: 'Phiếu của tôi',
            icon: 'pi pi-file',
            to: '/vendor-reports?filter=my'
        },
        {
            label: 'Chờ duyệt',
            icon: 'pi pi-clock',
            to: '/vendor-reports?filter=pending'
        },
        {
            label: 'Tất cả phiếu',
            icon: 'pi pi-list',
            to: '/vendor-reports'
        }
    ]
},
{
    label: 'Quản trị',
    icon: 'pi pi-cog',
    items: [
        {
            label: 'Phòng ban',
            icon: 'pi pi-building',
            to: '/admin/departments'
        },
        // ... existing admin items
    ]
}
```

**Checklist:**
- [ ] Cập nhật menu
- [ ] Icons
- [ ] Routes
- [ ] Permissions check
- [ ] Test

---

## **PHASE 5: TESTING & QA** (2-3 ngày)

### 5.1. Unit Tests (Ưu tiên: TRUNG 🟡)

#### **Task 5.1.1: Service Tests**

**Tests:**
- VendorReportCodeGenerator
- VendorReportWorkflowBuilder
- VendorReportSubmissionService
- VendorReportApprovalService
- VendorReportChainService

**Checklist:**
- [ ] Test code generation
- [ ] Test workflow building
- [ ] Test submission logic
- [ ] Test approval logic
- [ ] Test cloning
- [ ] Test edge cases

---

#### **Task 5.1.2: Model Tests**

**Tests:**
- Department relationships
- VendorReport relationships
- VendorReport scopes
- VendorReport helper methods

**Checklist:**
- [ ] Relationship tests
- [ ] Scope tests
- [ ] Helper method tests
- [ ] Validation tests

---

### 5.2. Feature Tests (Ưu tiên: CAO 🔴)

#### **Task 5.2.1: Department CRUD Tests**

**File:** `tests/Feature/DepartmentTest.php`

**Tests:**
- Create department
- Update department
- Soft delete department
- List departments
- Authorization

**Checklist:**
- [ ] All CRUD operations
- [ ] Authorization tests
- [ ] Validation tests

---

#### **Task 5.2.2: VendorReport CRUD Tests**

**File:** `tests/Feature/VendorReportTest.php`

**Tests:**
- Create report (DRAFT)
- Update report (DRAFT only)
- Delete report (DRAFT only)
- Cannot edit submitted report
- List reports
- Authorization

**Checklist:**
- [ ] CRUD tests
- [ ] Authorization
- [ ] Status restrictions

---

#### **Task 5.2.3: VendorReport Submission Tests**

**File:** `tests/Feature/VendorReportSubmissionTest.php`

**Tests:**
- Submit valid report
- Cannot submit without department head
- Cannot submit twice
- Generates code correctly
- Creates approval steps
- Sends notification

**Checklist:**
- [ ] Submit success
- [ ] Submit failures
- [ ] Code generation
- [ ] Steps creation
- [ ] Notifications

---

#### **Task 5.2.4: Approval Flow Tests**

**File:** `tests/Feature/VendorReportApprovalTest.php`

**Tests:**
- Approve step
- Reject step
- Cannot approve if not assignee
- Move to next step
- Final approval
- Notification sent

**Checklist:**
- [ ] Approve success
- [ ] Reject success
- [ ] Authorization
- [ ] Step progression
- [ ] Notifications

---

#### **Task 5.2.5: Workflow Tests**

**File:** `tests/Feature/VendorReportWorkflowTest.php`

**Tests:**
- NORMAL workflow
- SPECIAL_1 workflow
- SPECIAL_2 workflow
- SPECIAL_3 workflow
- URGENT workflow
- Selection mechanism

**Checklist:**
- [ ] Each workflow type
- [ ] Correct steps
- [ ] Correct assignees
- [ ] Selection works

---

#### **Task 5.2.6: Clone Tests**

**File:** `tests/Feature/VendorReportCloneTest.php`

**Tests:**
- Clone rejected report
- Cannot clone non-rejected
- Parent/root set correctly
- Chain logs work

**Checklist:**
- [ ] Clone success
- [ ] Clone restrictions
- [ ] Parent/root logic
- [ ] Chain logs

---

#### **Task 5.2.7: File Upload Tests**

**File:** `tests/Feature/VendorReportFileTest.php`

**Tests:**
- Upload file
- Delete file
- Download file
- Authorization
- Storage validation

**Checklist:**
- [ ] Upload success
- [ ] Delete success
- [ ] Download works
- [ ] Authorization
- [ ] File validation

---

### 5.3. Integration Tests (Ưu tiên: TRUNG 🟡)

#### **Task 5.3.1: End-to-End Workflow Test**

**Scenario:**
1. User creates DRAFT report
2. User submits report
3. Dept head approves
4. Purchasing admin selects next approver
5. Internal control approves
6. BOD approves
7. Report APPROVED

**Checklist:**
- [ ] Complete flow test
- [ ] All notifications
- [ ] Activity logs
- [ ] Verify final state

---

#### **Task 5.3.2: Rejection & Clone Test**

**Scenario:**
1. Create and submit report
2. Reject at some step
3. Clone report
4. Submit cloned report
5. Verify chain

**Checklist:**
- [ ] Rejection works
- [ ] Clone creates new report
- [ ] Chain linked correctly
- [ ] Logs preserved

---

### 5.4. Manual Testing Checklist (Ưu tiên: CAO 🔴)

#### **Task 5.4.1: User Flows**

**Test cases:**
- [ ] Login as requester
- [ ] Create new report
- [ ] Upload files
- [ ] Submit report
- [ ] Login as approver
- [ ] Approve step
- [ ] Login as purchasing admin
- [ ] Select next approver
- [ ] Complete approval flow
- [ ] Reject report
- [ ] Clone rejected report
- [ ] View activity logs
- [ ] View report chain

---

#### **Task 5.4.2: Authorization Testing**

**Test cases:**
- [ ] User cannot edit others' drafts
- [ ] User cannot approve if not assignee
- [ ] User cannot submit without department
- [ ] Admin can view all reports
- [ ] User can only see assigned reports

---

#### **Task 5.4.3: Edge Cases**

**Test cases:**
- [ ] Concurrent submissions
- [ ] Code generation uniqueness
- [ ] Missing department head
- [ ] Deleted users in workflow
- [ ] Large file uploads
- [ ] Multiple chains
- [ ] Long activity logs

---

## **PHASE 6: DEPLOYMENT & DOCUMENTATION** (1-2 ngày)

### 6.1. Configuration (Ưu tiên: CAO 🔴)

#### **Task 6.1.1: Environment Variables**

**Cập nhật .env.example:**
```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"

# Queue Configuration
QUEUE_CONNECTION=database

# Storage Configuration
FILESYSTEM_DISK=local
```

**Checklist:**
- [ ] Cập nhật .env.example
- [ ] Document variables
- [ ] Test mail config
- [ ] Test queue config

---

#### **Task 6.1.2: Storage Configuration**

**File:** `config/filesystems.php`

**Thêm disk:**
```php
'private' => [
    'driver' => 'local',
    'root' => storage_path('app/private'),
    'visibility' => 'private',
],
```

**Checklist:**
- [ ] Configure private disk
- [ ] Test file storage
- [ ] Test file retrieval
- [ ] Create directories

---

#### **Task 6.1.3: Queue Configuration**

**Setup:**
- Configure queue driver
- Create supervisor config (if production)
- Test queue workers

**Checklist:**
- [ ] Queue driver setup
- [ ] Test job dispatching
- [ ] Test job processing
- [ ] Monitor jobs

---

### 6.2. Migrations & Seeds (Ưu tiên: CAO 🔴)

#### **Task 6.2.1: Fresh Migration Test**

```bash
php artisan migrate:fresh --seed
```

**Checklist:**
- [ ] All migrations run
- [ ] No errors
- [ ] Foreign keys work
- [ ] Indexes created
- [ ] Seeders run successfully

---

#### **Task 6.2.2: Rollback Test**

```bash
php artisan migrate:rollback --step=10
php artisan migrate
```

**Checklist:**
- [ ] Rollbacks work
- [ ] Re-migrations work
- [ ] No data loss (if any data)

---

### 6.3. Documentation (Ưu tiên: TRUNG 🟡)

#### **Task 6.3.1: API Documentation**

**Cập nhật API_DOCUMENTATION.md:**
- All routes
- Request/Response examples
- Authorization rules
- Error responses

**Checklist:**
- [ ] Document all endpoints
- [ ] Request examples
- [ ] Response examples
- [ ] Error codes
- [ ] Authorization

---

#### **Task 6.3.2: User Guide**

**Tạo USER_GUIDE.md:**
- Hướng dẫn tạo phiếu
- Hướng dẫn duyệt phiếu
- Hướng dẫn từ chối/clone
- Hướng dẫn upload files
- FAQ

**Checklist:**
- [ ] Create user guide
- [ ] Screenshots
- [ ] Step-by-step instructions
- [ ] FAQ section

---

#### **Task 6.3.3: Technical Documentation**

**Cập nhật README.md:**
- System overview
- Installation steps
- Configuration
- Testing
- Deployment

**Checklist:**
- [ ] System overview
- [ ] Setup instructions
- [ ] Configuration guide
- [ ] Testing guide
- [ ] Deployment steps

---

#### **Task 6.3.4: Workflow Documentation**

**Tạo WORKFLOWS.md:**
- Chi tiết 5 workflows
- Flowcharts
- Business rules
- Examples

**Checklist:**
- [ ] Document all workflows
- [ ] Create flowcharts
- [ ] Explain business rules
- [ ] Provide examples

---

### 6.4. Deployment (Ưu tiên: CAO 🔴)

#### **Task 6.4.1: Production Checklist**

**Pre-deployment:**
- [ ] Run all tests
- [ ] Build frontend assets
- [ ] Optimize composer autoload
- [ ] Clear all caches
- [ ] Check .env configuration
- [ ] Backup database

**Checklist:**
```bash
npm run build
composer install --optimize-autoloader --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan storage:link
```

---

#### **Task 6.4.2: Post-Deployment Verification**

**Verify:**
- [ ] Application loads
- [ ] Login works
- [ ] Create test report
- [ ] Submit test report
- [ ] Approve test report
- [ ] Email notifications work
- [ ] File uploads work
- [ ] Activity logs work

---

## **PHASE 7: OPTIMIZATION & POLISH** (Optional, 1-2 ngày)

### 7.1. Performance (Ưu tiên: THẤP 🟢)

#### **Task 7.1.1: Database Optimization**

**Actions:**
- [ ] Add missing indexes
- [ ] Optimize queries
- [ ] Use eager loading
- [ ] Cache frequently accessed data

---

#### **Task 7.1.2: Frontend Optimization**

**Actions:**
- [ ] Lazy load components
- [ ] Optimize images
- [ ] Minimize API calls
- [ ] Add loading states

---

### 7.2. UI/UX Improvements (Ưu tiên: THẤP 🟢)

#### **Task 7.2.1: Responsive Design**

**Actions:**
- [ ] Test on mobile
- [ ] Test on tablet
- [ ] Fix responsive issues
- [ ] Improve mobile UX

---

#### **Task 7.2.2: Accessibility**

**Actions:**
- [ ] Add ARIA labels
- [ ] Keyboard navigation
- [ ] Color contrast
- [ ] Screen reader support

---

#### **Task 7.2.3: UX Enhancements**

**Actions:**
- [ ] Add tooltips
- [ ] Improve error messages
- [ ] Add confirmation dialogs
- [ ] Add success messages
- [ ] Loading indicators

---

## 📊 TỔNG KẾT THỜI GIAN DỰ KIẾN

| Phase | Nội dung | Thời gian | Ưu tiên |
|-------|----------|-----------|---------|
| Phase 1 | Database & Models | 2-3 ngày | 🔴 CAO |
| Phase 2 | Backend Services | 3-4 ngày | 🔴 CAO |
| Phase 3 | Controllers & Routes | 2-3 ngày | 🔴 CAO |
| Phase 4 | Frontend | 4-5 ngày | 🔴 CAO |
| Phase 5 | Testing & QA | 2-3 ngày | 🔴 CAO |
| Phase 6 | Deployment | 1-2 ngày | 🔴 CAO |
| Phase 7 | Optimization | 1-2 ngày | 🟢 THẤP |
| **TỔNG** | | **15-22 ngày** | |

---

## 🎯 ƯU TIÊN TRIỂN KHAI

### Giai đoạn MVP (Minimum Viable Product):
1. ✅ Phase 1: Database & Models
2. ✅ Phase 2: Core Services (Code Gen, Workflow Builder, Submission)
3. ✅ Phase 3: Basic Controllers
4. ✅ Phase 4: Basic Frontend (CRUD + Submit + Approve)
5. ✅ Phase 5: Basic Testing

**Thời gian MVP: 10-12 ngày**

### Giai đoạn hoàn thiện:
6. ✅ Phase 2 (remaining): Advanced features (Clone, Chain, Files)
7. ✅ Phase 4 (remaining): Advanced UI (Timeline, Logs, Chain)
8. ✅ Phase 5: Full testing
9. ✅ Phase 6: Deployment

**Thời gian hoàn thiện: 5-8 ngày**

### Giai đoạn tối ưu (Optional):
10. Phase 7: Optimization & Polish

**Thời gian tối ưu: 1-2 ngày**

---

## 🚀 BƯỚC TIẾP THEO

### Để bắt đầu triển khai, thực hiện theo thứ tự:

1. **Chuẩn bị môi trường:**
   ```bash
   # Đảm bảo database đã tạo
   # Cấu hình .env
   php artisan config:clear
   composer dump-autoload
   ```

2. **Bắt đầu Phase 1:**
   ```bash
   # Tạo migration đầu tiên
   php artisan make:migration create_departments_table
   ```

3. **Theo dõi tiến độ:**
   - Đánh dấu checklist sau mỗi task
   - Test sau mỗi feature
   - Commit code thường xuyên

4. **Code review:**
   - Review sau mỗi phase
   - Test integration
   - Update documentation

---

## 📝 LƯU Ý QUAN TRỌNG

1. **KHÔNG bỏ qua testing** - Test ngay sau khi code xong từng feature
2. **Commit thường xuyên** - Mỗi task nhỏ nên có 1 commit
3. **Follow naming conventions** - Nhất quán với code hiện tại
4. **Document as you go** - Viết comment và documentation ngay
5. **Authorization first** - Luôn implement Policy trước Controller
6. **Validate everything** - Backend validation là bắt buộc
7. **Activity log everything** - Tất cả actions quan trọng đều log
8. **Email notifications** - Đảm bảo queue configuration đúng

---

**Tài liệu này là living document - cập nhật khi có thay đổi!**

*Được tạo bởi: GitHub Copilot*  
*Ngày: 29/01/2026*
