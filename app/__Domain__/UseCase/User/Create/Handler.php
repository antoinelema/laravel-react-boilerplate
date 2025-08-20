<?php

namespace App\__Domain__\UseCase\User\Create;

use App\__Domain__\Data\User\Factory as UserFactory;
use App\__Domain__\Data\User\Collection as UserCollection;

class Handler
{
    public function __construct(
        private readonly UserCollection $userCollection,
    ) {
    }

    public function __invoke(Input $input): Output
    {
        $user = UserFactory::create(
            null,
            $input->name,
            $input->firstname,
            $input->email,
            $input->password,
            null,
            null
        );

        $savedUser = $this->userCollection->save($user);

        return new Output(
            $savedUser
        );
    }
}
