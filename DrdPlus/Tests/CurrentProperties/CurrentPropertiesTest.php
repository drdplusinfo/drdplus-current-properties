<?php
namespace DrdPlus\Tests\CurrentProperties;

use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\RaceCode;
use DrdPlus\Codes\SubRaceCode;
use DrdPlus\CurrentProperties\CurrentProperties;
use DrdPlus\Health\Health;
use DrdPlus\Professions\Profession;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Body\Size;
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

        $tables->shouldReceive('getArmourer')
            ->andReturn($armourer = $this->mockery(Armourer::class));
        $armourer->shouldReceive('canUseArmament')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(true);
        $armourer->shouldReceive('canUseArmament')
            ->with($helmCode, $strength, $size)
            ->andReturn(true);

        $currentProperties = new CurrentProperties(
            $propertiesByLevels,
            $health,
            $this->createProfession(),
            $this->createRaceCode(),
            $this->createSubraceCode(),
            $bodyArmorCode,
            $helmCode,
            $cargoWeight,
            $tables
        );

        $propertiesByLevels->shouldReceive('getAge')
            ->andReturn('baz');
        self::assertSame('baz', $currentProperties->getAge());

        $propertiesByLevels->shouldReceive('getAgility')
            ->andReturn($agilityWithoutMaluses = $this->mockery(Agility::class));
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')
            ->with($bodyArmorCode, $strength, $size)
            ->andReturn(123);
        $armourer->shouldReceive('getAgilityMalusByStrengthWithArmor')
            ->with($helmCode, $strength, $size)
            ->andReturn(456);
        $health->shouldReceive('getAgilityMalusFromAfflictions')
            ->andReturn(789);
        $agilityWithoutMaluses->shouldReceive('add')
            ->with(123 + 456 + 789 + $malusFromLoad)
            ->andReturn('qux');
        self::assertSame('qux', $currentProperties->getAgility());
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
     * @return \Mockery\MockInterface|Profession
     */
    private function createProfession()
    {
        return $this->mockery(Profession::class);
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
}