jQuery(document).ready(function($) {
    'use strict';
    
    // Variables globales
    var currentPage = 1;
    var loading = false;
    
    // Système de likes
    $('.astro-like-button').click(function(e) {
        e.preventDefault();
        
        var $button = $(this);
        var imageId = $button.data('image-id');
        var $count = $button.find('.like-count');
        
        if ($button.hasClass('liked')) {
            return; // Déjà liké
        }
        
        $button.prop('disabled', true).addClass('astro-loading');
        
        $.ajax({
            url: astroPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'astro_like_image',
                image_id: imageId,
                nonce: astroPublic.nonce
            },
            success: function(response) {
                if (response.success) {
                    $count.text(response.data.likes_count);
                    $button.addClass('liked').text('❤ ' + response.data.likes_count + ' ' + astroPublic.strings.liked);
                    
                    // Animation de feedback
                    $button.addClass('astro-pulse');
                    setTimeout(function() {
                        $button.removeClass('astro-pulse');
                    }, 600);
                } else {
                    alert(response.data.message || astroPublic.strings.error);
                }
            },
            error: function() {
                alert(astroPublic.strings.error);
            },
            complete: function() {
                $button.prop('disabled', false).removeClass('astro-loading');
            }
        });
    });
    
    // Chargement infini (Load More)
    var $loadMoreButton = $('.astro-load-more');
    var $imageGrid = $('.astro-image-grid');
    
    if ($loadMoreButton.length && $imageGrid.length) {
        $loadMoreButton.click(function(e) {
            e.preventDefault();
            loadMoreImages();
        });
        
        // Chargement automatique au scroll (optionnel)
        if ($loadMoreButton.hasClass('auto-load')) {
            $(window).scroll(function() {
                if (!loading && $(window).scrollTop() + $(window).height() > $(document).height() - 1000) {
                    loadMoreImages();
                }
            });
        }
    }
    
    function loadMoreImages() {
        if (loading) return;
        
        loading = true;
        currentPage++;
        
        $loadMoreButton.text(astroPublic.strings.loading).prop('disabled', true);
        
        var filters = getActiveFilters();
        filters.page = currentPage;
        
        $.ajax({
            url: astroPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'astro_load_more_images',
                page: currentPage,
                filters: filters,
                nonce: astroPublic.nonce
            },
            success: function(response) {
                if (response.success) {
                    var $newImages = $(response.data.html);
                    
                    // Ajouter avec animation
                    $newImages.hide().appendTo($imageGrid).fadeIn(600);
                    
                    if (!response.data.has_more) {
                        $loadMoreButton.hide();
                        
                        // Afficher un message si configuré
                        if ($('.astro-no-more-message').length === 0) {
                            $imageGrid.after('<p class="astro-no-more-message">' + astroPublic.strings.noMoreImages + '</p>');
                        }
                    } else {
                        $loadMoreButton.text('Charger plus d\'images').prop('disabled', false);
                    }
                } else {
                    alert(response.data.message || astroPublic.strings.error);
                    currentPage--; // Réinitialiser la page en cas d'erreur
                }
            },
            error: function() {
                alert(astroPublic.strings.error);
                currentPage--;
            },
            complete: function() {
                loading = false;
                $loadMoreButton.text('Charger plus d\'images').prop('disabled', false);
            }
        });
    }
    
    function getActiveFilters() {
        var filters = {};
        
        // Récupérer les filtres de l'URL ou du formulaire
        var urlParams = new URLSearchParams(window.location.search);
        
        if (urlParams.has('search')) {
            filters.search = urlParams.get('search');
        }
        
        if (urlParams.has('catalog')) {
            filters.catalog = urlParams.get('catalog');
        }
        
        if (urlParams.has('type')) {
            filters.type = urlParams.get('type');
        }
        
        if (urlParams.has('object')) {
            filters.object = urlParams.get('object');
        }
        
        return filters;
    }
    
    // Lightbox pour les images
    var $lightbox = null;
    
    $('.astro-image-link, .astro-gallery-image').click(function(e) {
        // Vérifier si c'est activé
        if (!$('body').hasClass('astro-lightbox-enabled')) {
            return; // Laisser le comportement par défaut
        }
        
        e.preventDefault();
        
        var imageUrl = $(this).find('img').attr('src') || $(this).attr('href');
        var imageTitle = $(this).find('img').attr('alt') || '';
        
        if (!imageUrl) return;
        
        openLightbox(imageUrl, imageTitle);
    });
    
    function openLightbox(imageUrl, title) {
        if (!$lightbox) {
            $lightbox = $('<div class="astro-lightbox">' +
                         '<div class="astro-lightbox-backdrop"></div>' +
                         '<div class="astro-lightbox-content">' +
                         '<button class="astro-lightbox-close">&times;</button>' +
                         '<img class="astro-lightbox-image" />' +
                         '<div class="astro-lightbox-title"></div>' +
                         '</div>' +
                         '</div>');
            
            $('body').append($lightbox);
            
            // Événements de fermeture
            $lightbox.find('.astro-lightbox-close, .astro-lightbox-backdrop').click(function() {
                closeLightbox();
            });
            
            // Fermer avec Échap
            $(document).keyup(function(e) {
                if (e.keyCode === 27) {
                    closeLightbox();
                }
            });
        }
        
        $lightbox.find('.astro-lightbox-image').attr('src', imageUrl);
        $lightbox.find('.astro-lightbox-title').text(title);
        $lightbox.fadeIn(300);
        $('body').addClass('astro-lightbox-open');
    }
    
    function closeLightbox() {
        if ($lightbox) {
            $lightbox.fadeOut(300);
            $('body').removeClass('astro-lightbox-open');
        }
    }
    
    // Recherche avec suggestions
    var $searchInput = $('.astro-search-input');
    var $searchSuggestions = $('.astro-search-suggestions');
    var searchTimeout;
    
    if ($searchInput.length) {
        $searchInput.on('input', function() {
            var query = $(this).val().trim();
            
            clearTimeout(searchTimeout);
            
            if (query.length < 2) {
                hideSuggestions();
                return;
            }
            
            searchTimeout = setTimeout(function() {
                searchSuggestions(query);
            }, 300);
        });
        
        // Navigation clavier dans les suggestions
        $searchInput.keydown(function(e) {
            var $suggestions = $('.astro-search-suggestion');
            var $active = $suggestions.filter('.active');
            
            switch (e.keyCode) {
                case 38: // Flèche haut
                    e.preventDefault();
                    if ($active.length) {
                        $active.removeClass('active').prev('.astro-search-suggestion').addClass('active');
                    } else {
                        $suggestions.last().addClass('active');
                    }
                    break;
                    
                case 40: // Flèche bas
                    e.preventDefault();
                    if ($active.length) {
                        $active.removeClass('active').next('.astro-search-suggestion').addClass('active');
                    } else {
                        $suggestions.first().addClass('active');
                    }
                    break;
                    
                case 13: // Entrée
                    if ($active.length) {
                        e.preventDefault();
                        $active.click();
                    }
                    break;
                    
                case 27: // Échap
                    hideSuggestions();
                    break;
            }
        });
        
        // Cacher les suggestions quand on clique ailleurs
        $(document).click(function(e) {
            if (!$(e.target).closest('.astro-search-container').length) {
                hideSuggestions();
            }
        });
    }
    
    function searchSuggestions(query) {
        $.ajax({
            url: astroPublic.ajaxUrl,
            type: 'POST',
            data: {
                action: 'astro_search_suggestions',
                query: query,
                nonce: astroPublic.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    showSuggestions(response.data);
                } else {
                    hideSuggestions();
                }
            },
            error: function() {
                hideSuggestions();
            }
        });
    }
    
    function showSuggestions(suggestions) {
        if (!$searchSuggestions.length) {
            $searchSuggestions = $('<div class="astro-search-suggestions"></div>');
            $searchInput.after($searchSuggestions);
        }
        
        var html = '';
        $.each(suggestions, function(index, item) {
            html += '<div class="astro-search-suggestion" data-type="' + item.type + '" data-url="' + item.url + '">';
            html += '<strong>' + item.title + '</strong>';
            if (item.subtitle) {
                html += '<small>' + item.subtitle + '</small>';
            }
            html += '</div>';
        });
        
        $searchSuggestions.html(html).show();
        
        // Gérer les clics sur les suggestions
        $('.astro-search-suggestion').click(function() {
            var url = $(this).data('url');
            var title = $(this).find('strong').text();
            
            $searchInput.val(title);
            hideSuggestions();
            
            if (url) {
                window.location.href = url;
            }
        });
    }
    
    function hideSuggestions() {
        if ($searchSuggestions && $searchSuggestions.length) {
            $searchSuggestions.hide();
        }
    }
    
    // Filtres avancés
    $('.astro-filter-toggle').click(function() {
        var $filters = $('.astro-advanced-filters');
        
        if ($filters.is(':visible')) {
            $filters.slideUp();
            $(this).text('Afficher les filtres avancés');
        } else {
            $filters.slideDown();
            $(this).text('Masquer les filtres avancés');
        }
    });
    
    // Réinitialiser les filtres
    $('.astro-reset-filters').click(function(e) {
        e.preventDefault();
        
        $('.astro-filters input, .astro-filters select').val('');
        $('.astro-filters form').submit();
    });
    
    // Animation d'apparition des éléments au scroll
    function animateOnScroll() {
        $('.astro-image-card, .astro-object-card').each(function() {
            var $element = $(this);
            
            if (!$element.hasClass('astro-animated')) {
                var elementTop = $element.offset().top;
                var windowBottom = $(window).scrollTop() + $(window).height();
                
                if (elementTop < windowBottom - 100) {
                    $element.addClass('astro-animated astro-fade-in');
                }
            }
        });
    }
    
    // Déclencher l'animation au chargement et au scroll
    animateOnScroll();
    $(window).scroll(throttle(animateOnScroll, 100));
    
    // Fonction utilitaire de throttling
    function throttle(func, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    }
    
    // Partage sur les réseaux sociaux (si implémenté)
    $('.astro-share-button').click(function(e) {
        e.preventDefault();
        
        var platform = $(this).data('platform');
        var url = encodeURIComponent(window.location.href);
        var title = encodeURIComponent(document.title);
        var shareUrl = '';
        
        switch (platform) {
            case 'facebook':
                shareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + url;
                break;
            case 'twitter':
                shareUrl = 'https://twitter.com/intent/tweet?url=' + url + '&text=' + title;
                break;
            case 'pinterest':
                var imageUrl = $('.astro-single-image img').first().attr('src');
                if (imageUrl) {
                    shareUrl = 'https://pinterest.com/pin/create/button/?url=' + url + '&media=' + encodeURIComponent(imageUrl) + '&description=' + title;
                }
                break;
        }
        
        if (shareUrl) {
            window.open(shareUrl, 'share', 'width=600,height=400');
        }
    });
    
    // Copier le lien de l'image
    $('.astro-copy-link').click(function(e) {
        e.preventDefault();
        
        var url = window.location.href;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(url).then(function() {
                showNotification('Lien copié dans le presse-papiers !', 'success');
            });
        } else {
            // Fallback
            var tempInput = $('<input>');
            $('body').append(tempInput);
            tempInput.val(url).select();
            document.execCommand('copy');
            tempInput.remove();
            
            showNotification('Lien copié dans le presse-papiers !', 'success');
        }
    });
    
    // Système de notifications
    function showNotification(message, type) {
        var $notification = $('<div class="astro-notification astro-notification-' + (type || 'info') + '">' + message + '</div>');
        
        $('body').append($notification);
        
        setTimeout(function() {
            $notification.addClass('astro-show');
        }, 100);
        
        setTimeout(function() {
            $notification.removeClass('astro-show');
            setTimeout(function() {
                $notification.remove();
            }, 300);
        }, 3000);
    }
    
    // Lazy loading des images (si supporté)
    if ('IntersectionObserver' in window) {
        var imageObserver = new IntersectionObserver(function(entries, observer) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    var img = entry.target;
                    var src = img.dataset.src;
                    
                    if (src) {
                        img.src = src;
                        img.classList.remove('astro-lazy');
                        img.classList.add('astro-loaded');
                        observer.unobserve(img);
                    }
                }
            });
        });
        
        $('.astro-lazy').each(function() {
            imageObserver.observe(this);
        });
    }
    
    // Mode plein écran pour les images
    $('.astro-fullscreen-toggle').click(function() {
        var $image = $(this).siblings('img').first();
        
        if (!$image.length) return;
        
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else if ($image[0].requestFullscreen) {
            $image[0].requestFullscreen();
        }
    });
    
    // Gestion des erreurs d'images
    $('.astro-image-grid img, .astro-single-image img').error(function() {
        $(this).replaceWith('<div class="astro-image-error">Image non disponible</div>');
    });
    
    // Smooth scroll pour les ancres internes
    $('a[href^="#"]').click(function(e) {
        var target = $(this.getAttribute('href'));
        
        if (target.length) {
            e.preventDefault();
            $('html, body').animate({
                scrollTop: target.offset().top - 80
            }, 800);
        }
    });
    
    // Initialisation des tooltips (si une bibliothèque est chargée)
    if (typeof $.fn.tooltip === 'function') {
        $('.astro-tooltip').tooltip();
    }
    
    // Statistiques de vues (tracking simple)
    if ($('.astro-single-image').length && typeof astroPublic.trackViews !== 'undefined' && astroPublic.trackViews) {
        // Incrémenter les vues après 5 secondes de présence sur la page
        setTimeout(function() {
            var imageId = $('[data-image-id]').first().data('image-id');
            
            if (imageId) {
                $.post(astroPublic.ajaxUrl, {
                    action: 'astro_track_view',
                    image_id: imageId,
                    nonce: astroPublic.nonce
                });
            }
        }, 5000);
    }
});