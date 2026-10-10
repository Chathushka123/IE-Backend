<?php

namespace Tests\Feature;

use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class UserSearchTest extends TestCase
{
    use RefreshDatabase;

    // Fixtures go through the query builder — see SkillMatrixInsightsTest::authHeaders()
    // for why (User's boot() hook needs Auth::user(); the factory_user row makes
    // ResolveFactoryScope resolve a non-empty FactoryContext).

    private function makeFactory(): int
    {
        $unique = strtoupper(substr(md5(uniqid('', true)), 0, 6));

        return DB::table('factories')->insertGetId([
            'name' => 'Plant '.$unique,
            'code' => 'PL'.$unique,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeUser(string $name, string $email, array $factoryIds, bool $isActive = true): int
    {
        $userId = DB::table('users')->insertGetId([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('Password1'),
            'is_active' => $isActive,
            'common_user_state' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ($factoryIds as $factoryId) {
            DB::table('factory_user')->insert([
                'factory_id' => $factoryId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $userId;
    }

    private function authHeaders(array $factoryIds): array
    {
        $userId = $this->makeUser('Search Caller', 'caller-'.uniqid().'@example.com', $factoryIds);
        $token = JWTAuth::fromUser(User::find($userId));

        return [
            'Authorization' => "Bearer $token",
            'X-Factory-Ids' => implode(',', $factoryIds),
        ];
    }

    private function search(array $headers, array $query)
    {
        return $this->getJson('/api/v1/users/search?'.http_build_query($query), $headers);
    }

    public function testFindsActiveUsersByNameOrEmailAndReturnsOnlySafeFields()
    {
        $factory = $this->makeFactory();
        $headers = $this->authHeaders([$factory]);
        $this->makeUser('Nimali Perera', 'nimali@example.com', [$factory]);
        $this->makeUser('Kasun Silva', 'kperera.ie@example.com', [$factory]);

        $response = $this->search($headers, ['term' => 'perera']);

        $response->assertStatus(200);
        $this->assertEqualsCanonicalizing(
            ['Nimali Perera', 'Kasun Silva'],
            collect($response->json('data'))->pluck('name')->all()
        );
        foreach ($response->json('data') as $row) {
            $this->assertEqualsCanonicalizing(['id', 'name', 'email'], array_keys($row));
        }
    }

    public function testExcludesInactiveUsers()
    {
        $factory = $this->makeFactory();
        $headers = $this->authHeaders([$factory]);
        $this->makeUser('Ruwan Active', 'ruwan.a@example.com', [$factory]);
        $this->makeUser('Ruwan Inactive', 'ruwan.i@example.com', [$factory], false);

        $names = collect($this->search($headers, ['term' => 'ruwan'])->json('data'))->pluck('name')->all();

        $this->assertEquals(['Ruwan Active'], $names);
    }

    public function testNarrowsToTheGivenFactory()
    {
        $factoryA = $this->makeFactory();
        $factoryB = $this->makeFactory();
        $headers = $this->authHeaders([$factoryA, $factoryB]);
        $this->makeUser('Dilan In A', 'dilan.a@example.com', [$factoryA]);
        $this->makeUser('Dilan In B', 'dilan.b@example.com', [$factoryB]);

        $names = collect($this->search($headers, ['term' => 'dilan', 'factory_id' => $factoryB])->json('data'))
            ->pluck('name')->all();

        $this->assertEquals(['Dilan In B'], $names);
    }

    public function testRejectsATermShorterThanThreeCharacters()
    {
        $headers = $this->authHeaders([$this->makeFactory()]);

        $this->search($headers, ['term' => 'ab'])->assertStatus(422);
    }

    public function testTreatsLikeWildcardsLiterally()
    {
        $factory = $this->makeFactory();
        $headers = $this->authHeaders([$factory]);
        $this->makeUser('Plain Name', 'plain@example.com', [$factory]);

        $this->assertCount(0, $this->search($headers, ['term' => '%%%'])->json('data'));
    }

    public function testRejectsUnauthenticatedRequests()
    {
        $this->getJson('/api/v1/users/search?term=abc')->assertStatus(401);
    }
}
