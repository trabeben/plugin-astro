<?php
/**
 * =============================================================================
 * CLASSE DE GESTION DES IMAGES ASTROFOLIO
 * =============================================================================
 * 
 * Cette classe gère toutes les opérations liées aux images d'astrophotographie
 * 
 * 🖼️ FONCTIONNALITÉS PRINCIPALES :
 * - Upload et traitement d'images (JPG, PNG, TIFF, WEBP)
 * - Extraction automatique des métadonnées EXIF
 * - Génération de miniatures optimisées
 * - Système de récupération d'images en 6 niveaux
 * - Gestion des métadonnées astronomiques complètes
 * 
 * 📸 TYPES D'IMAGES SUPPORTÉS :
 * - JPEG : Format principal pour l'affichage web
 * - PNG : Transparence et qualité maximale
 * - TIFF : Format de travail haute qualité
 * - WebP : Format moderne optimisé
 * 
 * 🔍 SYSTÈME DE RÉCUPÉRATION EN 6 NIVEAUX :
 * 1. Images dans wp-content/uploads/astrofolio/
 * 2. Images dans wp-content/uploads/ (toutes)
 * 3. Attachments WordPress avec métadonnées
 * 4. Scan récursif des dossiers uploads
 * 5. Images référencées mais manquantes
 * 6. Récupération forcée depuis sauvegardes
 * 
 * 📊 MÉTADONNÉES GÉRÉES :
 * - Techniques : ISO, temps d'exposition, focale, capteur
 * - Astronomiques : objet, coordonnées, catalogues
 * - Équipement : télescope, monture, caméra, filtres
 * - Traitement : logiciels, nombre d'images empilées
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Includes
 */
class Astro_Images {
    
    public static function create_image($data) {
        global $wpdb;
        
        $defaults = array(
            'user_id' => get_current_user_id(),
            'title' => '',
            'description' => '',
            'object_id' => null,
            'image_url' => '',
            'thumbnail_url' => '',
            'acquisition_date' => null,
            'location' => '',
            'telescope' => '',
            'camera_name' => '',
            'total_exposure_time' => 0,
            'status' => 'draft'
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'astro_images',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    public static function get_image($id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names,
                    u.display_name as author_name
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.id = %d",
            $id
        ));
    }
    
