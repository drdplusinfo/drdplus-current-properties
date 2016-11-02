<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ItemHoldingCode;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Armaments\Armourer;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

abstract class AbstractCombatProperties extends StrictObject
{
    /** @var CurrentProperties */
    protected $currentProperties;
    /** @var Skills */
    protected $skills;
    /** @var Tables */
    protected $tables;
    /** @var ItemHoldingCode */
    protected $weaponlikeHolding;
    /** @var WeaponlikeCode */
    protected $weaponlike;
    /** @var bool */
    protected $fightsWithTwoWeapons;

    /**
     * @param CurrentProperties $currentProperties
     * @param Skills $skills
     * @param Tables $tables
     * @param WeaponlikeCode $weaponlike
     * @param ItemHoldingCode $weaponHolding
     * @param bool $fightsWithTwoWeapons
     */
    protected function __construct(
        CurrentProperties $currentProperties,
        Skills $skills,
        Tables $tables,
        WeaponlikeCode $weaponlike,
        ItemHoldingCode $weaponHolding,
        $fightsWithTwoWeapons
    )
    {
        $this->currentProperties = $currentProperties;
        $this->skills = $skills;
        $this->tables = $tables;
        $this->guardHoldingCompatibleWithWeaponUsage(
            $weaponlike,
            $weaponHolding,
            $fightsWithTwoWeapons,
            $tables->getArmourer()
        );
        $this->guardWeaponOrShieldWearable($weaponlike, $this->getStrengthForWeaponOrShield($weaponlike, $weaponHolding));
        $this->weaponlike = $weaponlike;
        $this->weaponlikeHolding = $weaponHolding;
        $this->fightsWithTwoWeapons = $fightsWithTwoWeapons;
    }

    /**
     * @param WeaponlikeCode $weaponlikeForAttack
     * @param ItemHoldingCode $holding
     * @param bool $usesTwoWeapons
     * @param Armourer $armourer
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     * @throws \LogicException
     */
    private function guardHoldingCompatibleWithWeaponUsage(
        WeaponlikeCode $weaponlikeForAttack,
        ItemHoldingCode $holding,
        $usesTwoWeapons,
        Armourer $armourer
    )
    {
        if ($usesTwoWeapons && $holding->getValue() === ItemHoldingCode::TWO_HANDS) {
            throw new Exceptions\CanNotHoldItByTwoHands(
                "Can not hold weapon {$weaponlikeForAttack} by two hands when using two weapons"
            );
        }
        if ($holding->getValue() === ItemHoldingCode::TWO_HANDS) {
            if (!$armourer->canHoldItByTwoHands($weaponlikeForAttack)) {
                throw new \LogicException();
            }
        } else if (!$armourer->canHoldItByOneHand($weaponlikeForAttack)) {
            throw new \LogicException();
        }
    }

    /**
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $currentStrengthForWeapon
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    protected function guardWeaponOrShieldWearable(WeaponlikeCode $weaponlikeCode, Strength $currentStrengthForWeapon)
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
     * If one-handed weapon or shield is kept by both hands, the required strength for weapon is lower
     * (fighter strength is considered higher respectively), see details in PPH page 93, left column.
     *
     * @param WeaponlikeCode $weaponOrShield
     * @param ItemHoldingCode $holding
     * @return Strength
     * @throws \LogicException
     */
    protected function getStrengthForWeaponOrShield(WeaponlikeCode $weaponOrShield, ItemHoldingCode $holding)
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
                throw new \LogicException();
        }
    }

}