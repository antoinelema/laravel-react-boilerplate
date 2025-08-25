<?php

namespace App\__Domain__\UseCase\ProspectCategory\Input;

class AssignProspectInput
{
    public function __construct(
        public int $userId,
        public int $prospectId,
        public array $categoryIds
    ) {}
}