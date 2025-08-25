<?php

namespace App\__Domain__\UseCase\ProspectCategory\Output;

class DeleteOutput
{
    public function __construct(
        public bool $success = true,
        public ?string $errorMessage = null
    ) {}
}