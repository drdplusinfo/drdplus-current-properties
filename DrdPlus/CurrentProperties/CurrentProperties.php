<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\ArmamentCode;
use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\Armaments\MeleeWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\RaceCode;
use DrdPlus\Codes\SubRaceCode;
use DrdPlus\Health\Health;
use DrdPlus\Professions\Profession;
use DrdPlus\Properties\Base\Agility;
use DrdPlus\Properties\Base\Charisma;
use DrdPlus\Properties\Base\Intelligence;
use DrdPlus\Properties\Base\Knack;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Base\Will;
use DrdPlus\Properties\Body\Age;
use DrdPlus\Properties\Body\HeightInCm;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Properties\Body\WeightInKg;
use DrdPlus\Properties\Combat\BasePropertiesInterface;
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
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Measurements\Weight\Weight as CargoWeight;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

class CurrentProperties extends StrictObject implements BasePropertiesInterface
{
    /** @var PropertiesByLevels */
    private $propertiesByLevels;
    /** @var Health */
    private $health;
    /** @var RaceCode */
    private $raceCode;
    /** @var SubRaceCode */
    private $subRaceCode;
    /** @var BodyArmorCode */
    private $wornBodyArmor;
    /** @var HelmCode */
    private $wornHelm;
    /** @var WeaponlikeCode */
    private $wornWeapon;
    /** @var WeaponlikeCode */
    private $wornShieldOrOffhandWeapon;
    /** @var Size */
    private $size;
    /** @var Profession */
    private $profession;
    /** @var CargoWeight */
    private $cargoWeight;
    /** @var Tables */
    private $tables;

    /**
     * To give numbers for situations with different or even without weapon, shield, armor and helm, just create new instance
     * with desired equipment.
     * Same if weight of cargo can change - just create new instance (because it can affect strength and made unusable
     * previously usable armaments and we need to check that).
     * For "no weapon" use \DrdPlus\Codes\Armaments\MeleeWeaponCode::HAND, for no shield use \DrdPlus\Codes\Armaments\ShieldCode::WITHOUT_SHIELD
     *
     * @param PropertiesByLevels $propertiesByLevels
     * @param Health $health
     * @param Size $bodySize
     * @param Profession $profession
     * @param RaceCode $raceCode
     * @param SubRaceCode $subRaceCode
     * @param BodyArmorCode $wornBodyArmor for no armor use \DrdPlus\Codes\Armaments\BodyArmorCode::WITHOUT_ARMOR
     * @param HelmCode $wornHelm for no helm use \DrdPlus\Codes\Armaments\HelmCode::WITHOUT_HELM
     * @param WeaponlikeCode $wornWeaponOrShieldAsWeapon
     * @param WeaponlikeCode $wornShieldOrOffhandWeapon
     * @param CargoWeight $cargoWeight
     * @param Tables $tables
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    public function __construct(
        PropertiesByLevels $propertiesByLevels,
        Health $health,
        Size $bodySize,
        Profession $profession,
        RaceCode $raceCode,
        SubRaceCode $subRaceCode,
        BodyArmorCode $wornBodyArmor,
        HelmCode $wornHelm,
        WeaponlikeCode $wornWeaponOrShieldAsWeapon,
        WeaponlikeCode $wornShieldOrOffhandWeapon,
        CargoWeight $cargoWeight,
        Tables $tables
    )
    {
        $this->propertiesByLevels = $propertiesByLevels;
        $this->size = $bodySize;
        $this->profession = $profession;
        $this->health = $health;
        $this->raceCode = $raceCode;
        $this->subRaceCode = $subRaceCode;
        $this->cargoWeight = $cargoWeight;
        $this->tables = $tables;
        $this->guardArmamentWearable($wornBodyArmor, $this->getStrength());
        $this->wornBodyArmor = $wornBodyArmor;
        $this->guardArmamentWearable($wornHelm, $this->getStrength());
        $this->wornHelm = $wornHelm;
        $this->guardArmamentWearable(
            $wornWeaponOrShieldAsWeapon,
            $this->getStrengthForWornWeaponOrShield($wornWeaponOrShieldAsWeapon, $wornShieldOrOffhandWeapon)
        );
        $this->wornWeapon = $wornWeaponOrShieldAsWeapon;
        $this->guardArmamentWearable(
            $wornShieldOrOffhandWeapon,
            $this->getStrengthForWornWeaponOrShield($wornShieldOrOffhandWeapon, $wornWeaponOrShieldAsWeapon)
        );
        $this->wornShieldOrOffhandWeapon = $wornShieldOrOffhandWeapon;
    }

    /**
     * @param ArmamentCode $armamentCode
     * @param Strength $currentStrength
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardArmamentWearable(ArmamentCode $armamentCode, Strength $currentStrength)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (!$this->tables->getArmourer()->canUseArmament($armamentCode, $currentStrength, $this->getSize())) {
            throw new Exceptions\CanNotUseArmamentBecauseOfMissingStrength(
                "'{$armamentCode}' is too heavy to be used by with strength {$currentStrength}"
            );
        }
    }

    /**
     * If one-handed shield is kept by both hands, the required strength for weapon in is lower = fighter strength is considered higher
     * see details in PPH page 93, left column
     *
     * @param WeaponlikeCode $mainHand
     * @param WeaponlikeCode $offHand
     * @return Strength
     */
    public function getStrengthForWornWeaponOrShield(WeaponlikeCode $mainHand, WeaponlikeCode $offHand)
    {
        if (!$this->holdsOneHandedWeaponlikeByBothHands($mainHand, $offHand)) {
            return $this->getStrength();
        }

        // If one-handed is kept by both hands, the required strength is lower (workaround for conditioned increment of fighter strength)
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getStrength()->add(2);
    }

