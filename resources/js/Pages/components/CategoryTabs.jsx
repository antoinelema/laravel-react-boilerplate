import React, { useState } from 'react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Badge } from '@/components/ui/badge'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { Loader2, Plus, Users, Tag, FolderPlus, Trash2 } from 'lucide-react'
import { toast } from 'sonner'
import { secureApiClient } from '@/lib/secureApi'

export function CategoryTabs({ 
    categories, 
    activeCategoryId, 
    onCategoryChange, 
    onCategoriesUpdate, 
    stats 
}) {
    const [showCategoryManager, setShowCategoryManager] = useState(false)
    const [newCategoryName, setNewCategoryName] = useState('')
    const [newCategoryColor, setNewCategoryColor] = useState('#3b82f6')
    const [isCreatingCategory, setIsCreatingCategory] = useState(false)

    const handleCreateCategory = async () => {
        if (!newCategoryName.trim()) return

        setIsCreatingCategory(true)
        try {
            const data = await secureApiClient.post('/api/v1/prospect-categories', {
                name: newCategoryName.trim(),
                color: newCategoryColor
            })

            onCategoriesUpdate(prev => [...prev, data.data.category])
            setNewCategoryName('')
            setNewCategoryColor('#3b82f6')
            toast.success('Catégorie créée avec succès')
        } catch (error) {
            toast.error(error.message || 'Erreur lors de la création')
        } finally {
            setIsCreatingCategory(false)
        }
    }

    const handleDeleteCategory = async (categoryId) => {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette catégorie ?')) {
            return
        }

        try {
            await secureApiClient.delete(`/api/v1/prospect-categories/${categoryId}`)
            onCategoriesUpdate(prev => prev.filter(cat => cat.id !== categoryId))
            
            if (activeCategoryId === categoryId) {
                onCategoryChange(null)
            }
            
            toast.success('Catégorie supprimée avec succès')
        } catch (error) {
            toast.error(error.message || 'Erreur lors de la suppression')
        }
    }

    return (
        <TooltipProvider>
            <div className="mb-6">
                <div className="flex items-center justify-between mb-4">
                    <h2 className="text-lg font-semibold text-gray-900">Catégories</h2>
                </div>
                
                <Dialog open={showCategoryManager} onOpenChange={setShowCategoryManager}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Gérer les catégories</DialogTitle>
                            <DialogDescription>
                                Créez et organisez vos catégories de prospects
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div className="space-y-4">
                            <div>
                                <Label htmlFor="category-name">Nouvelle catégorie</Label>
                                <div className="flex gap-2 mt-1">
                                    <Input
                                        id="category-name"
                                        placeholder="Nom de la catégorie"
                                        value={newCategoryName}
                                        onChange={(e) => setNewCategoryName(e.target.value)}
                                        onKeyPress={(e) => e.key === 'Enter' && handleCreateCategory()}
                                    />
                                    <input
                                        type="color"
                                        value={newCategoryColor}
                                        onChange={(e) => setNewCategoryColor(e.target.value)}
                                        className="w-12 h-10 rounded border"
                                    />
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button 
                                                onClick={handleCreateCategory}
                                                disabled={isCreatingCategory || !newCategoryName.trim()}
                                            >
                                                {isCreatingCategory ? (
                                                    <Loader2 className="h-4 w-4 animate-spin" />
                                                ) : (
                                                    <Plus className="h-4 w-4" />
                                                )}
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>Créer la catégorie</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </div>
                            </div>
                            
                            <div>
                                <Label>Catégories existantes</Label>
                                <div className="space-y-2 mt-1 max-h-48 overflow-y-auto">
                                    {categories.map((category) => (
                                        <div key={category.id} className="flex items-center justify-between p-2 border rounded">
                                            <div className="flex items-center gap-2">
                                                <div 
                                                    className="w-4 h-4 rounded" 
                                                    style={{ backgroundColor: category.color }}
                                                />
                                                <span>{category.name}</span>
                                                <Badge variant="outline">{category.prospects_count}</Badge>
                                            </div>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handleDeleteCategory(category.id)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-3 w-3" />
                                                    </Button>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>Supprimer la catégorie "{category.name}"</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                </Dialog>
                
                <div className="flex flex-wrap gap-1 p-1 bg-gray-100 rounded-lg">
                    <button
                        onClick={() => onCategoryChange(null)}
                        className={`px-3 py-2 rounded text-sm font-medium transition-colors ${
                            activeCategoryId === null
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        <div className="flex items-center gap-2">
                            <Users className="h-4 w-4" />
                            Tous les prospects
                            <Badge variant="secondary">{stats.total}</Badge>
                        </div>
                    </button>
                    
                    <button
                        onClick={() => onCategoryChange(0)}
                        className={`px-3 py-2 rounded text-sm font-medium transition-colors ${
                            activeCategoryId === 0
                                ? 'bg-white text-gray-900 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        <div className="flex items-center gap-2">
                            <Tag className="h-4 w-4" />
                            Sans catégorie
                        </div>
                    </button>
                    
                    {categories.map((category) => (
                        <button
                            key={category.id}
                            onClick={() => onCategoryChange(category.id)}
                            className={`px-3 py-2 rounded text-sm font-medium transition-colors ${
                                activeCategoryId === category.id
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900'
                            }`}
                        >
                            <div className="flex items-center gap-2">
                                <div 
                                    className="w-3 h-3 rounded-full" 
                                    style={{ backgroundColor: category.color }}
                                />
                                {category.name}
                                <Badge variant="secondary">{category.prospects_count}</Badge>
                            </div>
                        </button>
                    ))}
                    
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <button
                                onClick={() => setShowCategoryManager(true)}
                                className="px-3 py-2 rounded text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-200 transition-colors"
                            >
                                <FolderPlus className="h-4 w-4" />
                            </button>
                        </TooltipTrigger>
                        <TooltipContent>
                            <p>Ajouter une nouvelle catégorie</p>
                        </TooltipContent>
                    </Tooltip>
                </div>
            </div>
        </TooltipProvider>
    )
}