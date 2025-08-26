<?php

namespace App\__Domain__\UseCase\ProspectCategory\Output;

use App\__Domain__\Data\ProspectCategory\Model as ProspectCategoryModel;

class UpdateOutput
{
    public function __construct(
        public ?ProspectCategoryModel $category = null,
        public bool $success = true,
        public ?string $errorMessage = null
    ) {}
}