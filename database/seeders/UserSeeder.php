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
            'department_id' => $departments['BGD'] ?? null, // BGD department
        ]);
        $adminSystem->assignRole('admin_system');

        // Create requesters (người tạo phiếu)
        $requester1 = User::factory()->create([
            'name' => 'Nguyễn Văn A',
            'email' => 'requester1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['TM'] ?? null, // Thương mại
        ]);
        $requester1->assignRole('requester');

        $requester2 = User::factory()->create([
            'name' => 'Trần Thị B',
            'email' => 'requester2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['BT'] ?? null, // Bảo trì
        ]);
        $requester2->assignRole('requester');

        // Create purchasing admin (theo dõi, không tham gia duyệt)
        $purchasingAdmin = User::factory()->create([
            'name' => 'Lê Văn C',
            'email' => 'purchasing.admin@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['MH'] ?? null,
        ]);
        $purchasingAdmin->assignRole('purchasing_admin');

        // Create internal control users (KSNB)
        $internalControl1 = User::factory()->create([
            'name' => 'Phạm Thị D',
            'email' => 'internal.control1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['KSNB'] ?? null,
        ]);
        $internalControl1->assignRole('internal_control');

        $internalControl2 = User::factory()->create([
            'name' => 'Hoàng Văn E',
            'email' => 'internal.control2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['KSNB'] ?? null,
        ]);
        $internalControl2->assignRole('internal_control');

        // Create national purchasing users (MH)
        $nationalPurchasing1 = User::factory()->create([
            'name' => 'Võ Thị F',
            'email' => 'national.purchasing1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['MH'] ?? null,
        ]);
        $nationalPurchasing1->assignRole('national_purchasing');

        $nationalPurchasing2 = User::factory()->create([
            'name' => 'Đặng Văn G',
            'email' => 'national.purchasing2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['MH'] ?? null,
        ]);
        $nationalPurchasing2->assignRole('national_purchasing');

        // Create tech board users (KT)
        $techBoard1 = User::factory()->create([
            'name' => 'Bùi Thị H',
            'email' => 'tech.board1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['KT'] ?? null,
        ]);
        $techBoard1->assignRole('tech_board');

        $techBoard2 = User::factory()->create([
            'name' => 'Mai Văn I',
            'email' => 'tech.board2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['KT'] ?? null,
        ]);
        $techBoard2->assignRole('tech_board');

        // Create BOD users (BGD)
        $bod1 = User::factory()->create([
            'name' => 'Ngô Thị K',
            'email' => 'bod1@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['BGD'] ?? null,
        ]);
        $bod1->assignRole('bod');

        $bod2 = User::factory()->create([
            'name' => 'Dương Văn L',
            'email' => 'bod2@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['BGD'] ?? null,
        ]);
        $bod2->assignRole('bod');

        $bod3 = User::factory()->create([
            'name' => 'Lý Thị M',
            'email' => 'bod3@example.com',
            'password' => bcrypt('password'),
            'department_id' => $departments['BGD'] ?? null,
        ]);
        $bod3->assignRole('bod');

        $this->command->info('Users created with workflow-specific roles successfully!');
    }
}

