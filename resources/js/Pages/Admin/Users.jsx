import React, { useState } from 'react';
import { Head, usePage, router, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';

export default function AdminUsers() {
    const { props } = usePage();
    const { auth, users, filters, stats } = props;
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedRole, setSelectedRole] = useState(filters.role ? filters.role : 'all');
    const [selectedSubscription, setSelectedSubscription] = useState(filters.subscription_type ? filters.subscription_type : 'all');

    const handleFilter = () => {
        router.get('/admin/users', {
            search: searchTerm,
            role: selectedRole === 'all' ? '' : selectedRole,
            subscription_type: selectedSubscription === 'all' ? '' : selectedSubscription,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedRole('all');
        setSelectedSubscription('all');
        router.get('/admin/users');
    };

    const upgradeUser = async (userId) => {
        if (!window.confirm('Upgrader cet utilisateur vers premium pour 1 mois ?')) return;
        
        try {
            const response = await fetch(`/admin/users/${userId}/upgrade`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                credentials: 'include', // Important pour les cookies de session
                body: JSON.stringify({ duration_months: 1 })
            });
            
            if (response.ok) {
                const result = await response.json();
                alert('Utilisateur upgradé avec succès');
                router.reload({ only: ['users', 'stats'] }); // Rechargement optimisé
            } else {
                const error = await response.json().catch(() => ({ message: 'Erreur inconnue' }));
                alert(`Erreur lors de l'upgrade: ${error.message}`);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'upgrade');
        }
    };

    const downgradeUser = async (userId) => {
        if (!window.confirm('Rétrograder cet utilisateur vers gratuit ?')) return;
        
        try {
            const response = await fetch(`/admin/users/${userId}/downgrade`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                credentials: 'include' // Important pour les cookies de session
            });
            
            if (response.ok) {
                const result = await response.json();
                alert('Utilisateur rétrogradé avec succès');
                router.reload({ only: ['users', 'stats'] }); // Rechargement optimisé
            } else {
                const error = await response.json().catch(() => ({ message: 'Erreur inconnue' }));
                alert(`Erreur lors du downgrade: ${error.message}`);
            }
        } catch (error) {
            console.error('Erreur:', error);
            alert('Erreur lors du downgrade');
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Gestion des Utilisateurs</h2>}
        >
            <Head title="Admin - Utilisateurs" />

            <div className="py-6 space-y-6">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    
                    {/* Statistiques */}
                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Total</CardTitle>
                                <svg className="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.total}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Premium</CardTitle>
                                <svg className="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-emerald-600">{stats.premium}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Gratuits</CardTitle>
                                <svg className="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-slate-600">{stats.free}</div>
                            </CardContent>
                        </Card>
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium">Admin</CardTitle>
                                <svg className="h-4 w-4 text-muted-foreground" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-violet-600">{stats.admin}</div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Filtres */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Filtres</CardTitle>
                            <CardDescription>
                                Rechercher et filtrer les utilisateurs par critères
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Recherche</label>
                                    <Input
                                        type="text"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        placeholder="Nom, prénom, email..."
                                    />
                                </div>
                                
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Rôle</label>
                                    <Select value={selectedRole} onValueChange={setSelectedRole}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Tous les rôles" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les rôles</SelectItem>
                                            <SelectItem value="user">Utilisateur</SelectItem>
                                            <SelectItem value="admin">Admin</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <div className="space-y-2">
                                    <label className="text-sm font-medium">Abonnement</label>
                                    <Select value={selectedSubscription} onValueChange={setSelectedSubscription}>
                                        <SelectTrigger>
                                            <SelectValue placeholder="Tous les types" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">Tous les types</SelectItem>
                                            <SelectItem value="premium">Premium</SelectItem>
                                            <SelectItem value="free">Gratuit</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                
                                <div className="flex items-end space-x-2">
                                    <Button onClick={handleFilter} className="flex-1">
                                        <svg className="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                        Filtrer
                                    </Button>
                                    <Button onClick={clearFilters} variant="outline">
                                        <svg className="mr-2 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                        </svg>
                                        Reset
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Liste des utilisateurs */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Utilisateurs ({users.total})</CardTitle>
                            <CardDescription>
                                Liste de tous les utilisateurs avec possibilités de gestion
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {users.data.map((user) => (
                                    <div key={user.id} className="flex items-center justify-between p-4 rounded-lg border hover:bg-muted/50 transition-colors">
                                        <div className="flex items-center space-x-4">
                                            <div className="flex-shrink-0">
                                                <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                                                    <span className="text-sm font-medium text-primary">
                                                        {user.name.charAt(0)}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="space-y-1">
                                                <div className="flex items-center space-x-2">
                                                    <p className="text-sm font-medium">{user.name}</p>
                                                    <Badge variant={user.role === 'admin' ? 'default' : 'secondary'} className="text-xs">
                                                        {user.role}
                                                    </Badge>
                                                    <Badge variant={user.is_premium ? 'default' : 'outline'} className="text-xs">
                                                        {user.subscription_type}
                                                    </Badge>
                                                </div>
                                                <div className="flex items-center space-x-4 text-sm text-muted-foreground">
                                                    <span>{user.email}</span>
                                                    {user.subscription_expires_at && (
                                                        <span>Expire: {user.subscription_expires_at}</span>
                                                    )}
                                                    <span>Inscrit: {user.created_at}</span>
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {user.is_premium ? (
                                                        <span className="text-emerald-600 font-medium">Recherches illimitées</span>
                                                    ) : (
                                                        <span>
                                                            Recherches: {user.daily_searches_count}/5 
                                                            ({user.remaining_searches} restantes)
                                                        </span>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        {user.role !== 'admin' && (
                                            <div className="flex items-center space-x-2">
                                                {user.is_premium ? (
                                                    <Button
                                                        onClick={() => downgradeUser(user.id)}
                                                        variant="destructive"
                                                        size="sm"
                                                        className="text-white"
                                                    >
                                                        <svg className="mr-1 h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19 14l-7 7m0 0l-7-7m7 7V3" />
                                                        </svg>
                                                        Downgrade
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        onClick={() => upgradeUser(user.id)}
                                                        variant="default"
                                                        size="sm"
                                                    >
                                                        <svg className="mr-1 h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 10l7-7m0 0l7 7m-7-7v18" />
                                                        </svg>
                                                        Upgrade
                                                    </Button>
                                                )}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                    
                    {/* Pagination */}
                    {users.total > users.per_page && (
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm text-muted-foreground">
                                        Affichage de <span className="font-medium">{users.from}</span> à{' '}
                                        <span className="font-medium">{users.to}</span> sur{' '}
                                        <span className="font-medium">{users.total}</span> résultats
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        {users.prev_page_url && (
                                            <Link href={users.prev_page_url}>
                                                <Button variant="outline" size="sm">
                                                    <svg className="mr-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                                    </svg>
                                                    Précédent
                                                </Button>
                                            </Link>
                                        )}
                                        
                                        <div className="flex items-center space-x-1">
                                            {users.links?.filter(link => link.label !== '&laquo; Previous' && link.label !== 'Next &raquo;').map((link, index) => (
                                                <Link key={index} href={link.url || '#'}>
                                                    <Button
                                                        variant={link.active ? "default" : "outline"}
                                                        size="sm"
                                                        className="h-8 w-8 p-0"
                                                        disabled={!link.url}
                                                    >
                                                        {link.label}
                                                    </Button>
                                                </Link>
                                            ))}
                                        </div>
                                        
                                        {users.next_page_url && (
                                            <Link href={users.next_page_url}>
                                                <Button variant="outline" size="sm">
                                                    Suivant
                                                    <svg className="ml-1 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                                                    </svg>
                                                </Button>
                                            </Link>
                                        )}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}