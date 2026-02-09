<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get departments by code
        $departments = \App\Models\Department::pluck('id', 'code')->toArray();

        // Create admin_system user
        $adminSystem = User::factory()->create([
            'name' => 'Tony Nguyen',
            'email' => 'nguyenvancuong@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['IT'] ?? null, // IT department
        ]);
        $adminSystem->assignRole('admin_system');

        // Create requesters (người mua hàng - chọn nhà cung cấp)
        $requester1 = User::factory()->create([
            'name' => 'Nguyễn Văn Đồng',
            'email' => 'nvtm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['TM'] ?? null, // Phòng Thu Mua
        ]);
        $requester1->assignRole('requester');

        $requester2 = User::factory()->create([
            'name' => 'Trần Quốc Dũng',
            'email' => 'nvtm2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['TM'] ?? null, // Phòng Thu Mua
        ]);
        $requester2->assignRole('requester');

        $requester3 = User::factory()->create([
            'name' => 'Đặng Thị Minh Thủy',
            'email' => 'tptm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['TM'] ?? null, // Phòng Thu Mua
        ]);
        $requester3->assignRole('requester');

        // Create purchasing admin (hành chính - thủ tục, theo dõi, không tham gia duyệt)
        $purchasingAdmin = User::factory()->create([
            'name' => 'Nguyễn Thị Duyên',
            'email' => 'admtm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['TM'] ?? null,
        ]);
        $purchasingAdmin->assignRole('purchasing_admin');

        // Create internal control users (KSNB)
        $internalControl1 = User::factory()->create([
            'name' => 'Nguyễn Thị Kim Oanh',
            'email' => 'nvks@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['KSNB'] ?? null,
        ]);
        $internalControl1->assignRole('internal_control');

        $internalControl2 = User::factory()->create([
            'name' => 'Phan Thị Huệ',
            'email' => 'nvks2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['KSNB'] ?? null,
        ]);
        $internalControl2->assignRole('internal_control');

        // Create national purchasing users (MH)
        $nationalPurchasing1 = User::factory()->create([
            'name' => 'Lê Thị Hồng',
            'email' => 'khm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['MH'] ?? null,
        ]);
        $nationalPurchasing1->assignRole('national_purchasing');

        $nationalPurchasing2 = User::factory()->create([
            'name' => 'Vũ Hoàng Giang',
            'email' => 'khm2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['MH'] ?? null,
        ]);
        $nationalPurchasing2->assignRole('national_purchasing');

        // Create tech board users (KT)
        $techBoard1 = User::factory()->create([
            'name' => 'Hồ Trung Tín',
            'email' => 'bkt@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['KT'] ?? null,
        ]);
        $techBoard1->assignRole('tech_board');

        $techBoard2 = User::factory()->create([
            'name' => 'Mai Văn I',
            'email' => 'bkt2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['KT'] ?? null,
        ]);
        $techBoard2->assignRole('tech_board');

        // Create BOD users (BGD)
        $bod1 = User::factory()->create([
            'name' => 'Nguyễn Khôi Nguyên',
            'email' => 'gd@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['BGD'] ?? null,
        ]);
        $bod1->assignRole('bod');

        $bod2 = User::factory()->create([
            'name' => 'Đỗ Tiến Quân',
            'email' => 'gd2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['BGD'] ?? null,
        ]);
        $bod2->assignRole('bod');

        // Create Accountant user (Kế toán)
        $accountant = User::factory()->create([
            'name' => 'Nguyễn Văn Kế Toán',
            'email' => 'nvkt@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'department_id' => $departments['PK'] ?? null,
        ]);
        $accountant->assignRole('accountant');

        $this->command->info('Users created with workflow-specific roles successfully!');
    }
}

