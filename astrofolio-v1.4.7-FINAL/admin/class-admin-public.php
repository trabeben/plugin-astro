<?php
/**
 * Page d'administration pour la gestion de l'affichage public
 * Gestion des shortcodes, pages publiques et paramètres d'affichage
 */

class Astro_Admin_Public {
    
    private $public_settings;
    
    public function __construct() {
        add_action('admin_init', array($this, 'init_settings'));
        $this->public_settings = get_option('astro_public_settings', $this->get_default_settings());
    }
    
    /**
     * Paramètres par défaut
     */
    private function get_default_settings() {
        return array(
            'gallery' => array(
                'default_columns' => 3,
                'default_limit' => 12,
                'default_size' => 'medium',
                'show_titles' => true,
                'show_metadata' => true,
                'show_pagination' => true,
                'enable_lightbox' => true,
                'enable_likes' => true,
                'enable_sharing' => true
            ),
            'pages' => array(
                'enable_public_gallery' => true,
                'gallery_page_title' => 'Galerie Astrophoto',
                'gallery_page_slug' => 'astrophoto',
                'images_per_page' => 24,
                'enable_filters' => true,
                'enable_search' => true,
                'show_object_info' => true,
                'gallery_page_id' => null,
                'detail_page_id' => null
            ),
            'seo' => array(
                'meta_description' => 'Galerie d\'astrophotographie - Images d\'objets célestes',
                'og_title' => 'Galerie Astrophoto',
                'og_description' => 'Découvrez ma collection d\'images d\'astrophotographie',
                'enable_schema' => true
            ),
            'style' => array(
                'primary_color' => '#667eea',
                'secondary_color' => '#764ba2',
                'accent_color' => '#28a745',
                'border_radius' => '8px',
                'enable_animations' => true,
                'dark_mode' => false
            ),
            'advanced' => array(
                'lazy_loading' => true,
                'image_optimization' => true,
                'cache_duration' => 3600,
                'enable_analytics' => false
            )
        );
    }
    
    /**
     * Initialisation des paramètres
     */
    public function init_settings() {
        register_setting('astro_public_settings', 'astro_public_settings', array($this, 'sanitize_settings'));
        
        // Section Galerie
        add_settings_section(
            'astro_gallery_section',
            '📸 Paramètres de la Galerie',
            array($this, 'gallery_section_callback'),
            'astro_public_settings'
        );
        
        // Section Pages
        add_settings_section(
            'astro_pages_section',
            '📄 Gestion des Pages',
            array($this, 'pages_section_callback'),
            'astro_public_settings'
        );
        
        // Section Style
        add_settings_section(
            'astro_style_section',
            '🎨 Personnalisation',
            array($this, 'style_section_callback'),
            'astro_public_settings'
        );
        
        $this->add_gallery_fields();
        $this->add_pages_fields();
        $this->add_style_fields();
    }
    
    private function add_gallery_fields() {
        add_settings_field(
            'gallery_display',
            'Affichage par défaut',
            array($this, 'gallery_display_callback'),
            'astro_public_settings',
            'astro_gallery_section'
        );
        
        add_settings_field(
            'gallery_features',
            'Fonctionnalités',
            array($this, 'checkbox_group_callback'),
            'astro_public_settings',
            'astro_gallery_section',
            array(
                'fields' => array(
                    'gallery.show_titles' => 'Afficher les titres',
                    'gallery.show_metadata' => 'Afficher les métadonnées',
                    'gallery.enable_lightbox' => 'Activer la lightbox',
                    'gallery.enable_likes' => 'Système de likes',
                    'gallery.enable_sharing' => 'Boutons de partage'
                )
            )
        );
    }
    
    private function add_pages_fields() {
        add_settings_field(
            'public_gallery_enable',
            'Page galerie publique',
            array($this, 'checkbox_field_callback'),
            'astro_public_settings',
            'astro_pages_section',
            array('field' => 'pages.enable_public_gallery')
        );
        
        add_settings_field(
            'gallery_page_config',
            'Configuration de la page galerie',
            array($this, 'page_config_callback'),
            'astro_public_settings',
            'astro_pages_section',
            array('type' => 'gallery')
        );
        
        add_settings_field(
            'detail_page_config',
            'Configuration de la page détail',
            array($this, 'page_config_callback'),
            'astro_public_settings',
            'astro_pages_section',
            array('type' => 'detail')
        );
        
        add_settings_field(
            'pages_management',
            'Gestion des pages',
            array($this, 'pages_management_callback'),
            'astro_public_settings',
            'astro_pages_section'
        );
    }
    
    private function add_style_fields() {
        add_settings_field(
            'color_scheme',
            'Palette de couleurs',
            array($this, 'color_scheme_callback'),
            'astro_public_settings',
            'astro_style_section'
        );
    }
    
