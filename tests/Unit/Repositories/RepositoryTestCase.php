<?php

namespace Tests\Unit\Repositories;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

abstract class RepositoryTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * Models' boot() creating/updating hooks read Auth::user(), so every
     * repository call in these tests needs an authenticated user first.
     * Inserted via the query builder (not User::create()) since User's own
     * boot() hook has the same Auth::user() dependency.
     */
    protected function actingAsTestUser(): User
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test User',
            'email' => 'test-user-'.uniqid().'@example.com',
            'password' => bcrypt('Password1'),
            'is_active' => true,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::find($userId);
        $this->actingAs($user);

        return $user;
    }
}
