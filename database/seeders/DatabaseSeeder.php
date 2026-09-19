<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\CourseProvisioner;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $accounts = [
            ['Maria', 'Santos', 'student@aquadissect.local', 'STUDENT-001', 'student', 'active', 'FISHBIO-101'],
            ['Dr. Ana', 'Reyes', 'instructor@aquadissect.local', 'FACULTY-001', 'instructor', 'active', null],
            ['System', 'Administrator', 'admin@aquadissect.local', 'ADMIN-001', 'admin', 'active', null],
        ];

        foreach ($accounts as [$firstName, $lastName, $email, $identityNumber, $role, $status, $classCode]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'identity_number' => $identityNumber,
                    'role' => $role,
                    'status' => $status,
                    'class_code' => $classCode,
                    'email_verified_at' => now(),
                    'password' => Hash::make('demo123'),
                ],
            );
        }

        $this->call(SpeciesCatalogSeeder::class);
        $this->call(LearningModuleSeeder::class);
        app(CourseProvisioner::class)->ensure();
    }
}
