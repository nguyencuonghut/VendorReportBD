# THIẾT KẾ PHẦN MỀM QUẢN LÝ BÁO CÁO LỰA CHỌN NHÀ CUNG CẤP

> **Mục tiêu:** Tài liệu này là bản thiết kế kỹ thuật & nghiệp vụ đầy đủ, dùng làm **nguồn đầu vào trực tiếp cho GitHub Copilot / coding agent** triển khai hệ thống.

---

## 0. Tổng quan

### 0.1 Mục tiêu hệ thống
- Quản lý **Phiếu lựa chọn nhà cung cấp** (Vendor Selection Report)
- Phê duyệt theo **nhiều luồng khác nhau**
- Email notification theo từng bước
- Audit log đầy đủ, không sửa
- Hỗ trợ **tạo phiếu mới từ phiếu bị từ chối** (phiếu con kế thừa log)

### 0.2 Stack công nghệ
- Backend: **Laravel 12**
- Frontend: **Inertia.js + Vue 3 + PrimeVue version 4**
- Auth: Laravel Breeze / Fortify
- RBAC: spatie/laravel-permission
- Activity log: spatie/laravel-activitylog
- Queue/Email: Laravel Queue + Horizon

---

## 1. Nguyên tắc thiết kế cốt lõi

1. **Trưởng phòng là thuộc tính của Department**, không nằm ở User
2. **Snapshot người duyệt tại thời điểm Submit / Select**, không tính động
3. Phiếu **REJECTED là trạng thái kết thúc (terminal)**
4. Workflow dùng **runtime steps trong DB**, không hard-code
5. Mã phiếu: `YYYY/DEPT_CODE/SEQ` (SEQ tăng theo năm + phòng ban)

---

## 2. Module Auth / User / Department / RBAC

### 2.1 Authentication
- Login email/password (session-based)
- User `is_active = false` → không cho đăng nhập

### 2.2 Departments

**Table: departments**
- id
- code (string, unique, uppercase – TM, KSNB, BGD…)
- name
- head_user_id (FK users, nullable) ← **Trưởng phòng hiện tại**
- parent_id (nullable, FK departments)
- is_active (bool)
- timestamps

**Rules:**
- **Chỉ dùng is_active, KHÔNG dùng softDeletes()**
- Không xóa cứng nếu có user/phiếu → set is_active=false
- code dùng để sinh mã phiếu

---

### 2.3 Users

**users (bổ sung):**
- department_id (FK departments)
- is_active (bool)
- last_login_at (nullable)

**Rules nghiệp vụ:**
- User tạo phiếu phải có department
- Department phải có code và head_user_id khi submit phiếu

---

### 2.4 Roles (spatie)

**Roles đề xuất:**
- admin_system
- requester
- purchasing_admin
- internal_control
- national_purchasing
- tech_board
- bod

> Lưu ý: Trưởng phòng xác định bằng `departments.head_user_id`, **không phụ thuộc role**

---

## 3. Module Phiếu lựa chọn nhà cung cấp

### 3.1 VendorReport

**Table: vendor_reports**
- id
- code (string, unique)
- title (string)
- workflow_type enum: NORMAL | SPECIAL_1 | SPECIAL_2 | SPECIAL_3 | URGENT
- purchasing_admin_id (FK users) ← **Chỉ để theo dõi, KHÔNG nằm trong workflow**
- created_by (FK users)
- status enum: DRAFT | SUBMITTED | IN_APPROVAL | APPROVED | REJECTED
- current_step_id (FK vendor_report_approval_steps, nullable)
- submitted_at, approved_at, rejected_at
- rejected_note (nullable)
- parent_id (nullable, FK vendor_reports)
- root_id (nullable, FK vendor_reports)
- timestamps

---

### 3.2 Workflow Definitions (Chi tiết 5 luồng)

#### 3.2.1 NORMAL - Phiếu thường
```
Người tạo → Trưởng phòng → Kiểm soát nội bộ (chọn BGĐ) → Ban giám đốc
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt
- **Step 2 - INTERNAL_CONTROL**: Kiểm soát nội bộ duyệt + chọn user trong phòng BGĐ
- **Step 3 - BOD**: BGĐ được chọn duyệt

#### 3.2.2 SPECIAL_1 - Phiếu đặc biệt 1
```
Người tạo → Trưởng phòng → Kiểm soát nội bộ (chọn BGĐ 1) → BGĐ 1 (chọn BGĐ 2) → BGĐ 2
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt
- **Step 2 - INTERNAL_CONTROL**: Kiểm soát nội bộ duyệt + chọn BGĐ duyệt lần 1
- **Step 3 - BOD_1**: BGĐ duyệt lần 1 + chọn BGĐ duyệt lần 2
- **Step 4 - BOD_2**: BGĐ duyệt lần 2

