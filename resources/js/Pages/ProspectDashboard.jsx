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

export default function ProspectDashboard() {
    const [prospects, setProspects] = useState([])
    const [filteredProspects, setFilteredProspects] = useState([])
    const [isLoading, setIsLoading] = useState(true)
    const [searchTerm, setSearchTerm] = useState('')
    const [filters, setFilters] = useState({
        status: '',
        sector: '',
        city: '',
        min_score: ''
    })
    const [stats, setStats] = useState({
        total: 0,
        byStatus: {},
        bySector: {},
        averageScore: 0
    })
    const [categories, setCategories] = useState([])
    const [activeCategoryId, setActiveCategoryId] = useState(null)

    useEffect(() => {
        fetchCategories()
        fetchProspects()
    }, [])

    useEffect(() => {
        applyFilters()
    }, [searchTerm, filters, prospects])

    useEffect(() => {
        fetchProspects()
    }, [activeCategoryId])

    const fetchProspects = async () => {
        setIsLoading(true)
        try {
            let url = '/api/v1/prospects'
            const params = new URLSearchParams()
            
            if (activeCategoryId) {
                params.append('category_id', activeCategoryId)
            } else if (activeCategoryId === 0) {
                params.append('without_category', 'true')
            }
            
            if (params.toString()) {
                url += '?' + params.toString()
            }
            
            const data = await secureApiClient.get(url)
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

    const calculateStats = (prospectsData) => {
        const total = prospectsData.length
        const byStatus = {}
        const bySector = {}
        let totalScore = 0

        prospectsData.forEach(prospect => {
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

        if (searchTerm) {
            const term = searchTerm.toLowerCase()
            filtered = filtered.filter(prospect =>
                prospect.name.toLowerCase().includes(term) ||
                (prospect.company && prospect.company.toLowerCase().includes(term)) ||
                (prospect.sector && prospect.sector.toLowerCase().includes(term)) ||
                (prospect.city && prospect.city.toLowerCase().includes(term))
            )
        }

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
            min_score: ''
        })
    }

    const getUniqueValues = (field) => {
        return prospects
            .map(p => p[field])
            .filter(value => value && value.trim())
            .filter((value, index, self) => self.indexOf(value) === index)
            .sort()
    }

    if (isLoading) {
        return (
            <Layout>
                <Head title="Mes Prospects" />
                <div className="container mx-auto px-4 py-6">
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
            
            <div className="container mx-auto px-4 py-6">
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
                    categories={categories}
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
                />

                {filteredProspects.length > 0 ? (
                    <ProspectGrid 
                        prospects={filteredProspects}
                        categories={categories}
                        onDeleteProspect={handleDeleteProspect}
                        onProspectUpdate={handleProspectUpdate}
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