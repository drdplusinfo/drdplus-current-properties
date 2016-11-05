<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\MeleeWeaponCode;
use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Codes\WoundTypeCode;
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
use Granam\Strict\Object\StrictObject;

class FightProperties extends StrictObject
{
    /** @var CurrentProperties */
    private $currentProperties;
    /** @var Skills */
    private $skills;
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

    /**
     * Warning: to define a weapon used by two hands, do NOT set the second weapon (provide NULL).
     * If you will provide @see MeleeWeaponCode::HAND or similar "empty" hand, it will be considered as a standard
     * weapon.
     * Whenever you provide two weapons for attack or defense then two weapons fighting will be taken into account,
     * even if you give two "empty" hands like @see MeleeWeaponCode::LEG it is taken as two weapons.
     *
     * @param CurrentProperties $currentProperties
     * @param CombatActions $combatActions
     * @param Skills $skills
     * @param Tables $tables
     * @param WeaponlikeCode $weaponlike
     * @param ItemHoldingCode $weaponlikeHolding
     * @param bool $fightsWithTwoWeapons
     * @param ShieldCode $shield
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByOneHand
     * @throws \DrdPlus\CurrentProperties\Exceptions\InvalidCombatActionFormat
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     * @throws \DrdPlus\CurrentProperties\Exceptions\ImpossibleActionsWithCurrentWeaponlike
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     */
    public function __construct(
        CurrentProperties $currentProperties,
        CombatActions $combatActions,
        Skills $skills,
        Tables $tables,
        WeaponlikeCode $weaponlike,
        ItemHoldingCode $weaponlikeHolding,
        $fightsWithTwoWeapons,
        ShieldCode $shield/** use @see ShieldCode::WITHOUT_SHIELD for no shield */
    )
    {
        $this->currentProperties = $currentProperties;
        $this->skills = $skills;
        $this->tables = $tables;
        $this->weaponlike = $weaponlike;
        $this->weaponlikeHolding = $weaponlikeHolding;
        $this->fightsWithTwoWeapons = $fightsWithTwoWeapons;
        $this->combatActions = $combatActions;
        $this->shield = $shield;
        $this->guardHoldingCompatibleWithWeaponlike();
        $this->guardCombatActionsCompatibleWithWeaponlike();
        $this->guardWeaponOrShieldWearable($weaponlike, $this->getStrengthForWeaponlike());
        $this->guardWeaponOrShieldWearable($shield, $this->getStrengthForShield());
    }

    /**
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByOneHand
     */
    private function guardHoldingCompatibleWithWeaponlike()
    {
        if ($this->fightsWithTwoWeapons && $this->weaponlikeHolding->holdsByTwoHands()) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Can not hold weapon {$this->weaponlike} by two hands when using two weapons"
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->weaponlikeHolding->getValue() === ItemHoldingCode::TWO_HANDS
            && !$this->tables->getArmourer()->canHoldItByTwoHands($this->weaponlike)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "You can not hold {$this->weaponlike} by two hands, despite your claim {$this->weaponlikeHolding}"
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (in_array($this->weaponlikeHolding->getValue(), [ItemHoldingCode::MAIN_HAND, ItemHoldingCode::OFFHAND], true)
            && !$this->tables->getArmourer()->canHoldItByOneHand($this->weaponlike)
        ) {
            throw new Exceptions\CanNotHoldItByOneHand(
                "You can not hold {$this->weaponlike} by single hand, despite your claim {$this->weaponlikeHolding}"
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
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $currentStrengthForWeapon
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponOrShieldWearable(WeaponlikeCode $weaponlikeCode, Strength $currentStrengthForWeapon)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (!$this->tables->getArmourer()->canUseArmament(
            $weaponlikeCode,
            $currentStrengthForWeapon,
            $this->currentProperties->getSize())
        ) {
            throw new Exceptions\CanNotUseArmamentBecauseOfMissingStrength(
                "'{$weaponlikeCode}' is too heavy to be used by with strength {$currentStrengthForWeapon}"
            );
        }
    }

    /**
     * @return Strength
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
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
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
     */
    private function getStrengthForWeaponOrShield(WeaponlikeCode $weaponOrShield, ItemHoldingCode $holding)
    {
        switch ($holding->getValue()) {
            case ItemHoldingCode::TWO_HANDS :
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                if ($this->tables->getArmourer()->isTwoHandedOnly($weaponOrShield)) {
                    // it is both-hands only weapon, can NOT count +2 bonus
                    return $this->currentProperties->getStrengthForMainHandOnly();
                }
                // if one-handed is kept by both hands, the required strength is lower (fighter strength is higher respectively)
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                return $this->currentProperties->getStrengthForMainHandOnly()->add(2);
            case ItemHoldingCode::MAIN_HAND :
                return $this->currentProperties->getStrengthForMainHandOnly();
            case ItemHoldingCode::OFFHAND :
                // Your less-dominant hand is weaker - try it.
                return $this->currentProperties->getStrengthForOffhandOnly();
            default :
                throw new Exceptions\UnknownWeaponHolding('Do not know how to use weapon when holding like ' . $holding);
        }
    }

    /**
     * @return Strength
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
     */
    private function getStrengthForShield()
    {
        return $this->getStrengthForWeaponOrShield($this->shield, $this->getShieldHolding($this->weaponlikeHolding));
    }

    /**
     * Gives holding opposite to given weapon holding.
     *
     * @param ItemHoldingCode $weaponlikeHolding
     * @return ItemHoldingCode
     * @throws \DrdPlus\CurrentProperties\Exceptions\NoHandLeftForShield
     * @throws \DrdPlus\CurrentProperties\Exceptions\UnknownWeaponHolding
     */
    private function getShieldHolding(ItemHoldingCode $weaponlikeHolding)
    {
        if ($weaponlikeHolding->holdsByMainHand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::OFFHAND);
        }
        if ($weaponlikeHolding->holdsByOffhand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::MAIN_HAND);
        }
        if ($weaponlikeHolding->holdsByTwoHands()) {
            throw new Exceptions\NoHandLeftForShield(
                "Can not give holding to a shield when holding {$weaponlikeHolding} like {$weaponlikeHolding}"
            );
        }
        throw new Exceptions\UnknownWeaponHolding('Do not know how to use weapon when holding like ' . $weaponlikeHolding);
    }

