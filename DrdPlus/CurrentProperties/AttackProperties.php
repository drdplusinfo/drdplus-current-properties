<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\MeleeWeaponCode;
use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Properties\Combat\EncounterRange;
use DrdPlus\Properties\Combat\LoadingInRounds;
use DrdPlus\Properties\Combat\MaximalRange;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Armaments\Exceptions\DistanceIsOutOfMaximalRange;
use DrdPlus\Tables\Armaments\Exceptions\EncounterRangeCanNotBeGreaterThanMaximalRange;
use DrdPlus\Tables\Environments\Exceptions\DistanceOutOfKnownValues;
use DrdPlus\Tables\Measurements\Distance\Distance;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus;
use DrdPlus\Tables\Tables;

class AttackProperties extends AbstractCombatProperties
{
    /**
     * Warning: to define a weapon used by two hands, do NOT set the second weapon (provide NULL).
     * If you will provide @see MeleeWeaponCode::HAND or similar "empty" hand, it will be considered as a standard
     * weapon.
     * Whenever you provide two weapons for attack or defense then two weapons fighting will be taken into account,
     * even if you give two "empty" hands like @see MeleeWeaponCode::LEG it is taken as two weapons.
     * Note: you can not attack by two weapons simultaneously, see PPH page 108 left column
     *
     * @param CurrentProperties $currentProperties
     * @param Skills $skills
     * @param Tables $tables
     * @param WeaponlikeCode $weaponlikeForAttack
     * @param ItemHoldingCode $holding
     * @param bool $fightsWithTwoWeapons
     */
    public function __construct(
        CurrentProperties $currentProperties,
        Skills $skills,
        Tables $tables,
        WeaponlikeCode $weaponlikeForAttack,
        ItemHoldingCode $holding,
        $fightsWithTwoWeapons
    )
    {
        parent::__construct($currentProperties, $skills, $tables, $weaponlikeForAttack, $holding, $fightsWithTwoWeapons);
    }

    /**
     * Fight number update according to weapon.
     *
     * @return int
     */
    public function getFightNumberModifier()
    {
        $fightNumberModifier = 0;

        // strength effect
        $fightNumberModifier += $this->getFightNumberMalusByStrength();

        // skills effect
        $fightNumberModifier += $this->getFightNumberMalusBySkills();

        // weapon length effect - length of weapon is directly used as bonus to fight number (shields and ranged weapons have length zero)
        $fightNumberModifier += $this->getFightNumberBonusByWeaponLength();

        return $fightNumberModifier;
    }

    /**
     * @return int
     */
    private function getFightNumberMalusByStrength()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponOrShield(
            $this->weaponlike,
            $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
        );
    }

    /**
     * @return int
     */
    private function getFightNumberMalusBySkills()
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
    private function getFightNumberBonusByWeaponLength()
    {
        // length of a weapon is directly used as a bonus to fight number
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->tables->getArmourer()->getLengthOfWeaponlike($this->weaponlike);
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
    public function getAttackNumberModifier(Distance $targetDistance)
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
     * @return int
     * @throws Exceptions\NoAttackActionChosen
     */
    public function getBaseOfWoundsModifier()
    {
        $baseOfWoundsModifier = 0;
        $strengthForWeaponUsedForAttack = $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding);

        // strength and weapon effects
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWoundsModifier += $this->tables->getArmourer()->getBaseOfWoundsUsingWeaponlike(
            $this->weaponlike,
            $strengthForWeaponUsedForAttack
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

        return $baseOfWoundsModifier;
    }

    /**
     * @return bool|LoadingInRounds
     */
    public function getLoadingInRounds()
    {
        if (!$this->weaponlike->isRanged()) {
            return false;
        }
        /** @var RangedWeaponCode $rangedWeaponCode */
        $rangedWeaponCode = $this->weaponlike;

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new LoadingInRounds(
            $this->tables->getArmourer()->getLoadingInRoundsByStrengthWithRangedWeapon(
                $rangedWeaponCode,
                $this->getStrengthForWeaponOrShield($this->weaponlike, $this->weaponlikeHolding)
            )
        );
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
     * @throws Exceptions\CanNotGiveWeaponForAttackWhenNoAttackActionChosen
     */
    public function getMaximalRange()
    {
        if ($this->weaponlike->isMelee()) {
            return MaximalRange::createForMeleeWeapon($this->getEncounterRange()); // no change for melee weapons
        }

        return MaximalRange::createForRangedWeapon($this->getEncounterRange());
    }
}