<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\CombatActions\CombatActionCode;
use DrdPlus\Tables\Actions\CombatActionsCompatibilityTable;
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
}