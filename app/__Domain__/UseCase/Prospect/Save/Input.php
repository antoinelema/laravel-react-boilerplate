<?php

namespace App\__Domain__\UseCase\Prospect\Save;

use App\__Domain__\Data\Prospect\Model as ProspectModel;

/**
 * Input pour le Use Case de sauvegarde de prospect
 */
class Input
{
    public int $userId;
    public ?ProspectModel $prospect;
    public ?int $searchId; // ID de la recherche d'origine pour statistiques
    public ?string $note; // Note optionnelle à créer lors de la sauvegarde
    
    // Données brutes pour création d'un nouveau prospect
    public ?array $prospectData;

    public function __construct(
        int $userId,
        ?ProspectModel $prospect = null,
        ?array $prospectData = null,
        ?int $searchId = null,
        ?string $note = null
    ) {
        $this->userId = $userId;
        $this->prospect = $prospect;
        $this->prospectData = $prospectData;
        $this->searchId = $searchId;
        $this->note = $note;
    }

    public static function fromProspect(int $userId, ProspectModel $prospect, ?int $searchId = null, ?string $note = null): self
    {
        return new self($userId, $prospect, null, $searchId, $note);
    }

    public static function fromData(int $userId, array $prospectData, ?int $searchId = null, ?string $note = null): self
    {
        return new self($userId, null, $prospectData, $searchId, $note);
    }
}