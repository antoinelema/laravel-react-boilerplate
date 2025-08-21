<?php

namespace App\__Application__\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de validation pour la recherche de prospects
 */
class ProspectSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée par les middlewares
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:255'],
            'filters' => ['sometimes', 'array'],
            'filters.location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filters.sector' => ['sometimes', 'nullable', 'string', 'max:255'],
            'filters.radius' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50000'],
            'filters.postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'filters.limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            'sources' => ['sometimes', 'array'],
            'sources.*' => ['string', 'in:google_maps,nominatim,clearbit,hunter'],
            'save_search' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Le terme de recherche est obligatoire',
            'query.min' => 'Le terme de recherche doit contenir au moins 2 caractères',
            'query.max' => 'Le terme de recherche ne peut pas dépasser 255 caractères',
            'filters.array' => 'Les filtres doivent être un objet',
            'filters.location.max' => 'La localisation ne peut pas dépasser 255 caractères',
            'filters.sector.max' => 'Le secteur ne peut pas dépasser 255 caractères',
            'filters.radius.min' => 'Le rayon doit être au minimum de 1 mètre',
            'filters.radius.max' => 'Le rayon ne peut pas dépasser 50 km',
            'filters.postal_code.max' => 'Le code postal ne peut pas dépasser 10 caractères',
            'filters.limit.min' => 'Le nombre de résultats minimum est 1',
            'filters.limit.max' => 'Le nombre de résultats maximum est 100',
            'sources.array' => 'Les sources doivent être un tableau',
            'sources.*.in' => 'Source invalide. Sources supportées: google_maps, nominatim, clearbit, hunter',
        ];
    }

    public function getQuery(): string
    {
        return $this->validated()['query'];
    }

    public function getFilters(): array
    {
        return $this->validated()['filters'] ?? [];
    }

    public function getSources(): array
    {
        return $this->validated()['sources'] ?? [];
    }

    public function shouldSaveSearch(): bool
    {
        return $this->validated()['save_search'] ?? true;
    }
}