    /**
     * Page d'administration principale
     */
    public function admin_page() {
        // Traitement des actions
        if (isset($_POST['action'])) {
            $this->handle_admin_actions();
        }
        
        // Statistiques rapides
        $stats = $this->get_public_stats();
        ?>
        <div class="wrap astro-public-admin">
            <div class="astro-admin-header">
                <h1>🌐 Gestion de l'Affichage Public</h1>
                <p class="description">Configurez l'apparence et les fonctionnalités de votre galerie publique</p>
            </div>
            
            <!-- Statistiques -->
            <div class="astro-stats-grid">
                <div class="astro-stat-card">
                    <div class="stat-icon">📸</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_images']; ?></div>
                        <div class="stat-label">Images publiques</div>
                    </div>
                </div>
                <div class="astro-stat-card">
                    <div class="stat-icon">👁️</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo number_format($stats['total_views']); ?></div>
                        <div class="stat-label">Vues totales</div>
                    </div>
                </div>
                <div class="astro-stat-card">
                    <div class="stat-icon">❤️</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['total_likes']; ?></div>
                        <div class="stat-label">Likes reçus</div>
                    </div>
                </div>
                <div class="astro-stat-card">
                    <div class="stat-icon">📄</div>
                    <div class="stat-content">
                        <div class="stat-number"><?php echo $stats['pages_created']; ?></div>
                        <div class="stat-label">Pages créées</div>
                    </div>
                </div>
            </div>
            
            <!-- Création de pages - Section prioritaire -->
            <div class="astro-page-creation" style="background: #fff; border: 1px solid #c3c4c7; padding: 20px; margin: 20px 0; border-radius: 8px;">
                <h2>🚀 Création Automatique des Pages</h2>
                <p>Créez automatiquement les pages WordPress nécessaires pour votre galerie publique.</p>
                
                <?php 
                $gallery_page_id = $this->public_settings['pages']['gallery_page_id'] ?? null;
                $detail_page_id = $this->public_settings['pages']['detail_page_id'] ?? null;
                ?>
                
                <!-- État actuel des pages -->
                <div style="background: #f0f0f1; padding: 15px; margin: 15px 0; border-radius: 6px;">
                    <h3>📋 État Actuel</h3>
                    <ul>
                        <li><strong>Page Galerie:</strong> 
                            <?php if ($gallery_page_id && get_post($gallery_page_id)) : ?>
                                ✅ Créée - <a href="<?php echo get_permalink($gallery_page_id); ?>" target="_blank">Voir</a>
                            <?php else : ?>
                                ❌ Non créée
                            <?php endif; ?>
                        </li>
                        <li><strong>Page Détail:</strong> 
                            <?php if ($detail_page_id && get_post($detail_page_id)) : ?>
                                ✅ Créée - <a href="<?php echo get_permalink($detail_page_id); ?>" target="_blank">Voir</a>
                            <?php else : ?>
                                ❌ Non créée
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
                
                <div style="display: flex; gap: 15px; margin: 15px 0; flex-wrap: wrap;">
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('create_pages', 'astro_nonce'); ?>
                        <input type="hidden" name="action" value="create_pages" />
                        <button type="submit" class="button button-primary button-large">
                            🚀 Créer les pages automatiquement
                        </button>
                        <p class="description">Crée les pages galerie et détail</p>
                    </form>
                    
                    <form method="post" style="display: inline-block;">
                        <?php wp_nonce_field('create_all_pages', 'astro_nonce'); ?>
                        <input type="hidden" name="action" value="create_all_pages" />
                        <button type="submit" class="button button-secondary">
                            🏗️ Créer toutes les pages
                        </button>
                        <p class="description">Force la création de toutes les pages</p>
                    </form>
                </div>
            </div>
            
            <!-- Onglets de navigation -->
            <div class="nav-tab-wrapper astro-nav-tabs">
                <a href="#settings" class="nav-tab nav-tab-active" data-tab="settings">⚙️ Paramètres</a>
                <a href="#pages" class="nav-tab" data-tab="pages">📄 Pages</a>
                <a href="#shortcodes" class="nav-tab" data-tab="shortcodes">📝 Shortcodes</a>
                <a href="#tools" class="nav-tab" data-tab="tools">🛠️ Outils</a>
            </div>
            
            <!-- Contenu des onglets -->
            <div class="tab-content" id="settings-tab">
                <form method="post" action="options.php" class="astro-settings-form">
                    <?php
                    settings_fields('astro_public_settings');
                    do_settings_sections('astro_public_settings');
                    ?>
                    <div class="astro-form-actions">
                        <?php submit_button('💾 Enregistrer les paramètres', 'primary', 'submit', false); ?>
                    </div>
                </form>
            </div>
            
            <div class="tab-content" id="pages-tab" style="display: none;">
                <?php $this->render_pages_tab(); ?>
            </div>
            
            <div class="tab-content" id="shortcodes-tab" style="display: none;">
                <?php $this->render_shortcodes_tab(); ?>
            </div>
            
            <div class="tab-content" id="tools-tab" style="display: none;">
                <?php $this->render_tools_tab(); ?>
            </div>
        </div>
        
        <?php $this->render_admin_styles(); ?>
        <?php $this->render_admin_scripts(); ?>
        <?php
    }
    
