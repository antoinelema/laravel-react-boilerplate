import { useState, useEffect, useCallback } from 'react';
import axios from 'axios';

/**
 * Hook pour gérer l'éligibilité d'enrichissement d'un prospect
 */
export const useEnrichmentEligibility = (prospectId) => {
    const [eligibility, setEligibility] = useState({
        is_eligible: false,
        reason: null,
        next_eligible_at: null,
        completeness_score: 0,
        priority: 'low',
        loading: true,
        error: null
    });

    const [refreshTrigger, setRefreshTrigger] = useState(0);

    const checkEligibility = useCallback(async () => {
        if (!prospectId) return;
        
        try {
            setEligibility(prev => ({ ...prev, loading: true, error: null }));
            
            const response = await axios.get(`/api/v1/prospects/${prospectId}/enrichment-eligibility`);
            
            if (response.data.success) {
                setEligibility(prev => ({
                    ...prev,
                    ...response.data.data,
                    loading: false,
                    error: null
                }));
            } else {
                throw new Error(response.data.message || 'Erreur de vérification d\'éligibilité');
            }
        } catch (error) {
            console.error('Error checking enrichment eligibility:', error);
            setEligibility(prev => ({
                ...prev,
                loading: false,
                error: error.response?.data?.message || error.message || 'Erreur inconnue'
            }));
        }
    }, [prospectId]);

    // Vérifier l'éligibilité au montage et lors des changements
    useEffect(() => {
        checkEligibility();
    }, [checkEligibility, refreshTrigger]);

    // Méthode pour forcer une nouvelle vérification
    const refresh = useCallback(() => {
        setRefreshTrigger(prev => prev + 1);
    }, []);

    // Helpers pour l'UI
    const getEligibilityMessage = useCallback(() => {
        if (eligibility.loading) return 'Vérification...';
        if (eligibility.error) return eligibility.error;
        
        switch (eligibility.reason) {
            case 'never_enriched':
                return 'Jamais enrichi - éligible';
            case 'previous_failure':
                return 'Échec précédent - retry possible';
            case 'outdated_enrichment':
                return 'Données anciennes - éligible';
            case 'incomplete_data':
                return 'Données incomplètes - éligible';
            case 'recently_enriched':
                const nextDate = eligibility.next_eligible_at ? 
                    new Date(eligibility.next_eligible_at).toLocaleDateString() : 'bientôt';
                return `Enrichi récemment - prochaine fois: ${nextDate}`;
            case 'complete_data':
                return `Données complètes (${eligibility.completeness_score}%)`;
            case 'blacklisted':
                return 'Enrichissement désactivé';
            case 'disabled':
                return 'Auto-enrichissement désactivé';
            case 'in_progress':
                return 'Enrichissement en cours...';
            case 'max_attempts_reached':
                return 'Nombre max de tentatives atteint';
            default:
                return eligibility.is_eligible ? 'Éligible' : 'Non éligible';
        }
    }, [eligibility]);

    const getEligibilityColor = useCallback(() => {
        if (eligibility.loading) return 'text-gray-500';
        if (eligibility.error) return 'text-red-500';
        
        if (eligibility.is_eligible) {
            switch (eligibility.priority) {
                case 'high': return 'text-green-600';
                case 'medium': return 'text-yellow-600';
                case 'low': return 'text-blue-600';
                default: return 'text-green-600';
            }
        }
        
        switch (eligibility.reason) {
            case 'recently_enriched':
            case 'complete_data':
                return 'text-gray-500';
            case 'blacklisted':
            case 'disabled':
            case 'max_attempts_reached':
                return 'text-red-500';
            case 'in_progress':
                return 'text-blue-500';
            default:
                return 'text-gray-600';
        }
    }, [eligibility]);

    const getEligibilityIcon = useCallback(() => {
        if (eligibility.loading) return '⏳';
        if (eligibility.error) return '❌';
        
        if (eligibility.is_eligible) {
            switch (eligibility.priority) {
                case 'high': return '🔥';
                case 'medium': return '⚡';
                case 'low': return '✨';
                default: return '✅';
            }
        }
        
        switch (eligibility.reason) {
            case 'recently_enriched': return '⏰';
            case 'complete_data': return '✅';
            case 'blacklisted': return '🚫';
            case 'disabled': return '⏸️';
            case 'in_progress': return '🔄';
            case 'max_attempts_reached': return '🛑';
            default: return '❓';
        }
    }, [eligibility]);

    // Helper pour déterminer si on peut forcer l'enrichissement
    const canForceEnrich = useCallback(() => {
        return !eligibility.loading && 
               !eligibility.error &&
               ['recently_enriched', 'complete_data'].includes(eligibility.reason);
    }, [eligibility]);

    return {
        eligibility,
        refresh,
        checkEligibility,
        getEligibilityMessage,
        getEligibilityColor,
        getEligibilityIcon,
        canForceEnrich,
        isLoading: eligibility.loading,
        hasError: !!eligibility.error,
        isEligible: eligibility.is_eligible,
        completenessScore: eligibility.completeness_score,
        priority: eligibility.priority
    };
};

