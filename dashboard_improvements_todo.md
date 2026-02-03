# DASHBOARD – TỔNG HỢP ĐIỂM CẦN SỬA (FE + BE)

> Mục tiêu: checklist rõ ràng để GitHub Copilot agent sửa nhanh, ưu tiên theo P0/P1/P2.

---

## FE – Dashboard.vue

### P0 (bắt buộc)
1) **Không mutate props trực tiếp**
- Hiện: `props.activities.splice(...)`
- Sửa: tạo local state `ref`:
  - `const activities = ref([...props.activities])`
  - `const pendingActions = ref([...props.pendingActions])` (nếu cập nhật bằng API)
- Render Timeline/DataTable bằng local state.

2) **Nút “Làm mới” refresh đúng kỳ vọng**
- Hiện: gọi `DashboardService.refresh()` rồi `router.reload({ only: [...] })` nhưng charts không refresh.
- Sửa: sau khi reload props (hoặc sau refresh API) phải gọi lại:
  - `fetchChartData('status')`
  - `fetchChartData('trend')`
  - và `refreshActivities()` nếu đang fetch riêng.

3) **Fix class ellipsis/nowrap**
- `white-space-nowrap` có thể sai.
- Nếu dùng Tailwind: đổi thành `whitespace-nowrap`.

### P1 (nên làm)
4) **Empty states cho các khối**
- PendingActions = 0: card “Không có phiếu cần xử lý” + CTA “Tạo phiếu mới”.
- Activities = 0: “Chưa có hoạt động”.
- Chart data trống: “Chưa có dữ liệu”.

5) **Bảng “Phiếu cần xử lý” thêm ngữ cảnh**
- Thêm cột “Phòng ban” hoặc “Người tạo” (tối thiểu 1) để BGĐ/KSNB xử lý nhanh.

6) **Quick actions hiển thị theo quyền**
- Dựa trên roles/isDeptHead để filter action.
- Tránh show nút rồi user bấm bị 403.

### P2 (optional)
7) **Giảm spam toast khi nhiều API fail**
- Thêm option `silent` / `toast=false` trong DashboardService.
- Dashboard show 1 toast tổng hợp.

8) **Timezone/date parsing**
- Đảm bảo backend trả ISO8601 có offset `+07:00`.
- Hoặc dùng dayjs/date-fns (nếu cần).

9) **Tránh request chồng**
- (Optional) AbortController cho chart/activities khi refresh liên tục.

---

## FE – DashboardService.js

### P0
10) **Chọn 1 chiến lược refresh, tránh request thừa**
- Hiện: gọi `POST /api/dashboard/refresh` + `router.reload()` → lãng phí.
- Chọn 1:
  - Option A: bỏ endpoint refresh, chỉ dùng `router.reload({ only: [...] })`.
  - Option B: dùng refresh API, cập nhật local state trực tiếp (không reload Inertia).

### P1
11) **Validate type/period trước khi gọi chart-data**
- type ∈ {status, workflow, trend, department}
- period ∈ {week, month, quarter, year}

---

## BE – DashboardController

### P0
1) **Bỏ hoặc dùng đúng /api/dashboard/refresh**
- Nếu FE dùng Inertia reload → xóa refresh endpoint.
- Nếu FE dùng refresh API → FE phải set state từ payload trả về.

2) **Không dùng `userRole = first()`**
- `getRoleNames()->first()` không ổn định.
- Sửa: trả `roles = $user->getRoleNames()` (array).

3) **Whitelist event filter**
- Validate `event` theo danh sách: created, submitted, approved, rejected, cancelled, ...

### P1
4) **Chuẩn hóa response shape**
- Giữ `{ success: true, data: ... }` nhất quán.
- Nếu pendingActions/activities có format: nên dùng Resource/DTO thống nhất.

---

## BE – App\\Services\\DashboardService.php

### P0 (bắt buộc)
1) **getActivities() tránh whereIn mảng reportIds lớn**
- Hiện: lấy ids về PHP rồi whereIn.
- Sửa: filter bằng subquery/orWhereIn trực tiếp trong DB:
  - subject_id IN (select id from vendor_reports where created_by = user)
  - OR subject_id IN (select report_id from vendor_report_approval_steps where assignee_user_id = user)

2) **calculateAvgResponseTime() chuyển sang DB aggregate**
- Hiện: `get()->map()->avg()`.
- Sửa: `AVG(TIMESTAMPDIFF(HOUR, created_at, acted_at))/24`.

3) **getTrendChartData() giảm query**
- Hiện: 6 tháng × 2 status = 12 queries.
- Sửa: 1 query groupBy `year-month` + `status`, rồi build arrays.

4) **Status labels đúng nghiệp vụ (không dùng ucfirst/strtolower)**
- Map label tiếng Việt: DRAFT/IN_APPROVAL/APPROVED/REJECTED/SUBMITTED...

### P1 (nên làm)
5) **Thêm caching (đã import Cache nhưng chưa dùng)**
- Cache per-user TTL 30–60s:
  - metrics, pendingActions, activities
  - chart-data theo key `dashboard:chart:{userId}:{type}:{period}`

6) **days_pending tính theo step.created_at**
- Hiện: diff từ report.submitted_at.
- Sửa: diff từ step.created_at để phản ánh “chờ ở bước hiện tại”.

7) **isDeptHead() tránh lazy load query phụ**
- loadMissing('department') hoặc query exists.

8) **Department chart quyền truy cập**
- Hiện: top departments global.
- Cân nhắc filter theo role (non-admin chỉ xem own).

### P2 (optional)
9) **Màu chart không hardcode ở backend**
- Backend trả labels/data, FE set colors theo theme/dark mode.

10) **Sort pendingActions rõ ràng**
- Sort 2 tiêu chí: URGENT desc rồi days_pending desc (tránh hack 1000+days).

---

## Action Plan đề xuất (thứ tự sửa)
1) Chốt 1 chiến lược refresh (Inertia reload hoặc refresh API) → sửa FE+BE.
2) FE: local state thay vì mutate props.
3) BE: trả roles array, whitelist event.
4) BE Service: sửa getActivities subquery + avgResponse DB + trend groupBy.
5) Thêm caching 30–60s.
6) days_pending theo step.created_at + status labels map.

---

**END**