    /**
     * Nouveaux callbacks pour la gestion des pages
     */
    public function page_config_callback($args) {
        $type = $args['type'];
        
        if ($type === 'gallery') {
            $page_id = $this->public_settings['pages']['gallery_page_id'];
            $title = $this->public_settings['pages']['gallery_page_title'];
            $slug = $this->public_settings['pages']['gallery_page_slug'];
            
            echo "<div class='page-config-group'>";
            echo "<label>Titre de la page:<br/>";
            echo "<input type='text' name='astro_public_settings[pages.gallery_page_title]' value='{$title}' class='regular-text' /></label><br/><br/>";
            echo "<label>Slug de la page:<br/>";
            echo "<input type='text' name='astro_public_settings[pages.gallery_page_slug]' value='{$slug}' class='regular-text' placeholder='astrophoto' /></label><br/><br/>";
            
            if ($page_id) {
                $page = get_post($page_id);
                if ($page) {
                    echo "<p class='description'>✅ Page galerie créée: <a href='" . get_permalink($page_id) . "' target='_blank'>" . $page->post_title . "</a> (<a href='" . admin_url('post.php?post=' . $page_id . '&action=edit') . "'>Éditer</a>)</p>";
                } else {
                    echo "<p class='description'>⚠️ Page galerie introuvable (ID: {$page_id}). Elle a peut-être été supprimée.</p>";
                }
            } else {
                echo "<p class='description'>❌ Aucune page galerie créée</p>";
            }
            echo "</div>";
            
        } else if ($type === 'detail') {
            $page_id = $this->public_settings['pages']['detail_page_id'];
            
            echo "<div class='page-config-group'>";
            echo "<p class='description'>Page pour afficher le détail de chaque image d'astrophoto</p>";
            
            if ($page_id) {
                $page = get_post($page_id);
                if ($page) {
                    echo "<p class='description'>✅ Page détail créée: <a href='" . get_permalink($page_id) . "' target='_blank'>" . $page->post_title . "</a> (<a href='" . admin_url('post.php?post=' . $page_id . '&action=edit') . "'>Éditer</a>)</p>";
                } else {
                    echo "<p class='description'>⚠️ Page détail introuvable (ID: {$page_id}). Elle a peut-être été supprimée.</p>";
                }
            } else {
                echo "<p class='description'>❌ Aucune page détail créée</p>";
            }
            echo "</div>";
        }
    }
    
