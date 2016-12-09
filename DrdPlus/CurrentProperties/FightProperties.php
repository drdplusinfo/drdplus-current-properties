<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\BodyArmorCode;
use DrdPlus\Codes\Armaments\HelmCode;
use DrdPlus\Codes\Armaments\MeleeWeaponCode;
use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\Codes\Body\WoundTypeCode;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Combat\Attack;
use DrdPlus\Properties\Combat\AttackNumber;
use DrdPlus\Properties\Combat\DefenseNumber;
use DrdPlus\Properties\Combat\DefenseNumberAgainstShooting;
use DrdPlus\Properties\Combat\EncounterRange;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Properties\Combat\LoadingInRounds;
use DrdPlus\Properties\Combat\MaximalRange;
use DrdPlus\Properties\Combat\Shooting;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Armaments\Exceptions\DistanceIsOutOfMaximalRange;
use DrdPlus\Tables\Armaments\Exceptions\EncounterRangeCanNotBeGreaterThanMaximalRange;
use DrdPlus\Tables\Environments\Exceptions\DistanceOutOfKnownValues;
use DrdPlus\Tables\Measurements\Distance\Distance;
use DrdPlus\Tables\Measurements\Distance\DistanceBonus;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus as BaseOfWounds;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus;
use DrdPlus\Tables\Tables;
use Granam\Boolean\Tools\ToBoolean;
use Granam\Strict\Object\StrictObject;

class FightProperties extends StrictObject
{
    use GuardArmamentWearableTrait;

    /** @var CurrentProperties */
    private $currentProperties;
    /** @var Skills */
    private $skills;
    /** @var BodyArmorCode */
    private $wornBodyArmor;
    /** @var HelmCode */
    private $wornHelm;
    /** @var ProfessionCode */
    private $professionCode;
    /** @var Tables */
    private $tables;
    /** @var ItemHoldingCode */
    private $weaponlikeHolding;
    /** @var WeaponlikeCode */
    private $weaponlike;
    /** @var bool */
    private $fightsWithTwoWeapons;
    /** @var CombatActions */
    private $combatActions;
    /** @var ShieldCode */
    private $shield;
    /** @var bool */
    private $enemyIsFasterThanYou;
    /** @var FightNumber */
    private $fightNumber;
    /** @var BaseOfWounds */
    private $baseOfWounds;
    /** @var DefenseNumber */
    private $defenseNumber;
    /** @var DefenseNumberAgainstShooting */
    private $defenseNumberAgainstShooting;
    /** @var DefenseNumberAgainstShooting */
    private $defenseNumberAgainstShootingWithShield;
    /** @var DefenseNumber */
    private $defenseNumberWithShield;
    /** @var Distance */
    private $movedDistance;
    /** @var MaximalRange */
    private $maximalRange;
    /** @var EncounterRange */
    private $encounterRange;
    /** @var LoadingInRounds */
    private $loadingInRounds;

