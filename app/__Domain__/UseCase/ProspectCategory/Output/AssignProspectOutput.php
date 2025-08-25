<?php

namespace App\__Domain__\UseCase\ProspectCategory\Output;

class AssignProspectOutput
{
    public function __construct(
        public bool $success = true,
        public ?string $errorMessage = null
    ) {}
}