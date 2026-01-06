<?php
/**
 * =============================================================================
 * CLASSE PUBLIQUE ASTROFOLIO - INTERFACE FRONTEND
 * =============================================================================
 * 
 * Cette classe gère toute l'interface publique (frontend) du plugin
 * 
 * 🌐 RESPONSABILITÉS PRINCIPALES :
 * - Affichage des galeries d'images sur le site public
 * - Rendu des shortcodes pour les visiteurs
 * - Chargement des scripts et styles frontend
 * - Gestion des URLs et templates personnalisés
 * - Interface de recherche publique
 * 
 * 📜 SHORTCODES DISPONIBLES :
 * - [astro_gallery] : Galerie d'images avec filtres
 * - [astro_image] : Affichage d'une image spécifique
 * - [astro_object] : Détails d'un objet astronomique
 * - [astro_search] : Formulaire de recherche
 * - [astro_random] : Image aléatoire
 * - [astro_stats] : Statistiques du portfolio
 * 
 * 🎨 FONCTIONNALITÉS D'AFFICHAGE :
 * - Templates responsives pour mobiles/tablettes
 * - Lightbox pour visualisation plein écran
 * - Lazy loading des images
 * - Pagination automatique
 * - Filtres par objet, équipement, date
 * 
 * 📱 RESPONSIVE ET PERFORMANCE :
 * - Images optimisées selon la taille d'écran
 * - Chargement progressif (lazy loading)
 * - Mise en cache des galeries
 * - Compression automatique
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Public
 */
class Astro_Public {
    
