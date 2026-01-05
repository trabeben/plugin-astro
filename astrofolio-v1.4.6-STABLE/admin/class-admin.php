<?php

/**
 * =============================================================================
 * CLASSE D'ADMINISTRATION ASTROFOLIO
 * =============================================================================
 * 
 * Cette classe gère toute l'interface d'administration du plugin AstroFolio
 * 
 * 🎯 RESPONSABILITÉS PRINCIPALES :
 * - Création du menu d'administration WordPress
 * - Gestion des pages d'administration (Dashboard, Upload, Galerie, etc.)
 * - Chargement des scripts et styles d'administration
 * - Interface de téléchargement d'images avec métadonnées
 * - Gestion de la galerie d'images existantes
 * - Outils de diagnostic et récupération
 * 
 * 📋 PAGES D'ADMINISTRATION :
 * - Dashboard principal : Vue d'ensemble et statistiques
 * - Upload Image : Téléchargement avec formulaire de métadonnées
 * - Galerie : Visualisation et édition des images existantes
 * - Catalogues : Gestion des catalogues astronomiques
 * - Import : Importation en lot d'images et données
 * - Diagnostic : Outils de débogage et réparation
 * 
 * 🔐 GESTION DES PERMISSIONS :
 * - manage_options : Accès au dashboard et configuration
 * - edit_astro_images : Upload et édition d'images
 * - Contrôles granulaires par fonctionnalité
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Admin
 */
class Astro_Admin {