/**
 * Hook pour gérer l'enrichissement d'un prospect
 */
export const useProspectEnrichment = (prospectId) => {
    const [enrichmentState, setEnrichmentState] = useState({
        isEnriching: false,
        lastEnrichment: null,
        results: null,
        error: null
    });

    const eligibility = useEnrichmentEligibility(prospectId);

    const enrichProspect = useCallback(async (options = {}) => {
        if (!prospectId) {
            throw new Error('ID du prospect requis');
        }

        try {
            setEnrichmentState(prev => ({
                ...prev,
                isEnriching: true,
                error: null
            }));

            const payload = {
                force: options.force || false,
                max_contacts: options.maxContacts || 10,
                custom_urls: options.customUrls || []
            };

            const response = await axios.post(`/api/v1/prospects/${prospectId}/enrich`, payload);

            if (response.data.success) {
                const results = response.data.data;
                
                setEnrichmentState(prev => ({
                    ...prev,
                    isEnriching: false,
                    lastEnrichment: new Date().toISOString(),
                    results: results,
                    error: null
                }));

                // Rafraîchir l'éligibilité après enrichissement
                eligibility.refresh();

                return {
                    success: true,
                    contacts: results.contacts,
                    metadata: results.metadata,
                    updated_prospect: results.updated_prospect
                };

            } else {
                throw new Error(response.data.message || 'Enrichissement échoué');
            }

        } catch (error) {
            const errorMessage = error.response?.data?.message || error.message;
            const errorReason = error.response?.data?.reason;
            
            setEnrichmentState(prev => ({
                ...prev,
                isEnriching: false,
                error: errorMessage
            }));

            // Si c'est une erreur d'éligibilité, rafraîchir les données d'éligibilité
            if (errorReason === 'not_eligible') {
                eligibility.refresh();
            }

            throw error;
        }
    }, [prospectId, eligibility]);

    const clearError = useCallback(() => {
        setEnrichmentState(prev => ({
            ...prev,
            error: null
        }));
    }, []);

    const getTotalContactsFound = useCallback(() => {
        if (!enrichmentState.results?.contacts) return 0;
        
        const contacts = enrichmentState.results.contacts;
        return (contacts.emails?.length || 0) + 
               (contacts.phones?.length || 0) + 
               (contacts.websites?.length || 0) + 
               (contacts.social_media?.length || 0);
    }, [enrichmentState.results]);

    return {
        ...enrichmentState,
        eligibility: eligibility.eligibility,
        enrichProspect,
        clearError,
        getTotalContactsFound,
        canEnrich: eligibility.isEligible || eligibility.canForceEnrich(),
        isEligible: eligibility.isEligible,
        canForceEnrich: eligibility.canForceEnrich(),
        eligibilityMessage: eligibility.getEligibilityMessage(),
        eligibilityColor: eligibility.getEligibilityColor(),
        eligibilityIcon: eligibility.getEligibilityIcon(),
        refreshEligibility: eligibility.refresh
    };
};

/**
 * Hook pour gérer les statistiques d'enrichissement
 */
export const useEnrichmentStats = () => {
    const [stats, setStats] = useState({
        total_prospects: 0,
        eligible_for_enrichment: 0,
        complete_data: 0,
        recently_enriched: 0,
        never_enriched: 0,
        enrichment_pending: 0,
        enrichment_failed: 0,
        blacklisted: 0,
        completion_rate: 0,
        enrichment_coverage: 0,
        loading: true,
        error: null
    });

    const [refreshTrigger, setRefreshTrigger] = useState(0);

    const fetchStats = useCallback(async () => {
        try {
            setStats(prev => ({ ...prev, loading: true, error: null }));
            
            const response = await axios.get('/api/v1/prospects/enrichment-stats');
            
            if (response.data.success) {
                setStats(prev => ({
                    ...prev,
                    ...response.data.data,
                    loading: false,
                    error: null
                }));
            } else {
                throw new Error(response.data.message || 'Erreur de récupération des statistiques');
            }
        } catch (error) {
            console.error('Error fetching enrichment stats:', error);
            setStats(prev => ({
                ...prev,
                loading: false,
                error: error.response?.data?.message || error.message
            }));
        }
    }, []);

    useEffect(() => {
        fetchStats();
    }, [fetchStats, refreshTrigger]);

    const refresh = useCallback(() => {
        setRefreshTrigger(prev => prev + 1);
    }, []);

    return {
        stats,
        refresh,
        isLoading: stats.loading,
        hasError: !!stats.error
    };
};