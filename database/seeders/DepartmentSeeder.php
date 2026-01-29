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
            ['code' => 'TM', 'name' => 'Phòng Thương mại'],
            ['code' => 'BT', 'name' => 'Phòng Bán hàng Thương mại'],
            ['code' => 'KSNB', 'name' => 'Phòng Kiểm soát nội bộ'],
            ['code' => 'BGD', 'name' => 'Ban Giám đốc'],
            ['code' => 'KT', 'name' => 'Ban Kỹ thuật'],
            ['code' => 'MH', 'name' => 'Khối Mua hàng toàn quốc'],
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
