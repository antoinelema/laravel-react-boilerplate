import React, { useState } from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog'
import { Textarea } from '@/components/ui/textarea'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { 
    Loader2, Star, MapPin, Phone, Mail, Globe, 
    MessageSquare, Edit, Trash2, Tag, Plus
} from 'lucide-react'
import { toast } from 'sonner'
import { secureApiClient } from '@/lib/secureApi'
import EnrichmentButton, { EnrichmentStatusBadge } from './EnrichmentButton'

export function ProspectCard({ 
    prospect, 
    onDelete, 
    onProspectUpdate, 
    categories = [], 
    isSelected = false, 
    onSelectionChange, 
    showEnrichment = false 
}) {
    const [isDeleting, setIsDeleting] = useState(false)
    const [notes, setNotes] = useState([])
    const [isLoadingNotes, setIsLoadingNotes] = useState(false)
    const [newNote, setNewNote] = useState('')
    const [isAddingNote, setIsAddingNote] = useState(false)
    const [isNotesDialogOpen, setIsNotesDialogOpen] = useState(false)
    const [showCategoryDropdown, setShowCategoryDropdown] = useState(false)
    const [isUpdatingCategories, setIsUpdatingCategories] = useState(false)

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

    const handleUpdateCategories = async (selectedCategoryIds) => {
        setIsUpdatingCategories(true)
        try {
            await secureApiClient.post(`/api/v1/prospects/${prospect.id}/categories`, {
                category_ids: selectedCategoryIds
            })
            
            const updatedCategories = categories.filter(cat => selectedCategoryIds.includes(cat.id))
            if (onProspectUpdate) {
                onProspectUpdate(prospect.id, {
                    categories: updatedCategories
                })
            }
            
            toast.success('Catégories mises à jour avec succès')
            setShowCategoryDropdown(false)
        } catch (error) {
            toast.error(error.message || 'Erreur lors de la mise à jour')
        } finally {
            setIsUpdatingCategories(false)
        }
    }

    return (
        <Card className="h-full flex flex-col">
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between">
                    <div className="flex-1 min-w-0">
                        <CardTitle className="text-lg mb-1 break-words">{prospect.name}</CardTitle>
                        {prospect.company && (
                            <CardDescription className="break-words">{prospect.company}</CardDescription>
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
                
                {prospect.categories && prospect.categories.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-2">
                        {prospect.categories.map((category) => (
                            <Badge
                                key={category.id}
                                variant="outline"
                                className="text-xs"
                                style={{
                                    borderColor: category.color,
                                    color: category.color
                                }}
                            >
                                <div className="flex items-center gap-1">
                                    <div 
                                        className="w-2 h-2 rounded-full" 
                                        style={{ backgroundColor: category.color }}
                                    />
                                    {category.name}
                                </div>
                            </Badge>
                        ))}
                    </div>
                )}
            </CardHeader>
            
            <CardContent className="space-y-3 flex-1 flex flex-col">
                <div className="flex-1 space-y-3">
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
                            <div className="flex items-center gap-2 min-w-0">
                                <Mail className="h-4 w-4 text-gray-500 flex-shrink-0" />
                                <a
                                    href={`mailto:${prospect.contact_info.email}`}
                                    className="text-sm text-blue-600 hover:underline break-all"
                                >
                                    {prospect.contact_info.email}
                                </a>
                            </div>
                        )}
                        
                        {prospect.contact_info.website && (
                            <div className="flex items-center gap-2 min-w-0">
                                <Globe className="h-4 w-4 text-gray-500 flex-shrink-0" />
                                <a
                                    href={prospect.contact_info.website}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-sm text-blue-600 hover:underline break-all"
                                >
                                    {prospect.contact_info.website}
                                </a>
                            </div>
                        )}
                    </div>
                )}

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
                </div>

                {/* Section source */}
                {prospect.source && (
                    <div className="pt-2 border-t">
                        <div className="text-xs text-gray-500">
                            <span>Source: {prospect.source}</span>
                        </div>
                    </div>
                )}
                    
                {/* Section enrichissement et sélection */}
                {(showEnrichment || onSelectionChange) && (
                    <div className="pt-2 border-t space-y-2">
                        {/* Ligne de sélection et badge */}
                        <div className="flex items-center gap-2">
                            {/* Checkbox de sélection */}
                            {onSelectionChange && (
                                <input
                                    type="checkbox"
                                    checked={isSelected}
                                    onChange={(e) => onSelectionChange(e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-2 focus:ring-primary focus:ring-offset-2"
                                />
                            )}

                            {/* Badge d'état d'enrichissement */}
                            {showEnrichment && (
                                <EnrichmentStatusBadge prospect={prospect} />
                            )}
                        </div>

                        {/* Ligne bouton d'enrichissement */}
                        {showEnrichment && (
                            <div className="flex justify-center">
                                <EnrichmentButton 
                                    prospect={prospect}
                                    onEnrichmentComplete={(prospect, result) => {
                                        if (onProspectUpdate && result.updated_prospect) {
                                            // Mettre à jour avec les nouvelles données du prospect
                                            onProspectUpdate(prospect.id, result.updated_prospect);
                                        }
                                    }}
                                    size="sm"
                                />
                            </div>
                        )}
                    </div>
                )}

                <div className="flex gap-2">
                        <Dialog open={isNotesDialogOpen} onOpenChange={setIsNotesDialogOpen}>
                            <DialogTrigger asChild>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline" size="sm" onClick={handleNotesDialog} className="relative">
                                            <MessageSquare className="h-3 w-3" />
                                            {prospect.notes_count > 0 && (
                                                <span className="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                                    {prospect.notes_count > 9 ? '9+' : prospect.notes_count}
                                                </span>
                                            )}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Gérer les notes ({prospect.notes_count || 0})</p>
                                    </TooltipContent>
                                </Tooltip>
                            </DialogTrigger>
                            <DialogContent className="max-w-md">
                                <DialogHeader>
                                    <DialogTitle>Notes pour {prospect.name}</DialogTitle>
                                    <DialogDescription>
                                        Gérez les notes concernant ce prospect
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div className="space-y-4">
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

                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <Edit className="h-3 w-3" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Modifier le prospect</p>
                            </TooltipContent>
                        </Tooltip>
                        
                        <Dialog open={showCategoryDropdown} onOpenChange={setShowCategoryDropdown}>
                            <DialogTrigger asChild>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button variant="outline" size="sm" className="relative">
                                            <Tag className="h-3 w-3" />
                                            {prospect.categories && prospect.categories.length > 0 && (
                                                <span className="absolute -top-1 -right-1 bg-blue-600 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center">
                                                    {prospect.categories.length}
                                                </span>
                                            )}
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Gérer les catégories ({prospect.categories ? prospect.categories.length : 0})</p>
                                    </TooltipContent>
                                </Tooltip>
                            </DialogTrigger>
                            <DialogContent className="max-w-sm">
                                <DialogHeader>
                                    <DialogTitle>Catégories pour {prospect.name}</DialogTitle>
                                    <DialogDescription>
                                        Choisissez les catégories pour ce prospect
                                    </DialogDescription>
                                </DialogHeader>
                                
                                <div className="space-y-2">
                                    {categories.length > 0 ? (
                                        <div className="space-y-2 max-h-48 overflow-y-auto">
                                            {categories.map((category) => {
                                                const isSelected = prospect.categories && prospect.categories.some(c => c.id === category.id)
                                                return (
                                                    <label
                                                        key={category.id}
                                                        className="flex items-center gap-2 p-2 rounded border hover:bg-gray-50 cursor-pointer"
                                                    >
                                                        <input
                                                            type="checkbox"
                                                            checked={isSelected}
                                                            onChange={(e) => {
                                                                const currentCategories = prospect.categories || []
                                                                let newCategoryIds
                                                                
                                                                if (e.target.checked) {
                                                                    newCategoryIds = [...currentCategories.map(c => c.id), category.id]
                                                                } else {
                                                                    newCategoryIds = currentCategories.map(c => c.id).filter(id => id !== category.id)
                                                                }
                                                                
                                                                handleUpdateCategories(newCategoryIds)
                                                            }}
                                                            disabled={isUpdatingCategories}
                                                        />
                                                        <div 
                                                            className="w-3 h-3 rounded-full" 
                                                            style={{ backgroundColor: category.color }}
                                                        />
                                                        <span className="flex-1">{category.name}</span>
                                                        <Badge variant="outline">{category.prospects_count}</Badge>
                                                    </label>
                                                )
                                            })}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-gray-500 py-4 text-center">
                                            Aucune catégorie disponible.<br />
                                            Créez des catégories via le bouton "Gérer les catégories".
                                        </p>
                                    )}
                                    
                                    {isUpdatingCategories && (
                                        <div className="flex items-center justify-center py-2">
                                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                            <span className="text-sm">Mise à jour en cours...</span>
                                        </div>
                                    )}
                                </div>
                            </DialogContent>
                        </Dialog>
                        
                        <Tooltip>
                            <TooltipTrigger asChild>
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
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Supprimer le prospect</p>
                            </TooltipContent>
                        </Tooltip>
                    </div>
            </CardContent>
        </Card>
    )
}