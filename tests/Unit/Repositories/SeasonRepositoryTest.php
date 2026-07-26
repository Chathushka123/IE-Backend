<?php

namespace Tests\Unit\Repositories;

use App\Customer;
use App\Exceptions\GeneralException;
use App\Http\Repositories\SeasonRepository;

class SeasonRepositoryTest extends RepositoryTestCase
{
    private function makeCustomer(string $description = 'Decathlon'): int
    {
        $code = strtoupper(substr(md5($description.uniqid()), 0, 6));

        return Customer::create(['description' => $description, 'code' => $code])->id;
    }

    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();
        $customerId = $this->makeCustomer();

        $model = SeasonRepository::createRec([
            'name' => 'Summer 27',
            'code' => 'SU27',
            'customer_id' => $customerId,
        ]);

        $this->assertDatabaseHas('seasons', ['id' => $model->id, 'code' => 'SU27']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();
        $customerId = $this->makeCustomer();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SeasonRepository::createRec(['name' => 'Summer 27', 'customer_id' => $customerId]);
    }

    public function testRejectsADuplicateCodeForTheSameCustomer()
    {
        $this->actingAsTestUser();
        $customerId = $this->makeCustomer();

        SeasonRepository::createRec(['name' => 'Summer 27', 'code' => 'SU27', 'customer_id' => $customerId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SeasonRepository::createRec(['name' => 'Summer Restock', 'code' => 'SU27', 'customer_id' => $customerId]);
    }

    /** The uniqueness is scoped per customer, so a different customer can reuse the same season code. */
    public function testAllowsTheSameCodeForADifferentCustomer()
    {
        $this->actingAsTestUser();
        $customerOneId = $this->makeCustomer('Decathlon');
        $customerTwoId = $this->makeCustomer('H&M');

        SeasonRepository::createRec(['name' => 'Summer 27', 'code' => 'SU27', 'customer_id' => $customerOneId]);
        $second = SeasonRepository::createRec(['name' => 'Summer 27', 'code' => 'SU27', 'customer_id' => $customerTwoId]);

        $this->assertDatabaseHas('seasons', ['id' => $second->id, 'code' => 'SU27', 'customer_id' => $customerTwoId]);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherSeasonsCodeForTheSameCustomer()
    {
        $this->actingAsTestUser();
        $customerId = $this->makeCustomer();

        $model = SeasonRepository::createRec(['name' => 'Summer 27', 'code' => 'SU27', 'customer_id' => $customerId]);
        SeasonRepository::createRec(['name' => 'Winter 27', 'code' => 'WI27', 'customer_id' => $customerId]);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        SeasonRepository::updateRec($model->id, [
            'name' => 'Summer 27',
            'code' => 'WI27',
            'customer_id' => $customerId,
            'updated_at' => $model->updated_at,
        ]);
    }
}
