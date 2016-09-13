<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\CombatActions\CombatActionCode;
use DrdPlus\Codes\CombatActions\MeleeCombatActionCode;
use DrdPlus\Codes\CombatActions\RangedCombatActionCode;
use DrdPlus\Codes\WoundTypeCode;
use DrdPlus\Tables\Actions\CombatActionsCompatibilityTable;
use DrdPlus\Tables\Armaments\Partials\WeaponlikeTable;
use Granam\Strict\Object\StrictObject;
use Granam\Tools\ValueDescriber;

class CombatActions extends StrictObject implements \IteratorAggregate
{
    /**
     * @var array|CombatActionCode[]
     */
    private $combatActionCodes;

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @param CombatActionsCompatibilityTable $combatActionsCompatibilityTable
     * @throws \LogicException
     */
    public function __construct(
        array $combatActionCodes,
        CombatActionsCompatibilityTable $combatActionsCompatibilityTable
    )
    {
        $this->validateActionCodesCoWork($combatActionCodes, $combatActionsCompatibilityTable);
        $this->combatActionCodes = $combatActionCodes;
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @param CombatActionsCompatibilityTable $combatActionsCompatibilityTable
     * @throws \LogicException
     */
    private function validateActionCodesCoWork(
        array $combatActionCodes,
        CombatActionsCompatibilityTable $combatActionsCompatibilityTable
    )
    {
        $this->guardUsableForSameAttackTypes($combatActionCodes);
        $this->checkIncompatibleActions($combatActionCodes, $combatActionsCompatibilityTable);
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @throws \LogicException
     */
    private function guardUsableForSameAttackTypes(array $combatActionCodes)
    {
        $forMeleeOnly = [];
        $forRangedOnly = [];
        foreach ($combatActionCodes as $combatActionCode) {
            if (!($combatActionCode instanceof CombatActionCode)) {
                throw new \LogicException(
                    'Expected ' . CombatActionCode::class . ', got ' . ValueDescriber::describe($combatActionCode)
                );
            }
            if ($combatActionCode->isForMelee() && !$combatActionCode->isForRanged()) {
                $forMeleeOnly[] = $combatActionCode;
            }
            if ($combatActionCode->isForRanged() && !$combatActionCode->isForMelee()) {
                $forRangedOnly[] = $combatActionCode;
            }
        }
        if (count($forMeleeOnly) > 0 && count($forRangedOnly) > 0) {
            throw new \LogicException(
                'There are combat actions usable only for melee and another only for ranged, which prohibits their joining;'
                . ' melee: ' . implode(', ', $forMeleeOnly) . '; ranged: ' . implode(', ', $forRangedOnly)
            );
        }
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @param CombatActionsCompatibilityTable $combatActionsCompatibilityTable
     * @throws \LogicException
     */
    private function checkIncompatibleActions(
        array $combatActionCodes,
        CombatActionsCompatibilityTable $combatActionsCompatibilityTable
    )
    {
        $incompatible = [];
        foreach ($combatActionCodes as $combatActionCode) {
            foreach ($combatActionCodes as $anotherCombatActionCode) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                if (!$combatActionsCompatibilityTable->canCombineTwoActions($combatActionCode, $anotherCombatActionCode)) {
                    $incompatible[] = [$combatActionCode, $anotherCombatActionCode];
                }
            }
        }
        if ($incompatible) {
            throw new \LogicException(
                'There are incompatible combat actions: '
                . implode(', ', array_map(function (array $incompatiblePair) {
                        return $incompatiblePair[1] . ' with ' . $incompatiblePair[1];
                    }, $incompatible)
                )
            );
        }
    }

    /**
     * @return array|CombatActionCode[]
     */
    public function getCombatActionCodes()
    {
        return $this->combatActionCodes;
    }

    /**
     * @return \ArrayIterator|CombatActionCode[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->combatActionCodes);
    }

    /**
     * @return int
     */
    public function getFightNumberModifier()
    {
        $fightNumber = 0;
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === CombatActionCode::CONCENTRATION_ON_DEFENSE) {
                $fightNumber += 2;
            }
            if ($combatActionCode->getValue() === RangedCombatActionCode::AIMED_SHOT) {
                $fightNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::SWAP_WEAPONS) {
                $fightNumber -= 2;
            }
            if ($combatActionCode->getValue() === MeleeCombatActionCode::HANDOVER_ITEM) {
                $fightNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::LAYING) {
                $fightNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::SITTING_OR_ON_KNEELS) {
                $fightNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUTTING_ON_ARMOR) {
                $fightNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUTTING_ON_ARMOR_WITH_HELP) {
                $fightNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::HELPING_TO_PUT_ON_ARMOR) {
                $fightNumber -= 2;
            }
        }

        return $fightNumber;
    }

