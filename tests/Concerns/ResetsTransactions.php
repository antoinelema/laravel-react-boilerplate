<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait ResetsTransactions 
{
    use RefreshDatabase {
        beginDatabaseTransaction as refreshDatabaseBeginTransaction;
    }
    
    /**
     * Override beginDatabaseTransaction to avoid transaction conflicts
     */
    protected function beginDatabaseTransaction(): void
    {
        // Only create database structure, don't start transactions in memory mode
        if (config('database.connections.' . config('database.default') . '.database') === ':memory:') {
            // For in-memory SQLite, just create the structure fresh each time
            if (!$this->isMemoryDatabaseSetUp) {
                $this->artisan('migrate:fresh');
                $this->isMemoryDatabaseSetUp = true;
            }
        } else {
            // For other databases, use the normal RefreshDatabase behavior
            $this->refreshDatabaseBeginTransaction();
        }
    }
    
    /**
     * Track if memory database is set up
     */
    protected $isMemoryDatabaseSetUp = false;
}