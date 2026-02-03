<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
        ]);

        // Update department heads after users are created
        $this->updateDepartmentHeads();

        $this->call([
            VendorReportSeeder::class,
        ]);
    }

    /**
     * Update department heads after users are created
     */
    private function updateDepartmentHeads(): void
    {
        // Set Đặng Thị Minh Thủy as head of Phòng Thu Mua
        $deptHeadUser = \App\Models\User::where('email', 'tptm@honghafeed.com.vn')->first();
        if ($deptHeadUser) {
            $thuMuaDept = \App\Models\Department::where('code', 'TM')->first();
            if ($thuMuaDept) {
                $thuMuaDept->update(['head_user_id' => $deptHeadUser->id]);
                $this->command->info('✓ Set Đặng Thị Minh Thủy as head of Phòng Thu Mua');
            }
        }
    }
}
