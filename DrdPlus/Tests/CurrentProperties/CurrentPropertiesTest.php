<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\Properties\RemarkableSenseCode;
use DrdPlus\Codes\RaceCode;
use DrdPlus\Codes\SubRaceCode;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\Health\Health;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Charisma;
use DrdPlus\Properties\Base\Intelligence;
use DrdPlus\Properties\Base\Knack;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Base\Will;
use DrdPlus\Properties\Body\Age;
use DrdPlus\Properties\Body\Height;
use DrdPlus\Properties\Body\HeightInCm;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Properties\Body\WeightInKg;
use DrdPlus\Properties\Derived\Beauty;
use DrdPlus\Properties\Derived\Dangerousness;
use DrdPlus\Properties\Derived\Dignity;
use DrdPlus\Properties\Derived\Endurance;
use DrdPlus\Properties\Derived\FatigueBoundary;
use DrdPlus\Properties\Derived\Senses;
use DrdPlus\Properties\Derived\Speed;
use DrdPlus\Properties\Derived\Toughness;
use DrdPlus\Properties\Derived\WoundBoundary;
use DrdPlus\PropertiesByLevels\PropertiesByLevels;
use DrdPlus\Races\Humans\CommonHuman;
use DrdPlus\Races\Race;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Measurements\Weight\Weight;
use DrdPlus\Tables\Measurements\Weight\WeightTable;
use DrdPlus\Tables\Races\RacesTable;
use DrdPlus\Tables\Tables;
use Granam\Tests\Tools\TestWithMockery;

