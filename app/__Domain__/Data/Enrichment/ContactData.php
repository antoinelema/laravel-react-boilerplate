<?php

namespace App\__Domain__\Data\Enrichment;

/**
 * Données de contact extraites lors du web scraping
 */
class ContactData
{
    public function __construct(
        public readonly string $type, // 'email', 'phone', 'website'
        public readonly string $value,
        public readonly float $validationScore, // 0-100
        public readonly string $confidenceLevel, // 'low', 'medium', 'high'
        public readonly array $context = [], // Contexte d'extraction (position HTML, mots-clés proches, etc.)
        public readonly array $validationDetails = [] // Détails de la validation
    ) {}

    public static function email(
        string $email,
        float $validationScore,
        string $confidenceLevel,
        array $context = [],
        array $validationDetails = []
    ): self {
        return new self(
            type: 'email',
            value: $email,
            validationScore: $validationScore,
            confidenceLevel: $confidenceLevel,
            context: $context,
            validationDetails: $validationDetails
        );
    }

    public static function phone(
        string $phone,
        float $validationScore,
        string $confidenceLevel,
        array $context = [],
        array $validationDetails = []
    ): self {
        return new self(
            type: 'phone',
            value: $phone,
            validationScore: $validationScore,
            confidenceLevel: $confidenceLevel,
            context: $context,
            validationDetails: $validationDetails
        );
    }

    public static function website(
        string $website,
        float $validationScore,
        string $confidenceLevel,
        array $context = [],
        array $validationDetails = []
    ): self {
        return new self(
            type: 'website',
            value: $website,
            validationScore: $validationScore,
            confidenceLevel: $confidenceLevel,
            context: $context,
            validationDetails: $validationDetails
        );
    }

    public function isHighConfidence(): bool
    {
        return $this->confidenceLevel === 'high' && $this->validationScore >= 80;
    }

    public function isMediumConfidence(): bool
    {
        return $this->confidenceLevel === 'medium' && $this->validationScore >= 60;
    }

    public function isLowConfidence(): bool
    {
        return $this->confidenceLevel === 'low' || $this->validationScore < 60;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'value' => $this->value,
            'validation_score' => $this->validationScore,
            'confidence_level' => $this->confidenceLevel,
            'context' => $this->context,
            'validation_details' => $this->validationDetails,
        ];
    }
}