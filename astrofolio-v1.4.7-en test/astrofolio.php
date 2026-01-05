<?php
/**
 * Plugin Name: AstroFolio
 * Plugin URI: https://photos-et-nature.com/astrofolio
 * Description: Plugin de gestion d'images d'astrophotographie avec métadonnées complètes et système de récupération avancé
 * Author: Benoist Degonne
 * Version: 1.4.7
 * License: GPL v2 or later
 * 
 * ==================================================
 * ASTROFOLIO v1.4.7 - VERSION STABLE AVEC ENVOI GROUPÉ
 * ==================================================
 * 
 * Ce plugin permet la gestion complète d'images d'astrophotographie avec :
 * 
 * 🔧 FONCTIONNALITÉS PRINCIPALES :
 * - Gestion de métadonnées complètes (objets, équipement, paramètres)
 * - Système de récupération d'images en 6 niveaux
 * - Interface d'administration intuitive
 * - Shortcodes publics pour galeries et affichage
 * - Intégration de catalogues astronomiques (NGC, IC, Messier, etc.)
 * - Références croisées entre catalogues
 * - Upload groupé de fichiers avec barre de progression (NOUVEAU v1.4.7)
 * 
 * 📁 STRUCTURE DU PLUGIN :
 * /admin/         - Classes et ressources de l'interface d'administration
 * /includes/      - Classes métier et logique principale
 * /public/        - Classes et ressources pour le frontend
 * /data/          - Catalogues CSV (NGC, IC, Messier, Caldwell, etc.)
 * 
 * 🚀 CLASSES PRINCIPALES :
 * - AstroFolio_Safe       : Classe principale et orchestrateur
 * - AstroFolio_Database   : Gestion de la base de données
 * - AstroFolio_Images     : Gestion des images et métadonnées
 * - AstroFolio_Catalogs   : Lecture des catalogues astronomiques
 * - AstroFolio_Admin      : Interface d'administration
 * - AstroFolio_Public     : Interface publique et shortcodes
 * 
 * 📊 PERFORMANCE ET SÉCURITÉ :
 * - Singleton pattern pour éviter les instanciations multiples
 * - Protection CSRF avec nonces WordPress
 * - Sanitisation de tous les inputs utilisateur
 * - Mise en cache des catalogues et métadonnées
 * 
 * ⚠️ COMPATIBILITÉ :
 * - WordPress 5.0+
 * - PHP 7.4+
 * - MySQL 5.7+ ou MariaDB 10.2+
 */

// =============================================================================
// SÉCURITÉ : PROTECTION CONTRE L'ACCÈS DIRECT
// =============================================================================
// Cette protection empêche l'exécution directe du fichier PHP
// sans passer par WordPress. Sécurité de base indispensable.
if (!defined('ABSPATH')) {
    exit('Accès direct interdit. Ce fichier doit être appelé via WordPress.');
}

// =============================================================================
// CONSTANTES DU PLUGIN
// =============================================================================
// Ces constantes définissent les chemins et versions du plugin
// Elles sont utilisées partout dans le code pour éviter les chemins en dur

/**
 * Version du plugin - Utilisée pour :
 * - Mise à jour de la base de données si nécessaire
 * - Cache busting des CSS/JS
 * - Vérification de compatibilité
 */
define('ANC_VERSION', '1.4.6');

/**
 * Chemin absolu vers le fichier principal du plugin
 * Utilisé pour les hooks d'activation/désactivation
 */
define('ANC_PLUGIN_FILE', __FILE__);

/**
 * Chemin absolu vers le dossier du plugin
 * Utilisé pour inclure les classes et fichiers de données
 */
define('ANC_PLUGIN_DIR', plugin_dir_path(__FILE__));

/**
 * URL publique du dossier du plugin  
 * Utilisée pour charger les CSS/JS et accéder aux ressources
 */
define('ANC_PLUGIN_URL', plugin_dir_url(__FILE__));

// =============================================================================
// CLASSE PRINCIPALE ASTROFOLIO_SAFE
// =============================================================================
/**
 * Classe principale du plugin AstroFolio
 * 
 * Cette classe orchestre toutes les fonctionnalités du plugin :
 * - Initialisation des hooks WordPress
 * - Chargement des classes métier
 * - Gestion des actions AJAX
 * - Enregistrement des shortcodes
 * - Configuration de l'interface d'administration
 * 
 * Patron de conception : Singleton (une seule instance)
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 */
class AstroFolio_Safe {
    
    /**
     * Instance de la classe d'administration
     * @var mixed|null
     */
    private $admin;
    
