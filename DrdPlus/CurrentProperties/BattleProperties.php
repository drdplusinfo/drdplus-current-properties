<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ProfessionCode;
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
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus as BaseOfWounds;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

class BattleProperties extends StrictObject
{
    /** @var CurrentProperties */
    private $currentProperties;
    /** @var CombatActions */
    private $combatActions;
    /** @var WeaponlikeCode */
    private $weaponOrShieldInMainHand;
    /** @var WeaponlikeCode */
    private $weaponOrShieldInOffhand;
    /** @var Skills */
    private $skills;
    /** @var Tables */
    private $tables;

    /**
     * @param CurrentProperties $currentProperties
     * @param CombatActions $combatActions
     * @param WeaponlikeCode $weaponOrShieldInMainHand
     * @param WeaponlikeCode $weaponOrShieldInOffhand
     * @param Skills $skills
     * @param Tables $tables
     * @throws Exceptions\CanNotHoldItByTwoHands
     * @throws \DrdPlus\CurrentProperties\Exceptions\InvalidCombatActionFormat
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActionsWithWeaponType
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    public function __construct(
        CurrentProperties $currentProperties,
        CombatActions $combatActions,
        WeaponlikeCode $weaponOrShieldInMainHand,
        WeaponlikeCode $weaponOrShieldInOffhand,
        Skills $skills,
        Tables $tables
    )
    {
        $this->currentProperties = $currentProperties;
        $this->skills = $skills;
        $this->tables = $tables;
        $this->combatActions = $combatActions;
        $this->weaponOrShieldInMainHand = $weaponOrShieldInMainHand;
        $this->weaponOrShieldInOffhand = $weaponOrShieldInOffhand;
        $this->guardWeaponsUsableTogether($weaponOrShieldInMainHand, $weaponOrShieldInOffhand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldInMainHand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldInOffhand);
        $this->guardWeaponsAndShieldsWearable($weaponOrShieldInMainHand, $weaponOrShieldInOffhand);
    }

    /**
     * @param WeaponlikeCode $weaponOrShieldInMainHand
     * @param WeaponlikeCode $weaponOrShieldInOffhand
     * @throws Exceptions\CanNotHoldItByTwoHands
     */
    private function guardWeaponsUsableTogether(WeaponlikeCode $weaponOrShieldInMainHand, WeaponlikeCode $weaponOrShieldInOffhand)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->tables->getArmourer()->isTwoHandedOnly($weaponOrShieldInMainHand)
            && !$this->tables->getArmourer()->hasEmptyHand($weaponOrShieldInOffhand)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Weapon {$weaponOrShieldInMainHand} is two-handed only but second hand is occupied by {$weaponOrShieldInOffhand}"
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->tables->getArmourer()->isTwoHandedOnly($weaponOrShieldInOffhand)
            && !$this->tables->getArmourer()->hasEmptyHand($weaponOrShieldInMainHand)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Weapon {$weaponOrShieldInOffhand} is two-handed only but second hand is occupied by {$weaponOrShieldInMainHand}"
            );
        }
    }

    /**
     * @param CombatActions $currentCombatActions
     * @param WeaponlikeCode $weaponOrShield
     * @throws \DrdPlus\CurrentProperties\Exceptions\InvalidCombatActionFormat
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActionsWithWeaponType
     */
    private function guardActionsCompatibleWithWeapon(CombatActions $currentCombatActions, WeaponlikeCode $weaponOrShield)
    {
        $availableCombatActions = new CombatActions(
            $this->tables->getCombatActionsWithWeaponTypeCompatibilityTable()
                ->getActionsPossibleWhenAttackingWith($weaponOrShield),
            $this->tables->getCombatActionsCompatibilityTable()
        );
        $currentCombatActions = $currentCombatActions->getIterator()->getArrayCopy();
        if (!$availableCombatActions->hasAllThose($currentCombatActions)) {
            throw new Exceptions\IncompatibleCombatActionsWithWeaponType(
                "With weapon {$weaponOrShield} can not be made action(s) "
                . implode(',', $availableCombatActions->filterThoseNotHave($currentCombatActions))
            );
        }
    }

    /**
     * @param WeaponlikeCode $weaponOrShieldInMainHand
     * @param WeaponlikeCode $weaponOrShieldInOffhand
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponsAndShieldsWearable(WeaponlikeCode $weaponOrShieldInMainHand, WeaponlikeCode $weaponOrShieldInOffhand)
    {
        if ($this->usesSingleWeaponOrShield()) {
            if ($this->combatActions->fightsByTwoHands()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $this->guardWeaponOrShieldWearable(
                    $this->getSingleWeaponOrShield(),
                    $this->getStrengthForWeaponInTwoHands($this->getSingleWeaponOrShield())
                );
            } else {
                assert($this->usesSingleOneHandedWeaponByOneHand());
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $this->guardWeaponOrShieldWearable(
                    $this->getSingleWeaponOrShield(),
                    $this->getStrengthForSingleOneHandedWeapon()
                );
            }
        } else {
            assert($this->usesTwoWeaponsOrWithShield());
            $this->guardWeaponOrShieldWearable($weaponOrShieldInMainHand, $this->currentProperties->getStrengthForMainHandOnly());
            $this->guardWeaponOrShieldWearable($weaponOrShieldInOffhand, $this->currentProperties->getStrengthForOffhandOnly());
        }
    }

    /**
     * @return bool
     */
    private function usesSingleWeaponOrShield()
    {
        return $this->combatActions->fightsByMainHandOnly() || $this->combatActions->fightsByOffhandOnly();
    }

    /**
     * @return bool
     */
    private function usesTwoWeaponsOrWithShield()
    {
        return !$this->usesSingleWeaponOrShield();
    }

    /**
     * @return WeaponlikeCode
     * @throws Exceptions\CanNotGiveSingleWeaponWhenTwoAreUsed
     */
    private function getSingleWeaponOrShield()
    {
        if (!$this->tables->getArmourer()->hasEmptyHand($this->weaponOrShieldInMainHand)) {
            return $this->weaponOrShieldInMainHand;
        }
        if (!$this->tables->getArmourer()->hasEmptyHand($this->weaponOrShieldInOffhand)) {
            return $this->weaponOrShieldInOffhand;
        }
        throw new Exceptions\CanNotGiveSingleWeaponWhenTwoAreUsed(
            "Can not give single weapon when using {$this->weaponOrShieldInMainHand} and {$this->weaponOrShieldInOffhand}"
        );
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
     * Final fight number including body state (level, fatigue, wounds, curses...), used weapon and chosen action.
     *
     * @return FightNumber
     */
    public function getFightNumber()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumber = new FightNumber(
            ProfessionCode::getIt($this->currentProperties->getProfession()->getValue()),
            $this->currentProperties,
            $this->currentProperties->getSize()
        );

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $fightNumber->add($this->getFightNumberModifier());
    }

    /**
     * @return int
     */
    private function getFightNumberModifier()
    {
        $fightNumberModifier = 0;

        // strength effect
        if ($this->usesSingleWeaponOrShield()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
                $this->getSingleWeaponOrShield(),
                $this->getStrengthForWeaponInTwoHands($this->getSingleWeaponOrShield())
            );
        } else {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
                $this->weaponOrShieldInMainHand,
                $this->getStrengthForMainHand()
            );
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
                $this->weaponOrShieldInOffhand,
                $this->getStrengthForOffhand()
            );
        }

        // skills effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberModifier +=
            $this->skills->getMalusToFightNumberWithProtective(
                $this->currentProperties->getWornBodyArmor(),
                $this->tables->getArmourer()
            );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberModifier += $this->skills->getMalusToFightNumberWithProtective(
            $this->currentProperties->getWornHelm(),
            $this->tables->getArmourer()
        );
        $shields = [];
        if ($this->weaponOrShieldInMainHand->isShield()) { // even if you use the shield in main hand as a weapon...
            $shields[] = $this->weaponOrShieldInMainHand;
        }
        if ($this->weaponOrShieldInOffhand->isShield()) { // even if you use the shield in offhand as a weapon...
            $shields[] = $this->weaponOrShieldInOffhand;
        }
        foreach ($shields as $shield) {
            /** @var ShieldCode $shield */
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithProtective(
                $shield,
                $this->tables->getArmourer()
            );
        }
        $weaponlikes = $this->getUsedWeaponlikes(); // weapons for attack or defense or shields for attack - NOT shields for defense
        foreach ($weaponlikes as $weaponlike) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                $weaponlike,
                $this->tables->getMissingWeaponSkillTable(),
                $this->fightsWithTwoWeapons()
            );
        }

        // weapon length effect - length of weapon is directly used as bonus to fight number (shields and ranged weapons have length zero)
        if ($this->usesSingleWeaponOrShield()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getLengthOfWeaponlike($this->getSingleWeaponOrShield());
        } else {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $mainHandLength = $this->tables->getArmourer()->getLengthOfWeaponlike($this->weaponOrShieldInMainHand);
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $offHandLength = $this->tables->getArmourer()->getLengthOfWeaponlike($this->weaponOrShieldInOffhand);
            $fightNumberModifier += max($mainHandLength, $offHandLength);
        }

        // combat actions effect
        $fightNumberModifier += $this->combatActions->getFightNumberModifier();

        return $fightNumberModifier;
    }

    /**
     * Gives weapons for attack or defense (including arms or legs) or shields for attack - NOT shields for defense.
     *
     * @return array|WeaponlikeCode[]
     */
    private function getUsedWeaponlikes()
    {
        $weaponlikes = [];
        if ($this->weaponOrShieldInMainHand->isShield()) {
            if ($this->combatActions->attacksByMainHandOnly()
                || $this->combatActions->attacksByTwoHands() // shield can be hold by both hands
            ) {
                $weaponlikes[] = $this->weaponOrShieldInMainHand; // shield in main hand is used for attack
            }
        } else {
            assert($this->weaponOrShieldInMainHand->isWeapon());
            if (!$this->weaponOrShieldInMainHand->isUnarmed()) { // standard weapon
                $weaponlikes[] = $this->weaponOrShieldInMainHand;
            } elseif ($this->combatActions->fightsByMainHandOnly()) {
                $weaponlikes[] = $this->weaponOrShieldInMainHand; // you use hand or leg (well, yes, leg can be main "hand")
            }
        }
        if ($this->weaponOrShieldInOffhand->isShield()) {
            if ($this->combatActions->attacksByOffhandOnly()
                || $this->combatActions->attacksByTwoHands() // shield can be hold by both hands
            ) {
                $weaponlikes[] = $this->weaponOrShieldInOffhand; // shield in offhand is used for attack
            }
        } else {
            assert($this->weaponOrShieldInOffhand->isWeapon());
            if (!$this->weaponOrShieldInOffhand->isUnarmed()) { // standard weapon
                $weaponlikes[] = $this->weaponOrShieldInOffhand;
            } elseif ($this->combatActions->fightsByOffhandOnly()) {
                $weaponlikes[] = $this->weaponOrShieldInOffhand; // you use hand or leg (well, yes, leg can be "offhand")
            }
        }

        return $weaponlikes;
    }

    /**
     * @return bool
     */
    private function usesSingleOneHandedWeaponByOneHand()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->usesSingleWeaponOrShield()
            && ($this->combatActions->fightsByMainHandOnly()
                || $this->combatActions->fightsByOffhandOnly()
            )
            && $this->tables->getArmourer()->canHoldItByOneHand($this->getSingleWeaponOrShield());
    }

    /**
     * If one-handed weapon or shield is kept by both hands, the required strength for weapon is lower
     * (fighter strength is considered higher respectively)
     * see details in PPH page 93, left column
     *
     * @param WeaponlikeCode $twoHandsBearableWeapon
     * @return Strength
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     */
    private function getStrengthForWeaponInTwoHands(WeaponlikeCode $twoHandsBearableWeapon)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (!$this->tables->getArmourer()->canHoldItByTwoHands($twoHandsBearableWeapon)) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Can not hold '{$twoHandsBearableWeapon}' by both hands or it has no effect."
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($this->tables->getArmourer()->isTwoHandedOnly($twoHandsBearableWeapon)) {
            return $this->currentProperties->getStrengthForMainHandOnly(); // it is both-hands only weapon, can NOT count +2 bonus
        }
        // If one-handed is kept by both hands, the required strength is lower (fighter strength is higher respectively)
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->currentProperties->getStrengthForMainHandOnly()->add(2);
    }

    /**
     * @return Strength
     * @throws Exceptions\NoOneHandActionChosen
     */
    private function getStrengthForSingleOneHandedWeapon()
    {
        if ($this->combatActions->attacksByMainHandOnly()) {
            return $this->getStrengthForMainHand();
        }
        if ($this->combatActions->attacksByOffhandOnly()) {
            return $this->getStrengthForOffhand();
        }
        throw new Exceptions\NoOneHandActionChosen(
            'Can not give strength for single weapon when no one hand attack action has been chose'
        );
    }

    /**
     * Final attack number including body state (level, fatigue, wounds, curses...), used weapon and action.
     *
     * @param Distance $targetDistance
     * @return AttackNumber
     * @throws Exceptions\NoAttackActionChosen
     */
    public function getAttackNumber(Distance $targetDistance)
    {
        $attackNumber = $this->createBaseAttackNumber();

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $attackNumber->add($this->getAttackNumberModifier($targetDistance));
    }

    /**
     * @return AttackNumber
     * @throws Exceptions\NoAttackActionChosen
     */
    private function createBaseAttackNumber()
    {
        if ($this->getWeaponUsedForAttack()->isShootingWeapon()) {
            return AttackNumber::createFromShooting(new Shooting($this->currentProperties->getKnack()));
        }
        assert($this->getWeaponUsedForAttack()->isMelee() || $this->getWeaponUsedForAttack()->isThrowingWeapon());

        return AttackNumber::createFromAttack(new Attack($this->currentProperties->getAgility()));
    }

    /**
     * @param Distance $targetDistance
     * @return int
     * @throws Exceptions\NoAttackActionChosen
     * @throws DistanceIsOutOfMaximalRange
     * @throws DistanceOutOfKnownValues
     * @throws EncounterRangeCanNotBeGreaterThanMaximalRange
     * @throws Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen
     */
    private function getAttackNumberModifier(Distance $targetDistance)
    {
        $attackNumberModifier = 0;
        $weaponUsedForAttack = $this->getWeaponUsedForAttack();
        $armourer = $this->tables->getArmourer();

        // strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $armourer->getAttackNumberMalusByStrengthWithWeaponlike(
            $weaponUsedForAttack,
            $this->getStrengthForWeaponUsedForAttack()
        );

        // skills effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $this->skills->getMalusToAttackNumberWithWeaponlike(
            $weaponUsedForAttack,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons()
        );

        // weapon effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumberModifier += $armourer->getOffensivenessOfWeaponlike($weaponUsedForAttack);

        // distance effect (for ranged only)
        if ($targetDistance > 1 && $weaponUsedForAttack->isRanged()) {
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
     * @return bool
     */
    private function fightsWithTwoWeapons()
    {
        return
            ($this->weaponOrShieldInMainHand->isWeapon()
                || ($this->weaponOrShieldInMainHand->isShield()
                    && ($this->combatActions->attacksByMainHandOnly()
                        || $this->combatActions->attacksByTwoHands()
                    )
                )
            )
            && ($this->weaponOrShieldInOffhand->isWeapon()
                || ($this->weaponOrShieldInOffhand->isShield()
                    && ($this->combatActions->attacksByOffhandOnly()
                        || $this->combatActions->attacksByTwoHands()
                    )
                )
            );
    }

    /**
     * Your defense WITHOUT weapon (@see getDefenseNumberWithOffhand and @see getDefenseNumberWithMainHand ).
     * Note: armor affects agility (can give restriction), but they do NOT affect defense number directly -
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
     * Your defense WITHOUT weapon (@see getDefenseNumberWithOffhand and @see getDefenseNumberWithMainHand ).
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
     *  - or just by a dodge (in that case use the pure @see getDefenseNumber ).
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithMainHand()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithMainHandModifier());
    }

    /**
     * @return int
     */
    private function getCoverWithMainHandModifier()
    {
        $coverModifier = 0;

        //strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponOrShieldInMainHand,
            $this->getStrengthForMainHand()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInMainHand);
        if ($this->weaponOrShieldInMainHand->isWeapon()) {
            assert($this->weaponOrShieldInMainHand instanceof WeaponCode);
            /** @var WeaponCode $weapon */
            $weapon = $this->weaponOrShieldInMainHand;
            // skill effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithWeapon(
                $weapon,
                $this->tables->getMissingWeaponSkillTable(),
                $this->fightsWithTwoWeapons()
            );
        } else { // even if you use shield as a weapon for attack, you are covering by it as a shield, of course
            // skill effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithShield($this->tables->getMissingShieldSkillTable());
        }

        return $coverModifier;
    }

    /**
     * @return DefenseNumber
     */
    public function getDefenseNumberWithMainHandAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithMainHandModifier());
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
    public function getDefenseNumberWithOffhand()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithOffhandModifier());
    }

    /**
     * @return int
     */
    private function getCoverWithOffhandModifier()
    {
        $coverModifier = 0;

        //strength effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponOrShieldInOffhand,
            $this->getStrengthForOffhand()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInOffhand);
        if ($this->weaponOrShieldInOffhand->isWeapon()) {
            assert($this->weaponOrShieldInOffhand instanceof WeaponCode);
            /** @var WeaponCode $weapon */
            $weapon = $this->weaponOrShieldInOffhand;
            // skill effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithWeapon(
                $weapon,
                $this->tables->getMissingWeaponSkillTable(),
                $this->fightsWithTwoWeapons()
            );
        } else { // even if you use shield as a weapon for attack, you are covering by it as a shield, of course
            // skill effect
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $coverModifier += $this->skills->getMalusToCoverWithShield($this->tables->getMissingShieldSkillTable());
        }

        return $coverModifier;
    }

    /**
     * @return DefenseNumber
     */
    public function getDefenseNumberWithOffhandAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithOffhandModifier());
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShooting()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumber(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberWithMainHandAgainstShooting()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberWithMainHand(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberWithOffhandAgainstShooting()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberWithOffhand(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @return DefenseNumberAgainstShooting
     * @throws \DrdPlus\CurrentProperties\Exceptions\MissingShieldInMainHand
     */
    public function getDefenseNumberAgainstShootingCoveredPassivelyByShieldInMainHand()
    {
        if (!$this->weaponOrShieldInMainHand->isShield()) {
            throw new Exceptions\MissingShieldInMainHand(
                'You have to hold a shield in main hand to get defense with shield.'
                . " You are holding {$this->weaponOrShieldInMainHand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInMainHand));
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @throws \DrdPlus\CurrentProperties\Exceptions\MissingShieldInOffhand
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingCoveredPassivelyByShieldInOffhand()
    {
        if (!$this->weaponOrShieldInOffhand->isShield()) {
            throw new Exceptions\MissingShieldInOffHand(
                'You have to hold a shield in offhand to get defense with shield.'
                . " You are holding {$this->weaponOrShieldInOffhand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInOffhand));
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
     * @throws \DrdPlus\CurrentProperties\Exceptions\MissingShieldInMainHand
     */
    public function getDefenseNumberAgainstShootingAndFasterOpponentCoveredPassivelyByShieldInMainHand()
    {
        if (!$this->weaponOrShieldInMainHand->isShield()) {
            throw new Exceptions\MissingShieldInMainHand(
                'You have to hold a shield in main hand to get defense with shield.'
                . " You are holding {$this->weaponOrShieldInMainHand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInMainHand));
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @throws \DrdPlus\CurrentProperties\Exceptions\MissingShieldInOffhand
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingAndFasterOpponentAndPassiveShieldInOffhand()
    {
        if (!$this->weaponOrShieldInOffhand->isShield()) {
            throw new Exceptions\MissingShieldInOffHand(
                'You have to hold a shield in offhand to get defense with shield.'
                . " You are holding {$this->weaponOrShieldInOffhand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponOrShieldInOffhand));
    }

    /**
     * Gives @see WoundsBonus - if you need Wounds just convert WoundsBonus to it by WoundsBonus->getWounds()
     *
     * @see Wounds
     * Note about both hands holding of a weapon - if you have empty off-hand (without shield) and the weapon you are
     * holding is single-hand, it will automatically add +2 for two-hand holding (if you choose such action).
     * See PPH page 92 right column.
     *
     * @return BaseOfWounds
     * @throws Exceptions\NoAttackActionChosen
     */
    public function getBaseOfWounds()
    {
        $baseOfWounds = 0;
        $weaponUsedForAttack = $this->getWeaponUsedForAttack();
        $strengthForWeaponUsedForAttack = $this->getStrengthForWeaponUsedForAttack();

        // strength and weapon effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsUsingWeaponlike(
            $weaponUsedForAttack,
            $strengthForWeaponUsedForAttack
        );

        // skill effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWounds += $this->skills->getMalusToBaseOfWoundsWithWeaponlike(
            $weaponUsedForAttack,
            $this->tables->getMissingWeaponSkillTable(),
            $this->fightsWithTwoWeapons()
        );

        // holding effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsBonusForHolding(
            $weaponUsedForAttack,
            $this->usesSingleWeaponInTwoHands()
        );

        // action effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWounds += $this->combatActions->getBaseOfWoundsModifier(
            $weaponUsedForAttack,
            $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getWeaponUsedForAttack())
        );

        return new BaseOfWounds($baseOfWounds, $this->tables->getWoundsTable());
    }

    /**
     * @return WeaponlikeCode
     * @throws Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen
     */
    private function getWeaponUsedForAttack()
    {
        if ($this->combatActions->attacksByMainHandOnly()) {
            return $this->weaponOrShieldInMainHand;
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            return $this->weaponOrShieldInOffhand;
        } else if ($this->combatActions->attacksByTwoHands()) {
            assert($this->usesSingleWeaponOrShield()); // you can not attack by two weapons simultaneously, see PPH page 108 left column
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return $this->getSingleWeaponOrShield();
        }
        throw new Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen(
            'Can not give weapon for attack with pacifist actions ' . $this->combatActions
        );
    }

    /**
     * @return Strength
     * @throws Exceptions\CanNotGiveStrengthForWeaponForAttackWhenNoAttackActionChosen
     */
    private function getStrengthForWeaponUsedForAttack()
    {
        if ($this->combatActions->attacksByMainHandOnly()) {
            return $this->getStrengthForMainHand();
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            return $this->getStrengthForOffhand();
        } else if ($this->combatActions->attacksByTwoHands()) {
            assert($this->usesSingleWeaponOrShield()); // you can not attack by two weapons simultaneously, see PPH page 108 left column
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return $this->getStrengthForWeaponInTwoHands($this->getSingleWeaponOrShield());
        }
        throw new Exceptions\CanNotGiveStrengthForWeaponForAttackWhenNoAttackActionChosen(
            'Can not give weapon for attack with pacifist actions ' . $this->combatActions
        );
    }

    /**
     * @return bool
     */
    private function usesSingleWeaponInTwoHands()
    {
        return $this->combatActions->attacksByTwoHands() && $this->usesSingleWeaponOrShield();
    }

    /**
     * @param RangedWeaponCode $rangedWeaponCode
     * @return LoadingInRounds
     */
    public function getLoadingInRoundsWithRangedWeapon(RangedWeaponCode $rangedWeaponCode)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new LoadingInRounds(
            $this->tables->getArmourer()->getLoadingInRoundsByStrengthWithRangedWeapon(
                $rangedWeaponCode,
                $this->getStrengthForMainHand()
            )
        );
    }

    /**
     * @return Strength
     */
    private function getStrengthForMainHand()
    {
        return $this->currentProperties->getStrengthForMainHandOnly();
    }

    /**
     * Your less-dominant hand is weaker - try it.
     *
     * @return Strength
     */
    private function getStrengthForOffhand()
    {
        return $this->currentProperties->getStrengthForOffhandOnly();
    }

    /**
     * Encounter range relates to weapon and strength for bows, speed for throwing weapons and nothing else for
     * crossbows. See PPH page 95 left column.
     * Also melee weapons have encounter range - calculated from their length parameter.
     * Note about SPEAR: if current weapon for attack is spear for melee, then range calculated from its length is used
     * instead of throwing range.
     *
     * @return EncounterRange
     * @throws Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen
     */
    public function getEncounterRange()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new EncounterRange(
            $this->tables->getArmourer()->getEncounterRangeWithWeaponlike(
                $this->getWeaponUsedForAttack(),
                $this->getStrengthForWeaponUsedForAttack(),
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
     * @throws Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen
     */
    public function getMaximalRange()
    {
        if ($this->getWeaponUsedForAttack()->isMelee()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return MaximalRange::createForMeleeWeapon($this->getEncounterRange()); // no change for melee weapons
        }

        assert($this->getWeaponUsedForAttack()->isRanged());

        return MaximalRange::createForRangedWeapon($this->getEncounterRange());
    }
}