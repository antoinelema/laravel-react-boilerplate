<?php

namespace App\__Domain__\UseCase\User\Create;

use App\__Domain__\Data\User\Model;

class Output
{
    public function __construct(
        public readonly Model $user,
    ) {}
}
