<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\ArmamentCode;
use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\CurrentProperties\CombatActions;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\CurrentProperties\FightProperties;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Body\Height;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Actions\CombatActionsWithWeaponTypeCompatibilityTable;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Armaments\Weapons\MissingWeaponSkillTable;
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

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, true);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, true);

        $fightProperties = new FightProperties(
            $this->createCurrentProperties($strength, $size, $strengthForMainHandOnly, $strengthForOffhandOnly),
            $this->createCombatActions($combatActionValues = ['foo']),
            $this->createSkills(),
            $bodyArmorCode,
            $helmCode,
            ProfessionCode::getIt(ProfessionCode::RANGER),
            $this->createTables($weaponlikeCode, $combatActionValues, $armourer),
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
     * @param ArmamentCode $armamentCode
     * @param Strength $strength
     * @param Size $size
     * @param $canUseArmament
     * @param $canHoldItByOneHand
     * @return Armourer
     */
    private function addCanUseArmament(
        Armourer $armourer,
        ArmamentCode $armamentCode,
        Strength $strength,
        Size $size,
        $canUseArmament,
        $canHoldItByOneHand = null
    )
    {
        $armourer->shouldReceive('canUseArmament')
            ->with($armamentCode, $strength, $size)
            ->andReturn($canUseArmament);
        if ($canHoldItByOneHand !== null) {
            $armourer->shouldReceive('canHoldItByOneHand')
                ->with($armamentCode)
                ->andReturn($canHoldItByOneHand);
        }

        return $armourer;
    }

    /**
     * @param Strength $strength
     * @param Size $size
     * @param Strength $strengthForMainHandOnly
     * @param Strength $strengthForOffhandOnly
     * @return \Mockery\MockInterface|CurrentProperties
     */
    private function createCurrentProperties(
        Strength $strength,
        Size $size,
        Strength $strengthForMainHandOnly,
        Strength $strengthForOffhandOnly
    )
    {
        $currentProperties = $this->mockery(CurrentProperties::class);
        $currentProperties->shouldReceive('getStrength')
            ->andReturn($strength);
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
     * @param int $fightNumberModifier
     * @return \Mockery\MockInterface|CombatActions
     */
    private function createCombatActions(array $values, $fightNumberModifier = null)
    {
        $combatActions = $this->mockery(CombatActions::class);
        $combatActions->shouldReceive('getIterator')
            ->andReturn(new \ArrayIterator($values));
        if ($fightNumberModifier !== null) {
            $combatActions->shouldReceive('getFightNumberModifier')
                ->andReturn($fightNumberModifier);
        }

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
     * @param MissingWeaponSkillTable $missingWeaponSkillsTable
     * @return \Mockery\MockInterface|Tables
     */
    private function createTables(
        WeaponlikeCode $weaponlikeCode,
        array $possibleActions,
        Armourer $armourer,
        MissingWeaponSkillTable $missingWeaponSkillsTable = null
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
        if ($missingWeaponSkillsTable) {
            $tables->shouldReceive('getMissingWeaponSkillTable')
                ->andReturn($missingWeaponSkillsTable);
        }

        return $tables;
    }

    /**
     * @param bool $isShield
     * @return \Mockery\MockInterface|WeaponlikeCode
     */
    private function createWeaponlike($isShield = false)
    {
        $weaponlikeCode = $this->mockery(WeaponlikeCode::class);
        $weaponlikeCode->shouldReceive('isShield')
            ->andReturn($isShield);

        return $weaponlikeCode;
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

    /**
     * @test
     */
    public function I_can_get_fight_number()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeaponlike();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, true, true);

        $strengthForOffhandOnly = Strength::getIt(234);
        $shieldCode = $this->createShieldCode();
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(987);
        $currentProperties = $this->createCurrentProperties($strength, $size, $strengthForMainHandOnly, $strengthForOffhandOnly);
        $this->addAgility($currentProperties, Agility::getIt(321));
        $this->addHeight($currentProperties, $this->createHeight(255));

        $wornBodyArmor = BodyArmorCode::getIt(BodyArmorCode::HOBNAILED_ARMOR);
        $this->addCanUseArmament($armourer, $wornBodyArmor, $strength, $size, true);
        $wornHelm = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $wornHelm, $strength, $size, true);

        $this->addFightNumberMalusByStrengthWithWeaponOrShield($armourer, $weaponlikeCode, $strengthForMainHandOnly, 45);
        $this->addFightNumberMalusByStrengthWithWeaponOrShield($armourer, $shieldCode, $strengthForOffhandOnly, 56);

        $skills = $this->createSkills();
        $this->addFightNumberMalusFromProtectivesBySkills($skills, $armourer, $wornBodyArmor, 11, $wornHelm, 22, $shieldCode, 33);
        $missingWeaponSkillsTable = new MissingWeaponSkillTable();
        $fightsWithTwoWeapons = false;
        $this->addFightNumberMalusFromWeaponlikeBySkills($skills, $weaponlikeCode, $missingWeaponSkillsTable, $fightsWithTwoWeapons, 44);
        $this->addFightNumberBonusByWeaponlikeLength($armourer, $weaponlikeCode, 55, $shieldCode, 66);

        $fightProperties = new FightProperties(
            $currentProperties,
            $this->createCombatActions($combatActionValues = ['foo'], 777 /* fight number modifier */),
            $skills,
            $wornBodyArmor,
            $wornHelm,
            ProfessionCode::getIt(ProfessionCode::FIGHTER),
            $this->createTables($weaponlikeCode, $combatActionValues, $armourer, $missingWeaponSkillsTable),
            $weaponlikeCode,
            $this->createWeaponlikeHolding(
                false, /* does not keep weapon by both hands now */
                true /* holds weapon by main hands now */
            ),
            $fightsWithTwoWeapons,
            $shieldCode,
            false // enemy is not faster now
        );

        $fightNumber = $fightProperties->getFightNumber();
        self::assertInstanceOf(FightNumber::class, $fightNumber);
    }

    private function addAgility(\Mockery\MockInterface $mock, Agility $agility)
    {
        $mock->shouldReceive('getAgility')
            ->andReturn($agility);
    }

    private function addHeight(\Mockery\MockInterface $mock, Height $height)
    {
        $mock->shouldReceive('getHeight')
            ->andReturn($height);
    }

    /**
     * @param $value
     * @return Height
     */
    private function createHeight($value)
    {
        $height = $this->mockery(Height::class);
        $height->shouldReceive('getValue')
            ->andReturn($value);

        return $height;
    }

    /**
     * @see FightProperties::getFightNumberMalusByStrength
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $expectedWeaponlikeCode
     * @param Strength $expectedStrength
     * @param int $fightNumberMalus
     */
    private function addFightNumberMalusByStrengthWithWeaponOrShield(
        Armourer $armourer,
        WeaponlikeCode $expectedWeaponlikeCode,
        Strength $expectedStrength,
        $fightNumberMalus
    )
    {
        $armourer->shouldReceive('getFightNumberMalusByStrengthWithWeaponOrShield')
            ->with($expectedWeaponlikeCode, $expectedStrength)
            ->andReturn($fightNumberMalus);
    }

    /**
     * @see FightProperties::getFightNumberMalusFromProtectivesBySkills
     * @param Skills|\Mockery\MockInterface $skills
     * @param Armourer $armourer
     * @param BodyArmorCode $bodyArmorCode
     * @param int $malusToFightNumberWithBodyArmor
     * @param HelmCode $helmCode
     * @param $malusToFightNumberWithHelm
     * @param ShieldCode $shieldCode
     * @param $malusToFightNumberWithShield
     */
    private function addFightNumberMalusFromProtectivesBySkills(
        Skills $skills,
        Armourer $armourer,
        BodyArmorCode $bodyArmorCode,
        $malusToFightNumberWithBodyArmor,
        HelmCode $helmCode,
        $malusToFightNumberWithHelm,
        ShieldCode $shieldCode,
        $malusToFightNumberWithShield
    )
    {
        $skills->shouldReceive('getMalusToFightNumberWithProtective')
            ->with($bodyArmorCode, $armourer)
            ->andReturn($malusToFightNumberWithBodyArmor);
        $skills->shouldReceive('getMalusToFightNumberWithProtective')
            ->with($helmCode, $armourer)
            ->andReturn($malusToFightNumberWithHelm);
        $skills->shouldReceive('getMalusToFightNumberWithProtective')
            ->with($shieldCode, $armourer)
            ->andReturn($malusToFightNumberWithShield);
    }

    /**
     * @see FightProperties::getFightNumberMalusFromWeaponlikesBySkills
     * @param Skills|\Mockery\MockInterface $skills
     * @param WeaponlikeCode $weaponlikeCode
     * @param MissingWeaponSkillTable $missingWeaponSkillTable
     * @param bool $fightsWithTwoWeapons ,
     * @param int $malusFromWeaponlike
     */
    private function addFightNumberMalusFromWeaponlikeBySkills(
        Skills $skills,
        WeaponlikeCode $weaponlikeCode,
        MissingWeaponSkillTable $missingWeaponSkillTable,
        $fightsWithTwoWeapons,
        $malusFromWeaponlike
    )
    {
        $skills->shouldReceive('getMalusToFightNumberWithWeaponlike')
            ->with($weaponlikeCode, $missingWeaponSkillTable, $fightsWithTwoWeapons)
            ->andReturn($malusFromWeaponlike);
    }

    /**
     * @see FightProperties::getFightNumberBonusByWeaponlikeLength
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param int $lengthOfWeaponlike
     * @param ShieldCode $shieldCode
     * @param int $lengthOfShield
     */
    private function addFightNumberBonusByWeaponlikeLength(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        $lengthOfWeaponlike,
        ShieldCode $shieldCode,
        $lengthOfShield
    )
    {
        $armourer->shouldReceive('getLengthOfWeaponOrShield')
            ->with($weaponlikeCode)
            ->andReturn($lengthOfWeaponlike);
        $armourer->shouldReceive('getLengthOfWeaponOrShield')
            ->with($shieldCode)
            ->andReturn($lengthOfShield);
    }

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     * @dataProvider provideArmamentsBearing
     * @param bool $weaponIsBearable
     * @param bool $shieldIsBearable
     * @param bool $armorIsBearable
     * @param bool $helmIsBearable
     */
    public function I_can_not_create_it_with_unbearable_weapon_and_shield(
        $weaponIsBearable,
        $shieldIsBearable,
        $armorIsBearable,
        $helmIsBearable
    )
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeaponlike();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, $weaponIsBearable, true);

        $shieldCode = $this->createShieldCode();
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, $shieldIsBearable, true);

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, $armorIsBearable);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, $helmIsBearable);

        new FightProperties(
            $this->createCurrentProperties($strength, $size, $strengthForMainHandOnly, $strengthForOffhandOnly),
            $this->createCombatActions($combatActionValues = ['foo']),
            $this->createSkills(),
            $bodyArmorCode,
            $helmCode,
            ProfessionCode::getIt(ProfessionCode::RANGER),
            $this->createTables($weaponlikeCode, $combatActionValues, $armourer),
            $weaponlikeCode,
            $this->createWeaponlikeHolding(
                false, /* does not keep weapon by both hands now */
                true /* holds weapon by main hands now */
            ),
            false, // does not fight with two weapons now
            $shieldCode,
            false // enemy is not faster now
        );
    }

    public function provideArmamentsBearing()
    {
        return [
            [false, true, true, true],
            [true, false, true, true],
            [true, true, false, true],
            [true, true, true, false],
        ];
    }
}