    public function pages_management_callback() {
        ?>
        <div class="pages-management">
            <h4>🔧 Actions sur les pages</h4>
            <form method="post" style="display: inline-block; margin-right: 20px;">
                <?php wp_nonce_field('astro_create_pages', 'astro_nonce'); ?>
                <input type="hidden" name="action" value="create_pages" />
                <button type="submit" class="button button-primary">
                    🚀 Créer les pages automatiquement
                </button>
                <p class="description">Crée automatiquement les pages galerie et détail avec le contenu approprié</p>
            </form>
            
            <form method="post" style="display: inline-block;">
                <?php wp_nonce_field('astro_update_pages', 'astro_nonce'); ?>
                <input type="hidden" name="action" value="update_pages" />
                <button type="submit" class="button">
                    🔄 Mettre à jour les pages existantes
                </button>
                <p class="description">Met à jour le contenu des pages avec les derniers shortcodes</p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Onglet spécialisé pour la gestion des pages
     */
    private function render_pages_tab() {
        $gallery_page_id = $this->public_settings['pages']['gallery_page_id'];
        $detail_page_id = $this->public_settings['pages']['detail_page_id'];
        ?>
        <div class="pages-management-section">
            <h2>📄 Gestion des Pages Publiques</h2>
            
            <div class="pages-grid">
                <!-- Page Galerie -->
                <div class="page-card">
                    <div class="page-header">
                        <h3>📸 Page Galerie</h3>
                        <div class="page-status <?php echo $gallery_page_id ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $gallery_page_id ? '✅ Créée' : '❌ Non créée'; ?>
                        </div>
                    </div>
                    
                    <?php if ($gallery_page_id) : 
                        $gallery_page = get_post($gallery_page_id);
                        if ($gallery_page) : ?>
                            <div class="page-details">
                                <p><strong>Titre:</strong> <?php echo $gallery_page->post_title; ?></p>
                                <p><strong>URL:</strong> <a href="<?php echo get_permalink($gallery_page_id); ?>" target="_blank"><?php echo get_permalink($gallery_page_id); ?></a></p>
                                <p><strong>Statut:</strong> <?php echo ucfirst($gallery_page->post_status); ?></p>
                                <p><strong>Dernière modification:</strong> <?php echo get_the_modified_date('d/m/Y H:i', $gallery_page_id); ?></p>
                            </div>
                            <div class="page-actions">
                                <a href="<?php echo admin_url('post.php?post=' . $gallery_page_id . '&action=edit'); ?>" class="button">✏️ Éditer</a>
                                <a href="<?php echo get_permalink($gallery_page_id); ?>" target="_blank" class="button">👁️ Voir</a>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_update_page_content', 'astro_nonce'); ?>
                                    <input type="hidden" name="action" value="update_page_content" />
                                    <input type="hidden" name="page_id" value="<?php echo $gallery_page_id; ?>" />
                                    <input type="hidden" name="page_type" value="gallery" />
                                    <button type="submit" class="button">🔄 Régénérer le contenu</button>
                                </form>
                            </div>
                        <?php else : ?>
                            <p class="page-error">⚠️ Page introuvable (ID: <?php echo $gallery_page_id; ?>)</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="page-preview">
                            <h4>🔮 Aperçu du contenu qui sera créé:</h4>
                            <div class="content-preview">
                                <p><strong>Titre:</strong> <?php echo $this->public_settings['pages']['gallery_page_title']; ?></p>
                                <p><strong>Slug:</strong> <?php echo $this->public_settings['pages']['gallery_page_slug']; ?></p>
                                <p><strong>Shortcode:</strong> [astrofolio_gallery columns="<?php echo $this->public_settings['gallery']['default_columns']; ?>"]</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Page Détail -->
                <div class="page-card">
                    <div class="page-header">
                        <h3>🔍 Page Détail</h3>
                        <div class="page-status <?php echo $detail_page_id ? 'status-active' : 'status-inactive'; ?>">
                            <?php echo $detail_page_id ? '✅ Créée' : '❌ Non créée'; ?>
                        </div>
                    </div>
                    
                    <?php if ($detail_page_id) : 
                        $detail_page = get_post($detail_page_id);
                        if ($detail_page) : ?>
                            <div class="page-details">
                                <p><strong>Titre:</strong> <?php echo $detail_page->post_title; ?></p>
                                <p><strong>URL:</strong> <a href="<?php echo get_permalink($detail_page_id); ?>" target="_blank"><?php echo get_permalink($detail_page_id); ?></a></p>
                                <p><strong>Statut:</strong> <?php echo ucfirst($detail_page->post_status); ?></p>
                                <p><strong>Dernière modification:</strong> <?php echo get_the_modified_date('d/m/Y H:i', $detail_page_id); ?></p>
                            </div>
                            <div class="page-actions">
                                <a href="<?php echo admin_url('post.php?post=' . $detail_page_id . '&action=edit'); ?>" class="button">✏️ Éditer</a>
                                <a href="<?php echo get_permalink($detail_page_id); ?>" target="_blank" class="button">👁️ Voir</a>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('astro_update_page_content', 'astro_nonce'); ?>
                                    <input type="hidden" name="action" value="update_page_content" />
                                    <input type="hidden" name="page_id" value="<?php echo $detail_page_id; ?>" />
                                    <input type="hidden" name="page_type" value="detail" />
                                    <button type="submit" class="button">🔄 Régénérer le contenu</button>
                                </form>
                            </div>
                        <?php else : ?>
                            <p class="page-error">⚠️ Page introuvable (ID: <?php echo $detail_page_id; ?>)</p>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="page-preview">
                            <h4>🔮 Aperçu du contenu qui sera créé:</h4>
                            <div class="content-preview">
                                <p><strong>Titre:</strong> Détail Astrophoto</p>
                                <p><strong>Slug:</strong> detail-astrophoto</p>
                                <p><strong>Fonctionnalité:</strong> Affichage détaillé d'une image avec métadonnées</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Actions globales -->
            <div class="global-actions">
                <h3>🎬 Actions Globales</h3>
                <div class="actions-row">
                    <form method="post" class="action-form">
                        <?php wp_nonce_field('astro_create_all_pages', 'astro_nonce'); ?>
                        <input type="hidden" name="action" value="create_all_pages" />
                        <button type="submit" class="button button-primary button-large">
                            🚀 Créer toutes les pages manquantes
                        </button>
                        <p class="description">Crée automatiquement toutes les pages nécessaires à l'affichage public</p>
                    </form>
                    
                    <form method="post" class="action-form">
                        <?php wp_nonce_field('astro_regenerate_all_pages', 'astro_nonce'); ?>
                        <input type="hidden" name="action" value="regenerate_all_pages" />
                        <button type="submit" class="button button-secondary">
                            🔄 Régénérer toutes les pages
                        </button>
                        <p class="description">Met à jour le contenu de toutes les pages existantes</p>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Traitement des actions d'administration
     */
    private function handle_admin_actions() {
        $action = $_POST['action'] ?? '';
        $nonce_name = '';
        
        // Déterminer le nom du nonce en fonction de l'action
        switch ($action) {
            case 'create_pages':
                $nonce_name = 'create_pages';
                break;
            case 'create_all_pages':
                $nonce_name = 'create_all_pages';
                break;
            case 'update_pages':
                $nonce_name = 'update_pages';
                break;
            case 'update_page_content':
                $nonce_name = 'update_page_content';
                break;
            case 'regenerate_all_pages':
                $nonce_name = 'regenerate_all_pages';
                break;
            default:
                wp_die('Action non autorisée');
        }
        
        if (!isset($_POST['astro_nonce']) || !wp_verify_nonce($_POST['astro_nonce'], $nonce_name)) {
            wp_die('Sécurité: Nonce invalide');
        }
        
        switch ($_POST['action']) {
            case 'create_all_pages':
                $this->create_all_pages();
                break;
                
            case 'create_pages':
                $gallery_result = $this->create_gallery_page();
                $detail_result = $this->create_detail_page();
                if ($gallery_result && $detail_result) {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-success"><p>✅ Les pages galerie et détail ont été créées avec succès!</p></div>';
                    });
                } else {
                    add_action('admin_notices', function() {
                        echo '<div class="notice notice-error"><p>❌ Erreur lors de la création d\'une ou plusieurs pages.</p></div>';
                    });
                }
                break;
                
            case 'update_pages':
                $this->update_existing_pages();
                break;
                
            case 'update_page_content':
                $this->update_page_content($_POST['page_id'], $_POST['page_type']);
                break;
                
            case 'regenerate_all_pages':
                $this->regenerate_all_pages();
                break;
        }
    }
    
