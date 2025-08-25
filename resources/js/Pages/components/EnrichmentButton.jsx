import React, { useState } from 'react';
import { useProspectEnrichment } from '../../hooks/useEnrichmentEligibility';

const EnrichmentButton = ({ 
    prospect, 
    onEnrichmentComplete, 
    onEnrichmentStart,
    className = '',
    size = 'sm' 
}) => {
    const {
        isEnriching,
        enrichProspect,
        eligibility,
        isEligible,
        canForceEnrich,
        eligibilityMessage,
        eligibilityColor,
        eligibilityIcon,
        error,
        clearError,
        getTotalContactsFound,
        results
    } = useProspectEnrichment(prospect?.id);

    const [forceMode, setForceMode] = useState(false);
    const [showDetails, setShowDetails] = useState(false);

    const handleEnrich = async () => {
        if (!prospect?.id) return;
        
        try {
            clearError();
            
            if (onEnrichmentStart) {
                onEnrichmentStart(prospect);
            }

            const result = await enrichProspect({
                force: forceMode,
                maxContacts: 10
            });

            if (onEnrichmentComplete) {
                onEnrichmentComplete(prospect, result);
            }

            // Réinitialiser le mode force après succès
            if (forceMode) {
                setForceMode(false);
            }

        } catch (error) {
            console.error('Enrichment failed:', error);
            // L'erreur est déjà gérée dans le hook
        }
    };

    const handleToggleForce = () => {
        setForceMode(!forceMode);
        clearError();
    };

    // Si pas de prospect ID, ne rien afficher
    if (!prospect?.id) {
        return null;
    }

    // Bouton principal
    const renderMainButton = () => {
        // Mode enrichissement ou éligible
        if (isEnriching) {
            return (
                <button 
                    className={`btn btn-primary ${size === 'sm' ? 'btn-sm' : ''} ${className}`}
                    disabled
                >
                    <div className="flex items-center">
                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Enrichissement...
                    </div>
                </button>
            );
        }

        // Éligible ou en mode force
        if (isEligible || forceMode) {
            const buttonText = forceMode ? 'Forcer enrichissement' : 'Enrichir';
            const buttonClass = forceMode ? 'btn-warning' : 'btn-primary';
            
            return (
                <button 
                    className={`btn ${buttonClass} ${size === 'sm' ? 'btn-sm' : ''} ${className}`}
                    onClick={handleEnrich}
                    title={eligibilityMessage}
                >
                    <div className="flex items-center">
                        <span className="mr-1">{eligibilityIcon}</span>
                        {buttonText}
                    </div>
                </button>
            );
        }

        // Non éligible - bouton désactivé avec info
        return (
            <button 
                className={`btn btn-secondary ${size === 'sm' ? 'btn-sm' : ''} ${className}`}
                disabled
                title={eligibilityMessage}
                onClick={() => setShowDetails(!showDetails)}
            >
                <div className="flex items-center">
                    <span className="mr-1">{eligibilityIcon}</span>
                    {getButtonLabel()}
                </div>
            </button>
        );
    };

    const getButtonLabel = () => {
        switch (eligibility.reason) {
            case 'recently_enriched':
                return 'Enrichi récemment';
            case 'complete_data':
                return `Complet (${eligibility.completeness_score}%)`;
            case 'blacklisted':
                return 'Désactivé';
            case 'in_progress':
                return 'En cours';
            case 'max_attempts_reached':
                return 'Max tentatives';
            default:
                return 'Non éligible';
        }
    };

    // Bouton d'option "Forcer" si applicable
    const renderForceButton = () => {
        if (isEnriching || isEligible || !canForceEnrich) return null;

        return (
            <button
                className={`btn btn-link ${size === 'sm' ? 'btn-xs' : 'btn-sm'} p-1 ml-2`}
                onClick={handleToggleForce}
                title={forceMode ? 'Annuler le mode force' : 'Forcer l\'enrichissement'}
            >
                {forceMode ? '❌ Annuler' : '🔧 Forcer'}
            </button>
        );
    };

    // Affichage des résultats du dernier enrichissement
    const renderResults = () => {
        if (!results || getTotalContactsFound() === 0) return null;

        return (
            <div className="mt-2 text-xs text-green-600">
                ✅ {getTotalContactsFound()} contacts trouvés
            </div>
        );
    };

    // Affichage des erreurs
    const renderError = () => {
        if (!error) return null;

        return (
            <div className="mt-2 text-xs text-red-600">
                ❌ {error}
                <button
                    className="ml-2 text-blue-600 hover:underline"
                    onClick={clearError}
                >
                    ✖
                </button>
            </div>
        );
    };

    // Détails d'éligibilité (optionnel)
    const renderDetails = () => {
        if (!showDetails || isEligible) return null;

        return (
            <div className="mt-2 p-2 bg-gray-50 rounded text-xs">
                <div className="font-medium mb-1">Détails d'éligibilité :</div>
                <div className={eligibilityColor}>
                    {eligibilityMessage}
                </div>
                {eligibility.next_eligible_at && (
                    <div className="text-gray-600 mt-1">
                        Prochaine fois : {new Date(eligibility.next_eligible_at).toLocaleDateString()}
                    </div>
                )}
                <div className="text-gray-600 mt-1">
                    Score complétude : {eligibility.completeness_score}%
                </div>
                {eligibility.details?.previous_attempts > 0 && (
                    <div className="text-gray-600">
                        Tentatives précédentes : {eligibility.details.previous_attempts}
                    </div>
                )}
            </div>
        );
    };

    return (
        <div className="enrichment-button-wrapper">
            <div className="flex items-center">
                {renderMainButton()}
                {renderForceButton()}
            </div>
            {renderResults()}
            {renderError()}
            {renderDetails()}
        </div>
    );
};

