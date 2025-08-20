<?php

namespace App\__Domain__\Data\User;

use App\__Domain__\Data\User\Model;

class Factory
{
    public static function create(
        ?int $id,
        string $name,
        string $firstname,
        string $email,
        string $password,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ): Model {
        return new Model(
            $id,
            $name,
            $firstname,
            $email,
            $password,
            $createdAt,
            $updatedAt
        );
    }
}
