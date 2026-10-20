<?php

namespace Tests\Unit\Repositories;

use App\Http\Repositories\SkillMatrixRepository;
use PHPUnit\Framework\TestCase;

class SkillMatrixOperatorOrderTest extends TestCase
{
    private function op(?string $factory, ?string $team, string $no): array
    {
        return ['factory_code' => $factory, 'team_code' => $team, 'employee_no' => $no];
    }

    public function testSortsByFactoryThenTeamThenEmployeeNo()
    {
        $operators = [
            $this->op('F2', 'A', 'EMP-00001'),
            $this->op('F1', 'B', 'EMP-00002'),
            $this->op('F1', 'A', 'EMP-00009'),
            $this->op('F1', 'A', 'EMP-00003'),
        ];

        usort($operators, [SkillMatrixRepository::class, 'compareOperators']);

        $this->assertSame(
            ['EMP-00003', 'EMP-00009', 'EMP-00002', 'EMP-00001'],
            array_column($operators, 'employee_no')
        );
    }

    public function testUncodedFactoryAndTeamSortAfterCodedOnes()
    {
        $operators = [
            $this->op(null, null, 'EMP-00001'),
            $this->op('F1', null, 'EMP-00002'),
            $this->op('F1', 'A', 'EMP-00003'),
        ];

        usort($operators, [SkillMatrixRepository::class, 'compareOperators']);

        $this->assertSame(['EMP-00003', 'EMP-00002', 'EMP-00001'], array_column($operators, 'employee_no'));
    }

    public function testNaturalOrderingKeepsNumericCodesInSequence()
    {
        $operators = [$this->op('F1', 'L10', 'EMP-00001'), $this->op('F1', 'L2', 'EMP-00002')];

        usort($operators, [SkillMatrixRepository::class, 'compareOperators']);

        $this->assertSame(['L2', 'L10'], array_column($operators, 'team_code'));
    }
}
