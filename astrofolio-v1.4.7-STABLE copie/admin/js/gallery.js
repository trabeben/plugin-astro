/**
 * JavaScript pour la galerie d'astrophotographie
 * Fonctionnalités avancées de filtrage et interface utilisateur
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Configuration
    const config = {
        itemsPerPage: 20,
        animationDuration: 400,
        searchDelay: 300
    };
    
    // Variables globales
    let currentPage = 1;
    let totalItems = 0;
    let filteredItems = [];
    let searchTimeout;
    
    // Initialisation
    init();
    
    function init() {
        setupEventListeners();
        setupLazyLoading();
        initializeFilters();
        setupInfiniteScroll();
        setupKeyboardShortcuts();
        
        // Compteur initial
        updateResultsCount();
    }
    
    /**
     * Configuration des écouteurs d'événements
     */
    function setupEventListeners() {
        // Recherche en temps réel
        $('#search').on('input', debounce(handleSearch, config.searchDelay));
        
        // Filtres en temps réel
        $('.filter-select').on('change', handleFilterChange);
        
        // Réinitialisation des filtres
        $('.reset-filters').on('click', resetFilters);
        
        // Soumission du formulaire
        $('.astro-filter-form').on('submit', handleFormSubmit);
        
        // Effets hover sur les cartes
        setupCardHoverEffects();
        
        // Lightbox pour les images
        setupLightbox();
        
        // Bouton Load More
        $('#load-more-btn').on('click', loadMoreItems);
        
        // Gestion du responsive
        $(window).on('resize', debounce(handleResize, 250));
    }
    
    /**
     * Gestion de la recherche en temps réel
     */
    function handleSearch() {
        const query = $('#search').val().toLowerCase().trim();
        
        if (query.length === 0) {
            showAllItems();
            return;
        }
        
        filterItemsBySearch(query);
    }
    
    /**
     * Filtrage par recherche textuelle
     */
    function filterItemsBySearch(query) {
        const $items = $('.astro-gallery-item');
        let visibleCount = 0;
        
        $items.each(function() {
            const $item = $(this);
            const title = $item.find('.astro-gallery-title').text().toLowerCase();
            const objectName = $item.find('.astro-object-name').text().toLowerCase();
            const constellation = $item.find('.astro-constellation').text().toLowerCase();
            
            const matches = title.includes(query) || 
                          objectName.includes(query) || 
                          constellation.includes(query);
                          
            if (matches) {
                $item.fadeIn(config.animationDuration);
                visibleCount++;
            } else {
                $item.fadeOut(config.animationDuration);
            }
        });
        
        updateResultsCount(visibleCount);
        
        // Afficher message si aucun résultat
        if (visibleCount === 0) {
            showNoResultsMessage('Aucun résultat pour "' + query + '"');
        } else {
            hideNoResultsMessage();
        }
    }
    
    /**
     * Gestion des changements de filtres
     */
    function handleFilterChange() {
        const filters = getActiveFilters();
        applyFilters(filters);
    }
    
    /**
     * Récupération des filtres actifs
     */
    function getActiveFilters() {
        return {
            objectType: $('#object_type').val(),
            telescope: $('#telescope').val(),
            camera: $('#camera').val(),
            search: $('#search').val().toLowerCase().trim()
        };
    }
    
    /**
     * Application des filtres
     */
    function applyFilters(filters) {
        const $items = $('.astro-gallery-item');
        let visibleCount = 0;
        
        $items.each(function() {
            const $item = $(this);
            let matches = true;
            
            // Filtre par type d'objet
            if (filters.objectType) {
                const itemObjectType = $item.data('object-type');
                if (itemObjectType !== filters.objectType) {
                    matches = false;
                }
            }
            
            // Filtre par télescope
            if (filters.telescope && matches) {
                const itemTelescope = $item.data('telescope');
                if (!itemTelescope || !itemTelescope.toLowerCase().includes(filters.telescope.toLowerCase())) {
                    matches = false;
                }
            }
            
            // Filtre par recherche textuelle
            if (filters.search && matches) {
                const title = $item.find('.astro-gallery-title').text().toLowerCase();
                const objectName = $item.find('.astro-object-name').text().toLowerCase();
                
                if (!title.includes(filters.search) && !objectName.includes(filters.search)) {
                    matches = false;
                }
            }
            
            // Affichage avec animation
            if (matches) {
                if (!$item.is(':visible')) {
                    $item.hide().fadeIn(config.animationDuration);
                }
                visibleCount++;
            } else {
                if ($item.is(':visible')) {
                    $item.fadeOut(config.animationDuration);
                }
            }
        });
        
        updateResultsCount(visibleCount);
        
        // Gestion du message "aucun résultat"
        if (visibleCount === 0 && hasActiveFilters(filters)) {
            showNoResultsMessage('Aucun résultat pour ces critères');
        } else {
            hideNoResultsMessage();
        }
    }
    
    /**
     * Vérifier si des filtres sont actifs
     */
    function hasActiveFilters(filters) {
        return filters.objectType || filters.telescope || filters.camera || filters.search;
    }
    
    /**
     * Affichage de tous les éléments
     */
    function showAllItems() {
        $('.astro-gallery-item').fadeIn(config.animationDuration);
        updateResultsCount();
        hideNoResultsMessage();
    }
    
    /**
     * Réinitialisation des filtres
     */
    function resetFilters() {
        $('#search').val('');
        $('.filter-select').val('');
        showAllItems();
    }
    
    /**
     * Mise à jour du compteur de résultats
     */
    function updateResultsCount(count = null) {
        if (count === null) {
            count = $('.astro-gallery-item:visible').length;
        }
        
        const total = $('.astro-gallery-item').length;
        
        $('.results-count').html(
            '📊 <strong>' + count + '</strong> image(s) affichée(s) sur <strong>' + total + '</strong> au total'
        );
    }
    
    /**
     * Affichage du message "aucun résultat"
     */
    function showNoResultsMessage(message) {
        let $noResults = $('.astro-no-results-dynamic');
        
        if ($noResults.length === 0) {
            $noResults = $('<div class="astro-no-results-dynamic" style="display:none;">' +
                '<div class="astro-no-content">' +
                '<h3>🔍 ' + message + '</h3>' +
                '<p>Essayez d\'ajuster vos critères de recherche.</p>' +
                '<button type="button" class="button reset-filters">🔄 Réinitialiser les filtres</button>' +
                '</div>' +
                '</div>');
            
            $('.astro-gallery-grid').after($noResults);
            
            // Ajouter l'événement de réinitialisation
            $noResults.find('.reset-filters').on('click', resetFilters);
        } else {
            $noResults.find('h3').text('🔍 ' + message);
        }
        
        $noResults.fadeIn(config.animationDuration);
    }
    
    /**
     * Masquage du message "aucun résultat"
     */
    function hideNoResultsMessage() {
        $('.astro-no-results-dynamic').fadeOut(config.animationDuration);
    }
    
    /**
     * Gestion de la soumission du formulaire
     */
    function handleFormSubmit(e) {
        e.preventDefault();
        const filters = getActiveFilters();
        applyFilters(filters);
    }
    
    /**
     * Configuration des effets hover sur les cartes
     */
    function setupCardHoverEffects() {
        $('.astro-gallery-item').each(function() {
            const $item = $(this);
            
            $item.on('mouseenter', function() {
                $(this).addClass('hovered');
            });
            
            $item.on('mouseleave', function() {
                $(this).removeClass('hovered');
            });
        });
    }
    
    /**
     * Configuration du lazy loading pour les images
     */
    function setupLazyLoading() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        if (img.dataset.src) {
                            img.src = img.dataset.src;
                            img.classList.remove('lazy');
                            img.classList.add('loaded');
                            observer.unobserve(img);
                        }
                    }
                });
            });
            
            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }
    
    /**
     * Configuration de la lightbox pour les images
     */
    function setupLightbox() {
        $('.astro-gallery-item img').on('click', function(e) {
            e.preventDefault();
            const src = $(this).closest('a').attr('href');
            const title = $(this).closest('.astro-gallery-item').find('.astro-gallery-title').text();
            
            showLightbox(src, title);
        });
    }
    
    /**
     * Affichage de la lightbox
     */
    function showLightbox(src, title) {
        const lightboxHtml = `
            <div class="astro-lightbox" style="display: none;">
                <div class="astro-lightbox-overlay"></div>
                <div class="astro-lightbox-content">
                    <button class="astro-lightbox-close">&times;</button>
                    <img src="${src}" alt="${title}">
                    <div class="astro-lightbox-title">${title}</div>
                </div>
            </div>
        `;
        
        $('body').append(lightboxHtml);
        
        const $lightbox = $('.astro-lightbox');
        $lightbox.fadeIn(config.animationDuration);
        
        // Fermeture de la lightbox
        $lightbox.find('.astro-lightbox-close, .astro-lightbox-overlay').on('click', function() {
            $lightbox.fadeOut(config.animationDuration, function() {
                $lightbox.remove();
            });
        });
        
        // Fermeture avec Escape
        $(document).on('keydown.lightbox', function(e) {
            if (e.keyCode === 27) {
                $lightbox.find('.astro-lightbox-close').click();
                $(document).off('keydown.lightbox');
            }
        });
    }
    
    /**
     * Initialisation des filtres
     */
    function initializeFilters() {
        // Appliquer les filtres déjà présents dans l'URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('search') || urlParams.has('object_type') || 
            urlParams.has('telescope') || urlParams.has('camera')) {
            
            setTimeout(() => {
                const filters = getActiveFilters();
                applyFilters(filters);
            }, 100);
        }
    }
    
    /**
     * Configuration des raccourcis clavier
     */
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            // Ctrl/Cmd + K pour focus sur la recherche
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 75) {
                e.preventDefault();
                $('#search').focus();
            }
            
            // Ctrl/Cmd + R pour réinitialiser
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 82) {
                e.preventDefault();
                resetFilters();
            }
        });
    }
    
    /**
     * Configuration du scroll infini (si nécessaire)
     */
    function setupInfiniteScroll() {
        // Implémentation future si beaucoup d'images
    }
    
    /**
     * Chargement de plus d'éléments
     */
    function loadMoreItems() {
        // Implémentation future pour la pagination
    }
    
    /**
     * Gestion du responsive
     */
    function handleResize() {
        // Ajustements responsive si nécessaire
    }
    
    /**
     * Fonction utilitaire de debouncing
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Styles CSS pour la lightbox
    if (!$('#astro-lightbox-styles').length) {
        $('head').append(`
            <style id="astro-lightbox-styles">
                .astro-lightbox {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    z-index: 999999;
                }
                
                .astro-lightbox-overlay {
                    position: absolute;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0,0,0,0.9);
                    backdrop-filter: blur(10px);
                }
                
                .astro-lightbox-content {
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%);
                    max-width: 90vw;
                    max-height: 90vh;
                    text-align: center;
                }
                
                .astro-lightbox-content img {
                    max-width: 100%;
                    max-height: 80vh;
                    box-shadow: 0 10px 50px rgba(0,0,0,0.5);
                    border-radius: 8px;
                }
                
                .astro-lightbox-close {
                    position: absolute;
                    top: -40px;
                    right: -40px;
                    background: rgba(255,255,255,0.2);
                    border: none;
                    color: white;
                    font-size: 30px;
                    width: 40px;
                    height: 40px;
                    border-radius: 50%;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                
                .astro-lightbox-close:hover {
                    background: rgba(255,255,255,0.3);
                    transform: scale(1.1);
                }
                
                .astro-lightbox-title {
                    color: white;
                    margin-top: 15px;
                    font-size: 18px;
                    font-weight: 600;
                }
                
                @media (max-width: 768px) {
                    .astro-lightbox-close {
                        top: 10px;
                        right: 10px;
                    }
                }
            </style>
        `);
    }
});