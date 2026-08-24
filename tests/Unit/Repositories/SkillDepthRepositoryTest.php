<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\SkillDepthRepository;

class SkillDepthRepositoryTest extends RepositoryTestCase
{
    public function testClassifiesAsNoDataWhenNoHistoricalDataAtAll()
    {
        $this->assertEquals('no_data', SkillDepthRepository::classifyOperation(0, false));
    }

    public function testClassifiesAsZeroCoverageWhenDataExistsButNoActiveQualified()
    {
        $this->assertEquals('zero_coverage', SkillDepthRepository::classifyOperation(0, true));
    }

    public function testClassifiesAsSinglePointOfFailureWhenExactlyOneQualified()
    {
        $this->assertEquals('single_point_of_failure', SkillDepthRepository::classifyOperation(1, true));
    }

    public function testClassifiesAsThinBenchWhenExactlyTwoQualified()
    {
        $this->assertEquals('thin_bench', SkillDepthRepository::classifyOperation(2, true));
    }

    public function testClassifiesAsHealthyWhenThreeOrMoreQualified()
    {
        $this->assertEquals('healthy', SkillDepthRepository::classifyOperation(3, true));
        $this->assertEquals('healthy', SkillDepthRepository::classifyOperation(10, true));
    }
}
