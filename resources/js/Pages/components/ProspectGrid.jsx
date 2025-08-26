import React from 'react'
import { TooltipProvider } from '@/components/ui/tooltip'
import { ProspectCard } from './ProspectCard'

export function ProspectGrid({ 
    prospects, 
    categories, 
    onDeleteProspect, 
    onProspectUpdate, 
    selectedProspects = [], 
    onSelectionChange,
    showEnrichment = false 
}) {
    return (
        <TooltipProvider>
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                {prospects.map((prospect) => (
                    <ProspectCard
                        key={prospect.id}
                        prospect={prospect}
                        onDelete={() => onDeleteProspect(prospect.id)}
                        onProspectUpdate={onProspectUpdate}
                        categories={categories}
                        isSelected={selectedProspects.some(p => p.id === prospect.id)}
                        onSelectionChange={(selected) => {
                            if (onSelectionChange) {
                                const currentIds = selectedProspects.map(p => p.id);
                                const newIds = selected 
                                    ? [...currentIds, prospect.id]
                                    : currentIds.filter(id => id !== prospect.id);
                                onSelectionChange(newIds);
                            }
                        }}
                        showEnrichment={showEnrichment}
                    />
                ))}
            </div>
        </TooltipProvider>
    )
}