<?php
namespace DrdPlus\CurrentProperties;

use DrdPlus\Codes\Armaments\ArmamentCode;
use DrdPlus\Properties\Base\Strength;
use DrdPlus\Properties\Body\Size;
use DrdPlus\Tables\Armaments\Armourer;

trait GuardArmamentWearableTrait
{
    /**
     * @param ArmamentCode $armamentCode
     * @param Strength $strength
     * @param Size $size
     * @param Armourer $armourer
     * @throws Exceptions\CanNotUseArmamentBecauseOfMissingStrength
     */
    protected function guardArmamentWearable(ArmamentCode $armamentCode, Strength $strength, Size $size, Armourer $armourer)
    {
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        if (!$armourer->canUseArmament($armamentCode, $strength, $size)) {
            throw new Exceptions\CanNotUseArmamentBecauseOfMissingStrength(
                "'{$armamentCode}' is too heavy to be used by with strength {$size}"
            );
        }
    }
}