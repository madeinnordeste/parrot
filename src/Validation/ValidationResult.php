<?php

declare(strict_types=1);

namespace Parrot\Validation;

class ValidationResult
{
    public function __construct(
        private bool $isValid,
        private string $title = '',
        private string $message = ''
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getMessage(): string
    {
        return $this->message;
    }
}