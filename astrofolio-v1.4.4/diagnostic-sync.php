<?php
/**
 * Script de diagnostic pour vérifier la synchronisation Admin/Frontend
 */

// Sécurité WordPress
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/../../../');
    require_once(ABSPATH . 'wp-config.php');
}

echo "<h1>🔍 DIAGNOSTIC ADMIN/FRONTEND ASTROFOLIO</h1>\n";

// 1. Vérifier les images uploadées
echo "<h2>1. 📸 IMAGES UPLOADÉES</h2>\n";

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
            'key' => '_astrofolio_image',
            'compare' => 'EXISTS'
        )
    )
);

$images = get_posts($args);
echo "<p>🖼️ <strong>Images trouvées :</strong> " . count($images) . "</p>\n";

if (!empty($images)) {
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>\n";
    echo "<tr><th>ID</th><th>Titre</th><th>Objet</th><th>Date</th><th>Meta AstroFolio</th></tr>\n";
    
    foreach (array_slice($images, 0, 5) as $image) {
        $object_name = get_post_meta($image->ID, 'astro_object_name', true);
        $shooting_date = get_post_meta($image->ID, 'astro_shooting_date', true);
        $is_astro = get_post_meta($image->ID, '_astrofolio_image', true);
        
        echo "<tr>\n";
        echo "<td>" . $image->ID . "</td>\n";
        echo "<td>" . ($image->post_title ?: 'Sans titre') . "</td>\n";
        echo "<td>" . ($object_name ?: 'Non défini') . "</td>\n";
        echo "<td>" . ($shooting_date ?: 'Non défini') . "</td>\n";
        echo "<td>" . ($is_astro ? '✅ OUI' : '❌ NON') . "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";
}

// 2. Vérifier la table des métadonnées
echo "<h2>2. 🗄️ TABLE MÉTADONNÉES</h2>\n";

global $wpdb;
$table_name = $wpdb->prefix . 'astro_image_metadata';

// Vérifier si la table existe
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
echo "<p>🗂️ <strong>Table '$table_name' :</strong> " . ($table_exists ? '✅ EXISTE' : '❌ N\'EXISTE PAS') . "</p>\n";

if ($table_exists) {
    $metadata_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    echo "<p>📊 <strong>Enregistrements :</strong> $metadata_count</p>\n";
    
    if ($metadata_count > 0) {
        $sample_metadata = $wpdb->get_results("SELECT * FROM $table_name LIMIT 3");
        echo "<table border='1' style='width:100%; border-collapse: collapse;'>\n";
        echo "<tr><th>Image ID</th><th>Télescope</th><th>Caméra</th><th>Monture</th><th>Notes</th></tr>\n";
        
        foreach ($sample_metadata as $meta) {
            echo "<tr>\n";
            echo "<td>" . $meta->image_id . "</td>\n";
            echo "<td>" . ($meta->telescope_model ?: 'Non défini') . "</td>\n";
            echo "<td>" . ($meta->camera_model ?: 'Non défini') . "</td>\n";
            echo "<td>" . ($meta->mount_model ?: 'Non défini') . "</td>\n";
            echo "<td>" . substr($meta->processing_notes ?: 'Aucune', 0, 50) . "</td>\n";
            echo "</tr>\n";
        }
        echo "</table>\n";
    }
}

// 3. Test du shortcode
echo "<h2>3. 🎨 TEST SHORTCODE</h2>\n";

// Créer une instance temporaire de la classe
$plugin_file = dirname(__FILE__) . '/astrofolio.php';
if (file_exists($plugin_file)) {
    require_once($plugin_file);
    
    if (class_exists('AstroFolio_Safe')) {
        $astrofolio = new AstroFolio_Safe();
        
        // Tester le shortcode
        $shortcode_output = $astrofolio->gallery_shortcode(array('limit' => 3));
        
        echo "<p>🎯 <strong>Output du shortcode :</strong></p>\n";
        echo "<div style='border: 1px solid #ccc; padding: 10px; background: #f9f9f9;'>\n";
        echo $shortcode_output;
        echo "</div>\n";
        
        // Analyser le contenu
        $has_images = strpos($shortcode_output, 'astro-image-item') !== false;
        $has_metadata = strpos($shortcode_output, 'astro-image-meta') !== false;
        
        echo "<p>📋 <strong>Analyse :</strong></p>\n";
        echo "<ul>\n";
        echo "<li>Images présentes : " . ($has_images ? '✅ OUI' : '❌ NON') . "</li>\n";
        echo "<li>Métadonnées affichées : " . ($has_metadata ? '✅ OUI' : '❌ NON') . "</li>\n";
        echo "</ul>\n";
        
    } else {
        echo "<p>❌ <strong>Erreur :</strong> Classe AstroFolio_Safe non trouvée</p>\n";
    }
} else {
    echo "<p>❌ <strong>Erreur :</strong> Fichier astrofolio.php non trouvé</p>\n";
}

// 4. Vérifier les options WordPress
echo "<h2>4. ⚙️ OPTIONS WORDPRESS</h2>\n";

$options_to_check = array(
    'astro_images_per_page',
    'astro_default_columns', 
    'astro_show_metadata',
    'astro_image_quality',
    'astrofolio_detail_page'
);

echo "<table border='1' style='width:100%; border-collapse: collapse;'>\n";
echo "<tr><th>Option</th><th>Valeur</th></tr>\n";

foreach ($options_to_check as $option) {
    $value = get_option($option, 'NON DÉFINIE');
    echo "<tr><td>$option</td><td>$value</td></tr>\n";
}
echo "</table>\n";

// 5. Test de récupération des métadonnées pour une image spécifique
if (!empty($images)) {
    $test_image = $images[0];
    echo "<h2>5. 🔬 TEST MÉTADONNÉES IMAGE #{$test_image->ID}</h2>\n";
    
    // Métadonnées de base
    $basic_meta = array(
        'astro_object_name' => get_post_meta($test_image->ID, 'astro_object_name', true),
        'astro_shooting_date' => get_post_meta($test_image->ID, 'astro_shooting_date', true),
        '_astrofolio_image' => get_post_meta($test_image->ID, '_astrofolio_image', true)
    );
    
    echo "<h3>Métadonnées post_meta :</h3>\n";
    echo "<ul>\n";
    foreach ($basic_meta as $key => $value) {
        echo "<li><strong>$key:</strong> " . ($value ?: 'Vide') . "</li>\n";
    }
    echo "</ul>\n";
    
    // Métadonnées avancées
    if ($table_exists) {
        $advanced_meta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE image_id = %d",
            $test_image->ID
        ));
        
        echo "<h3>Métadonnées table personnalisée :</h3>\n";
        if ($advanced_meta) {
            echo "<ul>\n";
            echo "<li><strong>Télescope:</strong> " . ($advanced_meta->telescope_model ?: 'Vide') . "</li>\n";
            echo "<li><strong>Caméra:</strong> " . ($advanced_meta->camera_model ?: 'Vide') . "</li>\n";
            echo "<li><strong>Monture:</strong> " . ($advanced_meta->mount_model ?: 'Vide') . "</li>\n";
            echo "<li><strong>Lieu:</strong> " . ($advanced_meta->location_name ?: 'Vide') . "</li>\n";
            echo "</ul>\n";
        } else {
            echo "<p>❌ Aucune métadonnée avancée trouvée pour cette image</p>\n";
        }
    }
}

echo "<h2>✅ DIAGNOSTIC TERMINÉ</h2>\n";
echo "<p><strong>Date :</strong> " . date('d/m/Y H:i:s') . "</p>\n";
?>