/**
 * Composant pour l'enrichissement par lot
 */
export const BulkEnrichmentButton = ({ 
    selectedProspects = [], 
    onBulkEnrichmentComplete,
    onBulkEnrichmentStart 
}) => {
    const [isProcessing, setIsProcessing] = useState(false);
    const [results, setResults] = useState(null);
    const [error, setError] = useState(null);

    const handleBulkEnrich = async () => {
        if (selectedProspects.length === 0) return;

        try {
            setIsProcessing(true);
            setError(null);
            
            if (onBulkEnrichmentStart) {
                onBulkEnrichmentStart(selectedProspects);
            }

            const prospectIds = selectedProspects.map(p => p.id);
            
            const response = await axios.post('/api/v1/prospects/bulk-enrich', {
                prospect_ids: prospectIds,
                max_processing: Math.min(prospectIds.length, 10)
            });

            if (response.data.success) {
                setResults(response.data.data);
                
                if (onBulkEnrichmentComplete) {
                    onBulkEnrichmentComplete(selectedProspects, response.data.data);
                }
            } else {
                throw new Error(response.data.message || 'Enrichissement par lot échoué');
            }

        } catch (error) {
            console.error('Bulk enrichment failed:', error);
            setError(error.response?.data?.message || error.message);
        } finally {
            setIsProcessing(false);
        }
    };

    if (selectedProspects.length === 0) {
        return null;
    }

    return (
        <div className="bulk-enrichment-wrapper">
            <button
                className="btn btn-primary"
                onClick={handleBulkEnrich}
                disabled={isProcessing}
            >
                {isProcessing ? (
                    <div className="flex items-center">
                        <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Enrichissement en cours...
                    </div>
                ) : (
                    <>
                        🔍 Enrichir la sélection ({selectedProspects.length})
                    </>
                )}
            </button>

            {results && (
                <div className="mt-2 text-sm">
                    <div className="text-green-600">
                        ✅ {results.processed?.length || 0} enrichis
                    </div>
                    {results.skipped?.length > 0 && (
                        <div className="text-yellow-600">
                            ⏭️ {results.skipped.length} ignorés
                        </div>
                    )}
                    {results.errors?.length > 0 && (
                        <div className="text-red-600">
                            ❌ {results.errors.length} erreurs
                        </div>
                    )}
                </div>
            )}

            {error && (
                <div className="mt-2 text-sm text-red-600">
                    ❌ {error}
                </div>
            )}
        </div>
    );
};

/**
 * Badge d'état d'enrichissement
 */
export const EnrichmentStatusBadge = ({ prospect, className = '' }) => {
    const { eligibility, eligibilityIcon, eligibilityColor } = useProspectEnrichment(prospect?.id);
    
    if (!prospect?.id || !eligibility) return null;

    const getBadgeClass = () => {
        if (eligibility.is_eligible) {
            switch (eligibility.priority) {
                case 'high': return 'badge-success';
                case 'medium': return 'badge-warning';
                case 'low': return 'badge-info';
                default: return 'badge-success';
            }
        }
        
        switch (eligibility.reason) {
            case 'complete_data': return 'badge-success';
            case 'recently_enriched': return 'badge-secondary';
            case 'blacklisted': return 'badge-error';
            case 'in_progress': return 'badge-info';
            default: return 'badge-secondary';
        }
    };

    return (
        <span className={`badge ${getBadgeClass()} ${className}`} title={eligibility.reason}>
            <span className="mr-1">{eligibilityIcon}</span>
            {eligibility.completeness_score}%
        </span>
    );
};

export default EnrichmentButton;