    /**
     * Final fight number including body state (level, fatigue, wounds, curses...), used weapon and chosen action.
     *
     * @return FightNumber
     */
    public function getFightNumber()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumber = new FightNumber(
            $this->currentProperties->getProfession()->getCode(),
            $this->currentProperties,
            $this->currentProperties->getSize()
        );

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $fightNumber->add($this->getFightNumberModifier());
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
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus = $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponlike,
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $this->shield,
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->getShieldHolding($this->weaponlikeHolding))
        );

        return $fightNumberMalus;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusBySkills()
    {
        $fightNumberMalus = $this->getFightNumberMalusFromProtectivesBySkills();
        $fightNumberMalus += $this->getFightNumberMalusFromWeaponlikesBySkills();

        return $fightNumberMalus;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusFromProtectivesBySkills()
    {
        // armor and helm
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus = $this->skills->getMalusToFightNumberWithProtective(
            $this->currentProperties->getWornBodyArmor(),
            $this->tables->getArmourer()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
            $this->currentProperties->getWornHelm(),
            $this->tables->getArmourer()
        );

        // shields
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
            $this->shield,
            $this->tables->getArmourer()
        );
        if ($this->weaponlike->isShield()) { // EVEN IF you use the shield in main hand as a weapon...
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
    private function getFightNumberMalusFromWeaponlikesBySkills()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->skills->getMalusToFightNumberWithWeaponlike(
            $this->weaponlike,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons
        );
    }

    /**
     * @return int
     */
    private function getFightNumberBonusByWeaponlikeLength()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $lengths[] = $this->tables->getArmourer()->getLengthOfWeaponOrShield($this->weaponlike);
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
        $attackNumber = $this->createBaseAttackNumber();

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $attackNumber->add($this->getAttackNumberModifier($targetDistance));
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
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
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

        // distance effect (for ranged only)
        if ($targetDistance > 1 && $this->weaponlike->isRanged()) {
            $armourer->getAttackNumberModifierByDistance(
                $targetDistance,
                $this->getEncounterRange(),
                $this->getMaximalRange()
            );
        }

        // combat actions effect
        $attackNumberModifier += $this->combatActions->getAttackNumberModifier();

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
        $baseOfWoundsModifier = 0;

        // strength and weapon effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWoundsModifier += $this->tables->getArmourer()->getBaseOfWoundsUsingWeaponlike(
            $this->weaponlike,
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
        );

        // skill effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWoundsModifier += $this->skills->getMalusToBaseOfWoundsWithWeaponlike(
            $this->weaponlike,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons
        );

        // holding effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWoundsModifier += $this->tables->getArmourer()->getBaseOfWoundsBonusForHolding(
            $this->weaponlike,
            $this->weaponlikeHolding->getValue() === ItemHoldingCode::TWO_HANDS
        );

        // action effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWoundsModifier += $this->combatActions->getBaseOfWoundsModifier(
            $this->tables->getWeaponlikeTableByWeaponlikeCode($this->weaponlike)
                ->getWoundsTypeOf($this->weaponlike) === WoundTypeCode::CRUSH
        );

        return new BaseOfWounds($baseOfWoundsModifier, $this->tables->getWoundsTable());
    }

    /**
     * Note: for melee weapons is loading zero.
     *
     * @return LoadingInRounds
     */
    public function getLoadingInRounds()
    {
        $loadingInRoundsValue = 0;
        if ($this->weaponlike instanceof RangedWeaponCode) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $loadingInRoundsValue = $this->tables->getArmourer()->getLoadingInRoundsByStrengthWithRangedWeapon(
                $this->weaponlike,
                $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new LoadingInRounds($loadingInRoundsValue);
    }

    /**
     * Encounter range relates to weapon and strength for bows, speed for throwing weapons and nothing else for
     * crossbows. See PPH page 95 left column.
     * Also melee weapons have encounter range - calculated from their length parameter.
     * Note about SPEAR: if current weapon for attack is spear for melee @see MeleeWeaponCode::SPEAR then range is zero.
     *
     * @return EncounterRange
     */
    public function getEncounterRange()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new EncounterRange(
            $this->tables->getArmourer()->getEncounterRangeWithWeaponlike(
                $this->weaponlike,
                $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding),
                $this->currentProperties->getSpeed()
            )
        );
    }

    /**
     * Ranged weapons can be used for indirect shooting and those have much longer maximal and still somehow
     * controllable
     * (more or less - depends on weapon) range.
     * Others have their maximal (and still controllable) range same as encounter range.
     * See PPH page 104 left column.
     *
     * @return MaximalRange
     */
    public function getMaximalRange()
    {
        if ($this->weaponlike->isMelee()) {
            return MaximalRange::createForMeleeWeapon($this->getEncounterRange()); // no change for melee weapons
        }

        return MaximalRange::createForRangedWeapon($this->getEncounterRange());
    }

    // DEFENSE

    /**
     * Your defense WITHOUT weapon (@see getDefenseNumberWithShield and @see getDefenseNumberWithMainHand ).
     * Note: armor affects agility (can give restriction), but does NOT affect defense number directly -
     * its protection is used after hit to lower final damage.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumber()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getBaseDefenseNumber()->add($this->combatActions->getDefenseNumberModifier());
    }

    /**
     * @return DefenseNumber
     */
    private function getBaseDefenseNumber()
    {
        return new DefenseNumber($this->currentProperties->getAgility());
    }

    /**
     * Your defense WITHOUT weapon (@see getDefenseNumberWithShield and @see getDefenseNumberWithMainHand ).
     * Note: armor affects agility (can give restriction), but they do NOT affect defense number directly -
     * its protection is used after hit to lower final damage.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getBaseDefenseNumber()->add($this->combatActions->getDefenseNumberModifierAgainstFasterOpponent());
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
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlike);

        // skill effect
        if ($this->weaponlike->isWeapon()) {
            /** @var WeaponCode $weapon */
            $weapon = $this->weaponlike;
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithWeapon(
                $weapon,
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
     * @return DefenseNumber
     */
    public function getDefenseNumberWithWeaponlikeAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithWeaponlike());
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
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithShield());
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
            $this->getStrengthForWeaponOrShield($this->shield, $this->getShieldHolding($this->weaponlikeHolding))
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
     * @return DefenseNumber
     */
    public function getDefenseNumberWithShieldAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithShield());
    }

    /**
     * Base defense number WITHOUT weapon nor shield.
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShooting()
    {
        return new DefenseNumberAgainstShooting($this->getDefenseNumber(), $this->currentProperties->getSize());
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberWithWeaponlikeAgainstShooting()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberWithWeaponlike(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberWithOffhandAgainstShooting()
    {
        return new DefenseNumberAgainstShooting($this->getDefenseNumberWithShield(), $this->currentProperties->getSize());
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingCoveredPassivelyByShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->shield));
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingAndFasterOpponent()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberAgainstFasterOpponent(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingAndFasterOpponentCoveredPassivelyByShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShootingAndFasterOpponent()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->shield));
    }

    // MOVEMENT

    /**
     * NOte: without chosen movement action you are not moving at all, therefore moved distance is zero.
     *
     * @return Distance
     */
    public function getMovedDistance()
    {
        if ($this->combatActions->getSpeedModifier() === 0) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return new Distance(0, Distance::M, $this->tables->getDistanceTable());
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $speed = $this->currentProperties->getSpeed()->add($this->combatActions->getSpeedModifier());
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $distanceBonus = new DistanceBonus($speed->getValue(), $this->tables->getDistanceTable());

        return $distanceBonus->getDistance();
    }
}