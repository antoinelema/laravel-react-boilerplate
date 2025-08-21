import React, { useState, useEffect } from 'react'
import { Head } from '@inertiajs/react'
import Layout from './layout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, Search, MapPin, Phone, Mail, Globe, Star, MessageSquare } from 'lucide-react'
import { toast } from 'sonner'
import { apiClient } from '@/lib/api'

export default function ProspectSearch() {
    const [searchForm, setSearchForm] = useState({
        query: '',
        filters: {
            location: '',
            sector: '',
            radius: '',
            postal_code: '',
            limit: '20'
        },
        sources: ['google_maps', 'pages_jaunes'],
        save_search: true
    })

    const [searchResults, setSearchResults] = useState([])
    const [isSearching, setIsSearching] = useState(false)
    const [availableSources, setAvailableSources] = useState({})
    const [searchStats, setSearchStats] = useState(null)

    // Charger les sources disponibles au montage
    useEffect(() => {
        fetchAvailableSources()
    }, [])

    const fetchAvailableSources = async () => {
        try {
            const data = await apiClient.get('/api/v1/prospects/sources')
            setAvailableSources(data.data.sources)
        } catch (error) {
            toast.error('Impossible de charger les sources disponibles')
        }
    }

    const handleSearch = async (e) => {
        e.preventDefault()
        
        if (!searchForm.query.trim()) {
            toast.error('Veuillez saisir un terme de recherche')
            return
        }

        setIsSearching(true)
        setSearchResults([])

        try {
            // Nettoyer les données avant envoi
            const cleanedForm = {
                ...searchForm,
                filters: Object.fromEntries(
                    Object.entries(searchForm.filters).filter(([key, value]) => value !== '' && value !== null)
                )
            }

            // Convertir les chaînes en nombres pour radius et limit
            if (cleanedForm.filters.radius) {
                cleanedForm.filters.radius = parseInt(cleanedForm.filters.radius, 10)
            }
            if (cleanedForm.filters.limit) {
                cleanedForm.filters.limit = parseInt(cleanedForm.filters.limit, 10)
            }

            const data = await apiClient.post('/api/v1/prospects/search', cleanedForm)
            setSearchResults(data.data.prospects)
            setSearchStats({
                total: data.data.total_found,
                search: data.data.search,
                sources: data.data.available_sources
            })

            if (data.data.total_found === 0) {
                toast.info('Aucun prospect trouvé pour cette recherche')
            } else {
                toast.success(`${data.data.total_found} prospect(s) trouvé(s)`)
            }

        } catch (error) {
            toast.error(error.message || 'Erreur lors de la recherche')
        } finally {
            setIsSearching(false)
        }
    }

    const handleSaveProspect = async (prospect, note = '') => {
        try {
            const data = await apiClient.post('/api/v1/prospects', {
                name: prospect.name,
                company: prospect.company,
                sector: prospect.sector,
                city: prospect.city,
                postal_code: prospect.postal_code,
                address: prospect.address,
                contact_info: prospect.contact_info,
                description: prospect.description,
                relevance_score: prospect.relevance_score,
                source: prospect.source,
                external_id: prospect.external_id,
                search_id: searchStats?.search?.id,
                note: note.trim() || null
            })
            
            if (data.data.was_already_exists) {
                toast.info('Ce prospect existe déjà dans votre base')
            } else {
                toast.success('Prospect sauvegardé avec succès')
            }

        } catch (error) {
            toast.error(error.message || 'Erreur lors de la sauvegarde')
        }
    }

    const updateFormField = (field, value) => {
        if (field.includes('.')) {
            const [parent, child] = field.split('.')
            setSearchForm(prev => ({
                ...prev,
                [parent]: {
                    ...prev[parent],
                    [child]: value
                }
            }))
        } else {
            setSearchForm(prev => ({
                ...prev,
                [field]: value
            }))
        }
    }

    const toggleSource = (source) => {
        setSearchForm(prev => ({
            ...prev,
            sources: prev.sources.includes(source)
                ? prev.sources.filter(s => s !== source)
                : [...prev.sources, source]
        }))
    }

    return (
        <Layout>
            <Head title="Recherche de Prospects" />
            
            <div className="container mx-auto px-4 py-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Recherche de Prospects</h1>
                    <p className="text-gray-600">Trouvez de nouveaux prospects grâce aux API externes</p>
                </div>

                {/* Formulaire de recherche */}
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Paramètres de recherche</CardTitle>
                        <CardDescription>
                            Définissez vos critères pour trouver les meilleurs prospects
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSearch} className="space-y-4">
                            {/* Terme de recherche */}
                            <div>
                                <Label htmlFor="query">Terme de recherche *</Label>
                                <Input
                                    id="query"
                                    type="text"
                                    placeholder="Ex: restaurant, boulangerie, coiffeur..."
                                    value={searchForm.query}
                                    onChange={(e) => updateFormField('query', e.target.value)}
                                    required
                                />
                            </div>

                            {/* Filtres */}
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <Label htmlFor="location">Localisation</Label>
                                    <Input
                                        id="location"
                                        placeholder="Paris, Lyon..."
                                        value={searchForm.filters.location}
                                        onChange={(e) => updateFormField('filters.location', e.target.value)}
                                    />
                                </div>
                                
                                <div>
                                    <Label htmlFor="sector">Secteur</Label>
                                    <Input
                                        id="sector"
                                        placeholder="Restaurant, tech..."
                                        value={searchForm.filters.sector}
                                        onChange={(e) => updateFormField('filters.sector', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="postal_code">Code postal</Label>
                                    <Input
                                        id="postal_code"
                                        placeholder="75001"
                                        value={searchForm.filters.postal_code}
                                        onChange={(e) => updateFormField('filters.postal_code', e.target.value)}
                                    />
                                </div>

                                <div>
                                    <Label htmlFor="limit">Limite de résultats</Label>
                                    <Input
                                        id="limit"
                                        type="number"
                                        min="1"
                                        max="100"
                                        value={searchForm.filters.limit}
                                        onChange={(e) => updateFormField('filters.limit', e.target.value)}
                                    />
                                </div>
                            </div>

                            {/* Sources */}
                            <div>
                                <Label>Sources de données</Label>
                                <div className="flex flex-wrap gap-2 mt-2">
                                    {Object.entries(availableSources).map(([key, source]) => (
                                        <Badge
                                            key={key}
                                            variant={searchForm.sources.includes(key) ? "default" : "outline"}
                                            className={`cursor-pointer ${
                                                !source.available ? 'opacity-50' : ''
                                            }`}
                                            onClick={() => source.available && toggleSource(key)}
                                        >
                                            {source.name}
                                            {!source.available && ' (indisponible)'}
                                        </Badge>
                                    ))}
                                </div>
                                <p className="text-xs text-gray-500 mt-1">
                                    Sélectionnez les sources à utiliser pour votre recherche
                                </p>
                            </div>

                            {/* Bouton de recherche */}
                            <Button
                                type="submit"
                                disabled={isSearching}
                                className="w-full md:w-auto"
                            >
                                {isSearching ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Recherche en cours...
                                    </>
                                ) : (
                                    <>
                                        <Search className="mr-2 h-4 w-4" />
                                        Rechercher
                                    </>
                                )}
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                {/* Statistiques de recherche */}
                {searchStats && (
                    <Card className="mb-6">
                        <CardContent className="pt-4">
                            <div className="flex flex-wrap items-center justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <span className="text-sm font-medium">
                                        {searchStats.total} résultat(s) trouvé(s)
                                    </span>
                                    {searchStats.search && (
                                        <Badge variant="outline">
                                            Recherche sauvegardée
                                        </Badge>
                                    )}
                                </div>
                                
                                {searchStats.search && (
                                    <div className="text-xs text-gray-500">
                                        ID: {searchStats.search.id} | 
                                        Taux de conversion: {searchStats.search.conversion_rate.toFixed(1)}%
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Résultats de recherche */}
                {searchResults.length > 0 && (
                    <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        {searchResults.map((prospect, index) => (
                            <ProspectCard
                                key={`${prospect.external_id}-${index}`}
                                prospect={prospect}
                                onSave={(note) => handleSaveProspect(prospect, note)}
                            />
                        ))}
                    </div>
                )}

                {/* État vide */}
                {!isSearching && searchResults.length === 0 && !searchStats && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Search className="h-12 w-12 text-gray-400 mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Aucune recherche effectuée
                            </h3>
                            <p className="text-gray-500 text-center max-w-sm">
                                Utilisez le formulaire ci-dessus pour rechercher des prospects
                                dans les différentes bases de données.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </Layout>
    )
}

function ProspectCard({ prospect, onSave }) {
    const [isSaving, setIsSaving] = useState(false)
    const [note, setNote] = useState('')
    const [showNoteField, setShowNoteField] = useState(false)

    const handleSave = async () => {
        setIsSaving(true)
        try {
            await onSave(note)
            setNote('')
            setShowNoteField(false)
        } finally {
            setIsSaving(false)
        }
    }

    return (
        <Card className="h-full">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex-1">
                        <CardTitle className="text-lg mb-1">{prospect.name}</CardTitle>
                        {prospect.company && (
                            <CardDescription>{prospect.company}</CardDescription>
                        )}
                    </div>
                    <div className="flex items-center gap-1 ml-2">
                        <Star className="h-4 w-4 text-yellow-500 fill-current" />
                        <span className="text-sm font-medium">{prospect.relevance_score}</span>
                    </div>
                </div>
                
                {prospect.sector && (
                    <Badge variant="secondary" className="w-fit">
                        {prospect.sector}
                    </Badge>
                )}
            </CardHeader>
            
            <CardContent className="space-y-3">
                {/* Localisation */}
                {(prospect.city || prospect.address) && (
                    <div className="flex items-start gap-2">
                        <MapPin className="h-4 w-4 text-gray-500 mt-0.5 flex-shrink-0" />
                        <div className="text-sm text-gray-600">
                            {prospect.address && <div>{prospect.address}</div>}
                            {prospect.city && (
                                <div>
                                    {prospect.city}
                                    {prospect.postal_code && ` ${prospect.postal_code}`}
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {/* Informations de contact */}
                {prospect.contact_info && (
                    <div className="space-y-2">
                        {prospect.contact_info.phone && (
                            <div className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-gray-500" />
                                <span className="text-sm">{prospect.contact_info.phone}</span>
                            </div>
                        )}
                        
                        {prospect.contact_info.email && (
                            <div className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-gray-500" />
                                <span className="text-sm">{prospect.contact_info.email}</span>
                            </div>
                        )}
                        
                        {prospect.contact_info.website && (
                            <div className="flex items-center gap-2">
                                <Globe className="h-4 w-4 text-gray-500" />
                                <a
                                    href={prospect.contact_info.website}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm text-blue-600 hover:underline truncate"
                                >
                                    {prospect.contact_info.website}
                                </a>
                            </div>
                        )}
                    </div>
                )}

                {/* Description */}
                {prospect.description && (
                    <p className="text-sm text-gray-600 line-clamp-3">
                        {prospect.description}
                    </p>
                )}

                {/* Note optionnelle */}
                {showNoteField && (
                    <div className="space-y-2 pt-2 border-t">
                        <Label htmlFor={`note-${prospect.external_id}`} className="text-xs">
                            Note (optionnelle)
                        </Label>
                        <Textarea
                            id={`note-${prospect.external_id}`}
                            placeholder="Ajoutez une note sur ce prospect..."
                            value={note}
                            onChange={(e) => setNote(e.target.value)}
                            rows={2}
                            className="text-xs"
                        />
                    </div>
                )}

                {/* Actions */}
                <div className="flex items-center justify-between pt-2 border-t">
                    <Badge variant="outline" className="text-xs">
                        Source: {prospect.source}
                    </Badge>
                    
                    <div className="flex gap-1">
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setShowNoteField(!showNoteField)}
                            className="text-xs px-2"
                        >
                            <MessageSquare className="h-3 w-3" />
                        </Button>
                        
                        <Button
                            onClick={handleSave}
                            disabled={isSaving}
                            size="sm"
                        >
                            {isSaving ? (
                                <>
                                    <Loader2 className="mr-1 h-3 w-3 animate-spin" />
                                    Sauvegarde...
                                </>
                            ) : (
                                'Sauvegarder'
                            )}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}