    /**
     * Constructeur de la classe d'administration
     * 
     * Enregistre tous les hooks nécessaires pour l'interface d'admin :
     * - Menu d'administration
     * - Scripts et styles
     * 
     * @since 1.4.6
     */
    public function __construct() {
        // Hook pour ajouter le menu d'administration
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Hook pour charger les scripts et styles d'administration
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Créer le menu d'administration dans WordPress
     * 
     * Structure du menu :
     * AstroFolio (menu principal)
     * ├── Dashboard (page principale)
     * ├── Upload Image (téléchargement)
     * ├── Galerie (visualisation)
     * ├── Catalogues (gestion des catalogues)
     * ├── Import (importation en lot)
     * └── Diagnostic (outils de débogage)
     * 
     * @since 1.4.6
     * @return void
     */
    public function add_admin_menu() {
        // =================================================================
        // PAGE PRINCIPALE - Menu racine avec icône caméra
        // =================================================================
        add_menu_page(
            'AstroFolio',                    // Titre de la page
            'AstroFolio',                    // Texte du menu
            'manage_options',                // Capacité requise (administrateur)
            'astrofolio',                    // Slug de la page
            array($this, 'dashboard_page'),  // Fonction de rendu
            'dashicons-camera',              // Icône du menu (caméra)
            30                               // Position dans le menu (après Médias)
        );

        // =================================================================
        // SOUS-PAGES DU MENU ASTROFOLIO
        // =================================================================
        
        // Dashboard - Page d'accueil avec statistiques
        add_submenu_page(
            'astrofolio',                    // Menu parent
            'Dashboard',                     // Titre de la page
            'Dashboard',                     // Texte du menu
            'manage_options',                // Capacité requise
            'astrofolio',                    // Slug (même que parent = page par défaut)
            array($this, 'dashboard_page')   // Fonction de rendu
        );

        // Upload - Interface de téléchargement d'images
        add_submenu_page(
            'astrofolio',                    // Menu parent
            'Télécharger une Image',         // Titre de la page
            'Upload Image',                  // Texte du menu
            'edit_astro_images',            // Capacité personnalisée pour l'édition
            'astrofolio-upload',            // Slug unique
            array($this, 'upload_image_page') // Fonction de rendu
        );

        // Galerie - Visualisation et gestion des images
        add_submenu_page(
            'astrofolio',                    // Menu parent
            'Galerie',                       // Titre de la page
            'Galerie',                       // Texte du menu
            'edit_astro_images',            // Capacité pour l'édition
            'astrofolio-gallery',
            array($this, 'gallery_page')
        );

        add_submenu_page(
            'astrofolio',
            '🚀 Upload Enhanced',
            '🚀 Upload Enhanced', 
            'edit_astro_images',
            'astrofolio-upload-enhanced',
            array($this, 'upload_enhanced_page')
        );

        add_submenu_page(
            'astrofolio',
            'Catalogues',
            'Catalogues',
            'manage_astro_catalogs',
            'astrofolio-catalogs',
            array($this, 'catalogs_page')
        );

        add_submenu_page(
            'astrofolio',
            '🌐 Gestion Public',
            '🌐 Gestion Public',
            'manage_options',
            'astrofolio-public-admin',
            array($this, 'public_admin_page')
        );

        add_submenu_page(
            'astrofolio',
            '🔄 Récupération Images',
            '🔄 Récupération',
            'manage_options',
            'astrofolio-recovery',
            array($this, 'recovery_page')
        );

        // Pages de diagnostic (masquées)
        add_submenu_page(
            null,
            'Test Upload',
            'Test Upload',
            'manage_options',
            'astrofolio-test-upload',
            array($this, 'test_upload_page')
        );

        add_submenu_page(
            null,
            'Debug Production',
            'Debug Production',
            'manage_options',
            'astrofolio-debug-production',
            array($this, 'debug_production_page')
        );

        add_submenu_page(
            null,
            'Diagnostic Uploads',
            'Diagnostic Uploads',
            'manage_options',
            'astrofolio-diagnostic-uploads',
            array($this, 'diagnostic_uploads_page')
        );
    }

    public function enqueue_admin_scripts($hook) {
        if (empty($hook) || strpos($hook, 'astrofolio') === false) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery');
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        wp_enqueue_style(
            'astrofolio-admin',
            ANC_PLUGIN_URL . 'admin/css/admin.css',
            array(),
            ANC_VERSION
        );

        // CSS amélioré pour les métadonnées
        wp_enqueue_style(
            'astrofolio-metadata-enhanced',
            ANC_PLUGIN_URL . 'admin/css/metadata-enhanced.css',
            array('astrofolio-admin'),
            ANC_VERSION
        );

        // CSS pour l'administration publique
        if ($hook === 'astrofolio_page_astrofolio-public-admin') {
            wp_enqueue_style(
                'astrofolio-admin-public',
                ANC_PLUGIN_URL . 'admin/css/admin-public.css',
                array('astrofolio-admin'),
                ANC_VERSION
            );
        }

        wp_enqueue_script(
            'astrofolio-admin',
            ANC_PLUGIN_URL . 'admin/js/admin.js',
            array('jquery', 'media-upload', 'thickbox'),
            ANC_VERSION,
            true
        );

        // JavaScript amélioré pour les métadonnées
        wp_enqueue_script(
            'astrofolio-metadata-enhanced',
            ANC_PLUGIN_URL . 'admin/js/metadata-enhanced.js',
            array('jquery', 'astrofolio-admin'),
            ANC_VERSION,
            true
        );

        // JavaScript pour l'administration publique
        if ($hook === 'astrofolio_page_astrofolio-public-admin') {
            wp_enqueue_script(
                'astrofolio-admin-public',
                ANC_PLUGIN_URL . 'admin/js/admin-public.js',
                array('jquery', 'astrofolio-admin'),
                ANC_VERSION,
                true
            );
            
            wp_localize_script('astrofolio-admin-public', 'astroAdmin', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('astro_admin_nonce')
            ));
        }
    }

    public function dashboard_page() {
        $total_images = $this->count_astro_images();
        $catalog_objects = $this->count_catalog_objects();
        ?>
        <div class="wrap">
            <h1>🚀 AstroFolio Dashboard</h1>
            
            <div class="astro-dashboard-stats">
                <div class="astro-stat-box">
                    <h3>Images Astrophoto</h3>
                    <p class="stat-number"><?php echo $total_images; ?></p>
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-gallery'); ?>" class="button">Voir la galerie</a>
                </div>
                
                <div class="astro-stat-box">
                    <h3>Objets Catalogués</h3>
                    <p class="stat-number"><?php echo number_format($catalog_objects); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=astrofolio-catalogs'); ?>" class="button">Voir les catalogues</a>
                </div>
            </div>

            <div class="astro-quick-actions">
                <a href="<?php echo admin_url('admin.php?page=astrofolio-upload'); ?>" class="button button-primary">📸 Télécharger une Image</a>
                <a href="<?php echo admin_url('admin.php?page=astrofolio-gallery'); ?>" class="button">🖼️ Voir la Galerie</a>
                <a href="<?php echo admin_url('admin.php?page=astrofolio-catalogs'); ?>" class="button">📋 Gérer les Catalogues</a>
            </div>
        </div>

        <style>
        .astro-dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .astro-stat-box {
            background: white;
            padding: 20px;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            text-align: center;
        }
        
        .astro-stat-box h3 {
            margin-top: 0;
            color: #23282d;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #0073aa;
            margin: 10px 0;
        }
        
        .astro-quick-actions {
            display: flex;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }
        
        .astro-quick-actions .button {
            padding: 10px 20px;
        }
        </style>
        <?php
    }

