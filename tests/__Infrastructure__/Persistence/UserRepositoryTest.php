<?php

declare(strict_types=1);

namespace Tests\__Infrastructure__\Persistence;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\__Infrastructure__\Persistence\User\UserRepository;
use App\__Domain__\Data\User\Model;

class UserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_find_by_id(): void
    {
        $repo = new UserRepository();
        $user = new Model(null, 'John Doe', 'John', 'john@example.com', bcrypt('secret'));
        $saved = $repo->save($user);
        $found = $repo->findById($saved->id);
        $this->assertInstanceOf(Model::class, $found);
        $this->assertEquals('John Doe', $found->name);
        $this->assertEquals('john@example.com', $found->email);
    }

    public function test_find_by_email(): void
    {
        $repo = new UserRepository();
        $user = new Model(null, 'Jane', 'Jane', 'jane@example.com', bcrypt('secret'));
        $repo->save($user);
        $found = $repo->findByEmail('jane@example.com');
        $this->assertInstanceOf(Model::class, $found);
        $this->assertEquals('Jane', $found->name);
    }

    public function test_update(): void
    {
        $repo = new UserRepository();
        $user = new Model(null, 'Old', 'Old', 'old@example.com', bcrypt('secret'));
        $saved = $repo->save($user);
        $saved->name = 'New';
        $updated = $repo->save($saved);
        $this->assertEquals('New', $updated->name);
    }

    public function test_delete(): void
    {
        $repo = new UserRepository();
        $user = new Model(null, 'ToDelete', 'ToDelete', 'delete@example.com', bcrypt('secret'));
        $saved = $repo->save($user);
        $repo->delete($saved);
        $this->assertNull($repo->findById($saved->id));
    }
}
