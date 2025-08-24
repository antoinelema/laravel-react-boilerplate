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
    getUniqueValues 
}) {
    const statusOptions = ['new', 'contacted', 'interested', 'qualified', 'converted', 'rejected']
    const sectorOptions = getUniqueValues('sector')
    const cityOptions = getUniqueValues('city')

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