<?php
/**
 * Shortcodes pour l'intégration dans les posts et pages
 */
class Astro_Shortcodes {
    
    public function __construct() {
        add_action('init', array($this, 'register_shortcodes'));
    }
    
    public function register_shortcodes() {
        add_shortcode('astro_gallery', array($this, 'gallery_shortcode'));
        add_shortcode('astro_image', array($this, 'image_shortcode'));
        add_shortcode('astro_object', array($this, 'object_shortcode'));
        add_shortcode('astro_catalog', array($this, 'catalog_shortcode'));
        add_shortcode('astro_recent', array($this, 'recent_images_shortcode'));
        add_shortcode('astro_random', array($this, 'random_image_shortcode'));
        add_shortcode('astro_stats', array($this, 'stats_shortcode'));
        add_shortcode('astro_search', array($this, 'search_form_shortcode'));
    }
    
    /**
     * Shortcode pour afficher une galerie d'images
     * [astro_gallery limit="12" object="M31" catalog="Messier" columns="4" show_filters="true"]
     */
    public function gallery_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'object' => '',
            'catalog' => '',
            'type' => '',
            'columns' => 3,
            'show_titles' => 'true',
            'show_meta' => 'true',
            'show_filters' => 'true', // Nouveau paramètre pour afficher les filtres
            'size' => 'medium'
        ), $atts);
        
        // Récupérer les filtres depuis l'URL (pour les pages avec filtres actifs)
        $url_filters = array();
        if (!empty($_GET['search'])) $url_filters['search'] = sanitize_text_field($_GET['search']);
        if (!empty($_GET['object_type'])) $url_filters['object_type'] = sanitize_text_field($_GET['object_type']);
        if (!empty($_GET['telescope'])) $url_filters['telescope'] = sanitize_text_field($_GET['telescope']);
        if (!empty($_GET['camera'])) $url_filters['camera'] = sanitize_text_field($_GET['camera']);
        if (!empty($_GET['camera_type'])) $url_filters['camera_type'] = sanitize_text_field($_GET['camera_type']);
        if (!empty($_GET['telescope_type'])) $url_filters['telescope_type'] = sanitize_text_field($_GET['telescope_type']);
        if (!empty($_GET['constellation'])) $url_filters['constellation'] = sanitize_text_field($_GET['constellation']);
        if (!empty($_GET['year'])) $url_filters['year'] = sanitize_text_field($_GET['year']);
        if (!empty($_GET['featured'])) $url_filters['featured'] = sanitize_text_field($_GET['featured']);
        if (!empty($_GET['min_exposure'])) $url_filters['min_exposure'] = intval($_GET['min_exposure']);
        if (!empty($_GET['max_exposure'])) $url_filters['max_exposure'] = intval($_GET['max_exposure']);
        if (!empty($_GET['min_aperture'])) $url_filters['min_aperture'] = intval($_GET['min_aperture']);
        if (!empty($_GET['date_from'])) $url_filters['date_from'] = sanitize_text_field($_GET['date_from']);
        if (!empty($_GET['date_to'])) $url_filters['date_to'] = sanitize_text_field($_GET['date_to']);
        
        $filters = array_merge(array(
            'status' => 'published',
            'limit' => intval($atts['limit'])
        ), $url_filters);
        
        // Ajouter les filtres du shortcode
        if (!empty($atts['object'])) {
            $filters['object'] = sanitize_text_field($atts['object']);
        }
        
        if (!empty($atts['catalog'])) {
            $filters['catalog'] = sanitize_text_field($atts['catalog']);
        }
        
        if (!empty($atts['type'])) {
            $filters['object_type'] = sanitize_text_field($atts['type']);
        }
        
        $images = Astro_Images::search_images($filters);
        $total_images = Astro_Images::count_images($filters);
        
        if (empty($images) && empty($url_filters)) {
            return '<p class="astro-no-images">Aucune image trouvée.</p>';
        }
        
        $columns = max(1, min(6, intval($atts['columns'])));
        $show_titles = ($atts['show_titles'] === 'true');
        $show_meta = ($atts['show_meta'] === 'true');
        $show_filters = ($atts['show_filters'] === 'true');
        
        ob_start();
        ?>
        <div class="astro-gallery-container">
            <?php if ($show_filters): ?>
                <?php
                // Préparer les données pour les filtres
                $filter_data = $this->extract_filter_data();
                $data = array(
                    'filters' => $url_filters,
                    'filter_data' => $filter_data,
                    'total_images' => $total_images
                );
                ?>
                <div class="astro-search-filters">
                    <?php echo $this->render_gallery_filters($data); ?>
                </div>
            <?php endif; ?>
            
            <div class="astro-image-grid" style="display: grid; grid-template-columns: repeat(<?php echo $columns; ?>, 1fr); gap: 20px; margin: 20px 0;">
                <?php if (!empty($images)): ?>
                    <?php foreach ($images as $image): ?>
                        <div class="astro-gallery-item" style="position: relative; overflow: hidden; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); transition: transform 0.2s ease; background: white;">
                            <div style="position: relative; width: 100%; padding-bottom: 75%; overflow: hidden;">
                                <a href="<?php echo $this->get_image_link($image->id); ?>" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: block; text-decoration: none;">
                                    <?php if (!empty($image->thumbnail_url)): ?>
                                        <img src="<?php echo esc_url($image->thumbnail_url); ?>" 
                                             alt="<?php echo esc_attr($image->title); ?>" 
                                             style="width: 100%; height: 100%; object-fit: cover; display: block;" />
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: #f5f5f5; font-size: 48px;">📷</div>
                                    <?php endif; ?>
                                </a>
                                
                                <?php if ($show_titles || $show_meta): ?>
                                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.7)); color: white; padding: 15px 10px 10px; font-size: 14px;">
                                        <?php if ($show_titles): ?>
                                            <h4 style="margin: 0 0 5px 0; font-size: 16px; font-weight: bold;"><?php echo esc_html($image->title); ?></h4>
                                        <?php endif; ?>
                                        
                                        <?php if ($show_meta): ?>
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span style="font-weight: 500;"><?php echo esc_html($image->object_designation ?? $image->object_names ?? ''); ?></span>
                                                <div style="display: flex; gap: 10px; font-size: 12px;">
                                                    <span>👁 <?php echo number_format($image->views_count ?? 0); ?></span>
                                                    <?php if (get_option('astro_enable_likes', 1)): ?>
                                                        <span>❤ <?php echo number_format($image->likes_count ?? 0); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="astro-no-results" style="grid-column: 1 / -1; text-align: center; padding: 40px 20px; background: #f9f9f9; border-radius: 8px;">
                        <p style="margin: 0; font-size: 18px; color: #666;">🔍 Aucune image ne correspond à vos critères.</p>
                        <p style="margin: 10px 0 0 0; color: #888;">Essayez de modifier vos filtres ou réinitialisez la recherche.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
        
        .astro-gallery-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .astro-no-image {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            color: #999;
        }
        
        .astro-image-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
            color: white;
            padding: 20px 15px 15px;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }
        
        .astro-gallery-item:hover .astro-image-overlay {
            transform: translateY(0);
        }
        
        .astro-image-title {
            margin: 0 0 5px 0;
            font-size: 1em;
            font-weight: bold;
        }
        
        .astro-image-meta {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .astro-object-name {
            display: block;
            margin-bottom: 5px;
        }
        
        .astro-meta-stats span {
            margin-right: 10px;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher une image spécifique
     * [astro_image id="123" size="large" show_details="true"]
     */
    public function image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => '',
            'size' => 'large',
            'show_details' => 'false',
            'show_title' => 'true',
            'link' => 'true'
        ), $atts);
        
        $image_id = intval($atts['id']);
        
        if (!$image_id) {
            return '<p class="astro-error">ID d\'image requis.</p>';
        }
        
        $image = Astro_Images::get_image_by_id($image_id);
        
        if (!$image || $image->status !== 'published') {
            return '<p class="astro-error">Image non trouvée.</p>';
        }
        
        $show_details = ($atts['show_details'] === 'true');
        $show_title = ($atts['show_title'] === 'true');
        $link = ($atts['link'] === 'true');
        
        ob_start();
        ?>
        <div class="astro-shortcode-image">
            <?php if ($show_title): ?>
                <h3 class="astro-image-title">
                    <?php if ($link): ?>
                        <a href="<?php echo $this->get_image_link($image->id); ?>"><?php echo esc_html($image->title); ?></a>
                    <?php else: ?>
                        <?php echo esc_html($image->title); ?>
                    <?php endif; ?>
                </h3>
            <?php endif; ?>
            
            <div class="astro-image-container">
                <?php if ($link): ?>
                    <a href="<?php echo $this->get_image_link($image->id); ?>">
                <?php endif; ?>
                
                <?php if ($image->image_url): ?>
                    <img src="<?php echo esc_url($image->image_url); ?>" 
                         alt="<?php echo esc_attr($image->title); ?>" 
                         class="astro-single-image" />
                <?php endif; ?>
                
                <?php if ($link): ?>
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($show_details): ?>
                <div class="astro-image-details">
                    <p><strong>Objet:</strong> 
                        <a href="<?php echo $this->get_object_link($image->object_name); ?>">
                            <?php echo esc_html($image->object_name); ?>
                        </a>
                    </p>
                    
                    <?php if ($image->telescope): ?>
                        <p><strong>Télescope:</strong> <?php echo esc_html($image->telescope); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($image->camera_name): ?>
                        <p><strong>Caméra:</strong> <?php echo esc_html($image->camera_name); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($image->total_exposure_time): ?>
                        <p><strong>Temps total:</strong> <?php echo esc_html($image->total_exposure_time); ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .astro-shortcode-image {
            margin: 20px 0;
            text-align: center;
        }
        
        .astro-shortcode-image .astro-image-title {
            margin-bottom: 15px;
        }
        
        .astro-shortcode-image .astro-single-image {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .astro-shortcode-image .astro-image-details {
            margin-top: 15px;
            text-align: left;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
        }
        
        .astro-shortcode-image .astro-image-details p {
            margin: 8px 0;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les images d'un objet
     * [astro_object name="M31" limit="6"]
     */
    public function object_shortcode($atts) {
        $atts = shortcode_atts(array(
            'name' => '',
            'limit' => 6,
            'columns' => 3,
            'show_info' => 'true'
        ), $atts);
        
        $object_name = sanitize_text_field($atts['name']);
        
        if (empty($object_name)) {
            return '<p class="astro-error">Nom d\'objet requis.</p>';
        }
        
        $images = Astro_Images::search_images(array(
            'object' => $object_name,
            'status' => 'published',
            'limit' => intval($atts['limit'])
        ));
        
        if (empty($images)) {
            return '<p class="astro-no-images">Aucune image trouvée pour ' . esc_html($object_name) . '.</p>';
        }
        
        $show_info = ($atts['show_info'] === 'true');
        
        ob_start();
        ?>
        <div class="astro-shortcode-object">
            <?php if ($show_info): ?>
                <h3>Images de <?php echo esc_html($object_name); ?></h3>
                <?php
                $object_info = Astro_Catalogs::get_object_by_name($object_name);
                if ($object_info): ?>
                    <div class="astro-object-info">
                        <?php if ($object_info->object_type): ?>
                            <span class="astro-object-type"><?php echo esc_html($object_info->object_type); ?></span>
                        <?php endif; ?>
                        
                        <?php if ($object_info->constellation): ?>
                            <span class="astro-constellation">dans <?php echo esc_html($object_info->constellation); ?></span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php
            // Réutiliser le shortcode de galerie
            $gallery_atts = array(
                'object' => $object_name,
                'limit' => $atts['limit'],
                'columns' => $atts['columns']
            );
            
            echo $this->gallery_shortcode($gallery_atts);
            ?>
            
            <?php if (count($images) > 0): ?>
                <p class="astro-view-more">
                    <a href="<?php echo $this->get_object_link($object_name); ?>" class="button">
                        Voir toutes les images de <?php echo esc_html($object_name); ?>
                    </a>
                </p>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher un catalogue
     * [astro_catalog name="Messier" limit="20" view="grid"]
     */
    public function catalog_shortcode($atts) {
        $atts = shortcode_atts(array(
            'name' => '',
            'limit' => 20,
            'view' => 'grid',
            'columns' => 4
        ), $atts);
        
        $catalog_name = strtoupper(sanitize_text_field($atts['name']));
        
        if (empty($catalog_name)) {
            return '<p class="astro-error">Nom de catalogue requis.</p>';
        }
        
        $objects = Astro_Catalogs::get_objects_by_catalog($catalog_name, intval($atts['limit']));
        
        if (empty($objects)) {
            return '<p class="astro-no-objects">Aucun objet trouvé dans le catalogue ' . esc_html($catalog_name) . '.</p>';
        }
        
        $view = in_array($atts['view'], array('grid', 'list')) ? $atts['view'] : 'grid';
        $columns = max(2, min(6, intval($atts['columns'])));
        
        ob_start();
        ?>
        <div class="astro-shortcode-catalog">
            <h3>Catalogue <?php echo esc_html($catalog_name); ?></h3>
            
            <div class="astro-catalog-<?php echo $view; ?> <?php echo $view === 'grid' ? 'columns-' . $columns : ''; ?>">
                <?php foreach ($objects as $object): ?>
                    <div class="astro-catalog-item">
                        <?php if ($view === 'grid'): ?>
                            <div class="astro-object-card">
                                <h4><?php echo esc_html($object->object_name); ?></h4>
                                
                                <?php if ($object->object_type): ?>
                                    <p class="astro-object-type"><?php echo esc_html($object->object_type); ?></p>
                                <?php endif; ?>
                                
                                <?php if ($object->constellation): ?>
                                    <p class="astro-constellation"><?php echo esc_html($object->constellation); ?></p>
                                <?php endif; ?>
                                
                                <a href="<?php echo $this->get_object_link($object->designation); ?>" class="astro-view-object">
                                    Voir les images
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="astro-object-row">
                                <strong><?php echo esc_html($object->object_name); ?></strong>
                                
                                <?php if ($object->object_type): ?>
                                    <span class="astro-object-type"><?php echo esc_html($object->object_type); ?></span>
                                <?php endif; ?>
                                
                                <?php if ($object->constellation): ?>
                                    <span class="astro-constellation"><?php echo esc_html($object->constellation); ?></span>
                                <?php endif; ?>
                                
                                <a href="<?php echo $this->get_object_link($object->object_name); ?>">Voir</a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <p class="astro-catalog-footer">
                <a href="<?php echo $this->get_catalog_link($catalog_name); ?>" class="button">
                    Voir le catalogue complet
                </a>
            </p>
        </div>
        
        <style>
        .astro-catalog-grid {
            display: grid;
            gap: 15px;
            margin: 20px 0;
        }
        
        .astro-catalog-grid.columns-2 { grid-template-columns: repeat(2, 1fr); }
        .astro-catalog-grid.columns-3 { grid-template-columns: repeat(3, 1fr); }
        .astro-catalog-grid.columns-4 { grid-template-columns: repeat(4, 1fr); }
        .astro-catalog-grid.columns-5 { grid-template-columns: repeat(5, 1fr); }
        .astro-catalog-grid.columns-6 { grid-template-columns: repeat(6, 1fr); }
        
        .astro-object-card {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e0e0e0;
        }
        
        .astro-object-card h4 {
            margin: 0 0 10px 0;
            color: #333;
        }
        
        .astro-object-card p {
            margin: 5px 0;
            color: #666;
            font-size: 0.9em;
        }
        
        .astro-catalog-list .astro-object-row {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .astro-catalog-list .astro-object-row:last-child {
            border-bottom: none;
        }
        
        .astro-view-object {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            background: #0073aa;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .astro-view-object:hover {
            background: #005a87;
            color: white;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les images récentes
     * [astro_recent limit="5" columns="5"]
     */
    public function recent_images_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'columns' => 5,
            'show_date' => 'true'
        ), $atts);
        
        $images = Astro_Images::get_recent_images(intval($atts['limit']));
        
        if (empty($images)) {
            return '<p class="astro-no-images">Aucune image récente.</p>';
        }
        
        // Utiliser le shortcode de galerie existant
        $gallery_atts = array(
            'limit' => $atts['limit'],
            'columns' => $atts['columns'],
            'show_meta' => $atts['show_date']
        );
        
        ob_start();
        ?>
        <div class="astro-shortcode-recent">
            <h3>Images récentes</h3>
            <?php echo $this->gallery_shortcode($gallery_atts); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher une image aléatoire
     * [astro_random]
     */
    public function random_image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'size' => 'medium',
            'show_details' => 'true'
        ), $atts);
        
        $image = Astro_Images::get_random_image();
        
        if (!$image) {
            return '<p class="astro-no-images">Aucune image disponible.</p>';
        }
        
        // Utiliser le shortcode d'image existant
        $image_atts = array(
            'id' => $image->id,
            'size' => $atts['size'],
            'show_details' => $atts['show_details'],
            'show_title' => 'true'
        );
        
        ob_start();
        ?>
        <div class="astro-shortcode-random">
            <h3>Image aléatoire</h3>
            <?php echo $this->image_shortcode($image_atts); ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher les statistiques
     * [astro_stats]
     */
    public function stats_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show' => 'all'  // all, images, objects, equipment
        ), $atts);
        
        global $wpdb;
        
        $stats = array();
        
        if ($atts['show'] === 'all' || $atts['show'] === 'images') {
            $stats['images'] = array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_images WHERE status = 'published'"),
                'this_month' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_images WHERE status = 'published' AND MONTH(upload_date) = MONTH(NOW()) AND YEAR(upload_date) = YEAR(NOW())")
            );
        }
        
        if ($atts['show'] === 'all' || $atts['show'] === 'objects') {
            $stats['objects'] = array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT designation) FROM {$wpdb->prefix}astro_objects"),
                'catalogs' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT catalog) FROM {$wpdb->prefix}astro_objects")
            );
        }
        
        if ($atts['show'] === 'all' || $atts['show'] === 'equipment') {
            $stats['equipment'] = array(
                'telescopes' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_equipment WHERE type = 'telescope'"),
                'cameras' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_equipment WHERE type = 'camera'")
            );
        }
        
        ob_start();
        ?>
        <div class="astro-shortcode-stats">
            <div class="astro-stats-grid">
                <?php if (isset($stats['images'])): ?>
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['images']['total']); ?></span>
                        <span class="astro-stat-label">Images publiées</span>
                    </div>
                    
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['images']['this_month']); ?></span>
                        <span class="astro-stat-label">Ce mois</span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($stats['objects'])): ?>
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['objects']['total']); ?></span>
                        <span class="astro-stat-label">Objets photographiés</span>
                    </div>
                    
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['objects']['catalogs']); ?></span>
                        <span class="astro-stat-label">Catalogues</span>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($stats['equipment'])): ?>
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['equipment']['telescopes']); ?></span>
                        <span class="astro-stat-label">Télescopes</span>
                    </div>
                    
                    <div class="astro-stat-item">
                        <span class="astro-stat-number"><?php echo number_format($stats['equipment']['cameras']); ?></span>
                        <span class="astro-stat-label">Caméras</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
        .astro-shortcode-stats {
            margin: 20px 0;
        }
        
        .astro-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .astro-stat-item {
            text-align: center;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
        }
        
        .astro-stat-number {
            display: block;
            font-size: 2em;
            font-weight: bold;
            color: #0073aa;
            margin-bottom: 5px;
        }
        
        .astro-stat-label {
            font-size: 0.9em;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Shortcode pour afficher un formulaire de recherche
     * [astro_search placeholder="Rechercher un objet..."]
     */
    public function search_form_shortcode($atts) {
        $atts = shortcode_atts(array(
            'placeholder' => 'Rechercher un objet astronomique...',
            'button_text' => 'Rechercher',
            'show_filters' => 'false'
        ), $atts);
        
        $show_filters = ($atts['show_filters'] === 'true');
        
        ob_start();
        ?>
        <div class="astro-shortcode-search">
            <form method="get" action="<?php echo home_url('/astro/search/'); ?>" class="astro-search-form">
                <div class="astro-search-input-group">
                    <input type="text" 
                           name="s" 
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           value="<?php echo esc_attr($_GET['s'] ?? ''); ?>"
                           class="astro-search-input" />
                    
                    <button type="submit" class="astro-search-button">
                        <?php echo esc_html($atts['button_text'] ?? 'Voir plus'); ?>
                    </button>
                </div>
                
                <?php if ($show_filters): ?>
                    <div class="astro-search-filters">
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
                        
                        <select name="type">
                            <option value="">Tous les types</option>
                            <option value="galaxy" <?php selected($_GET['type'] ?? '', 'galaxy'); ?>>Galaxies</option>
                            <option value="nebula" <?php selected($_GET['type'] ?? '', 'nebula'); ?>>Nébuleuses</option>
                            <option value="star_cluster" <?php selected($_GET['type'] ?? '', 'star_cluster'); ?>>Amas stellaires</option>
                            <option value="planetary_nebula" <?php selected($_GET['type'] ?? '', 'planetary_nebula'); ?>>Nébuleuses planétaires</option>
                        </select>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <style>
        .astro-shortcode-search {
            margin: 20px 0;
        }
        
        .astro-search-form {
            max-width: 600px;
        }
        
        .astro-search-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: <?php echo $show_filters ? '15px' : '0'; ?>;
        }
        
        .astro-search-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        
        .astro-search-input:focus {
            border-color: #0073aa;
            outline: none;
        }
        
        .astro-search-button {
            padding: 12px 20px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .astro-search-button:hover {
            background: #005a87;
        }
        
        .astro-search-filters {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        
        .astro-search-filters form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .astro-search-filters input,
        .astro-search-filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .astro-search-filters button {
            padding: 8px 16px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .astro-search-filters button:hover {
            background: #005a87;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Extraction des données pour les filtres
     */
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
    
    /**
     * Rendu des filtres de galerie
     */
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
                
                <!-- Actions -->
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
                        <div class="filter-results">
                            <span id="results-count"><?php echo number_format($data['total_images'] ?? 0); ?></span> 
                            image(s) trouvée(s)
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .astro-search-filters select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
        }
        </style>
        <?php
        
        return ob_get_clean();
    }
    
    // Fonctions utilitaires pour générer les liens
    
    private function get_image_link($image_id) {
        if (get_option('permalink_structure')) {
            return home_url('/astro/image/' . $image_id . '/');
        } else {
            return home_url('?astro_page=image&astro_image_id=' . $image_id);
        }
    }
    
    private function get_object_link($object_name) {
        if (get_option('permalink_structure')) {
            return home_url('/astro/object/' . urlencode($object_name) . '/');
        } else {
            return home_url('?astro_page=object&astro_object_name=' . urlencode($object_name));
        }
    }
    
    private function get_catalog_link($catalog_name) {
        if (get_option('permalink_structure')) {
            return home_url('/astro/catalog/' . urlencode($catalog_name) . '/');
        } else {
            return home_url('?astro_page=catalog&astro_catalog_name=' . urlencode($catalog_name));
        }
    }
}