    /**
     * Constructeur - Point d'entrée principal
     * 
     * Enregistre tous les hooks WordPress nécessaires :
     * - Hook d'initialisation (init)
     * - Hook d'activation du plugin
     * 
     * @since 1.4.6
     */
    public function __construct() {
        // Hook principal d'initialisation - Déclenché après que WordPress soit entièrement chargé
        add_action('init', array($this, 'init'));
        
        // Hook d'activation - Déclenché lors de l'activation du plugin
        // Utilisé pour créer les tables BDD, ajouter les options, etc.
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    /**
     * Initialisation principale du plugin
     * 
     * Cette méthode est appelée sur le hook 'init' de WordPress
     * Elle configure tous les hooks, actions et filtres nécessaires
     * 
     * STRUCTURE D'INITIALISATION :
     * 1. Configuration de l'administration (si contexte admin)
     * 2. Configuration du frontend (scripts, shortcodes, règles de réécriture)
     * 3. Enregistrement des shortcodes publics
     * 4. Configuration des filtres de média WordPress
     * 
     * @since 1.4.6
     * @return void
     */
    public function init() {
        // =================================================================
        // SECTION ADMINISTRATION - Uniquement en contexte admin
        // =================================================================
        if (is_admin()) {
            // Menu d'administration dans le backoffice WordPress
            add_action('admin_menu', array($this, 'add_admin_menu'));
            
            // Chargement des scripts et styles d'administration
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // Actions AJAX pour les métadonnées d'images
            add_action('wp_ajax_load_image_metadata', array($this, 'ajax_load_metadata'));
            
            // Actions AJAX pour la recherche et gestion des catalogues
            add_action('wp_ajax_astro_search_catalog', array($this, 'ajax_search_catalog'));
            add_action('wp_ajax_astro_load_catalog', array($this, 'ajax_load_catalog'));
            add_action('wp_ajax_search_catalog_objects', array($this, 'ajax_search_catalog_objects'));
            add_action('wp_ajax_get_object_cross_references', array($this, 'ajax_get_object_cross_references'));
            
            // Actions AJAX pour la récupération d'images (système en 6 niveaux)
            add_action('wp_ajax_astro_recover_single_image', array($this, 'ajax_recover_single_image'));
            add_action('wp_ajax_astro_batch_recovery', array($this, 'ajax_batch_recovery'));
            add_action('wp_ajax_astro_force_recover_image', array($this, 'ajax_force_recover_image'));
            add_action('wp_ajax_astro_force_recover_all', array($this, 'ajax_force_recover_all'));
            
            // NOUVEAU v1.4.7 : Action AJAX pour l'upload groupé d'images
            add_action('wp_ajax_astro_upload_bulk_images', array($this, 'ajax_upload_bulk_images'));
        }
        
        // =================================================================
        // SECTION FRONTEND - Scripts et fonctionnalités publiques
        // =================================================================
        
        // Chargement des scripts et styles pour le frontend
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        
        // Règles de réécriture d'URL personnalisées (pour URLs propres)
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Variables de requête personnalisées pour WordPress
        add_filter('query_vars', array($this, 'add_query_vars'));
        
        // Redirection de templates personnalisés
        add_action('template_redirect', array($this, 'template_redirect'));
        
        // =================================================================
        // SHORTCODES PUBLICS - Interface utilisateur frontend
        // =================================================================
        
        // Shortcode principal pour afficher une galerie d'images astro
        add_shortcode('astro_gallery', array($this, 'gallery_shortcode'));
        
        // Shortcode rétrocompatible (ancienne version du plugin)
        add_shortcode('astrofolio_gallery', array($this, 'gallery_shortcode'));
        
        // Shortcode pour afficher une image individuelle
        add_shortcode('astro_image', array($this, 'image_shortcode'));
        
        // Shortcode pour afficher les détails d'un objet astronomique
        add_shortcode('astro_object', array($this, 'object_shortcode'));
        
        // Shortcodes de statistiques et informations
        add_shortcode('astro_stats', array($this, 'stats_shortcode'));
        
        // Shortcode de recherche dans les catalogues
        add_shortcode('astro_search', array($this, 'search_shortcode'));
        
        // Shortcode pour affichage aléatoire d'images
        add_shortcode('astro_random', array($this, 'random_shortcode'));
        
        // Shortcode pour affichage détaillé d'une image
        add_shortcode('astrofolio_image_detail', array($this, 'image_detail_shortcode'));
        
        // =================================================================
        // SHORTCODES DE DEBUG - Uniquement pour les administrateurs
        // =================================================================
        
        // Shortcode de debug général (seulement si paramètre GET astro_debug)
        if (current_user_can('manage_options') && isset($_GET['astro_debug'])) {
            add_shortcode('astro_debug', array($this, 'debug_shortcode'));
        }
        
        // Shortcodes de debug et récupération d'images
        add_shortcode('astro_debug_images', array($this, 'debug_images_shortcode'));
        add_shortcode('astro_recover_images', array($this, 'recover_images_shortcode'));
        add_shortcode('astro_test_recovery', array($this, 'test_recovery_shortcode'));
        add_shortcode('astro_simple_test', array($this, 'simple_test_shortcode'));
        
        // =================================================================
        // FILTRES WORDPRESS - Personnalisation du comportement
        // =================================================================
        
        // Masquer les images AstroFolio de la bibliothèque WordPress standard
        // Ceci évite la pollution de la médiathèque avec nos images spécialisées
        add_filter('ajax_query_attachments_args', array($this, 'hide_astrofolio_images_from_library'));
        add_action('pre_get_posts', array($this, 'hide_astrofolio_images_from_media_list'));
        add_action('admin_notices', array($this, 'add_media_library_notice'));
        
        // =================================================================
        // ACTIONS AJAX SUPPLÉMENTAIRES - Fonctionnalités avancées
        // =================================================================
        
        // Actions AJAX pour l'autocomplétion des objets astronomiques
        add_action('wp_ajax_search_catalog_objects', array($this, 'ajax_search_catalog_objects'));
        add_action('wp_ajax_get_object_cross_references', array($this, 'ajax_get_object_cross_references'));
        add_action('wp_ajax_load_metadata', array($this, 'ajax_load_metadata'));
        
        // Ajouter le script d'autocomplétion dans le footer de l'admin
        add_action('admin_footer', array($this, 'add_autocomplete_script'));
    }
    
    /**
     * Ajouter le script d'autocomplétion dans le footer admin
     * 
     * Ce script JavaScript permet l'autocomplétion en temps réel
     * pour la saisie des noms d'objets astronomiques
     * 
     * FONCTIONNALITÉS :
     * - Recherche dynamique dans les catalogues
     * - Affichage de suggestions en dropdown
     * - Support des références croisées
     * 
     * @since 1.4.6
     * @return void Affiche directement le HTML/CSS/JS
     */
    public function add_autocomplete_script() {
        ?>
        <style>
        /* =============================================================
           STYLES CSS POUR L'AUTOCOMPLÉTION D'OBJETS ASTRONOMIQUES
           ============================================================= */
        .object-autocomplete-suggestions {
            position: absolute;
            z-index: 1000;
            background: white;
            border: 1px solid #ccc;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            display: none;
            width: 100%;
        }
        
        .suggestion-item {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .suggestion-item:hover {
            background-color: #e3f2fd;
            border-left: 3px solid #2196f3;
        }
        
        .suggestion-item:active {
            background-color: #bbdefb;
        }
        
        .object-cross-references {
            margin-top: 10px;
            padding: 10px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .cross-ref-badge {
            display: inline-block;
            padding: 2px 6px;
            margin: 2px;
            background: #007cba;
            color: white;
            border-radius: 3px;
            font-size: 12px;
            cursor: pointer;
        }
        
        .cross-ref-badge:hover {
            background: #005a87;
        }
        
        .object-name-container {
            position: relative;
        }
        </style>
        
        <script type="text/javascript">
        console.log('🚀 AstroFolio autocomplétion initialisée');
        
        jQuery(document).ready(function($) {
            console.log('✅ jQuery prêt pour autocomplétion');
            
            var $input = $('#object_name_input');
            if ($input.length === 0) {
                console.error('❌ Champ #object_name_input non trouvé');
                return;
            }
            
            console.log('✅ Champ trouvé, configuration de l\'autocomplétion...');
            
            // Créer le conteneur pour les suggestions
            var $container = $input.parent();
            $container.css('position', 'relative');
            
            var $suggestions = $('<div id="object_suggestions" class="object-autocomplete-suggestions"></div>');
            $container.append($suggestions);
            
            // Créer le conteneur pour les références croisées
            var $crossRefs = $('<div id="cross_references" class="object-cross-references" style="display:none;"></div>');
            $input.closest('tr').after('<tr><td colspan="2"></td></tr>');
            $input.closest('tr').next().find('td').append($crossRefs);
            
            var searchTimeout;
            
            $input.on('input', function() {
                var search = $(this).val().trim();
                console.log('🔍 Recherche: "' + search + '"');
                
                clearTimeout(searchTimeout);
                
                if (search.length < 2) {
                    $suggestions.empty().hide();
                    $crossRefs.hide();
                    return;
                }
                
                searchTimeout = setTimeout(function() {
                    console.log('📡 Requête AJAX pour: "' + search + '"');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        method: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'search_catalog_objects',
                            search: search,
                            _wpnonce: '<?php echo wp_create_nonce('astrofolio_nonce'); ?>'
                        },
                        success: function(response) {
                            console.log('✅ Réponse AJAX:', response);
                            
                            if (response && response.success && response.data && response.data.length > 0) {
                                console.log('📋 Affichage de ' + response.data.length + ' suggestions');
                                console.log('🔍 Premier objet:', response.data[0]); // Debug de la structure
                                
                                $suggestions.empty();
                                
                                response.data.slice(0, 10).forEach(function(item) {
                                    console.log('📝 Objet traité:', item); // Debug chaque objet
                                    
                                    // Utiliser la vraie structure des données
                                    var displayText = item.primary || 'Objet inconnu';
                                    
                                    // Ajouter le nom commun s'il existe
                                    if (item.common_name && item.common_name.trim()) {
                                        displayText += ' - ' + item.common_name;
                                    }
                                    
                                    // Ajouter les références alternatives
                                    if (item.alternates && item.alternates.trim()) {
                                        displayText += ' (' + item.alternates + ')';
                                    }
                                    
                                    console.log('🏷️ Texte affiché:', displayText);
                                    
                                    var $item = $('<div class="suggestion-item">')
                                        .html('<strong>' + displayText + '</strong>')
                                        .data('value', item.primary || displayText)
                                        .data('primary', item.primary)
                                        .data('alternates', item.alternates)
                                        .data('common_name', item.common_name)
                                        .data('full-text', displayText)
                                        .click(function() {
                                            var selectedValue = $(this).data('primary'); // Nom primaire pour les références croisées
                                            var fullText = $(this).data('full-text'); // Texte complet à afficher
                                            
                                            console.log('👆 Sélection - Nom primaire: "' + selectedValue + '"');
                                            console.log('👆 Sélection - Texte complet: "' + fullText + '"');
                                            
                                            // Mettre le TEXTE COMPLET dans le champ
                                            $input.val(fullText);
                                            $input.trigger('change'); // Déclencher l'événement change pour mise à jour
                                            
                                            console.log('✅ Valeur mise dans le champ: "' + $input.val() + '"');
                                            
                                            $suggestions.empty().hide();
                                            // Utiliser le nom primaire pour charger les références croisées
                                            loadCrossReferences(selectedValue);
                                        });
                                    
                                    $suggestions.append($item);
                                });
                                
                                $suggestions.show();
                                console.log('✅ Suggestions affichées');
                                
                            } else {
                                console.log('ℹ️ Aucun résultat');
                                $suggestions.empty().hide();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('❌ Erreur AJAX:', {
                                status: status,
                                error: error,
                                response: xhr.responseText
                            });
                            $suggestions.empty().hide();
                        }
                    });
                }, 300);
            });
            
            function loadCrossReferences(objectName) {
                if (!objectName) return;
                
                console.log('🔗 Chargement références pour: "' + objectName + '"');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'get_object_cross_references',
                        object_name: objectName,
                        _wpnonce: '<?php echo wp_create_nonce('astrofolio_nonce'); ?>'
                    },
                    success: function(response) {
                        console.log('✅ Réponse références:', response);
                        
                        if (response && response.success && response.data && response.data.length > 0) {
                            console.log('🔗 Affichage de ' + response.data.length + ' références');
                            console.log('🔍 Première référence:', response.data[0]); // Debug
                            
                            $crossRefs.html('<strong>📋 Références croisées :</strong><br>');
                            
                            response.data.forEach(function(ref) {
                                console.log('📝 Référence traitée:', ref); // Debug chaque référence
                                
                                var refText = ref.primary || 'Référence';
                                if (ref.common_name && ref.common_name.trim()) {
                                    refText += ' - ' + ref.common_name;
                                }
                                
                                console.log('🏷️ Texte référence:', refText);
                                
                                var $badge = $('<span class="cross-ref-badge">')
                                    .text(refText)
                                    .click(function() {
                                        console.log('👆 Clic référence: "' + refText + '"');
                                        
                                        // Créer le texte complet pour la référence aussi
                                        var fullRefText = ref.primary || 'Référence';
                                        if (ref.common_name && ref.common_name.trim()) {
                                            fullRefText += ' - ' + ref.common_name;
                                        }
                                        if (ref.alternates && ref.alternates.trim()) {
                                            fullRefText += ' (' + ref.alternates + ')';
                                        }
                                        
                                        $input.val(fullRefText); // Texte complet dans le champ
                                        $suggestions.empty().hide();
                                        loadCrossReferences(ref.primary || refText);
                                    });
                                
                                $crossRefs.append($badge).append(' ');
                            });
                            
                            $crossRefs.show();
                            console.log('✅ Références croisées affichées');
                            
                        } else {
                            console.log('ℹ️ Aucune référence croisée');
                            $crossRefs.hide();
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('❌ Erreur références:', error);
                    }
                });
            }
            
            // Masquer suggestions en cliquant ailleurs
            $(document).click(function(e) {
                if (!$(e.target).closest('#object_name_input, #object_suggestions').length) {
                    $suggestions.hide();
                }
            });
            
            console.log('🎯 Autocomplétion complètement configurée !');
        });
        </script>
        <?php
    }

    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'astrofolio') !== false) {
            wp_enqueue_media();
            wp_enqueue_script('jquery-ui-tabs');
            wp_enqueue_style('wp-jquery-ui-dialog');
            
            // Charger les fichiers JS et CSS du plugin
            wp_enqueue_script(
                'astrofolio-admin-js',
                plugin_dir_url(__FILE__) . 'admin/js/admin.js',
                array('jquery'),
                '1.4.3',
                true
            );
            
            wp_enqueue_style(
                'astrofolio-admin-css',
                plugin_dir_url(__FILE__) . 'admin/css/admin.css',
                array(),
                '1.4.3'
            );
            
            // Variables AJAX pour JavaScript
            wp_localize_script('astrofolio-admin-js', 'astrofolio_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('astrofolio_nonce')
            ));
            
            // Styles pour l'interface de métadonnées
            wp_add_inline_style('wp-admin', '
                .astro-metadata-form .nav-tab-wrapper { margin-bottom: 20px; }
                .astro-metadata-form .tab-content { display: none; background: #fff; padding: 20px; border: 1px solid #ccd0d4; }
                .astro-metadata-form .tab-content.active { display: block; }
                .astro-metadata-form .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
                .astro-metadata-form .form-group { margin-bottom: 15px; }
                .astro-metadata-form .form-group label { display: block; font-weight: bold; margin-bottom: 5px; }
                .astro-metadata-form .form-group input, .astro-metadata-form .form-group textarea, .astro-metadata-form .form-group select { width: 100%; }
                .astro-metadata-preview { background: #f9f9f9; padding: 20px; border: 1px solid #ddd; margin-top: 20px; }
                .astro-metadata-preview h3 { margin-top: 0; }
                .meta-section { margin-bottom: 30px; }
                .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px; }
                .meta-item { padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 3px; }
                .images-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px; }
                .image-card { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: #fff; }
                .image-card img { width: 100%; height: 200px; object-fit: contain; background: #f8f9fa; }
                .image-card-content { padding: 15px; }
                .image-card h3 { margin: 0 0 10px 0; }
                .image-card .meta-summary { font-size: 12px; color: #666; }
            ');
            
            // Scripts pour l'interface
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    // Gestion des onglets
                    $(".nav-tab").click(function(e) {
                        e.preventDefault();
                        var target = $(this).attr("href");
                        $(".nav-tab").removeClass("nav-tab-active");
                        $(this).addClass("nav-tab-active");
                        $(".tab-content").removeClass("active");
                        $(target).addClass("active");
                    });
                    
                    // Calculs automatiques
                    $("#telescope_aperture, #telescope_focal_length").on("input", function() {
                        var aperture = parseFloat($("#telescope_aperture").val()) || 0;
                        var focal = parseFloat($("#telescope_focal_length").val()) || 0;
                        if (aperture > 0 && focal > 0) {
                            var ratio = (focal / aperture).toFixed(1);
                            $("#telescope_focal_ratio").val("f/" + ratio);
                        }
                    });
                    
                    // Preview des métadonnées
                    $(".astro-metadata-form input, .astro-metadata-form textarea, .astro-metadata-form select").on("input", function() {
                        updatePreview();
                    });
                });
                
                function updatePreview() {
                    // Mise à jour dynamique du preview
                    var telescope = jQuery("#telescope_brand").val() + " " + jQuery("#telescope_model").val();
                    var camera = jQuery("#camera_brand").val() + " " + jQuery("#camera_model").val();
                    var exposure = jQuery("#lights_count").val() + " x " + jQuery("#lights_exposure").val() + "s";
                    
                    jQuery("#preview-telescope").text(telescope.trim() || "Non spécifié");
                    jQuery("#preview-camera").text(camera.trim() || "Non spécifié");
                    jQuery("#preview-exposure").text(exposure.replace(" x s", "") || "Non spécifié");
                }
            ');
        }
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'AstroFolio',
            'AstroFolio', 
            'manage_options',
            'astrofolio',
            array($this, 'admin_page'),
            'dashicons-camera',
            30
        );
        
        add_submenu_page(
            'astrofolio',
            'Upload Image',
            '📸 Upload Image',
            'edit_posts',
            'astrofolio-upload',
            array($this, 'upload_page')
        );
        
        // NOUVEAU v1.4.7 : Upload Groupé d'images
        add_submenu_page(
            'astrofolio',
            '📤 Upload Groupé d\'Images',
            '📤 Upload Groupé',
            'edit_posts',
            'astrofolio-upload-bulk',
            array($this, 'upload_bulk_page')
        );
        
        add_submenu_page(
            'astrofolio',
            'Gérer les Images',
            '🖼️ Gérer Images',
            'manage_options',
            'astrofolio-manage-images',
            array($this, 'manage_images_page')
        );
        
        add_submenu_page(
            'astrofolio',
            'Métadonnées Images',
            '🔭 Métadonnées',
            'manage_options',
            'astrofolio-metadata',
            array($this, 'metadata_page')
        );
        
        add_submenu_page(
            'astrofolio',
            'Catalogues Astronomiques',
            '🔄 Catalogues',
            'manage_options',
            'astrofolio-catalogs',
            array($this, 'catalogs_page')
        );
        
        add_submenu_page(
            'astrofolio',
            'Gestion Public',
            '🌐 Gestion Public',
            'manage_options',
            'astrofolio-public',
            array($this, 'public_page')
        );
        
        add_submenu_page(
            'astrofolio',
            'Maintenance Système',
            '⚙️ Maintenance',
            'manage_options',
            'astrofolio-maintenance',
            array($this, 'maintenance_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🚀 AstroFolio</h1>
            <div class="notice notice-success">
                <p><strong>✅ Plugin chargé sans erreur !</strong></p>
                <p>Cette version minimale fonctionne. Les fonctionnalités avancées ont été temporairement désactivées pour éviter les erreurs fatales.</p>
            </div>
            
            <h2>État du Plugin</h2>
            <table class="wp-list-table widefat">
                <tr><td><strong>Version:</strong></td><td>1.4.3</td></tr>
                <tr><td><strong>PHP Version:</strong></td><td><?php echo PHP_VERSION; ?></td></tr>
                <tr><td><strong>WordPress Version:</strong></td><td><?php echo get_bloginfo('version'); ?></td></tr>
                <tr><td><strong>Plugin Directory:</strong></td><td><?php echo plugin_dir_path(__FILE__); ?></td></tr>
            </table>
            
            <h3>Prochaines Étapes</h3>
            <p>Une fois cette version stable confirmée, nous pouvons réactiver progressivement les fonctionnalités :</p>
            <ol>
                <li>✅ Plugin de base (cette étape)</li>
                <li>✅ Upload d'images (ajouté !)</li>
                <li>🔄 Catalogues</li>
                <li>🔄 Interface publique</li>
            </ol>
        </div>
        <?php
    }
    
    public function upload_page() {
        // Vérifier les permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('Vous n\'avez pas l\'autorisation d\'accéder à cette page.'));
        }
        
        $message = '';
        $message_type = '';
        
        // Traitement du formulaire
        if (isset($_POST['upload_astro_image']) && check_admin_referer('astro_upload_nonce')) {
            $result = $this->handle_upload();
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
        
        ?>
        <div class="wrap">
            <h1>📸 Upload Image Astrophoto</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('astro_upload_nonce'); ?>
                
                <div style="background: #e8f5e8; padding: 15px; border-radius: 8px; border-left: 4px solid #28a745; margin-bottom: 20px;">
                    <h3>📸 Upload Simplifié</h3>
                    <p>Cette page permet un upload rapide avec les informations essentielles. Pour ajouter des métadonnées techniques détaillées (télescope, caméra, exposition, etc.), utilisez la page <a href="<?php echo admin_url('admin.php?page=astrofolio-metadata'); ?>" class="button button-secondary">🔭 Métadonnées</a> après l'upload.</p>
                </div>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">Fichier Image *</th>
                        <td>
                            <input type="file" name="image_file" accept="image/*" required />
                            <p class="description">JPG, PNG, GIF - Max: <?php echo size_format(wp_max_upload_size()); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Titre *</th>
                        <td><input type="text" name="title" class="regular-text" required placeholder="M31 - Galaxie d'Andromède" /></td>
                    </tr>
                    <tr>
                        <th scope="row">Nom de l'objet céleste</th>
                        <td>
                            <input type="text" name="object_name" id="object_name_input" class="regular-text" 
                                   placeholder="Tapez M31, NGC 224, Andromeda..."
                                   autocomplete="off" />
                            <div id="object_suggestions" class="object-autocomplete-suggestions"></div>
                            <div id="cross_references" class="object-cross-references" style="display:none;"></div>
                            <p class="description">Désignation de catalogue de l'objet photographié. Tapez pour voir les suggestions.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Description</th>
                        <td>
                            <textarea name="description" rows="4" class="large-text" placeholder="Description de votre image d'astrophotographie..."></textarea>
                            <p class="description">Description générale visible dans la galerie publique</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Date de prise de vue</th>
                        <td>
                            <input type="date" name="shooting_date" />
                            <p class="description">Date à laquelle la photo a été prise</p>
                        </td>
                    </tr>
                </table>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border-left: 4px solid #ffc107; margin: 20px 0;">
                    <h4>💡 Après l'upload</h4>
                    <p>Une fois votre image uploadée, vous pourrez :</p>
                    <ul>
                        <li>✅ Ajouter des <strong>métadonnées techniques complètes</strong> (télescope, caméra, exposition, etc.)</li>
                        <li>✅ Associer l'image à des <strong>objets de catalogues</strong> automatiquement</li>
                        <li>✅ Configurer les <strong>paramètres d'affichage public</strong></li>
                    </ul>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=astrofolio-metadata'); ?>" class="button">🔭 Gérer les Métadonnées</a>
                        <a href="<?php echo admin_url('admin.php?page=astrofolio-catalogs'); ?>" class="button">🔄 Gérer les Catalogues</a>
                        <a href="<?php echo admin_url('admin.php?page=astrofolio-public'); ?>" class="button">🌐 Configuration Publique</a>
                    </p>
                </div>
                
                <?php submit_button('📸 Uploader l\'Image', 'primary', 'upload_astro_image'); ?>
            </form>
        </div>
        <?php
    }
    
    private function handle_upload() {
        if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'Erreur lors de l\'upload du fichier.');
        }
        
        $uploaded_file = $_FILES['image_file'];
        $title = sanitize_text_field($_POST['title'] ?? 'Image Astrophoto');
        
        // Upload via WordPress
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $movefile = wp_handle_upload($uploaded_file, array('test_form' => false));
        
        if ($movefile && !isset($movefile['error'])) {
            // Créer l'attachment
            $attachment = array(
                'guid'           => $movefile['url'],
                'post_mime_type' => $movefile['type'],
                'post_title'     => $title,
                'post_content'   => sanitize_textarea_field($_POST['description'] ?? ''),
                'post_status'    => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $movefile['file']);
            
            if (!is_wp_error($attachment_id)) {
                // Générer les métadonnées
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attachment_id, $movefile['file']);
                wp_update_attachment_metadata($attachment_id, $attach_data);
                
                // Sauvegarder les métadonnées astro basiques
                $astro_fields = array('object_name', 'shooting_date', 'coordinates', 'telescope', 'camera', 'description');
                foreach ($astro_fields as $field) {
                    if (!empty($_POST[$field])) {
                        update_post_meta($attachment_id, 'astro_' . $field, sanitize_text_field($_POST[$field]));
                    }
                }
                
                // Marquer comme image AstroFolio
                update_post_meta($attachment_id, '_astrofolio_image', true);
                
                // Créer une entrée de base dans la table des métadonnées
                $this->ensure_metadata_entry($attachment_id);
                
                return array(
                    'success' => true,
                    'message' => '🎉 Image "' . $title . '" uploadée avec succès ! ID: ' . $attachment_id . ' - Vous pouvez maintenant ajouter des métadonnées techniques détaillées.'
                );
            }
        }
        
        return array('success' => false, 'message' => 'Erreur lors de la création de l\'attachment.');
    }
    
    /**
     * Page d'upload groupé d'images - NOUVEAU v1.4.7
     * 
     * Interface permettant de sélectionner et uploader plusieurs images
     * simultanément avec application de métadonnées communes
     * 
     * @since 1.4.7
     * @return void
     */
    public function upload_bulk_page() {
        // Vérifier les permissions
        if (!current_user_can('edit_posts')) {
            wp_die(__('Vous n\'avez pas l\'autorisation d\'accéder à cette page.'));
        }
        
        ?>
        <div class="wrap">
            <h1>📤 Upload Groupé d'Images Astro</h1>
            <p>Uploadez plusieurs images simultanément avec des métadonnées communes.</p>
            
            <div id="bulk-upload-messages"></div>
            
            <div class="astro-bulk-upload-container">
                
                <!-- Zone de sélection de fichiers -->
                <div class="bulk-upload-section">
                    <h2>📁 Sélection des Fichiers</h2>
                    <div id="bulk-file-drop-zone" class="bulk-file-drop-zone">
                        <div class="drop-zone-content">
                            <span class="dashicons dashicons-upload"></span>
                            <p><strong>Glissez-déposez vos images ici</strong></p>
                            <p>ou</p>
                            <label for="bulk-file-input" class="button button-primary">
                                📂 Sélectionner des fichiers
                            </label>
                            <input type="file" id="bulk-file-input" multiple accept="image/*" style="display: none;">
                            <p class="description">
                                Formats acceptés : JPG, PNG, GIF, WebP<br>
                                Taille max par fichier : <?php echo size_format(wp_max_upload_size()); ?><br>
                                Maximum 20 fichiers par envoi
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Liste des fichiers sélectionnés -->
                <div id="selected-files-section" class="bulk-upload-section" style="display: none;">
                    <h3>📋 Fichiers Sélectionnés</h3>
                    <div id="selected-files-list"></div>
                    <div class="files-actions">
                        <button type="button" id="clear-files" class="button">🗑️ Vider la sélection</button>
                        <span id="files-count">0 fichier(s) sélectionné(s)</span>
                    </div>
                </div>
                
                <!-- Formulaire de métadonnées communes -->
                <form id="bulk-upload-form" class="bulk-upload-section" style="display: none;">
                    <h3>📝 Métadonnées Communes</h3>
                    <p class="description">Ces informations seront appliquées à toutes les images uploadées.</p>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="bulk_description">Description commune</label>
                            <textarea id="bulk_description" name="description" rows="3" 
                                placeholder="Description qui s'appliquera à toutes les images..."></textarea>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="bulk_object_name">Nom de l'objet</label>
                            <input type="text" id="bulk_object_name" name="object_name" 
                                placeholder="M31, NGC 7000... (si toutes les images sont du même objet)">
                        </div>
                        
                        <div class="form-field">
                            <label for="bulk_location">Lieu d'observation</label>
                            <input type="text" id="bulk_location" name="location" 
                                placeholder="Observatoire, ville, pays...">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field">
                            <label for="bulk_telescope">Télescope</label>
                            <input type="text" id="bulk_telescope" name="telescope" 
                                placeholder="Celestron EdgeHD 8, Takahashi FSQ-106...">
                        </div>
                        
                        <div class="form-field">
                            <label for="bulk_camera_name">Caméra</label>
                            <input type="text" id="bulk_camera_name" name="camera_name" 
                                placeholder="Canon EOS R, ZWO ASI2600MC...">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" id="start-bulk-upload" class="button button-primary button-large">
                            🚀 Démarrer l'Upload Groupé
                        </button>
                    </div>
                </form>
                
                <!-- Barre de progression et résultats -->
                <div id="upload-progress-section" style="display: none;">
                    <h3>⏳ Progression de l'Upload</h3>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-fill" id="upload-progress-fill"></div>
                        </div>
                        <div class="progress-text">
                            <span id="progress-current">0</span> / <span id="progress-total">0</span> fichiers
                        </div>
                    </div>
                    <div id="upload-results"></div>
                </div>
            </div>
        </div>
        
        <?php wp_nonce_field('astro_admin_nonce', 'astro_nonce'); ?>
        
        <!-- CSS pour l'upload groupé -->
        <style>
        .astro-bulk-upload-container { max-width: 900px; margin: 0; }
        .bulk-upload-section { background: #fff; border: 1px solid #c3c4c7; border-radius: 6px; padding: 25px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); }
        .bulk-file-drop-zone { border: 3px dashed #007cba; border-radius: 12px; background: linear-gradient(45deg, #f8f9fa, #ffffff); text-align: center; padding: 80px 20px; transition: all 0.3s ease; cursor: pointer; }
        .bulk-file-drop-zone:hover, .bulk-file-drop-zone.dragover { background: linear-gradient(45deg, #e3f2fd, #f0f9ff); border-color: #0073aa; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0, 115, 170, 0.15); }
        .drop-zone-content .dashicons { font-size: 64px; color: #007cba; margin-bottom: 20px; display: block; }
        .form-row { display: flex; gap: 20px; margin-bottom: 20px; }
        .form-field { flex: 1; }
        .form-field label { display: block; font-weight: 600; margin-bottom: 8px; color: #1d2327; }
        .form-field input, .form-field textarea { width: 100%; padding: 10px 12px; border: 1px solid #c3c4c7; border-radius: 4px; }
        .form-actions { text-align: center; padding-top: 25px; border-top: 1px solid #c3c4c7; }
        .progress-bar { width: 100%; height: 24px; background: #f0f0f1; border-radius: 12px; overflow: hidden; margin-bottom: 15px; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #00a32a, #007cba); width: 0%; transition: width 0.4s ease; }
        .progress-text { text-align: center; font-weight: 600; font-size: 16px; color: #1d2327; }
        .file-item { display: flex; justify-content: space-between; align-items: flex-start; padding: 16px; border: 1px solid #c3c4c7; border-radius: 6px; margin-bottom: 10px; background: #f9f9f9; }
        .files-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid #c3c4c7; }
        @media (max-width: 768px) { .form-row { flex-direction: column; gap: 15px; } }
        </style>
        
        <!-- JavaScript pour l'upload groupé -->
        <script>
        jQuery(document).ready(function($) {
            // Configuration AJAX
            const ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
            
            let selectedFiles = [];
            const MAX_FILES = 20;
            
            // Configuration de la zone de drag & drop
            const dropZone = $('#bulk-file-drop-zone');
            
            dropZone.on('dragover dragenter', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });
            
            dropZone.on('dragleave', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
            });
            
            dropZone.on('drop', function(e) {
                e.preventDefault();
                $(this).removeClass('dragover');
                const files = e.originalEvent.dataTransfer.files;
                handleFileSelection(files);
            });
            
            dropZone.on('click', function() {
                $('#bulk-file-input').click();
            });
            
            $('#bulk-file-input').on('change', function() {
                handleFileSelection(this.files);
            });
            
            // Gestion des fichiers sélectionnés
            function handleFileSelection(files) {
                if (!files || files.length === 0) return;
                
                if (selectedFiles.length + files.length > MAX_FILES) {
                    alert('Trop de fichiers. Maximum ' + MAX_FILES + ' fichiers autorisés.');
                    return;
                }
                
                Array.from(files).forEach(file => {
                    if (validateFile(file)) {
                        const exists = selectedFiles.some(f => f.name === file.name && f.size === file.size);
                        if (!exists) {
                            selectedFiles.push(file);
                        }
                    }
                });
                
                updateSelectedFilesList();
                updateUIVisibility();
            }
            
            function validateFile(file) {
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                const maxSize = <?php echo wp_max_upload_size(); ?>;
                
                if (!allowedTypes.includes(file.type.toLowerCase())) {
                    alert('Type de fichier non autorisé: ' + file.name);
                    return false;
                }
                
                if (file.size > maxSize) {
                    alert('Fichier trop volumineux: ' + file.name);
                    return false;
                }
                
                return true;
            }
            
            function updateSelectedFilesList() {
                const container = $('#selected-files-list');
                container.empty();
                
                selectedFiles.forEach((file, index) => {
                    const fileItem = $('<div class="file-item" data-index="' + index + '">' +
                        '<div class="file-info">' +
                            '<span class="dashicons dashicons-format-image"></span>' +
                            '<div class="file-details">' +
                                '<div class="file-name">' + file.name + '</div>' +
                                '<div class="file-size">' + formatFileSize(file.size) + ' • ' + file.type + '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="file-actions">' +
                            '<button type="button" class="button-link remove-file" data-index="' + index + '">' +
                                '<span class="dashicons dashicons-no"></span>' +
                            '</button>' +
                        '</div>' +
                    '</div>');
                    container.append(fileItem);
                });
                
                $('#files-count').text(selectedFiles.length + ' fichier(s) sélectionné(s)');
            }
            
            function updateUIVisibility() {
                if (selectedFiles.length > 0) {
                    $('#selected-files-section').show();
                    $('#bulk-upload-form').show();
                } else {
                    $('#selected-files-section').hide();
                    $('#bulk-upload-form').hide();
                }
            }
            
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
            }
            
            // Gestionnaires d'événements
            $(document).on('click', '.remove-file', function() {
                const index = parseInt($(this).data('index'));
                selectedFiles.splice(index, 1);
                updateSelectedFilesList();
                updateUIVisibility();
            });
            
            $('#clear-files').on('click', function() {
                selectedFiles = [];
                updateSelectedFilesList();
                updateUIVisibility();
                $('#bulk-file-input').val('');
            });
            
            // Soumission du formulaire - Version complète
            $('#bulk-upload-form').on('submit', function(e) {
                e.preventDefault();
                if (selectedFiles.length === 0) {
                    alert('Aucun fichier sélectionné.');
                    return;
                }
                
                // Démarrer l'upload
                startBulkUpload();
            });
            
            // Fonction d'upload groupé
            function startBulkUpload() {
                // Test de connectivité AJAX d'abord
                console.log('Test de connectivité AJAX...');
                console.log('URL AJAX:', ajaxurl);
                
                // Afficher la section de progression
                $('#bulk-upload-form').hide();
                $('#upload-progress-section').show();
                
                // Initialiser la progression
                $('#progress-current').text('0');
                $('#progress-total').text(selectedFiles.length);
                $('#upload-progress-fill').css('width', '0%');
                $('#upload-results').empty();
                
                // Préparer FormData
                const formData = new FormData();
                
                // Ajouter tous les fichiers
                selectedFiles.forEach((file, index) => {
                    formData.append('images[]', file);
                    // Titre par défaut basé sur le nom de fichier
                    const title = file.name.replace(/\.[^/.]+$/, "");
                    formData.append('titles[]', title);
                });
                
                // Ajouter les métadonnées communes
                formData.append('description', $('#bulk_description').val());
                formData.append('object_name', $('#bulk_object_name').val());
                formData.append('location', $('#bulk_location').val());
                formData.append('telescope', $('#bulk_telescope').val());
                formData.append('camera_name', $('#bulk_camera_name').val());
                
                // Ajouter les données WordPress
                formData.append('action', 'astro_upload_bulk_images');
                const nonceValue = $('#astro_nonce').val();
                console.log('Nonce utilisé:', nonceValue);
                formData.append('nonce', nonceValue);
                
                // Debug: afficher le contenu du FormData
                console.log('FormData préparé:', {
                    files: selectedFiles.length,
                    action: 'astro_upload_bulk_images',
                    nonce: nonceValue
                });
                
                // Envoyer via AJAX
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    timeout: 300000, // 5 minutes timeout
                    xhr: function() {
                        const xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener('progress', function(evt) {
                            if (evt.lengthComputable) {
                                const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                                $('#upload-progress-fill').css('width', percentComplete + '%');
                                $('.progress-text').html('Upload en cours... ' + percentComplete + '%');
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        console.log('Réponse AJAX:', response);
                        if (response.success) {
                            $('#upload-progress-fill').css('width', '100%');
                            $('#progress-current').text(selectedFiles.length);
                            $('.progress-text').html('Upload terminé !');
                            
                            // Afficher les résultats
                            displayResults(response.data);
                        } else {
                            console.error('Erreur serveur:', response);
                            displayError(response.data ? response.data.message : 'Erreur serveur inconnue');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Erreur AJAX:', {xhr, status, error, responseText: xhr.responseText});
                        let errorMsg = 'Erreur réseau: ' + error;
                        if (xhr.responseText) {
                            try {
                                const errorData = JSON.parse(xhr.responseText);
                                errorMsg += ' - ' + (errorData.data ? errorData.data.message : errorData.message || '');
                            } catch(e) {
                                errorMsg += ' - ' + xhr.responseText.substring(0, 200);
                            }
                        }
                        displayError(errorMsg);
                    }
                });
            }
            
            // Afficher les résultats
            function displayResults(data) {
                const resultsDiv = $('#upload-results');
                resultsDiv.empty();
                
                const successCount = data.results.success.length;
                const errorCount = data.results.errors.length;
                
                let summaryClass = 'notice-success';
                if (errorCount > 0 && successCount === 0) {
                    summaryClass = 'notice-error';
                } else if (errorCount > 0) {
                    summaryClass = 'notice-warning';
                }
                
                resultsDiv.append(
                    '<div class="notice ' + summaryClass + '">' +
                        '<p><strong>' + data.message + '</strong></p>' +
                    '</div>'
                );
                
                // Détails des succès
                if (successCount > 0) {
                    let successHtml = '<h4>✅ Fichiers uploadés avec succès :</h4><ul>';
                    data.results.success.forEach(function(item) {
                        successHtml += '<li>' + item.file + ' → ' + item.title + '</li>';
                    });
                    successHtml += '</ul>';
                    resultsDiv.append('<div>' + successHtml + '</div>');
                }
                
                // Détails des erreurs
                if (errorCount > 0) {
                    let errorHtml = '<h4>❌ Erreurs :</h4><ul>';
                    data.results.errors.forEach(function(item) {
                        errorHtml += '<li>' + item.file + ' : ' + item.message + '</li>';
                    });
                    errorHtml += '</ul>';
                    resultsDiv.append('<div>' + errorHtml + '</div>');
                }
                
                // Actions post-upload
                resultsDiv.append(
                    '<div style="margin-top: 20px; text-align: center;">' +
                        '<button type="button" id="reset-upload" class="button">🔄 Nouvel Upload</button> ' +
                        '<a href="admin.php?page=astrofolio-manage-images" class="button button-primary">🖼️ Voir les Images</a> ' +
                        '<a href="upload.php" class="button">📁 Médiathèque WordPress</a>' +
                    '</div>'
                );
                
                // Gestionnaire pour recommencer
                $('#reset-upload').on('click', function() {
                    selectedFiles = [];
                    updateSelectedFilesList();
                    updateUIVisibility();
                    $('#bulk-file-input').val('');
                    $('#bulk-upload-form')[0].reset();
                    $('#upload-progress-section').hide();
                    $('#bulk-upload-form').show();
                    $('#bulk-upload-messages').empty();
                });
            }
            
            // Afficher une erreur
            function displayError(message) {
                $('#upload-results').html(
                    '<div class="notice notice-error">' +
                        '<p><strong>Erreur :</strong> ' + message + '</p>' +
                    '</div>' +
                    '<div style="margin-top: 20px; text-align: center;">' +
                        '<button type="button" onclick="location.reload()" class="button">🔄 Réessayer</button>' +
                    '</div>'
                );
            }
        });
        </script>
        <?php
    }
    
    /**
     * Page de gestion des images uploadées
     */
    public function manage_images_page() {
        $message = '';
        $message_type = '';
        
        // Traitement des actions (suppression, modification)
        if (isset($_POST['action']) && isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'astro_manage_images_nonce')) {
            $action = sanitize_text_field($_POST['action']);
            $image_id = intval($_POST['image_id'] ?? 0);
            
            switch ($action) {
                case 'delete':
                    $result = $this->delete_astro_image($image_id);
                    break;
                case 'update':
                    $result = $this->update_astro_image($image_id);
                    break;
                default:
                    $result = array('success' => false, 'message' => 'Action non reconnue');
            }
            
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        } elseif (isset($_POST['action'])) {
            $message = 'Erreur de sécurité : nonce invalide.';
            $message_type = 'error';
        }
        
        // Récupérer les images AstroFolio
        $images = $this->get_astro_images();
        $total_images = count($images);
        
        // Debug : récupérer aussi tous les attachments récents
        $recent_attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        ?>
        <div class="wrap">
            <h1>🖼️ Gestion des Images d'Astrophotographie</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Section de debug -->
            <details style="margin-bottom: 20px;">
                <summary><strong>🔧 Debug : Attachments récents</strong></summary>
                <div style="background: #f1f1f1; padding: 10px; margin-top: 10px;">
                    <p><strong>Derniers attachments ajoutés :</strong></p>
                    <?php if (empty($recent_attachments)): ?>
                        <p>Aucun attachment trouvé.</p>
                    <?php else: ?>
                        <ul>
                        <?php foreach ($recent_attachments as $att): ?>
                            <li>
                                <strong><?php echo esc_html($att->post_title); ?></strong> 
                                (ID: <?php echo $att->ID; ?>, Date: <?php echo $att->post_date; ?>)
                                <?php
                                $meta_astro = get_post_meta($att->ID, 'astro_object_name', true);
                                $meta_telescope = get_post_meta($att->ID, 'astro_telescope', true);
                                $meta_camera = get_post_meta($att->ID, 'astro_camera', true);
                                $meta_bulk = get_post_meta($att->ID, 'astro_uploaded_bulk', true);
                                echo $meta_astro ? " - Objet: $meta_astro" : '';
                                echo $meta_telescope ? " - Télescope: $meta_telescope" : '';
                                echo $meta_camera ? " - Caméra: $meta_camera" : '';
                                echo $meta_bulk ? " - (Upload groupé)" : '';
                                ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </details>
            
            <div style="display: flex; justify-content: space-between; align-items: center; margin: 20px 0;">
                <div>
                    <p class="description">
                        <strong><?php echo number_format($total_images); ?></strong> image(s) d'astrophotographie trouvée(s)
                    </p>
                </div>
                <div>
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-upload'); ?>" class="button button-primary">
                        ➕ Ajouter une Image
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-metadata'); ?>" class="button">
                        🔭 Métadonnées
                    </a>
                </div>
            </div>
            
            <?php if (empty($images)): ?>
                <div style="text-align: center; padding: 60px 20px; background: #f9f9f9; border-radius: 8px;">
                    <div style="font-size: 4em; margin-bottom: 20px;">📸</div>
                    <h2>Aucune image d'astrophoto trouvée</h2>
                    <p>Commencez par uploader votre première image !</p>
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-upload'); ?>" class="button button-primary">
                        📸 Uploader une Image
                    </a>
                </div>
            <?php else: ?>
                
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select id="bulk-action-selector-top">
                            <option value="-1">Actions groupées</option>
                            <option value="delete">Supprimer</option>
                        </select>
                        <button type="button" class="button action" onclick="processBulkAction()">Appliquer</button>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="check-column">
                                <input type="checkbox" id="cb-select-all-1" onclick="toggleAllCheckboxes(this)">
                            </td>
                            <th class="column-image">Aperçu</th>
                            <th class="column-title">Titre</th>
                            <th class="column-object">Objet</th>
                            <th class="column-date">Date</th>
                            <th class="column-size">Taille</th>
                            <th class="column-actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($images as $image): ?>
                        <?php
                            $image_id = $image->ID;
                            $title = get_the_title($image_id);
                            $object_name = get_post_meta($image_id, 'astro_object_name', true);
                            $shooting_date = get_post_meta($image_id, 'astro_shooting_date', true);
                            $upload_date = get_the_date('d/m/Y H:i', $image_id);
                            $image_url = wp_get_attachment_url($image_id);
                            $thumbnail = wp_get_attachment_image_src($image_id, 'thumbnail')[0];
                            $file_size = size_format(filesize(get_attached_file($image_id)));
                        ?>
                        <tr>
                            <th class="check-column">
                                <input type="checkbox" name="image_ids[]" value="<?php echo $image_id; ?>" class="image-checkbox">
                            </th>
                            <td class="column-image">
                                <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>" 
                                     style="width: 60px; height: 60px; object-fit: contain; border-radius: 4px; cursor: pointer; background: #f8f9fa; border: 1px solid #ddd;"
                                     onclick="openImageModal('<?php echo esc_url($image_url); ?>', '<?php echo esc_attr($title); ?>')">
                            </td>
                            <td class="column-title">
                                <strong><?php echo esc_html($title ?: 'Sans titre'); ?></strong>
                                <br>
                                <small style="color: #666;">ID: <?php echo $image_id; ?></small>
                            </td>
                            <td class="column-object">
                                <?php if ($object_name): ?>
                                    <span style="background: #e8f5e8; padding: 2px 8px; border-radius: 12px; font-size: 0.9em;">
                                        🌟 <?php echo esc_html($object_name); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999;">Non spécifié</span>
                                <?php endif; ?>
                            </td>
                            <td class="column-date">
                                <?php if ($shooting_date): ?>
                                    <strong><?php echo date('d/m/Y', strtotime($shooting_date)); ?></strong><br>
                                    <small style="color: #666;">Prise de vue</small>
                                <?php endif; ?>
                                <br>
                                <small style="color: #999;">Upload: <?php echo $upload_date; ?></small>
                            </td>
                            <td class="column-size">
                                <?php echo $file_size; ?>
                            </td>
                            <td class="column-actions">
                                <button type="button" class="button button-small" 
                                        onclick="editImage(<?php echo $image_id; ?>, '<?php echo esc_js($title); ?>', '<?php echo esc_js($object_name); ?>', '<?php echo esc_js($shooting_date); ?>')">
                                    ✏️ Modifier
                                </button>
                                <button type="button" class="button button-small button-link-delete" 
                                        onclick="deleteImage(<?php echo $image_id; ?>, '<?php echo esc_js($title); ?>')"
                                        style="color: #dc3232;">
                                    🗑️ Supprimer
                                </button>
                                <br>
                                <a href="<?php echo $image_url; ?>" target="_blank" class="button button-small" style="margin-top: 4px;">
                                    👁️ Voir
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <span class="displaying-num"><?php echo number_format($total_images); ?> élément(s)</span>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
        
        <!-- Modal pour l'aperçu des images -->
        <div id="image-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8);" onclick="closeImageModal()">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%;">
                <img id="modal-image" src="" alt="" style="max-width: 100%; max-height: 100%; border-radius: 8px;">
                <div id="modal-title" style="color: white; text-align: center; margin-top: 10px; font-size: 1.2em;"></div>
            </div>
            <div style="position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer;" onclick="closeImageModal()">×</div>
        </div>
        
        <!-- Modal pour l'édition -->
        <div id="edit-modal" style="display: none; position: fixed; z-index: 999999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7);">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 12px; width: 500px; max-width: 90%;">
                <h2>✏️ Modifier l'Image</h2>
                <form id="edit-form">
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_manage_images_nonce'); ?>">
                    <input type="hidden" id="edit-image-id" name="image_id">
                    <input type="hidden" name="action" value="update">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="edit-title">Titre</label></th>
                            <td><input type="text" id="edit-title" name="title" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th><label for="edit-object">Objet céleste</label></th>
                            <td>
                                <input type="text" id="edit-object" name="object_name" class="regular-text" 
                                       placeholder="Tapez M31, NGC 224, Andromeda..." autocomplete="off">
                                <div id="edit_object_suggestions" class="object-autocomplete-suggestions"></div>
                                <div id="edit_cross_references" class="object-cross-references" style="display:none;"></div>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit-date">Date de prise</label></th>
                            <td><input type="date" id="edit-date" name="shooting_date"></td>
                        </tr>
                    </table>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" class="button" onclick="closeEditModal()">Annuler</button>
                        <button type="submit" class="button button-primary">💾 Enregistrer</button>
                    </div>
                </form>
            </div>
        </div>
        
        <style>
        .column-image { width: 80px; }
        .column-title { width: 200px; }
        .column-object { width: 150px; }
        .column-date { width: 120px; }
        .column-size { width: 80px; }
        .column-actions { width: 140px; }
        </style>
        
        <script>
        function openImageModal(imageUrl, title) {
            document.getElementById('modal-image').src = imageUrl;
            document.getElementById('modal-title').textContent = title;
            document.getElementById('image-modal').style.display = 'block';
        }
        
        function closeImageModal() {
            document.getElementById('image-modal').style.display = 'none';
        }
        
        function editImage(id, title, object, date) {
            document.getElementById('edit-image-id').value = id;
            document.getElementById('edit-title').value = title;
            document.getElementById('edit-object').value = object;
            document.getElementById('edit-date').value = date;
            document.getElementById('edit-modal').style.display = 'block';
        }
        
        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
        }
        
        function deleteImage(id, title) {
            if (confirm('Êtes-vous sûr de vouloir supprimer l\'image "' + title + '" ?\n\nCette action est irréversible.')) {
                const form = document.createElement('form');
                form.method = 'post';
                form.innerHTML = `
                    <?php wp_nonce_field('astro_manage_images_nonce'); ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="image_id" value="{$id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleAllCheckboxes(source) {
            const checkboxes = document.querySelectorAll('.image-checkbox');
            checkboxes.forEach(cb => cb.checked = source.checked);
        }
        
        function processBulkAction() {
            const action = document.getElementById('bulk-action-selector-top').value;
            const checkedBoxes = document.querySelectorAll('.image-checkbox:checked');
            
            if (action === '-1') {
                alert('Veuillez sélectionner une action.');
                return;
            }
            
            if (checkedBoxes.length === 0) {
                alert('Veuillez sélectionner au moins une image.');
                return;
            }
            
            if (action === 'delete') {
                if (confirm('Êtes-vous sûr de vouloir supprimer ' + checkedBoxes.length + ' image(s) ?\n\nCette action est irréversible.')) {
                    checkedBoxes.forEach(cb => {
                        const form = document.createElement('form');
                        form.method = 'post';
                        form.innerHTML = `
                            <?php wp_nonce_field('astro_manage_images_nonce'); ?>
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="image_id" value="{$cb.value}">
                        `;
                        document.body.appendChild(form);
                        form.submit();
                    });
                }
            }
        }
        
        // Traitement du formulaire d'édition
        document.getElementById('edit-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            submitButton.innerHTML = '⏳ Enregistrement...';
            submitButton.disabled = true;
            
            const formData = new FormData(this);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Erreur réseau');
                }
                return response.text();
            })
            .then(() => {
                closeEditModal();
                location.reload();
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la mise à jour. Veuillez réessayer.');
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
            });
        });
        </script>
        <?php
    }
    
    /**
     * Récupérer toutes les images d'astrophotographie
     */
    private function get_astro_images() {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'astro_object_name',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_shooting_date',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_telescope',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_camera',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return get_posts($args);
    }
    
    /**
     * Récupérer TOUTES les images (pour le reset complet)
     */
    private function get_all_astro_images() {
        global $wpdb;
        
        // Méthode 1: Images avec métadonnées astro
        $images_with_meta = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'astro_object_name',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_shooting_date',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_telescope',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_camera',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        // Méthode 2: Images dans le répertoire astrofolio ou avec nom contenant astro
        $upload_dir = wp_upload_dir();
        $astro_images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wp_attached_file',
                    'value' => 'astrofolio',
                    'compare' => 'LIKE'
                ),
                array(
                    'key' => '_wp_attached_file',
                    'value' => 'astro',
                    'compare' => 'LIKE'
                )
            )
        ));
        
        // Méthode 3: Recherche par titre contenant des termes d'astro
        $title_search = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            's' => 'M31 M42 NGC IC astro astrophoto telescope'
        ));
        
        // Combiner toutes les images trouvées
        $all_images = array();
        $image_ids = array();
        
        foreach (array_merge($images_with_meta, $astro_images, $title_search) as $image) {
            if (!in_array($image->ID, $image_ids)) {
                $all_images[] = $image;
                $image_ids[] = $image->ID;
            }
        }
        
        return $all_images;
    }
    
    /**
     * Supprimer une image d'astrophotographie
     */
    private function delete_astro_image($image_id) {
        if (!$image_id || !current_user_can('delete_posts')) {
            return array('success' => false, 'message' => 'Permission insuffisante pour supprimer cette image.');
        }
        
        $attachment = get_post($image_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return array('success' => false, 'message' => 'Image non trouvée.');
        }
        
        $title = get_the_title($image_id);
        
        // Supprimer l'attachment et le fichier
        $deleted = wp_delete_attachment($image_id, true);
        
        if ($deleted) {
            return array('success' => true, 'message' => "Image \"$title\" supprimée avec succès.");
        } else {
            return array('success' => false, 'message' => "Erreur lors de la suppression de l'image \"$title\".");
        }
    }
    
    /**
     * Mettre à jour les métadonnées d'une image
     */
    private function update_astro_image($image_id) {
        if (!$image_id || !current_user_can('edit_posts')) {
            return array('success' => false, 'message' => 'Permission insuffisante pour modifier cette image.');
        }
        
        $attachment = get_post($image_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return array('success' => false, 'message' => 'Image non trouvée.');
        }
        
        $updated_fields = array();
        
        // Mettre à jour le titre
        $new_title = sanitize_text_field($_POST['title'] ?? '');
        if ($new_title && $new_title !== $attachment->post_title) {
            $result = wp_update_post(array(
                'ID' => $image_id,
                'post_title' => $new_title
            ));
            if ($result) {
                $updated_fields[] = 'titre';
            }
        }
        
        // Mettre à jour les métadonnées
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        $shooting_date = sanitize_text_field($_POST['shooting_date'] ?? '');
        
        $current_object = get_post_meta($image_id, 'astro_object_name', true);
        $current_date = get_post_meta($image_id, 'astro_shooting_date', true);
        
        if ($object_name !== $current_object) {
            if (empty($object_name)) {
                delete_post_meta($image_id, 'astro_object_name');
                $updated_fields[] = 'objet céleste supprimé';
            } else {
                update_post_meta($image_id, 'astro_object_name', $object_name);
                $updated_fields[] = 'objet céleste';
            }
        }
        
        if ($shooting_date !== $current_date) {
            if (empty($shooting_date)) {
                delete_post_meta($image_id, 'astro_shooting_date');
                $updated_fields[] = 'date supprimée';
            } else {
                update_post_meta($image_id, 'astro_shooting_date', $shooting_date);
                $updated_fields[] = 'date de prise';
            }
        }
        
        if (empty($updated_fields)) {
            return array('success' => true, 'message' => 'Aucune modification détectée.');
        }
        
        $final_title = $new_title ?: $attachment->post_title;
        return array('success' => true, 'message' => "Image \"$final_title\" mise à jour (" . implode(', ', $updated_fields) . ").");
    }
    
    /**
     * Page de maintenance système
     */
    public function maintenance_page() {
        $message = '';
        $message_type = '';
        
        // Traitement des actions de maintenance
        if (isset($_POST['maintenance_action']) && check_admin_referer('astro_maintenance_nonce')) {
            $action = sanitize_text_field($_POST['maintenance_action']);
            
            switch ($action) {
                case 'clear_cache':
                    $result = $this->clear_all_cache();
                    break;
                case 'reset_all_photos':
                    $result = $this->reset_all_photos();
                    break;
                case 'clean_metadata':
                    $result = $this->clean_orphaned_metadata();
                    break;
                case 'optimize_database':
                    $result = $this->optimize_database();
                    break;
                case 'reset_catalogs':
                    $result = $this->reset_catalog_data();
                    break;
                case 'clear_logs':
                    $result = $this->clear_error_logs();
                    break;
                case 'rebuild_thumbnails':
                    $result = $this->rebuild_all_thumbnails();
                    break;
                case 'reset_settings':
                    $result = $this->reset_plugin_settings();
                    break;
                case 'refresh_permalinks':
                    $result = array('success' => true, 'message' => $this->force_rewrite_rules_refresh());
                    break;
                case 'recover_lost_images':
                    $result = $this->recover_lost_images_batch();
                    break;
                case 'scan_recovery_candidates':
                    $result = $this->scan_recovery_candidates();
                    break;
                default:
                    $result = array('success' => false, 'message' => 'Action non reconnue');
            }
            
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
        
        // Récupérer les statistiques du système
        $stats = $this->get_maintenance_stats();
        
        ?>
        <div class="wrap">
            <h1>⚙️ Maintenance Système</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Statistiques système -->
            <div class="postbox">
                <h2 class="hndle"><span>📊 Statistiques Système</span></h2>
                <div class="inside">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px;">
                        <div style="text-align: center; padding: 15px; background: #f0f8ff; border-radius: 8px;">
                            <div style="font-size: 2em; color: #2271b1;">📸</div>
                            <strong><?php echo number_format($stats['total_images']); ?></strong>
                            <div>Images d'astrophoto</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f0fff0; border-radius: 8px;">
                            <div style="font-size: 2em; color: #00a32a;">🌟</div>
                            <strong><?php echo number_format($stats['catalog_objects']); ?></strong>
                            <div>Objets catalogués</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #fff8f0; border-radius: 8px;">
                            <div style="font-size: 2em; color: #dba617;">💾</div>
                            <strong><?php echo $stats['db_size']; ?></strong>
                            <div>Taille base de données</div>
                        </div>
                        <div style="text-align: center; padding: 15px; background: #f8f0ff; border-radius: 8px;">
                            <div style="font-size: 2em; color: #8344c5;">🗂️</div>
                            <strong><?php echo $stats['total_attachments']; ?></strong>
                            <div>Total attachments</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Récupération d'Images Perdues -->
            <div class="postbox">
                <h2 class="hndle"><span>🔧 Récupération d'Images Perdues</span></h2>
                <div class="inside">
                    <?php
                    // Scan rapide des images candidates
                    $candidate_count = $this->get_recovery_candidate_count();
                    ?>
                    
                    <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 20px; margin: 20px 0;">
                        <h3 style="margin-top: 0; color: #856404;">📋 Diagnostic Rapide</h3>
                        <p><strong><?php echo $candidate_count; ?> image(s)</strong> pourraient être des photos d'astrophotographie non intégrées dans AstroFolio.</p>
                        
                        <div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: center; margin-top: 15px;">
                            <form method="post" style="display: inline;">
                                <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                <input type="hidden" name="maintenance_action" value="scan_recovery_candidates">
                                <button type="submit" class="button">
                                    🔍 Analyser en détail
                                </button>
                            </form>
                            
                            <?php if ($candidate_count > 0): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="recover_lost_images">
                                    <button type="submit" class="button button-primary" 
                                            onclick="return confirm('Récupérer automatiquement les images détectées comme astrophotographie ?\\n\\nImages candidates: <?php echo $candidate_count; ?>')">
                                        🚀 Récupération Automatique
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <!-- Bouton de diagnostic avancé -->
                            <details style="margin-left: 20px;">
                                <summary style="cursor: pointer; color: #0073aa;">🔧 Debug</summary>
                                <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                                    <p><strong>Tests de diagnostic :</strong></p>
                                    <p><strong>Total images WordPress :</strong> <?php echo wp_count_posts('attachment')->inherit; ?></p>
                                    <p><strong>Images déjà marquées AstroFolio :</strong> <?php 
                                        $marked = get_posts([
                                            'post_type' => 'attachment',
                                            'post_status' => 'inherit',
                                            'posts_per_page' => -1,
                                            'fields' => 'ids',
                                            'meta_query' => [['key' => '_astrofolio_image', 'compare' => 'EXISTS']]
                                        ]);
                                        echo count($marked);
                                    ?></p>
                                    <p><em>Si le diagnostic ne fonctionne pas, utilisez les shortcodes manuels ci-dessous.</em></p>
                                </div>
                            </details>
                        </div>
                        
                        <?php if ($candidate_count > 0): ?>
                            <div style="margin-top: 15px; padding: 15px; background: #e3f2fd; border-radius: 6px;">
                                <h4 style="margin-top: 0;">💡 Mode de récupération</h4>
                                <p><strong>Automatique :</strong> Détecte et récupère les images basées sur les noms de fichiers et titres contenant :</p>
                                <ul style="margin: 5px 0; padding-left: 20px;">
                                    <li><strong>Catalogues :</strong> M31, NGC2024, IC1805, Caldwell 49, SH2-155...</li>
                                    <li><strong>Objets célèbres :</strong> Andromède, Orion, Horsehead, Eagle, Rosette...</li>
                                    <li><strong>Termes astro :</strong> nebula, galaxy, cluster, nébuleuse, étoile...</li>
                                </ul>
                            </div>
                        <?php else: ?>
                            <div style="margin-top: 15px; padding: 15px; background: #d4edda; border-radius: 6px;">
                                <p style="margin: 0; color: #155724;">✅ <strong>Aucune image à récupérer trouvée !</strong> Toutes vos photos d'astrophotographie semblent déjà intégrées.</p>
                            </div>
                        <?php endif; ?>
                        
                        <details style="margin-top: 15px;">
                            <summary style="cursor: pointer; font-weight: bold;">📝 Méthodes alternatives de récupération</summary>
                            <div style="margin-top: 10px; padding: 15px; background: #f8f9fa; border-radius: 6px;">
                                <p><strong>Pour une récupération manuelle précise, utilisez ces shortcodes :</strong></p>
                                <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                                    <tr style="background: #e9ecef;">
                                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Shortcode</th>
                                        <th style="padding: 8px; text-align: left; border: 1px solid #ddd;">Description</th>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><code>[astro_debug_images]</code></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;">Diagnostic complet des images perdues</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><code>[astro_recover_images]</code></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;">Aperçu de la récupération (sans modifications)</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px; border: 1px solid #ddd;"><code>[astro_recover_images mode="execute"]</code></td>
                                        <td style="padding: 8px; border: 1px solid #ddd;">Exécuter la récupération des images détectées</td>
                                    </tr>
                                </table>
                                <p><em>Utilisez ces shortcodes dans n'importe quelle page ou article de votre site.</em></p>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
            
            <!-- Actions de maintenance -->
            <div class="postbox">
                <h2 class="hndle"><span>🛠️ Actions de Maintenance</span></h2>
                <div class="inside">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; margin: 20px;">
                        
                        <!-- Cache et Performance -->
                        <div style="border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                            <h3 style="margin-top: 0; color: #2271b1;">🚀 Cache et Performance</h3>
                            
                            <div style="margin: 15px 0;">
                                <strong>Vider le cache</strong>
                                <p style="color: #666;">Supprime tous les caches WordPress et du plugin</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="clear_cache">
                                    <button type="submit" class="button" onclick="return confirm('Vider tous les caches ?')">
                                        🗑️ Vider Cache
                                    </button>
                                </form>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <strong>Optimiser la base de données</strong>
                                <p style="color: #666;">Optimise et nettoie les tables de la base de données</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="optimize_database">
                                    <button type="submit" class="button" onclick="return confirm('Optimiser la base de données ?')">
                                        ⚡ Optimiser DB
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Images et Médias -->
                        <div style="border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                            <h3 style="margin-top: 0; color: #d63638;">📸 Images et Médias</h3>
                            
                            <div style="margin: 15px 0;">
                                <strong>Reset toutes les photos</strong>
                                <p style="color: #666;">⚠️ Supprime TOUTES les images d'astrophotographie</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="reset_all_photos">
                                    <button type="submit" class="button button-link-delete" 
                                            onclick="return confirm('ATTENTION: Supprimer TOUTES les images d\'astrophotographie ?\\n\\nCette action est IRRÉVERSIBLE !')">
                                        🗑️ RESET Photos
                                    </button>
                                </form>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <strong>Reconstruire les miniatures</strong>
                                <p style="color: #666;">Régénère toutes les miniatures des images</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="rebuild_thumbnails">
                                    <button type="submit" class="button" onclick="return confirm('Reconstruire toutes les miniatures ?')">
                                        🔄 Rebuild Thumbnails
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Catalogues et Données -->
                        <div style="border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                            <h3 style="margin-top: 0; color: #00a32a;">🌟 Catalogues et Données</h3>
                            
                            <div style="margin: 15px 0;">
                                <strong>Reset catalogues</strong>
                                <p style="color: #666;">⚠️ Supprime tous les objets des catalogues astronomiques</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="reset_catalogs">
                                    <button type="submit" class="button button-link-delete" 
                                            onclick="return confirm('Supprimer tous les catalogues astronomiques ?\\n\\nCette action est IRRÉVERSIBLE !')">
                                        🗑️ RESET Catalogues
                                    </button>
                                </form>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <strong>Nettoyer les métadonnées</strong>
                                <p style="color: #666;">Supprime les métadonnées orphelines</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="clean_metadata">
                                    <button type="submit" class="button" onclick="return confirm('Nettoyer les métadonnées orphelines ?')">
                                        🧹 Nettoyer Métadonnées
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Système et Logs -->
                        <div style="border: 1px solid #c3c4c7; border-radius: 8px; padding: 20px;">
                            <h3 style="margin-top: 0; color: #dba617;">🛡️ Système et Logs</h3>
                            
                            <div style="margin: 15px 0;">
                                <strong>Vider les logs</strong>
                                <p style="color: #666;">Supprime tous les logs d'erreur du plugin</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="clear_logs">
                                    <button type="submit" class="button" onclick="return confirm('Vider tous les logs ?')">
                                        🗑️ Vider Logs
                                    </button>
                                </form>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <strong>Reset paramètres plugin</strong>
                                <p style="color: #666;">⚠️ Remet les paramètres par défaut</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="reset_settings">
                                    <button type="submit" class="button button-link-delete" 
                                            onclick="return confirm('Remettre tous les paramètres par défaut ?\\n\\nVos configurations seront perdues !')">
                                        🔄 RESET Paramètres
                                    </button>
                                </form>
                            </div>
                            
                            <div style="margin: 15px 0;">
                                <strong>Actualiser les permaliens</strong>
                                <p style="color: #666;">Force la réactivation des URLs personnalisées du plugin</p>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_maintenance_nonce'); ?>
                                    <input type="hidden" name="maintenance_action" value="refresh_permalinks">
                                    <button type="submit" class="button button-primary">
                                        🔄 Actualiser Permaliens
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Informations système -->
            <div class="postbox">
                <h2 class="hndle"><span>ℹ️ Informations Système</span></h2>
                <div class="inside">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th style="width: 30%;">Paramètre</th>
                                <th>Valeur</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Version Plugin</strong></td>
                                <td><?php echo esc_html($stats['plugin_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Version WordPress</strong></td>
                                <td><?php echo esc_html($stats['wp_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Version PHP</strong></td>
                                <td><?php echo esc_html($stats['php_version']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Mémoire PHP</strong></td>
                                <td><?php echo esc_html($stats['php_memory']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Tables plugin</strong></td>
                                <td><?php echo esc_html($stats['plugin_tables']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Espace disque utilisé</strong></td>
                                <td><?php echo esc_html($stats['disk_usage']); ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-radius: 8px; border: 1px solid #ffd700;">
                <h3 style="color: #8a6d3b; margin-top: 0;">⚠️ Attention</h3>
                <p>Les actions marquées en rouge sont <strong>IRRÉVERSIBLES</strong>. Assurez-vous d'avoir une sauvegarde avant de procéder.</p>
                <p>Il est recommandé d'effectuer ces opérations de maintenance pendant les heures de faible trafic.</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Récupérer les statistiques pour la page de maintenance
     */
    private function get_maintenance_stats() {
        global $wpdb;
        
        // Version du plugin
        $plugin_data = get_plugin_data(__FILE__);
        $plugin_version = $plugin_data['Version'] ?? '1.4.3';
        
        // Images d'astrophoto
        $total_images = $this->count_astro_images();
        
        // Objets catalogués
        $table_name = $wpdb->prefix . 'astro_objects';
        $catalog_objects = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Total attachments
        $total_attachments = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
        );
        
        // Taille de la base de données
        $db_size = $wpdb->get_var("
            SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE()
        ");
        
        // Espace disque utilisé par les uploads
        $upload_dir = wp_upload_dir();
        $disk_usage = 'N/A';
        if (is_dir($upload_dir['basedir'])) {
            $disk_usage = $this->get_directory_size($upload_dir['basedir']);
        }
        
        return array(
            'plugin_version' => $plugin_version,
            'wp_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'php_memory' => ini_get('memory_limit'),
            'total_images' => $total_images,
            'catalog_objects' => $catalog_objects ?: 0,
            'total_attachments' => $total_attachments,
            'db_size' => $db_size ? $db_size . ' MB' : 'N/A',
            'plugin_tables' => $this->count_plugin_tables(),
            'disk_usage' => $disk_usage
        );
    }
    
    /**
     * Compter les tables du plugin
     */
    private function count_plugin_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'astro_objects',
            $wpdb->prefix . 'astro_metadata'
        );
        
        $existing = 0;
        foreach ($tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                $existing++;
            }
        }
        
        return $existing . '/' . count($tables);
    }
    
    /**
     * Calculer la taille d'un répertoire
     */
    private function get_directory_size($directory) {
        $size = 0;
        
        if (is_dir($directory)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            foreach ($files as $file) {
                $size += $file->getSize();
            }
        }
        
        return $this->format_bytes($size);
    }

    /**
     * Formater les octets en unités lisibles
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Vider tous les caches
     */
    private function clear_all_cache() {
        try {
            // Cache WordPress
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            // Cache objet
            if (function_exists('wp_cache_init')) {
                wp_cache_init();
            }
            
            // Transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
            
            // Cache du plugin
            delete_option('astrofolio_cache');
            delete_transient('astrofolio_stats');
            
            return array('success' => true, 'message' => 'Cache vidé avec succès.');
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du vidage du cache: ' . $e->getMessage());
        }
    }
    
    /**
     * Supprimer toutes les photos d'astrophotographie
     */
    private function reset_all_photos() {
        if (!current_user_can('delete_posts')) {
            return array('success' => false, 'message' => 'Permissions insuffisantes.');
        }
        
        try {
            // Utiliser la méthode qui trouve TOUTES les images
            $images = $this->get_all_astro_images();
            $count = 0;
            $errors = array();
            
            if (empty($images)) {
                return array('success' => true, 'message' => 'Aucune image d\'astrophotographie trouvée à supprimer.');
            }
            
            foreach ($images as $image) {
                $title = get_the_title($image->ID);
                if (wp_delete_attachment($image->ID, true)) {
                    $count++;
                } else {
                    $errors[] = "Erreur suppression ID {$image->ID} ({$title})";
                }
            }
            
            // Nettoyer TOUTES les métadonnées astro orphelines
            global $wpdb;
            $cleaned_meta = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'astro_%'");
            $cleaned_astro_meta = $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_astrofolio_%'");
            
            // Nettoyer la table des métadonnées custom si elle existe
            $table_name = $wpdb->prefix . 'astro_metadata';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $wpdb->query("TRUNCATE TABLE $table_name");
            }
            
            $message = "$count image(s) supprimée(s) avec succès.";
            if ($cleaned_meta || $cleaned_astro_meta) {
                $message .= " Métadonnées nettoyées: $cleaned_meta + $cleaned_astro_meta.";
            }
            
            if (!empty($errors)) {
                $message .= " Erreurs: " . implode(', ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " (et " . (count($errors) - 3) . " autres)";
                }
            }
            
            return array('success' => true, 'message' => $message);
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage());
        }
    }
    
    /**
     * Nettoyer les métadonnées orphelines
     */
    private function clean_orphaned_metadata() {
        try {
            global $wpdb;
            
            // Supprimer les métadonnées sans post associé
            $orphaned = $wpdb->query("
                DELETE pm FROM {$wpdb->postmeta} pm
                LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE p.ID IS NULL AND pm.meta_key LIKE 'astro_%'
            ");
            
            return array('success' => true, 'message' => "$orphaned métadonnée(s) orpheline(s) supprimée(s).");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du nettoyage: ' . $e->getMessage());
        }
    }
    
    /**
     * Optimiser la base de données
     */
    private function optimize_database() {
        try {
            global $wpdb;
            
            // Tables du plugin
            $tables = array(
                $wpdb->prefix . 'astro_objects',
                $wpdb->prefix . 'astro_metadata'
            );
            
            $optimized = 0;
            foreach ($tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                    $wpdb->query("OPTIMIZE TABLE $table");
                    $optimized++;
                }
            }
            
            // Nettoyer les révisions anciennes
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision' AND post_date < DATE_SUB(NOW(), INTERVAL 30 DAY)");
            
            // Nettoyer la corbeille
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'trash' AND post_date < DATE_SUB(NOW(), INTERVAL 7 DAY)");
            
            return array('success' => true, 'message' => "$optimized table(s) optimisée(s), anciens contenus nettoyés.");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de l\'optimisation: ' . $e->getMessage());
        }
    }
    
    /**
     * Reset des données de catalogues
     */
    private function reset_catalog_data() {
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => 'Permissions insuffisantes.');
        }
        
        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'astro_objects';
            
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $wpdb->query("TRUNCATE TABLE $table_name");
            
            return array('success' => true, 'message' => "$count objet(s) de catalogues supprimé(s).");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du reset des catalogues: ' . $e->getMessage());
        }
    }
    
    /**
     * Vider les logs d'erreur
     */
    private function clear_error_logs() {
        try {
            // Log PHP
            $php_log = ini_get('error_log');
            if ($php_log && file_exists($php_log)) {
                file_put_contents($php_log, '');
            }
            
            // Logs WordPress
            $wp_content = WP_CONTENT_DIR;
            $log_files = glob($wp_content . '/debug*.log');
            
            $cleared = 0;
            foreach ($log_files as $log_file) {
                if (is_writable($log_file)) {
                    file_put_contents($log_file, '');
                    $cleared++;
                }
            }
            
            // Logs du plugin
            delete_option('astrofolio_error_log');
            
            return array('success' => true, 'message' => "$cleared fichier(s) de log vidé(s).");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du vidage des logs: ' . $e->getMessage());
        }
    }
    
    /**
     * Reconstruire toutes les miniatures
     */
    private function rebuild_all_thumbnails() {
        try {
            $images = $this->get_astro_images();
            $rebuilt = 0;
            
            foreach ($images as $image) {
                $file_path = get_attached_file($image->ID);
                if ($file_path && file_exists($file_path)) {
                    // Supprimer les anciennes miniatures
                    wp_delete_attachment_files($image->ID, array(), array(), $file_path);
                    
                    // Recréer les miniatures
                    $metadata = wp_generate_attachment_metadata($image->ID, $file_path);
                    wp_update_attachment_metadata($image->ID, $metadata);
                    
                    $rebuilt++;
                }
            }
            
            return array('success' => true, 'message' => "$rebuilt miniature(s) reconstruite(s).");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de la reconstruction: ' . $e->getMessage());
        }
    }
    
    /**
     * Reset des paramètres du plugin
     */
    private function reset_plugin_settings() {
        if (!current_user_can('manage_options')) {
            return array('success' => false, 'message' => 'Permissions insuffisantes.');
        }
        
        try {
            // Supprimer toutes les options du plugin
            $options = array(
                'astrofolio_settings',
                'astrofolio_cache',
                'astrofolio_version',
                'astrofolio_db_version',
                'astrofolio_catalogs_imported'
            );
            
            $deleted = 0;
            foreach ($options as $option) {
                if (delete_option($option)) {
                    $deleted++;
                }
            }
            
            // Supprimer les transients
            global $wpdb;
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'astrofolio_%'");
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_astrofolio_%'");
            
            return array('success' => true, 'message' => "Paramètres du plugin réinitialisés ($deleted option(s) supprimée(s)).");
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du reset: ' . $e->getMessage());
        }
    }
    
    /**
     * Récupérer les images récentes pour l'aperçu
     */
    private function get_recent_astro_images($limit = 6) {
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_object_name',
                    'compare' => 'EXISTS'
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        return get_posts($args);
    }
    
    /**
     * Récupérer les objets les plus populaires
     */
    private function get_popular_objects() {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT meta_value as object_name, COUNT(*) as count 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = 'astro_object_name' 
            AND meta_value != '' 
            GROUP BY meta_value 
            ORDER BY count DESC 
            LIMIT 10
        ");
        
        return $results;
    }
    
    /**
     * Sauvegarder les paramètres publics
     */
    private function save_public_settings() {
        try {
            update_option('astro_images_per_page', intval($_POST['astro_images_per_page'] ?? 12));
            update_option('astro_default_columns', intval($_POST['astro_default_columns'] ?? 3));
            update_option('astro_image_quality', sanitize_text_field($_POST['astro_image_quality'] ?? 'large'));
            update_option('astro_show_metadata', isset($_POST['astro_show_metadata']) ? 1 : 0);
            update_option('astro_show_object_info', isset($_POST['astro_show_object_info']) ? 1 : 0);
            update_option('astro_show_shooting_data', isset($_POST['astro_show_shooting_data']) ? 1 : 0);
            update_option('astro_enable_likes', isset($_POST['astro_enable_likes']) ? 1 : 0);
            update_option('astro_enable_comments', isset($_POST['astro_enable_comments']) ? 1 : 0);
            update_option('astro_enable_sharing', isset($_POST['astro_enable_sharing']) ? 1 : 0);
            update_option('astro_lightbox_style', sanitize_text_field($_POST['astro_lightbox_style'] ?? 'simple'));
            
            return array('success' => true, 'message' => '✅ Paramètres enregistrés avec succès !');
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de la sauvegarde: ' . $e->getMessage());
        }
    }
    
    /**
     * Régénérer les miniatures
     */
    private function regenerate_thumbnails() {
        try {
            $images = $this->get_astro_images();
            $regenerated = 0;
            
            foreach ($images as $image) {
                $file_path = get_attached_file($image->ID);
                if ($file_path && file_exists($file_path)) {
                    $metadata = wp_generate_attachment_metadata($image->ID, $file_path);
                    wp_update_attachment_metadata($image->ID, $metadata);
                    $regenerated++;
                }
            }
            
            return array('success' => true, 'message' => "✅ {$regenerated} miniature(s) régénérée(s) avec succès !");
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de la régénération: ' . $e->getMessage());
        }
    }
    
    /**
     * Vider le cache public
     */
    private function clear_public_cache() {
        try {
            // Supprimer les transients de cache
            delete_transient('astrofolio_public_stats');
            delete_transient('astrofolio_popular_objects');
            delete_transient('astrofolio_recent_images');
            
            // Cache WordPress si disponible
            if (function_exists('wp_cache_flush')) {
                wp_cache_flush();
            }
            
            return array('success' => true, 'message' => '✅ Cache vidé avec succès !');
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors du vidage du cache: ' . $e->getMessage());
        }
    }
    
    /**
     * Exporter les paramètres
     */
    private function export_public_settings() {
        try {
            $settings = array(
                'astro_images_per_page' => get_option('astro_images_per_page', 12),
                'astro_default_columns' => get_option('astro_default_columns', 3),
                'astro_image_quality' => get_option('astro_image_quality', 'large'),
                'astro_show_metadata' => get_option('astro_show_metadata', 1),
                'astro_show_object_info' => get_option('astro_show_object_info', 1),
                'astro_show_shooting_data' => get_option('astro_show_shooting_data', 1),
                'astro_enable_likes' => get_option('astro_enable_likes', 1),
                'astro_enable_comments' => get_option('astro_enable_comments', 0),
                'astro_enable_sharing' => get_option('astro_enable_sharing', 1),
                'astro_lightbox_style' => get_option('astro_lightbox_style', 'simple'),
                'export_date' => current_time('Y-m-d H:i:s')
            );
            
            $filename = 'astrofolio-settings-' . date('Y-m-d') . '.json';
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename=' . $filename);
            echo json_encode($settings, JSON_PRETTY_PRINT);
            exit;
            
        } catch (Exception $e) {
            return array('success' => false, 'message' => 'Erreur lors de l\'export: ' . $e->getMessage());
        }
    }
    
    public function metadata_page() {
        $message = '';
        $message_type = '';
        
        // Traitement du formulaire de métadonnées
        if (isset($_POST['save_metadata']) && check_admin_referer('astro_metadata_nonce')) {
            $result = $this->save_image_metadata();
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
        
        // Récupérer toutes les images uploadées via AstroFolio
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'numberposts' => 50,
            'post_status' => 'inherit',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_astrofolio_image',
                    'value' => true,
                    'compare' => '='
                ),
                array(
                    'key' => 'astro_telescope',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_camera',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        ?>
        <div class="wrap">
            <h1>🔭 Gestion des Métadonnées - Style AstroBin</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (empty($images)): ?>
                <div class="notice notice-info">
                    <p><strong>ℹ️ Aucune image AstroFolio trouvée.</strong></p>
                    <p>Seules les images uploadées via <strong>AstroFolio > 📸 Upload Image</strong> apparaissent ici.</p>
                    <p><a href="<?php echo admin_url('admin.php?page=astrofolio-upload'); ?>" class="button button-primary">Uploader votre première image</a></p>
                </div>
                <?php return; ?>
            <?php endif; ?>
            
            <div class="notice notice-success">
                <p>✅ <strong><?php echo count($images); ?> image(s) AstroFolio</strong> trouvée(s). Cliquez sur "✏️ Métadonnées" pour éditer les détails techniques.</p>
            </div>
            
            <div class="images-list">
                <?php foreach ($images as $image): ?>
                    <div class="image-card">
                        <?php echo wp_get_attachment_image($image->ID, 'medium'); ?>
                        <div class="image-card-content">
                            <h3><?php echo esc_html($image->post_title); ?></h3>
                            <p><?php echo esc_html(wp_trim_words($image->post_content, 20)); ?></p>
                            <div class="meta-summary">
                                <?php
                                $telescope = get_post_meta($image->ID, 'astro_telescope', true);
                                $camera = get_post_meta($image->ID, 'astro_camera', true);
                                $exposure = get_post_meta($image->ID, 'astro_exposure_time', true);
                                $date = get_post_meta($image->ID, 'astro_shooting_date', true);
                                
                                if ($telescope) echo "📡 " . esc_html($telescope) . "<br>";
                                if ($camera) echo "📷 " . esc_html($camera) . "<br>";
                                if ($exposure) echo "⏱️ " . esc_html($exposure) . "<br>";
                                if ($date) echo "📅 " . esc_html($date) . "<br>";
                                ?>
                            </div>
                            <button type="button" onclick="openMetadataForm(<?php echo $image->ID; ?>)" class="button button-primary">
                                ✏️ Métadonnées
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Formulaire modal de métadonnées -->
            <div id="metadata-modal" style="display: none;">
                <?php $this->render_metadata_form(); ?>
            </div>
        </div>
        
        <script>
        function openMetadataForm(imageId) {
            // Charger les métadonnées existantes via AJAX
            jQuery.post(ajaxurl, {
                action: 'load_image_metadata',
                image_id: imageId,
                nonce: '<?php echo wp_create_nonce('load_metadata'); ?>'
            }, function(response) {
                if (response.success) {
                    populateForm(response.data);
                    jQuery('#metadata-modal').show();
                }
            });
        }
        
        function populateForm(data) {
            jQuery('#current_image_id').val(data.image_id || '');
            for (let key in data) {
                jQuery('#' + key).val(data[key] || '');
            }
        }
        
        jQuery(document).ready(function($) {
            $('#metadata-modal').on('click', '.close-modal', function() {
                $('#metadata-modal').hide();
            });
        });
        </script>
        <?php
    }
    
    private function render_metadata_form() {
        ?>
        <div class="astro-metadata-form" style="background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); max-width: 1200px; margin: 50px auto; position: fixed; top: 50px; left: 50%; transform: translateX(-50%); z-index: 10000; max-height: 80vh; overflow-y: auto;">
            <button type="button" class="close-modal" style="float: right; background: #dc3232; color: white; border: none; padding: 10px; cursor: pointer; border-radius: 3px;">✕ Fermer</button>
            
            <h2>🔭 Métadonnées Détaillées</h2>
            
            <form method="post">
                <?php wp_nonce_field('astro_metadata_nonce'); ?>
                <input type="hidden" id="current_image_id" name="image_id" value="">
                
                <!-- Onglets -->
                <div class="nav-tab-wrapper">
                    <a href="#tab-equipment" class="nav-tab nav-tab-active">🔭 Équipement</a>
                    <a href="#tab-acquisition" class="nav-tab">📷 Acquisition</a>
                    <a href="#tab-conditions" class="nav-tab">🌤️ Conditions</a>
                    <a href="#tab-processing" class="nav-tab">⚙️ Traitement</a>
                    <a href="#tab-preview" class="nav-tab">👁️ Aperçu</a>
                </div>
                
                <!-- ÉQUIPEMENT -->
                <div id="tab-equipment" class="tab-content active">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>🔭 Télescope</label>
                            <input type="text" id="telescope_brand" name="telescope_brand" placeholder="Marque (Celestron, Sky-Watcher...)">
                            <input type="text" id="telescope_model" name="telescope_model" placeholder="Modèle (EdgeHD 8, Newton 200/1000...)">
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="telescope_aperture" name="telescope_aperture" placeholder="Diamètre (mm)">
                                <input type="number" id="telescope_focal_length" name="telescope_focal_length" placeholder="Focale (mm)">
                                <input type="text" id="telescope_focal_ratio" name="telescope_focal_ratio" placeholder="f/ratio" readonly>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>📷 Caméra</label>
                            <input type="text" id="camera_brand" name="camera_brand" placeholder="Marque (ZWO, QHY, Canon...)">
                            <input type="text" id="camera_model" name="camera_model" placeholder="Modèle (ASI2600MC-Pro, EOS R6...)">
                            <input type="text" id="camera_sensor" name="camera_sensor" placeholder="Capteur (Sony IMX571C...)">
                            <input type="text" id="camera_cooling" name="camera_cooling" placeholder="Refroidissement (-10°C)">
                        </div>
                        
                        <div class="form-group">
                            <label>🎯 Monture</label>
                            <input type="text" id="mount_brand" name="mount_brand" placeholder="Marque (Celestron, Skywatcher...)">
                            <input type="text" id="mount_model" name="mount_model" placeholder="Modèle (EQ6-R Pro, CGX...)">
                        </div>
                        
                        <div class="form-group">
                            <label>🔍 Filtres & Accessoires</label>
                            <input type="text" id="filters" name="filters" placeholder="Filtres (L-RGB, Ha-OIII-SII...)">
                            <input type="text" id="reducer_corrector" name="reducer_corrector" placeholder="Réducteur/Correcteur (0.8x)">
                            <input type="text" id="guiding_camera" name="guiding_camera" placeholder="Caméra de guidage">
                            <input type="text" id="guiding_scope" name="guiding_scope" placeholder="Lunette de guidage">
                        </div>
                    </div>
                </div>
                
                <!-- ACQUISITION -->
                <div id="tab-acquisition" class="tab-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>📅 Informations d'acquisition</label>
                            <input type="text" id="acquisition_dates" name="acquisition_dates" placeholder="Dates (2024-01-15, 2024-01-16)">
                            <input type="text" id="location_name" name="location_name" placeholder="Lieu (Observatoire du Pic du Midi)">
                            <input type="text" id="location_coords" name="location_coords" placeholder="Coordonnées (42.9365, 0.1425)">
                            <input type="number" id="location_altitude" name="location_altitude" placeholder="Altitude (m)">
                        </div>
                        
                        <div class="form-group">
                            <label>💡 Poses Luminance/Couleur</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="lights_count" name="lights_count" placeholder="Nb poses">
                                <input type="number" id="lights_exposure" name="lights_exposure" placeholder="Durée (s)">
                                <input type="text" id="lights_iso_gain" name="lights_iso_gain" placeholder="ISO/Gain">
                            </div>
                            <input type="text" id="filter_details" name="filter_details" placeholder="Détails filtres (Ha: 20x600s, OIII: 15x600s...)">
                        </div>
                        
                        <div class="form-group">
                            <label>🌑 Images de calibration</label>
                            <div style="display: flex; gap: 10px;">
                                <input type="number" id="darks_count" name="darks_count" placeholder="Darks">
                                <input type="number" id="flats_count" name="flats_count" placeholder="Flats">
                                <input type="number" id="bias_count" name="bias_count" placeholder="Bias">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>⚙️ Logiciels</label>
                            <input type="text" id="capture_software" name="capture_software" placeholder="Acquisition (NINA, SGP, MaxIm DL...)">
                            <input type="text" id="autoguiding" name="autoguiding" placeholder="Guidage (PHD2 settings...)">
                        </div>
                    </div>
                </div>
                
                <!-- CONDITIONS -->
                <div id="tab-conditions" class="tab-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>🌤️ Conditions météo</label>
                            <input type="text" id="weather_conditions" name="weather_conditions" placeholder="Conditions (Clear, transparent...)">
                            <input type="text" id="temperature" name="temperature" placeholder="Température (-5°C)">
                            <input type="text" id="humidity" name="humidity" placeholder="Humidité (45%)">
                            <input type="text" id="wind_speed" name="wind_speed" placeholder="Vent (5 km/h)">
                        </div>
                        
                        <div class="form-group">
                            <label>🌌 Conditions d'observation</label>
                            <input type="number" id="bortle_scale" name="bortle_scale" placeholder="Échelle Bortle (1-9)" min="1" max="9">
                            <input type="text" id="seeing" name="seeing" placeholder="Seeing (2 arcsec)">
                            <input type="text" id="moon_illumination" name="moon_illumination" placeholder="Lune (15%)">
                        </div>
                    </div>
                </div>
                
                <!-- TRAITEMENT -->
                <div id="tab-processing" class="tab-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>⚙️ Logiciels de traitement</label>
                            <input type="text" id="stacking_software" name="stacking_software" placeholder="Stacking (PixInsight, DeepSkyStacker...)">
                            <input type="text" id="processing_software" name="processing_software" placeholder="Traitement (PixInsight, Photoshop...)">
                        </div>
                        
                        <div class="form-group">
                            <label>🔧 Étapes de traitement</label>
                            <textarea id="preprocessing_steps" name="preprocessing_steps" rows="3" placeholder="Prétraitement (Calibration, registration, integration...)"></textarea>
                            <textarea id="processing_steps" name="processing_steps" rows="3" placeholder="Traitement (DynamicBackgroundExtraction, HistogramTransformation...)"></textarea>
                            <textarea id="special_techniques" name="special_techniques" rows="2" placeholder="Techniques spéciales (HDR, Drizzle, Deconvolution...)"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>📊 Résultat final</label>
                            <input type="text" id="final_resolution" name="final_resolution" placeholder="Résolution (4096x4096)">
                            <input type="number" id="pixel_scale" name="pixel_scale" placeholder="Échelle pixel (arcsec/pixel)" step="0.01">
                            <input type="text" id="field_of_view" name="field_of_view" placeholder="Champ (2.1° x 1.4°)">
                        </div>
                        
                        <div class="form-group">
                            <label>📝 Notes</label>
                            <textarea id="processing_notes" name="processing_notes" rows="3" placeholder="Notes sur le traitement..."></textarea>
                            <textarea id="acquisition_notes" name="acquisition_notes" rows="3" placeholder="Notes sur l'acquisition..."></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- APERÇU -->
                <div id="tab-preview" class="tab-content">
                    <div class="astro-metadata-preview">
                        <h3>Aperçu des métadonnées</h3>
                        <div class="meta-section">
                            <h4>🔭 Équipement</h4>
                            <div class="meta-grid">
                                <div class="meta-item"><strong>Télescope:</strong> <span id="preview-telescope">Non spécifié</span></div>
                                <div class="meta-item"><strong>Caméra:</strong> <span id="preview-camera">Non spécifié</span></div>
                            </div>
                        </div>
                        
                        <div class="meta-section">
                            <h4>📷 Acquisition</h4>
                            <div class="meta-grid">
                                <div class="meta-item"><strong>Exposition:</strong> <span id="preview-exposure">Non spécifié</span></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="save_metadata" class="button-primary" value="💾 Sauvegarder les Métadonnées">
                </p>
            </form>
        </div>
        <?php
    }
    
    private function save_image_metadata() {
        $image_id = intval($_POST['image_id'] ?? 0);
        if (!$image_id) {
            return array('success' => false, 'message' => 'ID d\'image manquant.');
        }
        
        // Créer la table de métadonnées si elle n'existe pas
        $this->create_metadata_table();
        
        // Champs de métadonnées
        $metadata_fields = array(
            'telescope_brand', 'telescope_model', 'telescope_aperture', 'telescope_focal_length', 'telescope_focal_ratio',
            'camera_brand', 'camera_model', 'camera_sensor', 'camera_cooling',
            'mount_brand', 'mount_model', 'filters', 'reducer_corrector', 'guiding_camera', 'guiding_scope',
            'acquisition_dates', 'location_name', 'location_coords', 'location_altitude',
            'lights_count', 'lights_exposure', 'lights_iso_gain', 'filter_details',
            'darks_count', 'flats_count', 'bias_count',
            'capture_software', 'autoguiding',
            'weather_conditions', 'temperature', 'humidity', 'wind_speed',
            'bortle_scale', 'seeing', 'moon_illumination',
            'stacking_software', 'processing_software',
            'preprocessing_steps', 'processing_steps', 'special_techniques',
            'final_resolution', 'pixel_scale', 'field_of_view',
            'processing_notes', 'acquisition_notes'
        );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        $metadata = array('image_id' => $image_id);
        foreach ($metadata_fields as $field) {
            $metadata[$field] = sanitize_text_field($_POST[$field] ?? '');
        }
        
        // Vérifier si des métadonnées existent
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT image_id FROM $table_name WHERE image_id = %d",
            $image_id
        ));
        
        if ($existing) {
            $result = $wpdb->update($table_name, $metadata, array('image_id' => $image_id));
            $success = $result !== false;
        } else {
            $result = $wpdb->insert($table_name, $metadata);
            $success = $result !== false;
        }
        
        if ($success) {
            // Vider tous les caches liés à cette image
            wp_cache_delete($image_id, 'post_meta');
            wp_cache_delete('astro_metadata_' . $image_id);
            clean_post_cache($image_id);
            
            // Forcer la suppression du cache d'objets si présent
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('astro_metadata');
            }
            
            // Forcer la mise à jour des métadonnées de base dans post_meta aussi
            if (isset($_POST['object_name']) && !empty($_POST['object_name'])) {
                update_post_meta($image_id, 'astro_object_name', sanitize_text_field($_POST['object_name']));
            }
            if (isset($_POST['acquisition_dates']) && !empty($_POST['acquisition_dates'])) {
                update_post_meta($image_id, 'astro_shooting_date', sanitize_text_field($_POST['acquisition_dates']));
            }
            
            return array('success' => true, 'message' => '✅ Métadonnées sauvegardées avec succès ! Visible immédiatement sur le frontend.');
        } else {
            // Debug en cas d'erreur
            $error_msg = '❌ Erreur lors de la sauvegarde des métadonnées.';
            if ($wpdb->last_error) {
                $error_msg .= ' Erreur SQL: ' . $wpdb->last_error;
            }
            return array('success' => false, 'message' => $error_msg);
        }
    }
    
    private function create_metadata_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        // Vérifier si la table existe déjà
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            return; // Table existe déjà
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            image_id bigint(20) NOT NULL,
            telescope_brand varchar(255),
            telescope_model varchar(255),
            telescope_aperture int,
            telescope_focal_length int,
            telescope_focal_ratio varchar(50),
            camera_brand varchar(255),
            camera_model varchar(255),
            camera_sensor varchar(255),
            camera_cooling varchar(100),
            mount_brand varchar(255),
            mount_model varchar(255),
            filters text,
            reducer_corrector varchar(255),
            guiding_camera varchar(255),
            guiding_scope varchar(255),
            acquisition_dates text,
            location_name varchar(255),
            location_coords varchar(100),
            location_altitude int,
            lights_count int,
            lights_exposure int,
            lights_iso_gain varchar(100),
            filter_details text,
            darks_count int,
            flats_count int,
            bias_count int,
            capture_software varchar(255),
            autoguiding text,
            weather_conditions varchar(255),
            temperature varchar(50),
            humidity varchar(50),
            wind_speed varchar(50),
            bortle_scale int,
            seeing varchar(50),
            moon_illumination varchar(50),
            stacking_software varchar(255),
            processing_software varchar(255),
            preprocessing_steps text,
            processing_steps text,
            special_techniques text,
            final_resolution varchar(100),
            pixel_scale decimal(10,3),
            field_of_view varchar(100),
            processing_notes text,
            acquisition_notes text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY image_id (image_id)
        ) $charset_collate;";
        
        // Capturer la sortie de dbDelta pour éviter les messages
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        ob_start();
        dbDelta($sql);
        ob_end_clean();
    }
    
    /**
     * S'assurer qu'une entrée existe dans la table des métadonnées pour une image
     */
    private function ensure_metadata_entry($image_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        // Vérifier si une entrée existe
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT image_id FROM $table_name WHERE image_id = %d",
            $image_id
        ));
        
        if (!$exists) {
            // Créer une entrée de base avec les métadonnées disponibles
            $basic_data = array(
                'image_id' => $image_id,
                'acquisition_dates' => get_post_meta($image_id, 'astro_shooting_date', true) ?: '',
                'telescope_model' => get_post_meta($image_id, 'astro_telescope', true) ?: '',
                'camera_model' => get_post_meta($image_id, 'astro_camera', true) ?: '',
                'location_name' => get_post_meta($image_id, 'astro_location', true) ?: '',
                'processing_notes' => get_post_meta($image_id, 'astro_description', true) ?: ''
            );
            
            $wpdb->insert($table_name, $basic_data);
        }
    }
    
    public function ajax_load_metadata() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'load_metadata')) {
            wp_die('Nonce invalide');
        }
        
        $image_id = intval($_POST['image_id'] ?? 0);
        if (!$image_id) {
            wp_send_json_error('ID image manquant');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        $metadata = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE image_id = %d",
            $image_id
        ), ARRAY_A);
        
        // Si pas de métadonnées en BDD, récupérer depuis post_meta (compatibilité)
        if (!$metadata) {
            $metadata = array(
                'image_id' => $image_id,
                'telescope_brand' => get_post_meta($image_id, 'astro_telescope', true),
                'camera_brand' => get_post_meta($image_id, 'astro_camera', true),
                'lights_exposure' => get_post_meta($image_id, 'astro_exposure_time', true),
                'lights_iso_gain' => get_post_meta($image_id, 'astro_iso_gain', true),
                'location_name' => get_post_meta($image_id, 'astro_location', true),
                'acquisition_dates' => get_post_meta($image_id, 'astro_shooting_date', true),
            );
        }
        
        wp_send_json_success($metadata);
    }
    
    // AJAX pour rechercher dans les catalogues d'objets
    public function ajax_search_catalog_objects() {
        $search = sanitize_text_field($_POST['search'] ?? '');
        
        if (strlen($search) < 2) {
            wp_send_json_success(array());
            return;
        }
        
        $catalog_data = $this->load_catalog_data();
        $results = array();
        
        foreach ($catalog_data as $object) {
            $match = false;
            
            // Recherche dans l'identifiant principal
            if (stripos($object['primary'], $search) !== false) {
                $match = true;
            }
            
            // Recherche dans les alternates
            if (!$match && !empty($object['alternates'])) {
                $alternates = explode(',', $object['alternates']);
                foreach ($alternates as $alt) {
                    if (stripos(trim($alt), $search) !== false) {
                        $match = true;
                        break;
                    }
                }
            }
            
            // Recherche dans le nom commun
            if (!$match && !empty($object['common_name'])) {
                if (stripos($object['common_name'], $search) !== false) {
                    $match = true;
                }
            }
            
            if ($match) {
                $results[] = array(
                    'primary' => $object['primary'],
                    'alternates' => $object['alternates'],
                    'common_name' => $object['common_name'] ?: '',
                    'catalogs' => $object['catalogs'] ?: ''
                );
            }
            
            // Limiter à 10 résultats
            if (count($results) >= 10) {
                break;
            }
        }
        
        wp_send_json_success($results);
    }
    
    // AJAX pour obtenir les références croisées d'un objet
    public function ajax_get_object_cross_references() {
        $object_name = sanitize_text_field($_POST['object_name'] ?? '');
        
        if (empty($object_name)) {
            wp_send_json_success(array());
            return;
        }
        
        $catalog_data = $this->load_catalog_data();
        $cross_refs = array();
        
        foreach ($catalog_data as $object) {
            if (strcasecmp($object['primary'], $object_name) === 0) {
                $cross_refs = array(
                    'primary' => $object['primary'],
                    'alternates' => $object['alternates'] ? explode(',', $object['alternates']) : array(),
                    'common_name' => $object['common_name'] ?: '',
                    'catalogs' => $object['catalogs'] ? explode('|', str_replace(' ', '', $object['catalogs'])) : array()
                );
                break;
            }
            
            // Chercher aussi dans les alternates
            if (!empty($object['alternates'])) {
                $alternates = explode(',', $object['alternates']);
                foreach ($alternates as $alt) {
                    if (strcasecmp(trim($alt), $object_name) === 0) {
                        $cross_refs = array(
                            'primary' => $object['primary'],
                            'alternates' => array_map('trim', $alternates),
                            'common_name' => $object['common_name'] ?: '',
                            'catalogs' => $object['catalogs'] ? explode('|', str_replace(' ', '', $object['catalogs'])) : array()
                        );
                        break 2;
                    }
                }
            }
        }
        
        wp_send_json_success($cross_refs);
    }
    
    // Charger les données du catalogue de références croisées
    private function load_catalog_data() {
        static $catalog_data = null;
        
        if ($catalog_data === null) {
            $catalog_data = array();
            $file_path = plugin_dir_path(__FILE__) . 'data/cross-references_maximum.csv';
            
            if (file_exists($file_path)) {
                $handle = fopen($file_path, 'r');
                $headers = fgetcsv($handle); // Ignorer les en-têtes
                
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 6) {
                        $catalog_data[] = array(
                            'primary' => $row[0],
                            'alternates' => $row[1],
                            'catalogs' => $row[2],
                            'common_name' => $row[3],
                            'match_type' => $row[4],
                            'notes' => $row[5]
                        );
                    }
                }
                
                fclose($handle);
            }
        }
        
        return $catalog_data;
    }
    
    public function catalogs_page() {
        $message = '';
        $message_type = '';
        
        // Traitement des actions sur les catalogues
        if (isset($_POST['import_catalog']) && check_admin_referer('astro_catalog_nonce')) {
            $result = $this->import_catalog_data();
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
        
        // Récupérer les statistiques des catalogues
        $catalog_stats = $this->get_catalog_statistics();
        
        ?>
        <div class="wrap">
            <h1>🔄 Catalogues Astronomiques</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                <div class="catalog-management">
                    <h2>📚 Gestion des Catalogues</h2>
                    
                    <div class="catalog-cards">
                        <?php $this->render_catalog_cards($catalog_stats); ?>
                    </div>
                    
                    <form method="post" class="catalog-import-form" style="margin-top: 30px;">
                        <?php wp_nonce_field('astro_catalog_nonce'); ?>
                        <h3>📥 Importer/Réimporter les Catalogues</h3>
                        <p class="description">Chargement des données depuis les fichiers CSV du plugin.</p>
                        
                        <table class="form-table">
                            <tr>
                                <th>Catalogues à traiter</th>
                                <td>
                                    <label><input type="checkbox" name="catalogs[]" value="messier" checked> 📡 Messier (110 objets - complet)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="ngc" checked> 🌌 NGC (New General Catalogue)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="ic" checked> ⭐ IC (Index Catalogue)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="caldwell" checked> 🔭 Caldwell (109 objets - complet)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="sharpless"> 💫 Sharpless (nébuleuses)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="ugc"> 🌠 UGC (Uppsala General Catalogue)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="pgc"> 🌌 PGC (Principal Galaxy Catalogue)</label><br>
                                    <label><input type="checkbox" name="catalogs[]" value="abell"> 🔥 Abell (amas de galaxies)</label><br>
                                </td>
                            </tr>
                        </table>
                        
                        <?php submit_button('🔄 Mettre à jour les Catalogues', 'primary', 'import_catalog'); ?>
                    </form>
                </div>
                
                <div class="catalog-browser">
                    <h2>🔍 Navigateur de Catalogues</h2>
                    
                    <div class="catalog-search" style="margin-bottom: 20px;">
                        <input type="text" id="catalog-search" placeholder="Rechercher un objet (M31, NGC 7000, Andromède...)" style="width: 100%; padding: 10px;">
                        <p class="description">Recherche dans les catalogues disponibles (<?php echo array_sum($catalog_stats); ?> objets - Messier et Caldwell complets, autres sélectionnés)</p>
                    </div>
                    
                    <div class="catalog-filters" style="margin-bottom: 20px; display: flex; gap: 10px; flex-wrap: wrap;">
                        <button class="button catalog-filter active" data-catalog="all">Tous</button>
                        <button class="button catalog-filter" data-catalog="messier">Messier</button>
                        <button class="button catalog-filter" data-catalog="ngc">NGC</button>
                        <button class="button catalog-filter" data-catalog="ic">IC</button>
                        <button class="button catalog-filter" data-catalog="caldwell">Caldwell</button>
                        <button class="button catalog-filter" data-catalog="sharpless">Sharpless</button>
                        <button class="button catalog-filter" data-catalog="ugc">UGC</button>
                        <button class="button catalog-filter" data-catalog="pgc">PGC</button>
                        <button class="button catalog-filter" data-catalog="abell">Abell</button>
                    </div>
                    
                    <div id="catalog-results" class="catalog-results" style="max-height: 500px; overflow-y: auto; border: 1px solid #ddd; padding: 15px;">
                        <p class="description">Utilisez la recherche ou les filtres pour explorer les catalogues.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .catalog-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
            .catalog-card { background: #f9f9f9; padding: 20px; border-left: 4px solid #3498db; border-radius: 4px; position: relative; }
            .catalog-card.complete { border-left-color: #27ae60; background: #f8fff8; }
            .catalog-card h4 { margin: 0 0 10px 0; color: #2c3e50; }
            .catalog-card .count { font-size: 24px; font-weight: bold; color: #3498db; }
            .catalog-card.complete .count { color: #27ae60; }
            .catalog-card .description { font-size: 12px; color: #7f8c8d; margin-top: 5px; }
            .catalog-card .complete-badge { position: absolute; top: 5px; right: 5px; background: #27ae60; color: white; font-size: 10px; padding: 2px 6px; border-radius: 10px; }
            .catalog-filter.active { background: #3498db; color: white; }
            .catalog-object { padding: 10px; border-bottom: 1px solid #eee; }
            .catalog-object:hover { background: #f5f5f5; }
            .catalog-object h5 { margin: 0 0 5px 0; color: #2c3e50; }
            .catalog-object .meta { font-size: 12px; color: #666; }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Recherche en temps réel
            $('#catalog-search').on('input', function() {
                var query = $(this).val();
                if (query.length >= 2) {
                    searchCatalogObjects(query);
                } else {
                    $('#catalog-results').html('<p class="description">Utilisez la recherche ou les filtres pour explorer les catalogues.</p>');
                }
            });
            
            // Filtres par catalogue
            $('.catalog-filter').click(function() {
                $('.catalog-filter').removeClass('active');
                $(this).addClass('active');
                var catalog = $(this).data('catalog');
                loadCatalogObjects(catalog);
            });
        });
        
        function searchCatalogObjects(query) {
            jQuery.post(ajaxurl, {
                action: 'astro_search_catalog',
                query: query,
                nonce: '<?php echo wp_create_nonce('catalog_search'); ?>'
            }, function(response) {
                if (response.success) {
                    displayCatalogResults(response.data);
                }
            });
        }
        
        function loadCatalogObjects(catalog) {
            jQuery.post(ajaxurl, {
                action: 'astro_load_catalog',
                catalog: catalog,
                nonce: '<?php echo wp_create_nonce('catalog_search'); ?>'
            }, function(response) {
                if (response.success) {
                    displayCatalogResults(response.data);
                }
            });
        }
        
        function displayCatalogResults(objects) {
            var html = '';
            if (objects.length === 0) {
                html = '<p>Aucun objet trouvé.</p>';
            } else {
                html += '<div style="margin-bottom: 10px;"><strong>' + objects.length + ' objet(s) trouvé(s)</strong></div>';
                objects.forEach(function(obj) {
                    html += '<div class="catalog-object">';
                    html += '<h5>' + obj.designation + ' - ' + obj.name + '</h5>';
                    html += '<div class="meta">';
                    html += '<strong>Type:</strong> ' + obj.type;
                    if (obj.magnitude) html += ' | <strong>Mag:</strong> ' + obj.magnitude;
                    html += ' | <strong>Constellation:</strong> ' + obj.constellation;
                    if (obj.distance) html += ' | <strong>Distance:</strong> ' + obj.distance;
                    if (obj.size) html += ' | <strong>Taille:</strong> ' + obj.size;
                    if (obj.ra && obj.dec) html += '<br><strong>Coordonnées:</strong> RA ' + obj.ra + '° / Dec ' + obj.dec + '°';
                    html += '</div>';
                    html += '</div>';
                });
                
                if (objects.length >= 200) {
                    html += '<div style="text-align: center; padding: 10px; color: #666; font-style: italic;">Résultats limités à 10000 objets pour les performances. Précisez votre recherche pour plus de pertinence.</div>';
                }
            }
            jQuery('#catalog-results').html(html);
        }
        </script>
        <?php
    }
    
    private function render_catalog_cards($stats) {
        $catalogs = array(
            'messier' => array('name' => 'Messier', 'icon' => '📡', 'description' => 'Catalogue complet (110 objets)'),
            'ngc' => array('name' => 'NGC', 'icon' => '🌌', 'description' => 'New General Catalogue'),
            'ic' => array('name' => 'IC', 'icon' => '⭐', 'description' => 'Index Catalogue'),
            'caldwell' => array('name' => 'Caldwell', 'icon' => '🔭', 'description' => 'Catalogue complet (109 objets)'),
            'sharpless' => array('name' => 'Sharpless', 'icon' => '💫', 'description' => 'Nébuleuses à émission'),
            'ugc' => array('name' => 'UGC', 'icon' => '🌠', 'description' => 'Uppsala General Catalogue'),
            'pgc' => array('name' => 'PGC', 'icon' => '🌌', 'description' => 'Principal Galaxy Catalogue'),
            'abell' => array('name' => 'Abell', 'icon' => '🔥', 'description' => 'Amas de galaxies')
        );
        
        $total_objects = array_sum($stats);
        
        echo '<div class="total-objects-banner" style="text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h3 style="margin: 0; color: white;">🌌 ' . number_format($total_objects) . ' objets astronomiques disponibles</h3>';
        echo '<p style="margin: 5px 0 0 0;">Recherche dans l\'ensemble des catalogues professionnels</p>';
        echo '</div>';
        
        foreach ($catalogs as $key => $catalog) {
            $count = $stats[$key] ?? 0;
            
            // Skip catalogues with 0 objects
            if ($count === 0) continue;
            
            $is_complete = in_array($key, array('messier', 'caldwell')) || $count > 1000;
            
            echo '<div class="catalog-card' . ($is_complete ? ' complete' : '') . '">';
            echo '<h4>' . $catalog['icon'] . ' ' . $catalog['name'] . '</h4>';
            echo '<div class="count">' . number_format($count) . '</div>';
            echo '<div class="description">' . $catalog['description'] . '</div>';
            if ($is_complete || $count > 1000) {
                echo '<div class="complete-badge">✅ ' . ($count > 5000 ? 'Massif' : 'Complet') . '</div>';
            }
            echo '</div>';
        }
    }
    
    private function get_catalog_statistics() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_objects';
        
        $stats = array();
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            // Compter les objets réellement importés dans la base de données
            $catalogs = array('messier', 'ngc', 'ic', 'caldwell', 'sharpless', 'ugc', 'pgc', 'abell');
            
            foreach ($catalogs as $catalog) {
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_name WHERE catalog_name = %s",
                    $catalog
                ));
                $stats[$catalog] = intval($count);
            }
        } else {
            // Fallback vers le comptage des fichiers CSV si la table n'existe pas
            $data_dir = plugin_dir_path(__FILE__) . 'data/';
            $catalogs = array('messier', 'ngc', 'ic', 'caldwell', 'sharpless', 'ugc', 'pgc', 'abell');
            
            foreach ($catalogs as $catalog) {
                // Chercher d'abord les versions complètes
                $csv_file = $data_dir . $catalog . '_complete.csv';
                if (!file_exists($csv_file)) {
                    $csv_file = $data_dir . $catalog . '.csv';
                }
                
                if (file_exists($csv_file)) {
                    $line_count = 0;
                    if (($handle = fopen($csv_file, 'r')) !== FALSE) {
                        // Pour les fichiers VizieR, compter seulement les vraies données
                        if (strpos($csv_file, '_complete.csv') !== false) {
                            while (($line = fgets($handle)) !== FALSE) {
                                $line = trim($line);
                                // Compter seulement les lignes de données (pas commentaires ni headers)
                                if (!empty($line) && !str_starts_with($line, '#') && 
                                    !str_starts_with($line, '-') && !str_starts_with($line, 'Name') &&
                                    !str_starts_with($line, '"')) {
                                    $line_count++;
                                }
                            }
                        } else {
                            // Format CSV standard
                            while (fgets($handle) !== FALSE) {
                                $line_count++;
                            }
                            $line_count = max(0, $line_count - 1); // -1 pour enlever les headers
                        }
                        fclose($handle);
                        $stats[$catalog] = $line_count;
                    }
                } else {
                    $stats[$catalog] = 0;
                }
            }
        }
        
        return $stats;
    }
    
    private function import_catalog_data() {
        $selected_catalogs = $_POST['catalogs'] ?? array();
        
        if (empty($selected_catalogs)) {
            return array('success' => false, 'message' => 'Aucun catalogue sélectionné.');
        }
        
        // Créer la table des catalogues si elle n'existe pas
        $this->create_catalog_table();
        
        $data_dir = plugin_dir_path(__FILE__) . 'data/';
        $total_imported = 0;
        $errors = array();
        $results = array();
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_objects';
        
        foreach ($selected_catalogs as $catalog) {
            // Chercher d'abord les versions complètes
            $csv_file = $data_dir . $catalog . '_complete.csv';
            if (!file_exists($csv_file)) {
                $csv_file = $data_dir . $catalog . '.csv';
            }
            
            if (file_exists($csv_file)) {
                try {
                    // Supprimer les anciennes données de ce catalogue
                    $deleted = $wpdb->delete($table_name, array('catalog_name' => $catalog));
                    
                    // Importer les nouvelles données
                    $import_result = $this->import_csv_to_database($csv_file, $catalog);
                    
                    if ($import_result['success']) {
                        $total_imported += $import_result['imported'];
                        $results[] = "✅ $catalog: {$import_result['imported']} objets importés";
                        if ($import_result['errors'] > 0) {
                            $results[] = "⚠️ $catalog: {$import_result['errors']} erreurs";
                        }
                    } else {
                        $errors[] = "❌ $catalog: " . $import_result['error'];
                    }
                    
                } catch (Exception $e) {
                    $errors[] = "❌ Erreur pour $catalog: " . $e->getMessage();
                }
            } else {
                $errors[] = "❌ Fichier $catalog.csv introuvable";
            }
        }
        
        if ($total_imported > 0) {
            $message = implode("\n", $results);
            if (!empty($errors)) {
                $message .= "\n" . implode("\n", $errors);
            }
            return array('success' => true, 'message' => $message, 'imported' => $total_imported);
        } else {
            return array('success' => false, 'message' => implode("\n", $errors));
        }
    }
    
    private function create_catalog_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astro_objects';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Supprimer l'ancienne table si elle existe (pour éviter les conflits de schéma)
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            catalog_name varchar(50) NOT NULL DEFAULT '',
            designation varchar(100) NOT NULL DEFAULT '',
            common_name varchar(255) DEFAULT '',
            object_type varchar(100) DEFAULT '',
            constellation varchar(50) DEFAULT '',
            ra_hours decimal(10,6) DEFAULT NULL,
            dec_degrees decimal(10,6) DEFAULT NULL,
            magnitude decimal(5,2) DEFAULT NULL,
            size varchar(100) DEFAULT '',
            distance_ly varchar(100) DEFAULT '',
            notes text DEFAULT '',
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            INDEX idx_catalog (catalog_name),
            INDEX idx_designation (designation),
            INDEX idx_name (common_name),
            INDEX idx_type (object_type),
            INDEX idx_constellation (constellation)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    private function import_csv_to_database($csv_file, $catalog_name) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_objects';
        
        if (!file_exists($csv_file)) {
            return array('success' => false, 'error' => "Fichier non trouvé: $csv_file");
        }
        
        $imported_count = 0;
        $error_count = 0;
        
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            // Détecter si c'est un fichier VizieR ou CSV standard
            $is_vizier_format = strpos($csv_file, '_complete.csv') !== false;
            
            if ($is_vizier_format) {
                // Parser le format VizieR
                $result = $this->import_vizier_to_database($handle, $catalog_name, $table_name);
                $imported_count = $result['imported'];
                $error_count = $result['errors'];
            } else {
                // Parser le format CSV standard
                $result = $this->import_standard_csv_to_database($handle, $catalog_name, $table_name);
                $imported_count = $result['imported'];
                $error_count = $result['errors'];
            }
            
            fclose($handle);
        }
        
        return array(
            'success' => true,
            'imported' => $imported_count,
            'errors' => $error_count,
            'catalog' => $catalog_name
        );
    }
    
    private function import_vizier_to_database($handle, $catalog_name, $table_name) {
        $imported_count = 0;
        $error_count = 0;
        $batch_data = array();
        $batch_size = 100;
        
        while (($line = fgets($handle)) !== FALSE) {
            $line = trim($line);
            
            // Ignorer commentaires, lignes vides, séparateurs et headers
            if (empty($line) || str_starts_with($line, '#') || 
                str_starts_with($line, '-') || str_starts_with($line, 'Name') ||
                str_starts_with($line, '"')) {
                continue;
            }
            
            // Parser la ligne format fixe VizieR
            if (preg_match('/^(\S+)\s+(\S*)\s+(\S+\s+\S+\s+\S+)\s+(\S+\s+\S+\s+\S+)\s+(\S*)\s+(\S+)\s+(\S*)\s+(\S*)\s+(\S*)\s+(\S*)\s+(.*)$/', $line, $matches)) {
                $designation = trim($matches[1]);
                if (empty($designation)) continue;
                
                $batch_data[] = array(
                    'catalog_name' => $catalog_name,
                    'designation' => $designation,
                    'common_name' => trim($matches[2] ?? ''),
                    'object_type' => $this->normalize_object_type(trim($matches[2] ?? '')),
                    'constellation' => trim($matches[6] ?? ''),
                    'ra_hours' => $this->parse_coordinates($matches[3] ?? '', 'ra'),
                    'dec_degrees' => $this->parse_coordinates($matches[4] ?? '', 'dec'), 
                    'magnitude' => is_numeric($matches[9] ?? '') ? floatval($matches[9]) : null,
                    'size' => trim($matches[8] ?? ''),
                    'distance_ly' => '',
                    'notes' => trim($matches[11] ?? '')
                );
                
                // Insérer par batch
                if (count($batch_data) >= $batch_size) {
                    $result = $this->insert_batch($table_name, $batch_data);
                    $imported_count += $result['success_count'];
                    $error_count += $result['error_count'];
                    $batch_data = array();
                }
            }
        }
        
        // Insérer le dernier batch
        if (!empty($batch_data)) {
            $result = $this->insert_batch($table_name, $batch_data);
            $imported_count += $result['success_count'];
            $error_count += $result['error_count'];
        }
        
        return array('imported' => $imported_count, 'errors' => $error_count);
    }
    
    private function import_standard_csv_to_database($handle, $catalog_name, $table_name) {
        // Ignorer les lignes de commentaire VizieR (commencent par #)
        while (($line = fgets($handle)) !== FALSE) {
            if (!empty(trim($line)) && !str_starts_with(trim($line), '#')) {
                // Remettre la ligne dans le flux pour fgetcsv
                fseek($handle, -strlen($line), SEEK_CUR);
                break;
            }
        }
        
        $headers = fgetcsv($handle); // Lire les headers
        
        // Préparer les données par batch pour optimiser
        $batch_data = array();
        $batch_size = 100;
        $imported_count = 0;
        $error_count = 0;
        
        while (($data = fgetcsv($handle)) !== FALSE) {
            if (count($data) >= 4) {
                // Validation stricte - ignorer les lignes avec designation vide
                $designation = trim(sanitize_text_field($data[0] ?? ''));
                if (empty($designation)) {
                    continue; // Ignorer les lignes sans désignation
                }
                
                $batch_data[] = array(
                    'catalog_name' => $catalog_name,
                    'designation' => $designation,
                    'common_name' => sanitize_text_field($data[1] ?? ''),
                    'object_type' => sanitize_text_field($data[2] ?? ''),
                    'constellation' => sanitize_text_field($data[3] ?? ''),
                    'ra_hours' => is_numeric($data[4] ?? '') ? floatval($data[4]) : null,
                    'dec_degrees' => is_numeric($data[5] ?? '') ? floatval($data[5]) : null,
                    'magnitude' => is_numeric($data[6] ?? '') ? floatval($data[6]) : null,
                    'size' => sanitize_text_field($data[7] ?? ''),
                    'distance_ly' => sanitize_text_field($data[8] ?? ''),
                    'notes' => sanitize_text_field($data[9] ?? '')
                );
                
                // Insérer par batch
                if (count($batch_data) >= $batch_size) {
                    $result = $this->insert_batch($table_name, $batch_data);
                    $imported_count += $result['success_count'];
                    $error_count += $result['error_count'];
                    $batch_data = array();
                }
            }
        }
        
        // Insérer le dernier batch
        if (!empty($batch_data)) {
            $result = $this->insert_batch($table_name, $batch_data);
            $imported_count += $result['success_count'];
            $error_count += $result['error_count'];
        }
        
        return array('imported' => $imported_count, 'errors' => $error_count);
    }
    
    private function normalize_object_type($type) {
        $type_map = array(
            'Gx' => 'Galaxy',
            'Pl' => 'Planetary Nebula', 
            'OC' => 'Open Cluster',
            'Gb' => 'Globular Cluster',
            'Nb' => 'Nebula',
            'PN' => 'Planetary Nebula',
            'HII' => 'HII Region',
            'SNR' => 'Supernova Remnant',
            'BN' => 'Bright Nebula',
            'DN' => 'Dark Nebula',
            'RN' => 'Reflection Nebula',
            'EN' => 'Emission Nebula'
        );
        
        return $type_map[$type] ?? ($type ?: 'Unknown');
    }
    
    private function parse_coordinates($coord_str, $type) {
        if (empty($coord_str)) return null;
        
        // Format VizieR: "00 00.1" ou "+32 45"
        if ($type === 'ra') {
            // RA en heures minutes
            if (preg_match('/(\d+)\s+(\d+\.?\d*)/', $coord_str, $matches)) {
                return floatval($matches[1]) + floatval($matches[2]) / 60.0;
            }
        } else {
            // DEC en degrés minutes
            if (preg_match('/([+-]?)(\d+)\s+(\d+\.?\d*)/', $coord_str, $matches)) {
                $sign = ($matches[1] === '-') ? -1 : 1;
                return $sign * (floatval($matches[2]) + floatval($matches[3]) / 60.0);
            }
        }
        
        return null;
    }
    
    private function insert_batch($table_name, $batch_data) {
        global $wpdb;
        
        if (empty($batch_data)) {
            return array('success_count' => 0, 'error_count' => 0);
        }
        
        $success_count = 0;
        $error_count = 0;
        
        // Utiliser REPLACE pour éviter les conflits et permettre les mises à jour
        foreach ($batch_data as $row) {
            // Construire une requête REPLACE manuelle pour plus de contrôle
            $sql = $wpdb->prepare(
                "REPLACE INTO $table_name 
                (catalog_name, designation, common_name, object_type, constellation, ra_hours, dec_degrees, magnitude, size, distance_ly, notes) 
                VALUES (%s, %s, %s, %s, %s, %f, %f, %f, %s, %s, %s)",
                $row['catalog_name'],
                $row['designation'], 
                $row['common_name'],
                $row['object_type'],
                $row['constellation'],
                $row['ra_hours'],
                $row['dec_degrees'], 
                $row['magnitude'],
                $row['size'],
                $row['distance_ly'],
                $row['notes']
            );
            
            $result = $wpdb->query($sql);
            
            if ($result !== false) {
                $success_count++;
            } else {
                $error_count++;
                // Log de débogage
                error_log("AstroFolio: Erreur insertion " . $row['designation'] . " - " . $wpdb->last_error);
            }
        }
        
        return array('success_count' => $success_count, 'error_count' => $error_count);
    }
    
    public function ajax_search_catalog() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'catalog_search')) {
            wp_die('Nonce invalide');
        }
        
        $query = sanitize_text_field($_POST['query'] ?? '');
        if (empty($query)) {
            wp_send_json_error('Query manquante');
        }
        
        $results = $this->search_catalog_objects($query);
        wp_send_json_success($results);
    }
    
    public function ajax_load_catalog() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'catalog_search')) {
            wp_die('Nonce invalide');
        }
        
        $catalog = sanitize_text_field($_POST['catalog'] ?? 'all');
        $results = $this->load_catalog_objects($catalog);
        wp_send_json_success($results);
    }
    
    private function search_catalog_objects($query) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_objects';
        
        // Vérifier si la table existe et contient des données
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Fallback vers la recherche dans les fichiers CSV
            return $this->search_catalog_objects_from_files($query);
        }
        
        // Recherche optimisée en base de données
        $search_term = '%' . $wpdb->esc_like(strtolower($query)) . '%';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT catalog_name, designation, common_name, object_type, constellation, 
                    magnitude, size, distance_ly, notes, ra_hours, dec_degrees
             FROM $table_name 
             WHERE LOWER(designation) LIKE %s 
                OR LOWER(common_name) LIKE %s
                OR LOWER(object_type) LIKE %s
             ORDER BY 
                CASE WHEN LOWER(designation) LIKE %s THEN 1 ELSE 2 END,
                designation
             LIMIT 10000",
            $search_term, $search_term, $search_term, $search_term
        ), ARRAY_A);
        
        // Formater les résultats au format attendu
        $formatted_results = array();
        foreach ($results as $row) {
            $formatted_results[] = array(
                'designation' => $row['designation'],
                'name' => $row['common_name'],
                'type' => $row['object_type'],
                'constellation' => $row['constellation'],
                'magnitude' => $row['magnitude'],
                'distance' => $this->format_distance($row['distance_ly']),
                'size' => $row['size'],
                'catalog' => ucfirst($row['catalog_name']),
                'ra' => $row['ra_hours'],
                'dec' => $row['dec_degrees'],
                'notes' => $row['notes']
            );
        }
        
        return $formatted_results;
    }
    
    private function search_catalog_objects_from_files($query) {
        // Ancienne méthode de recherche dans les fichiers CSV (fallback)
        $data_dir = plugin_dir_path(__FILE__) . 'data/';
        $results = array();
        
        $catalogs = array(
            'messier',
            'caldwell',       
            'ngc',           
            'ic',            
            'sharpless',     
            'ugc',           
            'pgc',           
            'abell'          
        );
        
        foreach ($catalogs as $catalog) {
            // Chercher d'abord les versions complètes
            $csv_file = $data_dir . $catalog . '_complete.csv';
            if (!file_exists($csv_file)) {
                $csv_file = $data_dir . $catalog . '.csv';
            }
            
            if (file_exists($csv_file)) {
                // Pas de limite artificielle pour la recherche
                $results = array_merge($results, $this->search_in_csv($csv_file, $query, ucfirst($catalog), 1000));
            }
        }
        
        return array_slice($results, 0, 10000);
    }
    
    private function load_catalog_objects($catalog, $limit = 10000) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_objects';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Fallback vers les fichiers CSV
            return $this->load_catalog_objects_from_files($catalog, $limit);
        }
        
        if ($catalog === 'all') {
            // Charger un échantillon de chaque catalogue
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT catalog_name, designation, common_name, object_type, constellation, 
                        magnitude, size, distance_ly, notes, ra_hours, dec_degrees
                 FROM $table_name 
                 GROUP BY catalog_name, designation
                 ORDER BY catalog_name, designation
                 LIMIT %d",
                $limit
            ), ARRAY_A);
        } else {
            // Charger un catalogue spécifique
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT catalog_name, designation, common_name, object_type, constellation, 
                        magnitude, size, distance_ly, notes, ra_hours, dec_degrees
                 FROM $table_name 
                 WHERE catalog_name = %s
                 ORDER BY designation
                 LIMIT %d",
                $catalog, $limit
            ), ARRAY_A);
        }
        
        // Formater les résultats
        $formatted_results = array();
        foreach ($results as $row) {
            $formatted_results[] = array(
                'designation' => $row['designation'],
                'name' => $row['common_name'],
                'type' => $row['object_type'],
                'constellation' => $row['constellation'],
                'magnitude' => $row['magnitude'],
                'distance' => $this->format_distance($row['distance_ly']),
                'size' => $row['size'],
                'catalog' => ucfirst($row['catalog_name']),
                'ra' => $row['ra_hours'],
                'dec' => $row['dec_degrees'],
                'notes' => $row['notes']
            );
        }
        
        return $formatted_results;
    }
    
    private function load_catalog_objects_from_files($catalog, $limit = 10000) {
        // Ancienne méthode avec fichiers CSV (fallback)
        $data_dir = plugin_dir_path(__FILE__) . 'data/';
        $results = array();
        
        if ($catalog === 'all') {
            $catalogs = array(
                'messier',
                'caldwell', 
                'ic',
                'ngc',
                'sharpless',
                'ugc',
                'pgc',
                'abell'
            );
            
            foreach ($catalogs as $cat) {
                // Chercher d'abord les versions complètes
                $csv_file = $data_dir . $cat . '_complete.csv';
                if (!file_exists($csv_file)) {
                    $csv_file = $data_dir . $cat . '.csv';
                }
                if (file_exists($csv_file)) {
                    // Pas de limite - charger tout le catalogue
                    $results = array_merge($results, $this->load_csv_objects($csv_file, ucfirst($cat), PHP_INT_MAX));
                }
            }
        } else {
            // Chercher d'abord les versions complètes
            $csv_file = $data_dir . $catalog . '_complete.csv';
            if (!file_exists($csv_file)) {
                $csv_file = $data_dir . $catalog . '.csv';
            }
            if (file_exists($csv_file)) {
                // Pas de limite artificielle - importer tout le catalogue
                $results = $this->load_csv_objects($csv_file, ucfirst($catalog), PHP_INT_MAX);
            }
        }
        
        return $results;
    }
    
    private function search_in_csv($csv_file, $query, $catalog_name, $limit = 100) {
        $results = array();
        $query = strtolower($query);
        
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            $headers = fgetcsv($handle); // Lire les headers
            $count = 0;
            
            while (($data = fgetcsv($handle)) !== FALSE && $count < $limit) {
                if (count($data) >= 4) { // Au minimum designation, nom, type, constellation
                    $designation = $data[0] ?? '';
                    $name = $data[1] ?? '';
                    
                    if (strpos(strtolower($designation), $query) !== false || 
                        strpos(strtolower($name), $query) !== false) {
                        
                        $results[] = array(
                            'designation' => $designation,
                            'name' => $name,
                            'type' => $data[2] ?? 'Inconnu',
                            'constellation' => $data[3] ?? '',
                            'magnitude' => $data[6] ?? $data[4] ?? '', // Essayer colonne 6 puis 4
                            'distance' => $this->format_distance($data[8] ?? $data[5] ?? ''), // Essayer colonne 8 puis 5
                            'size' => $data[7] ?? $data[5] ?? '', // Taille de l'objet
                            'catalog' => $catalog_name,
                            'ra' => $data[4] ?? '',
                            'dec' => $data[5] ?? ''
                        );
                        $count++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $results;
    }
    
    private function load_csv_objects($csv_file, $catalog_name, $limit = PHP_INT_MAX) {
        $results = array();
        
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            // Ignorer les lignes de commentaire VizieR (commencent par #)
            while (($line = fgets($handle)) !== FALSE) {
                if (!empty(trim($line)) && !str_starts_with(trim($line), '#')) {
                    // Remettre la ligne dans le flux pour fgetcsv
                    fseek($handle, -strlen($line), SEEK_CUR);
                    break;
                }
            }
            
            $headers = fgetcsv($handle); // Lire les headers pour comprendre la structure
            $count = 0;
            
            // Pas de limite artificielle - charger tout le fichier
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 4 && $count < $limit) {
                    $results[] = array(
                        'designation' => $data[0] ?? '',
                        'name' => $data[1] ?? '',
                        'type' => $data[2] ?? 'Inconnu',
                        'constellation' => $data[3] ?? '',
                        'magnitude' => $data[6] ?? $data[4] ?? '', // Essayer colonne 6 puis 4
                        'distance' => $this->format_distance($data[8] ?? $data[5] ?? ''), // Essayer colonne 8 puis 5
                        'size' => $data[7] ?? $data[5] ?? '',
                        'catalog' => $catalog_name,
                        'ra' => $data[4] ?? '',
                        'dec' => $data[5] ?? '',
                        'notes' => $data[9] ?? ''
                    );
                    $count++;
                }
            }
            fclose($handle);
        }
        
        return $results;
    }
    
    private function format_distance($distance) {
        if (empty($distance) || !is_numeric($distance)) {
            return $distance;
        }
        
        $dist = intval($distance);
        
        // Convertir en années-lumière si c'est un grand nombre
        if ($dist > 1000000) {
            return number_format($dist / 1000000, 1) . ' Mal';
        } elseif ($dist > 1000) {
            return number_format($dist / 1000, 1) . 'k al';
        }
        
        return number_format($dist) . ' al';
    }
    
    // === MÉTHODES FRONTEND ===
    
    public function enqueue_public_scripts() {
        if ($this->is_astro_page()) {
            wp_enqueue_style('astrofolio-public', plugin_dir_url(__FILE__) . 'public/css/public.css', array(), '1.4.3');
            wp_enqueue_script('astrofolio-public', plugin_dir_url(__FILE__) . 'public/js/public.js', array('jquery'), '1.4.3', true);
            
            wp_add_inline_style('wp-block-library', '
                .astrofolio-gallery { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
                .astro-image-card { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); overflow: hidden; transition: transform 0.3s ease; }
                .astro-image-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
                .astro-image-card img { width: 100%; height: 250px; object-fit: contain; background: #f8f9fa; }
                .astro-image-card-content { padding: 20px; }
                .astro-image-card h3 { margin: 0 0 10px 0; color: #2c3e50; }
                .astro-image-card .description { color: #666; font-size: 14px; margin-bottom: 15px; }
                .astro-metadata-summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 8px; font-size: 12px; }
                .meta-badge { background: #3498db; color: white; padding: 4px 8px; border-radius: 12px; text-align: center; }
                .meta-badge.telescope { background: #e74c3c; }
                .meta-badge.camera { background: #27ae60; }
                .meta-badge.exposure { background: #f39c12; }
                .meta-badge.location { background: #9b59b6; }
                .astro-lightbox { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; display: none; }
                .astro-lightbox-content { position: relative; max-width: 90%; max-height: 90%; margin: 5% auto; }
                .astro-lightbox img { max-width: 100%; max-height: 100%; border-radius: 8px; }
                .astro-lightbox-meta { position: absolute; top: 20px; right: 20px; background: rgba(255,255,255,0.95); padding: 20px; border-radius: 8px; max-width: 300px; }
                .astro-lightbox-close { position: absolute; top: 20px; right: 20px; background: #e74c3c; color: white; border: none; padding: 10px 15px; border-radius: 50%; cursor: pointer; font-size: 18px; }
                .astrofolio-page { max-width: 1200px; margin: 0 auto; padding: 20px; }
                .astrofolio-header { text-align: center; margin-bottom: 40px; }
                .astrofolio-header h1 { color: #2c3e50; margin-bottom: 10px; }
                .astrofolio-filters { display: flex; gap: 10px; margin-bottom: 30px; flex-wrap: wrap; justify-content: center; }
                .astrofolio-filter { padding: 8px 16px; border: 2px solid #3498db; background: transparent; color: #3498db; border-radius: 20px; cursor: pointer; transition: all 0.3s ease; }
                .astrofolio-filter.active, .astrofolio-filter:hover { background: #3498db; color: white; }
            ');
        }
    }
    
    public function is_astro_page() {
        global $wp_query;
        return isset($wp_query->query_vars['astrofolio']) || 
               is_page('astrofolio') || 
               has_shortcode(get_post()->post_content ?? '', 'astrofolio_gallery');
    }
    
    public function add_rewrite_rules() {
        add_rewrite_rule('^astrofolio/?$', 'index.php?astrofolio=gallery', 'top');
        add_rewrite_rule('^astrofolio/image/([0-9]+)/?$', 'index.php?astrofolio=image&image_id=$matches[1]', 'top');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'astrofolio';
        $vars[] = 'image_id';
        return $vars;
    }
    
    public function template_redirect() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['astrofolio'])) {
            $type = $wp_query->query_vars['astrofolio'];
            
            if ($type === 'gallery') {
                // Rediriger vers la page WordPress avec shortcode
                $gallery_page = get_option('astrofolio_gallery_page');
                if ($gallery_page) {
                    wp_redirect(get_permalink($gallery_page));
                } else {
                    $this->display_gallery_page();
                }
                exit;
            } elseif ($type === 'image') {
                $image_id = intval($wp_query->query_vars['image_id'] ?? 0);
                // Stocker l'ID de l'image dans une variable globale pour le shortcode
                global $astrofolio_current_image_id;
                $astrofolio_current_image_id = $image_id;
                
                // Rediriger vers la page WordPress avec shortcode détail
                $detail_page = get_option('astrofolio_detail_page');
                if ($detail_page) {
                    wp_redirect(get_permalink($detail_page) . '?image_id=' . $image_id);
                } else {
                    $this->display_image_page($image_id);
                }
                exit;
            }
        }
    }
    
    private function display_gallery_page() {
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>AstroFolio - Galerie - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    margin: 0;
                    padding: 0;
                    background: #f5f5f5;
                }
                .astrofolio-gallery-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .astrofolio-gallery-header {
                    text-align: center;
                    margin-bottom: 40px;
                    padding: 30px;
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                    color: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .astrofolio-gallery-header h1 {
                    font-size: 2.5em;
                    margin: 0;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
                }
                .astrofolio-gallery-header p {
                    font-size: 1.1em;
                    margin: 10px 0 0 0;
                    opacity: 0.9;
                }
                .astrofolio-gallery-content {
                    background: white;
                    border-radius: 12px;
                    padding: 30px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                @media (max-width: 768px) {
                    .astrofolio-gallery-container {
                        padding: 10px;
                    }
                    .astrofolio-gallery-header {
                        padding: 20px;
                    }
                    .astrofolio-gallery-header h1 {
                        font-size: 2em;
                    }
                    .astrofolio-gallery-content {
                        padding: 20px;
                    }
                }
            </style>
        </head>
        <body <?php body_class(); ?>>
            <div class="astrofolio-gallery-container">
                <div class="astrofolio-gallery-header">
                    <h1>🌌 AstroFolio</h1>
                    <p>Galerie d'Astrophotographie - Découvrez ma collection d'images avec leurs métadonnées détaillées</p>
                </div>
                
                <div class="astrofolio-gallery-content">
                    <?php echo $this->render_gallery(); ?>
                </div>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }
    
    private function display_image_page($image_id) {
        if (!$image_id || !wp_attachment_is_image($image_id)) {
            wp_redirect(home_url('/astrofolio'));
            exit;
        }
        
        $image = get_post($image_id);
        $metadata = $this->get_image_metadata($image_id);
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html($image->post_title); ?> - AstroFolio - <?php bloginfo('name'); ?></title>
            <?php wp_head(); ?>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    margin: 0;
                    padding: 0;
                    background: #f5f5f5;
                }
                .astrofolio-detail-container {
                    max-width: 1200px;
                    margin: 0 auto;
                    padding: 20px;
                }
                .astrofolio-detail-header {
                    text-align: center;
                    margin-bottom: 40px;
                    padding: 30px;
                    background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
                    color: white;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .astrofolio-detail-content {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    gap: 40px;
                    align-items: start;
                    background: white;
                    padding: 30px;
                    border-radius: 12px;
                    box-shadow: 0 4px 20px rgba(0,0,0,0.1);
                }
                .astrofolio-image-container {
                    position: relative;
                }
                .astrofolio-image-container img {
                    width: 100%;
                    height: auto;
                    border-radius: 8px;
                    box-shadow: 0 2px 15px rgba(0,0,0,0.2);
                }
                .astrofolio-lightbox-btn {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    background: rgba(0,0,0,0.7);
                    color: white;
                    border: none;
                    padding: 12px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 18px;
                    transition: background 0.3s ease;
                }
                .astrofolio-lightbox-btn:hover {
                    background: rgba(0,0,0,0.9);
                }
                .astrofolio-metadata-section {
                    margin-bottom: 30px;
                    padding: 20px;
                    background: #f8f9fa;
                    border-radius: 8px;
                    border-left: 4px solid #3498db;
                }
                .astrofolio-back-btn {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 6px;
                    transition: background 0.3s ease;
                    margin-top: 20px;
                }
                .astrofolio-back-btn:hover {
                    background: #2980b9;
                    color: white;
                    text-decoration: none;
                }
                @media (max-width: 768px) {
                    .astrofolio-detail-content {
                        grid-template-columns: 1fr;
                        gap: 30px;
                        padding: 20px;
                    }
                    .astrofolio-detail-container {
                        padding: 10px;
                    }
                }
            </style>
        </head>
        <body <?php body_class(); ?>>
            <div class="astrofolio-detail-container">
                <div class="astrofolio-detail-header">
                    <h1 style="margin: 0; font-size: 2.5em; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);">🌟 <?php echo esc_html($image->post_title); ?></h1>
                    <p style="margin: 10px 0 0 0; font-size: 1.1em; opacity: 0.9;">AstroFolio - Image détaillée</p>
                </div>
                
                <div class="astrofolio-detail-content">
                    <div class="astrofolio-image-container">
                        <?php 
                        $image_url = wp_get_attachment_image_src($image_id, 'full')[0];
                        echo wp_get_attachment_image($image_id, 'full');
                        ?>
                        <button class="astrofolio-lightbox-btn" onclick="openAstroLightbox('<?php echo esc_url($image_url); ?>', '<?php echo esc_attr($image->post_title); ?>')" title="Voir en plein écran">
                            🔍
                        </button>
                    </div>
                    
                    <div class="astrofolio-metadata-sidebar">
                        <?php if ($image->post_content): ?>
                            <div class="astrofolio-metadata-section">
                                <h3 style="margin-top: 0; color: #2c3e50;">📝 Description</h3>
                                <?php echo wpautop($image->post_content); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($metadata): ?>
                            <div class="astrofolio-detailed-metadata">
                                <?php echo $this->render_detailed_metadata($metadata); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div style="text-align: center;">
                            <a href="/astrofolio" class="astrofolio-back-btn">← Retour à la galerie</a>
                        </div>
                    </div>
                </div>
                
                <?php echo $this->get_lightbox_html(); ?>
            </div>
            <?php wp_footer(); ?>
        </body>
        </html>
        <?php
        exit;
    }
    
    private function get_image_metadata($image_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        // Vérifier le cache d'abord (mais permettre un bypass)
        $cache_key = 'astro_metadata_' . $image_id;
        $bypass_cache = isset($_GET['refresh']) || isset($_POST['refresh']) || is_admin();
        
        if (!$bypass_cache) {
            $cached_metadata = wp_cache_get($cache_key);
            if ($cached_metadata !== false) {
                return $cached_metadata;
            }
        }
        
        // S'assurer que la table existe
        $this->create_metadata_table();
        
        // Récupérer les métadonnées depuis la table personnalisée (forcer une nouvelle requête)
        $metadata_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE image_id = %d",
            $image_id
        ));
        
        // Aussi récupérer les métadonnées de base depuis post_meta (toujours les plus récentes)
        $object_name = get_post_meta($image_id, 'astro_object_name', true);
        $shooting_date = get_post_meta($image_id, 'astro_shooting_date', true);
        $coordinates = get_post_meta($image_id, 'astro_coordinates', true);
        $telescope = get_post_meta($image_id, 'astro_telescope', true);
        $camera = get_post_meta($image_id, 'astro_camera', true);
        
        $metadata = array();
        
        if ($metadata_row) {
            $metadata = array(
                // Objet céleste
                'object_name' => $object_name ?: '',
                'acquisition_dates' => $shooting_date ?: '',
                
                // Équipement
                'telescope_brand' => $metadata_row->telescope_brand ?: '',
                'telescope_model' => $metadata_row->telescope_model ?: '',
                'telescope_aperture' => $metadata_row->telescope_aperture ?: '',
                'telescope_focal_length' => $metadata_row->telescope_focal_length ?: '',
                'telescope_focal_ratio' => $metadata_row->telescope_focal_ratio ?: '',
                'camera_brand' => $metadata_row->camera_brand ?: '',
                'camera_model' => $metadata_row->camera_model ?: '',
                'camera_sensor' => $metadata_row->camera_sensor ?: '',
                'camera_cooling' => $metadata_row->camera_cooling ?: '',
                'mount_brand' => $metadata_row->mount_brand ?: '',
                'mount_model' => $metadata_row->mount_model ?: '',
                'filters' => $metadata_row->filters ?: '',
                'reducer_corrector' => $metadata_row->reducer_corrector ?: '',
                'guiding_camera' => $metadata_row->guiding_camera ?: '',
                'guiding_scope' => $metadata_row->guiding_scope ?: '',
                
                // Acquisition
                'location_name' => $metadata_row->location_name ?: '',
                'location_coords' => $metadata_row->location_coords ?: '',
                'location_altitude' => $metadata_row->location_altitude ?: '',
                'lights_count' => $metadata_row->lights_count ?: '',
                'lights_exposure' => $metadata_row->lights_exposure ?: '',
                'lights_iso_gain' => $metadata_row->lights_iso_gain ?: '',
                'filter_details' => $metadata_row->filter_details ?: '',
                'darks_count' => $metadata_row->darks_count ?: '',
                'flats_count' => $metadata_row->flats_count ?: '',
                'bias_count' => $metadata_row->bias_count ?: '',
                'capture_software' => $metadata_row->capture_software ?: '',
                'autoguiding' => $metadata_row->autoguiding ?: '',
                
                // Conditions
                'weather_conditions' => $metadata_row->weather_conditions ?: '',
                'temperature' => $metadata_row->temperature ?: '',
                'humidity' => $metadata_row->humidity ?: '',
                'wind_speed' => $metadata_row->wind_speed ?: '',
                'bortle_scale' => $metadata_row->bortle_scale ?: '',
                'seeing' => $metadata_row->seeing ?: '',
                'moon_illumination' => $metadata_row->moon_illumination ?: '',
                
                // Traitement
                'stacking_software' => $metadata_row->stacking_software ?: '',
                'processing_software' => $metadata_row->processing_software ?: '',
                'preprocessing_steps' => $metadata_row->preprocessing_steps ?: '',
                'processing_steps' => $metadata_row->processing_steps ?: '',
                'special_techniques' => $metadata_row->special_techniques ?: '',
                'final_resolution' => $metadata_row->final_resolution ?: '',
                'pixel_scale' => $metadata_row->pixel_scale ?: '',
                'field_of_view' => $metadata_row->field_of_view ?: '',
                'processing_notes' => $metadata_row->processing_notes ?: '',
                'acquisition_notes' => $metadata_row->acquisition_notes ?: ''
            );
        } else {
            // Fallback sur les métadonnées de base
            $metadata = array(
                'object_name' => $object_name ?: '',
                'acquisition_dates' => $shooting_date ?: '',
                'telescope_brand' => '',
                'camera_brand' => '',
                'lights_exposure' => '',
                'location_name' => '',
                'lights_count' => '',
                'lights_iso_gain' => '',
                'stacking_software' => '',
                'processing_software' => '',
            );
        }
        
        // Mettre en cache le résultat (mais pas trop longtemps)
        wp_cache_set($cache_key, $metadata, '', 300); // 5 minutes
        
        return $metadata;
    }
    
    private function render_detailed_metadata($metadata) {
        if (empty($metadata)) {
            return '<p>Aucune métadonnée disponible.</p>';
        }
        
        $html = '<div class="detailed-metadata-sections">';
        
        // Helper function
        $get_value = function($key) use ($metadata) {
            return !empty($metadata[$key]) ? esc_html($metadata[$key]) : '<em>N.C.</em>';
        };
        
        // Objet céleste
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #9b59b6; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">🌟 Objet Céleste</h4>';
        $html .= '<p><strong>Nom:</strong> ' . $get_value('object_name') . '</p>';
        $html .= '<p><strong>Date d\'acquisition:</strong> ' . $get_value('acquisition_dates') . '</p>';
        $html .= '</div>';
        
        // Équipement optique
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #3498db; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">🔭 Équipement Optique</h4>';
        
        // Télescope
        $telescope = '';
        if (!empty($metadata['telescope_brand']) || !empty($metadata['telescope_model'])) {
            if (!empty($metadata['telescope_brand'])) $telescope .= $metadata['telescope_brand'];
            if (!empty($metadata['telescope_model'])) $telescope .= ' ' . $metadata['telescope_model'];
            if (!empty($metadata['telescope_aperture'])) {
                $telescope .= ' (' . $metadata['telescope_aperture'] . 'mm';
                if (!empty($metadata['telescope_focal_length'])) {
                    $focal_ratio = round($metadata['telescope_focal_length'] / $metadata['telescope_aperture'], 1);
                    $telescope .= ', f/' . $focal_ratio;
                }
                $telescope .= ')';
            }
        }
        $html .= '<p><strong>Télescope:</strong> ' . ($telescope ?: '<em>N.C.</em>') . '</p>';
        $html .= '<p><strong>Ouverture:</strong> ' . $get_value('telescope_aperture') . (!empty($metadata['telescope_aperture']) ? 'mm' : '') . '</p>';
        $html .= '<p><strong>Focale:</strong> ' . $get_value('telescope_focal_length') . (!empty($metadata['telescope_focal_length']) ? 'mm' : '') . '</p>';
        $html .= '<p><strong>Rapport f/D:</strong> ' . $get_value('telescope_focal_ratio') . '</p>';
        $html .= '<p><strong>Réducteur/Correcteur:</strong> ' . $get_value('reducer_corrector') . '</p>';
        $html .= '<p><strong>Filtres:</strong> ' . $get_value('filters') . '</p>';
        $html .= '</div>';
        
        // Équipement de capture
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #e74c3c; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">📷 Équipement de Capture</h4>';
        
        // Caméra
        $camera = '';
        if (!empty($metadata['camera_brand']) || !empty($metadata['camera_model'])) {
            if (!empty($metadata['camera_brand'])) $camera .= $metadata['camera_brand'];
            if (!empty($metadata['camera_model'])) $camera .= ' ' . $metadata['camera_model'];
        }
        $html .= '<p><strong>Caméra:</strong> ' . ($camera ?: '<em>N.C.</em>') . '</p>';
        $html .= '<p><strong>Capteur:</strong> ' . $get_value('camera_sensor') . '</p>';
        $html .= '<p><strong>Refroidissement:</strong> ' . $get_value('camera_cooling') . '</p>';
        
        // Monture
        $mount = '';
        if (!empty($metadata['mount_brand']) || !empty($metadata['mount_model'])) {
            if (!empty($metadata['mount_brand'])) $mount .= $metadata['mount_brand'];
            if (!empty($metadata['mount_model'])) $mount .= ' ' . $metadata['mount_model'];
        }
        $html .= '<p><strong>Monture:</strong> ' . ($mount ?: '<em>N.C.</em>') . '</p>';
        $html .= '<p><strong>Autoguidage:</strong> ' . $get_value('autoguiding') . '</p>';
        $html .= '<p><strong>Caméra de guidage:</strong> ' . $get_value('guiding_camera') . '</p>';
        $html .= '<p><strong>Lunette de guidage:</strong> ' . $get_value('guiding_scope') . '</p>';
        $html .= '</div>';
        
        // Acquisition
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #27ae60; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">📷 Données d\'Acquisition</h4>';
        
        if (!empty($metadata['lights_count']) && !empty($metadata['lights_exposure'])) {
            $total_time = ($metadata['lights_count'] * $metadata['lights_exposure']) / 3600;
            $html .= '<p><strong>Poses Lights:</strong> ' . $metadata['lights_count'] . ' × ' . $metadata['lights_exposure'] . 's = ' . number_format($total_time, 1) . 'h</p>';
        } else {
            $html .= '<p><strong>Nombre de poses:</strong> ' . $get_value('lights_count') . '</p>';
            $html .= '<p><strong>Temps d\'exposition:</strong> ' . $get_value('lights_exposure') . (!empty($metadata['lights_exposure']) ? 's' : '') . '</p>';
        }
        
        $html .= '<p><strong>ISO/Gain:</strong> ' . $get_value('lights_iso_gain') . '</p>';
        $html .= '<p><strong>Poses Darks:</strong> ' . $get_value('darks_count') . '</p>';
        $html .= '<p><strong>Poses Flats:</strong> ' . $get_value('flats_count') . '</p>';
        $html .= '<p><strong>Poses Bias:</strong> ' . $get_value('bias_count') . '</p>';
        $html .= '<p><strong>Détail filtres:</strong> ' . $get_value('filter_details') . '</p>';
        $html .= '<p><strong>Logiciel de capture:</strong> ' . $get_value('capture_software') . '</p>';
        $html .= '</div>';
        
        // Lieu et conditions
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #17a2b8; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">🌍 Lieu et Conditions</h4>';
        $html .= '<p><strong>Lieu:</strong> ' . $get_value('location_name') . '</p>';
        $html .= '<p><strong>Coordonnées:</strong> ' . $get_value('location_coords') . '</p>';
        $html .= '<p><strong>Altitude:</strong> ' . $get_value('location_altitude') . (!empty($metadata['location_altitude']) ? 'm' : '') . '</p>';
        $html .= '<p><strong>Échelle Bortle:</strong> ' . $get_value('bortle_scale') . '</p>';
        $html .= '<p><strong>Seeing:</strong> ' . $get_value('seeing') . '</p>';
        $html .= '<p><strong>Conditions météo:</strong> ' . $get_value('weather_conditions') . '</p>';
        $html .= '<p><strong>Température:</strong> ' . $get_value('temperature') . '</p>';
        $html .= '<p><strong>Humidité:</strong> ' . $get_value('humidity') . '</p>';
        $html .= '<p><strong>Vent:</strong> ' . $get_value('wind_speed') . '</p>';
        $html .= '<p><strong>Illumination lunaire:</strong> ' . $get_value('moon_illumination') . '</p>';
        $html .= '</div>';
        
        // Traitement
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #f39c12; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">⚙️ Traitement</h4>';
        $html .= '<p><strong>Logiciel de stacking:</strong> ' . $get_value('stacking_software') . '</p>';
        $html .= '<p><strong>Logiciel de traitement:</strong> ' . $get_value('processing_software') . '</p>';
        $html .= '<p><strong>Étapes de prétraitement:</strong> ' . $get_value('preprocessing_steps') . '</p>';
        $html .= '<p><strong>Étapes de traitement:</strong> ' . $get_value('processing_steps') . '</p>';
        $html .= '<p><strong>Techniques spéciales:</strong> ' . $get_value('special_techniques') . '</p>';
        $html .= '</div>';
        
        // Résultat final
        $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #6f42c1; border-radius: 4px;">';
        $html .= '<h4 style="margin-top: 0; color: #2c3e50;">📊 Résultat Final</h4>';
        $html .= '<p><strong>Résolution finale:</strong> ' . $get_value('final_resolution') . '</p>';
        $html .= '<p><strong>Échelle de pixel:</strong> ' . $get_value('pixel_scale') . (!empty($metadata['pixel_scale']) ? '"/pixel' : '') . '</p>';
        $html .= '<p><strong>Champ de vision:</strong> ' . $get_value('field_of_view') . '</p>';
        $html .= '</div>';
        
        // Notes
        if (!empty($metadata['processing_notes']) || !empty($metadata['acquisition_notes'])) {
            $html .= '<div class="meta-section" style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #6c757d; border-radius: 4px;">';
            $html .= '<h4 style="margin-top: 0; color: #2c3e50;">📝 Notes</h4>';
            if (!empty($metadata['acquisition_notes'])) {
                $html .= '<p><strong>Notes d\'acquisition:</strong> ' . nl2br(esc_html($metadata['acquisition_notes'])) . '</p>';
            }
            if (!empty($metadata['processing_notes'])) {
                $html .= '<p><strong>Notes de traitement:</strong> ' . nl2br(esc_html($metadata['processing_notes'])) . '</p>';
            }
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    private function guess_object_type($title, $content = '') {
        $text = strtolower($title . ' ' . $content);
        
        if (preg_match('/\b(m\d+|ngc\d+|ic\d+)\b/', $text) || 
            strpos($text, 'nebula') !== false || 
            strpos($text, 'nébuleuse') !== false) {
            return 'nebula';
        }
        
        if (strpos($text, 'galaxie') !== false || 
            strpos($text, 'galaxy') !== false ||
            preg_match('/\b(m31|m33|m81|m82)\b/', $text)) {
            return 'galaxy';
        }
        
        if (strpos($text, 'amas') !== false || 
            strpos($text, 'cluster') !== false ||
            preg_match('/\b(m13|m15|m22|m4)\b/', $text)) {
            return 'cluster';
        }
        
        if (preg_match('/\b(jupiter|mars|saturn|venus|moon|lune|soleil|sun)\b/', $text)) {
            return 'planet';
        }
        
        return 'other';
    }
    
    // Shortcode pour intégrer dans les pages/posts
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => get_option('astro_images_per_page', 12),
            'columns' => get_option('astro_default_columns', 3),
            'size' => get_option('astro_image_quality', 'medium'),
            'show_metadata' => get_option('astro_show_metadata', 1) ? 'true' : 'false',
            'show_titles' => 'true' // Toujours afficher les titres
        ), $atts);
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_gallery($atts);
        return ob_get_clean();
    }
    
    // Shortcode pour afficher une image spécifique
    public function image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0,
            'size' => 'large',
            'show_metadata' => get_option('astro_show_metadata', 1)
        ), $atts);
        
        $image_id = intval($atts['id']);
        if (!$image_id) return '<p>Erreur: ID d\'image manquant</p>';
        
        $image = get_post($image_id);
        if (!$image || $image->post_type !== 'attachment') {
            return '<p>Image non trouvée</p>';
        }
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_single_image($image_id, $atts);
        return ob_get_clean();
    }
    
    // Shortcode pour afficher les images d'un objet céleste
    public function object_shortcode($atts) {
        $atts = shortcode_atts(array(
            'name' => '',
            'show_info' => true,
            'limit' => 10,
            'columns' => 3
        ), $atts);
        
        if (empty($atts['name'])) return '<p>Erreur: nom d\'objet manquant</p>';
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_object_images($atts);
        return ob_get_clean();
    }
    
    // Shortcode de debug pour les administrateurs
    public function debug_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p><em>Accès restreint aux administrateurs</em></p>';
        }
        
        global $wpdb;
        
        $output = '<div class="astro-debug" style="background: #f9f9f9; border: 2px solid #0073aa; padding: 20px; margin: 20px 0;">';
        $output .= '<h3>🔍 DEBUG ASTROFOLIO - Synchronisation Admin/Frontend</h3>';
        
        // Images AstroFolio
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 3,
            'meta_query' => array(
                'relation' => 'OR',
                array('key' => '_astrofolio_image', 'compare' => 'EXISTS'),
                array('key' => 'astro_object_name', 'compare' => 'EXISTS')
            )
        );
        
        $images = get_posts($args);
        $output .= '<p><strong>Images AstroFolio trouvées:</strong> ' . count($images) . '</p>';
        
        // Options de galerie actuelles
        $output .= '<h4>📋 Options Galerie Admin</h4>';
        $output .= '<table style="width:100%; border-collapse: collapse;">';
        $output .= '<tr><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Option</th><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Valeur</th></tr>';
        $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">Images par page</td><td style="border:1px solid #ddd; padding:8px;">' . get_option('astro_images_per_page', 12) . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">Colonnes par défaut</td><td style="border:1px solid #ddd; padding:8px;">' . get_option('astro_default_columns', 3) . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">Qualité des images</td><td style="border:1px solid #ddd; padding:8px;">' . get_option('astro_image_quality', 'medium') . '</td></tr>';
        $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">Afficher métadonnées</td><td style="border:1px solid #ddd; padding:8px;">' . (get_option('astro_show_metadata', 1) ? '✅ OUI' : '❌ NON') . '</td></tr>';
        $output .= '</table>';
        
        if (!empty($images)) {
            $test_image = $images[0];
            $output .= '<h4>Test Image ID: ' . $test_image->ID . ' - ' . get_the_title($test_image->ID) . '</h4>';
            
            // Test métadonnées
            $metadata = $this->get_image_metadata($test_image->ID);
            $post_object = get_post_meta($test_image->ID, 'astro_object_name', true);
            $post_date = get_post_meta($test_image->ID, 'astro_shooting_date', true);
            
            $output .= '<table style="width:100%; border-collapse: collapse;">';
            $output .= '<tr><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Source</th><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Objet</th><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Date</th><th style="border:1px solid #ddd; padding:8px; background:#f0f0f0;">Télescope</th></tr>';
            $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">post_meta direct</td><td style="border:1px solid #ddd; padding:8px;">' . ($post_object ?: '<em>vide</em>') . '</td><td style="border:1px solid #ddd; padding:8px;">' . ($post_date ?: '<em>vide</em>') . '</td><td style="border:1px solid #ddd; padding:8px;">-</td></tr>';
            $output .= '<tr><td style="border:1px solid #ddd; padding:8px;">get_image_metadata()</td><td style="border:1px solid #ddd; padding:8px;">' . ($metadata['object_name'] ?? '<em>vide</em>') . '</td><td style="border:1px solid #ddd; padding:8px;">' . ($metadata['acquisition_dates'] ?? '<em>vide</em>') . '</td><td style="border:1px solid #ddd; padding:8px;">' . ($metadata['telescope_model'] ?? '<em>vide</em>') . '</td></tr>';
            $output .= '</table>';
            
            // Test de cache
            $cache_key = 'astro_metadata_' . $test_image->ID;
            $cached = wp_cache_get($cache_key);
            $output .= '<p><strong>Cache:</strong> ' . ($cached ? 'Présent' : 'Absent') . '</p>';
            
        } else {
            $output .= '<p style="color: orange;">⚠️ Aucune image AstroFolio trouvée. Uploadez une image via AstroFolio > Upload Image</p>';
        }
        
        $output .= '<p><em>Ce debug n\'est visible que pour les administrateurs avec ?astro_debug=1 dans l\'URL</em></p>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Test ultra simple - juste compter les images
     */
    public function simple_test_shortcode($atts) {
        ob_start();
        ?>
        <div style="background: #e8f4f8; padding: 20px; border: 2px solid #17a2b8; margin: 20px 0;">
            <h3>🔧 Test Ultra-Simple</h3>
            
            <?php
            // Test 1 : Combien d'images au total ?
            $total_images = wp_count_attachments('image');
            $total_count = $total_images->{'image/jpeg'} + $total_images->{'image/png'} + $total_images->{'image/gif'};
            echo "<strong>Images totales :</strong> {$total_count}<br>";
            
            // Test 2 : Combien ont déjà le meta _astrofolio_image ?
            $marked_images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                ]]
            ]);
            echo "<strong>Images déjà marquées AstroFolio :</strong> " . count($marked_images) . "<br>";
            
            // Test 3 : Combien n'ont PAS le meta ?
            $unmarked_images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                ]]
            ]);
            echo "<strong>Images NON marquées :</strong> " . count($unmarked_images) . "<br>";
            
            // Test 4 : Est-ce qu'on peut en détecter comme astro ?
            if (!empty($unmarked_images)) {
                $test_id = $unmarked_images[0];
                $test_post = get_post($test_id);
                echo "<strong>Test sur image :</strong> {$test_post->post_title}<br>";
                
                $is_astro = $this->detect_astro_image($test_id);
                echo "<strong>Détection astro :</strong> " . ($is_astro ? "✅ OUI" : "❌ NON") . "<br>";
            }
            ?>
            
            <div style="margin-top: 15px; padding: 10px; background: #fff; border-left: 4px solid #17a2b8;">
                <strong>Actions possibles :</strong><br>
                • Si vous avez des images non marquées, utilisez <code>[astro_test_recovery]</code><br>
                • Pour récupérer directement : <code>[astro_recover_images mode="execute"]</code><br>
                • Page de maintenance : AstroFolio > Maintenance
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode de test pour le système de récupération
     */
    public function test_recovery_shortcode($atts) {
        ob_start();
        ?>
        <div style="background: #f0f8ff; padding: 20px; border: 2px solid #007cba; margin: 20px 0;">
            <h3>🔍 Test du Système de Récupération</h3>
            
            <div style="margin-bottom: 15px;">
                <strong>Étape 1 : Vérification des fonctions</strong><br>
                <?php
                if (method_exists($this, 'get_recovery_candidate_count')) {
                    echo '✅ get_recovery_candidate_count() existe<br>';
                } else {
                    echo '❌ get_recovery_candidate_count() MANQUANTE<br>';
                }
                
                if (method_exists($this, 'detect_astro_image')) {
                    echo '✅ detect_astro_image() existe<br>';
                } else {
                    echo '❌ detect_astro_image() MANQUANTE<br>';
                }
                
                if (method_exists($this, 'extract_object_name')) {
                    echo '✅ extract_object_name() existe<br>';
                } else {
                    echo '❌ extract_object_name() MANQUANTE<br>';
                }
                ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Étape 2 : Test de comptage</strong><br>
                <?php
                try {
                    $count = $this->get_recovery_candidate_count();
                    echo "✅ Nombre de candidats : {$count}<br>";
                } catch (Exception $e) {
                    echo "❌ Erreur lors du comptage : " . $e->getMessage() . "<br>";
                }
                ?>
            </div>

            <div style="margin-bottom: 15px;">
                <strong>Étape 3 : Test sur une image</strong><br>
                <?php
                $args = array(
                    'post_type' => 'attachment',
                    'posts_per_page' => 1,
                    'post_status' => 'inherit',
                    'post_mime_type' => 'image'
                );
                $images = get_posts($args);
                
                if (!empty($images)) {
                    $image = $images[0];
                    echo "Test sur : {$image->post_title}<br>";
                    
                    try {
                        $is_astro = $this->detect_astro_image($image->ID);
                        echo $is_astro ? "✅ Détectée comme image astro<br>" : "⚠️ Non détectée comme image astro<br>";
                    } catch (Exception $e) {
                        echo "❌ Erreur lors de la détection : " . $e->getMessage() . "<br>";
                    }
                } else {
                    echo "❌ Aucune image trouvée pour le test<br>";
                }
                ?>
            </div>

            <div style="background: #fff3cd; padding: 10px; border: 1px solid #ffeaa7;">
                <strong>📋 Instructions :</strong><br>
                1. Vérifiez que toutes les fonctions existent<br>
                2. Notez le nombre de candidats<br>
                3. Si tout semble normal mais la récupération échoue, consultez les logs WordPress<br>
                <strong>Logs disponibles :</strong> <code>wp-content/debug.log</code>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode de diagnostic pour comprendre pourquoi les images disparaissent
     */
    public function debug_images_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Diagnostic réservé aux administrateurs</p>';
        }
        
        global $wpdb;
        
        $output = '<div style="background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px;">';
        $output .= '<h2>🔍 DIAGNOSTIC IMAGES ASTROFOLIO</h2>';
        
        // 1. Compter TOUTES les images dans uploads
        $all_images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        $output .= '<h3>📊 Statistiques générales</h3>';
        $output .= '<p><strong>Total images WordPress :</strong> ' . count($all_images) . '</p>';
        
        // 2. Compter les images avec métadonnées astro
        $astro_images = $this->get_astro_images();
        $output .= '<p><strong>Images avec métadonnées AstroFolio :</strong> ' . count($astro_images) . '</p>';
        
        // 3. Analyser les métadonnées spécifiques
        $meta_counts = [];
        $meta_keys = ['astro_object_name', 'astro_shooting_date', 'astro_telescope', 'astro_camera', '_astrofolio_image'];
        
        foreach ($meta_keys as $key) {
            $count = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(DISTINCT post_id) 
                FROM {$wpdb->postmeta} pm 
                JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
                WHERE pm.meta_key = %s 
                AND p.post_type = 'attachment' 
                AND p.post_mime_type LIKE 'image%'
            ", $key));
            $meta_counts[$key] = intval($count);
        }
        
        $output .= '<h3>🏷️ Métadonnées par type</h3>';
        $output .= '<table style="border-collapse: collapse; width: 100%;">';
        $output .= '<tr style="background: #f8f9fa;"><th style="border: 1px solid #ddd; padding: 8px;">Métadonnée</th><th style="border: 1px solid #ddd; padding: 8px;">Nombre d\'images</th></tr>';
        foreach ($meta_counts as $key => $count) {
            $output .= '<tr><td style="border: 1px solid #ddd; padding: 8px;"><code>' . $key . '</code></td><td style="border: 1px solid #ddd; padding: 8px;">' . $count . '</td></tr>';
        }
        $output .= '</table>';
        
        // 4. Exemples d'images récentes avec métadonnées
        $output .= '<h3>🖼️ Exemples d\'images récentes</h3>';
        if (count($astro_images) > 0) {
            $output .= '<table style="border-collapse: collapse; width: 100%;">';
            $output .= '<tr style="background: #f8f9fa;"><th style="border: 1px solid #ddd; padding: 8px;">ID</th><th style="border: 1px solid #ddd; padding: 8px;">Titre</th><th style="border: 1px solid #ddd; padding: 8px;">Objet</th><th style="border: 1px solid #ddd; padding: 8px;">Date</th></tr>';
            
            for ($i = 0; $i < min(5, count($astro_images)); $i++) {
                $image = $astro_images[$i];
                $object_name = get_post_meta($image->ID, 'astro_object_name', true);
                $shooting_date = get_post_meta($image->ID, 'astro_shooting_date', true);
                
                $output .= '<tr>';
                $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $image->ID . '</td>';
                $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($image->post_title) . '</td>';
                $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($object_name ?: 'Non défini') . '</td>';
                $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($shooting_date ?: 'Non définie') . '</td>';
                $output .= '</tr>';
            }
            $output .= '</table>';
        } else {
            $output .= '<p><strong>❌ Aucune image trouvée avec métadonnées AstroFolio</strong></p>';
            $output .= '<p>Cela explique pourquoi la galerie est vide !</p>';
        }
        
        // 5. Check pour les images orphelines (dans uploads mais sans métadonnées)
        $orphaned = count($all_images) - count($astro_images);
        if ($orphaned > 0) {
            $output .= '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; margin: 15px 0; border-radius: 5px;">';
            $output .= '<h3>⚠️ Images potentiellement perdues</h3>';
            $output .= '<p><strong>' . $orphaned . ' images</strong> sont présentes dans WordPress mais n\'ont pas de métadonnées AstroFolio.</p>';
            $output .= '<p>Ces images ont probablement perdu leurs métadonnées lors de la mise à jour.</p>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode pour récupérer automatiquement les images "perdues"
     */
    public function recover_images_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>Récupération réservée aux administrateurs</p>';
        }
        
        global $wpdb;
        
        // Mode : preview (par défaut) ou execute
        $mode = sanitize_text_field($atts['mode'] ?? 'preview');
        
        $output = '<div style="background: #d1ecf1; border: 2px solid #bee5eb; padding: 20px; margin: 20px 0; border-radius: 8px;">';
        $output .= '<h2>🔧 RÉCUPÉRATION IMAGES ASTROFOLIO</h2>';
        
        // 1. Trouver toutes les images sans métadonnées AstroFolio
        $all_images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        $output .= '<p><strong>Images candidates à la récupération :</strong> ' . count($all_images) . '</p>';
        
        if ($mode === 'execute') {
            $recovered = 0;
            $skipped = 0;
            
            foreach ($all_images as $image) {
                // Critères de détection d'images astro (ajustables)
                $is_astro = $this->detect_astro_image($image);
                
                if ($is_astro) {
                    // Marquer comme image AstroFolio
                    update_post_meta($image->ID, '_astrofolio_image', '1');
                    
                    // Essayer de récupérer/deviner certaines métadonnées
                    $this->recover_image_metadata($image);
                    
                    $recovered++;
                } else {
                    $skipped++;
                }
            }
            
            $output .= '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 5px;">';
            $output .= '<h3>✅ RÉCUPÉRATION TERMINÉE</h3>';
            $output .= '<p><strong>Images récupérées :</strong> ' . $recovered . '</p>';
            $output .= '<p><strong>Images ignorées :</strong> ' . $skipped . '</p>';
            $output .= '</div>';
            
        } else {
            // Mode preview : montrer ce qui serait récupéré
            $output .= '<h3>📋 APERÇU (mode preview)</h3>';
            $output .= '<p><em>Utilisez [astro_recover_images mode="execute"] pour effectuer la récupération.</em></p>';
            
            if (count($all_images) > 0) {
                $output .= '<table style="border-collapse: collapse; width: 100%; margin: 10px 0;">';
                $output .= '<tr style="background: #f8f9fa;"><th style="border: 1px solid #ddd; padding: 8px;">ID</th><th style="border: 1px solid #ddd; padding: 8px;">Titre</th><th style="border: 1px solid #ddd; padding: 8px;">Nom fichier</th><th style="border: 1px solid #ddd; padding: 8px;">Action</th></tr>';
                
                for ($i = 0; $i < min(10, count($all_images)); $i++) {
                    $image = $all_images[$i];
                    $is_astro = $this->detect_astro_image($image);
                    $filename = basename(get_attached_file($image->ID));
                    
                    $output .= '<tr>';
                    $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $image->ID . '</td>';
                    $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($image->post_title) . '</td>';
                    $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($filename) . '</td>';
                    $output .= '<td style="border: 1px solid #ddd; padding: 8px;">' . ($is_astro ? '✅ Récupérer' : '⏭️ Ignorer') . '</td>';
                    $output .= '</tr>';
                }
                
                if (count($all_images) > 10) {
                    $output .= '<tr><td colspan="4" style="text-align: center; padding: 10px; font-style: italic;">... et ' . (count($all_images) - 10) . ' autres images</td></tr>';
                }
                
                $output .= '</table>';
            }
        }
        
        $output .= '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 15px 0; border-radius: 5px;">';
        $output .= '<h4>💡 Comment ça fonctionne ?</h4>';
        $output .= '<p>Cette fonction recherche les images qui peuvent être des photos d\'astrophotographie basé sur :</p>';
        $output .= '<ul>';
        $output .= '<li>Noms de fichiers contenant des termes astro (M31, NGC, IC, etc.)</li>';
        $output .= '<li>Titres d\'images avec des références astronomiques</li>';
        $output .= '<li>Images dans des dossiers spécifiques</li>';
        $output .= '</ul>';
        $output .= '</div>';
        
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Détecter si une image est probablement une photo d'astrophotographie
     */
    private function detect_astro_image($image, $debug = false) {
        // Support ID ou objet image
        if (is_numeric($image)) {
            $image_id = $image;
            $image_post = get_post($image_id);
            if (!$image_post) return false;
            $title = $image_post->post_title;
        } else {
            $image_id = $image->ID;
            $title = $image->post_title;
        }
        
        $title = strtolower($title);
        $filename = strtolower(basename(get_attached_file($image_id)));
        
        if ($debug) {
            error_log("AstroFolio DEBUG - Image ID: $image_id, Titre: '$title', Fichier: '$filename'");
        }
        
        // Mode permissif : si le titre ou fichier contient certains mots, on accepte
        $permissive_keywords = [
            'dso', 'deep', 'sky', 'space', 'night', 'star', 'astro', 'telescope',
            'nuit', 'étoile', 'ciel', 'cosmos', 'univers'
        ];
        
        foreach ($permissive_keywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($filename, $keyword) !== false) {
                if ($debug) error_log("AstroFolio DEBUG - Détecté par mot-clé permissif: $keyword");
                return true;
            }
        }

        // Mots-clés astronomiques courants
        $astro_keywords = [
            // Catalogues
            'm31', 'm42', 'm57', 'm81', 'm104', 'messier',
            'ngc', 'ic', 'caldwell', 'sh2', 'sharpless',
            // Objets célèbres
            'andromeda', 'orion', 'horsehead', 'eagle', 'rosette',
            'crab', 'whirlpool', 'sombrero', 'veil', 'north america',
            'ring', 'cat\'s eye', 'helix', 'dumbbell', 'pleiades',
            // Termes techniques
            'nebula', 'galaxy', 'cluster', 'supernova', 'planetary',
            'emission', 'reflection', 'dark', 'globular', 'open',
            'binary', 'double', 'variable', 'comet', 'asteroid',
            // Termes français
            'nébuleuse', 'galaxie', 'amas', 'étoile', 'planétaire',
            'comète', 'astéroïde', 'binaire', 'variable',
            // Mots-clés génériques d'astrophotographie
            'astro', 'astrophoto', 'astrophotography', 'deepsky', 
            'deep sky', 'space', 'cosmos', 'universe', 'milky way',
            'voie lactée', 'telescope', 'télescope', 'observatory',
            'observatoire', 'starfield', 'stars', 'étoiles',
            // Termes de traitement d'image astro
            'stack', 'stacked', 'calibrated', 'processed', 'hdr',
            'narrowband', 'broadband', 'luminance', 'rgb', 'lrgb',
            'ha', 'oiii', 'sii', 'hubble', 'palette'
        ];
        
        // Vérifier titre et nom de fichier
        foreach ($astro_keywords as $keyword) {
            if (strpos($title, $keyword) !== false || strpos($filename, $keyword) !== false) {
                if ($debug) error_log("AstroFolio DEBUG - Détecté par mot-clé astro: $keyword");
                return true;
            }
        }
        
        // Patterns de catalogues (M31, NGC2024, IC1805, etc.)
        $catalog_patterns = [
            '/\bm\s*\d+\b/',           // M31, M 42
            '/\bngc\s*\d+\b/',         // NGC2024
            '/\bic\s*\d+\b/',          // IC1805
            '/\bsh2[\s-]*\d+\b/',      // SH2-155
            '/\bcaldwell\s*\d+\b/',    // Caldwell 49
        ];
        
        foreach ($catalog_patterns as $pattern) {
            if (preg_match($pattern, $title) || preg_match($pattern, $filename)) {
                if ($debug) error_log("AstroFolio DEBUG - Détecté par pattern catalogue: $pattern");
                return true;
            }
        }
        
        // Patterns supplémentaires pour les noms de fichiers typiques d'astrophoto
        $astro_filename_patterns = [
            '/.*astro.*/',
            '/.*deep.*sky.*/',
            '/.*_stack.*/',
            '/.*_processed.*/',
            '/.*_final.*/',
            '/.*light.*frame.*/',
            '/.*darks?.*/',
            '/.*flats?.*/',
            '/.*bias.*/',
            '/.*calibrated.*/',
            '/.*dso.*/',  // Deep Sky Object
            '/.*_l_.*/',  // Luminance
            '/.*_rgb.*/', // RGB composite
            '/.*_ha.*/',  // Hydrogen Alpha
            '/.*_oiii.*/', // OIII
        ];
        
        foreach ($astro_filename_patterns as $pattern) {
            if (preg_match($pattern, $filename)) {
                if ($debug) error_log("AstroFolio DEBUG - Détecté par pattern fichier: $pattern");
                return true;
            }
        }
        
        // Si le titre contient des coordonnées célestes
        if (preg_match('/\b\d{1,2}h\s*\d{1,2}m?\b/', $title) || // RA format
            preg_match('/[+-]?\d{1,2}°?\s*\d{1,2}\'?/', $title)) { // DEC format
            if ($debug) error_log("AstroFolio DEBUG - Détecté par coordonnées célestes");
            return true;
        }

        // Mode ultra-permissif : si l'image a été uploadée la nuit (entre 19h et 7h)
        $upload_hour = intval(get_post_field('post_date', $image_id) ? date('H', strtotime(get_post_field('post_date', $image_id))) : 12);
        if ($upload_hour >= 19 || $upload_hour <= 7) {
            if ($debug) error_log("AstroFolio DEBUG - Détecté par upload nocturne ({$upload_hour}h)");
            return true;
        }

        if ($debug) error_log("AstroFolio DEBUG - Aucune détection pour cette image");
        return false;
    }
    
    /**
     * Essayer de récupérer/deviner les métadonnées d'une image
     */
    private function recover_image_metadata($image) {
        // Support ID ou objet image
        if (is_numeric($image)) {
            $image_id = $image;
            $image_post = get_post($image_id);
            if (!$image_post) return false;
            $title = $image_post->post_title;
        } else {
            $image_id = $image->ID;
            $title = $image->post_title;
        }
        
        $filename = basename(get_attached_file($image_id));
        
        // Essayer d'extraire le nom d'objet du titre/filename
        $object_name = $this->extract_object_name($title . ' ' . $filename);
        if ($object_name) {
            update_post_meta($image_id, 'astro_object_name', $object_name);
        }
        
        // Date de prise de vue = date d'upload si pas d'autre info
        $upload_date = get_post_field('post_date', $image_id);
        if ($upload_date) {
            update_post_meta($image_id, 'astro_shooting_date', $upload_date);
        }
        
        // Marquer comme récupéré automatiquement
        update_post_meta($image_id, '_astrofolio_recovered', current_time('mysql'));
    }
    
    /**
     * Extraire le nom d'objet céleste d'un texte
     */
    private function extract_object_name($text) {
        $text = strtolower($text);
        
        // Patterns pour catalogues
        $patterns = [
            '/\b(m\s*\d+)\b/' => 'Messier',
            '/\b(ngc\s*\d+)\b/' => 'NGC',
            '/\b(ic\s*\d+)\b/' => 'IC',
            '/\b(sh2[\s-]*\d+)\b/' => 'Sharpless',
        ];
        
        foreach ($patterns as $pattern => $catalog) {
            if (preg_match($pattern, $text, $matches)) {
                return strtoupper(str_replace(' ', '', $matches[1]));
            }
        }
        
        // Objets célèbres
        $famous_objects = [
            'andromeda' => 'M31 - Galaxie d\'Andromède',
            'orion' => 'M42 - Nébuleuse d\'Orion', 
            'horsehead' => 'B33 - Nébuleuse de la Tête de Cheval',
            'eagle' => 'M16 - Nébuleuse de l\'Aigle',
            'rosette' => 'NGC2237 - Nébuleuse de la Rosette',
        ];
        
        foreach ($famous_objects as $keyword => $name) {
            if (strpos($text, $keyword) !== false) {
                return $name;
            }
        }
        
        return '';
    }
    
    // Shortcode pour la page de détail d'une image (intégré dans le thème)
    public function image_detail_shortcode($atts) {
        $image_id = isset($_GET['image_id']) ? intval($_GET['image_id']) : 0;
        
        if (!$image_id) {
            return '<div class="astro-error">
                        <p><strong>❌ Erreur :</strong> ID d\'image manquant.</p>
                        <a href="/🌟-astrofolio-galerie/" class="button">← Retour à la galerie</a>
                    </div>';
        }
        
        $image = get_post($image_id);
        if (!$image) {
            return '<div class="astro-error">
                        <p><strong>❌ Erreur :</strong> Image non trouvée.</p>
                        <a href="/🌟-astrofolio-galerie/" class="button">← Retour à la galerie</a>
                    </div>';
        }
        
        // Récupérer les métadonnées avec notre fonction corrigée
        $metadata = $this->get_image_metadata($image_id);
        
        // Enqueue des scripts/styles
        $this->enqueue_public_scripts();
        
        // URLs et données de base
        $image_url = wp_get_attachment_image_url($image_id, 'large');
        $full_image_url = wp_get_attachment_image_url($image_id, 'full');
        $current_url = get_permalink() . '?image_id=' . $image_id;
        
        // Description optimisée pour SEO
        $description = $this->generate_seo_description($image->post_title, $metadata);
        
        // Ajouter les métadonnées dans le head
        add_action('wp_head', function() use ($image, $metadata, $image_url, $current_url, $description) {
            $this->add_seo_meta_tags($image, $metadata, $image_url, $current_url, $description);
        });
        
        $output = '';
        
        // JSON-LD Structured Data
        $output .= $this->generate_json_ld_schema($image, $metadata, $image_url, $full_image_url, $current_url);
        
        // Article principal avec structure sémantique
        $output .= '<article class="astro-image-detail" itemscope itemtype="https://schema.org/Photograph">';
        
        // En-tête sémantique
        $output .= '<header class="detail-header">';
        $output .= '<h1 class="image-title" itemprop="name">' . esc_html($image->post_title) . '</h1>';
        $output .= '<nav class="detail-nav" aria-label="Navigation">';
        $output .= '<a href="/🌟-astrofolio-galerie/" class="button button-secondary" rel="up">← Retour à la galerie</a>';
        $output .= '</nav>';
        $output .= '</header>';
        
        // Image principale avec métadonnées sémantiques
        $output .= '<figure class="main-image" itemprop="image" itemscope itemtype="https://schema.org/ImageObject">';
        
        $alt_text = $this->generate_alt_text($image->post_title, $metadata);
        $output .= '<a href="' . esc_url($full_image_url) . '" class="lightbox-trigger" itemprop="contentUrl">';
        $output .= wp_get_attachment_image($image_id, 'large', false, array(
            'class' => 'detail-image',
            'alt' => $alt_text,
            'title' => esc_attr($image->post_title),
            'itemprop' => 'url'
        ));
        $output .= '<div class="image-overlay" aria-hidden="true">';
        $output .= '<span class="zoom-icon">🔍 Cliquer pour agrandir</span>';
        $output .= '</div>';
        $output .= '</a>';
        
        // Caption sémantique
        if (!empty($metadata['object_name'])) {
            $output .= '<figcaption itemprop="caption">';
            $output .= 'Photographie astronomique de ' . esc_html($metadata['object_name']);
            if (!empty($metadata['acquisition_dates'])) {
                $output .= ' prise le ' . esc_html($metadata['acquisition_dates']);
            }
            $output .= '</figcaption>';
        }
        $output .= '</figure>';
        
        // Section des métadonnées techniques
        $output .= '<section class="metadata-section" aria-labelledby="tech-details">';
        $output .= '<h2 id="tech-details">Détails techniques de la photographie</h2>';
        
        if (!empty($metadata) && array_filter($metadata)) {
            $output .= $this->render_seo_optimized_metadata($metadata);
        } else {
            $output .= '<p class="no-metadata">Aucune métadonnée technique disponible pour cette image.</p>';
        }
        
        $output .= '</section>';
        
        // Breadcrumb sémantique
        $output .= '<nav aria-label="Fil d\'Ariane" class="breadcrumb">';
        $output .= '<ol itemscope itemtype="https://schema.org/BreadcrumbList">';
        $output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        $output .= '<a itemprop="item" href="/"><span itemprop="name">Accueil</span></a>';
        $output .= '<meta itemprop="position" content="1" />';
        $output .= '</li>';
        $output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        $output .= '<a itemprop="item" href="/🌟-astrofolio-galerie/"><span itemprop="name">Galerie Astro</span></a>';
        $output .= '<meta itemprop="position" content="2" />';
        $output .= '</li>';
        $output .= '<li itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">';
        $output .= '<span itemprop="name">' . esc_html($image->post_title) . '</span>';
        $output .= '<meta itemprop="position" content="3" />';
        $output .= '</li>';
        $output .= '</ol>';
        $output .= '</nav>';
        
        $output .= '</article>';
        
        return $output;
    }
    
    // Shortcode pour afficher les statistiques
    public function stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show' => 'images,objects',
            'style' => 'cards'
        ), $atts);
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_stats($atts);
        return ob_get_clean();
    }
    
    // Shortcode pour le formulaire de recherche
    public function search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Rechercher dans la galerie...',
            'button_text' => 'Rechercher'
        ), $atts);
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_search_form($atts);
        return ob_get_clean();
    }
    
    // Shortcode pour afficher des images aléatoires
    public function random_shortcode($atts) {
        $atts = shortcode_atts(array(
            'count' => 3,
            'columns' => 3,
            'size' => 'medium'
        ), $atts);
        
        ob_start();
        $this->enqueue_public_scripts();
        echo $this->render_random_images($atts);
        return ob_get_clean();
    }
    
    // === FONCTIONS HELPER POUR LE SEO ===
    
    private function generate_seo_description($title, $metadata) {
        $description = 'Photographie astronomique : ' . $title;
        
        if (!empty($metadata['object_name'])) {
            $description .= ' - Image de ' . $metadata['object_name'];
        }
        
        $equipment = array();
        if (!empty($metadata['telescope_brand'])) {
            $equipment[] = $metadata['telescope_brand'] . (!empty($metadata['telescope_model']) ? ' ' . $metadata['telescope_model'] : '');
        }
        if (!empty($metadata['camera_brand'])) {
            $equipment[] = $metadata['camera_brand'] . (!empty($metadata['camera_model']) ? ' ' . $metadata['camera_model'] : '');
        }
        
        if (!empty($equipment)) {
            $description .= ' capturée avec ' . implode(' et ', $equipment);
        }
        
        if (!empty($metadata['acquisition_dates'])) {
            $description .= ' le ' . $metadata['acquisition_dates'];
        }
        
        return substr($description, 0, 160);
    }
    
    private function add_seo_meta_tags($image, $metadata, $image_url, $current_url, $description) {
        echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
        
        // Open Graph
        echo '<meta property="og:type" content="article">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr($image->post_title) . '">' . "\n";
        echo '<meta property="og:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta property="og:image" content="' . esc_url($image_url) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url($current_url) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . get_bloginfo('name') . '">' . "\n";
        
        // Twitter Cards
        echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr($image->post_title) . '">' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta name="twitter:image" content="' . esc_url($image_url) . '">' . "\n";
        
        // Canonical URL
        echo '<link rel="canonical" href="' . esc_url($current_url) . '">' . "\n";
        
        // Schema.org basic
        echo '<meta itemprop="name" content="' . esc_attr($image->post_title) . '">' . "\n";
        echo '<meta itemprop="description" content="' . esc_attr($description) . '">' . "\n";
        echo '<meta itemprop="image" content="' . esc_url($image_url) . '">' . "\n";
    }
    
    private function generate_alt_text($title, $metadata) {
        $alt = 'Photo astronomique : ' . $title;
        
        if (!empty($metadata['object_name']) && $metadata['object_name'] !== $title) {
            $alt = 'Photo astronomique de ' . $metadata['object_name'] . ' (' . $title . ')';
        }
        
        // Ajouter l'équipement si pertinent
        if (!empty($metadata['telescope_brand']) && !empty($metadata['camera_brand'])) {
            $alt .= ' prise avec ' . $metadata['telescope_brand'] . ' et ' . $metadata['camera_brand'];
        }
        
        return $alt;
    }
    
    private function generate_json_ld_schema($image, $metadata, $image_url, $full_image_url, $current_url) {
        $schema = array(
            '@context' => 'https://schema.org',
            '@type' => 'Photograph',
            'name' => $image->post_title,
            'url' => $current_url,
            'contentUrl' => $full_image_url,
            'thumbnailUrl' => $image_url,
            'dateCreated' => $image->post_date,
            'author' => array(
                '@type' => 'Person',
                'name' => get_the_author_meta('display_name', $image->post_author)
            )
        );
        
        // Ajouter des données spécifiques à l'astronomie
        if (!empty($metadata['object_name'])) {
            $schema['about'] = array(
                '@type' => 'Thing',
                'name' => $metadata['object_name'],
                'description' => 'Objet céleste photographié'
            );
        }
        
        if (!empty($metadata['acquisition_dates'])) {
            $schema['dateCreated'] = $metadata['acquisition_dates'];
        }
        
        // Équipement utilisé
        $equipment = array();
        if (!empty($metadata['telescope_brand'])) {
            $equipment[] = array(
                '@type' => 'Product',
                'name' => $metadata['telescope_brand'] . (!empty($metadata['telescope_model']) ? ' ' . $metadata['telescope_model'] : ''),
                'category' => 'Télescope'
            );
        }
        if (!empty($metadata['camera_brand'])) {
            $equipment[] = array(
                '@type' => 'Product', 
                'name' => $metadata['camera_brand'] . (!empty($metadata['camera_model']) ? ' ' . $metadata['camera_model'] : ''),
                'category' => 'Caméra'
            );
        }
        
        if (!empty($equipment)) {
            $schema['instrument'] = $equipment;
        }
        
        // Lieu de prise de vue
        if (!empty($metadata['location_name'])) {
            $schema['contentLocation'] = array(
                '@type' => 'Place',
                'name' => $metadata['location_name']
            );
            
            if (!empty($metadata['location_coords'])) {
                $coords = explode(',', $metadata['location_coords']);
                if (count($coords) == 2) {
                    $schema['contentLocation']['geo'] = array(
                        '@type' => 'GeoCoordinates',
                        'latitude' => trim($coords[0]),
                        'longitude' => trim($coords[1])
                    );
                }
            }
        }
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>' . "\n";
    }
    
    private function render_seo_optimized_metadata($metadata) {
        // Utiliser la fonction existante mais avec des améliorations SEO
        $html = $this->render_detailed_metadata($metadata);
        
        // Ajouter des microdatas supplémentaires
        $html = str_replace('<div class="detailed-metadata-sections">', '<div class="detailed-metadata-sections" itemscope itemtype="https://schema.org/TechArticle">', $html);
        
        return $html;
    }
    
    // === MASQUAGE DES IMAGES ASTROFOLIO DE WORDPRESS ===
    
    public function hide_astrofolio_images_from_library($query) {
        // Ne pas masquer si on est dans l'admin AstroFolio
        if (is_admin() && isset($_GET['page']) && strpos($_GET['page'], 'astrofolio') !== false) {
            return $query;
        }
        
        // Ajouter le filtre pour exclure les images AstroFolio
        if (!isset($query['meta_query'])) {
            $query['meta_query'] = array();
        }
        
        $query['meta_query'][] = array(
            'key' => '_astrofolio_image',
            'compare' => 'NOT EXISTS'
        );
        
        return $query;
    }
    
    public function hide_astrofolio_images_from_media_list($query) {
        // Seulement dans l'admin et pour la liste des médias
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        
        // Seulement sur la page de médias (pas dans AstroFolio)
        global $pagenow;
        if ($pagenow === 'upload.php' && !isset($_GET['page'])) {
            
            // Vérifier si l'utilisateur veut voir les images AstroFolio
            $show_astrofolio = isset($_GET['show_astrofolio']) && $_GET['show_astrofolio'] === '1';
            
            if (!$show_astrofolio) {
                $meta_query = $query->get('meta_query') ?: array();
                $meta_query[] = array(
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                );
                $query->set('meta_query', $meta_query);
            }
        }
    }
    
    public function add_media_library_notice() {
        global $pagenow;
        
        // Seulement sur la page de médias
        if ($pagenow === 'upload.php' && !isset($_GET['page'])) {
            $show_astrofolio = isset($_GET['show_astrofolio']) && $_GET['show_astrofolio'] === '1';
            $astrofolio_count = $this->count_astrofolio_images();
            
            if ($astrofolio_count > 0) {
                if (!$show_astrofolio) {
                    echo '<div class="notice notice-info is-dismissible">';
                    echo '<p><strong>🔭 AstroFolio:</strong> ' . $astrofolio_count . ' image(s) astrophoto masquée(s) pour éviter l\'encombrement. ';
                    echo '<a href="' . add_query_arg('show_astrofolio', '1') . '" class="button button-small">Afficher les images AstroFolio</a> ';
                    echo '<a href="' . admin_url('admin.php?page=astrofolio-metadata') . '" class="button button-primary button-small">Gérer dans AstroFolio</a>';
                    echo '</p></div>';
                } else {
                    echo '<div class="notice notice-success is-dismissible">';
                    echo '<p><strong>🌌 Images AstroFolio visibles</strong> (' . $astrofolio_count . ' trouvée(s)). ';
                    echo '<a href="' . remove_query_arg('show_astrofolio') . '" class="button button-small">Masquer à nouveau</a>';
                    echo '</p></div>';
                }
            }
        }
    }
    
    private function count_astrofolio_images() {
        $images = get_posts(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => '_astrofolio_image',
                    'value' => true,
                    'compare' => '='
                )
            )
        ));
        
        return count($images);
    }
    
    /**
     * Page de gestion publique intégrée
     */
    public function public_page() {
        // Traitement des actions
        $message = '';
        $message_type = '';
        
        if (isset($_POST['action']) && wp_verify_nonce($_POST['_wpnonce'] ?? '', 'astro_public_nonce')) {
            $action = sanitize_text_field($_POST['action']);
            
            switch ($action) {
                case 'save_settings':
                    $result = $this->save_public_settings();
                    break;
                case 'regenerate_thumbs':
                    $result = $this->regenerate_thumbnails();
                    break;
                case 'clear_cache':
                    $result = $this->clear_public_cache();
                    break;
                case 'export_settings':
                    $result = $this->export_public_settings();
                    break;
                case 'create_integration_pages':
                    $this->create_theme_integration_pages();
                    $result = array('success' => true, 'message' => 'Pages d\'intégration créées avec succès !');
                    break;
                default:
                    $result = array('success' => false, 'message' => 'Action non reconnue');
            }
            
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
        
        // Récupérer les images pour l'aperçu
        $recent_images = $this->get_recent_astro_images(6);
        $popular_objects = $this->get_popular_objects();
        
        ?>
        <div class="wrap">
            <h1>🌌 Gestion de l'Affichage Public</h1>
            
            <div class="notice notice-info">
                <h3>📋 Intégration WordPress Automatique</h3>
                <p><strong>Pages créées automatiquement :</strong></p>
                <ul>
                    <li>🌌 <strong>Galerie :</strong> <?php 
                        $gallery_page_id = get_option('astrofolio_gallery_page');
                        if ($gallery_page_id && get_post($gallery_page_id)) {
                            echo '<a href="' . get_edit_post_link($gallery_page_id) . '" target="_blank">Page Galerie</a> - <a href="' . get_permalink($gallery_page_id) . '" target="_blank">Voir ➚</a>';
                        } else {
                            echo 'Pas encore créée';
                        }
                    ?></li>
                    <li>🌟 <strong>Détail :</strong> <?php 
                        $detail_page_id = get_option('astrofolio_detail_page');
                        if ($detail_page_id && get_post($detail_page_id)) {
                            echo '<a href="' . get_edit_post_link($detail_page_id) . '" target="_blank">Page Détail</a> - <a href="' . get_permalink($detail_page_id) . '" target="_blank">Voir ➚</a>';
                        } else {
                            echo 'Pas encore créée';
                        }
                    ?></li>
                </ul>
                <p><em>Ces pages utilisent votre thème WordPress (header, menu, footer, sidebar, etc.)</em></p>
                <?php if (!get_option('astrofolio_gallery_page') || !get_option('astrofolio_detail_page')): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_public_nonce'); ?>">
                        <input type="hidden" name="action" value="create_integration_pages">
                        <button type="submit" class="button button-primary">🔧 Créer les pages d'intégration</button>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <style>
            .astro-public-admin {
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                border-radius: 12px;
                padding: 30px;
                margin: 20px 0;
                box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            }
            
            .astro-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .astro-stat-card {
                background: white;
                border-radius: 12px;
                padding: 25px;
                display: flex;
                align-items: center;
                gap: 15px;
                box-shadow: 0 4px 15px rgba(0,0,0,0.08);
                border-left: 4px solid #667eea;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
            }
            
            .astro-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 25px rgba(0,0,0,0.12);
            }
            
            .stat-icon { font-size: 2em; opacity: 0.8; }
            .stat-number { font-size: 2em; font-weight: 700; color: #667eea; }
            .stat-label { color: #6c757d; font-weight: 600; }
            
            .shortcode-examples {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            
            .shortcode-card {
                background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
                border: 2px solid #e9ecef;
                border-radius: 12px;
                padding: 20px;
                transition: all 0.2s ease;
            }
            
            .shortcode-card:hover {
                border-color: #667eea;
                transform: translateY(-1px);
            }
            
            .shortcode-card h3 {
                color: #667eea;
                margin-top: 0;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            
            .shortcode-card code {
                background: #f1f3f4;
                padding: 12px;
                border-radius: 6px;
                display: block;
                margin: 10px 0;
                word-break: break-all;
                font-family: 'Monaco', 'Menlo', monospace;
                border-left: 3px solid #667eea;
            }
            
            .copy-btn {
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                border: none;
                border-radius: 6px;
                padding: 8px 16px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            
            .copy-btn:hover {
                transform: scale(1.05);
                box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
            }
            
            .recent-images-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin: 20px 0;
            }
            
            .recent-image-card {
                background: white;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                transition: all 0.2s ease;
            }
            
            .recent-image-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            }
            
            .recent-image-card img {
                width: 100%;
                height: 150px;
                object-fit: contain;
                background: #f8f9fa;
                border-bottom: 1px solid #eee;
            }
            
            .image-info {
                padding: 10px;
                text-align: center;
            }
            
            .image-title {
                font-weight: bold;
                color: #333;
                margin-bottom: 4px;
            }
            
            .image-object {
                font-size: 0.9em;
                color: #666;
                background: #e8f4f8;
                padding: 2px 8px;
                border-radius: 10px;
                display: inline-block;
            }
            
            .tools-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 20px 0;
            }
            
            .tool-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                text-align: center;
            }
            
            .tool-icon {
                font-size: 3em;
                margin-bottom: 10px;
                display: block;
            }
            
            .popular-objects {
                background: #f0f8ff;
                border-radius: 8px;
                padding: 15px;
                margin: 15px 0;
            }
            
            .object-tag {
                display: inline-block;
                background: #667eea;
                color: white;
                padding: 4px 12px;
                border-radius: 15px;
                margin: 2px;
                font-size: 0.9em;
            }
            </style>
            
            <div class="astro-public-admin">
                <p class="description">Interface complète de gestion pour votre galerie publique d'astrophotographie. Gérez l'affichage, les paramètres et visualisez vos statistiques en temps réel.</p>
                
                <!-- Statistiques -->
                <div class="astro-stats-grid">
                    <div class="astro-stat-card">
                        <div class="stat-icon">📸</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $this->count_astro_images(); ?></div>
                            <div class="stat-label">Images d'astrophoto</div>
                        </div>
                    </div>
                    <div class="astro-stat-card">
                        <div class="stat-icon">🌟</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo $this->count_catalog_objects(); ?></div>
                            <div class="stat-label">Objets dans les catalogues</div>
                        </div>
                    </div>
                    <div class="astro-stat-card">
                        <div class="stat-icon">👁️</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo number_format(get_option('astro_total_views', 0)); ?></div>
                            <div class="stat-label">Vues totales</div>
                        </div>
                    </div>
                    <div class="astro-stat-card">
                        <div class="stat-icon">❤️</div>
                        <div class="stat-content">
                            <div class="stat-number"><?php echo get_option('astro_total_likes', 0); ?></div>
                            <div class="stat-label">Likes reçus</div>
                        </div>
                    </div>
                </div>
                
                <div style="background: #e6f3ff; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; margin: 20px 0;">
                    <h3>📊 À propos des Statistiques</h3>
                    <ul style="margin: 10px 0;">
                        <li><strong>Images d'astrophoto</strong> : <?php echo $this->count_astro_images() > 0 ? 'Images avec métadonnées astronomiques' : 'Total des images WordPress (pas encore de métadonnées spécialisées)'; ?></li>
                        <li><strong>Objets dans les catalogues</strong> : <?php 
                        $catalog_count = $this->count_catalog_objects();
                        if ($catalog_count > 0) {
                            echo 'Objets importés en base de données';
                        } else {
                            echo 'Aucun catalogue importé - allez dans "🔄 Catalogues" pour importer';
                        }
                        ?></li>
                        <li><strong>Vues/Likes</strong> : Statistiques publiques (<?php echo get_option('astro_total_views', 0) > 0 ? 'actives' : 'à activer dans les paramètres'; ?>)</li>
                    </ul>
                    <?php if ($catalog_count == 0): ?>
                    <p><strong>💡 Conseil :</strong> <a href="<?php echo admin_url('admin.php?page=astrofolio-catalogs'); ?>" class="button button-primary">Importer vos premiers catalogues</a></p>
                    <?php endif; ?>
                </div>
                
                <!-- Aperçu des images récentes -->
                <?php if (!empty($recent_images)): ?>
                <h2>📸 Images Récentes</h2>
                <div class="recent-images-grid">
                    <?php foreach ($recent_images as $image): ?>
                    <?php 
                        $image_id = $image->ID;
                        $title = get_the_title($image_id) ?: 'Sans titre';
                        $object_name = get_post_meta($image_id, 'astro_object_name', true);
                        $thumbnail = wp_get_attachment_image_src($image_id, 'medium')[0];
                        $edit_url = admin_url('admin.php?page=astrofolio-metadata&image_id=' . $image_id);
                    ?>
                    <div class="recent-image-card">
                        <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($title); ?>">
                        <div class="image-info">
                            <div class="image-title"><?php echo esc_html($title); ?></div>
                            <?php if ($object_name): ?>
                                <div class="image-object"><?php echo esc_html($object_name); ?></div>
                            <?php endif; ?>
                            <div style="margin-top: 8px;">
                                <a href="<?php echo $edit_url; ?>" class="button button-small">✏️ Modifier</a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-manage-images'); ?>" class="button button-primary">
                        🖼️ Voir toutes les images (<?php echo $this->count_astro_images(); ?>)
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Objets populaires -->
                <?php if (!empty($popular_objects)): ?>
                <div class="popular-objects">
                    <h3>🌟 Objets les plus photographiés</h3>
                    <?php foreach ($popular_objects as $obj): ?>
                        <span class="object-tag"><?php echo esc_html($obj->object_name); ?> (<?php echo $obj->count; ?>)</span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                
                <h2>📝 Guide des Shortcodes</h2>
                <p>Utilisez ces shortcodes pour intégrer votre galerie dans vos posts et pages.</p>
                
                <div class="shortcode-examples">
                    <div class="shortcode-card">
                        <h3>🖼️ Galerie complète</h3>
                        <code>[astro_gallery limit="12" columns="4"]</code>
                        <p>Affiche une galerie avec 12 images sur 4 colonnes.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_gallery limit=&quot;12&quot; columns=&quot;4&quot;]')">📋 Copier</button>
                    </div>
                    
                    <div class="shortcode-card">
                        <h3>🎯 Image spécifique</h3>
                        <code>[astro_image id="123" size="large"]</code>
                        <p>Affiche une image spécifique avec ses métadonnées.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_image id=&quot;&quot; size=&quot;large&quot;]')">📋 Copier</button>
                    </div>
                    
                    <div class="shortcode-card">
                        <h3>🌟 Objet céleste</h3>
                        <code>[astro_object name="M31" show_info="true"]</code>
                        <p>Affiche toutes les images d'un objet spécifique.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_object name=&quot;&quot;]')">📋 Copier</button>
                    </div>
                    
                    <div class="shortcode-card">
                        <h3>📊 Statistiques</h3>
                        <code>[astro_stats show="images,objects"]</code>
                        <p>Affiche les statistiques de votre collection.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_stats]')">📋 Copier</button>
                    </div>
                    
                    <div class="shortcode-card">
                        <h3>🔍 Recherche</h3>
                        <code>[astro_search placeholder="Rechercher..."]</code>
                        <p>Formulaire de recherche dans la galerie.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_search]')">📋 Copier</button>
                    </div>
                    
                    <div class="shortcode-card">
                        <h3>🎲 Images aléatoires</h3>
                        <code>[astro_random count="3"]</code>
                        <p>Affiche des images aléatoires.</p>
                        <button class="copy-btn" onclick="copyToClipboard('[astro_random count=&quot;3&quot;]')">📋 Copier</button>
                    </div>
                </div>
                
                <h2>⚙️ Paramètres de la Galerie</h2>
                <form method="post" action="">
                    <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_public_nonce'); ?>">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="astro_images_per_page">Images par page</label>
                                <p class="description">Nombre d'images affichées dans la galerie publique</p>
                            </th>
                            <td>
                                <input type="number" id="astro_images_per_page" name="astro_images_per_page" 
                                       value="<?php echo get_option('astro_images_per_page', 12); ?>" 
                                       min="1" max="100" class="small-text" />
                                <span class="description">images (recommandé: 12-24)</span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="astro_default_columns">Colonnes par défaut</label>
                                <p class="description">Disposition des images dans la grille</p>
                            </th>
                            <td>
                                <select id="astro_default_columns" name="astro_default_columns">
                                    <option value="2" <?php selected(get_option('astro_default_columns', 3), 2); ?>>2 colonnes</option>
                                    <option value="3" <?php selected(get_option('astro_default_columns', 3), 3); ?>>3 colonnes</option>
                                    <option value="4" <?php selected(get_option('astro_default_columns', 3), 4); ?>>4 colonnes</option>
                                    <option value="5" <?php selected(get_option('astro_default_columns', 3), 5); ?>>5 colonnes</option>
                                    <option value="6" <?php selected(get_option('astro_default_columns', 3), 6); ?>>6 colonnes</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="astro_image_quality">Qualité d'affichage</label>
                                <p class="description">Taille des images dans la galerie</p>
                            </th>
                            <td>
                                <select id="astro_image_quality" name="astro_image_quality">
                                    <option value="medium" <?php selected(get_option('astro_image_quality', 'large'), 'medium'); ?>>Moyenne (300px)</option>
                                    <option value="large" <?php selected(get_option('astro_image_quality', 'large'), 'large'); ?>>Grande (1024px)</option>
                                    <option value="full" <?php selected(get_option('astro_image_quality', 'large'), 'full'); ?>>Originale</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Affichage des métadonnées</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="astro_show_metadata" value="1" <?php checked(get_option('astro_show_metadata', 1)); ?> />
                                        Afficher les informations techniques sous chaque image
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="astro_show_object_info" value="1" <?php checked(get_option('astro_show_object_info', 1)); ?> />
                                        Afficher les informations des objets célestes
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="astro_show_shooting_data" value="1" <?php checked(get_option('astro_show_shooting_data', 1)); ?> />
                                        Afficher les données de prise de vue
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Fonctionnalités interactives</th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox" name="astro_enable_likes" value="1" <?php checked(get_option('astro_enable_likes', 1)); ?> />
                                        Activer le système de likes sur les images
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="astro_enable_comments" value="1" <?php checked(get_option('astro_enable_comments', 0)); ?> />
                                        Permettre les commentaires sur les images
                                    </label><br>
                                    <label>
                                        <input type="checkbox" name="astro_enable_sharing" value="1" <?php checked(get_option('astro_enable_sharing', 1)); ?> />
                                        Boutons de partage sur les réseaux sociaux
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="astro_lightbox_style">Style de lightbox</label>
                                <p class="description">Affichage en plein écran</p>
                            </th>
                            <td>
                                <select id="astro_lightbox_style" name="astro_lightbox_style">
                                    <option value="simple" <?php selected(get_option('astro_lightbox_style', 'simple'), 'simple'); ?>>Simple</option>
                                    <option value="advanced" <?php selected(get_option('astro_lightbox_style', 'simple'), 'advanced'); ?>>Avancé avec métadonnées</option>
                                    <option value="slideshow" <?php selected(get_option('astro_lightbox_style', 'simple'), 'slideshow'); ?>>Diaporama</option>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('💾 Enregistrer les paramètres', 'primary', 'submit', false); ?>
                </form>
                
                <h2>🛠️ Outils de Gestion</h2>
                <div class="tools-grid">
                    <div class="tool-card">
                        <span class="tool-icon">🔄</span>
                        <h3>Régénérer les miniatures</h3>
                        <p>Recrée toutes les vignettes pour un affichage optimal</p>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_public_nonce'); ?>">
                            <input type="hidden" name="action" value="regenerate_thumbs">
                            <button type="submit" class="button button-primary">🔄 Régénérer</button>
                        </form>
                    </div>
                    
                    <div class="tool-card">
                        <span class="tool-icon">🧹</span>
                        <h3>Vider le cache</h3>
                        <p>Supprime les caches pour forcer le rechargement</p>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_public_nonce'); ?>">
                            <input type="hidden" name="action" value="clear_cache">
                            <button type="submit" class="button">🧹 Vider Cache</button>
                        </form>
                    </div>
                    
                    <div class="tool-card">
                        <span class="tool-icon">👁️</span>
                        <h3>Prévisualiser</h3>
                        <p>Voir votre galerie telle que la voient vos visiteurs</p>
                        <button class="button" onclick="window.open('<?php echo site_url(); ?>', '_blank')">
                            👁️ Voir le site
                        </button>
                    </div>
                    
                    <div class="tool-card">
                        <span class="tool-icon">📤</span>
                        <h3>Export des paramètres</h3>
                        <p>Sauvegarde votre configuration pour la réutiliser</p>
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="_wpnonce" value="<?php echo wp_create_nonce('astro_public_nonce'); ?>">
                            <input type="hidden" name="action" value="export_settings">
                            <button type="submit" class="button">📤 Exporter</button>
                        </form>
                    </div>
                </div>
                
                <div style="background: #e8f5e8; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin-top: 30px;">
                    <h3>✅ Interface Complète Restaurée</h3>
                    <p>Toutes les fonctionnalités d'administration sont maintenant disponibles :</p>
                    <ul>
                        <li>✅ <strong>📸 Upload Image</strong> - Pour télécharger vos photos</li>
                        <li>✅ <strong>🔭 Métadonnées</strong> - Pour gérer les informations détaillées</li>
                        <li>✅ <strong>🔄 Catalogues</strong> - Pour importer les catalogues astronomiques</li>
                        <li>✅ <strong>🌐 Gestion Public</strong> - Cette nouvelle interface</li>
                    </ul>
                </div>
            </div>
            
            <script>
            function copyToClipboard(text) {
                navigator.clipboard.writeText(text).then(function() {
                    alert('📋 Shortcode copié dans le presse-papiers !');
                });
            }
            
            function exportSettings() {
                const settings = {
                    images_per_page: document.querySelector('[name="astro_images_per_page"]').value,
                    default_columns: document.querySelector('[name="astro_default_columns"]').value,
                    show_metadata: document.querySelector('[name="astro_show_metadata"]').checked,
                    enable_likes: document.querySelector('[name="astro_enable_likes"]').checked
                };
                
                const blob = new Blob([JSON.stringify(settings, null, 2)], {type: 'application/json'});
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = 'astrofolio-settings-' + Date.now() + '.json';
                a.click();
                URL.revokeObjectURL(url);
                
                alert('📤 Paramètres exportés !');
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * Compter les images d'astrophotographie
     */
    private function count_astro_images() {
        global $wpdb;
        
        // Compter les images avec des métadonnées astrophoto
        $table_name = $wpdb->prefix . 'astro_metadata';
        
        // Vérifier si la table existe et a du contenu
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(DISTINCT attachment_id) FROM $table_name");
            if ($count > 0) {
                return intval($count);
            }
        }
        
        // Méthode alternative: compter les images marquées astrofolio ou avec métadonnées astro
        $images_with_meta = get_posts(array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_object_name',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_shooting_date',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_telescope',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_camera',
                    'compare' => 'EXISTS'
                )
            )
        ));
        
        return count($images_with_meta);
    }
    
    /**
     * Compter les objets dans les catalogues astronomiques
     */
    private function count_catalog_objects() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astro_objects';
        
        // Vérifier si la table existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            return intval($count);
        }
        
        // Sinon, compter approximativement depuis les CSV
        $plugin_path = plugin_dir_path(__FILE__);
        $csv_files = array(
            'data/messier.csv',
            'data/caldwell.csv', 
            'data/ngc.csv',
            'data/ic.csv',
            'data/sharpless.csv'
        );
        
        $total = 0;
        foreach ($csv_files as $file) {
            $filepath = $plugin_path . $file;
            if (file_exists($filepath)) {
                $lines = file($filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $total += max(0, count($lines) - 1); // -1 pour l'en-tête
            }
        }
        
        return $total;
    }
    
    // === MÉTHODES DE RENDU POUR LES SHORTCODES ===
    
    private function render_gallery($atts = array()) {
        // Récupérer les options admin avec fallbacks
        $limit = intval($atts['limit'] ?? get_option('astro_images_per_page', 12));
        $columns = intval($atts['columns'] ?? get_option('astro_default_columns', 3));
        $size = sanitize_text_field($atts['size'] ?? get_option('astro_image_quality', 'medium'));
        $show_metadata = isset($atts['show_metadata']) ? 
            ($atts['show_metadata'] === 'true' || $atts['show_metadata'] === '1') : 
            get_option('astro_show_metadata', 1);

        $images = $this->get_astro_images();
        
        if (empty($images)) {
            return '<div style="background: #fff3cd; border: 1px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 8px;"><p>Aucune image d\'astrophotographie disponible.</p></div>';
        }
        
        $images = array_slice($images, 0, $limit);
        
        // Créer un identifiant unique pour cette galerie
        $gallery_id = 'astro-gallery-' . uniqid();
        
        // Pas de CSS externe - tout en inline pour éviter les conflits
        $gallery_css = '';
        
        $output = $gallery_css;
        $output .= '<div style="display: grid; grid-template-columns: repeat(' . $columns . ', 1fr); gap: 20px; width: 100%;">';
        
        foreach ($images as $image) {
            $image_id = $image->ID;
            $title = get_the_title($image_id) ?: 'Image d\'astrophotographie';
            $image_url = wp_get_attachment_image_src($image_id, $size)[0];
            
            // Créer l'URL de détail
            $detail_page_id = get_option('astrofolio_detail_page');
            if ($detail_page_id && get_post($detail_page_id)) {
                $detail_url = get_permalink($detail_page_id) . '?image_id=' . $image_id;
            } else {
                $detail_url = '/astrofolio/image/' . $image_id;
            }
            
            // STRUCTURE SIMPLE qui fonctionne avec liens
            $output .= '<div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 10px; text-align: center;">';
            $output .= '<a href="' . esc_url($detail_url) . '" style="display: block; text-decoration: none; color: inherit;">';
            $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" style="width: 100%; height: 200px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">';
            $output .= '<div style="font-size: 14px; font-weight: bold; color: #333;">' . esc_html($title) . '</div>';
            $output .= '</a>';
            $output .= '</div>';
        }
        
        $output .= '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_single_image($image_id, $atts = array()) {
        $size = sanitize_text_field($atts['size'] ?? 'large');
        $show_metadata = intval($atts['show_metadata'] ?? 1);
        
        $title = get_the_title($image_id) ?: 'Image d\'astrophotographie';
        $object_name = get_post_meta($image_id, 'astro_object_name', true);
        $shooting_date = get_post_meta($image_id, 'astro_shooting_date', true);
        $telescope = get_post_meta($image_id, 'astro_telescope', true);
        $camera = get_post_meta($image_id, 'astro_camera', true);
        $image_url = wp_get_attachment_image_src($image_id, $size)[0];
        $full_url = wp_get_attachment_image_src($image_id, 'full')[0];
        
        $output = '<div class="astro-single-image" style="max-width: 800px; margin: 0 auto; text-align: center;">';
        $output .= '<div class="astro-image-container">';
        
        // Utiliser la page WordPress de détail si elle existe
        $detail_page_id = get_option('astrofolio_detail_page');
        if ($detail_page_id && get_post($detail_page_id)) {
            $detail_url = get_permalink($detail_page_id) . '?image_id=' . $image_id;
        } else {
            $detail_url = '/astrofolio/image/' . $image_id;
        }
        
        $output .= '<a href="' . esc_url($detail_url) . '" style="display: block; text-decoration: none;">';
        $output .= '<img src="' . esc_url($image_url) . '" alt="' . esc_attr($title) . '" ';
        $output .= 'style="width: 100%; height: auto; object-fit: contain; border-radius: 8px; cursor: pointer; transition: transform 0.3s ease;" ';
        $output .= 'onmouseover="this.style.transform=\'scale(1.02)\'" onmouseout="this.style.transform=\'scale(1)\'">';
        $output .= '</a>';
        $output .= '</div>';
        
        if ($show_metadata) {
            $output .= '<div class="astro-metadata" style="margin-top: 20px; padding: 20px; background: #f8f9fa; border-radius: 8px;">';
            $output .= '<h3>' . esc_html($title) . '</h3>';
            
            if ($object_name) {
                $output .= '<p><strong>Objet céleste:</strong> ' . esc_html($object_name) . '</p>';
            }
            if ($shooting_date) {
                $output .= '<p><strong>Date de prise:</strong> ' . date('d/m/Y', strtotime($shooting_date)) . '</p>';
            }
            if ($telescope) {
                $output .= '<p><strong>Télescope:</strong> ' . esc_html($telescope) . '</p>';
            }
            if ($camera) {
                $output .= '<p><strong>Caméra:</strong> ' . esc_html($camera) . '</p>';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_object_images($atts) {
        $object_name = sanitize_text_field($atts['name']);
        $show_info = filter_var($atts['show_info'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $limit = intval($atts['limit'] ?? 10);
        $columns = intval($atts['columns'] ?? 3);
        
        // Rechercher les images de cet objet
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => 'astro_object_name',
                    'value' => $object_name,
                    'compare' => '='
                )
            )
        );
        
        $images = get_posts($args);
        
        if (empty($images)) {
            return '<div class="astro-no-images"><p>Aucune image trouvée pour l\'objet "' . esc_html($object_name) . '"</p></div>';
        }
        
        $output = '<div class="astro-object-gallery">';
        
        if ($show_info) {
            $output .= '<div class="astro-object-header" style="text-align: center; margin-bottom: 20px;">';
            $output .= '<h3>🌟 ' . esc_html($object_name) . '</h3>';
            $output .= '<p>' . count($images) . ' image(s) disponible(s)</p>';
            $output .= '</div>';
        }
        
        $gallery_atts = array(
            'limit' => $limit,
            'columns' => $columns,
            'size' => 'medium'
        );
        
        $output .= $this->render_gallery($gallery_atts);
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_stats($atts) {
        $show = explode(',', $atts['show'] ?? 'images,objects');
        $style = sanitize_text_field($atts['style'] ?? 'cards');
        
        $total_images = $this->count_astro_images();
        $total_objects = $this->count_catalog_objects();
        
        $output = '<div class="astro-stats astro-stats-' . $style . '">';
        
        if ($style === 'cards') {
            $output .= '<div class="astro-stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">';
            
            if (in_array('images', $show)) {
                $output .= '<div class="astro-stat-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
                $output .= '<div style="font-size: 2em;">📸</div>';
                $output .= '<div style="font-size: 2em; font-weight: bold; color: #667eea;">' . number_format($total_images) . '</div>';
                $output .= '<div>Images d\'astrophotographie</div>';
                $output .= '</div>';
            }
            
            if (in_array('objects', $show)) {
                $output .= '<div class="astro-stat-card" style="background: white; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">';
                $output .= '<div style="font-size: 2em;">🌟</div>';
                $output .= '<div style="font-size: 2em; font-weight: bold; color: #667eea;">' . number_format($total_objects) . '</div>';
                $output .= '<div>Objets catalogués</div>';
                $output .= '</div>';
            }
            
            $output .= '</div>';
        } else {
            $output .= '<div class="astro-stats-simple">';
            if (in_array('images', $show)) {
                $output .= '<p><strong>' . number_format($total_images) . '</strong> images d\'astrophotographie</p>';
            }
            if (in_array('objects', $show)) {
                $output .= '<p><strong>' . number_format($total_objects) . '</strong> objets catalogués</p>';
            }
            $output .= '</div>';
        }
        
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_search_form($atts) {
        $placeholder = esc_attr($atts['placeholder'] ?? 'Rechercher...');
        $button_text = esc_html($atts['button_text'] ?? 'Rechercher');
        
        $output = '<div class="astro-search-form">';
        $output .= '<form method="get" style="display: flex; gap: 10px; max-width: 400px;">';
        $output .= '<input type="text" name="astro_search" placeholder="' . $placeholder . '" style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        $output .= '<button type="submit" style="padding: 10px 20px; background: #667eea; color: white; border: none; border-radius: 4px; cursor: pointer;">' . $button_text . '</button>';
        $output .= '</form>';
        $output .= '</div>';
        
        return $output;
    }
    
    private function render_random_images($atts) {
        $count = intval($atts['count'] ?? 3);
        $columns = intval($atts['columns'] ?? 3);
        $size = sanitize_text_field($atts['size'] ?? 'medium');
        
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $count,
            'orderby' => 'rand',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_astrofolio_image',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'astro_object_name',
                    'compare' => 'EXISTS'
                )
            )
        );
        
        $images = get_posts($args);
        
        if (empty($images)) {
            return '<div class="astro-no-images"><p>Aucune image disponible</p></div>';
        }
        
        $gallery_atts = array(
            'limit' => $count,
            'columns' => $columns,
            'size' => $size
        );
        
        return $this->render_gallery($gallery_atts);
    }
    
    private function get_lightbox_html() {
        return '
        <div id="astro-lightbox" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9);" onclick="closeAstroLightbox()">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 90%; max-height: 90%;">
                <img id="astro-lightbox-img" src="" alt="" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                <div id="astro-lightbox-title" style="color: white; text-align: center; margin-top: 10px; font-size: 1.2em;"></div>
            </div>
            <div style="position: absolute; top: 20px; right: 30px; color: white; font-size: 30px; cursor: pointer;" onclick="closeAstroLightbox()">×</div>
        </div>
        
        <script>
        function openAstroLightbox(imageUrl, title) {
            document.getElementById("astro-lightbox-img").src = imageUrl;
            document.getElementById("astro-lightbox-title").textContent = title;
            document.getElementById("astro-lightbox").style.display = "block";
        }
        
        function closeAstroLightbox() {
            document.getElementById("astro-lightbox").style.display = "none";
        }
        </script>';
    }
    
    public function activate() {
        // Activation ultra-minimale pour éviter toute sortie
        add_option('astrofolio_version', '1.4.3', '', 'no');
        
        // Ne rien faire d'autre lors de l'activation
        // Les tables et pages seront créées au premier accès
    }
    
    // Créer les pages WordPress pour l'intégration dans le thème (version différée)
    public function create_theme_integration_pages_delayed() {
        // Ne créer qu'une fois
        static $pages_created = false;
        if ($pages_created) return;
        $pages_created = true;
        
        $this->create_theme_integration_pages();
    }
    
    // Créer les pages WordPress pour l'intégration dans le thème
    private function create_theme_integration_pages() {
        // Page galerie
        $gallery_page_id = get_option('astrofolio_gallery_page');
        if (!$gallery_page_id || !get_post($gallery_page_id)) {
            $gallery_page = array(
                'post_title' => '🌌 AstroFolio - Galerie',
                'post_content' => '[astrofolio_gallery]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_slug' => 'astrofolio-galerie'
            );
            $gallery_page_id = wp_insert_post($gallery_page);
            update_option('astrofolio_gallery_page', $gallery_page_id);
        }
        
        // Page détail
        $detail_page_id = get_option('astrofolio_detail_page');
        if (!$detail_page_id || !get_post($detail_page_id)) {
            $detail_page = array(
                'post_title' => '🌟 AstroFolio - Détail Image',
                'post_content' => '[astrofolio_image_detail]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_slug' => 'astrofolio-detail'
            );
            $detail_page_id = wp_insert_post($detail_page);
            update_option('astrofolio_detail_page', $detail_page_id);
        }
    }
    
    // Méthode pour forcer la réactivation des règles (à utiliser via admin)
    public function force_rewrite_rules_refresh() {
        $this->add_rewrite_rules();
        flush_rewrite_rules();
        return "Règles de réécriture actualisées!";
    }
    
    /**
     * Compter rapidement les images candidates à la récupération
     */
    private function get_recovery_candidate_count() {
        try {
            $images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_astrofolio_image',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            
            return count($images);
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur get_recovery_candidate_count: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Scanner les images candidates avec détails
     */
    private function scan_recovery_candidates() {
        $images = get_posts([
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => 50, // Limiter pour éviter timeout
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                ]
            ]
        ]);
        
        $detected = 0;
        $total = count($images);
        $examples = [];
        
        foreach ($images as $image) {
            $is_astro = $this->detect_astro_image($image);
            if ($is_astro) {
                $detected++;
                if (count($examples) < 5) {
                    $object_name = $this->extract_object_name($image->post_title . ' ' . basename(get_attached_file($image->ID)));
                    $examples[] = [
                        'title' => $image->post_title ?: 'Sans titre',
                        'filename' => basename(get_attached_file($image->ID)),
                        'object' => $object_name ?: 'Objet non identifié'
                    ];
                }
            }
        }
        
        $message = "📊 <strong>Analyse terminée :</strong><br>";
        $message .= "• {$total} images analysées<br>";
        $message .= "• {$detected} images d'astrophotographie détectées<br>";
        $message .= "• " . ($total - $detected) . " images ignorées<br>";
        
        if (!empty($examples)) {
            $message .= "<br><strong>🎯 Exemples détectés :</strong><br>";
            foreach ($examples as $example) {
                $message .= "• <strong>{$example['object']}</strong> ({$example['filename']})<br>";
            }
        }
        
        return array('success' => true, 'message' => $message);
    }
    
    /**
     * Récupération automatique des images perdues en batch
     */
    private function recover_lost_images_batch() {
        try {
            // Augmenter le timeout pour éviter les interruptions
            set_time_limit(300);
            
            $images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => 50, // Réduit pour éviter timeout
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => '_astrofolio_image',
                        'compare' => 'NOT EXISTS'
                    ]
                ]
            ]);
            
            if (empty($images)) {
                return array('success' => false, 'message' => "Aucune image candidate trouvée pour la récupération.");
            }
            
            $recovered = 0;
            $skipped = 0;
            $recovered_objects = [];
            $errors = [];
            
            foreach ($images as $image) {
                try {
                    $is_astro = $this->detect_astro_image($image);
                    
                    if ($is_astro) {
                        // Marquer comme image AstroFolio
                        $meta_result = update_post_meta($image->ID, '_astrofolio_image', '1');
                        
                        if ($meta_result !== false) {
                            // Récupérer les métadonnées
                            $this->recover_image_metadata($image);
                            
                            // Ajouter timestamp de récupération
                            update_post_meta($image->ID, '_astrofolio_recovered', current_time('mysql'));
                            update_post_meta($image->ID, '_astrofolio_recovery_method', 'automatic_batch');
                            
                            // Récupérer l'objet pour les stats
                            $filename = get_attached_file($image->ID);
                            if ($filename) {
                                $object_name = $this->extract_object_name($image->post_title . ' ' . basename($filename));
                                if ($object_name) {
                                    $recovered_objects[] = $object_name;
                                }
                            }
                            
                            $recovered++;
                        } else {
                            $errors[] = "Erreur mise à jour métadonnées pour image ID {$image->ID}";
                        }
                    } else {
                        $skipped++;
                    }
                } catch (Exception $e) {
                    $errors[] = "Erreur traitement image ID {$image->ID}: " . $e->getMessage();
                    error_log("AstroFolio - Erreur récupération image {$image->ID}: " . $e->getMessage());
                }
            }
            
            if ($recovered > 0) {
                $message = "✅ <strong>Récupération réussie !</strong><br>";
                $message .= "• {$recovered} image(s) récupérée(s)<br>";
                $message .= "• {$skipped} image(s) ignorée(s)<br>";
                
                if (!empty($errors)) {
                    $message .= "• " . count($errors) . " erreur(s)<br>";
                }
                
                if (!empty($recovered_objects)) {
                    $unique_objects = array_unique($recovered_objects);
                    $message .= "<br><strong>🎯 Objets récupérés :</strong><br>";
                    $message .= "• " . implode('<br>• ', array_slice($unique_objects, 0, 10));
                    if (count($unique_objects) > 10) {
                        $message .= "<br>• ... et " . (count($unique_objects) - 10) . " autres";
                    }
                }
                
                $message .= "<br><br><em>Les images récupérées sont maintenant visibles dans votre galerie AstroFolio.</em>";
                
                // Log des erreurs pour debug
                if (!empty($errors)) {
                    error_log("AstroFolio - Erreurs récupération: " . implode(', ', $errors));
                }
                
                return array('success' => true, 'message' => $message);
            } else {
                $error_msg = "Aucune image d'astrophotographie détectée pour la récupération.";
                if (!empty($errors)) {
                    $error_msg .= " Erreurs: " . implode(', ', array_slice($errors, 0, 3));
                }
                return array('success' => false, 'message' => $error_msg);
            }
            
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur recover_lost_images_batch: ' . $e->getMessage());
            return array('success' => false, 'message' => 'Erreur système: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX : Récupérer une seule image
     */
    public function ajax_recover_single_image() {
        check_ajax_referer('astro_recovery', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission insuffisante', 403);
        }
        
        $image_id = intval($_POST['image_id']);
        if (!$image_id) {
            wp_send_json_error('ID image invalide');
        }
        
        try {
            // Vérifier que l'image existe
            $image = get_post($image_id);
            if (!$image || $image->post_type !== 'attachment') {
                wp_send_json_error('Image non trouvée');
            }
            
            // Vérifier qu'elle n'est pas déjà marquée
            if (get_post_meta($image_id, '_astrofolio_image', true)) {
                wp_send_json_error('Image déjà marquée AstroFolio');
            }
            
            // Détecter si c'est une image astro
            if (!$this->detect_astro_image($image_id)) {
                wp_send_json_error('Image non détectée comme astrophotographie');
            }
            
            // Marquer l'image
            update_post_meta($image_id, '_astrofolio_image', true);
            
            // Récupérer les métadonnées si possible
            $this->recover_image_metadata($image_id);
            
            wp_send_json_success('Image récupérée avec succès');
            
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur récupération unique: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX : Récupération par lot
     */
    public function ajax_batch_recovery() {
        check_ajax_referer('astro_recovery', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission insuffisante', 403);
        }
        
        $start_time = microtime(true);
        set_time_limit(300); // 5 minutes max
        
        try {
            // Récupérer toutes les images non marquées
            $images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                ]]
            ]);
            
            $recovered = 0;
            $processed = 0;
            
            foreach ($images as $image_id) {
                $processed++;
                
                // Détecter si c'est une image astro
                if ($this->detect_astro_image($image_id)) {
                    // Marquer l'image
                    update_post_meta($image_id, '_astrofolio_image', true);
                    
                    // Récupérer les métadonnées si possible
                    $this->recover_image_metadata($image_id);
                    
                    $recovered++;
                }
                
                // Éviter timeout - traiter par petits lots
                if ($processed % 50 === 0 && (microtime(true) - $start_time) > 240) {
                    break; // Arrêter si on approche du timeout
                }
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            wp_send_json_success([
                'recovered' => $recovered,
                'processed' => $processed,
                'time' => $execution_time . 's',
                'details' => "Traité {$processed} images, récupéré {$recovered} photos d'astrophotographie"
            ]);
            
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur récupération lot: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération par lot: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX : Marquer une image comme astro (sans vérification de détection)
     */
    public function ajax_force_recover_image() {
        check_ajax_referer('astro_recovery', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission insuffisante', 403);
        }
        
        $image_id = intval($_POST['image_id']);
        if (!$image_id) {
            wp_send_json_error('ID image invalide');
        }
        
        try {
            // Vérifier que l'image existe
            $image = get_post($image_id);
            if (!$image || $image->post_type !== 'attachment') {
                wp_send_json_error('Image non trouvée');
            }
            
            // Vérifier qu'elle n'est pas déjà marquée
            if (get_post_meta($image_id, '_astrofolio_image', true)) {
                wp_send_json_error('Image déjà marquée AstroFolio');
            }
            
            // Marquer l'image (sans vérification de détection)
            update_post_meta($image_id, '_astrofolio_image', true);
            
            // Ajouter une note que c'est un marquage manuel
            update_post_meta($image_id, '_astrofolio_manual_recovery', current_time('mysql'));
            
            // Récupérer les métadonnées si possible
            $this->recover_image_metadata($image_id);
            
            wp_send_json_success('Image marquée comme astrophotographie');
            
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur marquage forcé: ' . $e->getMessage());
            wp_send_json_error('Erreur lors du marquage: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX : Récupérer TOUTES les images non marquées (mode forcé)
     */
    public function ajax_force_recover_all() {
        check_ajax_referer('astro_recovery', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permission insuffisante', 403);
        }
        
        $start_time = microtime(true);
        set_time_limit(300); // 5 minutes max
        
        try {
            // Récupérer toutes les images non marquées
            $images = get_posts([
                'post_type' => 'attachment',
                'post_mime_type' => 'image',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => [[
                    'key' => '_astrofolio_image',
                    'compare' => 'NOT EXISTS'
                ]]
            ]);
            
            $recovered = 0;
            $processed = 0;
            
            foreach ($images as $image_id) {
                $processed++;
                
                // Marquer l'image SANS vérification de détection
                update_post_meta($image_id, '_astrofolio_image', true);
                
                // Marquer comme récupération forcée
                update_post_meta($image_id, '_astrofolio_force_recovered', current_time('mysql'));
                
                // Récupérer les métadonnées si possible
                $this->recover_image_metadata($image_id);
                
                $recovered++;
                
                // Éviter timeout - traiter par petits lots
                if ($processed % 50 === 0 && (microtime(true) - $start_time) > 240) {
                    break; // Arrêter si on approche du timeout
                }
            }
            
            $execution_time = round(microtime(true) - $start_time, 2);
            
            wp_send_json_success([
                'recovered' => $recovered,
                'processed' => $processed,
                'time' => $execution_time . 's'
            ]);
            
        } catch (Exception $e) {
            error_log('AstroFolio - Erreur récupération forcée: ' . $e->getMessage());
            wp_send_json_error('Erreur lors de la récupération forcée: ' . $e->getMessage());
        }
    }
    
    /**
     * Upload groupé d'images via AJAX - NOUVEAU v1.4.7
     * 
     * Traite l'upload simultané de plusieurs images avec métadonnées communes
     * 
     * @since 1.4.7
     * @return void
     */
    public function ajax_upload_bulk_images() {
        // Vérification de sécurité CSRF
        if (!check_ajax_referer('astro_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Erreur de sécurité - nonce invalide'));
        }
        
        // Vérification des permissions
        if (!current_user_can('edit_posts')) {
            error_log('AstroFolio: Permission refusée pour upload groupé');
            wp_send_json_error(array('message' => 'Permission insuffisante pour uploader des images'));
        }
        
        error_log('AstroFolio: Début upload groupé - ' . count($_FILES['images']['name'] ?? []) . ' fichiers');
        
        // Vérification de la présence de fichiers
        if (!isset($_FILES['images']) || empty($_FILES['images']['name'][0])) {
            error_log('AstroFolio: Aucun fichier reçu dans $_FILES');
            wp_send_json_error(array('message' => 'Aucun fichier reçu'));
        }
        
        // Limite du nombre de fichiers
        $file_count = count($_FILES['images']['name']);
        if ($file_count > 20) {
            wp_send_json_error(array('message' => 'Trop de fichiers (maximum 20 par envoi)'));
        }
        
        // Types et taille autorisés
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $max_file_size = wp_max_upload_size();
        
        // Récupération des métadonnées communes
        $common_data = array(
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'object_name' => sanitize_text_field($_POST['object_name'] ?? ''),
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'telescope' => sanitize_text_field($_POST['telescope'] ?? ''),
            'camera_name' => sanitize_text_field($_POST['camera_name'] ?? '')
        );
        
        // Initialisation des résultats
        $results = array(
            'success' => array(),
            'errors' => array(),
            'total' => $file_count
        );
        
        // Traitement de chaque fichier
        for ($i = 0; $i < $file_count; $i++) {
            $file_info = array(
                'name' => $_FILES['images']['name'][$i],
                'type' => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error' => $_FILES['images']['error'][$i],
                'size' => $_FILES['images']['size'][$i]
            );
            
            $file_title = !empty($_POST['titles'][$i]) ? 
                sanitize_text_field($_POST['titles'][$i]) : 
                pathinfo($file_info['name'], PATHINFO_FILENAME);
            
            // Validation du fichier
            if ($file_info['error'] !== UPLOAD_ERR_OK) {
                $results['errors'][] = array(
                    'file' => $file_info['name'],
                    'message' => 'Erreur d\'upload'
                );
                continue;
            }
            
            if (!in_array(strtolower($file_info['type']), $allowed_types)) {
                $results['errors'][] = array(
                    'file' => $file_info['name'],
                    'message' => 'Type de fichier non autorisé'
                );
                continue;
            }
            
            if ($file_info['size'] > $max_file_size) {
                $results['errors'][] = array(
                    'file' => $file_info['name'],
                    'message' => 'Fichier trop volumineux'
                );
                continue;
            }
            
            // Simulation de $_FILES pour wp_handle_upload
            $_FILES['temp_upload'] = $file_info;
            
            // Upload WordPress
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded_file = wp_handle_upload($_FILES['temp_upload'], array('test_form' => false));
            
            if (isset($uploaded_file['error'])) {
                $results['errors'][] = array(
                    'file' => $file_info['name'],
                    'message' => 'Erreur upload : ' . $uploaded_file['error']
                );
                continue;
            }
            
            // Créer l'attachement WordPress
            require_once(ABSPATH . 'wp-admin/includes/image.php');
            $attachment = array(
                'guid'           => $uploaded_file['url'], 
                'post_mime_type' => $uploaded_file['type'],
                'post_title'     => $file_title,
                'post_content'   => $common_data['description'],
                'post_status'    => 'inherit'
            );
            
            $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
            
            if (is_wp_error($attachment_id)) {
                $results['errors'][] = array(
                    'file' => $file_info['name'],
                    'message' => 'Erreur création attachement'
                );
                unlink($uploaded_file['file']);
                continue;
            }
            
            // Générer les métadonnées d'image
            $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
            wp_update_attachment_metadata($attachment_id, $metadata);
            
            // Ajouter les métadonnées personnalisées
            // Toujours marquer comme image astro
            update_post_meta($attachment_id, 'astro_uploaded_bulk', 'yes');
            update_post_meta($attachment_id, 'astro_upload_date', current_time('mysql'));
            
            if (!empty($common_data['object_name'])) {
                update_post_meta($attachment_id, 'astro_object_name', $common_data['object_name']);
            }
            if (!empty($common_data['location'])) {
                update_post_meta($attachment_id, 'astro_location', $common_data['location']);
            }
            if (!empty($common_data['telescope'])) {
                update_post_meta($attachment_id, 'astro_telescope', $common_data['telescope']);
            }
            if (!empty($common_data['camera_name'])) {
                update_post_meta($attachment_id, 'astro_camera', $common_data['camera_name']);
            }
            
            // S'assurer qu'au moins une métadonnée astro existe pour que l'image soit détectée
            if (empty($common_data['object_name']) && empty($common_data['telescope']) && 
                empty($common_data['camera_name']) && empty($common_data['location'])) {
                update_post_meta($attachment_id, 'astro_object_name', 'Objet non spécifié');
            }
            
            $results['success'][] = array(
                'file' => $file_info['name'],
                'attachment_id' => $attachment_id,
                'url' => $uploaded_file['url'],
                'title' => $file_title
            );
        }
        
        // Nettoyage
        unset($_FILES['temp_upload']);
        
        // Message de retour
        $success_count = count($results['success']);
        $error_count = count($results['errors']);
        
        if ($success_count > 0 && $error_count == 0) {
            $message = sprintf('%d image(s) uploadée(s) avec succès', $success_count);
        } elseif ($success_count > 0 && $error_count > 0) {
            $message = sprintf('%d image(s) uploadée(s), %d erreur(s)', $success_count, $error_count);
        } else {
            $message = sprintf('Échec complet : %d erreur(s)', $error_count);
        }
        
        wp_send_json_success(array(
            'message' => $message,
            'results' => $results
        ));
    }
}

// Variable globale pour accès depuis les pages d'administration
global $astrofolio_plugin;

// Initialiser seulement si WordPress est prêt
if (defined('ABSPATH')) {
    $astrofolio_plugin = new AstroFolio_Safe();
}