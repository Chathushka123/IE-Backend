<?php

namespace Tests\Unit\Repositories;

use App\Exceptions\GeneralException;
use App\Http\Repositories\CustomerRepository;

class CustomerRepositoryTest extends RepositoryTestCase
{
    public function testCreatesWithAUniqueCode()
    {
        $this->actingAsTestUser();

        $model = CustomerRepository::createRec(['description' => 'Decathlon', 'code' => 'DCL']);

        $this->assertDatabaseHas('customers', ['id' => $model->id, 'code' => 'DCL']);
    }

    public function testRejectsAMissingCode()
    {
        $this->actingAsTestUser();

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CustomerRepository::createRec(['description' => 'Decathlon']);
    }

    public function testRejectsADuplicateCodeOnCreate()
    {
        $this->actingAsTestUser();

        CustomerRepository::createRec(['description' => 'Decathlon', 'code' => 'DCL']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CustomerRepository::createRec(['description' => 'H&M', 'code' => 'DCL']);
    }

    public function testRejectsAnUpdateThatDuplicatesAnotherRecordsCode()
    {
        $this->actingAsTestUser();

        $model = CustomerRepository::createRec(['description' => 'Decathlon', 'code' => 'DCL']);
        CustomerRepository::createRec(['description' => 'H&M', 'code' => 'HM']);

        $this->expectException(GeneralException::class);
        $this->expectExceptionMessageMatches('/code/i');

        CustomerRepository::updateRec($model->id, [
            'description' => 'Decathlon',
            'code' => 'HM',
            'updated_at' => $model->updated_at,
        ]);
    }
}
