import React, { useState, useEffect } from 'react'
import { Head } from '@inertiajs/react'
import Layout from './layout'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { 
    Loader2, Search, MapPin, Phone, Mail, Globe, Star, 
    Filter, Plus, Edit, Trash2, Users, TrendingUp, MessageSquare
} from 'lucide-react'
import { toast } from 'sonner'
import { secureApiClient } from '@/lib/secureApi'

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

    useEffect(() => {
        fetchProspects()
    }, [])

    useEffect(() => {
        applyFilters()
    }, [searchTerm, filters, prospects])

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

    const calculateStats = (prospectsData) => {
        const total = prospectsData.length
        const byStatus = {}
        const bySector = {}
        let totalScore = 0

        prospectsData.forEach(prospect => {
            // Statistiques par statut
            byStatus[prospect.status] = (byStatus[prospect.status] || 0) + 1
            
            // Statistiques par secteur
            if (prospect.sector) {
                bySector[prospect.sector] = (bySector[prospect.sector] || 0) + 1
            }
            
            // Score moyen
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

        // Filtres spécifiques
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

    const handleProspectUpdate = (prospectId, updates) => {
        setProspects(prev => prev.map(p => 
            p.id === prospectId ? { ...p, ...updates } : p
        ))
    }

    const getUniqueValues = (field) => {
        const values = prospects
            .map(p => p[field])
            .filter(value => value && value.trim())
            .filter((value, index, self) => self.indexOf(value) === index)
            .sort()
        return values
    }

    const statusOptions = ['new', 'contacted', 'interested', 'qualified', 'converted', 'rejected']
    const sectorOptions = getUniqueValues('sector')
    const cityOptions = getUniqueValues('city')

    if (isLoading) {
        return (
            <Layout>
                <Head title="Mes Prospects" />
                <div className="container mx-auto px-4 py-6">
                    <div className="flex items-center justify-center h-64">
                        <Loader2 className="h-8 w-8 animate-spin" />
                        <span className="ml-2">Chargement des prospects...</span>
                    </div>
                </div>
            </Layout>
        )
    }

    return (
        <Layout>
            <Head title="Mes Prospects" />
            
            <div className="container mx-auto px-4 py-6">
                {/* En-tête */}
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

                {/* Statistiques */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-2xl font-bold">{stats.total}</CardTitle>
                            <CardDescription className="flex items-center">
                                <Users className="mr-1 h-4 w-4" />
                                Total prospects
                            </CardDescription>
                        </CardHeader>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-2xl font-bold">
                                {stats.byStatus.converted || 0}
                            </CardTitle>
                            <CardDescription className="flex items-center text-green-600">
                                <TrendingUp className="mr-1 h-4 w-4" />
                                Convertis
                            </CardDescription>
                        </CardHeader>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-2xl font-bold">
                                {stats.byStatus.qualified || 0}
                            </CardTitle>
                            <CardDescription className="flex items-center text-blue-600">
                                <Star className="mr-1 h-4 w-4" />
                                Qualifiés
                            </CardDescription>
                        </CardHeader>
                    </Card>
                    
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-2xl font-bold">{stats.averageScore}</CardTitle>
                            <CardDescription>Score moyen</CardDescription>
                        </CardHeader>
                    </Card>
                </div>

                {/* Filtres et recherche */}
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
                                    onChange={(e) => setSearchTerm(e.target.value)}
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
                                    onChange={(e) => updateFilter('status', e.target.value)}
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
                                    onChange={(e) => updateFilter('sector', e.target.value)}
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
                                    onChange={(e) => updateFilter('city', e.target.value)}
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
                                    onChange={(e) => updateFilter('min_score', e.target.value)}
                                />
                            </div>
                        </div>
                        
                        <div className="flex justify-between items-center pt-2">
                            <span className="text-sm text-gray-600">
                                {filteredProspects.length} prospect(s) trouvé(s)
                            </span>
                            <Button variant="outline" onClick={clearFilters}>
                                Effacer les filtres
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Liste des prospects */}
                {filteredProspects.length > 0 ? (
                    <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                        {filteredProspects.map((prospect) => (
                            <ProspectDashboardCard
                                key={prospect.id}
                                prospect={prospect}
                                onDelete={() => handleDeleteProspect(prospect.id)}
                                onProspectUpdate={handleProspectUpdate}
                            />
                        ))}
                    </div>
                ) : (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <Users className="h-12 w-12 text-gray-400 mb-4" />
                            <h3 className="text-lg font-medium text-gray-900 mb-2">
                                Aucun prospect trouvé
                            </h3>
                            <p className="text-gray-500 text-center max-w-sm mb-4">
                                {prospects.length === 0
                                    ? "Vous n'avez pas encore de prospects. Commencez par en rechercher."
                                    : "Aucun prospect ne correspond à vos critères de recherche."
                                }
                            </p>
                            {prospects.length === 0 && (
                                <Button asChild>
                                    <a href="/prospects/search">
                                        <Search className="mr-2 h-4 w-4" />
                                        Rechercher des prospects
                                    </a>
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </Layout>
    )
}

function ProspectDashboardCard({ prospect, onDelete, onProspectUpdate }) {
    const [isDeleting, setIsDeleting] = useState(false)
    const [notes, setNotes] = useState([])
    const [isLoadingNotes, setIsLoadingNotes] = useState(false)
    const [newNote, setNewNote] = useState('')
    const [isAddingNote, setIsAddingNote] = useState(false)
    const [isNotesDialogOpen, setIsNotesDialogOpen] = useState(false)

    const handleDelete = async () => {
        setIsDeleting(true)
        try {
            await onDelete()
        } finally {
            setIsDeleting(false)
        }
    }

    const fetchNotes = async () => {
        setIsLoadingNotes(true)
        try {
            const data = await secureApiClient.get(`/api/v1/prospects/${prospect.id}/notes`)
            setNotes(data.data.notes || [])
        } catch (error) {
            toast.error('Erreur lors du chargement des notes')
        } finally {
            setIsLoadingNotes(false)
        }
    }

    const handleAddNote = async () => {
        if (!newNote.trim()) return

        setIsAddingNote(true)
        try {
            const data = await secureApiClient.post(`/api/v1/prospects/${prospect.id}/notes`, {
                content: newNote,
                type: 'note'
            })
            const newNoteData = data.data.note
            setNotes(prev => [newNoteData, ...prev])
            setNewNote('')
            
            // Mettre à jour les informations du prospect avec la nouvelle note
            if (onProspectUpdate) {
                onProspectUpdate(prospect.id, {
                    notes_count: (prospect.notes_count || 0) + 1,
                    last_note: {
                        content: newNoteData.content,
                        created_at: newNoteData.created_at,
                        type: newNoteData.type
                    }
                })
            }
            
            toast.success('Note ajoutée avec succès')
        } catch (error) {
            toast.error('Erreur lors de l\'ajout de la note')
        } finally {
            setIsAddingNote(false)
        }
    }

    const handleNotesDialog = () => {
        setIsNotesDialogOpen(true)
        if (notes.length === 0) {
            fetchNotes()
        }
    }

    const getStatusColor = (status) => {
        const colors = {
            'new': 'bg-blue-100 text-blue-800',
            'contacted': 'bg-yellow-100 text-yellow-800',
            'interested': 'bg-purple-100 text-purple-800',
            'qualified': 'bg-green-100 text-green-800',
            'converted': 'bg-emerald-100 text-emerald-800',
            'rejected': 'bg-red-100 text-red-800'
        }
        return colors[status] || 'bg-gray-100 text-gray-800'
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
                
                <div className="flex items-center gap-2 mt-2">
                    <Badge variant="secondary" className={getStatusColor(prospect.status)}>
                        {prospect.status}
                    </Badge>
                    {prospect.sector && (
                        <Badge variant="outline">
                            {prospect.sector}
                        </Badge>
                    )}
                </div>
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
                                <a 
                                    href={`tel:${prospect.contact_info.phone}`}
                                    className="text-sm hover:underline"
                                >
                                    {prospect.contact_info.phone}
                                </a>
                            </div>
                        )}
                        
                        {prospect.contact_info.email && (
                            <div className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-gray-500" />
                                <a
                                    href={`mailto:${prospect.contact_info.email}`}
                                    className="text-sm text-blue-600 hover:underline"
                                >
                                    {prospect.contact_info.email}
                                </a>
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

                {/* Aperçu des notes */}
                {prospect.notes_count > 0 && (
                    <div className="pt-2 border-t">
                        <div className="flex items-center gap-2 mb-2">
                            <MessageSquare className="h-4 w-4 text-blue-600" />
                            <span className="text-sm font-medium text-blue-600">
                                {prospect.notes_count} note{prospect.notes_count > 1 ? 's' : ''}
                            </span>
                        </div>
                        {prospect.last_note && (
                            <div className="bg-blue-50 p-2 rounded-md">
                                <p className="text-xs text-gray-700 line-clamp-2">
                                    {prospect.last_note.content}
                                </p>
                                <p className="text-xs text-gray-500 mt-1">
                                    {new Date(prospect.last_note.created_at).toLocaleDateString('fr-FR', {
                                        day: '2-digit',
                                        month: '2-digit',
                                        year: 'numeric',
                                        hour: '2-digit',
                                        minute: '2-digit'
                                    })}
                                </p>
                            </div>
                        )}
                    </div>
                )}

                {/* Actions */}
                <div className="flex items-center justify-between pt-2 border-t">
                    <div className="text-xs text-gray-500">
                        {prospect.source && (
                            <span>Source: {prospect.source}</span>
                        )}
                    </div>
                    
                    <div className="flex gap-2">
                        <Dialog open={isNotesDialogOpen} onOpenChange={setIsNotesDialogOpen}>
                            <DialogTrigger asChild>
                                <Button variant="outline" size="sm" onClick={handleNotesDialog} className="relative">
                                    <MessageSquare className="h-3 w-3" />
                                    {prospect.notes_count > 0 && (
                                        <span className="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                            {prospect.notes_count > 9 ? '9+' : prospect.notes_count}
                                        </span>
                                    )}
                                </Button>
                            </DialogTrigger>
                            <DialogContent className="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Notes pour {prospect.name}</DialogTitle>
                                    <DialogDescription>
                                        Gérez les notes concernant ce prospect
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div className="space-y-4">
                                    {/* Formulaire d'ajout de note */}
                                    <div className="space-y-2">
                                        <Label htmlFor="new-note">Nouvelle note</Label>
                                        <Textarea
                                            id="new-note"
                                            placeholder="Saisissez votre note..."
                                            value={newNote}
                                            onChange={(e) => setNewNote(e.target.value)}
                                            rows={3}
                                        />
                                        <Button
                                            onClick={handleAddNote}
                                            disabled={isAddingNote || !newNote.trim()}
                                            size="sm"
                                            className="w-full"
                                        >
                                            {isAddingNote ? (
                                                <>
                                                    <Loader2 className="mr-2 h-3 w-3 animate-spin" />
                                                    Ajout en cours...
                                                </>
                                            ) : (
                                                <>
                                                    <Plus className="mr-2 h-3 w-3" />
                                                    Ajouter la note
                                                </>
                                            )}
                                        </Button>
                                    </div>

                                    {/* Liste des notes */}
                                    <div className="space-y-2">
                                        <Label>Notes existantes</Label>
                                        {isLoadingNotes ? (
                                            <div className="flex items-center justify-center py-4">
                                                <Loader2 className="h-4 w-4 animate-spin" />
                                                <span className="ml-2 text-sm">Chargement des notes...</span>
                                            </div>
                                        ) : notes.length > 0 ? (
                                            <div className="max-h-40 overflow-y-auto space-y-2">
                                                {notes.map((note) => (
                                                    <div
                                                        key={note.id}
                                                        className="p-2 border rounded-md bg-gray-50 text-sm"
                                                    >
                                                        <p className="text-gray-800">{note.content}</p>
                                                        <p className="text-xs text-gray-500 mt-1">
                                                            {new Date(note.created_at).toLocaleDateString('fr-FR', {
                                                                day: '2-digit',
                                                                month: '2-digit',
                                                                year: 'numeric',
                                                                hour: '2-digit',
                                                                minute: '2-digit'
                                                            })}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-gray-500 py-4 text-center">
                                                Aucune note pour ce prospect
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </DialogContent>
                        </Dialog>

                        <Button variant="outline" size="sm">
                            <Edit className="h-3 w-3" />
                        </Button>
                        
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={handleDelete}
                            disabled={isDeleting}
                            className="text-red-600 hover:text-red-700 hover:bg-red-50"
                        >
                            {isDeleting ? (
                                <Loader2 className="h-3 w-3 animate-spin" />
                            ) : (
                                <Trash2 className="h-3 w-3" />
                            )}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>
    )
}