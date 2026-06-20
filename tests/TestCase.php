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
            'branch_id' => match ($branch) {
                'kutisari' => 1,
                'gm' => 2,
                'darmo' => 3,
                'merr' => 4,
                'batam' => 5,
                'nginden' => 6,
                default => null,
            },
            'access_scope' => $scope,
            'is_active' => true,
        ]);
        $user->exists = true;

        $this->actingAs($user);

        return $user;
    }
}
