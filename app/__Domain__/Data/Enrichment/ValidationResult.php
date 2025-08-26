<?php

namespace App\__Domain__\Data\Enrichment;

/**
 * Résultat de validation des données extraites
 */
class ValidationResult
{
    public function __construct(
        public readonly float $overallScore, // Score global 0-100
        public readonly array $ruleScores, // Scores par règle
        public readonly bool $isValid,
        public readonly array $validationMessages = [],
        public readonly array $metadata = []
    ) {}

    public static function create(
        float $overallScore,
        array $ruleScores,
        bool $isValid = null,
        array $validationMessages = [],
        array $metadata = []
    ): self {
        return new self(
            overallScore: $overallScore,
            ruleScores: $ruleScores,
            isValid: $isValid ?? ($overallScore >= 40), // Seuil par défaut réduit
            validationMessages: $validationMessages,
            metadata: $metadata
        );
    }

    public static function empty(): self
    {
        return new self(
            overallScore: 0.0,
            ruleScores: [],
            isValid: false,
            validationMessages: ['No validation performed'],
            metadata: []
        );
    }

    public static function valid(float $score, array $ruleScores = []): self
    {
        return new self(
            overallScore: $score,
            ruleScores: $ruleScores,
            isValid: true,
            validationMessages: ['Validation passed'],
            metadata: []
        );
    }

    public static function invalid(float $score, array $reasons = [], array $ruleScores = []): self
    {
        return new self(
            overallScore: $score,
            ruleScores: $ruleScores,
            isValid: false,
            validationMessages: $reasons,
            metadata: []
        );
    }

    public function getConfidenceLevel(): string
    {
        if ($this->overallScore >= 80) {
            return 'high';
        } elseif ($this->overallScore >= 60) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    public function getRuleScore(string $ruleName): float
    {
        return $this->ruleScores[$ruleName] ?? 0.0;
    }

    public function addMessage(string $message): self
    {
        return new self(
            overallScore: $this->overallScore,
            ruleScores: $this->ruleScores,
            isValid: $this->isValid,
            validationMessages: array_merge($this->validationMessages, [$message]),
            metadata: $this->metadata
        );
    }

    public function toArray(): array
    {
        return [
            'overall_score' => $this->overallScore,
            'rule_scores' => $this->ruleScores,
            'is_valid' => $this->isValid,
            'confidence_level' => $this->getConfidenceLevel(),
            'validation_messages' => $this->validationMessages,
            'metadata' => $this->metadata,
        ];
    }
}