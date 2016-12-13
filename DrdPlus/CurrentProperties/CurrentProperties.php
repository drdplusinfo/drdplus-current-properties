<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
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
use DrdPlus\Properties\Combat\BaseProperties;
use DrdPlus\Properties\Derived\Beauty;
use DrdPlus\Properties\Derived\Dangerousness;
use DrdPlus\Properties\Derived\Dignity;
use DrdPlus\Properties\Derived\Endurance;
use DrdPlus\Properties\Derived\FatigueBoundary;
use DrdPlus\Properties\Derived\Partials\AbstractDerivedProperty;
use DrdPlus\Properties\Derived\Senses;
use DrdPlus\Properties\Derived\Speed;
use DrdPlus\Properties\Derived\Toughness;
use DrdPlus\Properties\Derived\WoundBoundary;
use DrdPlus\PropertiesByLevels\PropertiesByLevels;
use DrdPlus\Races\Race;
use DrdPlus\Tables\Measurements\Weight\Weight;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

class CurrentProperties extends StrictObject implements BaseProperties
{
    use GuardArmamentWearableTrait;

    /** @var PropertiesByLevels */
    private $propertiesByLevels;
    /** @var Health */
    private $health;
    /** @var Race */
    private $race;
    /** @var BodyArmorCode */
    private $wornBodyArmor;
    /** @var HelmCode */
    private $wornHelm;
    /** @var Weight */
    private $cargoWeight;
    /** @var Tables */
    private $tables;
    /** @var Strength */
    private $strength;
    /** @var Strength */
    private $strengthWithoutMalusFromLoad;
    /** @var Strength */
    private $strengthForOffhandOnly;
    /** @var Agility */
    private $agility;
    /** @var Knack */
    private $knack;
    /** @var Will */
    private $will;
    /** @var Intelligence */
    private $intelligence;
    /** @var Charisma */
    private $charisma;
    /** @var Speed */
    private $speed;
    /** @var Senses */
    private $senses;
    /** @var Beauty */
    private $beauty;
    /** @var Dangerousness */
    private $dangerousness;
    /** @var Dignity */
    private $dignity;

    /**
     * To give numbers for situations with different or even without weapon, shield, armor and helm, just create new
     * instance with desired equipment. Same if weight of cargo can change - just create new instance (because it can
     * affect strength and made unusable previously usable armaments and we need to check that). For "no weapon" use
     * \DrdPlus\Codes\Armaments\MeleeWeaponCode::HAND, for no shield use
     * \DrdPlus\Codes\Armaments\ShieldCode::WITHOUT_SHIELD
     *
     * @param PropertiesByLevels $propertiesByLevels
     * @param Health $health
     * @param Race $race
     * @param BodyArmorCode $wornBodyArmor for no armor use \DrdPlus\Codes\Armaments\BodyArmorCode::WITHOUT_ARMOR
     * @param HelmCode $wornHelm for no helm use \DrdPlus\Codes\Armaments\HelmCode::WITHOUT_HELM
     * @param Weight $cargoWeight
     * @param Tables $tables
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    public function __construct(
        PropertiesByLevels $propertiesByLevels,
        Health $health,
        Race $race,
        BodyArmorCode $wornBodyArmor,
        HelmCode $wornHelm,
        Weight $cargoWeight,
        Tables $tables
    )
    {
        $this->propertiesByLevels = $propertiesByLevels;
        $this->health = $health;
        $this->race = $race;
        $this->cargoWeight = $cargoWeight;
        $this->tables = $tables;
        $this->guardArmamentWearable($wornBodyArmor, $this->getStrength(), $this->getSize(), $tables->getArmourer());
        $this->wornBodyArmor = $wornBodyArmor;
        $this->guardArmamentWearable($wornHelm, $this->getStrength(), $this->getSize(), $tables->getArmourer());
        $this->wornHelm = $wornHelm;
    }

    /**
     * Current strength affected even by load.
     * It is NOT the constant strength, used for body parameters as endurance and so.
     * Note about both-hands weapon keeping - bonus +2 is NOT part of this strength in both-hands usage of a
     * single-hand weapon, because it could cause a lot of confusion - instead of it is two-hands bonus immediately and
     * automatically included both for missing weapon / shield strength as well as +2 bonus to base of wounds
     *
     * @return Strength
     */
    public function getStrength()
    {
        if ($this->strength === null) {
            $strengthWithoutMalusFromLoad = $this->getStrengthWithoutMalusFromLoad();
            // malus from missing strength is applied just once, even if it lowers the strength itself
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->strength = $strengthWithoutMalusFromLoad->add(
                $this->tables->getWeightTable()->getMalusFromLoad($strengthWithoutMalusFromLoad, $this->cargoWeight)
            );
        }

        return $this->strength;
    }

    /**
     * @return Strength
     */
    private function getStrengthWithoutMalusFromLoad()
    {
        if ($this->strengthWithoutMalusFromLoad === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->strengthWithoutMalusFromLoad = $this->propertiesByLevels->getStrength()
                ->add($this->health->getStrengthMalusFromAfflictions());
        }

        return $this->strengthWithoutMalusFromLoad;
    }

    /**
     * This is the stable value, used for endurance and toughness.
     * This value is affected only by levels, not by current weakness or load.
     *
     * @return Strength
     */
    public function getBodyStrength()
    {
        return $this->propertiesByLevels->getStrength();
    }

    /**
     * @return Strength
     */
    public function getStrengthForMainHandOnly()
    {
        return $this->getStrength();
    }

