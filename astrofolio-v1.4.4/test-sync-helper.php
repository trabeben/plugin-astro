<?php
/**
 * Script de test simple pour vérifier la synchronisation Admin/Frontend
 * À exécuter via l'admin WordPress ou en ajoutant ?test_sync=1 à une page
 */

// Protection WordPress
if (!defined('ABSPATH')) {
    exit;
}

if (isset($_GET['test_sync']) && current_user_can('manage_options')) {
    add_action('wp_footer', 'astrofolio_test_sync_display');
    add_action('admin_footer', 'astrofolio_test_sync_display');
}

function astrofolio_test_sync_display() {
    global $wpdb;
    
    echo '<div style="position: fixed; top: 50px; right: 50px; background: white; border: 3px solid #0073aa; padding: 20px; max-width: 500px; z-index: 9999; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">';
    echo '<h3 style="margin-top: 0;">🔍 TEST SYNC ASTROFOLIO</h3>';
    
    // Test 1: Images AstroFolio
    $args = array(
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'post_status' => 'inherit',
        'posts_per_page' => 5,
        'meta_query' => array(
            'relation' => 'OR',
            array('key' => '_astrofolio_image', 'compare' => 'EXISTS'),
            array('key' => 'astro_object_name', 'compare' => 'EXISTS')
        )
    );
    
    $images = get_posts($args);
    echo '<p><strong>Images trouvées:</strong> ' . count($images) . '</p>';
    
    if (!empty($images)) {
        $test_image = $images[0];
        echo '<h4>Test Image ID: ' . $test_image->ID . '</h4>';
        
        // Métadonnées post_meta
        $post_meta = array(
            'astro_object_name' => get_post_meta($test_image->ID, 'astro_object_name', true),
            'astro_shooting_date' => get_post_meta($test_image->ID, 'astro_shooting_date', true),
            '_astrofolio_image' => get_post_meta($test_image->ID, '_astrofolio_image', true)
        );
        
        echo '<p><strong>Post Meta:</strong></p>';
        echo '<ul>';
        foreach ($post_meta as $key => $value) {
            echo '<li>' . $key . ': ' . ($value ?: '<em>vide</em>') . '</li>';
        }
        echo '</ul>';
        
        // Table métadonnées
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        $metadata_row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE image_id = %d",
            $test_image->ID
        ));
        
        echo '<p><strong>Table métadonnées:</strong> ';
        if ($metadata_row) {
            echo 'Trouvée ✅';
            echo '<br>Télescope: ' . ($metadata_row->telescope_model ?: '<em>vide</em>');
            echo '<br>Caméra: ' . ($metadata_row->camera_model ?: '<em>vide</em>');
        } else {
            echo 'Non trouvée ❌';
        }
        echo '</p>';
        
        // Test de la fonction get_image_metadata du plugin
        if (class_exists('AstroFolio_Safe')) {
            $plugin = new AstroFolio_Safe();
            $reflection = new ReflectionClass($plugin);
            
            if ($reflection->hasMethod('get_image_metadata')) {
                $method = $reflection->getMethod('get_image_metadata');
                $method->setAccessible(true);
                $result = $method->invoke($plugin, $test_image->ID);
                
                echo '<p><strong>Plugin get_image_metadata:</strong></p>';
                echo '<ul>';
                echo '<li>Objet: ' . ($result['object_name'] ?? '<em>vide</em>') . '</li>';
                echo '<li>Date: ' . ($result['acquisition_dates'] ?? '<em>vide</em>') . '</li>';
                echo '<li>Télescope: ' . ($result['telescope_model'] ?? '<em>vide</em>') . '</li>';
                echo '</ul>';
            }
        }
    } else {
        echo '<p style="color: orange;">⚠️ Aucune image AstroFolio trouvée</p>';
        echo '<p>Uploadez une image via AstroFolio > Upload Image</p>';
    }
    
    echo '<p><em>Ajoutez &test_sync=1 à l\'URL pour voir ce test</em></p>';
    echo '<button onclick="this.parentNode.style.display=\'none\'" style="float: right; padding: 5px 10px;">Fermer</button>';
    echo '</div>';
}

// Version shortcode pour test public
function astrofolio_test_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '<p><em>Test disponible pour les administrateurs uniquement</em></p>';
    }
    
    ob_start();
    astrofolio_test_sync_display();
    return ob_get_clean();
}
add_shortcode('astrofolio_test', 'astrofolio_test_shortcode');
?>