    /**
     * Création de toutes les pages manquantes
     */
    private function create_all_pages() {
        $results = array();
        
        // Page galerie
        if (!$this->public_settings['pages']['gallery_page_id']) {
            $results['gallery'] = $this->create_gallery_page();
        }
        
        // Page détail
        if (!$this->public_settings['pages']['detail_page_id']) {
            $results['detail'] = $this->create_detail_page();
        }
        
        $this->show_creation_results($results);
    }
    
    /**
     * Création de la page galerie
     */
    private function create_gallery_page() {
        $settings = $this->public_settings;
        
        $page_content = $this->generate_gallery_page_content();
        
        $page_data = array(
            'post_title' => $settings['pages']['gallery_page_title'],
            'post_content' => $page_content,
            'post_name' => $settings['pages']['gallery_page_slug'],
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => array(
                '_astro_generated_page' => true,
                '_astro_page_type' => 'gallery'
            )
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Sauvegarder l'ID de la page
            $this->public_settings['pages']['gallery_page_id'] = $page_id;
            update_option('astro_public_settings', $this->public_settings);
            
            add_action('admin_notices', function() use ($page_id) {
                echo '<div class="notice notice-success"><p>✅ Page galerie créée avec succès! <a href="' . get_permalink($page_id) . '" target="_blank">Voir la page</a></p></div>';
            });
            
            return true;
        } else {
            add_action('admin_notices', function() use ($page_id) {
                $error = is_wp_error($page_id) ? $page_id->get_error_message() : 'Erreur inconnue';
                echo '<div class="notice notice-error"><p>❌ Erreur lors de la création de la page galerie: ' . $error . '</p></div>';
            });
            return false;
        }
    }
    
