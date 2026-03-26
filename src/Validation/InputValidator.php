<?php

declare(strict_types=1);

namespace Parrot\Validation;

use Parrot\Validation\ValidationResult;

class InputValidator
{
    public const MIN_GLUCOSE = 0;
    public const MAX_GLUCOSE = 600;
    public const MIN_DAYS = 7;
    public const MAX_DAYS = 365;

    public static function validateRecaptchaResponse(?string $response): ValidationResult
    {
        if (empty($response)) {
            return new ValidationResult(false, 'Recaptcha Error', 'Recaptcha response is missing');
        }
        return new ValidationResult(true);
    }

    public static function validateNightscoutAddress(?string $address): ValidationResult
    {
        if (empty($address)) {
            return new ValidationResult(false, 'Validation Error', 'Nightscout address is required');
        }
        if (!filter_var($address, FILTER_VALIDATE_URL)) {
            return new ValidationResult(false, 'Validation Error', 'Nightscout address must be a valid URL');
        }
        return new ValidationResult(true);
    }

    public static function validateGlucoseRange(int $min, int $max): ValidationResult
    {
        if ($min >= $max) {
            return new ValidationResult(false, 'Validation Error', 'Min glucose must be less than max glucose');
        }
        if ($min < self::MIN_GLUCOSE || $max > self::MAX_GLUCOSE) {
            return new ValidationResult(false, 'Validation Error', 'Glucose values must be between ' . self::MIN_GLUCOSE . ' and ' . self::MAX_GLUCOSE);
        }
        return new ValidationResult(true);
    }

    public static function validateDays(int $days): ValidationResult
    {
        if ($days < self::MIN_DAYS || $days > self::MAX_DAYS) {
            return new ValidationResult(false, 'Validation Error', 'Days must be between ' . self::MIN_DAYS . ' and ' . self::MAX_DAYS);
        }
        return new ValidationResult(true);
    }
}