    public function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_public_scripts'));
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'template_redirect'));
        add_action('wp_ajax_astro_like_image', array($this, 'handle_like_image'));
        add_action('wp_ajax_nopriv_astro_like_image', array($this, 'handle_like_image'));
        add_action('wp_ajax_astro_filter_gallery', array($this, 'handle_ajax_filter_gallery'));
        add_action('wp_ajax_nopriv_astro_filter_gallery', array($this, 'handle_ajax_filter_gallery'));
        add_action('wp_ajax_astro_load_more_images', array($this, 'load_more_images'));
        add_action('wp_ajax_nopriv_astro_load_more_images', array($this, 'load_more_images'));
    }
    
    public function enqueue_public_scripts() {
        // Charger les scripts sur les pages astro spécifiques ET sur toutes les pages/posts
        // car on ne peut pas détecter la présence d'un shortcode avant le rendu
        global $post;
        
        $should_load = false;
        
        // Pages astro spécifiques
        if ($this->is_astro_page()) {
            $should_load = true;
        }
        
        // Pages/posts avec potentiel shortcode astro_gallery
        if (is_singular() && $post && (
            strpos($post->post_content, '[astro_gallery') !== false ||
            strpos($post->post_content, '[astro_search') !== false ||
            strpos($post->post_content, '[astro_image') !== false
        )) {
            $should_load = true;
        }
        
        // Si c'est la page d'accueil ou une page d'archive, charger par sécurité
        if (is_home() || is_front_page()) {
            $should_load = true;
        }
        
        if ($should_load) {
            $plugin_url = plugin_dir_url(dirname(__FILE__)) ?: (defined('ANC_PLUGIN_URL') ? ANC_PLUGIN_URL : '');
            
            wp_enqueue_style(
                'astro-public-css',
                $plugin_url . 'public/css/public.css',
                array(),
                '1.4.8'
            );
            
            wp_enqueue_script(
                'astro-public-js',
                $plugin_url . 'public/js/public.js',
                array('jquery'),
                '1.4.8',
                true
            );
            
            wp_localize_script('astro-public-js', 'astroPublic', array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('astro_public_nonce'),
                'strings' => array(
                    'loading' => 'Chargement...',
                    'noMoreImages' => 'Plus d\'images à charger',
                    'error' => 'Une erreur est survenue',
                    'liked' => 'Aimé !',
                    'like' => 'J\'aime'
                )
            ));
        }
    }
    
    private function is_astro_page() {
        global $wp_query;
        return isset($wp_query->query_vars['astro_page']) || 
               (is_page() && get_query_var('astro_action'));
    }
    
    public function add_rewrite_rules() {
        // Page principale de la galerie
        add_rewrite_rule(
            '^astro/?$',
            'index.php?astro_page=gallery',
            'top'
        );
        
        // Page d'une image spécifique
        add_rewrite_rule(
            '^astro/image/([^/]+)/?$',
            'index.php?astro_page=image&astro_image_id=$matches[1]',
            'top'
        );
        
        // Page d'un objet spécifique
        add_rewrite_rule(
            '^astro/object/([^/]+)/?$',
            'index.php?astro_page=object&astro_object_name=$matches[1]',
            'top'
        );
        
        // Page de catalogue
        add_rewrite_rule(
            '^astro/catalog/([^/]+)/?$',
            'index.php?astro_page=catalog&astro_catalog_name=$matches[1]',
            'top'
        );
        
        // Page de recherche
        add_rewrite_rule(
            '^astro/search/?$',
            'index.php?astro_page=search',
            'top'
        );
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'astro_page';
        $vars[] = 'astro_image_id';
        $vars[] = 'astro_object_name';
        $vars[] = 'astro_catalog_name';
        $vars[] = 'astro_action';
        return $vars;
    }
    
    public function template_redirect() {
        $astro_page = get_query_var('astro_page');
        
        if (!$astro_page) {
            return;
        }
        
        // Vérifier si l'affichage public est activé
        if (!get_option('astro_enable_public', 1)) {
            wp_die('La galerie n\'est pas accessible au public.');
        }
        
        switch ($astro_page) {
            case 'gallery':
                $this->display_gallery();
                break;
            case 'image':
                $this->display_image();
                break;
            case 'object':
                $this->display_object();
                break;
            case 'catalog':
                $this->display_catalog();
                break;
            case 'search':
                $this->display_search();
                break;
            default:
                wp_die('Page non trouvée.');
        }
        
        exit;
    }
    
    private function display_gallery() {
        $page = max(1, intval(get_query_var('paged', 1)));
        $per_page = get_option('astro_images_per_page', 12);
        
        // Récupérer les filtres depuis l'URL
        $filters = $this->get_gallery_filters();
        $filters['status'] = 'published';
        $filters['limit'] = $per_page;
        $filters['offset'] = ($page - 1) * $per_page;
        
        $images = Astro_Images::search_images($filters);
        $total_images = Astro_Images::count_images($filters);
        
        // Récupérer les données pour les listes déroulantes
        $filter_data = $this->extract_filter_data();
        
        $this->render_page('gallery', array(
            'images' => $images,
            'current_page' => $page,
            'total_pages' => ceil($total_images / $per_page),
            'per_page' => $per_page,
            'total_images' => $total_images,
            'filters' => $filters,
            'filter_data' => $filter_data
        ));
    }
    
    private function get_gallery_filters() {
        return array(
            'search' => sanitize_text_field($_GET['search'] ?? ''),
            'object_type' => sanitize_text_field($_GET['object_type'] ?? ''),
            'telescope' => sanitize_text_field($_GET['telescope'] ?? ''),
            'camera' => sanitize_text_field($_GET['camera'] ?? ''),
            'camera_type' => sanitize_text_field($_GET['camera_type'] ?? ''),
            'telescope_type' => sanitize_text_field($_GET['telescope_type'] ?? ''),
            'constellation' => sanitize_text_field($_GET['constellation'] ?? ''),
            'year' => sanitize_text_field($_GET['year'] ?? ''),
            'object' => sanitize_text_field($_GET['object'] ?? ''),
            'catalog' => sanitize_text_field($_GET['catalog'] ?? ''),
            'featured' => sanitize_text_field($_GET['featured'] ?? ''),
            // Filtres avancés
            'min_exposure' => intval($_GET['min_exposure'] ?? 0),
            'max_exposure' => intval($_GET['max_exposure'] ?? 0),
            'min_aperture' => intval($_GET['min_aperture'] ?? 0),
            'date_from' => sanitize_text_field($_GET['date_from'] ?? ''),
            'date_to' => sanitize_text_field($_GET['date_to'] ?? '')
        );
    }
    
    private function extract_filter_data() {
        global $wpdb;
        
        $data = array(
            'object_types' => array(),
            'telescopes' => array(),
            'cameras' => array(),
            'constellations' => array(),
            'years' => array(),
            'camera_types' => array(),
            'telescope_types' => array()
        );
        
        // Vérifier si la table existe
        $table_name = $wpdb->prefix . 'astro_images';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return $data;
        }
        
        // Récupérer les colonnes disponibles
        $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
        
        // Récupérer les types d'objets depuis la table objects si elle existe
        $objects_table = $wpdb->prefix . 'astro_objects';
        if ($wpdb->get_var("SHOW TABLES LIKE '$objects_table'") == $objects_table) {
            $object_types = $wpdb->get_col(
                "SELECT DISTINCT o.object_type FROM {$wpdb->prefix}astro_objects o
                 INNER JOIN {$wpdb->prefix}astro_images i ON o.id = i.object_id
                 WHERE o.object_type IS NOT NULL AND o.object_type != '' AND i.status = 'published'
                 ORDER BY o.object_type"
            );
            $data['object_types'] = array_filter($object_types);
            
            // Récupérer les constellations
            $constellations = $wpdb->get_col(
                "SELECT DISTINCT o.constellation FROM {$wpdb->prefix}astro_objects o
                 INNER JOIN {$wpdb->prefix}astro_images i ON o.id = i.object_id
                 WHERE o.constellation IS NOT NULL AND o.constellation != '' AND i.status = 'published'
                 ORDER BY o.constellation"
            );
            $data['constellations'] = array_filter($constellations);
        }
        
        // Récupérer les télescopes distincts
        if (in_array('telescope', $columns)) {
            $telescopes = $wpdb->get_col(
                "SELECT DISTINCT telescope FROM {$wpdb->prefix}astro_images 
                 WHERE telescope IS NOT NULL AND telescope != '' AND status = 'published'
                 ORDER BY telescope"
            );
            $data['telescopes'] = array_filter($telescopes);
        }
        
        // Récupérer les types de télescopes
        if (in_array('telescope_type', $columns)) {
            $telescope_types = $wpdb->get_col(
                "SELECT DISTINCT telescope_type FROM {$wpdb->prefix}astro_images 
                 WHERE telescope_type IS NOT NULL AND telescope_type != '' AND status = 'published'
                 ORDER BY telescope_type"
            );
            $data['telescope_types'] = array_filter($telescope_types);
        }
        
        // Récupérer les caméras distinctes (utiliser camera_name)
        if (in_array('camera_name', $columns)) {
            $cameras = $wpdb->get_col(
                "SELECT DISTINCT camera_name FROM {$wpdb->prefix}astro_images 
                 WHERE camera_name IS NOT NULL AND camera_name != '' AND status = 'published'
                 ORDER BY camera_name"
            );
            $data['cameras'] = array_filter($cameras);
        }
        
        // Récupérer les types de caméras
        if (in_array('camera_type', $columns)) {
            $camera_types = $wpdb->get_col(
                "SELECT DISTINCT camera_type FROM {$wpdb->prefix}astro_images 
                 WHERE camera_type IS NOT NULL AND camera_type != '' AND status = 'published'
                 ORDER BY camera_type"
            );
            $data['camera_types'] = array_filter($camera_types);
        }
        
        // Récupérer les années d'acquisition
        if (in_array('acquisition_date', $columns)) {
            $years = $wpdb->get_col(
                "SELECT DISTINCT YEAR(acquisition_date) as year FROM {$wpdb->prefix}astro_images 
                 WHERE acquisition_date IS NOT NULL AND status = 'published'
                 ORDER BY year DESC"
            );
            $data['years'] = array_filter($years);
        }
        
        return $data;
    }
    
    private function render_gallery_filters($data) {
        $filters = $data['filters'] ?? array();
        $filter_data = $data['filter_data'] ?? array();
        
        ob_start();
        ?>
        <div class="astro-filter-form">
            <form method="get" action="" id="gallery-filters-form">
                <!-- Première ligne de filtres -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="search">🔍 Recherche</label>
                        <input type="text" 
                               id="search" 
                               name="search" 
                               placeholder="Nom de l'objet, titre..." 
                               value="<?php echo esc_attr($filters['search'] ?? ''); ?>" />
                    </div>
                    
                    <div class="filter-group">
                        <label for="object_type">🌌 Type d'objet</label>
                        <select id="object_type" name="object_type">
                            <option value="">Tous les types</option>
                            <?php foreach (($filter_data['object_types'] ?? array()) as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" 
                                        <?php selected($filters['object_type'] ?? '', $type); ?>>
                                    <?php echo esc_html(ucfirst($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="constellation">⭐ Constellation</label>
                        <select id="constellation" name="constellation">
                            <option value="">Toutes les constellations</option>
                            <?php foreach (($filter_data['constellations'] ?? array()) as $constellation): ?>
                                <option value="<?php echo esc_attr($constellation); ?>" 
                                        <?php selected($filters['constellation'] ?? '', $constellation); ?>>
                                    <?php echo esc_html($constellation); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="year">📅 Année</label>
                        <select id="year" name="year">
                            <option value="">Toutes les années</option>
                            <?php foreach (($filter_data['years'] ?? array()) as $year): ?>
                                <option value="<?php echo esc_attr($year); ?>" 
                                        <?php selected($filters['year'] ?? '', $year); ?>>
                                    <?php echo esc_html($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Deuxième ligne de filtres -->
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="telescope">🔭 Télescope</label>
                        <select id="telescope" name="telescope">
                            <option value="">Tous les télescopes</option>
                            <?php foreach (($filter_data['telescopes'] ?? array()) as $telescope): ?>
                                <option value="<?php echo esc_attr($telescope); ?>" 
                                        <?php selected($filters['telescope'] ?? '', $telescope); ?>>
                                    <?php echo esc_html($telescope); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="telescope_type">🔬 Type de télescope</label>
                        <select id="telescope_type" name="telescope_type">
                            <option value="">Tous les types</option>
                            <?php foreach (($filter_data['telescope_types'] ?? array()) as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" 
                                        <?php selected($filters['telescope_type'] ?? '', $type); ?>>
                                    <?php echo esc_html(ucfirst($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="camera">📷 Caméra</label>
                        <select id="camera" name="camera">
                            <option value="">Toutes les caméras</option>
                            <?php foreach (($filter_data['cameras'] ?? array()) as $camera): ?>
                                <option value="<?php echo esc_attr($camera); ?>" 
                                        <?php selected($filters['camera'] ?? '', $camera); ?>>
                                    <?php echo esc_html($camera); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="camera_type">📹 Type de caméra</label>
                        <select id="camera_type" name="camera_type">
                            <option value="">Tous les types</option>
                            <?php foreach (($filter_data['camera_types'] ?? array()) as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>" 
                                        <?php selected($filters['camera_type'] ?? '', $type); ?>>
                                    <?php echo esc_html(strtoupper($type)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Troisième ligne - Actions et options avancées -->
                <div class="filter-row filter-actions-row">
                    <div class="filter-group">
                        <label for="featured">⭐ Images en vedette</label>
                        <select id="featured" name="featured">
                            <option value="">Toutes les images</option>
                            <option value="1" <?php selected($filters['featured'] ?? '', '1'); ?>>En vedette uniquement</option>
                            <option value="0" <?php selected($filters['featured'] ?? '', '0'); ?>>Non en vedette</option>
                        </select>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn-filter">🔍 Filtrer</button>
                        <button type="button" id="reset-filters" class="btn-reset">🔄 Réinitialiser</button>
                        <button type="button" id="toggle-advanced" class="btn-advanced">🔧 Avancé</button>
                        <div class="filter-results">
                            <span id="results-count"><?php echo number_format($data['total_images'] ?? 0); ?></span> 
                            image(s) trouvée(s)
                        </div>
                    </div>
                </div>
                
                <!-- Section avancée (masquée par défaut) -->
                <div class="filter-advanced" id="advanced-filters" style="display: none;">
                    <h4>🔬 Filtres avancés</h4>
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="min_exposure">⏱️ Exposition min. (min)</label>
                            <input type="number" id="min_exposure" name="min_exposure" min="0" 
                                   value="<?php echo esc_attr($filters['min_exposure'] ?? ''); ?>" 
                                   placeholder="Ex: 60" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="max_exposure">⏱️ Exposition max. (min)</label>
                            <input type="number" id="max_exposure" name="max_exposure" min="0" 
                                   value="<?php echo esc_attr($filters['max_exposure'] ?? ''); ?>" 
                                   placeholder="Ex: 600" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="min_aperture">🔍 Ouverture min. (mm)</label>
                            <input type="number" id="min_aperture" name="min_aperture" min="0" 
                                   value="<?php echo esc_attr($filters['min_aperture'] ?? ''); ?>" 
                                   placeholder="Ex: 80" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_from">📅 Du</label>
                            <input type="date" id="date_from" name="date_from" 
                                   value="<?php echo esc_attr($filters['date_from'] ?? ''); ?>" />
                        </div>
                        
                        <div class="filter-group">
                            <label for="date_to">📅 Au</label>
                            <input type="date" id="date_to" name="date_to" 
                                   value="<?php echo esc_attr($filters['date_to'] ?? ''); ?>" />
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function display_image() {
        $image_id = intval(get_query_var('astro_image_id'));
        
        if (!$image_id) {
            wp_die('Image non trouvée.');
        }
        
        $image = Astro_Images::get_image_by_id($image_id);
        
        if (!$image || $image->status !== 'published') {
            wp_die('Image non trouvée.');
        }
        
        // Incrémenter le compteur de vues
        Astro_Images::increment_views($image_id);
        
        // Récupérer les commentaires si activés
        $comments = array();
        if (get_option('astro_enable_comments', 1)) {
            $comments = $this->get_image_comments($image_id);
        }
        
        // Images similaires (même objet)
        $similar_images = Astro_Images::get_similar_images($image_id, $image->object_name, 6);
        
        $this->render_page('image', array(
            'image' => $image,
            'comments' => $comments,
            'similar_images' => $similar_images
        ));
    }
    
    private function display_object() {
        $object_name = sanitize_text_field(get_query_var('astro_object_name') ?: '');
        
        if (!$object_name) {
            wp_die('Objet non trouvé.');
        }
        
        // Décoder l'URL
        $object_name = urldecode($object_name);
        
        // Récupérer les informations de l'objet
        $object_info = Astro_Catalogs::get_object_by_name($object_name);
        
        // Récupérer toutes les images de cet objet
        $images = Astro_Images::search_images(array(
            'object' => $object_name,
            'status' => 'published'
        ));
        
        if (empty($images)) {
            wp_die('Aucune image trouvée pour cet objet.');
        }
        
        $this->render_page('object', array(
            'object_name' => $object_name,
            'object_info' => $object_info,
            'images' => $images
        ));
    }
    
    private function display_catalog() {
        $catalog_name = sanitize_text_field(get_query_var('astro_catalog_name') ?: '');
        
        if (!$catalog_name) {
            wp_die('Catalogue non trouvé.');
        }
        
        $catalog_name = strtoupper(urldecode($catalog_name));
        
        // Récupérer les objets du catalogue
        $objects = Astro_Catalogs::get_objects_by_catalog($catalog_name);
        
        if (empty($objects)) {
            wp_die('Catalogue non trouvé.');
        }
        
        $this->render_page('catalog', array(
            'catalog_name' => $catalog_name,
            'objects' => $objects
        ));
    }
    
    private function display_search() {
        $search_term = sanitize_text_field($_GET['s'] ?? '');
        $images = array();
        $objects = array();
        
        if ($search_term) {
            // Rechercher dans les images
            $images = Astro_Images::search_images(array(
                'search' => $search_term,
                'status' => 'published'
            ));
            
            // Rechercher dans les objets
            $objects = Astro_Catalogs::search_objects($search_term);
        }
        
        $this->render_page('search', array(
            'search_term' => $search_term,
            'images' => $images,
            'objects' => $objects
        ));
    }
    
    private function render_page($template, $data = array()) {
        // Définir les variables pour le template
        extract($data);
        
        // Commencer la capture de sortie
        ob_start();
        
        // Inclure le header du thème
        get_header();
        
        // Charger le template approprié
        $template_file = $this->get_template_path($template);
        
        if (file_exists($template_file)) {
            include $template_file;
        } else {
            // Template par défaut si le fichier n'existe pas
            $this->render_default_template($template, $data);
        }
        
        // Inclure le footer du thème
        get_footer();
        
        // Afficher le contenu
        echo ob_get_clean();
    }
    
    private function get_template_path($template) {
        // Chercher d'abord dans le thème actuel
        $theme_template = get_stylesheet_directory() . '/astro-portfolio/' . $template . '.php';
        if (file_exists($theme_template)) {
            return $theme_template;
        }
        
        // Puis dans le thème parent
        $parent_template = get_template_directory() . '/astro-portfolio/' . $template . '.php';
        if (file_exists($parent_template)) {
            return $parent_template;
        }
        
        // Finalement dans le plugin
        return plugin_dir_path(dirname(__FILE__)) . 'public/templates/' . $template . '.php';
    }
    
    private function render_default_template($template, $data) {
        echo '<div class="astro-portfolio-wrapper">';
        
        switch ($template) {
            case 'gallery':
                $this->render_gallery_template($data);
                break;
            case 'image':
                $this->render_image_template($data);
                break;
            case 'object':
                $this->render_object_template($data);
                break;
            case 'catalog':
                $this->render_catalog_template($data);
                break;
            case 'search':
                $this->render_search_template($data);
                break;
        }
        
        echo '</div>';
    }
    
    private function render_gallery_template($data) {
        ?>
        <div class="astro-gallery">
            <h1>Galerie d'astrophotographie</h1>
            
            <!-- Système de filtres avancé -->
            <div class="astro-search-filters">
                <?php echo $this->render_gallery_filters($data); ?>
            </div>
            
            <!-- Grille d'images -->
            <div class="astro-image-grid">
                <?php if (empty($data['images'])): ?>
                    <p>Aucune image trouvée.</p>
                <?php else: ?>
                    <?php foreach ($data['images'] as $image): ?>
                        <div class="astro-image-card">
                            <a href="/astro/image/<?php echo $image->id; ?>">
                                <?php if ($image->thumbnail_url): ?>
                                    <img src="<?php echo esc_url($image->thumbnail_url); ?>" alt="<?php echo esc_attr($image->title); ?>" />
                                <?php else: ?>
                                    <div class="astro-no-image">📷</div>
                                <?php endif; ?>
                            </a>
                            
                            <div class="astro-image-info">
                                <h3><a href="/astro/image/<?php echo $image->id; ?>"><?php echo esc_html($image->title ?? 'Sans titre'); ?></a></h3>
                                <p><a href="/astro/object/<?php echo urlencode($image->object_name ?? ''); ?>"><?php echo esc_html($image->object_name ?? 'Objet inconnu'); ?></a></p>
                                
                                <div class="astro-image-meta">
                                    <span>👁 <?php echo number_format($image->views ?? 0); ?></span>
                                    <?php if (get_option('astro_enable_likes', 1) && isset($image->likes)): ?>
                                        <span>❤ <?php echo number_format($image->likes); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($data['total_pages'] > 1): ?>
                <div class="astro-pagination">
                    <?php
                    $pagination_links = paginate_links(array(
                        'current' => $data['current_page'],
                        'total' => $data['total_pages'],
                        'prev_text' => '« Précédent',
                        'next_text' => 'Suivant »',
                        'type' => 'array'
                    ));
                    
                    if ($pagination_links) {
                        foreach ($pagination_links as $link) {
                            echo $link;
                        }
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_image_template($data) {
        $image = $data['image'];
        ?>
        <div class="astro-single-image">
            <h1><?php echo esc_html($image->title); ?></h1>
            
            <div class="astro-image-container">
                <?php if ($image->image_url): ?>
                    <img src="<?php echo esc_url($image->image_url); ?>" alt="<?php echo esc_attr($image->title); ?>" />
                <?php endif; ?>
            </div>
            
            <div class="astro-image-details">
                <div class="astro-object-info">
                    <h3>Objet: <a href="/astro/object/<?php echo urlencode($image->object_name); ?>"><?php echo esc_html($image->object_name); ?></a></h3>
                    <?php if ($image->object_type): ?>
                        <p><strong>Type:</strong> <?php echo esc_html($image->object_type); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="astro-technical-details">
                    <h3>Données techniques</h3>
                    <div class="astro-tech-grid">
                        <?php if ($image->telescope): ?>
                            <div><strong>Télescope:</strong> <?php echo esc_html($image->telescope); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($image->camera_name): ?>
                            <div><strong>Caméra:</strong> <?php echo esc_html($image->camera_name); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($image->mount_name): ?>
                            <div><strong>Monture:</strong> <?php echo esc_html($image->mount_name); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($image->total_exposure_time): ?>
                            <div><strong>Temps total:</strong> <?php echo esc_html($image->total_exposure_time); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($image->filters): ?>
                            <div><strong>Filtres:</strong> <?php echo esc_html($image->filters); ?></div>
                        <?php endif; ?>
                        
                        <?php if ($image->location): ?>
                            <div><strong>Lieu:</strong> <?php echo esc_html($image->location); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($image->description): ?>
                    <div class="astro-description">
                        <h3>Description</h3>
                        <p><?php echo nl2br(esc_html($image->description)); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="astro-image-actions">
                    <?php if (get_option('astro_enable_likes', 1)): ?>
                        <button type="button" class="astro-like-button" data-image-id="<?php echo $image->id; ?>">
                            ❤ <span class="like-count"><?php echo number_format($image->likes ?? 0); ?></span> J'aime
                        </button>
                    <?php endif; ?>
                    
                    <span class="astro-views">👁 <?php echo number_format($image->views ?? 0); ?> vues</span>
                </div>
            </div>
            
            <!-- Images similaires -->
            <?php if (!empty($data['similar_images'])): ?>
                <div class="astro-similar-images">
                    <h3>Autres images de <?php echo esc_html($image->object_name); ?></h3>
                    <div class="astro-similar-grid">
                        <?php foreach ($data['similar_images'] as $similar): ?>
                            <a href="/astro/image/<?php echo $similar->id; ?>" class="astro-similar-item">
                                <?php if ($similar->thumbnail_url): ?>
                                    <img src="<?php echo esc_url($similar->thumbnail_url); ?>" alt="<?php echo esc_attr($similar->title); ?>" />
                                <?php endif; ?>
                                <span><?php echo esc_html($similar->title); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    private function render_object_template($data) {
        $object_name = $data['object_name'];
        $object_info = $data['object_info'];
        $images = $data['images'];
        
        ?>
        <div class="astro-object-page">
            <h1><?php echo esc_html($object_name); ?></h1>
            
            <?php if ($object_info): ?>
                <div class="astro-object-details">
                    <?php if ($object_info->object_type): ?>
                        <p><strong>Type:</strong> <?php echo esc_html($object_info->object_type); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($object_info->constellation): ?>
                        <p><strong>Constellation:</strong> <?php echo esc_html($object_info->constellation); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($object_info->magnitude): ?>
                        <p><strong>Magnitude:</strong> <?php echo esc_html($object_info->magnitude); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($object_info->coordinates_ra && $object_info->coordinates_dec): ?>
                        <p><strong>Coordonnées:</strong> RA <?php echo esc_html($object_info->coordinates_ra); ?>, DEC <?php echo esc_html($object_info->coordinates_dec); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="astro-object-images">
                <h3><?php echo count($images); ?> image<?php echo count($images) > 1 ? 's' : ''; ?> de cet objet</h3>
                
                <div class="astro-image-grid">
                    <?php foreach ($images as $image): ?>
                        <div class="astro-image-card">
                            <a href="/astro/image/<?php echo $image->id; ?>">
                                <?php if ($image->thumbnail_url): ?>
                                    <img src="<?php echo esc_url($image->thumbnail_url); ?>" alt="<?php echo esc_attr($image->title); ?>" />
                                <?php else: ?>
                                    <div class="astro-no-image">📷</div>
                                <?php endif; ?>
                            </a>
                            
                            <div class="astro-image-info">
                                <h4><a href="/astro/image/<?php echo $image->id; ?>"><?php echo esc_html($image->title); ?></a></h4>
                                <div class="astro-image-meta">
                                    <span>👁 <?php echo number_format($image->views ?? 0); ?></span>
                                    <?php if (get_option('astro_enable_likes', 1)): ?>
                                        <span>❤ <?php echo number_format($image->likes ?? 0); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_catalog_template($data) {
        // Template pour afficher un catalogue (simplifié)
        echo '<h1>Catalogue ' . esc_html($data['catalog_name'] ?? 'Inconnu') . '</h1>';
        echo '<p>' . count($data['objects']) . ' objets dans ce catalogue.</p>';
    }
    
    private function render_search_template($data) {
        // Template pour les résultats de recherche (simplifié)
        echo '<h1>Résultats de recherche</h1>';
        if ($data['search_term']) {
            echo '<p>Recherche pour: <strong>' . esc_html($data['search_term'] ?? '') . '</strong></p>';
        }
    }
    
    public function handle_like_image() {
        check_ajax_referer('astro_public_nonce', 'nonce');
        
        $image_id = intval($_POST['image_id']);
        
        if (!$image_id) {
            wp_send_json_error(array('message' => 'ID d\'image invalide'));
        }
        
        // Vérifier si l'utilisateur peut liker (utiliser les cookies pour les visiteurs)
        $user_likes = get_transient('user_likes_' . $this->get_user_identifier());
        if (!$user_likes) {
            $user_likes = array();
        }
        
        if (in_array($image_id, $user_likes)) {
            wp_send_json_error(array('message' => 'Vous avez déjà aimé cette image'));
        }
        
        // Ajouter le like
        $result = Astro_Images::add_like($image_id);
        
        if ($result) {
            // Marquer que l'utilisateur a liké cette image
            $user_likes[] = $image_id;
            set_transient('user_likes_' . $this->get_user_identifier(), $user_likes, DAY_IN_SECONDS);
            
            $new_count = Astro_Images::get_likes_count($image_id);
            wp_send_json_success(array(
                'message' => 'Image aimée !',
                'likes_count' => $new_count
            ));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de l\'ajout du like'));
        }
    }
    
    public function load_more_images() {
        // Debug: Log des données reçues
        error_log('ASTRO DEBUG - load_more_images appelée avec: ' . print_r($_POST, true));
        
        // Vérification du nonce plus souple
        if (!wp_verify_nonce($_POST['nonce'], 'astro_public_nonce')) {
            error_log('ASTRO ERROR - Nonce invalide: ' . $_POST['nonce']);
            wp_send_json_error(array('message' => 'Nonce invalide'));
            return;
        }
        
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = intval($_POST['limit'] ?? get_option('astro_images_per_page', 12));
        $offset = ($page - 1) * $limit;
        
        error_log("ASTRO DEBUG - Page: $page, Limit: $limit, Offset: $offset");
        
        // Utiliser la même méthode que le shortcode pour récupérer les images
        $args = array(
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'post_status' => 'inherit',
            'posts_per_page' => $limit,
            'offset' => $offset,
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
        
        $images = get_posts($args);
        error_log('ASTRO DEBUG - Images trouvées: ' . count($images));
        
        if (empty($images)) {
            wp_send_json_error(array('message' => 'Plus d\'images à charger'));
            return;
        }
        
        ob_start();
        foreach ($images as $image) {
            $image_id = $image->ID;
            $title = get_the_title($image_id) ?: 'Image d\'astrophotographie';
            $image_url = wp_get_attachment_image_src($image_id, 'medium')[0];
            
            // Créer l'URL de détail
            $detail_page_id = get_option('astrofolio_detail_page');
            if ($detail_page_id && get_post($detail_page_id)) {
                $detail_url = get_permalink($detail_page_id) . '?image_id=' . $image_id;
            } else {
                $detail_url = '/astrofolio/image/' . $image_id;
            }
            
            // Utiliser le même style que le shortcode
            ?>
            <div style="background: white; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); padding: 10px; text-align: center;">
                <a href="<?php echo esc_url($detail_url); ?>" style="display: block; text-decoration: none; color: inherit;">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($title); ?>" style="width: 100%; height: 200px; object-fit: cover; border-radius: 4px; margin-bottom: 10px; cursor: pointer; transition: transform 0.2s ease;" onmouseover="this.style.transform='scale(1.02)'" onmouseout="this.style.transform='scale(1)'">
                    <div style="font-size: 14px; font-weight: bold; color: #333;"><?php echo esc_html($title); ?></div>
                </a>
            </div>
            <?php
        }
        $html = ob_get_clean();
        
        // Vérifier s'il y a encore plus d'images
        $args['posts_per_page'] = 1;
        $args['offset'] = $offset + $limit;
        $next_images = get_posts($args);
        $has_more = !empty($next_images);
        
        error_log('ASTRO DEBUG - HTML généré: ' . strlen($html) . ' caractères, has_more: ' . ($has_more ? 'true' : 'false'));
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => $has_more
        ));
    }
    
    /**
     * Gestion AJAX du filtrage de la galerie
     */
    public function handle_ajax_filter_gallery() {
        // Vérification des permissions et du nonce
        check_ajax_referer('astro_public_nonce', 'nonce');
        
        // Récupérer les filtres depuis la requête AJAX
        $filters = $_POST['filters'] ?? array();
        
        // Nettoyer les filtres
        foreach ($filters as $key => $value) {
            $filters[$key] = sanitize_text_field($value);
        }
        
        // Ajouter le statut publié
        $filters['status'] = 'published';
        
        try {
            // Récupérer les images filtrées
            $images = Astro_Images::search_images($filters);
            $total_images = Astro_Images::count_images($filters);
            
            // Générer le HTML
            ob_start();
            if (!empty($images)) {
                foreach ($images as $image) {
                    $this->render_gallery_image($image);
                }
            } else {
                echo '<div class="no-results">';
                echo '<p>🔍 Aucune image ne correspond à vos critères de recherche.</p>';
                echo '<p>Essayez de modifier vos filtres ou <button type="button" id="reset-filters-inline" class="button">réinitialisez la recherche</button>.</p>';
                echo '</div>';
            }
            $html = ob_get_clean();
            
            wp_send_json_success(array(
                'html' => $html,
                'count' => intval($total_images)
            ));
            
        } catch (Exception $e) {
            error_log('AstroFolio Filter Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Erreur lors du filtrage des images.'
            ));
        }
    }
    
    /**
     * Rendu d'une image dans la galerie
     */
    private function render_gallery_image($image) {
        $title = $image->title ?? 'Image d\'astrophotographie';
        $image_url = $image->thumbnail_url ?: $image->image_url;
        $object_name = $image->object_designation ?? $image->object_names ?? '';
        $telescope = $image->telescope ?? '';
        $camera = $image->camera_name ?? '';
        
        // URL de détail
        $detail_url = home_url("/astro/image/{$image->id}");
        
        ?>
        <div class="astro-gallery-item" data-image-id="<?php echo $image->id; ?>">
            <div class="image-card">
                <a href="<?php echo esc_url($detail_url); ?>" class="image-link">
                    <img src="<?php echo esc_url($image_url); ?>" 
                         alt="<?php echo esc_attr($title); ?>" 
                         class="gallery-image" 
                         loading="lazy" />
                    
                    <div class="image-overlay">
                        <h3 class="image-title"><?php echo esc_html($title); ?></h3>
                        <?php if ($object_name): ?>
                            <p class="image-object">🌌 <?php echo esc_html($object_name); ?></p>
                        <?php endif; ?>
                        <?php if ($telescope): ?>
                            <p class="image-telescope">🔭 <?php echo esc_html($telescope); ?></p>
                        <?php endif; ?>
                        <?php if ($camera): ?>
                            <p class="image-camera">📷 <?php echo esc_html($camera); ?></p>
                        <?php endif; ?>
                    </div>
                </a>
                
                <div class="image-actions">
                    <button class="astro-like-button" data-image-id="<?php echo $image->id; ?>">
                        ❤ <?php echo intval($image->likes_count ?? 0); ?>
                    </button>
                    <span class="view-count">👁 <?php echo intval($image->views_count ?? 0); ?></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_user_identifier() {
        // Créer un identifiant unique pour l'utilisateur (cookie-based pour les anonymes)
        if (is_user_logged_in()) {
            return 'user_' . get_current_user_id();
        } else {
            if (!isset($_COOKIE['astro_visitor_id'])) {
                $visitor_id = uniqid('visitor_', true);
                setcookie('astro_visitor_id', $visitor_id, time() + YEAR_IN_SECONDS, '/');
                return $visitor_id;
            } else {
                return $_COOKIE['astro_visitor_id'];
            }
        }
    }
    
    private function get_image_comments($image_id) {
        // Système de commentaires simple (à développer davantage)
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}astro_comments 
             WHERE image_id = %d AND status = 'approved' 
             ORDER BY created_date DESC",
            $image_id
        ));
    }
}