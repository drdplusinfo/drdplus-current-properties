<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\Armaments\WeaponlikeCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Combat\AttackNumber;
use DrdPlus\Properties\Combat\DefenseNumber;
use DrdPlus\Properties\Combat\DefenseNumberAgainstShooting;
use DrdPlus\Properties\Combat\EncounterRange;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Properties\Combat\LoadingInRounds;
use DrdPlus\Properties\Combat\Shooting;
use DrdPlus\Skills\Skills;
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
        $this->guardWeaponsUsableTogether($weaponOrShieldInMainHand, $weaponOrShieldInOffhand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldInMainHand);
        $this->guardActionsCompatibleWithWeapon($combatActions, $weaponOrShieldInOffhand);
        $this->weaponOrShieldInMainHand = $weaponOrShieldInMainHand;
        $this->combatActions = $combatActions;
        $this->weaponOrShieldInOffhand = $weaponOrShieldInOffhand;
        if ($this->usesSingleWeapon()) {
            if ($this->usesSingleOneHandedWeaponByTwoHands()) {
                $this->guardWeaponlikeWearable(
                    $this->getSingleWeapon(),
                    $this->getStrengthForTwoHandedWeapon($this->getSingleWeapon())
                );
            } else if ($this->usesSingleOneHandedWeaponByOneHand()) {
                $this->guardWeaponlikeWearable(
                    $this->getSingleWeapon(),
                    $this->getStrengthForSingleOneHandedWeapon()
                );
            } else { // uses two handed weapon by two hands
                $this->guardWeaponlikeWearable(
                    $this->getSingleWeapon(),
                    $this->getStrengthForTwoHandedWeapon($this->getSingleWeapon())
                );
            }
        } else {
            $this->guardWeaponlikeWearable($weaponOrShieldInMainHand, $this->currentProperties->getStrengthForMainHandOnly());
            $this->guardWeaponlikeWearable($weaponOrShieldInOffhand, $this->currentProperties->getStrengthForOffhandOnly());
        }
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
     * Is one hand empty and a weapon in other one?
     *
     * @return bool
     */
    private function usesSingleWeapon()
    {
        return
            (!$this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInMainHand())
                && $this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInOffhand())
            )
            || ($this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInMainHand())
                && !$this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInOffhand())
            );
    }

    /**
     * @return WeaponlikeCode
     * @throws Exceptions\CanNotGiveSingleWeaponWhenTwoAreUsed
     */
    private function getSingleWeapon()
    {
        if (!$this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInMainHand())) {
            return $this->getWeaponOrShieldInMainHand();
        }
        if (!$this->tables->getArmourer()->hasEmptyHand($this->getWeaponOrShieldInOffhand())) {
            return $this->getWeaponOrShieldInOffhand();
        }
        throw new Exceptions\CanNotGiveSingleWeaponWhenTwoAreUsed(
            "Can not give single weapon when using {$this->getWeaponOrShieldInMainHand()} and {$this->getWeaponOrShieldInOffhand()}"
        );
    }

    /**
     * @param WeaponlikeCode $weaponlikeCode
     * @param Strength $currentStrengthForWeapon
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    private function guardWeaponlikeWearable(WeaponlikeCode $weaponlikeCode, Strength $currentStrengthForWeapon)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (!$this->tables->getArmourer()->canUseArmament($weaponlikeCode, $currentStrengthForWeapon, $this->currentProperties->getSize())) {
            throw new Exceptions\CanNotUseArmamentBecauseOfMissingStrength(
                "'{$weaponlikeCode}' is too heavy to be used by with strength {$currentStrengthForWeapon}"
            );
        }
    }

    // TODO shield can be offhand weapon as well (-2 strength if weapon, two weapons skill counted for both weapons)
    // TODO for main hand only (or two-hands holding); offhand only; two weapons; according to ACTIONS

    /**
     * This is probably weapon, but in case of emergency also shield can be used for smash.
     *
     * @return WeaponlikeCode
     */
    public function getWeaponOrShieldInMainHand()
    {
        return $this->weaponOrShieldInMainHand;
    }

    /**
     * This is probably shield or empty hand (for two-hand weapon or both-hands holding of one-hand weapon),
     * but skilled, crazy or despair individuals can use also a weapon in their offhand.
     *
     * @return WeaponlikeCode
     */
    public function getWeaponOrShieldInOffhand()
    {
        return $this->weaponOrShieldInOffhand;
    }

    /**
     * Final fight number including body state (level, fatigue, wounds, curses...), used main hand weapon and action.
     *
     * @return FightNumber
     */
    public function getFightNumber()
    {
        $fightNumber = $this->getBaseFightNumber();

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $fightNumber->add($this->getFightNumberModifier());
    }

    /**
     * @return FightNumber
     */
    private function getBaseFightNumber()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new FightNumber(
            ProfessionCode::getIt($this->currentProperties->getProfession()->getValue()),
            $this->currentProperties,
            $this->currentProperties->getSize()
        );
    }

    /**
     * @return int
     */
    private function getFightNumberModifier()
    {
        $fightNumberModifier = 0;

        // strength effect
        if ($this->combatActions->attacksByMainHandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->getStrengthForMainHand()
            );
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInOffhand(),
                $this->getStrengthForOffhand()
            );
        } else if ($this->combatActions->attacksByTwoHands()) {
            if ($this->usesSingleWeapon()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                    $this->getSingleWeapon(),
                    $this->getStrengthForTwoHandedWeapon($this->getSingleWeapon())
                );
            } else {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                    $this->getWeaponOrShieldInMainHand(),
                    $this->getStrengthForMainHand()
                );
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberModifier += $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                    $this->getWeaponOrShieldInOffhand(),
                    $this->getStrengthForOffhand()
                );
            }
        }

        // skills effect
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberModifier +=
            $this->skills->getMalusToFightNumberWithProtective(
                $this->currentProperties->getWornBodyArmor(),
                $this->tables->getArmourer()
            )
            + $this->skills->getMalusToFightNumberWithProtective(
                $this->currentProperties->getWornHelm(),
                $this->tables->getArmourer()
            );
        if ($this->combatActions->attacksByMainHandOnly()) {
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->tables->getMissingWeaponSkillTable()
            );
            if ($this->getWeaponOrShieldInOffhand()->isShield()) {
                /** @var ShieldCode $shield */
                $shield = $this->getWeaponOrShieldInOffhand();
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberModifier += $this->skills->getMalusToFightNumberWithProtective(
                    $shield,
                    $this->tables->getArmourer()
                );
            }
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                $this->getWeaponOrShieldInOffhand(),
                $this->tables->getMissingWeaponSkillTable()
            );
            if ($this->getWeaponOrShieldInMainHand()->isShield()) {
                /** @var ShieldCode $shield */
                $shield = $this->getWeaponOrShieldInMainHand();
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $fightNumberModifier += $this->skills->getMalusToFightNumberWithProtective(
                    $shield,
                    $this->tables->getArmourer()
                );
            }
        } else if ($this->combatActions->attacksByTwoHands()) {
            if ($this->usesSingleWeapon()) {
                $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                    $this->getSingleWeapon(),
                    $this->tables->getMissingWeaponSkillTable()
                );
            } else {
                $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                    $this->getWeaponOrShieldInMainHand(),
                    $this->tables->getMissingWeaponSkillTable()
                );
                $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                    $this->getWeaponOrShieldInOffhand(),
                    $this->tables->getMissingWeaponSkillTable()
                );
            }
        } // else no attack at all ...

        // weapon length effect - length of weapon is directly used as bonus to fight number (shields and ranged weapons have length zero)
        if ($this->usesSingleWeapon()) {
            $fightNumberModifier += $this->tables->getArmourer()->getLengthOfWeaponlike($this->getSingleWeapon());
        } else {
            $mainHandLength = $this->tables->getArmourer()->getLengthOfWeaponlike($this->getWeaponOrShieldInMainHand());
            $offHandLength = $this->tables->getArmourer()->getLengthOfWeaponlike($this->getWeaponOrShieldInOffhand());
            $fightNumberModifier += max($mainHandLength, $offHandLength);
        }

        // combat actions effect
        $fightNumberModifier += $this->combatActions->getFightNumberModifier();

        return $fightNumberModifier;
    }

    /**
     * @return bool
     */
    private function usesSingleOneHandedWeaponByTwoHands()
    {
        return
            $this->usesSingleWeapon()
            && ($this->combatActions->attacksByTwoHands() || $this->combatActions->defensesByTwoHands())
            && $this->tables->getArmourer()->canHoldItByOneHandAsWellAsTwoHands($this->getSingleWeapon());
    }

    /**
     * @return bool
     */
    private function usesSingleOneHandedWeaponByOneHand()
    {
        return
            $this->usesSingleWeapon()
            && ($this->combatActions->attacksByMainHandOnly() || $this->combatActions->attacksByOffhandOnly())
            && $this->tables->getArmourer()->canHoldItByOneHand($this->getSingleWeapon());
    }

    /**
     * If one-handed shield is kept by both hands, the required strength for weapon is lower
     * (fighter strength is considered higher respectively)
     * see details in PPH page 93, left column
     *
     * @param WeaponlikeCode $twoHandsBearableWeapon
     * @return Strength
     * @throws \DrdPlus\CurrentProperties\Exceptions\CanNotHoldItByTwoHands
     */
    public function getStrengthForTwoHandedWeapon(WeaponlikeCode $twoHandsBearableWeapon)
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
        // If one-handed is kept by both hands, the required strength is lower (workaround for conditioned increment of fighter strength)
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->currentProperties->getStrengthForMainHandOnly()->add(2);
    }

    /**
     * @return Strength
     * @throws \LogicException
     */
    private function getStrengthForSingleOneHandedWeapon()
    {
        if ($this->combatActions->attacksByMainHandOnly()) {
            return $this->getStrengthForMainHand();
        }
        if ($this->combatActions->attacksByOffhandOnly()) {
            return $this->getStrengthForOffhand();
        }
        throw new \LogicException();
    }

    /**
     * Final attack number including body state (level, fatigue, wounds, curses...), used weapon and action.
     *
     * @return AttackNumber
     */
    public function getAttackNumber()
    {
        // TODO change it for any hand (even offhand can attack, see actions)
        $attackNumber = new AttackNumber($this->currentProperties->getAgility());

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $attackNumber->add($this->getAttackNumberModifier());
    }

    /**
     * @return int
     */
    private function getAttackNumberModifier()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->skills->getMalusToAttackNumberWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getAttackNumberMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->getStrengthForMainHand()
            )
            + $this->tables->getArmourer()->getOffensivenessOfWeaponlike($this->getWeaponOrShieldInMainHand())
            + $this->combatActions->getAttackNumberModifier();
    }

    /**
     * Final shooting (attack number for bows and crossbows) including body state (level, fatigue, wounds, curses...),
     * used weapon and action.
     *
     * @return Shooting
     */
    public function getShooting()
    {
        $shooting = new Shooting($this->currentProperties->getKnack());

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $shooting->add($this->getAttackNumberModifier()); // all the modifications are very sme as for attack number
    }

    /**
     * Note: armors are use to find out agility restriction, but they do not affect defense number directly.
     * Their protection is used after hit to lower final damage.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumber()
    {
        $defenseNumber = new DefenseNumber($this->currentProperties->getAgility());

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $defenseNumber->add($this->combatActions->getDefenseNumberModifier());
    }

    /**
     * Note: armors are use to find out agility restriction, but they do not affect defense number directly.
     * Their protection is used after hit to lower final damage.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberAgainstFasterOpponent()
    {
        $defenseNumber = new DefenseNumber($this->currentProperties->getAgility());

        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $defenseNumber->add($this->combatActions->getDefenseNumberModifierAgainstFasterOpponent());
    }

    // TODO change defense with weapon / shield to defense with main hand / offhand

    /**
     * You have to choose if cover by shield (can twice per round even if already attacked)
     * or by weapon (can only once per round and only if attacked before defense or decided to spent his slower attack
     * action to this defense) or just by a dodge (in that case use the pure DefenseNumber). If you need to distinguish
     * defense number with and without weapon, just create another instance of CurrentProperties with different
     * parameters.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithWornWeapon());
    }

    /**
     * @return int
     */
    private function getCoverWithWornWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->getStrengthForMainHand()
            )
            + $this->skills->getMalusToCoverWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(), // it can be shield if used for attack
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getCoverOfWeaponlike($this->getWeaponOrShieldInMainHand());
    }

    /**
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByWeaponAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithWornWeapon());
    }

    /**
     * You have to choose if cover by shield or by weapon or just by a dodge (in that case use pure @see
     * getDefenseNumber ).
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithWornShield());
    }

    /**
     * @return int
     */
    private function getCoverWithWornShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInOffhand(),
                $this->getStrengthForWornShield()
            )
            + $this->skills->getMalusToCoverWithWeaponlike(
                $this->getWeaponOrShieldInOffhand(), // it can be shield if used for attack
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getCoverOfWeaponlike($this->getWeaponOrShieldInOffhand());
    }

    /**
     * @return Strength
     */
    private function getStrengthForWornShield()
    {
        // TODO
        return $this->getStrengthForMainHand();
    }

    /**
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByShieldAgainstFasterOpponent()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumberAgainstFasterOpponent()->add($this->getCoverWithWornShield());
    }

    /**
     * Shield cover is always counted to your defense against shooting, even if you does not know about attack
     * (of course only if it has a sense - shield hardly covers your back if you hold it in hand).
     *
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShooting()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberWithCoverByShield(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * @return DefenseNumberAgainstShooting
     */
    public function getDefenseNumberAgainstShootingAndFasterOpponent()
    {
        return new DefenseNumberAgainstShooting(
            $this->getDefenseNumberWithCoverByShieldAgainstFasterOpponent(),
            $this->currentProperties->getSize()
        );
    }

    /**
     * Gives @see WoundsBonus - if you need Wounds just convert WoundsBonus to it by WoundsBonus->getWounds()
     *
     * @see Wounds
     * Note about both hands holding of a weapon - if you have empty off-hand (without shield) and the weapon you are
     * holding is single-hand, it will automatically add +2 for two-hand holding (if you choose such action).
     * See PPH page 92 right column.
     *
     * @return WoundsBonus
     */
    public function getBaseOfWoundsWithWornWeapon()
    {
        $baseOfWounds = 0;

        // strength effect
        if ($this->combatActions->attacksByMainHandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInMainHand(),
                $this->getStrengthForMainHand()
            );
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsMalusByStrengthWithWeaponlike(
                $this->getWeaponOrShieldInOffhand(),
                $this->getStrengthForOffhand()
            );
        } else if ($this->combatActions->attacksByTwoHands()) {
            if ($this->usesSingleWeapon()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsMalusByStrengthWithWeaponlike(
                    $this->getSingleWeapon(),
                    $this->getStrengthForTwoHandedWeapon($this->getSingleWeapon())
                );
            } else {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsMalusByStrengthWithWeaponlike(
                    $this->getWeaponOrShieldInMainHand(),
                    $this->getStrengthForMainHand()
                );
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getArmourer()->getBaseOfWoundsMalusByStrengthWithWeaponlike(
                    $this->getWeaponOrShieldInOffhand(),
                    $this->getStrengthForOffhand()
                );
            }
        }

        // weapon effects
        if ($this->combatActions->attacksByMainHandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
                $this->tables->getArmourer()->getWoundsOfWeaponlike($this->getWeaponOrShieldInMainHand()),
                $this->getStrengthForMainHand()->getValue()
            );
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
                $this->tables->getArmourer()->getWoundsOfWeaponlike($this->getWeaponOrShieldInOffhand()),
                $this->getStrengthForOffhand()->getValue()
            );
        } else if ($this->combatActions->attacksByTwoHands()) {
            if ($this->usesSingleWeapon()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
                    $this->tables->getArmourer()->getWoundsOfWeaponlike($this->getSingleWeapon()),
                    $this->getStrengthForTwoHandedWeapon($this->getSingleWeapon())->getValue()
                );
            } else {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
                    $this->tables->getArmourer()->getWoundsOfWeaponlike($this->getWeaponOrShieldInMainHand()),
                    $this->getStrengthForMainHand()->getValue()
                );
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
                    $this->tables->getArmourer()->getWoundsOfWeaponlike($this->getWeaponOrShieldInOffhand()),
                    $this->getStrengthForOffhand()->getValue()
                );
            }
        }

        // holding effect
        if ($this->combatActions->attacksByTwoHands() && $this->usesSingleWeapon()) {
            /** PPH page 92 right column */
            $baseOfWounds += 2;
        }

        // action effects
        if ($this->combatActions->attacksByMainHandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->combatActions->getBaseOfWoundsModifier(
                $this->getWeaponOrShieldInMainHand(),
                $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getWeaponOrShieldInMainHand())
            );
        } else if ($this->combatActions->attacksByOffhandOnly()) {
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $baseOfWounds += $this->combatActions->getBaseOfWoundsModifier(
                $this->getWeaponOrShieldInOffhand(),
                $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getWeaponOrShieldInOffhand())
            );
        } else if ($this->combatActions->attacksByTwoHands()) {
            if ($this->usesSingleWeapon()) {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $baseOfWounds += $this->combatActions->getBaseOfWoundsModifier(
                    $this->getSingleWeapon(),
                    $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getSingleWeapon())
                );
            } else {
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $mainHandModifier = $this->combatActions->getBaseOfWoundsModifier(
                    $this->getWeaponOrShieldInMainHand(),
                    $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getWeaponOrShieldInMainHand())
                );
                /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
                $offHandModifier = $this->combatActions->getBaseOfWoundsModifier(
                    $this->getWeaponOrShieldInOffhand(),
                    $this->tables->getWeaponlikeTableByWeaponlikeCode($this->getWeaponOrShieldInOffhand())
                );
                $baseOfWounds += max($mainHandModifier, $offHandModifier);
            }
        }

        return new WoundsBonus($baseOfWounds, $this->tables->getWoundsTable());
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
     *
     * @param RangedWeaponCode $rangedWeaponCode
     * @return EncounterRange
     */
    public function getEncounterRangeWithRangedWeapon(RangedWeaponCode $rangedWeaponCode)
    {
        // TODO change this to currently kept weapons instead of external
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return new EncounterRange(
            $this->tables->getArmourer()->getEncounterRangeWithRangedWeapon(
                $rangedWeaponCode,
                $this->getStrengthForMainHand(),
                $this->currentProperties->getSpeed()
            )
        );
    }
}