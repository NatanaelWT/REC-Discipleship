<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsRecUser(string $username = 'tester', ?string $branch = 'kutisari', string $scope = 'pemuridan_cabang'): User
    {
        $user = new User;
        $user->forceFill([
            'id' => 999999,
            'username' => $username,
            'name' => $username,
            'email' => $username.'@rec.local',
            'branch_code' => $branch,
            'access_scope' => $scope,
            'is_active' => true,
        ]);
        $user->exists = true;

        $this->actingAs($user);

        return $user;
    }
}
