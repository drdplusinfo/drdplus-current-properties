<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\CombatActions\CombatActionCode;
use DrdPlus\Tables\Actions\CombatActionsCompatibilityTable;
use Granam\Tests\Tools\TestWithMockery;

class CombatActionsTest extends TestWithMockery
{
    /**
     * @test
     */
    public function I_can_use_it()
    {
        $combatActions = new CombatActions(
            $inputActions = [
                CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
            ],
            $this->createCombatActionsCompatibilityTable($inputActions, true /* compatible */)
        );
        self::assertCount(1, $combatActions);
        self::assertSame($inputActions, $combatActions->getCombatActionCodes());
    }

    /**
     * @param array $expectedActionsToCombine
     * @param bool $compatible
     * @return \Mockery\MockInterface|CombatActionsCompatibilityTable
     */
    private function createCombatActionsCompatibilityTable(array $expectedActionsToCombine, $compatible)
    {
        $combatActionsCompatibilityTable = $this->mockery(CombatActionsCompatibilityTable::class);
        $combatActionsCompatibilityTable->shouldReceive('canCombineTwoActions')
            ->with(\Mockery::type(CombatActionCode::class), \Mockery::type(CombatActionCode::class))
            ->andReturnUsing(function (CombatActionCode $someAction, CombatActionCode $anotherAction) use ($expectedActionsToCombine, $compatible) {
                self::assertTrue(in_array($someAction, $expectedActionsToCombine, true));
                self::assertTrue(in_array($anotherAction, $expectedActionsToCombine, true));

                return $compatible;
            });

        return $combatActionsCompatibilityTable;
    }
}