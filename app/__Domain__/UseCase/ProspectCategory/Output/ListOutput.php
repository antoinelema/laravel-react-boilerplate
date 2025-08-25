<?php

namespace App\__Domain__\UseCase\ProspectCategory\Output;

use Illuminate\Support\Collection;

class ListOutput
{
    public function __construct(
        public Collection $categories,
        public bool $success = true,
        public ?string $errorMessage = null
    ) {}
}