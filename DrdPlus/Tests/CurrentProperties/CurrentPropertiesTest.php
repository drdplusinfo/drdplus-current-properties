<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
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
use DrdPlus\Properties\Derived\Beauty;
use DrdPlus\PropertiesByLevels\PropertiesByLevels;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Measurements\Weight\Weight;
use DrdPlus\Tables\Measurements\Weight\WeightTable;
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
        $tables->shouldReceive('getWeightTable')
            ->andReturn($weightTable = $this->mockery(WeightTable::class));
        $cargoWeight = $this->createCargoWeight();
        $weightTable->shouldReceive('getMalusFromLoad')
            ->with($strengthWithoutMalusFromLoad, $cargoWeight)
            ->andReturn($malusFromLoad = 112233);
        $strengthWithoutMalusFromLoad->shouldReceive('add')
            ->with($malusFromLoad)
            ->andReturn($strength = $this->mockery(Strength::class));
        $strength->shouldReceive('sub')
            ->with(2)
            ->andReturn($strengthForOffhand = $this->mockery(Strength::class));

        // agility
        $propertiesByLevels->shouldReceive('getAgility')->andReturn($agilityWithoutMaluses = $this->mockery(Agility::class));
        $bodyArmorCode = $this->createBodyArmorCode();
        $helmCode = $this->createHelmCode();
        $tables->shouldReceive('getArmourer')
            ->andReturn($armourer = $this->mockery(Armourer::class));
        $propertiesByLevels->shouldReceive('getSize')
            ->andReturn($size = $this->mockery(Size::class));
        $armourer->shouldReceive('canUseArmament')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(true);
        $armourer->shouldReceive('canUseArmament')
            ->with($helmCode, $strength, $size)
            ->andReturn(true);
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

        // will
        $propertiesByLevels->shouldReceive('getWill')->andReturn($baseWill = Will::getIt(88965));
        $health->shouldReceive('getWillMalusFromAfflictions')
            ->andReturn($willMalusFromAfflictions = -7845);

        // intelligence
        $propertiesByLevels->shouldReceive('getIntelligence')->andReturn($baseIntelligence = Intelligence::getIt(789456));
        $health->shouldReceive('getIntelligenceMalusFromAfflictions')
            ->andReturn($intelligenceMalusFromAfflictions = -556623);

        // charisma
        $propertiesByLevels->shouldReceive('getCharisma')->andReturn($baseCharisma = Charisma::getIt(556655));
        $health->shouldReceive('getCharismaMalusFromAfflictions')
            ->andReturn($charismaMalusFromAfflictions = -6666674);

        $propertiesByLevels->shouldReceive('getAge')->andReturn($age = $this->mockery(Age::class));
        $propertiesByLevels->shouldReceive('getHeightInCm')->andReturn($heightInCm = $this->mockery(HeightInCm::class));
        $propertiesByLevels->shouldReceive('getHeight')->andReturn($height = $this->createHeight(123789));

        $currentProperties = new CurrentProperties(
            $propertiesByLevels,
            $health,
            $this->createRaceCode(),
            $this->createSubraceCode(),
            $bodyArmorCode,
            $helmCode,
            $cargoWeight,
            $tables
        );

        $this->I_can_get_current_properties(
            $currentProperties,
            $baseStrength,
            $strength,
            $strengthForOffhand,
            $agility,
            $baseKnack,
            $knackMalusFromAfflictions,
            $baseWill,
            $willMalusFromAfflictions,
            $baseIntelligence,
            $intelligenceMalusFromAfflictions,
            $baseCharisma,
            $charismaMalusFromAfflictions,
            $heightInCm,
            $height,
            $size,
            $age,
            $malusFromLoad
        );
    }

    /**
     * @param CurrentProperties $currentProperties
     * @param Strength|\Mockery\MockInterface $baseStrength
     * @param Strength|\Mockery\MockInterface $strength
     * @param Strength|\Mockery\MockInterface $strengthForOffhand
     * @param Agility $agility
     * @param Knack $baseKnack
     * @param int $knackMalusFromAfflictions
     * @param Will $baseWill
     * @param int $willMalusFromAfflictions
     * @param Intelligence $baseIntelligence
     * @param int $intelligenceMalusFromAfflictions
     * @param Charisma $baseCharisma
     * @param int $charismaMalusFromAfflictions
     * @param HeightInCm $heightInCm
     * @param Height $height
     * @param Size|\Mockery\MockInterface $size
     * @param Age|\Mockery\MockInterface $age
     * @param int $malusFromLoad
     */
    private function I_can_get_current_properties(
        CurrentProperties $currentProperties,
        Strength $baseStrength,
        Strength $strength,
        Strength $strengthForOffhand,
        Agility $agility,
        Knack $baseKnack,
        $knackMalusFromAfflictions,
        Will $baseWill,
        $willMalusFromAfflictions,
        Intelligence $baseIntelligence,
        $intelligenceMalusFromAfflictions,
        Charisma $baseCharisma,
        $charismaMalusFromAfflictions,
        HeightInCm $heightInCm,
        Height $height,
        Size $size,
        Age $age,
        $malusFromLoad
    )
    {
        // base properties
        self::assertSame($baseStrength, $currentProperties->getBodyStrength());
        self::assertSame($strength, $currentProperties->getStrength());
        self::assertSame($strength, $currentProperties->getStrengthForMainHandOnly());
        self::assertSame($strengthForOffhand, $currentProperties->getStrengthForOffhandOnly());
        self::assertSame($agility, $currentProperties->getAgility());
        self::assertInstanceOf(Knack::class, $currentProperties->getKnack());
        self::assertSame($baseKnack->getValue() + $knackMalusFromAfflictions + $malusFromLoad, $currentProperties->getKnack()->getValue());
        self::assertInstanceOf(Will::class, $currentProperties->getWill());
        self::assertSame($baseWill->getValue() + $willMalusFromAfflictions, $currentProperties->getWill()->getValue());
        self::assertInstanceOf(Intelligence::class, $currentProperties->getIntelligence());
        self::assertSame($baseIntelligence->getValue() + $intelligenceMalusFromAfflictions, $currentProperties->getIntelligence()->getValue());
        self::assertInstanceOf(Charisma::class, $currentProperties->getCharisma());
        self::assertSame($baseCharisma->getValue() + $charismaMalusFromAfflictions, $currentProperties->getCharisma()->getValue());

        $expectedBeauty = new Beauty($agility, $currentProperties->getKnack(), $currentProperties->getCharisma());
        self::assertInstanceOf(Beauty::class, $currentProperties->getBeauty());
        self::assertSame($expectedBeauty->getValue(), $currentProperties->getBeauty()->getValue());

        self::assertSame($size, $currentProperties->getSize());
        self::assertSame($age, $currentProperties->getAge());
        self::assertSame($heightInCm, $currentProperties->getHeightInCm());
        self::assertSame($height, $currentProperties->getHeight());
    }

    /**
     * @param $value
     * @return \Mockery\MockInterface|Agility
     */
    private
    function createAgility($value)
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
    private
    function createHeight($value)
    {
        $height = $this->mockery(Height::class);
        $height->shouldReceive('getValue')
            ->andReturn($value);

        return $height;
    }

    /**
     * @return \Mockery\MockInterface|PropertiesByLevels
     */
    private
    function createPropertiesByLevels()
    {
        return $this->mockery(PropertiesByLevels::class);
    }

    /**
     * @return \Mockery\MockInterface|Health
     */
    private
    function createHealth()
    {
        return $this->mockery(Health::class);
    }

    /**
     * @return \Mockery\MockInterface|RaceCode
     */
    private
    function createRaceCode()
    {
        return $this->mockery(RaceCode::class);
    }

    /**
     * @return \Mockery\MockInterface|SubRaceCode
     */
    private
    function createSubraceCode()
    {
        return $this->mockery(SubRaceCode::class);
    }

    /**
     * @return \Mockery\MockInterface|BodyArmorCode
     */
    private
    function createBodyArmorCode()
    {
        return $this->mockery(BodyArmorCode::class);
    }

    /**
     * @return \Mockery\MockInterface|HelmCode
     */
    private
    function createHelmCode()
    {
        return $this->mockery(HelmCode::class);
    }

    /**
     * @return \Mockery\MockInterface|Weight
     */
    private
    function createCargoWeight()
    {
        return $this->mockery(Weight::class);
    }

    /**
     * @return \Mockery\MockInterface|Tables
     */
    private
    function createTables()
    {
        return $this->mockery(Tables::class);
    }
}