    /**
     * Second hand is empty and you can hold single-hand weapon or shield by both hands (if has length at least 1).
     *
     * @param WeaponlikeCode $mainHand
     * @param WeaponlikeCode $offHand
     * @return bool
     */
    public function holdsOneHandedWeaponlikeByBothHands(WeaponlikeCode $mainHand, WeaponlikeCode $offHand)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $mainHand->isMeleeArmament()
            && (($offHand instanceof ShieldCode && $offHand->isWithoutShield())
                || $offHand instanceof MeleeWeaponCode && $offHand->isUnarmed()
            )
            && !$this->tables->getArmourer()->isTwoHanded($mainHand)
            && $this->tables->getArmourer()->getLengthOfWeaponlike($mainHand) >= 1;
    }

    /**
     * @return BodyArmorCode
     */
    public function getWornBodyArmor()
    {
        return $this->wornBodyArmor;
    }

    /**
     * @return HelmCode
     */
    public function getWornHelm()
    {
        return $this->wornHelm;
    }

    /**
     * This is probably weapon, but in case of emergency also shield can be used for smash.
     *
     * @return WeaponlikeCode
     */
    public function getWornWeapon()
    {
        return $this->wornWeapon;
    }

    /**
     * This is probably shield or empty hand (for two-hand weapon or both-hands holding of one-hand weapon),
     * but skilled, crazy or despair individuals can use also a weapon as their offhand.
     *
     * @return WeaponlikeCode
     */
    public function getWornShieldOrOffhandWeapon()
    {
        return $this->wornShieldOrOffhandWeapon;
    }

    /**
     * @return Profession
     */
    public function getProfession()
    {
        return $this->profession;
    }

    /**
     * Current strength affected even by load.
     * It is NOT the constant strength, used for body parameters as endurance and so.
     * Note about both-hands weapon keeping - bonus +2 is NOT part of this strength in both-hands usage of a single-hand weapon,
     * because it could cause a lot of confusion - instead of it is two-hands bonus immediately and automatically included
     * both for missing weapon / shield strength as well as +2 bonus to base of wounds
     *
     * @return Strength
     */
    public function getStrength()
    {
        $strengthWithoutMalusFromLoad = $this->getStrengthWithoutMalusFromLoad();

        // malus from missing strength is applied just once, even if it lowers the strength itself
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $strengthWithoutMalusFromLoad->add(
            $this->tables->getWeightTable()->getMalusFromLoad($strengthWithoutMalusFromLoad, $this->cargoWeight)
        );
    }

    /**
     * @return Strength
     */
    private function getStrengthWithoutMalusFromLoad()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getStrength()->add($this->health->getStrengthMalusFromAfflictions());
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
     * @return Agility
     */
    public function getAgility()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getAgility()->add($this->getAgilityTotalMalus());
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
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getKnack()->add(
            $this->health->getKnackMalusFromAfflictions()
            + $this->tables->getWeightTable()->getMalusFromLoad(
                $this->getStrengthWithoutMalusFromLoad(),
                $this->cargoWeight
            )
        );
    }

    /**
     * @return Will
     */
    public function getWill()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getWill()->add($this->health->getWillMalusFromAfflictions());
    }

    /**
     * @return Intelligence
     */
    public function getIntelligence()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getIntelligence()->add($this->health->getIntelligenceMalusFromAfflictions());
    }

    /**
     * @return Charisma
     */
    public function getCharisma()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->propertiesByLevels->getCharisma()->add($this->health->getCharismaMalusFromAfflictions());
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
        return new Speed($this->getStrength(), $this->getAgility(), $this->getSize());
    }

    /**
     * @return Senses
     * @throws \DrdPlus\Health\Exceptions\NeedsToRollAgainstMalusFirst
     */
    public function getSenses()
    {
        $senses = new Senses($this->getKnack(), $this->raceCode, $this->subRaceCode, $this->tables->getRacesTable());

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $senses->add($this->health->getSignificantMalusFromPains($this->getWoundBoundary()));
    }

    /**
     * @return Beauty
     */
    public function getBeauty()
    {
        return new Beauty($this->getAgility(), $this->getKnack(), $this->getCharisma());
    }

    /**
     * @return Dangerousness
     */
    public function getDangerousness()
    {
        return new Dangerousness($this->getStrength(), $this->getWill(), $this->getCharisma());
    }

    /**
     * @return Dignity
     */
    public function getDignity()
    {
        return new Dignity($this->getIntelligence(), $this->getWill(), $this->getCharisma());
    }

    /**
     * Wound boundary is not affected by temporary maluses, therefore is same as given on new level.
     *
     * @return WoundBoundary
     */
    public function getWoundBoundary()
    {
        return $this->propertiesByLevels->getWoundBoundary();
    }

    /**
     * Fatigue boundary is not affected by temporary maluses, therefore is same as given on new level.
     *
     * @return FatigueBoundary
     */
    public function getFatigueBoundary()
    {
        return $this->propertiesByLevels->getFatigueBoundary();
    }
}