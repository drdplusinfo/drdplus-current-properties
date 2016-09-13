<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\RangedWeaponCode;
use DrdPlus\Codes\Armaments\ShieldCode;
use DrdPlus\Codes\ProfessionCode;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Combat\AttackNumber;
use DrdPlus\Properties\Combat\DefenseNumber;
use DrdPlus\Properties\Combat\DefenseNumberAgainstShooting;
use DrdPlus\Properties\Combat\FightNumber;
use DrdPlus\Properties\Combat\Shooting;
use DrdPlus\Skills\Skills;
use DrdPlus\Tables\Measurements\Wounds\WoundsBonus;
use DrdPlus\Tables\Tables;
use Granam\Strict\Object\StrictObject;

class BattleProperties extends StrictObject
{
    /**
     * @var CurrentProperties
     */
    private $currentProperties;
    /**
     * @var CombatActions
     */
    private $combatActions;
    /**
     * @var Skills
     */
    private $skills;
    /**
     * @var Tables
     */
    private $tables;

    public function __construct(
        CurrentProperties $currentProperties,
        CombatActions $combatActions,
        Skills $skills,
        Tables $tables
    )
    {
        // TODO shield can be offhand weapon as well (-2 strength if weapon, two weapons skill counted for both weapons)
        // TODO add modifiers from combat actions
        $this->currentProperties = $currentProperties;
        $this->combatActions = $combatActions;
        $this->skills = $skills;
        $this->tables = $tables;
    }

    /**
     * Final fight number including body state (level, fatigue, wounds, curses...) and used weapon.
     *
     * @return FightNumber
     */
    public function getFightNumber()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumber = new FightNumber(
            ProfessionCode::getIt($this->currentProperties->getProfession()->getValue()),
            $this->currentProperties,
            $this->$this->currentProperties->getSize()
        );
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumber->add($this->getFightNumberModifierWithWornArmaments());