    /**
     * Création de la page détail
     */
    private function create_detail_page() {
        $page_content = $this->generate_detail_page_content();
        
        $page_data = array(
            'post_title' => 'Détail Astrophoto',
            'post_content' => $page_content,
            'post_name' => 'detail-astrophoto',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_author' => get_current_user_id(),
            'meta_input' => array(
                '_astro_generated_page' => true,
                '_astro_page_type' => 'detail'
            )
        );
        
        $page_id = wp_insert_post($page_data);
        
        if ($page_id && !is_wp_error($page_id)) {
            // Sauvegarder l'ID de la page
            $this->public_settings['pages']['detail_page_id'] = $page_id;
            update_option('astro_public_settings', $this->public_settings);
            
            add_action('admin_notices', function() use ($page_id) {
                echo '<div class="notice notice-success"><p>✅ Page détail créée avec succès! <a href="' . get_permalink($page_id) . '" target="_blank">Voir la page</a></p></div>';
            });
            
            return true;
        } else {
            add_action('admin_notices', function() use ($page_id) {
                $error = is_wp_error($page_id) ? $page_id->get_error_message() : 'Erreur inconnue';
                echo '<div class="notice notice-error"><p>❌ Erreur lors de la création de la page détail: ' . $error . '</p></div>';
            });
            return false;
        }
    }
    