    public function upload_image_page() {
        $message = '';
        $message_type = '';
        
        // Traitement du formulaire simple
        if (isset($_POST['upload_image'])) {
            error_log('AstroFolio Debug: Upload simple - traitement');
            
            // Vérification du nonce
            if (!wp_verify_nonce($_POST['upload_nonce'], 'astrofolio_upload')) {
                $message = 'Erreur de sécurité. Veuillez rafraîchir la page et réessayer.';
                $message_type = 'error';
            } else {
                error_log('AstroFolio Debug: Nonce valide');
                
                // Vérification fichier
                if (empty($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
                    $message = 'Erreur: Aucun fichier sélectionné ou erreur d\'upload.';
                    $message_type = 'error';
                } else {
                    try {
                        $result = $this->handle_simple_upload();
                        $message = $result['message'];
                        $message_type = $result['success'] ? 'success' : 'error';
                    } catch (Exception $e) {
                        error_log('AstroFolio Debug: Exception: ' . $e->getMessage());
                        $message = 'Erreur système: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1>📸 Télécharger une Image Astro</h1>
            
            <?php if ($message): ?>
                <div class="notice notice-<?php echo $message_type; ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                    <?php if ($message_type === 'success'): ?>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=astrofolio-gallery'); ?>" class="button button-primary">
                                🖼️ Voir la Galerie
                            </a>
                            <a href="<?php echo admin_url('admin.php?page=astrofolio-upload'); ?>" class="button">
                                📸 Télécharger une Autre Image
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="astro-upload-container">
                <h2>📸 Upload d'Image Astrophotographie</h2>
                <p>Uploadez votre image avec les métadonnées essentielles.</p>
                
                <form method="post" enctype="multipart/form-data" class="astro-upload-form">
                    <?php wp_nonce_field('astrofolio_upload', 'upload_nonce'); ?>
                    
                    <!-- Fichier et infos de base -->
                    <div class="upload-section">
                        <h3>📁 Fichier et Informations de Base</h3>
                        
                        <div class="form-field">
                            <label for="image_file">Fichier Image *</label>
                            <input type="file" id="image_file" name="image_file" accept="image/*" required>
                            <p class="description">Formats acceptés : JPG, PNG, GIF. Taille max : <?php echo size_format(wp_max_upload_size()); ?></p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="title">Titre de l'image *</label>
                                <input type="text" id="title" name="title" required placeholder="ex: M31 - Galaxie d'Andromède">
                            </div>
                            
                            <div class="form-field">
                                <label for="object_name">Nom de l'objet</label>
                                <input type="text" id="object_name" name="object_name" placeholder="M31, NGC 7000...">
                            </div>
                        </div>
                        
                        <div class="form-field">
                            <label for="description">Description</label>
                            <textarea id="description" name="description" rows="3" placeholder="Description de votre image..."></textarea>
                        </div>
                    </div>
                    
                    <!-- Équipement principal -->
                    <div class="upload-section">
                        <h3>🔭 Équipement Principal</h3>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="telescope">Télescope</label>
                                <input type="text" id="telescope" name="telescope" placeholder="Celestron EdgeHD 8...">
                            </div>
                            
                            <div class="form-field">
                                <label for="camera">Caméra</label>
                                <input type="text" id="camera" name="camera" placeholder="ZWO ASI2600MC-Pro...">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Paramètres d'acquisition -->
                    <div class="upload-section">
                        <h3>⚙️ Paramètres d'Acquisition</h3>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="exposure_time">Temps d'exposition</label>
                                <input type="text" id="exposure_time" name="exposure_time" placeholder="300s">
                            </div>
                            
                            <div class="form-field">
                                <label for="iso_gain">ISO/Gain</label>
                                <input type="text" id="iso_gain" name="iso_gain" placeholder="139">
                            </div>
                            
                            <div class="form-field">
                                <label for="number_of_frames">Nombre d'images</label>
                                <input type="text" id="number_of_frames" name="number_of_frames" placeholder="60">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field">
                                <label for="location">Lieu de prise de vue</label>
                                <input type="text" id="location" name="location" placeholder="Observatoire de...">
                            </div>
                            
                            <div class="form-field">
                                <label for="shooting_date">Date de prise de vue</label>
                                <input type="date" id="shooting_date" name="shooting_date">
                            </div>
                        </div>
                    </div>
                    
                    <p class="submit">
                        <input type="submit" name="upload_image" class="button button-primary button-large" value="📸 Publier l'Image">
                    </p>
                </form>
            </div>
        </div>
        
        <style>
        .astro-upload-container {
            max-width: 1000px;
            margin: 20px 0;
        }
        
        .upload-section {
            background: #f9f9f9;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border: 1px solid #ddd;
        }
        
        .upload-section h3 {
            margin-top: 0;
            color: #333;
            font-size: 18px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-field {
            margin-bottom: 15px;
        }
        
        .form-field label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        
        .form-field input,
        .form-field textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-field input:focus,
        .form-field textarea:focus {
            border-color: #0073aa;
            box-shadow: 0 0 5px rgba(0,115,170,0.3);
            outline: none;
        }
        
        .button-large {
            padding: 12px 24px !important;
            font-size: 16px !important;
            height: auto !important;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        </style>
        
        <?php
    }
    
    /**
     * Upload simple et fonctionnel
     */
    private function handle_simple_upload() {
        error_log('AstroFolio Debug: handle_simple_upload appelé');
        
        if (empty($_FILES['image_file']) || !isset($_FILES['image_file']['error']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
            return array('success' => false, 'message' => 'Aucun fichier sélectionné ou erreur d\'upload.');
        }
        
        $uploaded_file = $_FILES['image_file'];
        error_log('AstroFolio Debug: Fichier: ' . $uploaded_file['name']);
        
        // Utiliser la méthode manuelle qui fonctionne
        $upload_result = $this->manual_file_upload($uploaded_file);
        
        if (!$upload_result || isset($upload_result['error']) || empty($upload_result['file'])) {
            return array(
                'success' => false, 
                'message' => 'Erreur upload: ' . (isset($upload_result['error']) ? $upload_result['error'] : 'Fichier non créé')
            );
        }
        
        // Créer l'attachment WordPress
        $attachment = array(
            'guid'           => $upload_result['url'] ?? '',
            'post_mime_type' => $upload_result['type'] ?? 'image/jpeg',
            'post_title'     => sanitize_text_field($_POST['title'] ?? ''),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $upload_result['file'] ?? '');
        
        if (is_wp_error($attachment_id)) {
            return array(
                'success' => false,
                'message' => 'Erreur création attachment: ' . $attachment_id->get_error_message()
            );
        }
        
        // Générer les métadonnées
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once(ABSPATH . 'wp-admin/includes/image.php');
        }
        $file_path = $upload_result['file'] ?? '';
        if (!empty($file_path) && file_exists($file_path)) {
            $metadata = wp_generate_attachment_metadata($attachment_id, $file_path);
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
        
        // Sauvegarder toutes les métadonnées
        $astro_fields = array(
            'title', 'description', 'object_name', 'telescope', 'camera',
            'exposure_time', 'iso_gain', 'number_of_frames', 'location', 'shooting_date'
        );
        
        foreach ($astro_fields as $field) {
            if (isset($_POST[$field]) && !empty($_POST[$field])) {
                update_post_meta($attachment_id, 'astro_' . $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        return array(
            'success' => true,
            'message' => '🎉 Image "' . sanitize_text_field($_POST['title'] ?? 'Sans titre') . '" uploadée avec succès !',
            'attachment_id' => $attachment_id
        );
    }
    
    /**
     * Méthode d'upload manuelle en cas d'échec de wp_handle_upload
     */
    private function manual_file_upload($file) {
        error_log('AstroFolio Debug: Début manual_file_upload');
        
        try {
            if (!isset($file['tmp_name']) || empty($file['tmp_name']) || $file['tmp_name'] === null) {
                return array('error' => 'Fichier temporaire manquant');
            }
            
            if (!file_exists($file['tmp_name'])) {
                return array('error' => 'Fichier temporaire inexistant');
            }
            
            $upload_dir = wp_upload_dir();
            if ($upload_dir['error']) {
                return array('error' => $upload_dir['error']);
            }
            
            // Générer un nom unique
            $filename = sanitize_file_name($file['name'] ?? 'upload_' . time());
            $target_file = $upload_dir['path'] . '/' . $filename;
            
            // S'assurer que le fichier n'existe pas déjà
            $counter = 1;
            $base_filename = $filename;
            while (file_exists($target_file)) {
                $pathinfo = pathinfo($base_filename);
                $filename_part = $pathinfo['filename'] ?? 'upload';
                $extension = $pathinfo['extension'] ?? 'jpg';
                $filename = $filename_part . '-' . $counter . '.' . $extension;
                $target_file = $upload_dir['path'] . '/' . $filename;
                $counter++;
            }
            
            // Méthode 1: move_uploaded_file (standard)
            if (is_uploaded_file($file['tmp_name'])) {
                if (move_uploaded_file($file['tmp_name'], $target_file)) {
                    return array(
                        'file' => $target_file,
                        'url'  => $upload_dir['url'] . '/' . basename($target_file),
                        'type' => $file['type'] ?? 'image/jpeg',
                        'method' => 'manual_move_uploaded'
                    );
                }
            }
            
            // Méthode 2: copy (fallback)
            if (copy($file['tmp_name'], $target_file)) {
                return array(
                    'file' => $target_file,
                    'url'  => $upload_dir['url'] . '/' . basename($target_file),
                    'type' => $file['type'] ?? 'image/jpeg',
                    'method' => 'manual_copy'
                );
            }
            
            return array('error' => 'Toutes les méthodes d\'upload ont échoué');
            
        } catch (Exception $e) {
            error_log('AstroFolio Debug: Exception manual_file_upload: ' . $e->getMessage());
            return array('error' => $e->getMessage());
        }
    }

    public function gallery_page() {
        echo '<div class="wrap">';
        echo '<h1>🖼️ Galerie Astrophoto</h1>';
        echo '<p>Galerie simplifiée - fonctionnalité de base</p>';
        echo '</div>';
    }

    public function upload_enhanced_page() {
        include_once ANC_PLUGIN_DIR . 'test-upload-enhanced.php';
    }

    public function catalogs_page() {
        echo '<div class="wrap">';
        echo '<h1>📋 Catalogues</h1>';
        echo '<p>Gestion des catalogues - fonctionnalité de base</p>';
        echo '</div>';
    }

    private function count_astro_images() {
        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_query' => array(
                array(
                    'key' => 'astro_title',
                    'compare' => 'EXISTS'
                )
            ),
            'posts_per_page' => -1
        ));
        return $query->found_posts;
    }

    private function count_catalog_objects() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'astro_catalog_objects';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
            return $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        }
        return 0;
    }

    // Pages de diagnostic - méthodes simples
    public function test_upload_page() {
        include_once ANC_PLUGIN_DIR . 'test-upload-production.php';
    }

    public function debug_production_page() {
        include_once ANC_PLUGIN_DIR . 'diagnostic-production.php';
    }

    public function diagnostic_uploads_page() {
        include_once ANC_PLUGIN_DIR . 'diagnostic-uploads.php';
    }

    public function public_admin_page() {
        if (!class_exists('Astro_Admin_Public')) {
            require_once ANC_PLUGIN_DIR . 'admin/class-admin-public.php';
        }
        $public_admin = new Astro_Admin_Public();
        $public_admin->admin_page();
    }

    public function recovery_page() {
        ?>
        <div class="wrap">
            <h1>🔄 Récupération d'Images AstroFolio</h1>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <h2>🔍 Diagnostic du Système</h2>
                
                <?php
                global $astrofolio_plugin;
                
                // Test des comptages
                $total_images = wp_count_attachments('image');
                $total_count = 0;
                if (isset($total_images->{'image/jpeg'})) $total_count += $total_images->{'image/jpeg'};
                if (isset($total_images->{'image/png'})) $total_count += $total_images->{'image/png'};
                if (isset($total_images->{'image/gif'})) $total_count += $total_images->{'image/gif'};
                
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
                ?>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                    <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #007cba;">
                        <h3 style="margin-top: 0;">📊 Images Totales</h3>
                        <p style="font-size: 24px; margin: 10px 0; color: #007cba;"><strong><?php echo $total_count; ?></strong></p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #28a745;">
                        <h3 style="margin-top: 0;">✅ Déjà marquées AstroFolio</h3>
                        <p style="font-size: 24px; margin: 10px 0; color: #28a745;"><strong><?php echo count($marked_images); ?></strong></p>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 15px; border-left: 4px solid #ffc107;">
                        <h3 style="margin-top: 0;">⚠️ Images non marquées</h3>
                        <p style="font-size: 24px; margin: 10px 0; color: #ffc107;"><strong><?php echo count($unmarked_images); ?></strong></p>
                    </div>
                </div>
            </div>

            <?php if (!empty($unmarked_images)): ?>
            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <h2>🎯 Images Candidates à la Récupération</h2>
                
                <?php
                // Debug : vérifier l'accès à l'instance du plugin
                $debug_info = [];
                $debug_info[] = "Instance globale existe : " . (isset($astrofolio_plugin) ? "✅ OUI" : "❌ NON");
                
                if (!isset($astrofolio_plugin)) {
                    // Essayer de récupérer l'instance autrement
                    if (class_exists('AstroFolio_Safe')) {
                        $debug_info[] = "Classe AstroFolio_Safe existe : ✅ OUI";
                        // Créer une instance temporaire pour le test
                        $temp_plugin = new AstroFolio_Safe();
                        $astrofolio_plugin = $temp_plugin;
                    } else {
                        $debug_info[] = "Classe AstroFolio_Safe existe : ❌ NON";
                    }
                }
                
                // Tester quelques images pour voir si elles sont détectables comme astro
                $candidates = [];
                $test_details = []; // Debug info
                $test_count = min(10, count($unmarked_images));
                
                for ($i = 0; $i < $test_count; $i++) {
                    $image_id = $unmarked_images[$i];
                    $image_post = get_post($image_id);
                    
                    $debug_detail = [
                        'id' => $image_id,
                        'title' => $image_post ? $image_post->post_title : 'ERREUR: Post introuvable',
                        'filename' => '',
                        'detected' => false,
                        'error' => ''
                    ];
                    
                    if ($image_post) {
                        $filename = basename(get_attached_file($image_id));
                        $debug_detail['filename'] = $filename;
                        
                        // Vérifier si la méthode existe
                        if (isset($astrofolio_plugin) && method_exists($astrofolio_plugin, 'detect_astro_image')) {
                            try {
                                // Activer le debug pour voir exactement ce qui est testé
                                $is_astro = $astrofolio_plugin->detect_astro_image($image_id, true); // DEBUG activé
                                $debug_detail['detected'] = $is_astro;
                                
                                if ($is_astro) {
                                    $candidates[] = [
                                        'id' => $image_id,
                                        'title' => $image_post->post_title,
                                        'filename' => $filename
                                    ];
                                }
                            } catch (Exception $e) {
                                $debug_detail['error'] = 'Exception: ' . $e->getMessage();
                            }
                        } else {
                            $debug_detail['error'] = 'Méthode detect_astro_image non disponible';
                        }
                    }
                    
                    $test_details[] = $debug_detail;
                }
                ?>
                
                <p><strong>Images détectées comme astrophotographie :</strong> <?php echo count($candidates); ?> sur <?php echo $test_count; ?> testées</p>
                
                <!-- Section de debug détaillée -->
                <details style="margin: 20px 0; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6;">
                    <summary style="cursor: pointer; font-weight: bold;">🔍 Détails de la détection (Debug Complet)</summary>
                    <div style="margin-top: 10px;">
                        
                        <!-- Debug de l'instance -->
                        <div style="background: #e7f3ff; padding: 10px; margin: 10px 0; border-left: 4px solid #007cba;">
                            <strong>🔧 Debug du système :</strong><br>
                            <?php foreach ($debug_info as $info): ?>
                            <?php echo $info; ?><br>
                            <?php endforeach; ?>
                        </div>
                    <div style="margin-top: 10px;">
                        <?php if (!empty($test_details)): ?>
                        <table class="widefat" style="margin: 10px 0;">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Titre</th>
                                    <th>Nom de fichier</th>
                                    <th>Détectée ?</th>
                                    <th>Erreur</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($test_details as $detail): ?>
                                <tr style="<?php echo $detail['detected'] ? 'background: #d4edda;' : ($detail['error'] ? 'background: #f8d7da;' : 'background: #fff3cd;'); ?>">
                                    <td><?php echo esc_html($detail['id']); ?></td>
                                    <td><?php echo esc_html($detail['title']); ?></td>
                                    <td><?php echo esc_html($detail['filename']); ?></td>
                                    <td><?php echo $detail['detected'] ? '✅ OUI' : '❌ NON'; ?></td>
                                    <td><?php echo esc_html($detail['error']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;">
                            <strong>💡 Test manuel :</strong><br>
                            Si vous ne voyez aucune détection, essayez de renommer une de vos images avec un titre comme "M31" ou "Orion" pour tester.
                        </div>
                        <?php else: ?>
                        <p>Aucune image à analyser.</p>
                        <?php endif; ?>
                    </div>
                </details>
                
                <?php if (!empty($candidates)): ?>
                <table class="widefat fixed" style="margin: 20px 0;">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Nom de fichier</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($candidates, 0, 5) as $candidate): ?>
                        <tr>
                            <td><?php echo esc_html($candidate['title']); ?></td>
                            <td><?php echo esc_html($candidate['filename']); ?></td>
                            <td>
                                <button type="button" class="button button-small" 
                                        onclick="recoverSingleImage(<?php echo $candidate['id']; ?>)">
                                    Récupérer cette image
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
                
                <!-- Section pour récupération manuelle si aucune détection automatique -->
                <?php if (empty($candidates) && !empty($unmarked_images)): ?>
                <div style="background: #fff3cd; padding: 15px; margin: 20px 0; border: 1px solid #ffc107;">
                    <h3>🔧 Récupération Manuelle</h3>
                    <p>Aucune image automatiquement détectée, mais vous pouvez marquer manuellement vos images d'astrophotographie :</p>
                    
                    <table class="widefat fixed" style="margin: 10px 0;">
                        <thead>
                            <tr>
                                <th>Titre</th>
                                <th>Nom de fichier</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $manual_candidates = array_slice($unmarked_images, 0, 10);
                            foreach ($manual_candidates as $image_id): 
                                $image_post = get_post($image_id);
                                if ($image_post):
                            ?>
                            <tr>
                                <td><?php echo esc_html($image_post->post_title); ?></td>
                                <td><?php echo esc_html(basename(get_attached_file($image_id))); ?></td>
                                <td>
                                    <button type="button" class="button button-small button-secondary" 
                                            onclick="forceRecoverImage(<?php echo $image_id; ?>)">
                                        Marquer comme astro
                                    </button>
                                </td>
                            </tr>
                            <?php endif; endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="description">
                        <strong>Note :</strong> Ces images ne correspondent pas aux critères de détection automatique, 
                        mais vous pouvez les marquer manuellement si ce sont des photos d'astrophotographie.
                    </p>
                </div>
                <?php endif; ?>
            </div>

            <div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; box-shadow: 0 1px 1px rgba(0,0,0,.04); margin: 20px 0;">
                <h2>🚀 Actions de Récupération</h2>
                
                <div style="margin: 20px 0;">
                    <button type="button" class="button button-primary button-large" onclick="startBatchRecovery()" id="batch-recovery-btn">
                        🔄 Lancer la récupération automatique
                    </button>
                    <p class="description">Analysera toutes les images non marquées et récupérera celles détectées comme astrophotographie.</p>
                </div>
                
                <div style="margin: 20px 0; padding: 15px; background: #fff3cd; border: 1px solid #ffc107;">
                    <h3>🚨 Mode Récupération Forcée</h3>
                    <p>Si la détection automatique ne fonctionne pas pour vos images, vous pouvez forcer la récupération de TOUTES les images non marquées :</p>
                    <button type="button" class="button button-secondary button-large" onclick="forceRecoverAll()" id="force-recovery-btn">
                        ⚡ Récupérer TOUTES les images non marquées
                    </button>
                    <p class="description" style="color: #856404;"><strong>Attention :</strong> Cette option marquera toutes vos images non marquées comme des photos d'astrophotographie, sans vérification.</p>
                </div>
                
                <div id="recovery-progress" style="display: none; background: #f0f8ff; padding: 15px; border: 1px solid #007cba; margin: 20px 0;">
                    <h3>🔄 Récupération en cours...</h3>
                    <div id="progress-info"></div>
                </div>
                
                <div id="recovery-results" style="display: none; margin: 20px 0;"></div>
            </div>
            <?php else: ?>
            <div style="background: #d4edda; padding: 20px; border: 1px solid #c3e6cb; margin: 20px 0;">
                <h2>✅ Parfait !</h2>
                <p>Toutes vos images sont déjà correctement marquées dans AstroFolio. Aucune récupération nécessaire.</p>
            </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
        function recoverSingleImage(imageId) {
            if (!confirm('Récupérer cette image dans AstroFolio ?')) return;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'astro_recover_single_image',
                    image_id: imageId,
                    nonce: '<?php echo wp_create_nonce('astro_recovery'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? 'Image récupérée avec succès !' : 'Erreur : ' + data.data);
                location.reload();
            });
        }

        function forceRecoverImage(imageId) {
            if (!confirm('Marquer cette image comme photo d\'astrophotographie ?')) return;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'astro_force_recover_image',
                    image_id: imageId,
                    nonce: '<?php echo wp_create_nonce('astro_recovery'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                alert(data.success ? 'Image marquée avec succès !' : 'Erreur : ' + data.data);
                location.reload();
            });
        }

        function startBatchRecovery() {
            if (!confirm('Lancer la récupération automatique de toutes les images détectées ?')) return;
            
            const btn = document.getElementById('batch-recovery-btn');
            const progress = document.getElementById('recovery-progress');
            const results = document.getElementById('recovery-results');
            
            btn.disabled = true;
            btn.textContent = '🔄 Récupération en cours...';
            progress.style.display = 'block';
            results.style.display = 'none';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'astro_batch_recovery',
                    nonce: '<?php echo wp_create_nonce('astro_recovery'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                progress.style.display = 'none';
                results.style.display = 'block';
                
                if (data.success) {
                    results.innerHTML = `
                        <div style="background: #d4edda; padding: 15px; border: 1px solid #c3e6cb;">
                            <h3>✅ Récupération terminée !</h3>
                            <p><strong>Images récupérées :</strong> ${data.data.recovered || 0}</p>
                            <p><strong>Temps de traitement :</strong> ${data.data.time || 'N/A'}</p>
                            ${data.data.details ? '<p><strong>Détails :</strong> ' + data.data.details + '</p>' : ''}
                        </div>
                    `;
                } else {
                    results.innerHTML = `
                        <div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb;">
                            <h3>❌ Erreur lors de la récupération</h3>
                            <p>${data.data || 'Erreur inconnue'}</p>
                        </div>
                    `;
                }
                
                btn.disabled = false;
                btn.textContent = '🔄 Lancer la récupération automatique';
                
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                progress.style.display = 'none';
                results.innerHTML = `
                    <div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb;">
                        <h3>❌ Erreur de connexion</h3>
                        <p>${error.message}</p>
                    </div>
                `;
                btn.disabled = false;
                btn.textContent = '🔄 Lancer la récupération automatique';
            });
        }
        
        function forceRecoverAll() {
            if (!confirm('ATTENTION: Cette action marquera TOUTES les images non marquées comme photos d\'astrophotographie.\n\nÊtes-vous sûr de vouloir continuer ?')) return;
            
            const btn = document.getElementById('force-recovery-btn');
            const progress = document.getElementById('recovery-progress');
            const results = document.getElementById('recovery-results');
            
            btn.disabled = true;
            btn.textContent = '⚡ Récupération forcée en cours...';
            progress.style.display = 'block';
            results.style.display = 'none';
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'astro_force_recover_all',
                    nonce: '<?php echo wp_create_nonce('astro_recovery'); ?>'
                })
            })
            .then(response => response.json())
            .then(data => {
                progress.style.display = 'none';
                results.style.display = 'block';
                
                if (data.success) {
                    results.innerHTML = `
                        <div style="background: #d4edda; padding: 15px; border: 1px solid #c3e6cb;">
                            <h3>✅ Récupération forcée terminée !</h3>
                            <p><strong>Images récupérées :</strong> ${data.data.recovered || 0}</p>
                            <p><strong>Images traitées :</strong> ${data.data.processed || 0}</p>
                            <p><strong>Temps de traitement :</strong> ${data.data.time || 'N/A'}</p>
                        </div>
                    `;
                } else {
                    results.innerHTML = `
                        <div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb;">
                            <h3>❌ Erreur lors de la récupération</h3>
                            <p>${data.data || 'Erreur inconnue'}</p>
                        </div>
                    `;
                }
                
                btn.disabled = false;
                btn.textContent = '⚡ Récupérer TOUTES les images non marquées';
                
                setTimeout(() => location.reload(), 2000);
            })
            .catch(error => {
                progress.style.display = 'none';
                results.innerHTML = `
                    <div style="background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb;">
                        <h3>❌ Erreur de connexion</h3>
                        <p>${error.message}</p>
                    </div>
                `;
                btn.disabled = false;
                btn.textContent = '⚡ Récupérer TOUTES les images non marquées';
            });
        }
        </script>
        <?php
    }
}

?>