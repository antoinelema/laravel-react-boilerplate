<?php

namespace App\__Domain__\Data\ProspectNote;

/**
 * Interface Collection pour les ProspectNotes
 */
interface Collection
{
    public function findById(int $id): ?Model;
    
    public function findByProspectId(int $prospectId): array;
    
    public function findByProspectIdAndType(int $prospectId, string $type): array;
    
    public function findRemindersDue(\DateTimeImmutable $date): array;
    
    public function save(Model $note): Model;
    
    public function delete(Model $note): void;
    
    public function countByProspectId(int $prospectId): int;
}