    /**
     * Génération du contenu de la page galerie
     */
    private function generate_gallery_page_content() {
        $settings = $this->public_settings;
        
        $content = '<div class="astro-gallery-page">' . "\n\n";
        
        // Introduction
        $content .= '<div class="gallery-intro">' . "\n";
        $content .= '<h2>🌌 Ma Collection d\'Astrophotographie</h2>' . "\n";
        $content .= '<p>Découvrez ma passion pour l\'astrophotographie à travers cette galerie d\'images d\'objets célestes. Chaque image raconte l\'histoire d\'une nuit d\'observation sous les étoiles.</p>' . "\n";
        $content .= '</div>' . "\n\n";
        
        // Shortcode principal de la galerie
        $shortcode = '[astrofolio_gallery';
        $shortcode .= ' columns="' . $settings['gallery']['default_columns'] . '"';
        $shortcode .= ' limit="' . $settings['gallery']['default_limit'] . '"';
        $shortcode .= ' size="' . $settings['gallery']['default_size'] . '"';
        if ($settings['gallery']['show_titles']) $shortcode .= ' show_titles="true"';
        if ($settings['gallery']['show_metadata']) $shortcode .= ' show_metadata="true"';
        $shortcode .= ']';
        
        $content .= $shortcode . "\n\n";
        
        // Section informative
        $content .= '<div class="gallery-info">' . "\n";
        $content .= '<h3>📡 À propos de mes équipements</h3>' . "\n";
        $content .= '<p>Ces images ont été capturées avec différents télescopes et caméras astronomiques. Les détails techniques de chaque prise de vue sont disponibles en cliquant sur les images.</p>' . "\n";
        $content .= '</div>' . "\n";
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Génération du contenu de la page détail
     */
    private function generate_detail_page_content() {
        $content = '<div class="astro-detail-page">' . "\n\n";
        
        // Message d'information
        $content .= '<div class="detail-info">' . "\n";
        $content .= '<p><em>Cette page affiche automatiquement le détail de l\'image sélectionnée dans la galerie.</em></p>' . "\n";
        $content .= '</div>' . "\n\n";
        
        // Template pour affichage dynamique
        $content .= '<div id="astro-image-detail">' . "\n";
        $content .= '<div class="detail-loading">' . "\n";
        $content .= '<p>🔄 Chargement des détails de l\'image...</p>' . "\n";
        $content .= '</div>' . "\n";
        $content .= '</div>' . "\n\n";
        
        // Script pour gestion dynamique
        $content .= '<script>' . "\n";
        $content .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
        $content .= '    // Récupération de l\'ID de l\'image depuis l\'URL' . "\n";
        $content .= '    const urlParams = new URLSearchParams(window.location.search);' . "\n";
        $content .= '    const imageId = urlParams.get("image_id");' . "\n";
        $content .= '    ' . "\n";
        $content .= '    if (imageId) {' . "\n";
        $content .= '        // Chargement AJAX du détail de l\'image' . "\n";
        $content .= '        loadImageDetail(imageId);' . "\n";
        $content .= '    } else {' . "\n";
        $content .= '        document.getElementById("astro-image-detail").innerHTML = "<p>❌ Aucune image spécifiée.</p>";' . "\n";
        $content .= '    }' . "\n";
        $content .= '});' . "\n";
        $content .= '</script>' . "\n";
        
        $content .= '</div>';
        
        return $content;
    }
    
    /**
     * Mise à jour du contenu d'une page existante
     */
    private function update_page_content($page_id, $page_type) {
        if ($page_type === 'gallery') {
            $new_content = $this->generate_gallery_page_content();
        } else if ($page_type === 'detail') {
            $new_content = $this->generate_detail_page_content();
        } else {
            return false;
        }
        
        $result = wp_update_post(array(
            'ID' => $page_id,
            'post_content' => $new_content
        ));
        
        if ($result && !is_wp_error($result)) {
            add_action('admin_notices', function() use ($page_type) {
                echo '<div class="notice notice-success"><p>✅ Contenu de la page ' . $page_type . ' mis à jour avec succès!</p></div>';
            });
            return true;
        } else {
            add_action('admin_notices', function() use ($page_type, $result) {
                $error = is_wp_error($result) ? $result->get_error_message() : 'Erreur inconnue';
                echo '<div class="notice notice-error"><p>❌ Erreur lors de la mise à jour de la page ' . $page_type . ': ' . $error . '</p></div>';
            });
            return false;
        }
    }
    
    /**
     * Callbacks d'interface
     */
    public function gallery_section_callback() {
        echo '<p>Configurez l\'apparence et le comportement par défaut de vos galeries.</p>';
    }
    
    public function pages_section_callback() {
        echo '<p>Gérez la création et la configuration des pages publiques de votre site.</p>';
    }
    
    public function style_section_callback() {
        echo '<p>Personnalisez l\'apparence visuelle de votre galerie.</p>';
    }
    
    public function gallery_display_callback() {
        $columns = $this->public_settings['gallery']['default_columns'];
        $limit = $this->public_settings['gallery']['default_limit'];
        $size = $this->public_settings['gallery']['default_size'];
        
        echo "<label>Nombre de colonnes: ";
        echo "<select name='astro_public_settings[gallery.default_columns]'>";
        for ($i = 1; $i <= 6; $i++) {
            echo "<option value='{$i}'" . selected($columns, $i, false) . ">{$i}</option>";
        }
        echo "</select></label><br/><br/>";
        
        echo "<label>Limite d'images: <input type='number' name='astro_public_settings[gallery.default_limit]' value='{$limit}' min='1' max='100' /></label><br/><br/>";
        
        echo "<label>Taille des images: ";
        echo "<select name='astro_public_settings[gallery.default_size]'>";
        $sizes = array('thumbnail' => 'Miniature', 'medium' => 'Moyenne', 'large' => 'Grande', 'full' => 'Originale');
        foreach ($sizes as $value => $label) {
            echo "<option value='{$value}'" . selected($size, $value, false) . ">{$label}</option>";
        }
        echo "</select></label>";
    }
    
    public function checkbox_field_callback($args) {
        $field = $args['field'];
        $value = $this->get_nested_value($this->public_settings, $field);
        echo "<input type='checkbox' name='astro_public_settings[{$field}]' value='1' " . checked($value, true, false) . " />";
    }
    
    public function checkbox_group_callback($args) {
        foreach ($args['fields'] as $field => $label) {
            $value = $this->get_nested_value($this->public_settings, $field);
            echo "<label><input type='checkbox' name='astro_public_settings[{$field}]' value='1' " . checked($value, true, false) . " /> {$label}</label><br/>";
        }
    }
    
    public function color_scheme_callback() {
        $primary = $this->public_settings['style']['primary_color'];
        $secondary = $this->public_settings['style']['secondary_color'];
        $accent = $this->public_settings['style']['accent_color'];
        
        echo "<div class='color-picker-group'>";
        echo "<label>Couleur primaire: <input type='color' name='astro_public_settings[style.primary_color]' value='{$primary}' /></label><br/>";
        echo "<label>Couleur secondaire: <input type='color' name='astro_public_settings[style.secondary_color]' value='{$secondary}' /></label><br/>";
        echo "<label>Couleur accent: <input type='color' name='astro_public_settings[style.accent_color]' value='{$accent}' /></label>";
        echo "</div>";
    }
    
    /**
     * Onglets additionnels
     */
    private function render_shortcodes_tab() {
        ?>
        <div class="shortcodes-reference">
            <h2>📝 Guide des Shortcodes</h2>
            <div class="shortcode-examples">
                <div class="shortcode-example">
                    <h3>[astrofolio_gallery]</h3>
                    <p>Affiche la galerie d'images d'astrophotographie avec pagination</p>
                    <code>[astrofolio_gallery columns="3" limit="12" show_titles="true" show_pagination="true"]</code>
                    <p><strong>Paramètres disponibles :</strong></p>
                    <ul>
                        <li><strong>limit</strong> : nombre d'images par page (défaut: 12)</li>
                        <li><strong>columns</strong> : nombre de colonnes (défaut: 3)</li>
                        <li><strong>show_titles</strong> : afficher les titres (true/false)</li>
                        <li><strong>show_pagination</strong> : afficher le bouton "Charger plus" (true/false)</li>
                        <li><strong>size</strong> : taille des images (thumbnail/medium/large)</li>
                    </ul>
                </div>
                
                <div class="shortcode-example">
                    <h3>[astro_debug_images]</h3>
                    <p>Diagnostic des images pour le dépannage</p>
                    <code>[astro_debug_images]</code>
                </div>
                
                <div class="shortcode-example">
                    <h3>[astro_recover_images]</h3>
                    <p>Récupération des images "perdues"</p>
                    <code>[astro_recover_images mode="preview"]</code>
                    <code>[astro_recover_images mode="execute"]</code>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function render_tools_tab() {
        ?>
        <div class="tools-section">
            <h2>🛠️ Outils de Maintenance</h2>
            
            <div class="tools-grid">
                <div class="tool-card">
                    <h3>🔄 Régénération des miniatures</h3>
                    <p>Régénère toutes les miniatures des images.</p>
                    <button class="button button-primary" id="regenerate-thumbnails">Régénérer</button>
                </div>
                
                <div class="tool-card">
                    <h3>🧹 Nettoyage du cache</h3>
                    <p>Vide le cache des pages publiques.</p>
                    <button class="button" id="clear-cache">Vider le cache</button>
                </div>
                
                <div class="tool-card">
                    <h3>📊 Recalcul des statistiques</h3>
                    <p>Recalcule les vues et statistiques.</p>
                    <button class="button" id="recalc-stats">Recalculer</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Fonctions utilitaires
     */
    private function get_nested_value($array, $path) {
        $path = explode('.', $path);
        $current = $array;
        foreach ($path as $key) {
            if (isset($current[$key])) {
                $current = $current[$key];
            } else {
                return '';
            }
        }
        return $current;
    }
    
    private function get_public_stats() {
        // Calcul des statistiques simples
        $total_images = wp_count_posts('attachment')->inherit ?? 0;
        
        return array(
            'total_images' => $total_images,
            'total_views' => get_option('astro_total_views', 0),
            'total_likes' => get_option('astro_total_likes', 0),
            'pages_created' => ($this->public_settings['pages']['gallery_page_id'] ? 1 : 0) + ($this->public_settings['pages']['detail_page_id'] ? 1 : 0)
        );
    }
    
    public function sanitize_settings($input) {
        // Validation et nettoyage des données
        return $input; // Simplified for now
    }
    
    /**
     * Styles CSS pour l'admin
     */
    private function render_admin_styles() {
        ?>
        <style>
        .astro-public-admin {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 12px;
            margin: 20px 20px 20px 0;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
        }
        
        .astro-admin-header h1 {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .astro-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .astro-stat-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .astro-stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 2em;
            margin-bottom: 10px;
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #667eea;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .astro-nav-tabs .nav-tab {
            font-size: 1.1em;
            padding: 12px 20px;
        }
        
        .pages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
            margin: 30px 0;
        }
        
        .page-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 25px;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .page-header h3 {
            margin: 0;
            color: #495057;
        }
        
        .page-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-active {
            background: #d4edda;
            color: #155724;
        }
        
        .status-inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .page-details p {
            margin: 8px 0;
            font-size: 0.9em;
        }
        
        .page-actions {
            margin-top: 15px;
        }
        
        .page-actions .button {
            margin-right: 10px;
            margin-bottom: 5px;
        }
        
        .content-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            font-size: 0.9em;
        }
        
        .global-actions {
            margin-top: 40px;
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        
        .actions-row {
            display: flex;
            gap: 30px;
            flex-wrap: wrap;
        }
        
        .action-form {
            flex: 1;
            min-width: 300px;
        }
        
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        
        .tool-card {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .tool-card h3 {
            color: #667eea;
            margin-top: 0;
        }
        
        .shortcode-examples {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .shortcode-example {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
        }
        
        .shortcode-example h3 {
            color: #495057;
            margin-top: 0;
        }
        
        .shortcode-example code {
            display: block;
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 10px;
            font-family: Monaco, 'Courier New', monospace;
        }
        </style>
        <?php
    }
    
    /**
     * Scripts JavaScript pour l'admin
     */
    private function render_admin_scripts() {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gestion des onglets
            const tabs = document.querySelectorAll('.astro-nav-tabs .nav-tab');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => {
                tab.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Retirer la classe active de tous les onglets
                    tabs.forEach(t => t.classList.remove('nav-tab-active'));
                    contents.forEach(c => c.style.display = 'none');
                    
                    // Activer l'onglet cliqué
                    this.classList.add('nav-tab-active');
                    const targetId = this.getAttribute('data-tab') + '-tab';
                    document.getElementById(targetId).style.display = 'block';
                });
            });
            
            // Confirmation pour les actions importantes
            const dangerousButtons = document.querySelectorAll('[data-confirm]');
            dangerousButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const message = this.getAttribute('data-confirm');
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });
        });
        </script>
        <?php
    }
}
?>