#### 3.2.3 SPECIAL_2 - Phiếu đặc biệt 2
```
Người tạo → Trưởng phòng (chọn Khối mua hàng) → Khối mua hàng → Kiểm soát nội bộ (chọn BGĐ) → BGĐ
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt + chọn user trong Khối mua hàng toàn quốc
- **Step 2 - NATIONAL_PURCHASING**: Khối mua hàng toàn quốc duyệt
- **Step 3 - INTERNAL_CONTROL**: Kiểm soát nội bộ duyệt + chọn BGĐ
- **Step 4 - BOD**: BGĐ duyệt

#### 3.2.4 SPECIAL_3 - Phiếu đặc biệt 3
```
Người tạo → Trưởng phòng (chọn Ban kỹ thuật) → Ban kỹ thuật → Kiểm soát nội bộ (chọn BGĐ) → BGĐ
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt + chọn user trong Ban kỹ thuật
- **Step 2 - TECH_BOARD**: Ban kỹ thuật duyệt
- **Step 3 - INTERNAL_CONTROL**: Kiểm soát nội bộ duyệt + chọn BGĐ
- **Step 4 - BOD**: BGĐ duyệt

#### 3.2.5 URGENT - Phiếu gấp (Bỏ qua Kiểm soát nội bộ)
```
Người tạo → Trưởng phòng (tick gấp + chọn BGĐ) → BGĐ
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt + tick "Phiếu gấp" + chọn BGĐ
- **Step 2 - BOD**: BGĐ duyệt (BỎ QUA kiểm soát nội bộ)

---

### 3.3 Files

**Table: vendor_report_files**
- id
- report_id (FK vendor_reports)
- type enum: REPORT_IMAGE | QUOTATION | BOQ
- disk
- path
- original_name
- mime
- size
- uploaded_by (FK users)
- timestamps

**Rules:**
- Lưu private storage
- Download có authorization

---

### 3.4 Sinh mã phiếu

**Table: yearly_sequences**
- id
- year (int, unique) ← **GLOBAL sequence, không có department_id**
- current_seq (int)

**Algorithm:**
- Transaction + SELECT FOR UPDATE trên year
- Increment current_seq (GLOBAL cho tất cả departments)
- code = YYYY/DEPT_CODE/SEQ
- **Ví dụ:** 2026/TM/024 → 2026/BT/025 → 2026/KSNB/026

---

## 4. Workflow Engine

### 4.1 Runtime Steps

**Table: vendor_report_approval_steps**
- id
- report_id
- step_key (DEPT_HEAD, INTERNAL_CONTROL, BOD_1…)
- step_order
- status: PENDING | APPROVED | REJECTED | SKIPPED
- assignee_user_id (snapshot)
- assignee_role (nullable)
- acted_by, acted_at, note
- requires_selection (bool)
- selection_role (nullable)
- selected_next_approver_id (nullable)
- timestamps

---
Config File

**File: `config/vendor_report_workflows.php`**

Chi tiết xem section 3.2 ở trên. Config này sẽ dùng để VendorReportWorkflowBuilder tự động tạo runtime steps khi submit phiếu.
(chi tiết giống nội dung đã thống nhất trong trao đổi, dùng cho Copilot build runtime steps)

---

## 5. Transition Rules

### 5.1 Submit
- Validate dữ liệu bắt buộc
- Validate department.head_user_id
- Sinh code nếu chưa có
- Build approval steps
- status = IN_APPROVAL
- current_step_id = step đầu
- Gửi email notify

### 5.2 Approve
- Chỉ assignee_user_id mới được duyệt
- Nếu requires_selection mà chưa chọn → reject action
- Move sang step tiếp theo hoặc APPROVED

### 5.3 Reject
- Set step REJECTED
- Set report REJECTED (terminal)
- Không cho duyệt lại

---

## 6. Clone phiếu từ phiếu bị từ chối

- Chỉ áp dụng khi status = REJECTED
- Phiếu mới:
  - parent_id = old.id
  - root_id = old.root_id ?? old.id
  - status = DRAFT
- Log: report_cloned_from_rejected

---

## 7. Activity Log

- Dùng spatie/activitylog
- Log: create/update/submit/approve/reject/select/upload/clone
- Tab nhật ký hiển thị **log của toàn bộ chain (cha – con)**

---

## 8. Notifications

- Submit → notify step 1
- Approve → notify step tiếp theo
- Reject → notify creator + purchasing admin
- Gửi qua Queue

---

## 9. Authorization (Policies)

**VendorReportPolicy:**
- view: creator | purchasing_admin | approver | admin
- update: creator & DRAFT
- submit: creator & DRAFT
- approve/reject: current step assignee
- clone: creator | purchasing_admin & REJECTED

---

## 10. Routes

### Admin
- /admin/users
- /admin/departments

### Vendor Reports
- GET /vendor-reports
- POST /vendor-reports
- PUT /vendor-reports/{id}
- POST /vendor-reports/{id}/submit
- POST /vendor-reports/{id}/approve
- POST /vendor-reports/{id}/reject
- POST /vendor-reports/{id}/clone
- POST /vendor-reports/{id}/files

---

## 11. Services (để Copilot code nhanh)

- VendorReportCodeGenerator
- VendorReportWorkflowBuilder
- VendorReportSubmissionService
- VendorReportApprovalService
- VendorReportChainService

---

## 12. Acceptance Criteria

- Không submit nếu thiếu trưởng phòng
- Mã phiếu tăng đúng theo năm/phòng
- Workflow chạy đúng 5 loại
- Reject là terminal
- Clone tạo phiếu mới, giữ log chain

---

## 13. Migrations Checklist

1. departments
2. users (add fields)
3. yearly_sequences
4. vendor_reports
5. vendor_report_files
6. vendor_report_approval_steps
7. spatie permission tables
8. activity_log

---

**END OF DOCUMENT**

