import React, { useState, useEffect } from 'react'
import { Head } from '@inertiajs/react'
import Layout from './layout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Textarea } from '@/components/ui/textarea'
import { Loader2, Search, MapPin, Phone, Mail, Globe, Star, MessageSquare, Users, Building2, Database, TrendingUp, Copy, CheckCircle, AlertTriangle } from 'lucide-react'
import { toast } from 'sonner'
import { secureApiClient as apiClient } from '@/lib/secureApi'

export default function ProspectSearch() {
    const [searchForm, setSearchForm] = useState({
        query: '',
        filters: {
            location: '', // Champ fusionné pour ville/code postal
            sector: '',
            radius: 5, // Rayon par défaut en km
            limit: '20'
        },
        sources: [], // L'agrégateur utilise toutes les sources disponibles
        save_search: true
    })

    const [searchResults, setSearchResults] = useState([])
    const [isSearching, setIsSearching] = useState(false)
    const [availableSources, setAvailableSources] = useState({})
    const [searchStats, setSearchStats] = useState(null)
    const [duplicatesFound, setDuplicatesFound] = useState([])
    const [deduplicationInfo, setDeduplicationInfo] = useState(null)
    const [showDuplicates, setShowDuplicates] = useState(false)

    // Charger les sources disponibles au montage
    useEffect(() => {
        fetchAvailableSources()
    }, [])

    const fetchAvailableSources = async () => {
        try {
            const data = await apiClient.get('/api/v1/prospects/sources')
            setAvailableSources(data.data.sources)
        } catch (error) {
            // Ne pas afficher d'erreur si c'est un problème d'authentification
            // Les sources par défaut seront utilisées
            if (!error.message.includes('Session expirée') && !error.message.includes('401')) {
                toast.error('Impossible de charger les sources disponibles')
            }
            console.warn('Could not fetch available sources:', error.message)
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
                // Convertir km en mètres pour le backend
                cleanedForm.filters.radius = parseInt(cleanedForm.filters.radius, 10) * 1000
            }
            if (cleanedForm.filters.limit) {
                cleanedForm.filters.limit = parseInt(cleanedForm.filters.limit, 10)
            }

            const data = await apiClient.post('/api/v1/prospects/search', cleanedForm)
            setSearchResults(data.data.prospects)
            setDuplicatesFound(data.data.duplicates_found || [])
            setDeduplicationInfo(data.data.deduplication_info || null)
            setSearchStats({
                total: data.data.total_found,
                duplicates: data.data.duplicates_found || 0,
                search: data.data.search,
                sources: data.data.available_sources,
                sourcesStats: data.data.sources_stats || {},
                cacheInfo: data.data.cache_info
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


    return (
        <Layout>
            <Head title="Recherche de Prospects" />
            
            <div className="container mx-auto px-4 py-6">
                <div className="mb-6">
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Recherche Multi-Source</h1>
                    <p className="text-gray-600">
                        Recherche automatique dans toutes les sources légales avec agrégation intelligente et déduplication des résultats
                    </p>
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
                                        placeholder="Paris, Lyon, 75001, Paris 75001..."
                                        value={searchForm.filters.location}
                                        onChange={(e) => updateFormField('filters.location', e.target.value)}
                                    />
                                    <p className="text-xs text-gray-500 mt-1">
                                        Ville, code postal ou combinaison
                                    </p>
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
                                    <Label htmlFor="radius">Rayon de recherche</Label>
                                    <div className="space-y-2">
                                        <div className="flex items-center space-x-2">
                                            <input
                                                id="radius"
                                                type="range"
                                                min="1"
                                                max="50"
                                                step="1"
                                                value={searchForm.filters.radius}
                                                onChange={(e) => updateFormField('filters.radius', parseInt(e.target.value))}
                                                className="flex-1 h-2 bg-gray-200 rounded-lg appearance-none cursor-pointer"
                                            />
                                            <span className="text-sm font-medium text-gray-700 min-w-[3rem]">
                                                {searchForm.filters.radius} km
                                            </span>
                                        </div>
                                        <div className="flex justify-between text-xs text-gray-400">
                                            <span>1 km</span>
                                            <span>50 km</span>
                                        </div>
                                    </div>
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

                            {/* Affichage des sources disponibles (informatif seulement) */}
                            {Object.keys(availableSources).length > 0 && (
                                <div>
                                    <Label>Sources utilisées automatiquement</Label>
                                    <div className="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-2">
                                        {Object.entries(availableSources).map(([key, source]) => {
                                            const getSourceIcon = (sourceType) => {
                                                switch (sourceType) {
                                                    case 'geographic':
                                                        return <MapPin className="h-4 w-4" />
                                                    case 'enrichment':
                                                        return <Building2 className="h-4 w-4" />
                                                    case 'contact':
                                                        return <Mail className="h-4 w-4" />
                                                    default:
                                                        return <Database className="h-4 w-4" />
                                                }
                                            }

                                            return (
                                                <div
                                                    key={key}
                                                    className={`
                                                        p-3 rounded-lg border
                                                        ${source.available 
                                                            ? 'border-green-200 bg-green-50' 
                                                            : 'border-orange-200 bg-orange-50'
                                                        }
                                                    `}
                                                >
                                                    <div className="flex items-center gap-2 mb-1">
                                                        {getSourceIcon(source.type)}
                                                        <span className="font-medium text-sm">{source.name}</span>
                                                        {source.available ? (
                                                            <CheckCircle className="h-3 w-3 text-green-600 ml-auto" />
                                                        ) : (
                                                            <AlertTriangle className="h-3 w-3 text-orange-600 ml-auto" />
                                                        )}
                                                    </div>
                                                    <p className="text-xs text-gray-600">{source.description}</p>
                                                    <p className={`text-xs mt-1 ${source.available ? 'text-green-700' : 'text-orange-700'}`}>
                                                        {source.available ? 'Prêt' : 'Mode démo'}
                                                    </p>
                                                </div>
                                            )
                                        })}
                                    </div>
                                    <p className="text-xs text-gray-500 mt-2">
                                        L'agrégateur utilise automatiquement toutes les sources disponibles et fusionne les résultats.
                                    </p>
                                </div>
                            )}

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

                {/* Statistiques de recherche agrégée */}
                {searchStats && (
                    <Card className="mb-6">
                        <CardContent className="pt-4">
                            <div className="space-y-4">
                                {/* Résultats globaux */}
                                <div className="flex flex-wrap items-center justify-between gap-4">
                                    <div className="flex items-center gap-4">
                                        <span className="text-sm font-medium">
                                            {searchStats.total} prospect(s) trouvé(s)
                                        </span>
                                        {searchStats.duplicates > 0 && (
                                            <Badge 
                                                variant="secondary" 
                                                className="cursor-pointer"
                                                onClick={() => setShowDuplicates(!showDuplicates)}
                                            >
                                                {searchStats.duplicates} doublon(s) détecté(s)
                                            </Badge>
                                        )}
                                        {searchStats.search && (
                                            <Badge variant="outline">
                                                Recherche sauvegardée
                                            </Badge>
                                        )}
                                        {searchStats.cacheInfo?.from_cache && (
                                            <Badge variant="outline" className="text-green-600">
                                                Cache ({Math.round(searchStats.cacheInfo.age_seconds / 60)}min)
                                            </Badge>
                                        )}
                                    </div>
                                    
                                    {searchStats.search && (
                                        <div className="text-xs text-gray-500">
                                            ID: {searchStats.search.id}
                                            {searchStats.search.conversion_rate !== undefined && (
                                                <> | Taux de conversion: {searchStats.search.conversion_rate.toFixed(1)}%</>
                                            )}
                                        </div>
                                    )}
                                </div>

                                {/* Statistiques par source */}
                                {searchStats.sourcesStats && (
                                    <div>
                                        <h4 className="text-sm font-medium mb-2">Performance des sources</h4>
                                        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                                            {Object.entries(searchStats.sourcesStats.by_source || {}).map(([source, stats]) => (
                                                <div key={source} className="text-center p-2 bg-gray-50 rounded-lg">
                                                    <div className="text-xs font-medium capitalize">{source}</div>
                                                    <div className="text-lg font-bold text-blue-600">{stats.count}</div>
                                                    <div className="text-xs text-gray-500">
                                                        {stats.response_time_ms}ms
                                                        {stats.cached && ' (cache)'}
                                                    </div>
                                                    {!stats.success && (
                                                        <div className="text-xs text-red-500">Erreur</div>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="text-xs text-gray-500 mt-2">
                                            Temps total: {((searchStats.sourcesStats.total_time_seconds || 0) * 1000).toFixed(0)}ms | 
                                            Sources réussies: {searchStats.sourcesStats.sources_successful || 0}/{searchStats.sourcesStats.sources_used || 0}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                )}

                {/* Interface de gestion des doublons */}
                {showDuplicates && duplicatesFound.length > 0 && (
                    <Card className="mb-6 border-orange-200">
                        <CardHeader>
                            <CardTitle className="text-orange-800">Doublons détectés</CardTitle>
                            <CardDescription>
                                {duplicatesFound.length} groupe(s) de doublons ont été détectés et fusionnés automatiquement.
                                Les données ont été combinées intelligemment selon les critères de confiance.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {duplicatesFound.map((duplicateGroup, groupIndex) => (
                                    <div key={groupIndex} className="border rounded-lg p-4 bg-orange-50">
                                        <div className="flex items-center justify-between mb-3">
                                            <h4 className="font-medium text-orange-900">
                                                Groupe {groupIndex + 1} - {duplicateGroup.master_record?.name || 'Prospect fusionné'}
                                            </h4>
                                            <Badge variant="outline" className="text-orange-700">
                                                {duplicateGroup.duplicates?.length || 0} doublons
                                            </Badge>
                                        </div>
                                        
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            {/* Prospect principal (résultat de la fusion) */}
                                            <div className="border-l-4 border-green-500 pl-3 bg-green-50 rounded-r">
                                                <div className="text-sm font-medium text-green-800 mb-2">✓ Résultat fusionné</div>
                                                <div className="space-y-1 text-sm">
                                                    <div><strong>Nom:</strong> {duplicateGroup.master_record?.name}</div>
                                                    <div><strong>Entreprise:</strong> {duplicateGroup.master_record?.company}</div>
                                                    <div><strong>Sources:</strong> {duplicateGroup.master_record?.merged_from?.join(', ')}</div>
                                                    <div><strong>Score confiance:</strong> {duplicateGroup.master_record?.confidence_score?.toFixed(2)}</div>
                                                </div>
                                            </div>
                                            
                                            {/* Doublons détectés */}
                                            <div className="space-y-2">
                                                <div className="text-sm font-medium text-gray-700 mb-2">Doublons originaux</div>
                                                {duplicateGroup.duplicates?.slice(0, 3).map((duplicate, dupIndex) => (
                                                    <div key={dupIndex} className="text-sm bg-gray-50 p-2 rounded border-l-2 border-gray-300">
                                                        <div><strong>Source:</strong> {duplicate.source}</div>
                                                        <div><strong>Nom:</strong> {duplicate.name}</div>
                                                        <div><strong>Similitude:</strong> {(duplicate.similarity_score * 100)?.toFixed(1)}%</div>
                                                    </div>
                                                ))}
                                                {duplicateGroup.duplicates?.length > 3 && (
                                                    <div className="text-xs text-gray-500">
                                                        ... et {duplicateGroup.duplicates.length - 3} autres
                                                    </div>
                                                )}
                                            </div>
                                        </div>
                                        
                                        {/* Informations de déduplication */}
                                        {duplicateGroup.deduplication_method && (
                                            <div className="mt-3 p-2 bg-blue-50 rounded text-xs">
                                                <strong>Méthode:</strong> {duplicateGroup.deduplication_method} | 
                                                <strong> Critères:</strong> {duplicateGroup.matching_criteria?.join(', ')}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                            
                            {deduplicationInfo && (
                                <div className="mt-4 p-3 bg-blue-50 rounded-lg text-sm">
                                    <h5 className="font-medium text-blue-900 mb-2">Informations de déduplication</h5>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-2 text-xs">
                                        <div><strong>Total brut:</strong> {deduplicationInfo.total_raw_results}</div>
                                        <div><strong>Après fusion:</strong> {deduplicationInfo.total_after_dedup}</div>
                                        <div><strong>Taux déduplication:</strong> {deduplicationInfo.deduplication_rate?.toFixed(1)}%</div>
                                        <div><strong>Temps traitement:</strong> {deduplicationInfo.processing_time_ms}ms</div>
                                    </div>
                                </div>
                            )}
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
                        <span className="text-sm font-medium">
                            {typeof prospect.relevance_score === 'object' 
                                ? (prospect.relevance_score?.total || prospect.relevance_score?.score || 0)
                                : (prospect.relevance_score || 0)
                            }
                        </span>
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
                <div className="space-y-2">
                    {/* Contact traditionnel via contact_info */}
                    {prospect.contact_info && (
                        <>
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
                        </>
                    )}
                    
                    {/* Contact direct (nouveau format agrégé) */}
                    {prospect.phone && (
                        <div className="flex items-center gap-2">
                            <Phone className="h-4 w-4 text-gray-500" />
                            <span className="text-sm">{prospect.phone}</span>
                        </div>
                    )}
                    
                    {prospect.email && (
                        <div className="flex items-center gap-2">
                            <Mail className="h-4 w-4 text-gray-500" />
                            <span className="text-sm">{prospect.email}</span>
                        </div>
                    )}
                    
                    {prospect.website && (
                        <div className="flex items-center gap-2">
                            <Globe className="h-4 w-4 text-gray-500" />
                            <a
                                href={prospect.website}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="text-sm text-blue-600 hover:underline truncate"
                            >
                                {prospect.website}
                            </a>
                        </div>
                    )}
                </div>

                {/* Description */}
                {prospect.description && (
                    <p className="text-sm text-gray-600 line-clamp-3">
                        {prospect.description}
                    </p>
                )}


                {/* Sources fusionnées */}
                {prospect.merged_from && prospect.merged_from.length > 0 && (
                    <div className="p-2 bg-green-50 rounded-lg border-l-2 border-green-200">
                        <div className="text-xs font-medium text-green-800 mb-1">
                            Prospect fusionné ({prospect.merged_from.length} sources)
                        </div>
                        <div className="flex flex-wrap gap-1">
                            {prospect.merged_from.map((source, idx) => (
                                <Badge key={idx} variant="outline" className="text-xs px-1 py-0">
                                    {source}
                                </Badge>
                            ))}
                        </div>
                        {prospect.data_quality && (
                            <div className="mt-1 text-xs text-green-700">
                                Qualité: {((prospect.data_quality.completeness || 0) * 100).toFixed(0)}%
                                {prospect.data_quality.verified && ' ✓ Vérifié'}
                            </div>
                        )}
                    </div>
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
                        Source: {typeof prospect.source === 'object' ? (prospect.source?.name || 'Inconnu') : (prospect.source || 'Inconnu')}
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