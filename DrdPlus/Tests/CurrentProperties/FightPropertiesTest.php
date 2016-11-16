<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\CurrentProperties\CombatActions;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\CurrentProperties\FightProperties;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Actions\CombatActionsWithWeaponTypeCompatibilityTable;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Tables;
use Granam\Tests\Tools\TestWithMockery;

class FightPropertiesTest extends TestWithMockery
{
    /**
     * @test
     */
    public function I_can_create_it()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeaponlike();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, true, true);

        $shieldCode = $this->createShieldCode();
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $fightProperties = new FightProperties(
            $this->createCurrentProperties($size, $strengthForMainHandOnly, $strengthForOffhandOnly),
            $this->createCombatActions($combatActionValues = ['foo']),
            $this->createSkills(),
            $this->createTables(
                $weaponlikeCode,
                $combatActionValues,
                $armourer
            ),
            $weaponlikeCode,
            $this->createWeaponlikeHolding(
                false, /* does not keep weapon by both hands now */
                true /* holds weapon by main hands now */
            ),
            false, // does not fight with two weapons now
            $shieldCode,
            false // enemy is not faster now
        );

        self::assertInstanceOf(FightProperties::class, $fightProperties);
    }

    /**
     * @return \Mockery\MockInterface|Armourer
     */
    private function createArmourer()
    {
        return $this->mockery(Armourer::class);
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $strength
     * @param Size $size
     * @param $canUseArmament
     * @param $canHoldItByOneHand
     * @return Armourer
     */
    private function addCanUseArmament(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        Strength $strength,
        Size $size,
        $canUseArmament,
        $canHoldItByOneHand
    )
    {
        $armourer->shouldReceive('canUseArmament')
            ->with($weaponlikeCode, $strength, $size)
            ->andReturn($canUseArmament);
        $armourer->shouldReceive('canHoldItByOneHand')
            ->with($weaponlikeCode)
            ->andReturn($canHoldItByOneHand);

        return $armourer;
    }

    /**
     * @param Size $size
     * @param Strength $strengthForMainHandOnly
     * @param Strength $strengthForOffhandOnly
     * @return \Mockery\MockInterface|CurrentProperties
     */
    private function createCurrentProperties(Size $size, Strength $strengthForMainHandOnly, Strength $strengthForOffhandOnly)
    {
        $currentProperties = $this->mockery(CurrentProperties::class);
        $currentProperties->shouldReceive('getSize')
            ->andReturn($size);
        $currentProperties->shouldReceive('getStrengthForMainHandOnly')
            ->andReturn($strengthForMainHandOnly);
        $currentProperties->shouldReceive('getStrengthForOffhandOnly')
            ->andReturn($strengthForOffhandOnly);

        return $currentProperties;
    }

    /**
     * @param array $values
     * @return \Mockery\MockInterface|CombatActions
     */
    private function createCombatActions(array $values)
    {
        $combatActions = $this->mockery(CombatActions::class);
        $combatActions->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($values));

        return $combatActions;
    }

    /**
     * @return \Mockery\MockInterface|Skills
     */
    private function createSkills()
    {
        return $this->mockery(Skills::class);
    }

    /**
     * @param WeaponlikeCode $weaponlikeCode
     * @param array $possibleActions
     * @param Armourer $armourer
     * @return \Mockery\MockInterface|Tables
     */
    private function createTables(
        WeaponlikeCode $weaponlikeCode,
        array $possibleActions,
        Armourer $armourer
    )
    {
        $tables = $this->mockery(Tables::class);
        $tables->shouldReceive('getCombatActionsWithWeaponTypeCompatibilityTable')
            ->andReturn($compatibilityTable = $this->mockery(CombatActionsWithWeaponTypeCompatibilityTable::class));
        $compatibilityTable->shouldReceive('getActionsPossibleWhenFightingWith')
            ->with($weaponlikeCode)
            ->andReturn($possibleActions);
        $tables->shouldReceive('getArmourer')
            ->andReturn($armourer);

        return $tables;
    }

    /**
     * @return \Mockery\MockInterface|WeaponlikeCode
     */
    private function createWeaponlike()
    {
        return $this->mockery(WeaponlikeCode::class);
    }

    /**
     * @param $holdsByTwoHands
     * @param $holdsByMainHand
     * @return \Mockery\MockInterface|ItemHoldingCode
     */
    private function createWeaponlikeHolding($holdsByTwoHands, $holdsByMainHand)
    {
        $itemHolding = $this->mockery(ItemHoldingCode::class);
        $itemHolding->shouldReceive('holdsByTwoHands')
            ->andReturn($holdsByTwoHands);
        $itemHolding->shouldReceive('holdsByOneHand')
            ->andReturn(!$holdsByTwoHands);
        $itemHolding->shouldReceive('holdsByMainHand')
            ->andReturn($holdsByMainHand);
        $itemHolding->shouldReceive('holdsByOffhand')
            ->andReturn(!$holdsByMainHand);

        return $itemHolding;
    }

    /**
     * @return \Mockery\MockInterface|ShieldCode
     */
    private function createShieldCode()
    {
        return $this->mockery(ShieldCode::class);
    }
}
