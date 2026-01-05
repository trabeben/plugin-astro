<?php
/**
 * Gestion des images d'astrophotographie
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
    
    public static function search_images($search_term, $filters = array(), $limit = 20) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        $where_clauses = array("i.status = 'published'");
        $params = array();
        
        // Recherche textuelle
        if (!empty($search_term)) {
            $where_clauses[] = "(i.title LIKE %s OR i.description LIKE %s OR o.designation LIKE %s OR o.common_names LIKE %s)";
            $params = array_merge($params, array($search, $search, $search, $search));
        }
        
        // Filtres additionnels
        if (!empty($filters['object_type'])) {
            $where_clauses[] = "o.object_type = %s";
            $params[] = $filters['object_type'];
        }
        
        if (!empty($filters['constellation'])) {
            $where_clauses[] = "o.constellation = %s";
            $params[] = $filters['constellation'];
        }
        
        if (!empty($filters['telescope_type'])) {
            $where_clauses[] = "i.telescope_type = %s";
            $params[] = $filters['telescope_type'];
        }
        
        if (!empty($filters['camera_type'])) {
            $where_clauses[] = "i.camera_type = %s";
            $params[] = $filters['camera_type'];
        }
        
        if (!empty($filters['min_exposure'])) {
            $where_clauses[] = "i.total_exposure_time >= %d";
            $params[] = intval($filters['min_exposure']);
        }
        
        $where_sql = implode(' AND ', $where_clauses);
        $params[] = $limit;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT i.*, o.designation as object_designation, o.common_names as object_names,
                    o.object_type, o.constellation,
                    u.display_name as author_name, u.user_nicename as author_slug
             FROM {$wpdb->prefix}astro_images i
             LEFT JOIN {$wpdb->prefix}astro_objects o ON i.object_id = o.id
             LEFT JOIN {$wpdb->users} u ON i.user_id = u.ID
             WHERE $where_sql
             ORDER BY i.likes_count DESC, i.created_at DESC
             LIMIT %d",
            ...$params
        ));
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