class CurrentPropertiesTest extends TestWithMockery
{
    /**
     * @test
     */
    public function I_can_use_it()
    {
        // strength
        $propertiesByLevels = $this->createPropertiesByLevels();
        $health = $this->createHealth();
        $health->shouldReceive('getStrengthMalusFromAfflictions')
            ->andReturn($strengthMalusFromAfflictions = 'foo');
        $propertiesByLevels->shouldReceive('getStrength')
            ->andReturn($baseStrength = $this->mockery(Strength::class));
        $baseStrength->shouldReceive('add')
            ->with($strengthMalusFromAfflictions)
            ->andReturn($strengthWithoutMalusFromLoad = $this->mockery(Strength::class));
        $tables = $this->createTables();
        $tables->shouldReceive('getWeightTable')->andReturn($weightTable = $this->mockery(WeightTable::class));
        $cargoWeight = $this->createCargoWeight();
        $weightTable->shouldReceive('getMalusFromLoad')
            ->with($strengthWithoutMalusFromLoad, $cargoWeight)
            ->andReturn($malusFromLoad = 112233);
        $strengthWithoutMalusFromLoad->shouldReceive('add')
            ->with($malusFromLoad)
            ->andReturn($strength = $this->createStrength(2233441));
        $strength->shouldReceive('sub')->with(2)->andReturn($strengthForOffhand = $this->mockery(Strength::class));

        // agility
        $propertiesByLevels->shouldReceive('getAgility')->andReturn($agilityWithoutMaluses = $this->mockery(Agility::class));
        $bodyArmorCode = $this->createBodyArmorCode();
        $helmCode = $this->createHelmCode();
        $tables->shouldReceive('getArmourer')->andReturn($armourer = $this->mockery(Armourer::class));
        $size = $this->mockery(Size::class);
        $armourer->shouldReceive('canUseArmament')->with($bodyArmorCode, $strength, $size)->andReturn(true);
        $armourer->shouldReceive('canUseArmament')->with($helmCode, $strength, $size)->andReturn(true);
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(123);
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')->with($helmCode, $strength, $size)->andReturn(456);
        $health->shouldReceive('getAgilityMalusFromAfflictions')->andReturn(789);
        $agilityWithoutMaluses->shouldReceive('add')
            ->with(123 + 456 + 789 + $malusFromLoad)
            ->andReturn($agility = $this->createAgility(456));

        // knack
        $propertiesByLevels->shouldReceive('getKnack')->andReturn($baseKnack = Knack::getIt(667788));
        $health->shouldReceive('getKnackMalusFromAfflictions')
            ->andReturn($knackMalusFromAfflictions = -3344);
        $expectedKnack = $baseKnack->add($knackMalusFromAfflictions)->add($malusFromLoad);

        // will
        $propertiesByLevels->shouldReceive('getWill')->andReturn($baseWill = Will::getIt(88965));
        $health->shouldReceive('getWillMalusFromAfflictions')
            ->andReturn($willMalusFromAfflictions = -7845);
        $expectedWill = $baseWill->add($willMalusFromAfflictions);

        // intelligence
        $propertiesByLevels->shouldReceive('getIntelligence')->andReturn($baseIntelligence = Intelligence::getIt(789456));
        $health->shouldReceive('getIntelligenceMalusFromAfflictions')
            ->andReturn($intelligenceMalusFromAfflictions = -556623);
        $expectedIntelligence = $baseIntelligence->add($intelligenceMalusFromAfflictions);

        // charisma
        $propertiesByLevels->shouldReceive('getCharisma')->andReturn($baseCharisma = Charisma::getIt(556655));
        $health->shouldReceive('getCharismaMalusFromAfflictions')
            ->andReturn($charismaMalusFromAfflictions = -6666674);
        $expectedCharisma = $baseCharisma->add($charismaMalusFromAfflictions);

        $propertiesByLevels->shouldReceive('getWeightInKg')->andReturn($weightInKg = $this->mockery(WeightInKg::class));
        $propertiesByLevels->shouldReceive('getHeightInCm')->andReturn($heightInCm = $this->mockery(HeightInCm::class));
        $propertiesByLevels->shouldReceive('getHeight')->andReturn($height = $this->createHeight(123789));
        $propertiesByLevels->shouldReceive('getAge')->andReturn($age = $this->mockery(Age::class));
        $propertiesByLevels->shouldReceive('getToughness')->andReturn($toughness = $this->mockery(Toughness::class));
        $propertiesByLevels->shouldReceive('getEndurance')->andReturn($endurance = $this->mockery(Endurance::class));
        $propertiesByLevels->shouldReceive('getSize')->andReturn($size);
        $commonHuman = $this->createRace(RaceCode::getIt(RaceCode::HUMAN), SubRaceCode::getIt(SubRaceCode::COMMON));
        $tables->shouldReceive('getRacesTable')->andReturn($this->createRacesTable($commonHuman, 3344551));
        $propertiesByLevels->shouldReceive('getWoundBoundary')->andReturn($expectedWoundBoundary = $this->mockery(WoundBoundary::class));
        $health->shouldReceive('getSignificantMalusFromPains')->with($expectedWoundBoundary)->andReturn($significantMalusFromPains = 11399);
        $propertiesByLevels->shouldReceive('getFatigueBoundary')->andReturn($expectedFatigueBoundary = $this->mockery(FatigueBoundary::class));

        $currentProperties = new CurrentProperties(
            $propertiesByLevels,
            $health,
            $commonHuman,
            $bodyArmorCode,
            $helmCode,
            $cargoWeight,
            $tables
        );

        $this->I_can_get_current_properties(
            $tables,
            $currentProperties,
            $commonHuman,
            $baseStrength,
            $strength,
            $strengthForOffhand,
            $agility,
            $expectedKnack,
            $expectedWill,
            $expectedIntelligence,
            $expectedCharisma,
            $weightInKg,
            $heightInCm,
            $height,
            $age,
            $toughness,
            $endurance,
            $size,
            $expectedWoundBoundary,
            $expectedFatigueBoundary,
            $significantMalusFromPains
        );
    }

