<?php

namespace App\__Infrastructure__\Persistence\ProspectNote;

use App\__Domain__\Data\ProspectNote\Collection;
use App\__Domain__\Data\ProspectNote\Model;
use App\__Infrastructure__\Eloquent\ProspectNoteEloquent;

class ProspectNoteRepository implements Collection
{
    public function findById(int $id): ?Model
    {
        $eloquent = ProspectNoteEloquent::find($id);
        return $eloquent ? $this->toDomain($eloquent) : null;
    }

    public function findByProspectId(int $prospectId): array
    {
        $notes = ProspectNoteEloquent::where('prospect_id', $prospectId)
                                   ->orderBy('created_at', 'desc')
                                   ->get();

        return $notes->map(fn($n) => $this->toDomain($n))->toArray();
    }

    public function findByProspectIdAndType(int $prospectId, string $type): array
    {
        $notes = ProspectNoteEloquent::where('prospect_id', $prospectId)
                                   ->byType($type)
                                   ->orderBy('created_at', 'desc')
                                   ->get();

        return $notes->map(fn($n) => $this->toDomain($n))->toArray();
    }

    public function findRemindersDue(\DateTimeImmutable $date): array
    {
        $notes = ProspectNoteEloquent::dueReminders($date->format('Y-m-d H:i:s'))
                                   ->get();

        return $notes->map(fn($n) => $this->toDomain($n))->toArray();
    }

    public function save(Model $note): Model
    {
        if ($note->id) {
            // Update existing note
            $eloquent = ProspectNoteEloquent::findOrFail($note->id);
            $eloquent->fill($this->toArray($note));
            $eloquent->save();
        } else {
            // Create new note
            $data = $this->toArray($note);
            unset($data['id']);
            $eloquent = ProspectNoteEloquent::create($data);
        }

        return $this->toDomain($eloquent->fresh());
    }

    public function delete(Model $note): void
    {
        if ($note->id) {
            ProspectNoteEloquent::destroy($note->id);
        }
    }

    public function countByProspectId(int $prospectId): int
    {
        return ProspectNoteEloquent::where('prospect_id', $prospectId)->count();
    }

    private function toDomain(ProspectNoteEloquent $eloquent): Model
    {
        return new Model(
            id: $eloquent->id,
            prospectId: $eloquent->prospect_id,
            userId: $eloquent->user_id,
            content: $eloquent->content,
            type: $eloquent->type,
            remindedAt: $eloquent->reminded_at ? new \DateTimeImmutable($eloquent->reminded_at) : null,
            createdAt: $eloquent->created_at ? new \DateTimeImmutable($eloquent->created_at) : null,
            updatedAt: $eloquent->updated_at ? new \DateTimeImmutable($eloquent->updated_at) : null
        );
    }

    private function toArray(Model $note): array
    {
        return [
            'id' => $note->id,
            'prospect_id' => $note->prospectId,
            'user_id' => $note->userId,
            'content' => $note->content,
            'type' => $note->type,
            'reminded_at' => $note->remindedAt?->format('Y-m-d H:i:s'),
        ];
    }
}