<?php

namespace App\__Domain__\Data\User;

/**
 * Entité de domaine User
 */
class Model
{
    public ?int $id;
    public string $name;
    public string $firstname;
    public string $email;
    public ?string $password;
    public ?\DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;

    public function __construct(
        ?int $id,
        string $name,
        string $firstname,
        string $email,
        ?string $password = null,
        ?\DateTimeImmutable $createdAt = null,
        ?\DateTimeImmutable $updatedAt = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->firstname = $firstname;
        $this->email = $email;
        $this->password = $password;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }
}
