<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\MeleeWeaponCode;
use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
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

class FightProperties extends StrictObject
{
    /** @var CombatActions */
    private $combatActions;

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
     * @throws \DrdPlus\CurrentProperties\Exceptions\InvalidCombatActionFormat
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActionsWithWeaponType
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    public function __construct(
        CurrentProperties $currentProperties,
        CombatActions $combatActions,
        Skills $skills,
        Tables $tables,
        WeaponlikeCode $weaponlike,
        ItemHoldingCode $weaponlikeHolding,
        $fightsWithTwoWeapons,
        ShieldCode $shield
    )
    {
        $attackProperties = new AttackProperties(
            $currentProperties,
            $skills,
            $tables,
            $weaponlike,
            $weaponlikeHolding,
            $fightsWithTwoWeapons
        );
        $defenseProperties = new DefenseProperties(
            $currentProperties,
            $skills,
            $tables,
            $weaponlike,
            $weaponlikeHolding,
            $fightsWithTwoWeapons,
            $shield
        );
        $this->currentProperties = $currentProperties;
        $this->skills = $skills;
        $this->tables = $tables;
        $this->combatActions = $combatActions;
        // weapon-likes for attack
        $this->guardAttackActionsCompatibleWithWeapon($weaponlikeForAttackInMainHand, true /* main hand */);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponlikeForAttackInMainHand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponlikeForAttackInOffhand);
        $this->guardAttackActionsCompatibleWithWeapon($weaponlikeForAttackInOffhand, false /* offhand */);
        $this->guardWeaponsAndShieldsWearable($weaponlikeForAttackInMainHand, $weaponlikeForAttackInOffhand);
        $this->weaponlikeForAttackInMainHand = $weaponlikeForAttackInMainHand;
        $this->weaponlikeForAttackInOffhand = $weaponlikeForAttackInOffhand;
        // weapons and shields for defense
        $this->guardWeaponsUsableTogether($weaponOrShieldForDefenseInMainHand, $weaponOrShieldForDefenseInOffhand, false /* not for attack */);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldForDefenseInMainHand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldForDefenseInOffhand);
        $this->guardWeaponsAndShieldsWearable($weaponOrShieldForDefenseInMainHand, $weaponOrShieldForDefenseInOffhand);
        $this->weaponOrShieldForDefenseInMainHand = $weaponOrShieldForDefenseInMainHand;
        $this->weaponOrShieldForDefenseInOffhand = $weaponOrShieldForDefenseInOffhand;
    }

    /**
     * @param WeaponlikeCode $weaponOrShieldInMainHand
     * @param WeaponlikeCode|null $weaponOrShieldInOffhand
     * @param bool $forAttack
     * @throws Exceptions\CanNotHoldItByTwoHands
     */
    private function guardWeaponsUsableTogether(
        WeaponlikeCode $weaponOrShieldInMainHand = null,
        WeaponlikeCode $weaponOrShieldInOffhand = null,
        $forAttack
    )
    {
        if ($weaponOrShieldInMainHand === null && $weaponOrShieldInOffhand === null) {
            return;
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($weaponOrShieldInOffhand !== null // offhand is NOT empty
            && $this->tables->getArmourer()->isTwoHandedOnly($weaponOrShieldInMainHand)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Main-hand weapon {$weaponOrShieldInMainHand} is two-handed only but second hand is occupied by {$weaponOrShieldInOffhand}"
                . ($weaponOrShieldInOffhand->isUnarmed() ? ' (even this is a weapon, because you say so)' : '')
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if ($weaponOrShieldInMainHand !== null // main hand is NOT empty
            && $this->tables->getArmourer()->isTwoHandedOnly($weaponOrShieldInOffhand)
        ) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Offhand weapon {$weaponOrShieldInOffhand} is two-handed only but second hand is occupied by {$weaponOrShieldInMainHand}"
                . ($weaponOrShieldInMainHand->isUnarmed() ? ' (even this is a weapon, because you say so)' : '')
            );
        }
        if (!$forAttack || !$weaponOrShieldInMainHand || !$weaponOrShieldInOffhand) {
            return;
        }
        if (($weaponOrShieldInMainHand->isShootingWeapon() && !$weaponOrShieldInOffhand->isShootingWeapon())
            || (!$weaponOrShieldInMainHand->isShootingWeapon() && $weaponOrShieldInOffhand->isShootingWeapon())
        ) {
            throw new \LogicException('Can not combine shooting and non-shooting weapon for attack');
        }
    }

    /**
     * @param CombatActions $currentCombatActions
     * @param WeaponlikeCode $weaponOrShield
     * @throws \DrdPlus\CurrentProperties\Exceptions\InvalidCombatActionFormat
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActions
     * @throws \DrdPlus\CurrentProperties\Exceptions\IncompatibleCombatActionsWithWeaponType
     */
    private function guardActionsCompatibleWithWeapon(
        CombatActions $currentCombatActions,
        WeaponlikeCode $weaponOrShield = null
    )
    {
        if (!$weaponOrShield) {
            return;
        }
        $availableCombatActions = new CombatActions(
            $this->tables->getCombatActionsWithWeaponTypeCompatibilityTable()
                ->getActionsPossibleWhenFightingWith($weaponOrShield),
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
     * @param WeaponlikeCode $weaponlike
     * @param bool $isForMainHand
     * @throws \LogicException
     */
    private function guardAttackActionsCompatibleWithWeapon(WeaponlikeCode $weaponlike = null, $isForMainHand)
    {
        if ($weaponlike) {
            return;
        }
        if ($isForMainHand) {
            if (!$weaponlike && $this->combatActions->attacksByMainHandOnly()) {
                throw new \LogicException();
            }
        } else {
            if (!$weaponlike && $this->combatActions->attacksByOffhandOnly()) {
                throw new \LogicException();
            }
        }
    }

    /**
     * @param WeaponlikeCode $weaponOrShieldInMainHand
     * @param WeaponlikeCode $weaponOrShieldInOffhand
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponsAndShieldsWearable(
        WeaponlikeCode $weaponOrShieldInMainHand = null,
        WeaponlikeCode $weaponOrShieldInOffhand = null
    )
    {
        if ($weaponOrShieldInMainHand === null && $weaponOrShieldInOffhand === null) {
            return;
        }
        $singleWeaponlike = $this->findSingleWeaponlike($weaponOrShieldInMainHand, $weaponOrShieldInMainHand);
        if ($singleWeaponlike) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $this->guardWeaponOrShieldWearable($singleWeaponlike, $this->getStrengthForWeaponInTwoHands($singleWeaponlike));

            return;
        }
        $this->guardWeaponOrShieldWearable($weaponOrShieldInMainHand, $this->currentProperties->getStrengthForMainHandOnly());
        $this->guardWeaponOrShieldWearable($weaponOrShieldInOffhand, $this->currentProperties->getStrengthForOffhandOnly());
    }

    /**
     * @param WeaponlikeCode|null $weaponOrShieldInMainHand
     * @param WeaponlikeCode|null $weaponOrShieldInOffhand
     * @return WeaponlikeCode|null
     */
    private function findSingleWeaponlike(
        WeaponlikeCode $weaponOrShieldInMainHand = null,
        WeaponlikeCode $weaponOrShieldInOffhand = null)
    {
        if ($weaponOrShieldInMainHand && !$weaponOrShieldInOffhand) {
            return $weaponOrShieldInMainHand;
        } else if (!$weaponOrShieldInMainHand && $weaponOrShieldInOffhand) {
            return $weaponOrShieldInOffhand;
        }

        return null;
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
        $fightNumberModifier += $this->getFightNumberMalusByStrength();

        // skills effect
        $fightNumberModifier += $this->getFightNumberMalusBySkills();

        // weapon length effect - length of weapon is directly used as bonus to fight number (shields and ranged weapons have length zero)
        $fightNumberModifier += $this->getFightNumberBonusByWeaponLength();

        // combat actions effect
        $fightNumberModifier += $this->combatActions->getFightNumberModifier();

        return $fightNumberModifier;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusByStrength()
    {
        return max( // lesser malus is used (negative number closer to zero)
            $this->getFightNumberMalusByStrengthFor(
                $this->weaponlikeForAttackInMainHand,
                $this->weaponlikeForAttackInOffhand
            ),
            $this->getFightNumberMalusByStrengthFor(
                $this->weaponOrShieldForDefenseInMainHand,
                $this->weaponOrShieldForDefenseInOffhand
            )
        );
    }

    /**
     * @param WeaponlikeCode|null $mainHand
     * @param WeaponlikeCode|null $offhand
     * @return int
     */
    private function getFightNumberMalusByStrengthFor(WeaponlikeCode $mainHand = null, WeaponlikeCode $offhand = null)
    {
        if (!$mainHand && !$offhand) {
            return 0; // no weapons at all, so no malus at all
        }
        $singleWeaponlike = $this->findSingleWeaponlike($mainHand, $offhand);
        if ($singleWeaponlike) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            return $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield( // single weapon
                $singleWeaponlike,
                $this->getStrengthForWeaponInTwoHands($singleWeaponlike)
            );
        }
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus = $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $mainHand,
            $this->getStrengthForMainHand()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberMalus += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $offhand,
            $this->getStrengthForOffhand()
        );

        return $fightNumberMalus; // sum of strength maluses from both weapons
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
        $shields = [];
        if ($this->weaponlikeForAttackInMainHand->isShield()) { // EVEN IF you use the shield in main hand as a weapon...
            $shields[] = $this->weaponlikeForAttackInMainHand;
        }
        if ($this->weaponlikeForAttackInOffhand->isShield()) { // EVEN IF you use the shield in offhand as a weapon...
            $shields[] = $this->weaponlikeForAttackInOffhand;
        }
        if ($this->weaponOrShieldForDefenseInMainHand->isShield()) {
            $shields[] = $this->weaponOrShieldForDefenseInMainHand;
        }
        if ($this->weaponOrShieldForDefenseInOffhand->isShield()) {
            $shields[] = $this->weaponOrShieldForDefenseInOffhand;
        }
        foreach ($shields as $shield) {
            /** @var ShieldCode $shield */
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberMalus += $this->skills->getMalusToFightNumberWithProtective(
                $shield,
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
        $weaponlikePairs = [];
        $weaponlikePairForAttack = [];
        if ($this->weaponlikeForAttackInMainHand) { // if it is a shield than it is used for attack
            $weaponlikePairForAttack[] = $this->weaponlikeForAttackInMainHand;
        }
        if ($this->weaponlikeForAttackInOffhand) { // if it is a shield than it is used for attack
            $weaponlikePairForAttack[] = $this->weaponlikeForAttackInOffhand;
        }
        $weaponlikePairs[] = $weaponlikePairForAttack;
        $weaponlikePairForDefense = [];
        if ($this->weaponOrShieldForDefenseInMainHand && $this->weaponOrShieldForDefenseInMainHand->isWeapon()) {
            assert(!$this->weaponOrShieldForDefenseInMainHand->isShield());
            $weaponlikePairForDefense[] = $this->weaponOrShieldForDefenseInMainHand;
        }
        if ($this->weaponOrShieldForDefenseInOffhand && $this->weaponOrShieldForDefenseInOffhand->isWeapon()) {
            assert(!$this->weaponOrShieldForDefenseInOffhand->isShield());
            $weaponlikePairForDefense[] = $this->weaponOrShieldForDefenseInOffhand;
        }
        $weaponlikePairs[] = $weaponlikePairForDefense;

        $fightNumberMalus = 0;
        foreach ($weaponlikePairs as $weaponlikePair) {
            /** @var array $weaponlikePair */
            foreach ($weaponlikePair as $weaponlike) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberMalus += $this->skills->getMalusToFightNumberWithWeaponlike(
                    $weaponlike,
                    $this->tables->getMissingWeaponSkillTable(),
                    count($weaponlikePair) === 2 // fights with two weapons
                );
            }
        }

        return $fightNumberMalus;
    }

    /**
     * @return int
     */
    private function getFightNumberBonusByWeaponLength()
    {
        $weaponsAndShields = [];
        if ($this->weaponlikeForAttackInMainHand) {
            $weaponsAndShields = [$this->weaponlikeForAttackInMainHand];
        }
        if ($this->weaponlikeForAttackInOffhand) {
            $weaponsAndShields = [$this->weaponlikeForAttackInOffhand];
        }
        if ($this->weaponOrShieldForDefenseInMainHand) {
            $weaponsAndShields = [$this->weaponOrShieldForDefenseInMainHand];
        }
        if ($this->weaponOrShieldForDefenseInOffhand) {
            $weaponsAndShields = [$this->weaponOrShieldForDefenseInOffhand];
        }
        $lengths = [];
        foreach ($weaponsAndShields as $weaponOrShield) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $lengths[] = $this->tables->getArmourer()->getLengthOfWeaponlike($weaponOrShield);
        }

        return max($lengths); // length of a weapon is directly used as a bonus to fight number
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
        if (($this->weaponlikeForAttackInMainHand && $this->weaponlikeForAttackInMainHand->isShootingWeapon())
            || ($this->weaponlikeForAttackInOffhand && $this->weaponlikeForAttackInOffhand->isShootingWeapon())
        ) {
            /** shooting and melee or throwing weapons can not be combined, @see guardWeaponsUsableTogether */
            return AttackNumber::createFromShooting(new Shooting($this->currentProperties->getKnack()));
        }

        // covers no-weapon-at-all and melee or throwing weapons
        return AttackNumber::createFromAttack(new Attack($this->currentProperties->getAgility()));
    }

    /**
     * @return WeaponlikeCode|null
     */
    private function getWeaponUsedForAttack()
    {
        if (!$this->weaponlikeForAttackInMainHand && !$this->weaponlikeForAttackInOffhand) {
            return null;
        }
        $singleWeaponlike = $this->findSingleWeaponlike($this->weaponlikeForAttackInMainHand, $this->weaponlikeForAttackInOffhand);
        if ($singleWeaponlike) {
            return $singleWeaponlike;
        }
        if ($this->combatActions->attacksByMainHandOnly()) {
            /** if there is an attack action, there HAS TO be a weapon @see guardAttackActionsCompatibleWithWeapon */
            return $this->weaponlikeForAttackInMainHand;
        }
        if ($this->combatActions->attacksByOffhandOnly()) {
            /** if there is an attack action, there HAS TO be a weapon @see guardAttackActionsCompatibleWithWeapon */
            return $this->weaponlikeForAttackInOffhand;
        }
        if ($this->weaponlikeForAttackInMainHand) {
            return $this->weaponlikeForAttackInMainHand; // two hands attack
        }
        if ($this->weaponlikeForAttackInOffhand) {
            return $this->weaponlikeForAttackInOffhand; // two hands attack
        }

        return null; // no planned attack at all and therefore weapon used for attack is not known
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
            $this->weaponlikeForAttackInMainHand,
            $this->getStrengthForMainHand()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInMainHand);
        if ($this->weaponlikeForAttackInMainHand->isWeapon()) {
            assert($this->weaponlikeForAttackInMainHand instanceof WeaponCode);
            /** @var WeaponCode $weapon */
            $weapon = $this->weaponlikeForAttackInMainHand;
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
            $this->weaponlikeForAttackInOffhand,
            $this->getStrengthForOffhand()
        );

        // weapon or shield effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $coverModifier += $this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInOffhand);
        if ($this->weaponlikeForAttackInOffhand->isWeapon()) {
            assert($this->weaponlikeForAttackInOffhand instanceof WeaponCode);
            /** @var WeaponCode $weapon */
            $weapon = $this->weaponlikeForAttackInOffhand;
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
        if (!$this->weaponlikeForAttackInMainHand->isShield()) {
            throw new Exceptions\MissingShieldInMainHand(
                'You have to hold a shield in main hand to get defense with shield.'
                . " You are holding {$this->weaponlikeForAttackInMainHand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInMainHand));
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
        if (!$this->weaponlikeForAttackInOffhand->isShield()) {
            throw new Exceptions\MissingShieldInOffHand(
                'You have to hold a shield in offhand to get defense with shield.'
                . " You are holding {$this->weaponlikeForAttackInOffhand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInOffhand));
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
        if (!$this->weaponlikeForAttackInMainHand->isShield()) {
            throw new Exceptions\MissingShieldInMainHand(
                'You have to hold a shield in main hand to get defense with shield.'
                . " You are holding {$this->weaponlikeForAttackInMainHand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInMainHand));
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
        if (!$this->weaponlikeForAttackInOffhand->isShield()) {
            throw new Exceptions\MissingShieldInOffHand(
                'You have to hold a shield in offhand to get defense with shield.'
                . " You are holding {$this->weaponlikeForAttackInOffhand} instead"
            );
        }

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstShooting()
            ->add($this->tables->getArmourer()->getCoverOfWeaponOrShield($this->weaponlikeForAttackInOffhand));
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
            return $this->getStrengthForWeaponInTwoHands($this->getSingleWeaponlikeForAttack());
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