    /**
     * Even shield can be used as a weapon, because it is @see WeaponlikeCode
     * Use @see ShieldCode::WITHOUT_SHIELD for no shield.
     * Note about SHIELD and range attack - there is really confusing rule on PPH page 86 right column about AUTOMATIC
     * cover by shield even if you do not know about attack. So you are not using that shield at all, it just exists.
     * So there is no malus by missing strength or skill. So you would have full cover with any shield...?
     * Don't think so... so that rule is IGNORED here.
     *
     * @param CurrentProperties $currentProperties
     * @param CombatActions $combatActions
     * @param Skills $skills
     * @param BodyArmorCode $wornBodyArmor
     * @param HelmCode $wornHelm
     * @param ProfessionCode $professionCode
     * @param Tables $tables
     * @param WeaponlikeCode $weaponlike
     * @param ItemHoldingCode $weaponlikeHolding
     * @param bool $fightsWithTwoWeapons
     * @param ShieldCode $shield
     * @param bool $enemyIsFasterThanYou
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByOneHand
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     * @throws \DrdPlus\CurrentProperties\Exceptions\ImpossibleActionsWithCurrentWeaponlike
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     * @throws \Granam\Boolean\Tools\Exceptions\WrongParameterType
     */
    public function __construct(
        CurrentProperties $currentProperties,
        CombatActions $combatActions,
        Skills $skills,
        BodyArmorCode $wornBodyArmor,
        HelmCode $wornHelm,
        ProfessionCode $professionCode,
        Tables $tables,
        WeaponlikeCode $weaponlike,
        ItemHoldingCode $weaponlikeHolding,
        $fightsWithTwoWeapons,
        ShieldCode $shield, /** use @see ShieldCode::WITHOUT_SHIELD for no shield */
        $enemyIsFasterThanYou
    )
    {
        $this->currentProperties = $currentProperties;
        $this->skills = $skills;
        $this->wornBodyArmor = $wornBodyArmor;
        $this->wornHelm = $wornHelm;
        $this->professionCode = $professionCode;
        $this->tables = $tables;
        $this->weaponlike = $weaponlike;
        $this->weaponlikeHolding = $weaponlikeHolding;
        $this->fightsWithTwoWeapons = ToBoolean::toBoolean($fightsWithTwoWeapons);
        $this->combatActions = $combatActions;
        $this->shield = $shield;
        $this->enemyIsFasterThanYou = ToBoolean::toBoolean($enemyIsFasterThanYou);
        $this->guardWornBodyArmorWearable();
        $this->guardWornHelmWearable();
        $this->guardKnownHolding();
        $this->guardHoldingCompatibleWithWeaponlike();
        $this->guardCombatActionsCompatibleWithWeaponlike();
        $this->guardShieldWearable();
        $this->guardWeaponlikeWearable();
    }

    /**
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWornBodyArmorWearable()
    {
        $this->guardArmamentWearable(
            $this->wornBodyArmor,
            $this->currentProperties->getStrength(),
            $this->currentProperties->getSize(),
            $this->tables->getArmourer()
        );
    }

    /**
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWornHelmWearable()
    {
        $this->guardArmamentWearable(
            $this->wornHelm,
            $this->currentProperties->getStrength(),
            $this->currentProperties->getSize(),
            $this->tables->getArmourer()
        );
    }

    /**
     * @throws Exceptions\UnknownWeaponHolding
     */
    private function guardKnownHolding()
    {
        if (!$this->weaponlikeHolding->holdsByMainHand() && !$this->weaponlikeHolding->holdsByOffhand()
            && !$this->weaponlikeHolding->holdsByTwoHands()
        ) {
            throw new Exceptions\UnknownWeaponHolding(
                "Given holding {$this->weaponlikeHolding} is strange"
            );
        }
    }

