import React from 'react'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Filter, Search } from 'lucide-react'

export function ProspectFilters({ 
    searchTerm, 
    onSearchChange, 
    filters, 
    onFilterChange, 
    onClearFilters, 
    prospects, 
    filteredCount, 
    getUniqueValues,
    enrichmentStats 
}) {
    const statusOptions = ['new', 'contacted', 'interested', 'qualified', 'converted', 'rejected']
    const sectorOptions = getUniqueValues('sector')
    const cityOptions = getUniqueValues('city')
    
    const enrichmentStatusOptions = [
        { value: 'never_enriched', label: 'Jamais enrichi' },
        { value: 'recently_enriched', label: 'Enrichi récemment (<30j)' },
        { value: 'needs_refresh', label: 'À rafraîchir (>90j)' },
        { value: 'complete_data', label: 'Données complètes (≥80%)' },
        { value: 'incomplete_data', label: 'Données incomplètes (<80%)' },
        { value: 'has_contacts', label: 'Email et téléphone présents' },
        { value: 'missing_contacts', label: 'Contacts manquants' }
    ]
    
    const enrichmentEligibilityOptions = [
        { value: 'eligible', label: 'Éligible à l\'enrichissement' },
        { value: 'not_eligible', label: 'Non éligible' },
        { value: 'blacklisted', label: 'Enrichissement désactivé' }
    ]

    return (
        <Card className="mb-6">
            <CardHeader>
                <CardTitle className="flex items-center">
                    <Filter className="mr-2 h-5 w-5" />
                    Filtres et recherche
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div>
                    <Label htmlFor="search">Recherche</Label>
                    <div className="relative">
                        <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                        <Input
                            id="search"
                            placeholder="Rechercher par nom, entreprise, secteur, ville..."
                            className="pl-10"
                            value={searchTerm}
                            onChange={(e) => onSearchChange(e.target.value)}
                        />
                    </div>
                </div>
                
                <div className="space-y-4">
                    {/* Filtres généraux */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <Label htmlFor="status-filter">Statut</Label>
                            <select
                                id="status-filter"
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={filters.status}
                                onChange={(e) => onFilterChange('status', e.target.value)}
                            >
                                <option value="">Tous les statuts</option>
                                {statusOptions.map(status => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </select>
                        </div>
                        
                        <div>
                            <Label htmlFor="sector-filter">Secteur</Label>
                            <select
                                id="sector-filter"
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={filters.sector}
                                onChange={(e) => onFilterChange('sector', e.target.value)}
                            >
                                <option value="">Tous les secteurs</option>
                                {sectorOptions.map(sector => (
                                    <option key={sector} value={sector}>
                                        {sector}
                                    </option>
                                ))}
                            </select>
                        </div>
                        
                        <div>
                            <Label htmlFor="city-filter">Ville</Label>
                            <select
                                id="city-filter"
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={filters.city}
                                onChange={(e) => onFilterChange('city', e.target.value)}
                            >
                                <option value="">Toutes les villes</option>
                                {cityOptions.map(city => (
                                    <option key={city} value={city}>
                                        {city}
                                    </option>
                                ))}
                            </select>
                        </div>
                        
                        <div>
                            <Label htmlFor="score-filter">Score minimum</Label>
                            <Input
                                id="score-filter"
                                type="number"
                                min="0"
                                max="100"
                                placeholder="Score min"
                                value={filters.min_score}
                                onChange={(e) => onFilterChange('min_score', e.target.value)}
                            />
                        </div>
                    </div>

                    {/* Filtres d'enrichissement */}
                    <div className="border-t pt-4">
                        <h4 className="text-sm font-medium text-gray-700 mb-3 flex items-center">
                            🔍 Filtres d'enrichissement
                            {enrichmentStats?.stats && !enrichmentStats.isLoading && (
                                <span className="ml-2 text-xs text-gray-500">
                                    ({enrichmentStats.stats.eligible_for_enrichment || 0} éligibles)
                                </span>
                            )}
                        </h4>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="enrichment-status-filter">Statut d'enrichissement</Label>
                                <select
                                    id="enrichment-status-filter"
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={filters.enrichment_status}
                                    onChange={(e) => onFilterChange('enrichment_status', e.target.value)}
                                >
                                    <option value="">Tous</option>
                                    {enrichmentStatusOptions.map(option => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            
                            <div>
                                <Label htmlFor="enrichment-eligibility-filter">Éligibilité</Label>
                                <select
                                    id="enrichment-eligibility-filter"
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                    value={filters.enrichment_eligibility}
                                    onChange={(e) => onFilterChange('enrichment_eligibility', e.target.value)}
                                >
                                    <option value="">Tous</option>
                                    {enrichmentEligibilityOptions.map(option => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div className="flex justify-between items-center pt-2">
                    <span className="text-sm text-gray-600">
                        {filteredCount} prospect(s) trouvé(s)
                    </span>
                    <Button variant="outline" onClick={onClearFilters}>
                        Effacer les filtres
                    </Button>
                </div>
            </CardContent>
        </Card>
    )
}