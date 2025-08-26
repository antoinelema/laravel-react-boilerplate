<?php

namespace App\__Domain__\Data\Enrichment;

use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;

/**
 * Résultat d'une session de web scraping
 */
class WebScrapingResult
{
    public function __construct(
        public readonly string $prospectName,
        public readonly string $prospectCompany,
        public readonly string $source,
        public readonly array $contacts,
        public readonly ValidationResult $validation,
        public readonly array $metadata = [],
        public readonly float $executionTimeMs = 0.0,
        public readonly bool $success = true,
        public readonly ?string $errorMessage = null
    ) {}

    public static function success(
        string $prospectName,
        string $prospectCompany,
        string $source,
        array $contacts,
        ValidationResult $validation,
        array $metadata = [],
        float $executionTimeMs = 0.0
    ): self {
        return new self(
            prospectName: $prospectName,
            prospectCompany: $prospectCompany,
            source: $source,
            contacts: $contacts,
            validation: $validation,
            metadata: $metadata,
            executionTimeMs: $executionTimeMs,
            success: true
        );
    }

    public static function failure(
        string $prospectName,
        string $prospectCompany,
        string $source,
        string $errorMessage,
        float $executionTimeMs = 0.0
    ): self {
        return new self(
            prospectName: $prospectName,
            prospectCompany: $prospectCompany,
            source: $source,
            contacts: [],
            validation: ValidationResult::empty(),
            metadata: [],
            executionTimeMs: $executionTimeMs,
            success: false,
            errorMessage: $errorMessage
        );
    }

    public function getBestContacts(int $limit = 5): array
    {
        $sortedContacts = $this->contacts;
        
        // Trier par score de validation décroissant
        usort($sortedContacts, fn($a, $b) => 
            $b->validationScore <=> $a->validationScore
        );

        return array_slice($sortedContacts, 0, $limit);
    }

    public function hasValidContacts(): bool
    {
        return !empty($this->contacts) && 
               $this->validation->isValid &&
               $this->success;
    }

    public function toArray(): array
    {
        return [
            'prospect_name' => $this->prospectName,
            'prospect_company' => $this->prospectCompany,
            'source' => $this->source,
            'contacts' => array_map(fn($contact) => $contact->toArray(), $this->contacts),
            'validation' => $this->validation->toArray(),
            'metadata' => $this->metadata,
            'execution_time_ms' => $this->executionTimeMs,
            'success' => $this->success,
            'error_message' => $this->errorMessage,
        ];
    }
}