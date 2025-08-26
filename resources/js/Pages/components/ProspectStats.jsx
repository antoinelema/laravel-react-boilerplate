import React from 'react'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Users, TrendingUp, Star } from 'lucide-react'

export function ProspectStats({ stats }) {
    return (
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
    )
}