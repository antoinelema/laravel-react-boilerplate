<?php

namespace App\__Domain__\UseCase\User\Create;

class Input
{
    public function __construct(
        public readonly string $name,
        public readonly string $firstname,
        public readonly string $email,
        public readonly string $password,
    ) {}
}