    /**
     * Note about AIMED SHOT, you have to sum bonus to attack number by yourself (maximum is +3).
     *
     * @return int
     */
    public function getAttackNumberModifier()
    {
        $attackNumber = 0;
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === MeleeCombatActionCode::HEADLESS_ATTACK) {
                $attackNumber += 2;
            }
            if ($combatActionCode->getValue() === MeleeCombatActionCode::PRESSURE) {
                $attackNumber += 2;
            }
            if ($combatActionCode->getValue() === RangedCombatActionCode::AIMED_SHOT) {
                $attackNumber += 1; // you have to sum those bonuses on attack after aiming yourself
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM) {
                $attackNumber += 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM) {
                $attackNumber += 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::LAYING) {
                $attackNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::SITTING_OR_ON_KNEELS) {
                $attackNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::BLINDFOLD_FIGHT) {
                $attackNumber -= 6;
            }
            if ($combatActionCode->getValue() === CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY) {
                $attackNumber -= 1;
            }
        }

        return $attackNumber;
    }

    /**
     * @param WeaponlikeCode $weaponlikeCode
     * @param WeaponlikeTable $weaponlikeTable
     * @return int
     */
    public function getBaseOfWoundsModifier(
        WeaponlikeCode $weaponlikeCode,
        WeaponlikeTable $weaponlikeTable
    )
    {
        $baseOfWounds = 0;
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === MeleeCombatActionCode::HEADLESS_ATTACK) {
                $baseOfWounds += 2;
            }
            if ($combatActionCode->getValue() === MeleeCombatActionCode::FLAT_ATTACK
                && $weaponlikeTable->getWoundsTypeOf($weaponlikeCode) !== WoundTypeCode::CRUSH
            ) {
                $baseOfWounds -= 6;
            }
        }

        return $baseOfWounds;
    }

    /**
     * Note about RUN: if someone attacks you before you RUN, than you have to choose between canceling of RUN and running
     * with malus -4 to defense number and without weapon.
     * Note about PUTTING OUT HARDLY ACCESSIBLE ITEM: whenever someone attacks you before you put out desired item,
     * than you have to choose between canceling of PUT OUT... and continuing with malus -4 to defense number and without weapon,
     * Note about ATTACKED FROM BEHIND: if you are surprised then you can not defense yourself and your defense roll is automatically zero.
     *
     * @see getDefenseNumberModifierAgainstFasterOpponent
     *
     * @return int
     */
    public function getDefenseNumberModifier()
    {
        $defenseNumber = 0;
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === MeleeCombatActionCode::HEADLESS_ATTACK) {
                $defenseNumber -= 5;
            }
            if ($combatActionCode->getValue() === CombatActionCode::CONCENTRATION_ON_DEFENSE) {
                $defenseNumber += 2;
            }
            if ($combatActionCode->getValue() === MeleeCombatActionCode::PRESSURE) {
                $defenseNumber -= 1;
            }
            if ($combatActionCode->getValue() === MeleeCombatActionCode::RETREAT) {
                $defenseNumber += 1;
            }
            if ($combatActionCode->getValue() === CombatActionCode::LAYING) {
                $defenseNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::SITTING_OR_ON_KNEELS) {
                $defenseNumber -= 2;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUTTING_ON_ARMOR) {
                $defenseNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUTTING_ON_ARMOR_WITH_HELP) {
                $defenseNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::ATTACKED_FROM_BEHIND) {
                $defenseNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::BLINDFOLD_FIGHT) {
                $defenseNumber -= 10;
            }
            if ($combatActionCode->getValue() === CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY) {
                $defenseNumber -= 2;
            }
        }

        return $defenseNumber;
    }

    public function getDefenseNumberModifierAgainstFasterOpponent()
    {
        $defenseNumber = self::getDefenseNumberModifier();
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === CombatActionCode::RUN) {
                $defenseNumber -= 4;
            }
            if ($combatActionCode->getValue() === CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM) {
                $defenseNumber -= 4;
            }
        }
    }

    /**
     * @return int
     */
    public function getSpeedBonus()
    {
        $speedBonus = 0;
        foreach ($this->combatActionCodes as $combatActionCode) {
            if ($combatActionCode->getValue() === CombatActionCode::MOVE) {
                /** see PPH page 107 left column */
                $speedBonus += 8; // can not be combined with run, but that should be solved in constructor
            }
            if ($combatActionCode->getValue() === CombatActionCode::RUN) {
                /** see PPH page 107 left column */
                $speedBonus += 22; // can not be combined with move, but that should be solved in constructor
            }
        }

        return $speedBonus;
    }
}