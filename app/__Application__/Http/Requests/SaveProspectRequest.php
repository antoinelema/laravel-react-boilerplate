<?php

namespace App\__Application__\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request de validation pour sauvegarder un prospect
 */
class SaveProspectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // L'autorisation est gérée par les middlewares
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'address' => ['nullable', 'string', 'max:500'],
            'contact_info' => ['nullable', 'array'],
            'contact_info.email' => ['nullable', 'email', 'max:255'],
            'contact_info.phone' => ['nullable', 'string', 'max:20'],
            'contact_info.website' => ['nullable', 'url', 'max:255'],
            'contact_info.social_networks' => ['nullable', 'array'],
            'description' => ['nullable', 'string', 'max:1000'],
            'relevance_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'string', 'in:new,contacted,interested,qualified,converted,rejected'],
            'source' => ['nullable', 'string', 'max:50'],
            'external_id' => ['nullable', 'string', 'max:255'],
            'raw_data' => ['nullable', 'array'],
            'search_id' => ['nullable', 'integer', 'exists:prospect_searches,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire',
            'name.max' => 'Le nom ne peut pas dépasser 255 caractères',
            'company.max' => 'Le nom de l\'entreprise ne peut pas dépasser 255 caractères',
            'sector.max' => 'Le secteur ne peut pas dépasser 255 caractères',
            'city.max' => 'La ville ne peut pas dépasser 255 caractères',
            'postal_code.max' => 'Le code postal ne peut pas dépasser 10 caractères',
            'address.max' => 'L\'adresse ne peut pas dépasser 500 caractères',
            'contact_info.array' => 'Les informations de contact doivent être un objet',
            'contact_info.email.email' => 'L\'adresse email n\'est pas valide',
            'contact_info.email.max' => 'L\'adresse email ne peut pas dépasser 255 caractères',
            'contact_info.phone.max' => 'Le numéro de téléphone ne peut pas dépasser 20 caractères',
            'contact_info.website.url' => 'L\'URL du site web n\'est pas valide',
            'contact_info.website.max' => 'L\'URL du site web ne peut pas dépasser 255 caractères',
            'description.max' => 'La description ne peut pas dépasser 1000 caractères',
            'relevance_score.integer' => 'Le score de pertinence doit être un nombre entier',
            'relevance_score.min' => 'Le score de pertinence doit être au minimum 0',
            'relevance_score.max' => 'Le score de pertinence doit être au maximum 100',
            'status.in' => 'Statut invalide',
            'source.max' => 'La source ne peut pas dépasser 50 caractères',
            'external_id.max' => 'L\'ID externe ne peut pas dépasser 255 caractères',
            'search_id.exists' => 'La recherche référencée n\'existe pas',
            'note.max' => 'La note ne peut pas dépasser 1000 caractères',
        ];
    }

    public function getProspectData(): array
    {
        $data = $this->validated();
        
        // Nettoyer les valeurs null et vides
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    public function getSearchId(): ?int
    {
        return $this->validated()['search_id'] ?? null;
    }
}