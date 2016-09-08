<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\CombatActions\CombatActionCode;
use DrdPlus\Codes\CombatActions\MeleeCombatActionCode;
use DrdPlus\Codes\CombatActions\RangedCombatActionCode;
use Granam\Strict\Object\StrictObject;

class CombatActions extends StrictObject implements \IteratorAggregate
{
    /**
     * @var array|\DrdPlus\Codes\CombatActions\CombatActionCode[]
     */
    private $combatActionCodes;

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @throws \LogicException
     */
    public function __construct(array $combatActionCodes)
    {
        $this->validateActionCodesCoWork($combatActionCodes);
        $this->combatActionCodes = $combatActionCodes;
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @throws \LogicException
     */
    private function validateActionCodesCoWork(array $combatActionCodes)
    {
        $this->guardUsableForSameAttackTypes($combatActionCodes);
        $this->checkIncompatibleActions($combatActionCodes);
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @throws \LogicException
     */
    private function guardUsableForSameAttackTypes(array $combatActionCodes)
    {
        $canBeUsedForMelee = true;
        $canBeUsedForRanged = true;
        foreach ($combatActionCodes as $combatActionCode) {
            $currentActionIsForMelee = $combatActionCode->isForMelee();
            if (!$canBeUsedForMelee && $currentActionIsForMelee) {
                throw new \LogicException();
            }
            $canBeUsedForMelee = $currentActionIsForMelee;

            $currentActionIsForRanged = $combatActionCode->isForRanged();
            if (!$canBeUsedForRanged && $currentActionIsForRanged) {
                throw new \LogicException();
            }
            $canBeUsedForRanged = $currentActionIsForRanged;
        }
    }

    /**
     * @param array|CombatActionCode[] $combatActionCodes
     * @throws \LogicException
     */
    private function checkIncompatibleActions(array $combatActionCodes)
    {
        $actionValuesToCheck = [];
        foreach ($combatActionCodes as $combatActionCode) {
            $actionValuesToCheck[] = $combatActionCode->getValue();
        }
        foreach ($combatActionCodes as $combatActionCode) {
            $allowedActionValues = array_keys(self::getAllowedCombatActionsFor($combatActionCode));
            $prohibitedActionValues = array_diff($actionValuesToCheck, $allowedActionValues);
            if (count($prohibitedActionValues) > 0) {
                throw new \LogicException();
            }
        }
    }

    /**
     * @param CombatActionCode $combatActionCode
     * @return array|CombatActionCode[]
     * @throws \InvalidArgumentException
     */
    public static function getAllowedCombatActionsFor(CombatActionCode $combatActionCode)
    {
        switch ($combatActionCode->getValue()) {
            case MeleeCombatActionCode::HEADLESS_ATTACK : // do not think, just do it! (no defense or some smart action)
                return [
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    // you can not RUN when attacking
                    // you can not use STANDARD ATTACK when using headless attack, you know?
                    // you choose attacking, therefore can not SWAP WEAPONS
                    // you are in attack, so you can not CONCENTRATE ON DEFENSE
                    // you do not have time for PUTTING OUT even EASILY ACCESSIBLE ITEM when you are attacking
                    // you do not have time for PUTTING OUT HARDLY ACCESSIBLE ITEM when you are attacking
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING), // it is quite strange, but in fact is not hard to be fury even laying on back
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    // you decide to attack, there is not time to try GET UP
                    // do not even think about that, PUTTING ON ARMOR when you are in attack? no way
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    // you are fury and headlessly attacking, no time for thoughts about COVER OF an ALLY
                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    // FLAT ATTACKING? You mean using head on headless attack? No
                    // even PRESSURE require some thinking which your headless attack does not allow
                    // you coward! you are in headless attack, u should think about RETREAT before
                    MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT),
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),
                    // headless AIM SHOT is possible only in theatre
                ];
            case MeleeCombatActionCode::COVER_OF_ALLY : // no fight is possible
                return [
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    // you can not RUN when attacking
                    // you can not use STANDARD ATTACK on covering an ally
                    // you need weapons for defense, can not SWAP WEAPONS
                    // CONCENTRATION ON DEFENSE is not possible - covering of an ally requires concentration itself
                    CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM),
                    // PUTTING OUT HARDLY ACCESSIBLE ITEM requires both hands, but you need at least one hand to cover an ally
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING), // if you are laying close enough, you can still cover someone ny your weapon
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    // you are GETTING UP or covering and ally, and you made your choice already, remember?
                    // no, you can not PUTTING ON ARMOR when you are covering someone
                    // you simply can not attack, even ATTACK FROM BEHIND is impossible when covering an ally
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    // FLAT ATTACK is an attack and those are not possible on covering an ally
                    // PRESSURE is part of an attack and attacks are not possible
                    MeleeCombatActionCode::RETREAT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::RETREAT),
                    // ATTACK ON DISABLED opponent is same dishonest as impossible when covering and ally
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    // are you covering or trying AIM SHOT ?
                ];
            case MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS :
                return [
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    // you can not RUN when fighting
                    CombatActionCode::STANDARD_ATTACK => CombatActionCode::getIt(CombatActionCode::STANDARD_ATTACK),
                    // you are fighting, not SWAPPING WEAPONS
                    CombatActionCode::CONCENTRATION_ON_DEFENSE => CombatActionCode::getIt(CombatActionCode::CONCENTRATION_ON_DEFENSE),
                    // you choose both weapons, so can not PUT OUT even EASILY ACCESSIBLE ITEM
                    // you choose both weapons, so can not PUT OUT HARDLY ACCESSIBLE ITEM
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING),
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    // you can attack or GETTING UP, not both
                    // only gods are able to PUTTING ON ARMOR and attack simultaneously
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    MeleeCombatActionCode::HEADLESS_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HEADLESS_ATTACK),
                    MeleeCombatActionCode::COVER_OF_ALLY => MeleeCombatActionCode::getIt(MeleeCombatActionCode::COVER_OF_ALLY),
                    MeleeCombatActionCode::FLAT_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FLAT_ATTACK),
                    MeleeCombatActionCode::PRESSURE => MeleeCombatActionCode::getIt(MeleeCombatActionCode::PRESSURE),
                    MeleeCombatActionCode::RETREAT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::RETREAT),
                    MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT),
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    // you can hold two mini crossbows, but can not AIM SHOT from both
                ];
            case MeleeCombatActionCode::FLAT_ATTACK :
                return [
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    // you can not RUN when fighting
                    // you are trying to attack flat, so forget about STANDARD ATTACK
                    // you are attacking, not SWAPPING WEAPONS
                    // you choose attack, so CONCENTRATION ON DEFENSE is not possible
                    CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM),
                    // you need both hands to PUT OUT HARDLY ACCESSIBLE ITEM, but you need at least one for attack
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING),
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    // you are attacking, not GETTING UP, you know?
                    // PUTTING ARMOR on attack is fairytale
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    // when you are trying to hit opponent flat, you can not HEADLESS ATTACK him as well because you need think about it
                    // are you attacking or COVERING an ALLY?
                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    MeleeCombatActionCode::PRESSURE => MeleeCombatActionCode::getIt(MeleeCombatActionCode::PRESSURE),
                    MeleeCombatActionCode::RETREAT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::RETREAT), // it is possible to try flat attack then retreat
                    MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT),
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    // no, sorry, you can not make AIM SHOT flat
                ];
            case MeleeCombatActionCode::PRESSURE :
                return [
                    // you are pressing, any MOVE would cancel that
                    // you can not RUN and attack
                    // do you feel pressure as STANDARD ATTACK ?
                    // you are pressing, can not SWAP WEAPONS
                    // press has quite different meaning that CONCENTRATION ON DEFENSE
                    CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM),
                    // for press you need some weapon and you can not hold any if PUTTING OUT HARDLY ACCESSIBLE ITEM
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING), // well, if DM allows this...
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS), // ... and this ...
                    // if you are trying to GET UP, you definitely can not press opponent
                    // so you are trying to PUT ON ARMOR again when attacking? have you contacted your psychologist already?
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    // pressure requires some effort, same as HEADLESS_ATTACK so you can use only one of them
                    // are you pressing or COVERING some ALLY?
                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    MeleeCombatActionCode::FLAT_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FLAT_ATTACK),
                    // when you are pressing hard, you can hardly realize you should RETREAT
                    // the opponent is down, how you want to let him feel your pressure? so ATTACK ON DISABLED OPPONENT has no meaning
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    // pressure requires almost physical contact, which is not an AIMED_SHOOT
                ];
             case MeleeCombatActionCode::RETREAT :
                return [
                    // TODO
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    CombatActionCode::RUN => CombatActionCode::getIt(CombatActionCode::RUN),
                    CombatActionCode::STANDARD_ATTACK => CombatActionCode::getIt(CombatActionCode::STANDARD_ATTACK),
                    CombatActionCode::SWAP_WEAPONS => CombatActionCode::getIt(CombatActionCode::SWAP_WEAPONS),
                    CombatActionCode::CONCENTRATION_ON_DEFENSE => CombatActionCode::getIt(CombatActionCode::CONCENTRATION_ON_DEFENSE),
                    CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM),
                    CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM),
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING),
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    CombatActionCode::GETTING_UP => CombatActionCode::getIt(CombatActionCode::GETTING_UP),
                    CombatActionCode::PUTTING_ON_ARMOR => CombatActionCode::getIt(CombatActionCode::PUTTING_ON_ARMOR),
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    MeleeCombatActionCode::HEADLESS_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HEADLESS_ATTACK),
                    MeleeCombatActionCode::COVER_OF_ALLY => MeleeCombatActionCode::getIt(MeleeCombatActionCode::COVER_OF_ALLY),
                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    MeleeCombatActionCode::FLAT_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FLAT_ATTACK),
                    MeleeCombatActionCode::PRESSURE => MeleeCombatActionCode::getIt(MeleeCombatActionCode::PRESSURE),
                    MeleeCombatActionCode::RETREAT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::RETREAT),
                    MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT),
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    RangedCombatActionCode::getIt(RangedCombatActionCode::AIMED_SHOOT),
                ];
            case 'foo' : // TODO
                return [
                    CombatActionCode::MOVE => CombatActionCode::getIt(CombatActionCode::MOVE),
                    CombatActionCode::RUN => CombatActionCode::getIt(CombatActionCode::RUN),
                    CombatActionCode::STANDARD_ATTACK => CombatActionCode::getIt(CombatActionCode::STANDARD_ATTACK),
                    CombatActionCode::SWAP_WEAPONS => CombatActionCode::getIt(CombatActionCode::SWAP_WEAPONS),
                    CombatActionCode::CONCENTRATION_ON_DEFENSE => CombatActionCode::getIt(CombatActionCode::CONCENTRATION_ON_DEFENSE),
                    CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_EASILY_ACCESSIBLE_ITEM),
                    CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM => CombatActionCode::getIt(CombatActionCode::PUT_OUT_HARDLY_ACCESSIBLE_ITEM),
                    CombatActionCode::LAYING => CombatActionCode::getIt(CombatActionCode::LAYING),
                    CombatActionCode::SITTING_OR_ON_KNEELS => CombatActionCode::getIt(CombatActionCode::SITTING_OR_ON_KNEELS),
                    CombatActionCode::GETTING_UP => CombatActionCode::getIt(CombatActionCode::GETTING_UP),
                    CombatActionCode::PUTTING_ON_ARMOR => CombatActionCode::getIt(CombatActionCode::PUTTING_ON_ARMOR),
                    CombatActionCode::ATTACK_FROM_BEHIND => CombatActionCode::getIt(CombatActionCode::ATTACK_FROM_BEHIND),
                    CombatActionCode::BLINDFOLD_FIGHT => CombatActionCode::getIt(CombatActionCode::BLINDFOLD_FIGHT),
                    CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY => CombatActionCode::getIt(CombatActionCode::FIGHT_IN_REDUCED_VISIBILITY),

                    MeleeCombatActionCode::HEADLESS_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HEADLESS_ATTACK),
                    MeleeCombatActionCode::COVER_OF_ALLY => MeleeCombatActionCode::getIt(MeleeCombatActionCode::COVER_OF_ALLY),
                    MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FIGHT_WITH_TWO_WEAPONS),
                    MeleeCombatActionCode::FLAT_ATTACK => MeleeCombatActionCode::getIt(MeleeCombatActionCode::FLAT_ATTACK),
                    MeleeCombatActionCode::PRESSURE => MeleeCombatActionCode::getIt(MeleeCombatActionCode::PRESSURE),
                    MeleeCombatActionCode::RETREAT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::RETREAT),
                    MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT => MeleeCombatActionCode::getIt(MeleeCombatActionCode::ATTACK_ON_DISABLED_OPPONENT),
                    MeleeCombatActionCode::HANDOVER_ITEM => MeleeCombatActionCode::getIt(MeleeCombatActionCode::HANDOVER_ITEM),

                    RangedCombatActionCode::getIt(RangedCombatActionCode::AIMED_SHOOT),
                ];

            default :
                throw new \InvalidArgumentException();
        }
    }

    /**
     * @param CombatActionCode $firstAction
     * @param CombatActionCode $secondAction
     * @return bool
     */
    public static function canCombineActions(CombatActionCode $firstAction, CombatActionCode $secondAction)
    {
        return array_key_exists($secondAction->getValue(), self::getAllowedCombatActionsFor($firstAction));
    }

    /**
     * @return array|\DrdPlus\Codes\CombatActions\CombatActionCode[]
     */
    public function getCombatActionCodes()
    {
        return $this->combatActionCodes;
    }

    /**
     * @return \ArrayIterator|\DrdPlus\Codes\CombatActions\CombatActionCode[]
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->combatActionCodes);
    }
}