    /**
     * @return Strength
     */
    public function getStrengthForOffhandOnly()
    {
        if ($this->strengthForOffhandOnly === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->strengthForOffhandOnly = $this->getStrength()->sub(2); // offhand has a malus to strength (try to carry you purchase in offhand sometimes...)
        }

        return $this->strengthForOffhandOnly;
    }

    /**
     * @return Agility
     */
    public function getAgility()
    {
        if ($this->agility === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->agility = $this->propertiesByLevels->getAgility()->add($this->getAgilityTotalMalus());
        }

        return $this->agility;
    }

    /**
     * @return int
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function getAgilityTotalMalus()
    {
        $agilityMalus = 0;
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $agilityMalus += $this->tables->getArmourer()->getAgilityMalusByStrengthWithArmor(
            $this->wornBodyArmor,
            $this->getStrength(),
            $this->getSize()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $agilityMalus += $this->tables->getArmourer()->getAgilityMalusByStrengthWithArmor(
            $this->wornHelm,
            $this->getStrength(),
            $this->getSize()
        );
        $agilityMalus += $this->health->getAgilityMalusFromAfflictions();
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $agilityMalus += $this->tables->getWeightTable()->getMalusFromLoad(
            $this->getStrengthWithoutMalusFromLoad(),
            $this->cargoWeight
        );

        return $agilityMalus;
    }

    /**
     * @return Knack
     */
    public function getKnack()
    {
        if ($this->knack === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->knack = $this->propertiesByLevels->getKnack()
                ->add($this->health->getKnackMalusFromAfflictions())
                ->add($this->tables->getWeightTable()->getMalusFromLoad(
                    $this->getStrengthWithoutMalusFromLoad(),
                    $this->cargoWeight
                ));
        }

        return $this->knack;
    }

    /**
     * @return Will
     */
    public function getWill()
    {
        if ($this->will === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->will = $this->propertiesByLevels->getWill()->add($this->health->getWillMalusFromAfflictions());
        }

        return $this->will;
    }

    /**
     * @return Intelligence
     */
    public function getIntelligence()
    {
        if ($this->intelligence === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->intelligence = $this->propertiesByLevels->getIntelligence()
                ->add($this->health->getIntelligenceMalusFromAfflictions());
        }

        return $this->intelligence;
    }

    /**
     * @return Charisma
     */
    public function getCharisma()
    {
        if ($this->charisma === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->charisma = $this->propertiesByLevels->getCharisma()->add($this->health->getCharismaMalusFromAfflictions());
        }

        return $this->charisma;
    }

    /**
     * @return WeightInKg
     */
    public function getWeightInKg()
    {
        return $this->propertiesByLevels->getWeightInKg();
    }

    /**
     * @return HeightInCm
     */
    public function getHeightInCm()
    {
        return $this->propertiesByLevels->getHeightInCm();
    }

    /**
     * Bonus of height in fact - usable for Fight and Speed
     *
     * @return Height
     */
    public function getHeight()
    {
        return $this->propertiesByLevels->getHeight();
    }

    /**
     * @return Age
     */
    public function getAge()
    {
        return $this->propertiesByLevels->getAge();
    }

    /**
     * @return Toughness
     */
    public function getToughness()
    {
        return $this->propertiesByLevels->getToughness();
    }

    /**
     * @return Endurance
     */
    public function getEndurance()
    {
        return $this->propertiesByLevels->getEndurance();
    }

    /**
     * @return Size
     */
    public function getSize()
    {
        return $this->propertiesByLevels->getSize();
    }

    /**
     * @return Speed
     */
    public function getSpeed()
    {
        if ($this->speed === null) {
            $this->speed = new Speed($this->getStrength(), $this->getAgility(), $this->getHeight());
        }

        return $this->speed;
    }

    /**
     * @return AbstractDerivedProperty|Senses
     * @throws \DrdPlus\Health\Exceptions\NeedsToRollAgainstMalusFirst
     */
    public function getSenses()
    {
        if ($this->senses === null) {
            $baseSenses = new Senses(
                $this->getKnack(),
                $this->race->getRaceCode(),
                $this->race->getSubraceCode(),
                $this->tables->getRacesTable()
            );
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->senses = $baseSenses->add($this->health->getSignificantMalusFromPains($this->getWoundBoundary()));
        }

        return $this->senses;
    }

    /**
     * @return Beauty
     */
    public function getBeauty()
    {
        if ($this->beauty === null) {
            $this->beauty = new Beauty($this->getAgility(), $this->getKnack(), $this->getCharisma());
        }

        return $this->beauty;
    }

    /**
     * @return Dangerousness
     */
    public function getDangerousness()
    {
        if ($this->dangerousness === null) {
            $this->dangerousness = new Dangerousness($this->getStrength(), $this->getWill(), $this->getCharisma());
        }

        return $this->dangerousness;
    }

    /**
     * @return Dignity
     */
    public function getDignity()
    {
        if ($this->dignity === null) {
            $this->dignity = new Dignity($this->getIntelligence(), $this->getWill(), $this->getCharisma());
        }

        return $this->dignity;
    }

    /**
     * Wound boundary is not affected by temporary maluses, therefore is same as given by current level.
     *
     * @return WoundBoundary
     */
    public function getWoundBoundary()
    {
        return $this->propertiesByLevels->getWoundBoundary();
    }

    /**
     * Fatigue boundary is not affected by temporary maluses, therefore is same as given by current level.
     *
     * @return FatigueBoundary
     */
    public function getFatigueBoundary()
    {
        return $this->propertiesByLevels->getFatigueBoundary();
    }
}