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
use DrdPlus\Properties\Combat\Attack;
use DrdPlus\Properties\Combat\AttackNumber;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Actions\CombatActionsWithWeaponTypeCompatibilityTable;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Armaments\Weapons\MissingWeaponSkillTable;
use DrdPlus\Tables\Measurements\Distance\Distance;
use DrdPlus\Tables\Tables;
use Granam\Tests\Tools\TestWithMockery;

class FightPropertiesTest extends TestWithMockery
{
    /**
     * @test
     */
    public function I_can_use_it()
    {
        $armourer = $this->createArmourer();

        $weaponlikeCode = $this->createWeaponlike(false /* not a shield */, false /* not shooting */);
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
        $skills = $this->createSkills();
        $missingWeaponSkillsTable = new MissingWeaponSkillTable();
        $combatActions = $this->createCombatActions($combatActionValues = ['foo']);

        // attack number
        $this->addAttackNumberMalusByStrengthWithWeaponlike(
            $armourer,
            $weaponlikeCode,
            $strengthForMainHandOnly,
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

        // fight number
        $this->addFightNumberMalusByStrengthWithWeaponOrShield(
            $armourer,
            $weaponlikeCode,
            $strengthForMainHandOnly,
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

        $fightProperties = new FightProperties(
            $currentProperties,
            $combatActions,
            $skills,
            $wornBodyArmor,
            $wornHelm,
            $professionCode = ProfessionCode::getIt(ProfessionCode::FIGHTER),
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

        $this->I_can_get_expected_attack_number(
            $fightProperties,
            $currentProperties,
            $attackNumberMalusByStrengthWithWeapon,
            $attackNumberMalusBySkillsWithWeapon,
            $offensiveness,
            $combatActionsAttackNumberModifier
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
        self::assertSame($fightNumber, $fightProperties->getFightNumber(), 'Same instance should be given');
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
     * @param bool $isShooting
     * @return \Mockery\MockInterface|WeaponlikeCode
     */
    private function createWeaponlike($isShield = false, $isShooting = false)
    {
        $weaponlikeCode = $this->mockery(WeaponlikeCode::class);
        $weaponlikeCode->shouldReceive('isShield')
            ->andReturn($isShield);
        $weaponlikeCode->shouldReceive('isShootingWeapon')
            ->andReturn($isShooting);

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
     * @param WeaponlikeCode $expectedWeaponlikeCode
     * @param Strength $expectedStrength
     * @param int $attackNumberMalus
     */
    private function addAttackNumberMalusByStrengthWithWeaponlike(
        Armourer $armourer,
        WeaponlikeCode $expectedWeaponlikeCode,
        Strength $expectedStrength,
        $attackNumberMalus
    )
    {
        $armourer->shouldReceive('getAttackNumberMalusByStrengthWithWeaponlike')
            ->with($expectedWeaponlikeCode, $expectedStrength)
            ->andReturn($attackNumberMalus);
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
        $armourer->shouldReceive('getFightNumberMalusByStrengthWithWeaponOrShield')
            ->with($expectedWeaponlikeCode, $expectedStrength)
            ->andReturn($fightNumberMalusByStrengthWithWeapon);
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