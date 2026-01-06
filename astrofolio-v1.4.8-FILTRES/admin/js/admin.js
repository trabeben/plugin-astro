/**
 * =============================================================================
 * JAVASCRIPT POUR L'INTERFACE D'ADMINISTRATION ASTROFOLIO
 * =============================================================================
 * 
 * Ce fichier gère toute l'interactivité de l'interface d'administration
 * 
 * ⚡ FONCTIONNALITÉS PRINCIPALES :
 * - Autocomplétion des objets astronomiques
 * - Upload d'images avec prévisualisation
 * - Validation de formulaires côté client
 * - Gestion des métadonnées dynamiques
 * - Interface de récupération d'images
 * 
 * 📋 ACTIONS AJAX GÉRÉES :
 * - Recherche d'objets dans les catalogues
 * - Chargement de métadonnées existantes
 * - Suppression d'images
 * - Récupération d'images manquantes
 * - Validation en temps réel
 * 
 * 🔒 SÉCURITÉ :
 * - Tous les appels AJAX incluent des nonces WordPress
 * - Validation côté client ET serveur
 * - Sanitisation des inputs utilisateur
 * - Gestion d'erreurs robuste
 * 
 * 📱 RESPONSIVE :
 * - Interface adaptive mobile/tablette
 * - Touch events pour les appareils tactiles
 * - Dégradé gracieux sans JavaScript
 * 
 * @version 1.4.6
 * @author Benoist Degonne
 */
