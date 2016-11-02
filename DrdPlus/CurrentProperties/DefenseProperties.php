<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Tables;

class DefenseProperties extends AbstractCombatProperties
{
    /**
     * @var ShieldCode
     */
    private $shield;

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
     * @param WeaponlikeCode $weaponlikeForDefense
     * @param ItemHoldingCode $weaponHolding
     * @param bool $fightsWithTwoWeapons
     * @param ShieldCode|null $shield = null
     */
    public function __construct(
        CurrentProperties $currentProperties,
        Skills $skills,
        Tables $tables,
        WeaponlikeCode $weaponlikeForDefense,
        ItemHoldingCode $weaponHolding,
        $fightsWithTwoWeapons,
        ShieldCode $shield /** use @see ShieldCode::WITHOUT_SHIELD for no shield */
    )
    {
        $this->guardWeaponOrShieldWearable(
            $shield,
            $this->getStrengthForWeaponOrShield($shield, $this->getShieldHolding($weaponHolding))
        );
        $this->shield = $shield;
        parent::__construct($currentProperties, $skills, $tables, $weaponlikeForDefense, $weaponHolding, $fightsWithTwoWeapons);
    }

    /**
     * Gives holding opposite to given weapon holding.
     *
     * @param ItemHoldingCode $weaponlikeHolding
     * @return ItemHoldingCode
     * @throws \LogicException
     */
    private function getShieldHolding(ItemHoldingCode $weaponlikeHolding)
    {
        if ($weaponlikeHolding->holdsByMainHand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::OFFHAND);
        }
        if ($weaponlikeHolding->holdsByOffhand()) {
            return ItemHoldingCode::getIt(ItemHoldingCode::MAIN_HAND);
        }
        throw new \LogicException(
            "Can not give holding to a shield when holding {$weaponlikeHolding} by two hands"
        );
    }

    /**
     * Modification of defense WITHOUT weapon (@see getDefenseNumberModifierWithShield and @see
     * getDefenseNumberWithMainHand ). Note: armor affects agility (can give restriction), but they do NOT affect
     * defense number directly - its protection is used after hit to lower final damage.
     *
     * @return int
     */
    public function getDefenseNumberModifier()
    {
        return $this->currentProperties->getAgility()->getValue();
    }

    /**
     * You have to choose
     *  - if cover by shield (can twice per round even if already attacked)
     *  - or by weapon (can only once per round and only if you have attacked before defense or if you simply did not
     * used this weapon yet)
     *  - or just by a dodge (in that case use the pure @see getDefenseNumberModifier ).
     *
     * @return int
     */
    public function getDefenseNumberModifierWithWeaponlike()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberModifier() + $this->getCoverWithWeaponlike();
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
     * You have to choose
     *  - if cover by shield (can twice per round even if already attacked)
     *  - or by weapon (can only once per round and only if you have attacked before defense or if you simply did not
     * used this weapon yet)
     *  - or just by a dodge (in that case use the pure @see getDefenseNumberModifier ).
     * Note about offhand - even shield is affected by lower strength of your offhand lower strength (-2).
     *
     * @return int
     * @throws \LogicException
     */
    public function getDefenseNumberModifierWithShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberModifier() + $this->getCoverWithShield();
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
}