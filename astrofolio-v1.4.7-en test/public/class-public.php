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
        add_action('wp_ajax_astro_load_more_images', array($this, 'load_more_images'));
        add_action('wp_ajax_nopriv_astro_load_more_images', array($this, 'load_more_images'));
    }
    
    public function enqueue_public_scripts() {
        if ($this->is_astro_page()) {
            $plugin_url = plugin_dir_url(dirname(__FILE__)) ?: (defined('ANC_PLUGIN_URL') ? ANC_PLUGIN_URL : '');
            
            wp_enqueue_style(
                'astro-public-css',
                $plugin_url . 'public/css/public.css',
                array(),
                '1.0.0'
            );
            
            wp_enqueue_script(
                'astro-public-js',
                $plugin_url . 'public/js/public.js',
                array('jquery'),
                '1.0.0',
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
        
        $filters = array(
            'status' => 'published',
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        );
        
        // Filtres depuis l'URL
        if (isset($_GET['object']) && !empty($_GET['object'])) {
            $filters['object'] = sanitize_text_field($_GET['object']);
        }
        
        if (isset($_GET['catalog']) && !empty($_GET['catalog'])) {
            $filters['catalog'] = sanitize_text_field($_GET['catalog']);
        }
        
        $images = Astro_Images::search_images($filters);
        $total_images = Astro_Images::count_images($filters);
        
        $this->render_page('gallery', array(
            'images' => $images,
            'current_page' => $page,
            'total_pages' => ceil($total_images / $per_page),
            'per_page' => $per_page,
            'filters' => $filters
        ));
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
            
            <!-- Filtres -->
            <div class="astro-filters">
                <form method="get" action="">
                    <input type="text" name="search" placeholder="Rechercher un objet..." value="<?php echo esc_attr($_GET['search'] ?? ''); ?>" />
                    <select name="catalog">
                        <option value="">Tous les catalogues</option>
                        <?php
                        $catalogs = Astro_Catalogs::get_available_catalogs();
                        foreach ($catalogs as $catalog) {
                            $selected = ($_GET['catalog'] ?? '') === $catalog ? 'selected' : '';
                            echo '<option value="' . esc_attr($catalog) . '" ' . $selected . '>' . esc_html($catalog) . '</option>';
                        }
                        ?>
                    </select>
                    <input type="submit" value="Filtrer" class="button" />
                </form>
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
        check_ajax_referer('astro_public_nonce', 'nonce');
        
        $page = max(1, intval($_POST['page']));
        $per_page = get_option('astro_images_per_page', 12);
        
        $filters = array(
            'status' => 'published',
            'limit' => $per_page,
            'offset' => ($page - 1) * $per_page
        );
        
        $images = Astro_Images::search_images($filters);
        
        if (empty($images)) {
            wp_send_json_error(array('message' => 'Plus d\'images à charger'));
        }
        
        ob_start();
        foreach ($images as $image) {
            // Renderiser les cartes d'images
            ?>
            <div class="astro-image-card">
                <a href="/astro/image/<?php echo $image->id; ?>">
                    <?php if ($image->thumbnail_url): ?>
                        <img src="<?php echo esc_url($image->thumbnail_url); ?>" alt="<?php echo esc_attr($image->title); ?>" />
                    <?php else: ?>
                        <div class="astro-no-image">📷</div>
                    <?php endif; ?>
                </a>
                
                <div class="astro-image-info">
                    <h3><a href="/astro/image/<?php echo $image->id; ?>"><?php echo esc_html($image->title); ?></a></h3>
                    <p><a href="/astro/object/<?php echo urlencode($image->object_name); ?>"><?php echo esc_html($image->object_name); ?></a></p>
                    
                    <div class="astro-image-meta">
                        <span>👁 <?php echo number_format($image->views ?? 0); ?></span>
                        <?php if (get_option('astro_enable_likes', 1)): ?>
                            <span>❤ <?php echo number_format($image->likes ?? 0); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'has_more' => count($images) === $per_page
        ));
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