    /**
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByOneHand
     */
    private function guardHoldingCompatibleWithWeaponlike()
    {
        if ($this->fightsWithTwoWeapons && $this->weaponlikeHolding->holdsByTwoHands()) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Can not hold weapon '{$this->weaponlike}' by two hands when using two weapons"
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->weaponlikeHolding->holdsByTwoHands()
            && !$this->tables->getArmourer()->canHoldItByTwoHands($this->weaponlike)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "You can not hold '{$this->weaponlike}' by '{$this->weaponlikeHolding}'"
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->weaponlikeHolding->holdsByOneHand()
            && !$this->tables->getArmourer()->canHoldItByOneHand($this->weaponlike)
        ) {
            throw new Exceptions\CanNotHoldItByOneHand(
                "You can not hold '{$this->weaponlike}' by '{$this->weaponlikeHolding}'"
            );
        }
    }

    /**
     * @throws Exceptions\ImpossibleActionsWithCurrentWeaponlike
     */
    private function guardCombatActionsCompatibleWithWeaponlike()
    {
        $possibleActions = $this->tables->getCombatActionsWithWeaponTypeCompatibilityTable()
            ->getActionsPossibleWhenFightingWith($this->weaponlike);
        $currentActions = $this->combatActions->getIterator()->getArrayCopy();
        $impossibleActions = array_diff($currentActions, $possibleActions);
        if (count($impossibleActions) > 0) {
            throw new Exceptions\ImpossibleActionsWithCurrentWeaponlike(
                "With {$this->weaponlike} you can not do " . implode(', ', $impossibleActions)
            );
        }
    }

    /**
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponlikeWearable()
    {
        $this->guardWeaponOrShieldWearable($this->weaponlike, $this->getStrengthForWeaponlike());
    }

    /**
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     */
    private function guardShieldWearable()
    {
        $this->guardWeaponOrShieldWearable($this->shield, $this->getStrengthForShield());
    }

    /**
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $currentStrengthForWeapon
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponOrShieldWearable(WeaponlikeCode $weaponlikeCode, Strength $currentStrengthForWeapon)
    {
        $this->guardArmamentWearable(
            $weaponlikeCode,
            $currentStrengthForWeapon,
            $this->currentProperties->getSize(),
            $this->tables->getArmourer()
        );
    }

    /**
     * @return Strength
     */
    private function getStrengthForWeaponlike()
    {
        return $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding);
    }

    /**
     * If one-handed weapon or shield is kept by both hands, the required strength for weapon is lower
     * (fighter strength is considered higher respectively), see details in PPH page 93, left column.
     *
     * @param WeaponlikeCode $weaponOrShield
     * @param ItemHoldingCode $holding
     * @return Strength
     */
    private function getStrengthForWeaponOrShield(WeaponlikeCode $weaponOrShield, ItemHoldingCode $holding)
    {
        if ($holding->holdsByMainHand()) {
            return $this->currentProperties->getStrengthForMainHandOnly();
        }
        if ($holding->holdsByOffhand()) {
            // your less-dominant hand is weaker (try it)
            return $this->currentProperties->getStrengthForOffhandOnly();
        }
        // two hands holding
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->tables->getArmourer()->isTwoHandedOnly($weaponOrShield)) {
            // it is both-hands only weapon, can NOT count +2 bonus
            return $this->currentProperties->getStrengthForMainHandOnly();
        }
        // if one-handed is kept by both hands, the required strength is lower (fighter strength is higher respectively)
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->currentProperties->getStrengthForMainHandOnly()->add(2);
    }

    /**
     * @return Strength
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     */
    private function getStrengthForShield()
    {
        return $this->getStrengthForWeaponOrShield($this->shield, $this->getShieldHolding());
    }

    /**
     * Gives holding opposite to given weapon holding.
     *
     * @return ItemHoldingCode
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     */
    private function getShieldHolding()
    {
        if ($this->weaponlikeHolding->holdsByMainHand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::OFFHAND);
        }
        if ($this->weaponlikeHolding->holdsByOffhand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::MAIN_HAND);
        }
        // two hands holding
        if ($this->shield->getValue() === ShieldCode::WITHOUT_SHIELD) {
            return ItemHoldingCode::getIt(ItemHoldingCode::MAIN_HAND);
        }
        throw new Exceptions\NoHandLeftForShield(
            "Can not hold {$this->shield} when holding {$this->weaponlike} with {$this->weaponlikeHolding}"
        );
    }

    /**
     * Final fight number including body state (level, fatigue, wounds, curses...), used weapon and chosen action.
     *
     * @return FightNumber
     */
    public function getFightNumber()
    {
        if ($this->fightNumber === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->fightNumber = (new FightNumber(
                $this->professionCode,
                $this->currentProperties,
                $this->currentProperties->getHeight()
            ))->add($this->getFightNumberModifier());
        }

        return $this->fightNumber;
    }

    /**
     * Fight number update according to weapon.
     *
     * @return int
     */
    private function getFightNumberModifier()
    {
        $fightNumberModifier = 0;

        // strength effect
        $fightNumberModifier += $this->getFightNumberMalusByStrength();

        // skills effect
        $fightNumberModifier += $this->getFightNumberMalusBySkills();

        // weapon length effect - length of weapon is directly used as bonus to fight number (shields and ranged weapons have length zero)
        $fightNumberModifier += $this->getFightNumberBonusByWeaponlikeLength();

        // combat actions effect
        $fightNumberModifier += $this->combatActions->getFightNumberModifier();

        return $fightNumberModifier;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusByStrength()
    {
        $fightNumberMalus = 0;

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponlike,
            $this->getStrengthForWeaponlike()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $this->shield,
            $this->getStrengthForShield()
        );

        return $fightNumberMalus;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusBySkills()
    {
        $fightNumberMalus = 0;

        // weapon
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithWeaponlike(
            $this->weaponlike,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons
        );

        // armor and helm
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
            $this->wornBodyArmor,
            $this->tables->getArmourer()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
            $this->wornHelm,
            $this->tables->getArmourer()
        );

        // shields
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
            $this->shield,
            $this->tables->getArmourer()
        );
        // rare situation when you have two shields (or shield and nothing) and uses one as a weapon
        if ($this->weaponlike->isShield()) {
            /** @var ShieldCode $shieldAsWeapon */
            $shieldAsWeapon = $this->weaponlike;
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
                $shieldAsWeapon,
                $this->tables->getArmourer()
            );
        }

        return $fightNumberMalus;
    }

    /**
     * @return int
     */
    private function getFightNumberBonusByWeaponlikeLength()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $lengths[] = $this->tables->getArmourer()->getLengthOfWeaponOrShield($this->weaponlike);
        // shields have length 0, but who knows...
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $lengths[] = $this->tables->getArmourer()->getLengthOfWeaponOrShield($this->shield);

        return max($lengths); // length of a weapon is directly used as a bonus to fight number
    }

    // ATTACK

    /**
     * Final attack number including body state (level, fatigue, wounds, curses...), used weapon and action.
     *
     * @param Distance $targetDistance
     * @return AttackNumber
     */
    public function getAttackNumber(Distance $targetDistance)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->createBaseAttackNumber()->add($this->getAttackNumberModifier($targetDistance));
    }

    /**
     * @return AttackNumber
     */
    private function createBaseAttackNumber()
    {
        if ($this->weaponlike->isShootingWeapon()) {
            return AttackNumber::createFromShooting(new Shooting($this->currentProperties->getKnack()));
        }

        // covers melee and throwing weapons
        return AttackNumber::createFromAttack(new Attack($this->currentProperties->getAgility()));
    }

    /**
     * @param Distance $targetDistance
     * @return int
     * @throws DistanceIsOutOfMaximalRange
     * @throws DistanceOutOfKnownValues
     * @throws EncounterRangeCanNotBeGreaterThanMaximalRange
     */
    private function getAttackNumberModifier(Distance $targetDistance)
    {
        $attackNumberModifier = 0;
        $armourer = $this->tables->getArmourer();

        // strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $armourer->getAttackNumberMalusByStrengthWithWeaponlike(
            $this->weaponlike,
            $this->getStrengthForWeaponlike()
        );

        // skills effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $this->skills->getMalusToAttackNumberWithWeaponlike(
            $this->weaponlike,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons // affects also ranged (mini-crossbows can be hold in one hand for example)
        );

        // weapon effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $armourer->getOffensivenessOfWeaponlike($this->weaponlike);

        // combat actions effect
        $attackNumberModifier += $this->combatActions->getAttackNumberModifier();

        // distance effect (for ranged only)
        if ($this->weaponlike->isRanged()) {
            $attackNumberModifier += $armourer->getAttackNumberModifierByDistance(
                $targetDistance,
                $this->getEncounterRange(),
                $this->getMaximalRange()
            );
        }

        return $attackNumberModifier;
    }

    /**
     * Gives @see WoundsBonus - if you need Wounds just convert WoundsBonus to it by WoundsBonus->getWounds()
     * This number is without actions.
     *
     * @see Wounds
     * Note about both hands holding of a weapon - if you have empty off-hand (without shield) and the weapon you are
     * holding is single-hand, it will automatically add +2 for two-hand holding (if you choose such action).
     * See PPH page 92 right column.
     *
     * @return BaseOfWounds
     */
    public function getBaseOfWounds()
    {
        if ($this->baseOfWounds === null) {
            $baseOfWoundsValue = 0;

            // strength and weapon effects
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWoundsValue += $this->tables->getArmourer()->getBaseOfWoundsUsingWeaponlike(
                $this->weaponlike,
                $this->getStrengthForWeaponlike()
            );

            // skill effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWoundsValue += $this->skills->getMalusToBaseOfWoundsWithWeaponlike(
                $this->weaponlike,
                $this->tables->getMissingWeaponSkillTable(),
                $this->fightsWithTwoWeapons
            );

            // holding effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWoundsValue += $this->tables->getArmourer()->getBaseOfWoundsBonusForHolding(
                $this->weaponlike,
                $this->weaponlikeHolding->holdsByTwoHands()
            );

            // action effects
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWoundsValue += $this->combatActions->getBaseOfWoundsModifier(
                $this->tables->getWeaponlikeTableByWeaponlikeCode($this->weaponlike)
                    ->getWoundsTypeOf($this->weaponlike) === WoundTypeCode::CRUSH
            );

            $this->baseOfWounds = new BaseOfWounds($baseOfWoundsValue, $this->tables->getWoundsTable());
        }

        return $this->baseOfWounds;
    }

    /**
     * Note: for melee weapons the loading is zero.
     *
     * @return LoadingInRounds
     */
    public function getLoadingInRounds()
    {
        if ($this->loadingInRounds === null) {
            $loadingInRoundsValue = 0;
            if ($this->weaponlike instanceof RangedWeaponCode) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $loadingInRoundsValue = $this->tables->getArmourer()->getLoadingInRoundsByStrengthWithRangedWeapon(
                    $this->weaponlike,
                    $this->getStrengthForWeaponlike()
                );
            }

            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->loadingInRounds = new LoadingInRounds($loadingInRoundsValue);
        }

        return $this->loadingInRounds;
    }

    /**
     * Encounter range relates to weapon and strength for bows, speed for throwing weapons and nothing else for
     * crossbows. See PPH page 95 left column.
     * Melee weapons have encounter range zero.
     * Note about SPEAR: if current weapon for attack is spear for melee @see MeleeWeaponCode::SPEAR then range is zero.
     *
     * @return EncounterRange
     */
    public function getEncounterRange()
    {
        if ($this->encounterRange === null) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->encounterRange = new EncounterRange(
                $this->tables->getArmourer()->getEncounterRangeWithWeaponlike(
                    $this->weaponlike,
                    $this->getStrengthForWeaponlike(),
                    $this->currentProperties->getSpeed()
                )
            );
        }

        return $this->encounterRange;
    }

    /**
     * Ranged weapons can be used for indirect shooting and those have much longer maximal and still somehow
     * controllable (more or less - depends on weapon) range.
     * Others have their maximal (and still controllable) range same as encounter range.
     * See PPH page 104 left column.
     *
     * @return MaximalRange
     */
    public function getMaximalRange()
    {
        if ($this->maximalRange === null) {
            if ($this->weaponlike instanceof RangedWeaponCode) {
                $this->maximalRange = MaximalRange::createForRangedWeapon($this->getEncounterRange());
            } else {
                $this->maximalRange = MaximalRange::createForMeleeWeapon($this->getEncounterRange()); // encounter = maximal for melee weapons
            }
        }

        return $this->maximalRange;
    }

    // DEFENSE

    /**
     * Your defense WITHOUT weapon or shield.
     * For standard defense @see getDefenseNumberWithShield and @see getDefenseNumberWithWeaponlike
     * Note: armor affects agility (can give restriction), but does NOT affect defense number directly -
     * its protection is used after hit to lower final damage.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumber()
    {
        if ($this->defenseNumber === null) {
            if ($this->enemyIsFasterThanYou) {
                $this->defenseNumber = $this->getDefenseNumberAgainstFasterOpponent();
            } else {
                $this->defenseNumber = $this->getDefenseNumberAgainstSlowerOpponent();
            }
        }

        return $this->defenseNumber;
    }

    /**
     * You CAN BE affected by some of your actions because someone attacked you before you finished them.
     * Your defense WITHOUT weapon  nor shield. shield.
     *
     * @return DefenseNumber
     */
    private function getDefenseNumberAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getBaseDefenseNumber()->add($this->combatActions->getDefenseNumberModifierAgainstFasterOpponent());
    }

    /**
     * @return DefenseNumber
     */
    private function getBaseDefenseNumber()
    {
        return new DefenseNumber($this->currentProperties->getAgility());
    }

    /**
     * You are NOT affected by any of your action just because someone attacked you before you are ready.
     *
     * @return DefenseNumber
     */
    private function getDefenseNumberAgainstSlowerOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getBaseDefenseNumber()->add($this->combatActions->getDefenseNumberModifier());
    }

    /**
     * You have to choose
     *  - if cover by shield (can twice per round even if already attacked)
     *  - or by weapon (can only once per round and only if you have attacked before defense or if you simply did not
     * used this weapon yet)
     *  - or just by a dodge (in that case use the pure @see getDefenseNumberModifier ).
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithWeaponlike()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithWeaponlike());
    }

    /**
     * @return int
     */
    private function getCoverWithWeaponlike()
    {
        $coverModifier = 0;

        //strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponlike,
            $this->getStrengthForWeaponlike()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlike);

        // skill effect
        if ($this->weaponlike instanceof WeaponCode) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithWeapon(
                $this->weaponlike,
                $this->tables->getMissingWeaponSkillTable(),
                $this->fightsWithTwoWeapons
            );
        } else { // even if you use shield as a weapon for attack, you are covering by it as a shield, of course
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithShield($this->tables->getMissingShieldSkillTable());
        }

        return $coverModifier;
    }

    /**
     * You have to choose
     *  - if cover by shield (can twice per round even if already attacked)
     *  - or by weapon (can only once per round and only if you have attacked before defense or if you simply did not
     * used this weapon yet)
     *  - or just by a dodge (in that case use the pure @see getDefenseNumber ).
     * Note about offhand - even shield is affected by lower strength of your offhand lower strength (-2).
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithShield()
    {
        if ($this->defenseNumberWithShield === null) {
            $this->defenseNumberWithShield = $this->getDefenseNumber()->add($this->getCoverWithShield());
        }

        return $this->defenseNumberWithShield;
    }

    /**
     * @return int
     */
    private function getCoverWithShield()
    {
        $coverModifier = 0;

        //strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponOrShield(
            $this->shield,
            $this->getStrengthForShield()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->shield);

        // skill effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->skills->getMalusToCoverWithShield($this->tables->getMissingShieldSkillTable());

        return $coverModifier;
    }

    /**
     * You do not know how to cover against shooting by a weapon without special skill and that skill is not
     * part of PPH.
     * Therefore this is in fact base defense number WITHOUT weapon, just with size taken into account.
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShooting()
    {
        if ($this->defenseNumberAgainstShooting === null) {
            $this->defenseNumberAgainstShooting = new DefenseNumberAgainstShooting(
                $this->getDefenseNumber(),
                $this->currentProperties->getSize()
            );
        }

        return $this->defenseNumberAgainstShooting;
    }

    /**
     * Note: you do not know how to cover against shooting by a weapon without special skill and that skill is not
     * part of PPH.
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingWithShield()
    {
        if ($this->defenseNumberAgainstShootingWithShield === null) {
            $this->defenseNumberAgainstShootingWithShield = new DefenseNumberAgainstShooting(
                $this->getDefenseNumberWithShield(),
                $this->currentProperties->getSize()
            );
        }

        return $this->defenseNumberAgainstShootingWithShield;
    }

    // MOVEMENT

    /**
     * Note: without chosen movement action you are not moving at all, therefore moved distance is zero.
     *
     * @return Distance
     */
    public function getMovedDistance()
    {
        if ($this->movedDistance === null) {
            if ($this->combatActions->getSpeedModifier() === 0) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $this->movedDistance = new Distance(0, Distance::M, $this->tables->getDistanceTable());
            } else {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $speedInFight = $this->currentProperties->getSpeed()->add($this->combatActions->getSpeedModifier());
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $distanceBonus = new DistanceBonus($speedInFight->getValue(), $this->tables->getDistanceTable());
                $this->movedDistance = $distanceBonus->getDistance();
            }
        }

        return $this->movedDistance;
    }
}