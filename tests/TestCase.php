<?php

namespace Tests;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\ReferenceSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedReference(): void
    {
        $this->seed(ReferenceSeeder::class);
    }

    /** A user in the given branch (by code) with one role and signing PIN 2468. */
    protected function makeUser(string $role, string $branchCode = 'HQ'): User
    {
        $u = User::create([
            'name' => $role.' '.$branchCode, 'email' => strtolower(preg_replace('/\W+/', '.', $role.' '.$branchCode)).'.'.uniqid().'@test.example',
            'branch_id' => Branch::where('code', $branchCode)->value('id'), 'password' => bcrypt('secret-password'), 'is_active' => true,
        ]);
        $u->setSigningPin('2468');
        $u->assignRole($role);

        return $u->fresh();
    }
}