    /**
     * @param Tables $tables
     * @param CurrentProperties $currentProperties
     * @param Race|\Mockery\MockInterface $race
     * @param Strength $baseStrength
     * @param Strength $expectedStrength
     * @param Strength $strengthForOffhand
     * @param Agility $agility
     * @param Knack $expectedKnack
     * @param Will $expectedWill
     * @param Intelligence $expectedIntelligence
     * @param Charisma $expectedCharisma
     * @param WeightInKg $weightInKg
     * @param HeightInCm $heightInCm
     * @param Height $height
     * @param Size $size
     * @param Age $age
     * @param Toughness $toughness
     * @param Endurance $endurance
     * @param WoundBoundary $expectedWoundBoundary
     * @param FatigueBoundary $expectedFatigueBoundary
     * @param int $significantMalusFromPains
     */
    private function I_can_get_current_properties(
        Tables $tables,
        CurrentProperties $currentProperties,
        Race $race,
        Strength $baseStrength,
        Strength $expectedStrength,
        Strength $strengthForOffhand,
        Agility $agility,
        Knack $expectedKnack,
        Will $expectedWill,
        Intelligence $expectedIntelligence,
        Charisma $expectedCharisma,
        WeightInKg $weightInKg,
        HeightInCm $heightInCm,
        Height $height,
        Age $age,
        Toughness $toughness,
        Endurance $endurance,
        Size $size,
        WoundBoundary $expectedWoundBoundary,
        FatigueBoundary $expectedFatigueBoundary,
        $significantMalusFromPains
    )
    {
        // base properties
        self::assertSame($baseStrength, $currentProperties->getBodyStrength());
        self::assertSame($expectedStrength, $currentProperties->getStrength());
        self::assertSame($expectedStrength, $currentProperties->getStrengthOfMainHand());
        self::assertSame($strengthForOffhand, $currentProperties->getStrengthOfOffhand());
        self::assertSame($agility, $currentProperties->getAgility());
        self::assertInstanceOf(Knack::class, $currentProperties->getKnack());
        self::assertSame($expectedKnack->getValue(), $currentProperties->getKnack()->getValue());
        self::assertInstanceOf(Will::class, $currentProperties->getWill());
        self::assertSame($expectedWill->getValue(), $currentProperties->getWill()->getValue());
        self::assertInstanceOf(Intelligence::class, $currentProperties->getIntelligence());
        self::assertSame($expectedIntelligence->getValue(), $currentProperties->getIntelligence()->getValue());
        self::assertInstanceOf(Charisma::class, $currentProperties->getCharisma());
        self::assertSame($expectedCharisma->getValue(), $currentProperties->getCharisma()->getValue());

        self::assertSame($weightInKg, $currentProperties->getWeightInKg());
        self::assertSame($heightInCm, $currentProperties->getHeightInCm());
        self::assertSame($height, $currentProperties->getHeight());
        self::assertSame($age, $currentProperties->getAge());
        self::assertSame($toughness, $currentProperties->getToughness());
        self::assertSame($endurance, $currentProperties->getEndurance());
        self::assertSame($size, $currentProperties->getSize());
        self::assertSame($expectedWoundBoundary, $currentProperties->getWoundBoundary());
        self::assertSame($expectedFatigueBoundary, $currentProperties->getFatigueBoundary());

        $expectedSpeed = Speed::getIt($expectedStrength, $agility, $height);
        self::assertInstanceOf(Speed::class, $currentProperties->getSpeed());
        self::assertSame($expectedSpeed->getValue(), $currentProperties->getSpeed()->getValue());

        $expectedBeauty = Beauty::getIt($agility, $currentProperties->getKnack(), $currentProperties->getCharisma());
        self::assertInstanceOf(Beauty::class, $currentProperties->getBeauty());
        self::assertSame($expectedBeauty->getValue(), $currentProperties->getBeauty()->getValue());

        $baseSenses = Senses::getIt($expectedKnack, $race->getRaceCode(), $race->getSubraceCode(), $tables);
        $expectedSenses = $baseSenses->add($significantMalusFromPains);
        self::assertInstanceOf(Senses::class, $currentProperties->getSenses());
        self::assertSame($expectedSenses->getValue(), $currentProperties->getSenses()->getValue());
        $race->shouldReceive('getRemarkableSense')
            ->with($tables)
            ->andReturn(RemarkableSenseCode::getIt(RemarkableSenseCode::HEARING));
        self::assertSame(
            $expectedSenses->getValue(),
            $currentProperties->getSenses(RemarkableSenseCode::getIt(RemarkableSenseCode::SIGHT))->getValue(),
            'Should have senses untouched by currently used remarkable sense'
        );
        self::assertSame(
            $expectedSenses->getValue() + 1,
            $currentProperties->getSenses(RemarkableSenseCode::getIt(RemarkableSenseCode::HEARING))->getValue(),
            'Should have senses increased by currently used remarkable sense'
        );

        $expectedDangerousness = Dangerousness::getIt($expectedStrength, $expectedWill, $expectedCharisma);
        self::assertInstanceOf(Dangerousness::class, $currentProperties->getDangerousness());
        self::assertSame($expectedDangerousness->getValue(), $currentProperties->getDangerousness()->getValue());

        $expectedDignity = Dignity::getIt($expectedIntelligence, $expectedWill, $expectedCharisma);
        self::assertInstanceOf(Dignity::class, $currentProperties->getDignity());
        self::assertSame($expectedDignity->getValue(), $currentProperties->getDignity()->getValue());
    }

    /**
     * @param RaceCode $raceCode
     * @param SubRaceCode $subRaceCode
     * @return Race|\Mockery\MockInterface
     */
    private function createRace(RaceCode $raceCode, SubRaceCode $subRaceCode)
    {
        $race = $this->mockery(Race::class);
        $race->shouldReceive('getRaceCode')
            ->andReturn($raceCode);
        $race->shouldReceive('getSubraceCode')
            ->andReturn($subRaceCode);

        return $race;
    }

