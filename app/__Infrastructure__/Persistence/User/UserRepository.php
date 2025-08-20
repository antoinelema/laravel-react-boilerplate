<?php

namespace App\__Infrastructure__\Persistence\User;

use App\__Domain__\Data\User\Collection;
use App\__Domain__\Data\User\Model;
use App\__Infrastructure__\Eloquent\UserEloquent as EloquentUser;

class UserRepository implements Collection
{
    public function findById(int $id): ?Model
    {
        $eloquent = EloquentUser::find($id);
        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function findByEmail(string $email): ?Model
    {
        $eloquent = EloquentUser::where('email', $email)->first();
        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function save(Model $user, array $data = []): Model
    {
        if ($user->id) {
            // Update existing user
            $eloquent = EloquentUser::findOrFail($user->id);
            $eloquent->fill((array)$user);
            $eloquent->save();
        } else {
            // Create new user
            $eloquent = EloquentUser::create((array)$user);
        }

        return $this->toDomain($eloquent);
    }

    public function delete(Model $user): void
    {
        $eloquent = EloquentUser::findOrFail($user->id);
        $eloquent->delete();
    }

    /**
     * Mappe un modèle Eloquent vers le modèle de domaine
     */
    private function toDomain(EloquentUser $eloquent): Model
    {
        return new Model(
            $eloquent->id,
            $eloquent->name,
            $eloquent->firstname,
            $eloquent->email,
            $eloquent->password,
            $eloquent->created_at ? new \DateTimeImmutable($eloquent->created_at) : null,
            $eloquent->updated_at ? new \DateTimeImmutable($eloquent->updated_at) : null
        );
    }
}
