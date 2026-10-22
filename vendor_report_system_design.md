# THIẾT KẾ PHẦN MỀM QUẢN LÝ BÁO CÁO LỰA CHỌN NHÀ CUNG CẤP

> **Mục tiêu:** Thiết kế kỹ thuật và nghiệp vụ của hệ thống. Phần workflow được đối chiếu source ngày 22/10/2026. Kế hoạch, tiêu chí nghiệm thu, kết quả kiểm thử và các quyết định còn mở xem [quy trình KSNB → Giám đốc](docs/PLAN_INTERNAL_CONTROL_BOD_WORKFLOW.md). Tài liệu không xác nhận đã triển khai lên DB ứng dụng.

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
5. Mã phiếu: `YYYY/DEPT_CODE/SEQ` (SEQ tăng chung theo năm cho tất cả phòng ban)

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
- Department phải đang hoạt động, có code để sinh mã phiếu. Chỉ bắt buộc `head_user_id` nếu cấu hình workflow chứa `DEPT_HEAD`.

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
- workflow_type enum: NORMAL | SPECIAL_1 | SPECIAL_2 | SPECIAL_3 | URGENT | SPECIAL_4 | INTERNAL_CONTROL_BOD
- purchasing_admin_id (FK users) ← **Chỉ để theo dõi, KHÔNG nằm trong workflow**
- created_by (FK users)
- status enum: DRAFT | SUBMITTED | IN_APPROVAL | APPROVED | REJECTED | CANCELED
- current_step_id (FK vendor_report_approval_steps, nullable)
- submitted_at, approved_at, rejected_at
- rejected_note (nullable)
- parent_id (nullable, FK vendor_reports)
- root_id (nullable, FK vendor_reports)
- timestamps

---

### 3.2 Workflow Definitions (Chi tiết 7 luồng)

#### 3.2.1 NORMAL - Phiếu thường
```
Người tạo → Trưởng phòng → Kiểm soát nội bộ (chọn BGĐ) → Ban giám đốc
```
- **Step 1 - DEPT_HEAD**: Trưởng phòng duyệt
- **Step 2 - INTERNAL_CONTROL**: Kiểm soát nội bộ duyệt + chọn user có role `bod`
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

#### 3.2.6 SPECIAL_4 - Bỏ qua Trưởng phòng, qua hai lượt BGĐ

Người tạo → KSNB (chọn BGĐ 1) → BGĐ 1 (chọn BGĐ 2) → BGĐ 2.

Runtime steps: `INTERNAL_CONTROL → BOD_1 → BOD_2`; hai bước đầu bắt buộc chọn người có role `bod`.

#### 3.2.7 INTERNAL_CONTROL_BOD - KSNB → Giám đốc (bỏ qua Trưởng phòng)

Người tạo lưu nháp và gửi → KSNB (chọn một Giám đốc) → Giám đốc được chọn.

- Đúng hai runtime steps: `INTERNAL_CONTROL → BOD`. Không tạo `DEPT_HEAD`, kể cả dưới trạng thái `SKIPPED`; không có lượt BGĐ thứ hai.
- KSNB là bước đầu giao theo role `internal_control`. Khi duyệt, bắt buộc chọn user `bod` đang hoạt động, chưa bị xóa; bước `BOD` lưu `assignee_user_id` của người này.
- Submit yêu cầu có ít nhất một KSNB và một BGĐ đang hoạt động, chưa bị xóa. Thiếu người thì báo lỗi, giữ nháp và không sinh mã/steps.
- Hai lượt duyệt luôn thủ công, kể cả người tạo kiêm role KSNB/BGĐ. Chưa có ràng buộc ba người phải khác nhau.
- Loại mới là một lựa chọn thêm; không thay mặc định `NORMAL`, không chuyển đổi phiếu đã submit. Sáu workflow cũ giữ cấu hình và cơ chế tự duyệt cũ.

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

Chi tiết xem mục 3.2. `VendorReportWorkflowBuilder` tạo snapshot runtime steps từ config khi submit. Phiếu đang duyệt tiếp tục dùng steps trong DB; thay đổi config không dựng lại chuỗi đã submit.

---

## 5. Transition Rules

### 5.1 Submit

- Khóa và đọc lại report trong transaction; chỉ nhận `DRAFT`.
- Builder là nơi dùng chung để kiểm tra workflow/phòng ban/Trưởng phòng và preflight người duyệt của loại mới.
- Tạo steps, sinh mã, cập nhật `IN_APPROVAL`, `submitted_at`, `current_step_id` và audit trong cùng transaction; lỗi thì rollback toàn bộ.
- `INTERNAL_CONTROL_BOD` giữ cả hai bước `PENDING`. Các loại khác vẫn chạy cơ chế tự duyệt của creator hiện có.
- Gửi thông báo người đến lượt sau khi transaction ngoài cùng commit; submit lặp không tạo thêm steps.

### 5.2 Approve