    /**
     * @param Race $race
     * @param int $raceSensesValue
     * @return \Mockery\MockInterface|RacesTable
     */
    private function createRacesTable(Race $race, $raceSensesValue)
    {
        $racesTable = $this->mockery(RacesTable::class);
        $racesTable->shouldReceive('getSenses')
            ->with($race->getRaceCode(), $race->getSubraceCode())
            ->andReturn($raceSensesValue);

        return $racesTable;
    }

    /**
     * @param $value
     * @return \Mockery\MockInterface|Strength
     */
    private function createStrength($value)
    {
        $strength = $this->mockery(Strength::class);
        $strength->shouldReceive('getValue')
            ->andReturn($value);

        return $strength;
    }

    /**
     * @param $value
     * @return \Mockery\MockInterface|Agility
     */
    private function createAgility($value)
    {
        $agility = $this->mockery(Agility::class);
        $agility->shouldReceive('getValue')
            ->andReturn($value);

        return $agility;
    }

    /**
     * @param $value
     * @return \Mockery\MockInterface|Height
     */
    private function createHeight($value)
    {
        $height = $this->mockery(Height::class);
        $height->shouldReceive('getValue')
            ->andReturn($value);

        return $height;
    }

    /**
     * @return \Mockery\MockInterface|PropertiesByLevels
     */
    private function createPropertiesByLevels()
    {
        return $this->mockery(PropertiesByLevels::class);
    }

    /**
     * @return \Mockery\MockInterface|Health
     */
    private function createHealth()
    {
        return $this->mockery(Health::class);
    }

    /**
     * @return \Mockery\MockInterface|BodyArmorCode
     */
    private function createBodyArmorCode()
    {
        return $this->mockery(BodyArmorCode::class);
    }

    /**
     * @return \Mockery\MockInterface|HelmCode
     */
    private function createHelmCode()
    {
        return $this->mockery(HelmCode::class);
    }

    /**
     * @return \Mockery\MockInterface|Weight
     */
    private function createCargoWeight()
    {
        return $this->mockery(Weight::class);
    }

    /**
     * @return \Mockery\MockInterface|Tables
     */
    private function createTables()
    {
        return $this->mockery(Tables::class);
    }

    /**
     * @test
     * @expectedException \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     * @dataProvider provideNotBearableArmorOrHelm
     * @param $canUseArmor
     * @param $canUseHelm
     */
    public function I_can_not_create_it_with_unbearable_armor_and_helm($canUseArmor, $canUseHelm)
    {
        // strength
        $propertiesByLevels = $this->createPropertiesByLevels();
        $health = $this->createHealth();
        $health->shouldReceive('getStrengthMalusFromAfflictions')
            ->andReturn($strengthMalusFromAfflictions = 'foo');
        $propertiesByLevels->shouldReceive('getStrength')
            ->andReturn($baseStrength = $this->mockery(Strength::class));
        $baseStrength->shouldReceive('add')
            ->with($strengthMalusFromAfflictions)
            ->andReturn($strengthWithoutMalusFromLoad = $this->mockery(Strength::class));
        $tables = $this->createTables();
        $tables->shouldReceive('getWeightTable')->andReturn($weightTable = $this->mockery(WeightTable::class));
        $cargoWeight = $this->createCargoWeight();
        $weightTable->shouldReceive('getMalusFromLoad')
            ->with($strengthWithoutMalusFromLoad, $cargoWeight)
            ->andReturn($malusFromLoad = 112233);
        $strengthWithoutMalusFromLoad->shouldReceive('add')
            ->with($malusFromLoad)
            ->andReturn($strength = $this->createStrength(2233441));
        $strength->shouldReceive('sub')->with(2)->andReturn($strengthForOffhand = $this->mockery(Strength::class));

        $bodyArmorCode = $this->createBodyArmorCode();
        $helmCode = $this->createHelmCode();
        $tables->shouldReceive('getArmourer')->andReturn($armourer = $this->mockery(Armourer::class));
        $size = $this->mockery(Size::class);
        $armourer->shouldReceive('canUseArmament')->with($bodyArmorCode, $strength, $size)->andReturn($canUseArmor);
        $armourer->shouldReceive('canUseArmament')->with($helmCode, $strength, $size)->andReturn($canUseHelm);

        $propertiesByLevels->shouldReceive('getSize')->andReturn($size);

        new CurrentProperties(
            $propertiesByLevels,
            $health,
            CommonHuman::getIt(),
            $bodyArmorCode,
            $helmCode,
            $cargoWeight,
            $tables
        );
    }

    public function provideNotBearableArmorOrHelm(): array
    {
        return [
            [false, true],
            [true, false],
        ];
    }
}