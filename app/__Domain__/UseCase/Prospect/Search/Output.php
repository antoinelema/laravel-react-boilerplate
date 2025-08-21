<?php

namespace App\__Domain__\UseCase\Prospect\Search;

use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Domain__\Data\ProspectSearch\Model as ProspectSearchModel;

/**
 * Output pour le Use Case de recherche de prospects
 */
class Output
{
    /**
     * @var ProspectModel[]
     */
    public array $prospects;
    public ?ProspectSearchModel $search;
    public int $totalFound;
    public array $availableSources;
    public bool $success;
    public ?string $errorMessage;

    public function __construct(
        array $prospects = [],
        ?ProspectSearchModel $search = null,
        int $totalFound = 0,
        array $availableSources = [],
        bool $success = true,
        ?string $errorMessage = null
    ) {
        $this->prospects = $prospects;
        $this->search = $search;
        $this->totalFound = $totalFound;
        $this->availableSources = $availableSources;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }

    public static function success(
        array $prospects,
        ?ProspectSearchModel $search = null,
        array $availableSources = []
    ): self {
        return new self(
            prospects: $prospects,
            search: $search,
            totalFound: count($prospects),
            availableSources: $availableSources,
            success: true
        );
    }

    public static function failure(string $errorMessage): self
    {
        return new self(
            success: false,
            errorMessage: $errorMessage
        );
    }
}