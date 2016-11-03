<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\CombatActions\CombatActionCode;
use DrdPlus\Codes\CombatActions\MeleeCombatActionCode;
use DrdPlus\Codes\CombatActions\RangedCombatActionCode;
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
     * @param bool $areCompatible
     * @return \Mockery\MockInterface|CombatActionsCompatibilityTable
     */
    private function createCombatActionsCompatibilityTable(array $expectedActionsToCombine, $areCompatible)
    {
        $expectedActionsToCombine = array_map(
            function ($expectedActionToCombine) {
                return (string)$expectedActionToCombine;
            },
            $expectedActionsToCombine
        );
        $combatActionsCompatibilityTable = $this->mockery(CombatActionsCompatibilityTable::class);
        $combatActionsCompatibilityTable->shouldReceive('canCombineTwoActions')
            ->with(\Mockery::type(CombatActionCode::class), \Mockery::type(CombatActionCode::class))
            ->andReturnUsing(function (CombatActionCode $someAction, CombatActionCode $anotherAction) use ($expectedActionsToCombine, $areCompatible) {
                self::assertTrue(
                    in_array($someAction->getValue(), $expectedActionsToCombine, true),
                    "Unexpected {$someAction}, expected one of " . implode(',', $expectedActionsToCombine)
                );
                self::assertTrue(
                    in_array($anotherAction->getValue(), $expectedActionsToCombine, true),
                    "Unexpected {$anotherAction}, expected one of " . implode(',', $expectedActionsToCombine)
                );

                return $areCompatible;
            });

        return $combatActionsCompatibilityTable;
    }

    /**
     * @test
     */
    public function I_can_get_codes()
    {
        $combatActions = new CombatActions(
            $expected = [
                CombatActionCode::MOVE,
                CombatActionCode::ATTACK_ON_DISABLED_OPPONENT,
                CombatActionCode::BLINDFOLD_FIGHT,
                CombatActionCode::SWAP_WEAPONS,
            ],
            $this->createCombatActionsCompatibilityTable($expected, true)
        );
        foreach ($combatActions->getCombatActionCodes() as $combatActionCode) {
            $collected[] = $combatActionCode->getValue();
        }
        sort($expected);
        sort($collected);
        self::assertSame($expected, $collected);
    }

    /**
     * @test
     */
    public function I_can_iterate_through_them()
    {
        $combatActions = new CombatActions(
            $expected = [
                CombatActionCode::MOVE,
                CombatActionCode::ATTACK_ON_DISABLED_OPPONENT,
                CombatActionCode::BLINDFOLD_FIGHT,
                CombatActionCode::SWAP_WEAPONS,
            ],
            $this->createCombatActionsCompatibilityTable($expected, true)
        );
        $collected = [];
        foreach ($combatActions as $combatActionCode) {
            $collected[] = $combatActionCode->getValue();
        }
        sort($expected);
        sort($collected);
        self::assertSame($expected, $collected);
    }

    /**
     * @test
     */
    public function I_can_count_them()
    {
        $combatActions = new CombatActions([], $this->createCombatActionsCompatibilityTable([], true));
        self::assertCount(0, $combatActions);

        $combatActions = new CombatActions(
            $values = [
                CombatActionCode::MOVE,
                CombatActionCode::ATTACK_ON_DISABLED_OPPONENT,
                CombatActionCode::BLINDFOLD_FIGHT,
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertCount(3, $combatActions);
    }

    /**
     * @test
     */
    public function I_can_get_list_of_actions_as_string()
    {
        $combatActions = new CombatActions([], $this->createCombatActionsCompatibilityTable([], true));
        self::assertSame('', (string)$combatActions);

        $combatActions = new CombatActions(
            $values = [
                CombatActionCode::ATTACK_ON_DISABLED_OPPONENT,
                CombatActionCode::BLINDFOLD_FIGHT,
                CombatActionCode::LAYING,
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(implode(',', $values), (string)$combatActions);
    }

    /**
     * @test
     */
    public function I_can_get_fight_number_modifier()
    {
        $combatActions = new CombatActions(
            $values = [
                CombatActionCode::ATTACK_ON_DISABLED_OPPONENT,
                CombatActionCode::BLINDFOLD_FIGHT,
                CombatActionCode::ATTACKED_FROM_BEHIND,
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(0, $combatActions->getFightNumberModifier());

        $combatActions = new CombatActions(
            $values = [
                CombatActionCode::LAYING, // -4
                CombatActionCode::SITTING_OR_ON_KNEELS, // -2
                CombatActionCode::PUTTING_ON_ARMOR, // -4
                CombatActionCode::PUTTING_ON_ARMOR_WITH_HELP, // -2
                CombatActionCode::HELPING_TO_PUT_ON_ARMOR, // -2
                CombatActionCode::CONCENTRATION_ON_DEFENSE, // +2
                RangedCombatActionCode::AIMED_SHOT, // -2
                CombatActionCode::SWAP_WEAPONS, // -2
                CombatActionCode::HANDOVER_ITEM, // -2
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(-18, $combatActions->getFightNumberModifier());
    }

    /**
     * @test
     */
    public function I_can_get_attack_number_modifier()
    {
        $combatActions = new CombatActions(
            $genericValues = [
                CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM, // +2
                CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM, // +2
                CombatActionCode::LAYING, // -4
                CombatActionCode::SITTING_OR_ON_KNEELS, // -2
                CombatActionCode::BLINDFOLD_FIGHT, // -6
                CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY, // -1
            ],
            $this->createCombatActionsCompatibilityTable($genericValues, true)
        );
        self::assertSame(-9, $combatActions->getAttackNumberModifier());

        $meleeValues = $genericValues;
        $meleeValues[] = MeleeCombatActionCode::HEADLESS_ATTACK; // +2
        $meleeValues[] = MeleeCombatActionCode::PRESSURE; // +2
        $combatActions = new CombatActions(
            $meleeValues,
            $this->createCombatActionsCompatibilityTable($meleeValues, true)
        );
        self::assertSame(-5, $combatActions->getAttackNumberModifier());

        $rangedValues = $genericValues;
        $rangedValues[] = RangedCombatActionCode::AIMED_SHOT; // +0 (zero rounds of aiming as default value)
        $combatActions = new CombatActions(
            $rangedValues,
            $this->createCombatActionsCompatibilityTable($rangedValues, true)
        );
        self::assertSame(-9, $combatActions->getAttackNumberModifier());

        $combatActions = new CombatActions(
            $rangedValues,
            $this->createCombatActionsCompatibilityTable($rangedValues, true),
            2 // aiming for 2 rounds
        );
        self::assertSame(-7 /* +2 for max aiming */, $combatActions->getAttackNumberModifier());

        $combatActions = new CombatActions(
            $rangedValues,
            $this->createCombatActionsCompatibilityTable($rangedValues, true),
            11 // aiming for 11 rounds (up to 3 rounds should be counted)
        );
        self::assertSame(-6 /* +3 for max aiming */, $combatActions->getAttackNumberModifier());
    }

    /**
     * @test
     */
    public function I_can_get_base_of_wounds_modifier()
    {
        $combatActions = new CombatActions(
            $values = [
                MeleeCombatActionCode::HEADLESS_ATTACK, // +2
                MeleeCombatActionCode::FLAT_ATTACK, // -6
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(-4, $combatActions->getBaseOfWoundsModifier(false /* not crushing weapon */));

        $combatActions = new CombatActions(
            $values = [
                MeleeCombatActionCode::HEADLESS_ATTACK, // +2
                MeleeCombatActionCode::FLAT_ATTACK, // 0 because of crushing weapon
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(2, $combatActions->getBaseOfWoundsModifier(true /* crushing weapon */));
    }

    /**
     * @test
     */
    public function I_can_get_defense_number_modifier()
    {
        $combatActions = new CombatActions(
            $values = [
                MeleeCombatActionCode::HEADLESS_ATTACK, // -5
                CombatActionCode::CONCENTRATION_ON_DEFENSE, // +2
                MeleeCombatActionCode::PRESSURE, // -1
                MeleeCombatActionCode::RETREAT, // +1
                MeleeCombatActionCode::LAYING, // -4
                MeleeCombatActionCode::SITTING_OR_ON_KNEELS, // -2
                MeleeCombatActionCode::PUTTING_ON_ARMOR, // -4
                MeleeCombatActionCode::PUTTING_ON_ARMOR_WITH_HELP, // -4
                MeleeCombatActionCode::ATTACKED_FROM_BEHIND, // -4
                MeleeCombatActionCode::BLINDFOLD_FIGHT, // -10
                MeleeCombatActionCode::FIGHT_IN_REDUCED_VISIBILITY, // -2
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(-33, $combatActions->getDefenseNumberModifier());
    }

    /**
     * @test
     */
    public function I_can_get_defense_number_modifier_against_faster_opponent()
    {
        $combatActions = new CombatActions(
            $values = [
                MeleeCombatActionCode::HEADLESS_ATTACK, // -5
                CombatActionCode::CONCENTRATION_ON_DEFENSE, // +2
                MeleeCombatActionCode::PRESSURE, // -1
                MeleeCombatActionCode::RETREAT, // +1
                MeleeCombatActionCode::LAYING, // -4
                MeleeCombatActionCode::SITTING_OR_ON_KNEELS, // -2
                MeleeCombatActionCode::PUTTING_ON_ARMOR, // -4
                MeleeCombatActionCode::PUTTING_ON_ARMOR_WITH_HELP, // -4
                MeleeCombatActionCode::ATTACKED_FROM_BEHIND, // -4
                MeleeCombatActionCode::BLINDFOLD_FIGHT, // -10
                MeleeCombatActionCode::FIGHT_IN_REDUCED_VISIBILITY, // -2
                MeleeCombatActionCode::RUN, // -4
                MeleeCombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM, // -4
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(-41, $combatActions->getDefenseNumberModifierAgainstFasterOpponent());
    }


    /**
     * @test
     */
    public function I_can_get_speed_modifier()
    {
        $combatActions = new CombatActions(
            $values = [
                CombatActionCode::MOVE, // +8
                CombatActionCode::RUN, // +22
            ],
            $this->createCombatActionsCompatibilityTable($values, true)
        );
        self::assertSame(30, $combatActions->getSpeedModifier());
    }
}