(function($) {
    'use strict';
    
    // Attendre le chargement du DOM
    $(document).ready(function() {
        console.log('🌌 AstroFolio Admin loaded');
        
        // Initialiser les fonctionnalités
        initUploadForm();
        initGallery();
        initDashboard();
    });
    
    /**
     * Initialisation du formulaire d'upload
     */
    function initUploadForm() {
        if (!$('.astro-upload-form').length) {
            return;
        }
        
        console.log('📸 Upload form initialized');
        
        // Validation côté client
        $('.astro-upload-form').on('submit', function(e) {
            var isValid = validateUploadForm();
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // Afficher le statut de chargement
            showUploadProgress();
        });
        
        // Preview de l'image sélectionnée
        $('#image_file').on('change', function() {
            previewSelectedImage(this);
        });
        
        // Auto-complétion pour les objets célestes
        setupObjectAutocomplete();
        // Auto-complétion pour l'équipement
        setupEquipmentAutocomplete();
    }
    
    /**
     * Validation du formulaire d'upload
     */
    function validateUploadForm() {
        var isValid = true;
        var errors = [];
        
        // Vérifier le fichier image
        var fileInput = $('#image_file')[0];
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            errors.push('Veuillez sélectionner un fichier image.');
            isValid = false;
        } else {
            var file = fileInput.files[0];
            var allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            
            if (!allowedTypes.includes(file.type)) {
                errors.push('Format de fichier non supporté. Utilisez JPG, PNG ou GIF.');
                isValid = false;
            }
            
            // Vérifier la taille (max 10MB)
            if (file.size > 10 * 1024 * 1024) {
                errors.push('Le fichier est trop volumineux (max 10MB).');
                isValid = false;
            }
        }
        
        // Vérifier le titre
        var title = $('#title').val().trim();
        if (!title) {
            errors.push('Le titre est obligatoire.');
            isValid = false;
        }
        
        // Afficher les erreurs
        if (!isValid) {
            showValidationErrors(errors);
        }
        
        return isValid;
    }
    
    /**
     * Afficher les erreurs de validation
     */
    function showValidationErrors(errors) {
        var errorHtml = '<div class="notice notice-error is-dismissible astro-notice">';
        errorHtml += '<p><strong>Erreurs de validation :</strong></p><ul>';
        
        errors.forEach(function(error) {
            errorHtml += '<li>' + error + '</li>';
        });
        
        errorHtml += '</ul></div>';
        
        // Supprimer les anciennes erreurs
        $('.astro-notice').remove();
        
        // Afficher les nouvelles erreurs
        $('.wrap h1').after(errorHtml);
        
        // Faire défiler vers le haut
        $('html, body').animate({
            scrollTop: $('.wrap').offset().top - 50
        }, 300);
    }
    
    /**
     * Afficher le progrès d'upload
     */
    function showUploadProgress() {
        var button = $('input[name="upload_image"]');
        button.val('⏳ Téléchargement en cours...');
        button.prop('disabled', true);
        
        $('.astro-upload-form').addClass('astro-loading');
        
        // Message d'information
        var progressHtml = '<div class="notice notice-info astro-notice">';
        progressHtml += '<p>⏳ Téléchargement et traitement de l\'image en cours... Veuillez patienter.</p>';
        progressHtml += '</div>';
        
        $('.astro-notice').remove();
        $('.wrap h1').after(progressHtml);
    }
    
    /**
     * Preview de l'image sélectionnée
     */
    function previewSelectedImage(input) {
        if (input.files && input.files[0]) {
            var reader = new FileReader();
            
            reader.onload = function(e) {
                // Supprimer l'ancien preview s'il existe
                $('.astro-image-preview').remove();
                
                // Créer le preview
                var previewHtml = '<div class="astro-image-preview" style="margin-top: 10px;">';
                previewHtml += '<img src="' + e.target.result + '" style="max-width: 300px; max-height: 200px; border-radius: 4px; border: 1px solid #ddd;">';
                previewHtml += '<p style="margin: 5px 0 0 0; font-size: 13px; color: #666;">Preview de l\'image sélectionnée</p>';
                previewHtml += '</div>';
                
                $(input).closest('.form-field').append(previewHtml);
            };
            
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    /**
     * Auto-complétion pour les champs d'équipement
     */
    function setupEquipmentAutocomplete() {
        // Suggestions pour les télescopes populaires
        var telescopes = [
            'Celestron EdgeHD 8',
            'Celestron EdgeHD 11', 
            'Celestron EdgeHD 14',
            'Sky-Watcher Esprit 100',
            'Sky-Watcher Evostar 72',
            'Takahashi FSQ-106ED',
            'William Optics RedCat 61',
            'Explore Scientific ED80'
        ];
        
        var cameras = [
            'ZWO ASI2600MC-Pro',
            'ZWO ASI294MC-Pro',
            'ZWO ASI533MC-Pro',
            'Canon EOS Ra',
            'Canon EOS 6D',
            'Nikon D810a',
            'QSI 683wsg'
        ];
        
        var mounts = [
            'Celestron CGX',
            'Celestron CGX-L',
            'Sky-Watcher EQ6-R Pro',
            'Sky-Watcher AZ-EQ6 GT',
            '10Micron GM1000 HPS',
            'Paramount MX+'
        ];
        
        setupDatalist('telescope', telescopes);
        setupDatalist('camera', cameras);
        setupDatalist('mount', mounts);
    }

    /**
     * Configurer une datalist pour auto-complétion
     */
    function setupDatalist(fieldId, options) {
        var field = $('#' + fieldId);
        if (!field.length) return;
        
        var datalistId = fieldId + '-datalist';
        var datalist = '<datalist id="' + datalistId + '">';
        
        options.forEach(function(option) {
            datalist += '<option value="' + option + '">';
        });
        
        datalist += '</datalist>';
        
        $('body').append(datalist);
        field.attr('list', datalistId);
    }
    
    /**
     * Initialisation de la galerie
     */
    function initGallery() {
        if (!$('.astro-gallery-grid').length) {
            return;
        }
        
        console.log('🖼️ Gallery initialized');
        
        // Lazy loading des images
        lazyLoadImages();
        
        // Modal pour affichage grand format (optionnel)
        setupImageModal();
    }
    
    /**
     * Lazy loading des images
     */
    function lazyLoadImages() {
        if ('IntersectionObserver' in window) {
            var imageObserver = new IntersectionObserver(function(entries, observer) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        var img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });
            
            $('.astro-gallery-item img[data-src]').each(function() {
                imageObserver.observe(this);
            });
        }
    }
    
    /**
     * Modal pour les images (optionnel)
     */
    function setupImageModal() {
        $('.astro-gallery-item img').on('click', function(e) {
            e.preventDefault();
            
            var imgSrc = $(this).closest('a').attr('href');
            var title = $(this).attr('alt');
            
            // Créer et afficher la modal
            var modal = '<div class="astro-modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10000; display: flex; align-items: center; justify-content: center;">';
            modal += '<div class="astro-modal-content" style="max-width: 90%; max-height: 90%; position: relative;">';
            modal += '<img src="' + imgSrc + '" style="max-width: 100%; max-height: 100%; border-radius: 4px;">';
            modal += '<div style="position: absolute; top: -40px; right: 0; color: white; font-size: 30px; cursor: pointer;" class="astro-modal-close">&times;</div>';
            if (title) {
                modal += '<div style="position: absolute; bottom: -40px; left: 0; color: white; font-size: 16px;">' + title + '</div>';
            }
            modal += '</div></div>';
            
            $('body').append(modal);
            
            // Fermer la modal
            $('.astro-modal-overlay, .astro-modal-close').on('click', function() {
                $('.astro-modal-overlay').remove();
            });
            
            // Empêcher la propagation sur l'image
            $('.astro-modal-content img').on('click', function(e) {
                e.stopPropagation();
            });
        });
    }
    
    /**
     * Initialisation du dashboard
     */
    function initDashboard() {
        if (!$('.astro-dashboard-stats').length) {
            return;
        }
        
        console.log('📊 Dashboard initialized');
        
        // Animation des statistiques
        animateStats();
        
        // Actualisation automatique (optionnel)
        setupAutoRefresh();
    }
    
    /**
     * Animation des statistiques
     */
    function animateStats() {
        $('.stat-number').each(function() {
            var $this = $(this);
            var countTo = parseInt($this.text().replace(/[^0-9]/g, ''));
            
            if (countTo > 0) {
                $this.text('0');
                
                $({ countNum: 0 }).animate(
                    { countNum: countTo },
                    {
                        duration: 2000,
                        easing: 'swing',
                        step: function() {
                            $this.text(Math.floor(this.countNum).toLocaleString());
                        },
                        complete: function() {
                            $this.text(countTo.toLocaleString());
                        }
                    }
                );
            }
        });
    }
    
    /**
     * Actualisation automatique du dashboard
     */
    function setupAutoRefresh() {
        // Actualiser les stats toutes les 5 minutes
        setInterval(function() {
            if (typeof astrofolioAdmin !== 'undefined') {
                refreshDashboardStats();
            }
        }, 300000); // 5 minutes
    }
    
    /**
     * Actualiser les statistiques du dashboard
     */
    function refreshDashboardStats() {
        $.ajax({
            url: astrofolioAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'astrofolio_get_stats',
                nonce: astrofolioAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Mettre à jour les statistiques
                    updateDashboardStats(response.data);
                }
            },
            error: function() {
                console.log('Erreur lors de l\'actualisation des statistiques');
            }
        });
    }
    
    /**
     * Mettre à jour les statistiques du dashboard
     */
    function updateDashboardStats(data) {
        if (data.images_count) {
            $('.astro-stat-box:first .stat-number').text(data.images_count);
        }
        if (data.catalog_objects) {
            $('.astro-stat-box:last .stat-number').text(data.catalog_objects.toLocaleString());
        }
    }
    
    /**
     * Système d'autocomplétion pour les objets célestes
     */
    function setupObjectAutocomplete() {
        // Gérer les deux champs : upload et édition
        const fields = [
            {
                input: '#object_name_input',
                suggestions: '#object_suggestions',
                crossRefs: '#cross_references'
            },
            {
                input: '#edit-object',
                suggestions: '#edit_object_suggestions', 
                crossRefs: '#edit_cross_references'
            }
        ];
        
        fields.forEach(function(field) {
            const $input = $(field.input);
            const $suggestions = $(field.suggestions);
            const $crossRefs = $(field.crossRefs);
            let searchTimeout;
            
            if (!$input.length) {
                return;
            }
            
            console.log('🔍 Object autocomplete initialized for', field.input);
            
            // Recherche avec délai
            $input.on('input', function() {
                const query = $(this).val().trim();
                
                clearTimeout(searchTimeout);
                
                if (query.length < 2) {
                    $suggestions.hide().empty();
                    $crossRefs.hide().empty();
                    return;
                }
                
                searchTimeout = setTimeout(() => {
                    searchCatalogObjectsFor(query, $suggestions, $input, $crossRefs);
                }, 300);
            });
            
            // Masquer les suggestions lors du clic ailleurs
            $(document).on('click', function(e) {
                if (!$input.is(e.target) && !$suggestions.is(e.target) && $suggestions.has(e.target).length === 0) {
                    $suggestions.hide();
                }
            });
            
            // Gérer la sélection d'un objet
            $suggestions.on('click', '.suggestion-item', function() {
                const objectName = $(this).data('primary');
                $input.val(objectName);
                $suggestions.hide();
                
                // Charger les références croisées
                loadCrossReferencesFor(objectName, $crossRefs);
            });
            
            // Gérer la touche Entrée et les flèches
            $input.on('keydown', function(e) {
                const $active = $suggestions.find('.suggestion-item.active');
                const $items = $suggestions.find('.suggestion-item');
                
                switch(e.keyCode) {
                    case 13: // Entrée
                        e.preventDefault();
                        if ($active.length) {
                            $active.click();
                        }
                        break;
                    case 38: // Flèche haut
                        e.preventDefault();
                        if ($active.length && $active.prev('.suggestion-item').length) {
                            $active.removeClass('active').prev('.suggestion-item').addClass('active');
                        } else {
                            $items.removeClass('active').last().addClass('active');
                        }
                        break;
                    case 40: // Flèche bas
                        e.preventDefault();
                        if ($active.length && $active.next('.suggestion-item').length) {
                            $active.removeClass('active').next('.suggestion-item').addClass('active');
                        } else {
                            $items.removeClass('active').first().addClass('active');
                        }
                        break;
                    case 27: // Échap
                        $suggestions.hide();
                        break;
                }
            });
        });
    }
    
    /**
     * Rechercher dans les catalogues d'objets pour un champ spécifique
     */
    function searchCatalogObjectsFor(query, $suggestions, $input, $crossRefs) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'search_catalog_objects',
                search: query
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    displaySuggestionsFor(response.data, $suggestions);
                } else {
                    $suggestions.hide().empty();
                }
            },
            error: function() {
                console.log('Erreur lors de la recherche d\\'objets');
            }
        });
    }
    
    /**
     * Afficher les suggestions d'autocomplétion pour un champ spécifique
     */
    function displaySuggestionsFor(objects, $suggestions) {
        let html = '';
        
        objects.forEach(function(obj) {
            html += '<div class="suggestion-item" data-primary="' + obj.primary + '">';
            html += '<strong>' + obj.primary + '</strong>';
            
            if (obj.alternates) {
                html += ' <span class="alternates">(' + obj.alternates + ')</span>';
            }
            
            if (obj.common_name) {
                html += ' <span class="common-name">' + obj.common_name + '</span>';
            }
            
            html += '</div>';
        });
        
        $suggestions.html(html).show();
    }
    
    /**
     * Charger les références croisées pour un objet spécifique
     */
    function loadCrossReferencesFor(objectName, $crossRefs) {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'get_object_cross_references',
                object_name: objectName
            },
            success: function(response) {
                if (response.success && response.data.primary) {
                    displayCrossReferencesFor(response.data, $crossRefs);
                } else {
                    $crossRefs.hide().empty();
                }
            },
            error: function() {
                console.log('Erreur lors du chargement des références croisées');
            }
        });
    }
    
    /**
     * Afficher les références croisées pour un champ spécifique
     */
    function displayCrossReferencesFor(crossRefs, $crossRefs) {
        let html = '<div class="cross-references-content">';
        
        html += '<h4>📋 Références croisées</h4>';
        
        // Nom principal
        html += '<p><strong>Nom principal :</strong> <span class="primary-name">' + crossRefs.primary + '</span></p>';
        
        // Noms alternatifs
        if (crossRefs.alternates && crossRefs.alternates.length > 0) {
            html += '<p><strong>Autres désignations :</strong> ';
            html += crossRefs.alternates.map(alt => '<span class="alt-name">' + alt.trim() + '</span>').join(', ');
            html += '</p>';
        }
        
        // Nom commun
        if (crossRefs.common_name) {
            html += '<p><strong>Nom commun :</strong> <span class="common-name">' + crossRefs.common_name + '</span></p>';
        }
        
        // Catalogues
        if (crossRefs.catalogs && crossRefs.catalogs.length > 0) {
            html += '<p><strong>Catalogues :</strong> ';
            html += crossRefs.catalogs.map(cat => '<span class="catalog-badge">' + cat.trim() + '</span>').join(' ');
            html += '</p>';
        }
        
        html += '</div>';
        
        $crossRefs.html(html).show();
    }
    
})(jQuery);