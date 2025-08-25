<?php

namespace App\__Domain__\UseCase\ProspectCategory\Input;

class DeleteInput
{
    public function __construct(
        public int $userId,
        public int $categoryId
    ) {}
}