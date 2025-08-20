<?php

namespace App\__Domain__\Data\User;

interface Collection
{
    /**
     * Retourne un utilisateur du domaine (pas un modèle Eloquent)
     */
    public function findById(int $id): ?Model;

    public function findByEmail(string $email): ?Model;

    public function save(Model $user, array $data = []): Model;

    public function delete(Model $user): void;
}
