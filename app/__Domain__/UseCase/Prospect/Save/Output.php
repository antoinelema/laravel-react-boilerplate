<?php

namespace App\__Domain__\UseCase\Prospect\Save;

use App\__Domain__\Data\Prospect\Model as ProspectModel;

/**
 * Output pour le Use Case de sauvegarde de prospect
 */
class Output
{
    public ?ProspectModel $prospect;
    public bool $success;
    public ?string $errorMessage;
    public bool $wasAlreadyExists;

    public function __construct(
        ?ProspectModel $prospect = null,
        bool $success = true,
        ?string $errorMessage = null,
        bool $wasAlreadyExists = false
    ) {
        $this->prospect = $prospect;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
        $this->wasAlreadyExists = $wasAlreadyExists;
    }

    public static function success(ProspectModel $prospect, bool $wasAlreadyExists = false): self
    {
        return new self(
            prospect: $prospect,
            success: true,
            wasAlreadyExists: $wasAlreadyExists
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage
        );
    }

    public static function alreadyExists(ProspectModel $prospect): self
    {
        return new self(
            prospect: $prospect,
            success: true,
            wasAlreadyExists: true
        );
    }
}