- Khóa report trước, rồi khóa bước hiện tại/bước tiếp theo theo thứ tự workflow. Đọc lại trạng thái và người hành động sau khóa; từ chối model đã cũ khi status/current step thay đổi.
- Report phải `IN_APPROVAL`, bước hiện tại phải `PENDING`; actor đang hoạt động, chưa bị xóa và đúng assignment. Bước chưa gán user nhận người có role tương ứng; đã gán user thì chỉ user đó có quyền.
- `requires_selection` bắt buộc chọn user đang hoạt động, chưa bị xóa, đúng `selection_role`, dùng chung `User::activeWithRole()` với danh sách lựa chọn. Bước không yêu cầu lựa chọn từ chối payload chọn người tùy ý.
- Ghi người thực hiện, ghi chú, thời gian, người được chọn và audit; chuyển `current_step_id` hoặc kết thúc `APPROVED`, có `approved_at`, `current_step_id = null`.
- Assignment cụ thể là snapshot: đổi role sau khi được chọn không tự chuyển quyền cho BGĐ khác. User bị khóa/xóa không được hành động; chưa có luồng gán lại. Chính sách vận hành khi thu hồi role vẫn cần chốt theo kế hoạch.

### 5.3 Reject / Cancel

- Reject dùng cùng thứ tự khóa và kiểm tra quyền với approve; ghi bước `REJECTED`, report `REJECTED`, lý do/thời gian, xóa `current_step_id`. Không duyệt tiếp phiếu này.
- Cancel dành cho `admin_system` đang hoạt động, khóa report trước steps; ghi `CANCELED`, lý do/thời gian, xóa bước hiện tại và đánh dấu các bước còn `PENDING` thành `SKIPPED`.
- Audit và cập nhật dữ liệu cùng transaction; thông báo chỉ phát sau commit. Các thao tác cạnh tranh không cùng xử lý một snapshot cũ.

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

- Submit → người đang hoạt động được giao bước đầu (cụ thể hoặc cả nhóm role chưa được gán).
- Approve → người được giao bước tiếp theo; hoàn tất hoặc reject → creator và purchasing admin đang hoạt động, không gửi trùng nếu cùng user.
- Cancel → creator và những người đã hành động đang hoạt động.
- Gửi qua queue, kênh mail/database, sau commit transaction ngoài cùng. Rollback không gửi thông báo.

---

## 9. Authorization (Policies)

- `VendorReport::visibleTo()` là nguồn quyền xem dùng chung cho policy, danh sách và dashboard. Quyền nhiều role được kết hợp, không dừng ở role requester: phiếu của requester, phiếu được giao purchasing admin (không phải nháp), accountant xem phiếu hoàn tất, Trưởng phòng xem phiếu cùng phòng, người đến lượt/đã duyệt xem theo assignment/lịch sử, KSNB xem phiếu đã qua nhóm KSNB; admin_system xem toàn bộ. User không hoạt động/bị xóa không được xem.
- `awaitingApprovalBy()` và `VendorReportApprovalStep::pendingFor()` chỉ nhận report `IN_APPROVAL`, bước `PENDING` đúng `current_step_id`, đúng assignment; dùng cho việc chờ xử lý và bộ đếm, không tính mọi bước tương lai.
- Tạo phiếu: user active có department. Sửa/gửi/xóa: creator active và `DRAFT`. Clone: creator active, phiếu `REJECTED` và chưa có phiếu con.
- Approve/reject dùng `canActOn()` của bước hiện tại; service kiểm tra lại sau khóa. Bỏ bước Trưởng phòng không bỏ quyền xem cùng phòng.
- Create/Edit dùng mô tả workflow chung từ `VendorReportService.js`. Show giữ yêu cầu chọn người dù danh sách rỗng, báo lỗi và khóa duyệt khi thiếu lựa chọn; backend vẫn xác thực lại.
- Timeline và bản in dùng runtime steps hiện có, không thêm nhánh dựng chuỗi riêng cho loại mới.

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

- Workflow chứa `DEPT_HEAD` không submit khi thiếu Trưởng phòng; hai loại bỏ Trưởng phòng không yêu cầu trường này.
- Mã phiếu chứa mã phòng, sequence tăng chung theo năm.
- Workflow chạy đúng 7 loại; loại mới luôn có đúng hai thao tác duyệt thủ công.
- Quyền chọn BGĐ, nhiều role, cạnh tranh, rollback thông báo và snapshot được kiểm thử theo [ma trận nghiệm thu](docs/PLAN_INTERNAL_CONTROL_BOD_WORKFLOW.md#7-ma-trận-nghiệm-thu).
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
9. Migration nâng cấp `2026_10_21_000000_add_internal_control_bod_workflow_type.php` thêm giá trị mới trên MySQL/MariaDB và SQLite, giữ dữ liệu cũ. `down()` từ chối nếu còn phiếu dùng loại mới; không đổi migration tạo bảng hoặc chạy `migrate:fresh` trên DB ứng dụng.

`VendorReportSeeder` vẫn là dữ liệu mô phỏng cũ, chưa sinh loại mới và có chuỗi hard-code khác config (ví dụ URGENT). Không dùng seeder này làm chuẩn nghiệm thu; test workflow dùng fixture tối thiểu và runtime builder thật.

---

**END OF DOCUMENT**

