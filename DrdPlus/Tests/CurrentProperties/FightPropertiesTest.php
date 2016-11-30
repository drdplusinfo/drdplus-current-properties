<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\ArmamentCode;
use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\Codes\WoundTypeCode;
use DrdPlus\CurrentProperties\CombatActions;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\CurrentProperties\FightProperties;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Body\Height;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Properties\Combat\Attack;
use DrdPlus\Properties\Combat\AttackNumber;
use DrdPlus\Properties\Combat\DefenseNumber;
use DrdPlus\Properties\Combat\DefenseNumberAgainstShooting;
use DrdPlus\Properties\Combat\EncounterRange;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Properties\Combat\LoadingInRounds;
use DrdPlus\Properties\Combat\MaximalRange;
use DrdPlus\Properties\Derived\Speed;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Actions\CombatActionsWithWeaponTypeCompatibilityTable;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Armaments\Partials\WeaponlikeTable;
use DrdPlus\Tables\Armaments\Shields\MissingShieldSkillTable;
use DrdPlus\Tables\Armaments\Weapons\MissingWeaponSkillTable;
use DrdPlus\Tables\Measurements\Distance\Distance;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus;
use DrdPlus\Tables\Tables;
use Granam\Tests\Tools\TestWithMockery;

class FightPropertiesTest extends TestWithMockery
{
    /**
     * @test
     * @dataProvider provideUsageCombinations
     * @param bool $enemyIsFasterThanYou
     * @param bool $holdWeaponByTwoHands
     * @param bool $weaponIsTwoHandedOnly
     */
    public function I_can_use_it($enemyIsFasterThanYou, $holdWeaponByTwoHands, $weaponIsTwoHandedOnly)
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeapon(false /* not shooting */);
        $strengthForMainHandOnly = Strength::getIt(123);
        $strengthForWeapon = $holdWeaponByTwoHands && !$weaponIsTwoHandedOnly
            ? $strengthForMainHandOnly->add(2)
            : $strengthForMainHandOnly;
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForWeapon, $size, true, true, true, $weaponIsTwoHandedOnly);

        $strengthForOffhandOnly = Strength::getIt(234);
        $shieldCode = $holdWeaponByTwoHands
            ? ShieldCode::getIt(ShieldCode::WITHOUT_SHIELD)
            : ShieldCode::getIt(ShieldCode::HEAVY_SHIELD);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(987);
        $currentProperties = $this->createCurrentProperties(
            $strength,
            $size,
            $strengthForMainHandOnly,
            $strengthForOffhandOnly,
            $speed = $this->mockery(Speed::class)
        );
        $this->addAgility($currentProperties, Agility::getIt(321));
        $this->addHeight($currentProperties, $this->createHeight(255));

        $wornBodyArmor = BodyArmorCode::getIt(BodyArmorCode::HOBNAILED_ARMOR);
        $this->addCanUseArmament($armourer, $wornBodyArmor, $strength, $size, true);
        $wornHelm = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $wornHelm, $strength, $size, true);
        $skills = $this->createSkills();
        $missingWeaponSkillsTable = new MissingWeaponSkillTable();
        $combatActions = $this->createCombatActions($combatActionValues = ['foo']);

        // attack number
        $this->addAttackNumberMalusByStrengthWithWeaponlike(
            $armourer,
            $weaponlikeCode,
            $strengthForWeapon,
            $attackNumberMalusByStrengthWithWeapon = 442271
        );
        $this->addMalusToAttackNumberFromSkillsWithWeaponlike(
            $skills,
            $weaponlikeCode,
            $missingWeaponSkillsTable,
            false, // does not fight with two weapons
            $attackNumberMalusBySkillsWithWeapon = 3450
        );
        $this->addOffensiveness($armourer, $weaponlikeCode, $offensiveness = 12123);
        $this->addCombatActionsAttackNumber($combatActions, $combatActionsAttackNumberModifier = 8171);

        // base of wounds
        $this->addWeaponBaseOfWounds($armourer, $weaponlikeCode, $strengthForWeapon, $weaponBaseOfWounds = 91967);
        $this->addBaseOfWoundsMalusFromSkills(
            $skills,
            $weaponlikeCode,
            $missingWeaponSkillsTable,
            false, // does not fight with two weapons
            $baseOfWoundsMalusFromSkills = -12607
        );
        $this->addBaseOfWoundsBonusByHolding($armourer, $weaponlikeCode, $holdWeaponByTwoHands, $baseOfWoundsBonusForHolding = 748);
        $missingShieldSkillsTable = new MissingShieldSkillTable();
        $tables = $this->createTables($weaponlikeCode, $combatActionValues, $armourer, $missingWeaponSkillsTable, $missingShieldSkillsTable);
        $this->addWoundsTypeOf($tables, $weaponlikeCode, WoundTypeCode::CUT);
        $this->addBaseOfWoundsModifierFromActions($combatActions, false /* weapon is not crushing */, $baseOfWoundsModifierFromActions = -1357);

        // fight number
        $this->addFightNumberMalusByStrengthWithWeaponOrShield(
            $armourer,
            $weaponlikeCode,
            $strengthForWeapon,
            $fightNumberMalusByStrengthWithWeapon = 45
        );
        $this->addFightNumberMalusByStrengthWithWeaponOrShield(
            $armourer,
            $shieldCode,
            $strengthForOffhandOnly,
            $fightNumberMalusByStrengthWithShield = 56
        );
        $this->addFightNumberMalusFromProtectivesBySkills(
            $skills,
            $armourer,
            $wornBodyArmor,
            $fightNumberMalusFromBodyArmor = 11,
            $wornHelm,
            $fightNumberMalusFromHelm = 22,
            $shieldCode,
            $fightNumberMalusFromShield = 33
        );
        $fightsWithTwoWeapons = false;
        $this->addFightNumberMalusFromWeaponlikeBySkills(
            $skills,
            $weaponlikeCode,
            $missingWeaponSkillsTable,
            $fightsWithTwoWeapons,
            $fightNumberMalusFromWeapon = 44
        );
        $this->addFightNumberBonusByWeaponlikeLength($armourer, $weaponlikeCode, $weaponLength = 55, $shieldCode, $shieldLength = 66);
        $this->addCombatActionsFightNumber($combatActions, $combatActionsFightNumberModifier = 777);

        // encounter range
        $this->addEncounterRange($armourer, $weaponlikeCode, $strengthForWeapon, $speed, $encounterRangeValue = 1824);

        // defense number
        $this->addDefenseNumberFromActions($combatActions, $enemyIsFasterThanYou, $defenseNumberModifierFromActions = -155157);
        $this->addDefenseNumberMalusByStrength($armourer, $weaponlikeCode, $strengthForWeapon, $defenseNumberMalusByStrengthWithWeapon = -518415);
        $this->addCoverOf($armourer, $weaponlikeCode, $coverOfWeapon = 6511);
        $this->addSkillsMalusToCoverWithWeapon(
            $skills,
            $weaponlikeCode,
            $missingWeaponSkillsTable,
            $fightsWithTwoWeapons,
            $skillsMalusToCoverWithWeapon = -551514
        );
        $this->addDefenseNumberMalusByStrength($armourer, $shieldCode, $strengthForOffhandOnly, $defenseNumberMalusByStrengthWithShield = -1640);
        $this->addCoverOf($armourer, $shieldCode, $coverOfShield = 712479);
        $this->addSkillsMalusToCoverWithShield(
            $skills,
            $missingShieldSkillsTable,
            $skillsMalusToCoverWithShield = -71810482
        );

        // moved distance
        $this->addActionsSpeedModifier($combatActions, $combatActionsSpeedModifier = 0);

        $fightProperties = new FightProperties(
            $currentProperties,
            $combatActions,
            $skills,
            $wornBodyArmor,
            $wornHelm,
            $professionCode = ProfessionCode::getIt(ProfessionCode::FIGHTER),
            $tables,
            $weaponlikeCode,
            $holdWeaponByTwoHands
                ? ItemHoldingCode::getIt(ItemHoldingCode::TWO_HANDS)
                : ItemHoldingCode::getIt(ItemHoldingCode::MAIN_HAND),
            $fightsWithTwoWeapons,
            $shieldCode,
            $enemyIsFasterThanYou
        );

        $this->I_can_get_expected_fight_number(
            $fightProperties,
            $professionCode,
            $currentProperties,
            $fightNumberMalusByStrengthWithWeapon,
            $fightNumberMalusByStrengthWithShield,
            $fightNumberMalusFromWeapon,
            $fightNumberMalusFromBodyArmor,
            $fightNumberMalusFromHelm,
            $fightNumberMalusFromShield,
            $weaponLength,
            $shieldLength,
            $combatActionsFightNumberModifier
        );

        $this->I_can_get_expected_attack_number(
            $fightProperties,
            $currentProperties,
            $attackNumberMalusByStrengthWithWeapon,
            $attackNumberMalusBySkillsWithWeapon,
            $offensiveness,
            $combatActionsAttackNumberModifier
        );

        $this->I_can_get_expected_base_of_wounds(
            $fightProperties,
            $weaponBaseOfWounds,
            $baseOfWoundsMalusFromSkills,
            $baseOfWoundsBonusForHolding,
            $baseOfWoundsModifierFromActions
        );

        $this->I_can_get_expected_loading_in_rounds($fightProperties, $weaponlikeCode);

        $this->I_can_get_expected_encounter_range($fightProperties, $encounterRangeValue);

        $this->I_can_get_expected_maximal_range($fightProperties, $weaponlikeCode);

        $this->I_can_get_defense_number(
            $fightProperties,
            $currentProperties,
            $defenseNumberModifierFromActions,
            $defenseNumberMalusByStrengthWithWeapon,
            $coverOfWeapon,
            $skillsMalusToCoverWithWeapon,
            $defenseNumberMalusByStrengthWithShield,
            $coverOfShield,
            $skillsMalusToCoverWithShield,
            $size
        );

        $this->I_can_get_moved_distance($fightProperties, $combatActionsSpeedModifier);
    }

    /**
     * @param FightProperties $fightProperties
     * @param ProfessionCode $professionCode
     * @param CurrentProperties $currentProperties
     * @param int $fightNumberMalusFromStrengthForWeapon
     * @param int $fightNumberMalusFromStrengthForShield
     * @param int $fightNumberMalusFromWeapon
     * @param int $fightNumberMalusFromBodyArmor
     * @param int $fightNumberMalusFromHelm
     * @param int $fightNumberMalusFromShield
     * @param int $weaponLength
     * @param int $shieldLength
     * @param int $combatActionsFightNumberModifier
     */
    private function I_can_get_expected_fight_number(
        FightProperties $fightProperties,
        ProfessionCode $professionCode,
        CurrentProperties $currentProperties,
        $fightNumberMalusFromStrengthForWeapon,
        $fightNumberMalusFromStrengthForShield,
        $fightNumberMalusFromWeapon,
        $fightNumberMalusFromBodyArmor,
        $fightNumberMalusFromHelm,
        $fightNumberMalusFromShield,
        $weaponLength,
        $shieldLength,
        $combatActionsFightNumberModifier
    )
    {
        $fightNumber = $fightProperties->getFightNumber();
        self::assertInstanceOf(FightNumber::class, $fightNumber);
        self::assertSame($fightNumber, $fightProperties->getFightNumber(), 'Expected same instances');
        $expectedFightNumber = (new FightNumber($professionCode, $currentProperties, $currentProperties->getHeight()))
            ->add(
                $fightNumberMalusFromStrengthForWeapon
                + $fightNumberMalusFromStrengthForShield
                + $fightNumberMalusFromWeapon
                + $fightNumberMalusFromBodyArmor
                + $fightNumberMalusFromHelm
                + $fightNumberMalusFromShield
                + max($weaponLength, $shieldLength)
                + $combatActionsFightNumberModifier
            );
        self::assertSame($expectedFightNumber->getValue(), $fightNumber->getValue());
    }

    public function provideUsageCombinations()
    {
        // enemy is faster than you, holds weapon by two hands, weapon is two handed only
        return [
            [true, false, false],
            [false, true, false],
            [false, true, true],
        ];
    }

    /**
     * @param FightProperties $fightProperties
     * @param CurrentProperties $currentProperties
     * @param int $attackNumberMalusByStrengthWithWeapon
     * @param int $attackNumberMalusBySkillsWithWeapon
     * @param int $offensiveness
     * @param int $combatActionsAttackNumberModifier
     */
    private function I_can_get_expected_attack_number(
        FightProperties $fightProperties,
        CurrentProperties $currentProperties,
        $attackNumberMalusByStrengthWithWeapon,
        $attackNumberMalusBySkillsWithWeapon,
        $offensiveness,
        $combatActionsAttackNumberModifier
    )
    {
        $attackNumber = $fightProperties->getAttackNumber($this->createDistance(0));
        self::assertInstanceOf(AttackNumber::class, $attackNumber);

        $expectedAttackNumber = AttackNumber::createFromAttack(new Attack($currentProperties->getAgility()))
            ->add(
                $attackNumberMalusByStrengthWithWeapon
                + $attackNumberMalusBySkillsWithWeapon
                + $offensiveness
                + $combatActionsAttackNumberModifier
            );
        self::assertSame($expectedAttackNumber->getValue(), $attackNumber->getValue());
    }

    /**
     * @param FightProperties $fightProperties
     * @param int $weaponBaseOfWounds
     * @param int $baseOfWoundsMalusFromSkills
     * @param int $baseOfWoundsBonusForHolding
     * @param int $baseOfWoundsModifierFromActions
     */
    private function I_can_get_expected_base_of_wounds(
        FightProperties $fightProperties,
        $weaponBaseOfWounds,
        $baseOfWoundsMalusFromSkills,
        $baseOfWoundsBonusForHolding,
        $baseOfWoundsModifierFromActions
    )
    {
        $baseOfWounds = $fightProperties->getBaseOfWounds();
        self::assertInstanceOf(WoundsBonus::class, $baseOfWounds);
        self::assertSame($baseOfWounds, $fightProperties->getBaseOfWounds(), 'Expected same instances');
        $expectedBaseOfWoundsValue = $weaponBaseOfWounds + $baseOfWoundsMalusFromSkills + $baseOfWoundsBonusForHolding
            + $baseOfWoundsModifierFromActions;
        self::assertSame($baseOfWounds->getValue(), $expectedBaseOfWoundsValue);
    }

    /**
     * @param FightProperties $fightProperties
     * @param WeaponlikeCode $weaponlikeCode
     */
    private function I_can_get_expected_loading_in_rounds(FightProperties $fightProperties, WeaponlikeCode $weaponlikeCode)
    {
        $loadingInRounds = $fightProperties->getLoadingInRounds();
        self::assertInstanceOf(LoadingInRounds::class, $loadingInRounds);
        self::assertNotInstanceOf(RangedWeaponCode::class, $weaponlikeCode);
        self::assertSame(0, $loadingInRounds->getValue());
        self::assertSame($loadingInRounds, $fightProperties->getLoadingInRounds(), 'Expected same instances');
    }

    /**
     * @param FightProperties $fightProperties
     * @param int $encounterRangeValue
     */
    private function I_can_get_expected_encounter_range(
        FightProperties $fightProperties,
        $encounterRangeValue
    )
    {
        $encounterRange = $fightProperties->getEncounterRange();
        self::assertInstanceOf(EncounterRange::class, $encounterRange);
        self::assertSame($encounterRangeValue, $encounterRange->getValue());
        self::assertSame($encounterRange, $fightProperties->getEncounterRange(), 'Expected same instances');
    }

    /**
     * @param FightProperties $fightProperties
     * @param WeaponlikeCode $weaponlikeCode
     */
    private function I_can_get_expected_maximal_range(FightProperties $fightProperties, WeaponlikeCode $weaponlikeCode)
    {
        self::assertNotInstanceOf(RangedWeaponCode::class, $weaponlikeCode);
        $expectedMaximalRange = MaximalRange::createForMeleeWeapon($fightProperties->getEncounterRange());
        $maximalRange = $fightProperties->getMaximalRange();
        self::assertInstanceOf(MaximalRange::class, $maximalRange);
        self::assertSame($expectedMaximalRange->getValue(), $maximalRange->getValue());
        self::assertSame($maximalRange, $fightProperties->getMaximalRange(), 'Expected same instances');
    }

    /**
     * @param FightProperties $fightProperties
     * @param CurrentProperties $currentProperties
     * @param int $defenseNumberModifierFromCombatActions
     * @param int $defenseNumberMalusByStrengthWithWeapon
     * @param int $coverOfWeapon
     * @param int $skillsMalusToCoverWithWeapon
     * @param int $defenseNumberMalusByStrengthWithShield
     * @param int $coverOfShield
     * @param int $skillsMalusToCoverWithShield
     * @param Size $size
     */
    private function I_can_get_defense_number(
        FightProperties $fightProperties,
        CurrentProperties $currentProperties,
        $defenseNumberModifierFromCombatActions,
        $defenseNumberMalusByStrengthWithWeapon,
        $coverOfWeapon,
        $skillsMalusToCoverWithWeapon,
        $defenseNumberMalusByStrengthWithShield,
        $coverOfShield,
        $skillsMalusToCoverWithShield,
        Size $size
    )
    {
        self::assertInstanceOf(DefenseNumber::class, $fightProperties->getDefenseNumber());
        $expectedDefenseNumber = (new DefenseNumber($currentProperties->getAgility()))
            ->add($defenseNumberModifierFromCombatActions);
        self::assertSame($expectedDefenseNumber->getValue(), $fightProperties->getDefenseNumber()->getValue());

        $expectedDefenseNumberWithWeapon = $expectedDefenseNumber->add(
            $defenseNumberMalusByStrengthWithWeapon
            + $coverOfWeapon
            + $skillsMalusToCoverWithWeapon
        );
        self::assertSame(
            $expectedDefenseNumberWithWeapon->getValue(),
            $fightProperties->getDefenseNumberWithWeaponlike()->getValue()
        );

        $expectedDefenseNumberWithShield = $expectedDefenseNumber->add(
            $defenseNumberMalusByStrengthWithShield
            + $coverOfShield
            + $skillsMalusToCoverWithShield
        );
        self::assertSame(
            $expectedDefenseNumberWithShield->getValue(),
            $fightProperties->getDefenseNumberWithShield()->getValue()
        );

        $expectedDefenseNumberAgainstShooting = new DefenseNumberAgainstShooting($expectedDefenseNumber, $size);
        $defenseNumberAgainstShooting = $fightProperties->getDefenseNumberAgainstShooting();
        self::assertInstanceOf(DefenseNumberAgainstShooting::class, $defenseNumberAgainstShooting);
        self::assertSame($defenseNumberAgainstShooting->getValue(), $expectedDefenseNumberAgainstShooting->getValue());

        $expectedDefenseNumberAgainstShootingWithShield = new DefenseNumberAgainstShooting($expectedDefenseNumberWithShield, $size);
        $defenseNumberAgainstShootingWithShield = $fightProperties->getDefenseNumberAgainstShootingWithShield();
        self::assertInstanceOf(DefenseNumberAgainstShooting::class, $defenseNumberAgainstShootingWithShield);
        self::assertSame($expectedDefenseNumberAgainstShootingWithShield->getValue(), $defenseNumberAgainstShootingWithShield->getValue());
    }

    /**
     * @param FightProperties $fightProperties
     * @param int $combatActionsSpeedModifier
     */
    private function I_can_get_moved_distance(FightProperties $fightProperties, $combatActionsSpeedModifier)
    {
        self::assertSame(0, $combatActionsSpeedModifier, 'Non-zero movement is not tested yet. Do it.');
        $movedDistance = $fightProperties->getMovedDistance();
        self::assertInstanceOf(Distance::class, $movedDistance);
        self::assertSame(0.0, $movedDistance->getValue());
        self::assertSame($movedDistance, $fightProperties->getMovedDistance(), 'Same instances expected');
    }

    /**
     * @param int $value
     * @return \Mockery\MockInterface|Distance
     */
    private function createDistance($value)
    {
        $distance = $this->mockery(Distance::class);
        $distance->shouldReceive('getValue')
            ->andReturn($value);

        return $distance;
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
     * @param Strength $expectedStrength
     * @param Size $size
     * @param bool $canUseArmament
     * @param bool $canHoldItByOneHand
     * @param bool $canHoldItByTwoHands
     * @param bool $isTwoHandedOnly
     * @return Armourer
     */
    private function addCanUseArmament(
        Armourer $armourer,
        ArmamentCode $armamentCode,
        Strength $expectedStrength,
        Size $size,
        $canUseArmament,
        $canHoldItByOneHand = true,
        $canHoldItByTwoHands = true,
        $isTwoHandedOnly = false
    )
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('canUseArmament')
            ->with($armamentCode, \Mockery::type(Strength::class), $size)
            ->andReturnUsing(
                function (ArmamentCode $armamentCode, Strength $strength, Size $size) use ($expectedStrength, $canUseArmament) {
                    self::assertSame(
                        $expectedStrength->getValue(),
                        $strength->getValue(),
                        "Expected strength {$expectedStrength->getValue()}, got {$strength->getValue()}"
                    );

                    return $canUseArmament;
                }
            );
        $armourer->shouldReceive('canHoldItByOneHand')
            ->with($armamentCode)
            ->andReturn($canHoldItByOneHand);
        $armourer->shouldReceive('canHoldItByTwoHands')
            ->with($armamentCode)
            ->andReturn($canHoldItByTwoHands);
        $armourer->shouldReceive('isTwoHandedOnly')
            ->with($armamentCode)
            ->andReturn($isTwoHandedOnly);

        return $armourer;
    }

    /**
     * @param Strength $strength
     * @param Size $size
     * @param Strength $strengthForMainHandOnly
     * @param Strength $strengthForOffhandOnly
     * @param Speed $speed
     * @return \Mockery\MockInterface|CurrentProperties
     */
    private function createCurrentProperties(
        Strength $strength,
        Size $size,
        Strength $strengthForMainHandOnly,
        Strength $strengthForOffhandOnly,
        Speed $speed = null
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
        if ($speed !== null) {
            $currentProperties->shouldReceive('getSpeed')
                ->andReturn($speed);
        }

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
     * @param MissingWeaponSkillTable $missingWeaponSkillsTable
     * @param MissingShieldSkillTable $missingShieldSkillsTable
     * @return \Mockery\MockInterface|Tables
     */
    private function createTables(
        WeaponlikeCode $weaponlikeCode,
        array $possibleActions,
        Armourer $armourer,
        MissingWeaponSkillTable $missingWeaponSkillsTable = null,
        MissingShieldSkillTable $missingShieldSkillsTable = null
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
        if ($missingShieldSkillsTable) {
            $tables->shouldReceive('getMissingShieldSkillTable')
                ->andReturn($missingShieldSkillsTable);
        }
        $tables->shouldDeferMissing();

        return $tables;
    }

    /**
     * @param Tables|\Mockery\MockInterface $tables
     * @param WeaponlikeCode $weaponlikeCode
     * @param string $woundType
     */
    private function addWoundsTypeOf(Tables $tables, WeaponlikeCode $weaponlikeCode, $woundType)
    {
        $tables->shouldReceive('getWeaponlikeTableByWeaponlikeCode')
            ->with($weaponlikeCode)
            ->andReturn($weaponlikeTable = $this->mockery(WeaponlikeTable::class));
        $weaponlikeTable->shouldReceive('getWoundsTypeOf')
            ->with($weaponlikeCode)
            ->andReturn($woundType);
    }

    /**
     * @param bool $isShooting
     * @return \Mockery\MockInterface|WeaponCode
     */
    private function createWeapon($isShooting = false)
    {
        $weaponlikeCode = $this->mockery(WeaponCode::class);
        $weaponlikeCode->shouldReceive('isShield')
            ->andReturn(false);
        $weaponlikeCode->shouldReceive('isShootingWeapon')
            ->andReturn($isShooting);

        return $weaponlikeCode;
    }

    /**
     * @param bool $holdsByTwoHands
     * @param bool $holdsByMainHand
     * @param bool $holdsByOffhand
     * @return \Mockery\MockInterface|ItemHoldingCode
     */
    private function createWeaponlikeHolding($holdsByTwoHands, $holdsByMainHand, $holdsByOffhand)
    {
        $itemHolding = $this->mockery(ItemHoldingCode::class);
        $itemHolding->shouldReceive('holdsByTwoHands')
            ->andReturn($holdsByTwoHands);
        $itemHolding->shouldReceive('holdsByOneHand')
            ->andReturn(!$holdsByTwoHands);
        $itemHolding->shouldReceive('holdsByMainHand')
            ->andReturn($holdsByMainHand);
        $itemHolding->shouldReceive('holdsByOffhand')
            ->andReturn($holdsByOffhand);

        return $itemHolding;
    }

    /**
     * @return \Mockery\MockInterface|ShieldCode
     */
    private function createShieldCode()
    {
        return $this->mockery(ShieldCode::class);
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
     * @see FightProperties::getAttackNumberModifier
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $expectedStrength
     * @param int $attackNumberMalus
     */
    private function addAttackNumberMalusByStrengthWithWeaponlike(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        Strength $expectedStrength,
        $attackNumberMalus
    )
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('getAttackNumberMalusByStrengthWithWeaponlike')
            ->with($weaponlikeCode, \Mockery::type(Strength::class))
            ->andReturnUsing(
                function (WeaponlikeCode $weaponlikeCode, Strength $strength) use ($attackNumberMalus, $expectedStrength) {
                    self::assertSame($expectedStrength->getValue(), $strength->getValue());

                    return $attackNumberMalus;
                }
            );
    }

    /**
     * @see FightProperties::getAttackNumberModifier
     * @param Skills|\Mockery\MockInterface $skills
     * @param WeaponlikeCode $expectedWeaponlikeCode
     * @param MissingWeaponSkillTable $missingWeaponSkillsTable
     * @param bool $fightsWithTwoWeapons
     * @param int $attackNumberMalus
     */
    private function addMalusToAttackNumberFromSkillsWithWeaponlike(
        Skills $skills,
        WeaponlikeCode $expectedWeaponlikeCode,
        MissingWeaponSkillTable $missingWeaponSkillsTable,
        $fightsWithTwoWeapons,
        $attackNumberMalus
    )
    {
        $skills->shouldReceive('getMalusToAttackNumberWithWeaponlike')
            ->with($expectedWeaponlikeCode, $missingWeaponSkillsTable, $fightsWithTwoWeapons)
            ->andReturn($attackNumberMalus);
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param $offensiveness
     */
    private function addOffensiveness(Armourer $armourer, WeaponlikeCode $weaponlikeCode, $offensiveness)
    {
        $armourer->shouldReceive('getOffensivenessOfWeaponlike')
            ->with($weaponlikeCode)
            ->andReturn($offensiveness);
    }

    /**
     * @param CombatActions|\Mockery\MockInterface $combatActions
     * @param $attackNumberModifier
     */
    private function addCombatActionsAttackNumber(CombatActions $combatActions, $attackNumberModifier)
    {
        $combatActions->shouldReceive('getAttackNumberModifier')
            ->andReturn($attackNumberModifier);
    }

    /**
     * @param CombatActions|\Mockery\MockInterface $combatActions
     * @param $attackNumberModifier
     */
    private function addCombatActionsFightNumber(CombatActions $combatActions, $attackNumberModifier)
    {
        $combatActions->shouldReceive('getFightNumberModifier')
            ->andReturn($attackNumberModifier);
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $expectedStrength
     * @param Speed $speed
     * @param $encounterRangeValue
     */
    private function addEncounterRange(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        Strength $expectedStrength,
        Speed $speed,
        $encounterRangeValue
    )
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('getEncounterRangeWithWeaponlike')
            ->with($weaponlikeCode, \Mockery::type(Strength::class), $speed)
            ->andReturnUsing(
                function (WeaponlikeCode $weaponlikeCode, Strength $strength, Speed $speed)
                use ($encounterRangeValue, $expectedStrength) {
                    self::assertSame($expectedStrength->getValue(), $strength->getValue());

                    return $encounterRangeValue;
                }
            );
    }

    /**
     * @param CombatActions|\Mockery\MockInterface $combatActions
     * @param bool $enemyIsFasterThanYou
     * @param int $defenseNumberModifier
     */
    private function addDefenseNumberFromActions(CombatActions $combatActions, $enemyIsFasterThanYou, $defenseNumberModifier)
    {
        $combatActions->shouldReceive($enemyIsFasterThanYou
            ? 'getDefenseNumberModifierAgainstFasterOpponent'
            : 'getDefenseNumberModifier'
        )
            ->andReturn($defenseNumberModifier);
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $expectedStrength
     * @param int $defenseNumberMalus
     */
    private function addDefenseNumberMalusByStrength(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        Strength $expectedStrength,
        $defenseNumberMalus
    )
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('getDefenseNumberMalusByStrengthWithWeaponOrShield')
            ->with($weaponlikeCode, \Mockery::type(Strength::class))
            ->andReturnUsing(
                function (WeaponlikeCode $weaponlikeCode, Strength $strength) use ($defenseNumberMalus, $expectedStrength) {
                    self::assertSame($expectedStrength->getValue(), $strength->getValue());

                    return $defenseNumberMalus;
                }
            );
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param int $coverOfWeapon
     */
    private function addCoverOf(Armourer $armourer, WeaponlikeCode $weaponlikeCode, $coverOfWeapon)
    {
        $armourer->shouldReceive('getCoverOfWeaponOrShield')
            ->with($weaponlikeCode)
            ->andReturn($coverOfWeapon);
    }

    /**
     * @param Skills|\Mockery\MockInterface $skills
     * @param WeaponlikeCode $weaponlikeCode
     * @param MissingWeaponSkillTable $missingWeaponSkillTable
     * @param bool $fightsWithTwoWeapons
     * @param int $skillsMalusToCoverWithWeapon
     */
    private function addSkillsMalusToCoverWithWeapon(
        Skills $skills,
        WeaponlikeCode $weaponlikeCode,
        MissingWeaponSkillTable $missingWeaponSkillTable,
        $fightsWithTwoWeapons,
        $skillsMalusToCoverWithWeapon
    )
    {
        $skills->shouldReceive('getMalusToCoverWithWeapon')
            ->with($weaponlikeCode, $missingWeaponSkillTable, $fightsWithTwoWeapons)
            ->andReturn($skillsMalusToCoverWithWeapon);
    }

    /**
     * @param Skills|\Mockery\MockInterface $skills
     * @param MissingShieldSkillTable $missingShieldSkillTable
     * @param int $skillsMalusToCoverWithShield
     */
    private function addSkillsMalusToCoverWithShield(
        Skills $skills,
        MissingShieldSkillTable $missingShieldSkillTable,
        $skillsMalusToCoverWithShield
    )
    {
        $skills->shouldReceive('getMalusToCoverWithShield')
            ->with($missingShieldSkillTable)
            ->andReturn($skillsMalusToCoverWithShield);
    }

    /**
     * @see FightProperties::getFightNumberMalusByStrength
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $expectedWeaponlikeCode
     * @param Strength $expectedStrength
     * @param int $fightNumberMalusByStrengthWithWeapon
     */
    private function addFightNumberMalusByStrengthWithWeaponOrShield(
        Armourer $armourer,
        WeaponlikeCode $expectedWeaponlikeCode,
        Strength $expectedStrength,
        $fightNumberMalusByStrengthWithWeapon
    )
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('getFightNumberMalusByStrengthWithWeaponOrShield')
            ->with($expectedWeaponlikeCode, \Mockery::type(Strength::class))
            ->andReturnUsing(
                function (WeaponlikeCode $expectedWeaponlikeCode, Strength $strength)
                use ($fightNumberMalusByStrengthWithWeapon, $expectedStrength) {
                    self::assertSame($expectedStrength->getValue(), $strength->getValue());

                    return $fightNumberMalusByStrengthWithWeapon;
                }
            );
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
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $expectedStrength
     * @param int $baseOfWounds
     */
    private function addWeaponBaseOfWounds(Armourer $armourer, WeaponlikeCode $weaponlikeCode, Strength $expectedStrength, $baseOfWounds)
    {
        /** @noinspection PhpUnusedParameterInspection */
        $armourer->shouldReceive('getBaseOfWoundsUsingWeaponlike')
            ->with($weaponlikeCode, \Mockery::type(Strength::class))
            ->andReturnUsing(
                function (WeaponlikeCode $weaponlikeCode, Strength $strength) use ($baseOfWounds, $expectedStrength) {
                    self::assertSame($expectedStrength->getValue(), $strength->getValue());

                    return $baseOfWounds;
                }
            );
    }

    /**
     * @param Skills|\Mockery\MockInterface $skills
     * @param WeaponlikeCode $weaponlikeCode
     * @param MissingWeaponSkillTable $missingWeaponSkillsTable
     * @param $fightsWithTwoWeapons
     * @param $baseOfWoundsMalusFromSkills
     */
    private function addBaseOfWoundsMalusFromSkills(
        Skills $skills,
        WeaponlikeCode $weaponlikeCode,
        MissingWeaponSkillTable $missingWeaponSkillsTable,
        $fightsWithTwoWeapons,
        $baseOfWoundsMalusFromSkills
    )
    {
        $skills->shouldReceive('getMalusToBaseOfWoundsWithWeaponlike')
            ->with($weaponlikeCode, $missingWeaponSkillsTable, $fightsWithTwoWeapons)
            ->andReturn($baseOfWoundsMalusFromSkills);
    }

    /**
     * @param Armourer|\Mockery\MockInterface $armourer
     * @param WeaponlikeCode $weaponlikeCode
     * @param $holdsByTwoHands
     * @param int $bonusFromHolding
     */
    private function addBaseOfWoundsBonusByHolding(
        Armourer $armourer,
        WeaponlikeCode $weaponlikeCode,
        $holdsByTwoHands,
        $bonusFromHolding
    )
    {
        $armourer->shouldReceive('getBaseOfWoundsBonusForHolding')
            ->with($weaponlikeCode, $holdsByTwoHands)
            ->andReturn($bonusFromHolding);
    }

    /**
     * @param CombatActions|\Mockery\MockInterface $combatActions
     * @param $weaponIsCrushing
     * @param int $baseOfWoundsModifierFromActions
     */
    private function addBaseOfWoundsModifierFromActions(CombatActions $combatActions, $weaponIsCrushing, $baseOfWoundsModifierFromActions)
    {
        $combatActions->shouldReceive('getBaseOfWoundsModifier')
            ->with($weaponIsCrushing)
            ->andReturn($baseOfWoundsModifierFromActions);
    }

    /**
     * @param CombatActions|\Mockery\MockInterface $combatActions
     * @param int $speedModifier
     */
    private function addActionsSpeedModifier(CombatActions $combatActions, $speedModifier)
    {
        $combatActions->shouldReceive('getSpeedModifier')
            ->andReturn($speedModifier);
    }

    // NEGATIVE TESTS

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

        $weaponlikeCode = $this->createWeapon();
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
                true, /* holds weapon by main hand */
                false /* does not hold weapon by offhand */
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

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @dataProvider provideWeaponOrShieldInvalidTwoHandsHolding
     * @param bool $fightsWithTwoWeapons
     * @param bool $holdsByTwoHands
     * @param bool $canHoldByOneHand
     */
    public function I_can_not_create_it_with_two_hands_holding_if_not_possible(
        $fightsWithTwoWeapons,
        $holdsByTwoHands,
        $canHoldByOneHand
    )
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeapon();
        $strengthForMainHandOnly = Strength::getIt(123);
        $strengthForWeapon = $holdsByTwoHands && $canHoldByOneHand
            ? $strengthForMainHandOnly->add(2)
            : $strengthForMainHandOnly;
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForWeapon, $size, true, $canHoldByOneHand, !$canHoldByOneHand);

        $shieldCode = ShieldCode::getIt(ShieldCode::WITHOUT_SHIELD);
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, true);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, true);

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
                $holdsByTwoHands,
                true, /* holds weapon by main hand */
                false /* does not hold weapon by offhand */
            ),
            $fightsWithTwoWeapons,
            $shieldCode,
            false // enemy is not faster now
        );
    }

    public function provideWeaponOrShieldInvalidTwoHandsHolding()
    {
        return [
            [true, true, false],
            [false, true, true],
        ];
    }

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByOneHand
     */
    public function I_can_not_create_it_with_one_hand_holding_if_not_possible()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeapon();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, true, false /* can not hold by one hand */);

        $shieldCode = ShieldCode::getIt(ShieldCode::WITHOUT_SHIELD);
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, true);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, true);

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
                false, // does not hold it by two hands
                true, /* holds weapon by main hand */
                false /* does not hold weapon by offhand */
            ),
            true, // fights with two weapons (does not affect this test)
            $shieldCode,
            false // enemy is not faster now
        );
    }

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\ImpossibleActionsWithCurrentWeaponlike
     * @expectedExceptionMessageRegExp ~foo~
     */
    public function I_can_not_create_it_with_weapon_incompatible_actions()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeapon();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, true, true);

        $shieldCode = ShieldCode::getIt(ShieldCode::WITHOUT_SHIELD);
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, true);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, true);

        new FightProperties(
            $this->createCurrentProperties($strength, $size, $strengthForMainHandOnly, $strengthForOffhandOnly),
            $this->createCombatActions($combatActionValues = ['foo']),
            $this->createSkills(),
            $bodyArmorCode,
            $helmCode,
            ProfessionCode::getIt(ProfessionCode::RANGER),
            $this->createTables($weaponlikeCode, ['bar'] /* different combat actions possible */, $armourer),
            $weaponlikeCode,
            $this->createWeaponlikeHolding(
                false, // does not hold it by two hands
                true, // holds weapon by main hand
                false /* does not hold weapon by offhand */
            ),
            true, // fights with two weapons (does not affect this test)
            $shieldCode,
            false // enemy is not faster now (does not affect this test)
        );
    }

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponOrShieldHolding
     */
    public function I_can_not_create_it_with_unknown_holding()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeapon();
        $strengthForMainHandOnly = Strength::getIt(123);
        $size = Size::getIt(456);
        $this->addCanUseArmament($armourer, $weaponlikeCode, $strengthForMainHandOnly, $size, true, true);

        $shieldCode = ShieldCode::getIt(ShieldCode::WITHOUT_SHIELD);
        $strengthForOffhandOnly = Strength::getIt(234);
        $this->addCanUseArmament($armourer, $shieldCode, $strengthForOffhandOnly, $size, true, true);

        $strength = Strength::getIt(698);
        $bodyArmorCode = BodyArmorCode::getIt(BodyArmorCode::WITHOUT_ARMOR);
        $this->addCanUseArmament($armourer, $bodyArmorCode, $strength, $size, true);
        $helmCode = HelmCode::getIt(HelmCode::WITHOUT_HELM);
        $this->addCanUseArmament($armourer, $helmCode, $strength, $size, true);

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
                false, // does not hold weapon by two hands
                false, // does not hold weapon by main hand
                false /* does not hold weapon by offhand */
            ),
            true, // fights with two weapons (does not affect this test)
            $shieldCode,
            false // enemy is not faster now (does not affect this test)
        );
    }

}