        return $fightNumber;
    }

    /**
     * @return int
     */
    private function getFightNumberModifierWithWornArmaments()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $fightNumberModifier =
            // strength effects and weapon itself
            +$this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                $this->currentProperties->getWornWeapon(),
                $this->getStrengthForWornWeapon()
            )
            // length of weapon is directly used as bonus to fight number (shields and ranged weapons have length / bonus zero)
            + $this->tables->getArmourer()->getLengthOfWeaponlike($this->currentProperties->getWornWeapon())
            + $this->tables->getArmourer()->getFightNumberMalusByStrengthWithWeaponlike(
                $this->currentProperties->getWornShieldOrOffhandWeapon(),
                $this->getStrengthForWornShield()
            )
            //skills effects
            + $this->skills->getMalusToFightNumberWithWeaponlike(
                $this->currentProperties->getWornWeapon(),
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->skills->getMalusToFightNumberWithProtective(
                $this->currentProperties->getWornBodyArmor(),
                $this->tables->getArmourer()
            )
            + $this->skills->getMalusToFightNumberWithProtective(
                $this->currentProperties->getWornHelm(),
                $this->tables->getArmourer()
            );
        if ($this->currentProperties->getWornShieldOrOffhandWeapon() instanceof ShieldCode) {
            /** @var ShieldCode $shield */
            $shield = $this->currentProperties->getWornShieldOrOffhandWeapon();
            /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithProtective($shield, $this->tables->getArmourer());
        } else { // if you hold offhand weapon, instead of shield, the malus from skill with that weapon type is used
            $fightNumberModifier += $this->skills->getMalusToFightNumberWithWeaponlike(
                $this->currentProperties->getWornShieldOrOffhandWeapon(),
                $this->tables->getMissingWeaponSkillTable()
            );
        }
        $fightNumberModifier += $this->combatActions->getFightNumberModifier();

        return $fightNumberModifier;
    }

    /**
     * Final attack number including body state (level, fatigue, wounds, curses...) and used weapon.
     *
     * @return AttackNumber
     */
    public function getAttackNumber()
    {
        $attackNumber = new AttackNumber($this->currentProperties->getAgility());
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $attackNumber->add($this->getAttackNumberModifierWithWornWeapon());

        return $attackNumber;
    }

    /**
     * @return int
     */
    private function getAttackNumberModifierWithWornWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->skills->getMalusToAttackNumberWithWeaponlike(
                $this->currentProperties->getWornWeapon(),
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getAttackNumberMalusByStrengthWithWeaponlike(
                $this->currentProperties->getWornWeapon(),
                $this->getStrengthForWornWeapon()
            )
            + $this->tables->getArmourer()->getOffensivenessOfWeaponlike($this->currentProperties->getWornWeapon())
            + $this->combatActions->getAttackNumberModifier();
    }

    /**
     * Final shooting (attack number for bows and crossbows) including body state (level, fatigue, wounds, curses...) and used weapon.
     *
     * @return Shooting
     */
    public function getShooting()
    {
        $shooting = new Shooting($this->currentProperties->getKnack());
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $shooting->add($this->getAttackNumberModifierWithWornWeapon()); // all the modifications are very sme as for attack number

        return $shooting;
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
        $defenseNumber->add($this->combatActions->getDefenseNumberModifier());

        return $defenseNumber;
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
        $defenseNumber->add($this->combatActions->getDefenseNumberModifierAgainstFasterOpponent());

        return $defenseNumber;
    }

    /**
     * You have to choose if cover by shield (can twice per round even if already attacked)
     * or by weapon (can only once per round and only if attacked before defense or decided to spent his slower attack action to this defense)
     * or just by a dodge (in that case use the pure DefenseNumber).
     * If you need to distinguish defense number with and without weapon, just create another instance of CurrentProperties
     * with different parameters.
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithWornWeapon());
    }

    private function getCoverWithWornWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponlike(
                $this->currentProperties->getWornWeapon(),
                $this->getStrengthForWornWeapon()
            )
            + $this->skills->getMalusToCoverWithWeaponlike(
                $this->currentProperties->getWornWeapon(), // it can be shield if used for attack
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getCoverOfWeaponlike($this->currentProperties->getWornWeapon());
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
     * You have to choose if cover by shield or by weapon or just by a dodge (in that case use the pure DefenseNumber).
     *
     * @return DefenseNumber
     */
    public function getDefenseNumberWithCoverByShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->getDefenseNumber()->add($this->getCoverWithWornShield());
    }

    private function getCoverWithWornShield()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return
            $this->tables->getArmourer()->getDefenseNumberMalusByStrengthWithWeaponlike(
                $this->currentProperties->getWornShieldOrOffhandWeapon(),
                $this->getStrengthForWornShield()
            )
            + $this->skills->getMalusToCoverWithWeaponlike(
                $this->currentProperties->getWornShieldOrOffhandWeapon(), // it can be shield if used for attack
                $this->tables->getMissingWeaponSkillTable()
            )
            + $this->tables->getArmourer()->getCoverOfWeaponlike($this->currentProperties->getWornShieldOrOffhandWeapon());
    }

    /**
     * @return Strength
     */
    private function getStrengthForWornShield()
    {
        return $this->currentProperties->getStrengthForWornWeaponOrShield(
            $this->currentProperties->getWornShieldOrOffhandWeapon(),
            $this->currentProperties->getWornWeapon()
        );
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
        return new DefenseNumberAgainstShooting($this->getDefenseNumberWithCoverByShield(), $this->currentProperties->getSize());
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
     * Gives WoundsBonus - if you need the Wounds, just convert WoundsBonus to it by WoundsBonus->getWounds()
     * Note about both hands holding of a weapon - if you have empty off-hand (without shield) and the weapon you are holding
     * is single-hand, it will automatically add +2 for two-hand holding.
     * See PPH page 92 right column.
     *
     * @return WoundsBonus
     */
    public function getBaseOfWoundsWithWornWeapon()
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $baseOfWounds = $this->tables->getBaseOfWoundsTable()->calculateBaseOfWounds(
        /* sadly there has to be used standard strength, not that dependent on one/two-hands holding
         (appropriate bonus is expressed by simple +2, see bellow) */
            $this->currentProperties->getStrength()->getValue(),
            $this->tables->getArmourer()->getWoundsOfWeaponlike($this->currentProperties->getWornWeapon())
        );
        if ($this->currentProperties->holdsOneHandedWeaponlikeByBothHands(
            $this->currentProperties->getWornWeapon(),
            $this->currentProperties->getWornShieldOrOffhandWeapon()
        )
        ) {
            $baseOfWounds += 2;
        }
        $baseOfWounds += $this->combatActions->getBaseOfWoundsModifier(
            $this->currentProperties->getWornWeapon(),
            $this->tables->getWeaponlikeTableByWeaponlikeCode($this->currentProperties->getWornWeapon())
        );

        return new WoundsBonus($baseOfWounds, $this->tables->getWoundsTable());
    }

    /**
     * @param RangedWeaponCode $rangedWeaponCode
     * @return int
     */
    public function getLoadingInRoundsWithRangedWeapon(RangedWeaponCode $rangedWeaponCode)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->tables->getArmourer()->getLoadingInRoundsByStrengthWithRangedWeapon(
            $rangedWeaponCode,
            $this->getStrengthForWornWeapon()
        );
    }

    /**
     * @return Strength
     */
    private function getStrengthForWornWeapon()
    {
        return $this->currentProperties->getStrengthForWornWeaponOrShield(
            $this->currentProperties->getWornWeapon(),
            $this->currentProperties->getWornShieldOrOffhandWeapon()
        );
    }

    /**
     * @param RangedWeaponCode $rangedWeaponCode
     * @return int
     */
    public function getEncounterRangeWithRangedWeapon(RangedWeaponCode $rangedWeaponCode)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        return $this->tables->getArmourer()->getEncounterRangeMalusByStrengthWithRangedWeapon(
            $rangedWeaponCode,
            $this->getStrengthForWornWeapon()
        )
        + $this->tables->getArmourer()->getRangeOfRangedWeapon($rangedWeaponCode);
    }
}