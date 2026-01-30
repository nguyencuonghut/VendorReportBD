<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            ['code' => 'HCNS', 'name' => 'Phòng Hành Chính Nhân Sự'],
            ['code' => 'TM', 'name' => 'Phòng Thu Mua'],
            ['code' => 'MH', 'name' => 'Khối Mua Hàng Toàn Quốc'],
            ['code' => 'KSNB', 'name' => 'Bộ phận Kiểm Soát Nội Bộ'],
            ['code' => 'SX', 'name' => 'Phòng Sản Xuất'],
            ['code' => 'BGD', 'name' => 'Ban Giám Đốc'],
            ['code' => 'KT', 'name' => 'Ban Kỹ Thuật'],
            ['code' => 'IT', 'name' => 'Bộ phận IT'],
        ];

        foreach ($departments as $dept) {
            Department::create([
                'code' => $dept['code'],
                'name' => $dept['name'],
                'is_active' => true,
            ]);
        }
    }
}
