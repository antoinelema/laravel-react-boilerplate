import React, { useState, useEffect } from 'react'
import { Head } from '@inertiajs/react'
import Layout from './layout'
import { Button } from '@/components/ui/button'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { secureApiClient } from '@/lib/secureApi'
import { ProspectStats } from './components/ProspectStats'
import { ProspectFilters } from './components/ProspectFilters'
import { CategoryTabs } from './components/CategoryTabs'
import { ProspectGrid } from './components/ProspectGrid'
import { EmptyState } from './components/EmptyState'
import { 
    
 } from './components/EnrichmentButton'
import { useEnrichmentStats } from '../hooks/useEnrichmentEligibility'

export default function ProspectDashboard() {
    const [prospects, setProspects] = useState([])
    const [filteredProspects, setFilteredProspects] = useState([])
    const [isLoading, setIsLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')
    const [filters, setFilters] = useState({
        status: '',
        sector: '',
        city: '',
        min_score: '',
        enrichment_status: '',
        enrichment_eligibility: ''
    })
    const [stats, setStats] = useState({
        total: 0,
        byStatus: {},
        bySector: {},
        averageScore: 0
    })
    const [categories, setCategories] = useState([])
    const [activeCategoryId, setActiveCategoryId] = useState(null)
    const [selectedProspects, setSelectedProspects] = useState([])
    
    // Hook d'enrichissement pour les statistiques
    const enrichmentStats = useEnrichmentStats()

    useEffect(() => {
        fetchCategories()
        fetchProspects()
    }, [])

    useEffect(() => {
        applyFilters()
    }, [searchTerm, filters, prospects, activeCategoryId])

    useEffect(() => {
        if (prospects.length > 0) {
            calculateStats(prospects)
        }
    }, [activeCategoryId, prospects])

    const fetchProspects = async () => {
        setIsLoading(true)
        try {
            const data = await secureApiClient.get('/api/v1/prospects')
            setProspects(data.data.prospects)
            calculateStats(data.data.prospects)
        } catch (error) {
            toast.error(error.message || 'Erreur lors du chargement')
        } finally {
            setIsLoading(false)
        }
    }

    const fetchCategories = async () => {
        try {
            const data = await secureApiClient.get('/api/v1/prospect-categories')
            setCategories(data.data.categories)
        } catch (error) {
            toast.error('Erreur lors du chargement des catégories')
        }
    }

    const getCategoriesWithCounts = () => {
        return categories.map(category => ({
            ...category,
            prospects_count: prospects.filter(prospect => 
                prospect.categories && 
                prospect.categories.some(cat => cat.id === category.id)
            ).length
        }))
    }

    const getProspectsByCategory = (prospectsData) => {
        if (activeCategoryId === null) {
            return prospectsData
        } else if (activeCategoryId === 0) {
            return prospectsData.filter(prospect => 
                !prospect.categories || prospect.categories.length === 0
            )
        } else {
            return prospectsData.filter(prospect => 
                prospect.categories && 
                prospect.categories.some(cat => cat.id === activeCategoryId)
            )
        }
    }

    const calculateStats = (prospectsData) => {
        const categoryFiltered = getProspectsByCategory(prospectsData)
        const total = categoryFiltered.length
        const byStatus = {}
        const bySector = {}
        let totalScore = 0

        categoryFiltered.forEach(prospect => {
            byStatus[prospect.status] = (byStatus[prospect.status] || 0) + 1
            if (prospect.sector) {
                bySector[prospect.sector] = (bySector[prospect.sector] || 0) + 1
            }
            totalScore += prospect.relevance_score
        })

        setStats({
            total,
            byStatus,
            bySector,
            averageScore: total > 0 ? Math.round(totalScore / total) : 0
        })
    }

    const applyFilters = () => {
        let filtered = [...prospects]

        // Filtre par catégorie
        if (activeCategoryId !== null) {
            if (activeCategoryId === 0) {
                // "Sans catégorie" - prospects sans catégories
                filtered = filtered.filter(prospect => 
                    !prospect.categories || prospect.categories.length === 0
                )
            } else {
                // Catégorie spécifique
                filtered = filtered.filter(prospect => 
                    prospect.categories && 
                    prospect.categories.some(cat => cat.id === activeCategoryId)
                )
            }
        }

        // Filtre par terme de recherche
        if (searchTerm) {
            const term = searchTerm.toLowerCase()
            filtered = filtered.filter(prospect =>
                prospect.name.toLowerCase().includes(term) ||
                (prospect.company && prospect.company.toLowerCase().includes(term)) ||
                (prospect.sector && prospect.sector.toLowerCase().includes(term)) ||
                (prospect.city && prospect.city.toLowerCase().includes(term))
            )
        }

        // Autres filtres
        if (filters.status) {
            filtered = filtered.filter(prospect => prospect.status === filters.status)
        }

        if (filters.sector) {
            filtered = filtered.filter(prospect => prospect.sector === filters.sector)
        }

        if (filters.city) {
            filtered = filtered.filter(prospect => prospect.city === filters.city)
        }

        if (filters.min_score) {
            filtered = filtered.filter(prospect => prospect.relevance_score >= parseInt(filters.min_score))
        }

        // Filtres d'enrichissement
        if (filters.enrichment_status) {
            filtered = filtered.filter(prospect => {
                switch (filters.enrichment_status) {
                    case 'never_enriched':
                        return !prospect.last_enrichment_at
                    case 'recently_enriched':
                        if (!prospect.last_enrichment_at) return false
                        const enrichedAt = new Date(prospect.last_enrichment_at)
                        const thirtyDaysAgo = new Date()
                        thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30)
                        return enrichedAt > thirtyDaysAgo
                    case 'needs_refresh':
                        if (!prospect.last_enrichment_at) return true
                        const lastEnriched = new Date(prospect.last_enrichment_at)
                        const ninetyDaysAgo = new Date()
                        ninetyDaysAgo.setDate(ninetyDaysAgo.getDate() - 90)
                        return lastEnriched < ninetyDaysAgo
                    case 'complete_data':
                        return prospect.data_completeness_score >= 80
                    case 'incomplete_data':
                        return prospect.data_completeness_score < 80
                    case 'has_contacts':
                        return prospect.email && prospect.telephone
                    case 'missing_contacts':
                        return !prospect.email || !prospect.telephone
                    default:
                        return true
                }
            })
        }

        if (filters.enrichment_eligibility) {
            // Ce filtre nécessiterait d'appeler l'API d'éligibilité pour chaque prospect
            // Pour l'instant, on fait un filtrage basique
            filtered = filtered.filter(prospect => {
                switch (filters.enrichment_eligibility) {
                    case 'eligible':
                        return !prospect.enrichment_blacklisted_at && 
                               prospect.auto_enrich_enabled !== false &&
                               (!prospect.last_enrichment_at || 
                                new Date(prospect.last_enrichment_at) < new Date(Date.now() - 30*24*60*60*1000))
                    case 'not_eligible':
                        return prospect.enrichment_blacklisted_at || 
                               prospect.auto_enrich_enabled === false ||
                               (prospect.last_enrichment_at && 
                                new Date(prospect.last_enrichment_at) >= new Date(Date.now() - 30*24*60*60*1000))
                    case 'blacklisted':
                        return prospect.enrichment_blacklisted_at
                    default:
                        return true
                }
            })
        }

        setFilteredProspects(filtered)
    }

    const handleDeleteProspect = async (prospectId) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce prospect ?')) {
            return
        }

        try {
            await secureApiClient.delete(`/api/v1/prospects/${prospectId}`)
            toast.success('Prospect supprimé avec succès')
            setProspects(prev => prev.filter(p => p.id !== prospectId))
        } catch (error) {
            toast.error(error.message || 'Erreur lors de la suppression')
        }
    }

    const handleProspectUpdate = (prospectId, updates) => {
        setProspects(prev => prev.map(p => 
            p.id === prospectId ? { ...p, ...updates } : p
        ))
    }

    const updateFilter = (key, value) => {
        setFilters(prev => ({
            ...prev,
            [key]: value
        }))
    }

    const clearFilters = () => {
        setSearchTerm('')
        setFilters({
            status: '',
            sector: '',
            city: '',
            min_score: '',
            enrichment_status: '',
            enrichment_eligibility: ''
        })
    }

    const getUniqueValues = (field) => {
        return prospects
            .map(p => p[field])
            .filter(value => value && value.trim())
            .filter((value, index, self) => self.indexOf(value) === index)
            .sort()
    }

    const handleBulkEnrichmentComplete = (prospects, results) => {
        toast.success(`Enrichissement terminé : ${results.processed?.length || 0} prospects traités`)
        // Rafraîchir la liste des prospects
        fetchProspects()
        // Désélectionner les prospects
        setSelectedProspects([])
        // Rafraîchir les stats d'enrichissement
        enrichmentStats.refresh()
    }

    const handleBulkEnrichmentStart = (prospects) => {
        toast.info(`Démarrage de l'enrichissement pour ${prospects.length} prospects...`)
    }

    const handleProspectSelectionChange = (selectedIds) => {
        setSelectedProspects(
            prospects.filter(prospect => selectedIds.includes(prospect.id))
        )
    }

    if (isLoading) {
        return (
            <Layout>
                <Head title="Mes Prospects" />
                <div className="w-full px-6 py-6">
                    <div className="flex items-center justify-center h-64">
                        <div className="text-center">
                            <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto mb-4"></div>
                            <span>Chargement des prospects...</span>
                        </div>
                    </div>
                </div>
            </Layout>
        )
    }

    return (
        <Layout>
            <Head title="Mes Prospects" />
            
            <div className="w-full px-6 py-6">
                <div className="flex items-center justify-between mb-6">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">Mes Prospects</h1>
                        <p className="text-gray-600">Gérez et suivez vos prospects</p>
                    </div>
                    
                    <Button asChild>
                        <a href="/prospects/search">
                            <Plus className="mr-2 h-4 w-4" />
                            Rechercher des prospects
                        </a>
                    </Button>
                </div>

                <CategoryTabs 
                    categories={getCategoriesWithCounts()}
                    activeCategoryId={activeCategoryId}
                    onCategoryChange={setActiveCategoryId}
                    onCategoriesUpdate={setCategories}
                    stats={stats}
                />

                <ProspectStats stats={stats} />

                <ProspectFilters 
                    searchTerm={searchTerm}
                    onSearchChange={setSearchTerm}
                    filters={filters}
                    onFilterChange={updateFilter}
                    onClearFilters={clearFilters}
                    prospects={prospects}
                    filteredCount={filteredProspects.length}
                    getUniqueValues={getUniqueValues}
                    enrichmentStats={enrichmentStats}
                />

                {selectedProspects.length > 0 && (
                    <div className="mb-6 p-4 bg-blue-50 rounded-lg border border-blue-200">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center space-x-4">
                                <span className="text-sm font-medium text-blue-900">
                                    {selectedProspects.length} prospect{selectedProspects.length > 1 ? 's' : ''} sélectionné{selectedProspects.length > 1 ? 's' : ''}
                                </span>
                                <button
                                    onClick={() => setSelectedProspects([])}
                                    className="text-sm text-blue-600 hover:text-blue-800"
                                >
                                    Désélectionner tout
                                </button>
                            </div>
                            <BulkEnrichmentButton
                                selectedProspects={selectedProspects}
                                onBulkEnrichmentComplete={handleBulkEnrichmentComplete}
                                onBulkEnrichmentStart={handleBulkEnrichmentStart}
                            />
                        </div>
                    </div>
                )}

                {filteredProspects.length > 0 ? (
                    <ProspectGrid 
                        prospects={filteredProspects}
                        categories={getCategoriesWithCounts()}
                        onDeleteProspect={handleDeleteProspect}
                        onProspectUpdate={handleProspectUpdate}
                        selectedProspects={selectedProspects}
                        onSelectionChange={handleProspectSelectionChange}
                        showEnrichment={true}
                    />
                ) : (
                    <EmptyState 
                        hasProspects={prospects.length > 0}
                    />
                )}
            </div>
        </Layout>
    )
}