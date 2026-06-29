<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ChangePasswordByUserSelfTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/v1/users/change-paasword-by-user-self';

    /**
     * Inserted via the query builder (not User::create) because
     * App\User::boot()'s creating listener requires an authenticated
     * Auth::user(), which doesn't exist in these unauthenticated tests.
     */
    private function makeUser(array $overrides = []): int
    {
        return DB::table('users')->insertGetId(array_merge([
            'name' => 'Test User',
            'email' => 'sysadmin@gmail.com',
            'password' => bcrypt('OldPass1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    public function testChangesPasswordWithValidCurrentPasswordAndEmail()
    {
        $this->makeUser();

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'OldPass1',
            'newPassword' => 'NewPass2',
        ]);

        $response->assertStatus(200)->assertJson(['status' => 'success']);

        $updatedHash = DB::table('users')->where('email', 'sysadmin@gmail.com')->value('password');
        $this->assertTrue(Hash::check('NewPass2', $updatedHash));
    }

    public function testRejectsWrongCurrentPassword()
    {
        $this->makeUser();

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'WrongPass1',
            'newPassword' => 'NewPass2',
        ]);

        $response->assertStatus(406);
        $this->assertStringContainsString('Invalid email or current password', $response->getContent());
    }

    public function testRejectsUnknownEmailWithSameGenericMessageAsWrongPassword()
    {
        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'doesnotexist@gmail.com',
            'currentPassword' => 'WhateverPass1',
            'newPassword' => 'NewPass2',
        ]);

        $response->assertStatus(406);
        $this->assertStringContainsString('Invalid email or current password', $response->getContent());
    }

    public function testRejectsInactiveUser()
    {
        $this->makeUser(['is_active' => false]);

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'OldPass1',
            'newPassword' => 'NewPass2',
        ]);

        $response->assertStatus(406);
        $this->assertStringContainsString('not an Active User', $response->getContent());
    }

    public function testRejectsNewPasswordSameAsCurrentPassword()
    {
        $this->makeUser();

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'OldPass1',
            'newPassword' => 'OldPass1',
        ]);

        $response->assertStatus(422);
    }

    public function testRejectsNewPasswordShorterThanEightCharacters()
    {
        $this->makeUser();

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'OldPass1',
            'newPassword' => 'short1',
        ]);

        $response->assertStatus(422);
    }

    public function testRejectsMissingFields()
    {
        $response = $this->postJson(self::ENDPOINT, []);

        $response->assertStatus(422);
    }

    public function testSyncsCommonUserCipherWhenCommonUserStateEnabled()
    {
        $this->makeUser(['common_user_state' => 1]);

        $response = $this->postJson(self::ENDPOINT, [
            'email' => 'sysadmin@gmail.com',
            'currentPassword' => 'OldPass1',
            'newPassword' => 'NewPass2',
        ]);

        $response->assertStatus(200);

        $commonUser = DB::table('users')->where('email', 'sysadmin@gmail.com')->value('common_user');
        $this->assertNotNull($commonUser);
    }
}
