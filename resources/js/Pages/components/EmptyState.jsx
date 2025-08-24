import React from 'react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Users, Search } from 'lucide-react'

export function EmptyState({ hasProspects }) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center justify-center py-12">
                <Users className="h-12 w-12 text-gray-400 mb-4" />
                <h3 className="text-lg font-medium text-gray-900 mb-2">
                    Aucun prospect trouvé
                </h3>
                <p className="text-gray-500 text-center max-w-sm mb-4">
                    {!hasProspects
                        ? "Vous n'avez pas encore de prospects. Commencez par en rechercher."
                        : "Aucun prospect ne correspond à vos critères de recherche."
                    }
                </p>
                {!hasProspects && (
                    <Button asChild>
                        <a href="/prospects/search">
                            <Search className="mr-2 h-4 w-4" />
                            Rechercher des prospects
                        </a>
                    </Button>
                )}
            </CardContent>
        </Card>
    )
}