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
use DrdPlus\Properties\Base\Knack;
use DrdPlus\Properties\Base\Strength;
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
        $bodyArmorCode = $this->createBodyArmorCode();
        $helmCode = $this->createHelmCode();
        $propertiesByLevels = $this->createPropertiesByLevels();
        $propertiesByLevels->shouldReceive('getSize')
            ->andReturn($size = $this->mockery(Size::class));
        $health = $this->createHealth();
        $health->shouldReceive('getStrengthMalusFromAfflictions')
            ->andReturn($strengthMalusFromAfflictions = 'foo');
        $propertiesByLevels->shouldReceive('getStrength')
            ->andReturn($baseStrength = $this->mockery(Strength::class));
        $baseStrength->shouldReceive('add')
            ->with($strengthMalusFromAfflictions)
            ->andReturn($strengthWithoutMalusFromLoad = $this->mockery(Strength::class));
        $cargoWeight = $this->createCargoWeight();

        $tables = $this->createTables();
        $tables->shouldReceive('getWeightTable')
            ->andReturn($weightTable = $this->mockery(WeightTable::class));
        $weightTable->shouldReceive('getMalusFromLoad')
            ->with($strengthWithoutMalusFromLoad, $cargoWeight)
            ->andReturn($malusFromLoad = 112233);
        $strengthWithoutMalusFromLoad->shouldReceive('add')
            ->with($malusFromLoad)
            ->andReturn($strength = $this->mockery(Strength::class));
        $strength->shouldReceive('sub')
            ->with(2)
            ->andReturn($strengthForOffhand = $this->mockery(Strength::class));

        $tables->shouldReceive('getArmourer')
            ->andReturn($armourer = $this->mockery(Armourer::class));
        $armourer->shouldReceive('canUseArmament')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(true);
        $armourer->shouldReceive('canUseArmament')
            ->with($helmCode, $strength, $size)
            ->andReturn(true);

        $propertiesByLevels->shouldReceive('getAge')->andReturn($age = $this->mockery(Age::class));

        $propertiesByLevels->shouldReceive('getAgility')->andReturn($agilityWithoutMaluses = $this->mockery(Agility::class));
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(123);
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')->with($helmCode, $strength, $size)->andReturn(456);
        $health->shouldReceive('getAgilityMalusFromAfflictions')->andReturn(789);
        $agilityWithoutMaluses->shouldReceive('add')
            ->with(123 + 456 + 789 + $malusFromLoad)
            ->andReturn($agility = $this->createAgility(456));
        $propertiesByLevels->shouldReceive('getHeightInCm')->andReturn($heightInCm = $this->mockery(HeightInCm::class));
        $propertiesByLevels->shouldReceive('getHeight')->andReturn($height = $this->createHeight(123789));
        $propertiesByLevels->shouldReceive('getKnack')->andReturn($baseKnack = Knack::getIt(667788));
        $health->shouldReceive('getKnackMalusFromAfflictions')
            ->andReturn($knackMalusFromAfflictions = -3344);
        $propertiesByLevels->shouldReceive('getCharisma')->andReturn($baseCharisma = Charisma::getIt(556655));
        $health->shouldReceive('getCharismaMalusFromAfflictions')
            ->andReturn($charismaMalusFromAfflictions = -6666674);

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
     * @return \Mockery\MockInterface|RaceCode
     */
    private function createRaceCode()
    {
        return $this->mockery(RaceCode::class);
    }

    /**
     * @return \Mockery\MockInterface|SubRaceCode
     */
    private function createSubraceCode()
    {
        return $this->mockery(SubRaceCode::class);
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
     * @param CurrentProperties $currentProperties
     * @param Strength|\Mockery\MockInterface $baseStrength
     * @param Strength|\Mockery\MockInterface $strength
     * @param Strength|\Mockery\MockInterface $strengthForOffhand
     * @param Agility $agility
     * @param Knack $baseKnack
     * @param int $knackMalusFromAfflictions
     * @param Charisma $baseCharisma
     * @param $charismaMalusFromAfflictions
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
        Charisma $baseCharisma,
        $charismaMalusFromAfflictions,
        HeightInCm $heightInCm,
        Height $height,
        Size $size,
        Age $age,
        $malusFromLoad
    )
    {
        self::assertSame($baseStrength, $currentProperties->getBodyStrength());
        self::assertSame($strength, $currentProperties->getStrength());
        self::assertSame($strength, $currentProperties->getStrengthForMainHandOnly());
        self::assertSame($strengthForOffhand, $currentProperties->getStrengthForOffhandOnly());
        self::assertSame($agility, $currentProperties->getAgility());
        self::assertInstanceOf(Knack::class, $currentProperties->getKnack());
        self::assertSame($baseKnack->getValue() + $knackMalusFromAfflictions + $malusFromLoad, $currentProperties->getKnack()->getValue());
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
}