    public static function get_recent_images($limit = 12, $status = 'published') {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names,
                    u.display_name as author_name, u.user_nicename as author_slug
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.status = %s
             ORDER BY i.created_at DESC
             LIMIT %d",
            $status,
            $limit
        ));
    }
    
    public static function get_featured_images($limit = 6) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names,
                    u.display_name as author_name, u.user_nicename as author_slug
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.status = 'published' AND i.featured = 1
             ORDER BY i.created_at DESC
             LIMIT %d",
            $limit
        ));
    }
    
    public static function get_user_images($user_id, $limit = 20, $status = null) {
        global $wpdb;
        
        $where = '';
        $params = array($user_id);
        
        if ($status) {
            $where = ' AND i.status = %s';
            $params[] = $status;
        }
        
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             WHERE i.user_id = %d $where
             ORDER BY i.created_at DESC
             LIMIT %d",
            ...$params
        ));
    }
    
    public static function get_object_images($object_id, $limit = 20) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, u.display_name as author_name, u.user_nicename as author_slug
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE i.object_id = %d AND i.status = 'published'
             ORDER BY i.likes_count DESC, i.created_at DESC
             LIMIT %d",
            $object_id,
            $limit
        ));
    }
    
    public static function search_images($filters = array()) {
        global $wpdb;
        
        $where_clauses = array("i.status = 'published'");
        $params = array();
        $joins = array();
        
        // Vérifier si les tables et colonnes existent
        $images_table = $wpdb->prefix . 'astro_images';
        $objects_table = $wpdb->prefix . 'astro_objects';
        
        // Jointure avec la table des objets si nécessaire
        $objects_join_added = false;
        if (!empty($filters['object_type']) || !empty($filters['constellation'])) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$objects_table'") == $objects_table) {
                $joins[] = "LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id";
                $objects_join_added = true;
            }
        }
        
        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            if ($objects_join_added) {
                $where_clauses[] = "(i.title LIKE %s OR i.description LIKE %s OR o.designation LIKE %s OR o.common_names LIKE %s)";
                $params = array_merge($params, array($search, $search, $search, $search));
            } else {
                $where_clauses[] = "(i.title LIKE %s OR i.description LIKE %s)";
                $params = array_merge($params, array($search, $search));
            }
        }
        
        // Filtre par type d'objet (dans la table objects)
        if (!empty($filters['object_type']) && $objects_join_added) {
            $where_clauses[] = "o.object_type = %s";
            $params[] = $filters['object_type'];
        }
        
        // Filtre par constellation (dans la table objects)
        if (!empty($filters['constellation']) && $objects_join_added) {
            $where_clauses[] = "o.constellation = %s";
            $params[] = $filters['constellation'];
        }
        
        // Filtre par télescope
        if (!empty($filters['telescope'])) {
            $where_clauses[] = "i.telescope = %s";
            $params[] = $filters['telescope'];
        }
        
        // Filtre par type de télescope
        if (!empty($filters['telescope_type'])) {
            $where_clauses[] = "i.telescope_type = %s";
            $params[] = $filters['telescope_type'];
        }
        
        // Filtre par caméra (utiliser camera_name)
        if (!empty($filters['camera'])) {
            $where_clauses[] = "i.camera_name = %s";
            $params[] = $filters['camera'];
        }
        
        // Filtre par type de caméra
        if (!empty($filters['camera_type'])) {
            $where_clauses[] = "i.camera_type = %s";
            $params[] = $filters['camera_type'];
        }
        
        // Filtre par année d'acquisition
        if (!empty($filters['year'])) {
            $where_clauses[] = "YEAR(i.acquisition_date) = %d";
            $params[] = intval($filters['year']);
        }
        
        // Filtre par images en vedette
        if ($filters['featured'] === '1') {
            $where_clauses[] = "i.featured = 1";
        } elseif ($filters['featured'] === '0') {
            $where_clauses[] = "i.featured = 0";
        }
        
        // Filtres avancés - exposition
        if (!empty($filters['min_exposure']) && $filters['min_exposure'] > 0) {
            $where_clauses[] = "i.total_exposure_time >= %d";
            $params[] = intval($filters['min_exposure']);
        }
        
        if (!empty($filters['max_exposure']) && $filters['max_exposure'] > 0) {
            $where_clauses[] = "i.total_exposure_time <= %d";
            $params[] = intval($filters['max_exposure']);
        }
        
        // Filtre par ouverture minimale
        if (!empty($filters['min_aperture']) && $filters['min_aperture'] > 0) {
            $where_clauses[] = "i.telescope_aperture >= %d";
            $params[] = intval($filters['min_aperture']);
        }
        
        // Filtre par dates
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "i.acquisition_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "i.acquisition_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Filtre par objet spécifique (pour compatibilité)
        if (!empty($filters['object']) && $objects_join_added) {
            $where_clauses[] = "(o.designation = %s OR o.common_names LIKE %s)";
            $params[] = $filters['object'];
            $params[] = '%' . $wpdb->esc_like($filters['object']) . '%';
        }
        
        // Filtre par catalogue (pour compatibilité)
        if (!empty($filters['catalog']) && $objects_join_added) {
            $where_clauses[] = "o.designation LIKE %s";
            $params[] = $filters['catalog'] . '%';
        }
        
        // Construction de la requête
        $base_select = "SELECT i.*, u.display_name as author_name, u.user_nicename as author_slug";
        if ($objects_join_added) {
            $base_select .= ", o.designation as object_designation, o.common_names as object_names, o.object_type, o.constellation";
        }
        
        $joins_sql = implode(' ', $joins);
        $joins_sql .= " LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID";
        
        $where_sql = implode(' AND ', $where_clauses);
        
        $sql = "$base_select FROM {$wpdb->prefix}astro_images i $joins_sql WHERE $where_sql ORDER BY i.created_at DESC";
        
        // Gestion de la pagination
        if (isset($filters['limit']) && $filters['limit'] > 0) {
            $sql .= $wpdb->prepare(" LIMIT %d", $filters['limit']);
            if (isset($filters['offset']) && $filters['offset'] > 0) {
                $sql .= $wpdb->prepare(" OFFSET %d", $filters['offset']);
            }
        }
        
        // Exécution de la requête
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($sql, ...$params));
        } else {
            return $wpdb->get_results($sql);
        }
    }
            $params[] = $filters['catalog'] . '%';
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        
        // Gestion de la pagination
        $limit_sql = '';
        if (!empty($filters['limit'])) {
            $limit_sql = ' LIMIT ' . intval($filters['limit']);
            if (!empty($filters['offset'])) {
                $limit_sql .= ' OFFSET ' . intval($filters['offset']);
            }
        }
        
        $query = "SELECT i.*, u.display_name as author_name
                  FROM $images_table i
                  LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
                  WHERE $where_sql
                  ORDER BY i.created_at DESC" . $limit_sql;
        
        if (!empty($params)) {
            return $wpdb->get_results($wpdb->prepare($query, ...$params));
        } else {
            return $wpdb->get_results($query);
        }
    }
    
    public static function count_images($filters = array()) {
        global $wpdb;
        
        $where_clauses = array("i.status = 'published'");
        $params = array();
        $joins = array();
        
        // Vérifier si les tables existent
        $objects_table = $wpdb->prefix . 'astro_objects';
        
        // Jointure avec la table des objets si nécessaire
        $objects_join_added = false;
        if (!empty($filters['object_type']) || !empty($filters['constellation'])) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$objects_table'") == $objects_table) {
                $joins[] = "LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id";
                $objects_join_added = true;
            }
        }
        
        // Recherche textuelle
        if (!empty($filters['search'])) {
            $search = '%' . $wpdb->esc_like($filters['search']) . '%';
            if ($objects_join_added) {
                $where_clauses[] = "(i.title LIKE %s OR i.description LIKE %s OR o.designation LIKE %s OR o.common_names LIKE %s)";
                $params = array_merge($params, array($search, $search, $search, $search));
            } else {
                $where_clauses[] = "(i.title LIKE %s OR i.description LIKE %s)";
                $params = array_merge($params, array($search, $search));
            }
        }
        
        // Appliquer tous les mêmes filtres que search_images
        if (!empty($filters['object_type']) && $objects_join_added) {
            $where_clauses[] = "o.object_type = %s";
            $params[] = $filters['object_type'];
        }
        
        if (!empty($filters['constellation']) && $objects_join_added) {
            $where_clauses[] = "o.constellation = %s";
            $params[] = $filters['constellation'];
        }
        
        if (!empty($filters['telescope'])) {
            $where_clauses[] = "i.telescope = %s";
            $params[] = $filters['telescope'];
        }
        
        if (!empty($filters['telescope_type'])) {
            $where_clauses[] = "i.telescope_type = %s";
            $params[] = $filters['telescope_type'];
        }
        
        if (!empty($filters['camera'])) {
            $where_clauses[] = "i.camera_name = %s";
            $params[] = $filters['camera'];
        }
        
        if (!empty($filters['camera_type'])) {
            $where_clauses[] = "i.camera_type = %s";
            $params[] = $filters['camera_type'];
        }
        
        if (!empty($filters['year'])) {
            $where_clauses[] = "YEAR(i.acquisition_date) = %d";
            $params[] = intval($filters['year']);
        }
        
        if ($filters['featured'] === '1') {
            $where_clauses[] = "i.featured = 1";
        } elseif ($filters['featured'] === '0') {
            $where_clauses[] = "i.featured = 0";
        }
        
        if (!empty($filters['min_exposure']) && $filters['min_exposure'] > 0) {
            $where_clauses[] = "i.total_exposure_time >= %d";
            $params[] = intval($filters['min_exposure']);
        }
        
        if (!empty($filters['max_exposure']) && $filters['max_exposure'] > 0) {
            $where_clauses[] = "i.total_exposure_time <= %d";
            $params[] = intval($filters['max_exposure']);
        }
        
        if (!empty($filters['min_aperture']) && $filters['min_aperture'] > 0) {
            $where_clauses[] = "i.telescope_aperture >= %d";
            $params[] = intval($filters['min_aperture']);
        }
        
        if (!empty($filters['date_from'])) {
            $where_clauses[] = "i.acquisition_date >= %s";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where_clauses[] = "i.acquisition_date <= %s";
            $params[] = $filters['date_to'];
        }
        
        // Construction de la requête de comptage
        $joins_sql = implode(' ', $joins);
        $where_sql = implode(' AND ', $where_clauses);
        
        $query = "SELECT COUNT(*) FROM {$wpdb->prefix}astro_images i $joins_sql WHERE $where_sql";
        
        // Exécution de la requête
        if (!empty($params)) {
            return intval($wpdb->get_var($wpdb->prepare($query, ...$params)));
        } else {
            return intval($wpdb->get_var($query));
        }
    }
    
    public static function update_image($id, $data) {
        global $wpdb;
        
        return $wpdb->update(
            $wpdb->prefix . 'astro_images',
            $data,
            array('id' => $id)
        );
    }
    
    public static function delete_image($id) {
        global $wpdb;
        
        // Supprimer les likes associés
        $wpdb->delete(
            $wpdb->prefix . 'astro_likes',
            array('image_id' => $id)
        );
        
        // Supprimer les commentaires associés
        $wpdb->delete(
            $wpdb->prefix . 'astro_comments',
            array('image_id' => $id)
        );
        
        // Supprimer l'image
        return $wpdb->delete(
            $wpdb->prefix . 'astro_images',
            array('id' => $id)
        );
    }
    
    public static function toggle_like($image_id, $user_id) {
        global $wpdb;
        
        $like_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}astro_likes 
             WHERE image_id = %d AND user_id = %d",
            $image_id, $user_id
        ));
        
        if ($like_exists) {
            // Supprimer le like
            $wpdb->delete(
                $wpdb->prefix . 'astro_likes',
                array('image_id' => $image_id, 'user_id' => $user_id)
            );
            
            // Décrémenter le compteur
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}astro_images 
                 SET likes_count = likes_count - 1 
                 WHERE id = %d",
                $image_id
            ));
            
            return false; // Like supprimé
        } else {
            // Ajouter le like
            $wpdb->insert(
                $wpdb->prefix . 'astro_likes',
                array('image_id' => $image_id, 'user_id' => $user_id)
            );
            
            // Incrémenter le compteur
            $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}astro_images 
                 SET likes_count = likes_count + 1 
                 WHERE id = %d",
                $image_id
            ));
            
            return true; // Like ajouté
        }
    }
    
    public static function increment_views($image_id) {
        global $wpdb;
        
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->prefix}astro_images 
             SET views_count = views_count + 1 
             WHERE id = %d",
            $image_id
        ));
    }
    
    public static function get_all_images($limit = 50, $offset = 0) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names,
                    u.display_name as author_name, u.user_nicename as author_slug
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             ORDER BY i.created_at DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ));
    }
    
    public static function get_popular_objects($limit = 10) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.designation as object_name, o.common_names, o.object_type, o.constellation,
                    COUNT(i.id) as image_count
             FROM {$wpdb->prefix}astro_objects o
             LEFT JOIN {$wpdb->prefix}astro_images i ON o.id = i.object_id AND i.status = 'published'
             WHERE o.designation IS NOT NULL
             GROUP BY o.id
             ORDER BY image_count DESC, o.designation ASC
             LIMIT %d",
            $limit
        ));
    }
    
    public static function get_stats() {
        global $wpdb;
        
        return array(
            'total_images' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_images WHERE status = 'published'"),
            'total_users' => $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}astro_images WHERE status = 'published'"),
            'total_exposure' => $wpdb->get_var("SELECT SUM(total_exposure_time) FROM {$wpdb->prefix}astro_images WHERE status = 'published'"),
            'total_likes' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}astro_likes"),
            'popular_objects' => $wpdb->get_results("
                SELECT o.designation as object_name, o.common_names, COUNT(i.id) as image_count
                FROM {$wpdb->prefix}astro_objects o
                JOIN {$wpdb->prefix}astro_images i ON o.id = i.object_id
                WHERE i.status = 'published'
                GROUP BY o.id
                ORDER BY image_count DESC
                LIMIT 10
            "),
            'popular_telescopes' => $wpdb->get_results("
                SELECT telescope, COUNT(*) as count
                FROM {$wpdb->prefix}astro_images
                WHERE status = 'published' AND telescope != ''
                GROUP BY telescope
                ORDER BY count DESC
                LIMIT 10
            ")
        );
    }
}