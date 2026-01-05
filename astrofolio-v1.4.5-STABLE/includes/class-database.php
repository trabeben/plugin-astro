<?php
/**
 * Gestion de la base de données
 */
class Astro_Database {
    
    const DB_VERSION = '1.0.0';
    
    public static function create_tables() {
        // Créer d'abord les tables normales
        self::update_database_schema();
        
        // Corriger la table des métadonnées qui pose problème
        self::fix_metadata_table();
    }
    
    public static function update_database_schema() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Vérifier si la table astro_objects existe
        $table_name = $wpdb->prefix . 'astro_objects';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($table_exists) {
            // Vérifier la structure actuelle et migrer si nécessaire
            $columns = $wpdb->get_results("SHOW COLUMNS FROM `$table_name`");
            $column_names = array_column($columns, 'Field');
            
            $has_designation = in_array('designation', $column_names);
            $has_object_name = in_array('object_name', $column_names);
            
            // Si on a object_name mais pas designation, renommer
            if ($has_object_name && !$has_designation) {
                $wpdb->query("ALTER TABLE `$table_name` CHANGE `object_name` `designation` varchar(50) NOT NULL");
                error_log('AstroPortfolio: Migration de object_name vers designation effectuée');
            }
            
            // S'assurer que catalog_id existe (nouvelle structure avec FK)
            $has_catalog_id = in_array('catalog_id', $column_names);
            $has_catalog = in_array('catalog', $column_names);
            
            if ($has_catalog && !$has_catalog_id) {
                // Ajouter catalog_id et migrer les données
                $wpdb->query("ALTER TABLE `$table_name` ADD COLUMN `catalog_id` mediumint(9) AFTER `designation`");
                
                // Mettre à jour catalog_id basé sur catalog
                $wpdb->query("
                    UPDATE `$table_name` o 
                    SET o.catalog_id = (
                        SELECT c.id 
                        FROM {$wpdb->prefix}astro_catalogs c 
                        WHERE c.code = o.catalog 
                        OR c.name = o.catalog
                        LIMIT 1
                    )
                    WHERE o.catalog_id IS NULL
                ");
                
                error_log('AstroPortfolio: Migration vers catalog_id effectuée');
            }
        }
        
        // Créer/recréer toutes les tables avec le schéma à jour
        self::create_tables_sql();
        
        // Mettre à jour la version de la base de données
        update_option('astro_db_version', self::DB_VERSION);
    }
    
    private static function create_tables_sql() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Table des catalogues
        $sql_catalogs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_catalogs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(100) NOT NULL,
            code varchar(20) NOT NULL,
            description text,
            total_objects int(11) DEFAULT 0,
            source_url varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY code (code)
        ) $charset_collate;";
        
        // Table des objets astronomiques  
        $sql_objects = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_objects (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            designation varchar(50) NOT NULL,
            catalog_id mediumint(9),
            object_type varchar(50),
            constellation varchar(50),
            ra_hours decimal(8,6),
            dec_degrees decimal(8,6),
            magnitude decimal(5,2),
            size varchar(50),
            distance varchar(50),
            common_names text,
            
            -- Désignations alternatives pour références croisées
            messier_number varchar(20),
            ngc_number varchar(20),
            ic_number varchar(20),
            caldwell_number varchar(20),
            sharpless_number varchar(20),
            other_designations text, -- JSON pour autres catalogues
            
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY catalog_id (catalog_id),
            KEY designation (designation),
            KEY messier_number (messier_number),
            KEY ngc_number (ngc_number),
            KEY ic_number (ic_number),
            KEY constellation (constellation),
            KEY object_type (object_type),
            FOREIGN KEY (catalog_id) REFERENCES {$wpdb->prefix}astro_catalogs(id)
        ) $charset_collate;";
        
        // Table des références croisées entre objets
        $sql_cross_refs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_object_cross_refs (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            primary_object_id mediumint(9) NOT NULL,
            related_object_id mediumint(9) NOT NULL,
            relationship_type enum('same_object','related','part_of') DEFAULT 'same_object',
            notes varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_relationship (primary_object_id, related_object_id),
            KEY primary_object_id (primary_object_id),
            KEY related_object_id (related_object_id),
            FOREIGN KEY (primary_object_id) REFERENCES {$wpdb->prefix}astro_objects(id) ON DELETE CASCADE,
            FOREIGN KEY (related_object_id) REFERENCES {$wpdb->prefix}astro_objects(id) ON DELETE CASCADE
        ) $charset_collate;";
        
        // Table des images d'astrophotographie
        $sql_images = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_images (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            title varchar(255) NOT NULL,
            description text,
            object_id mediumint(9),
            
            -- Fichiers
            image_url varchar(500) NOT NULL,
            thumbnail_url varchar(500),
            full_resolution_url varchar(500),
            
            -- Localisation et dates
            acquisition_date date,
            location varchar(255),
            latitude decimal(10,8),
            longitude decimal(11,8),
            bortle_scale tinyint(1),
            
            -- Télescope/Monture
            telescope varchar(255),
            telescope_aperture int(11), -- mm
            telescope_focal_length int(11), -- mm
            telescope_type enum('refractor','reflector','cassegrain','schmidt-cassegrain','maksutov','other'),
            mount_type enum('alt-az','equatorial','dobsonian','other'),
            mount_name varchar(255),
            
            -- Caméra
            camera_name varchar(255),
            camera_type enum('ccd','cmos','dslr','mirrorless','other'),
            camera_cooling varchar(50),
            pixel_size decimal(4,2), -- microns
            binning varchar(10),
            
            -- Optiques
            reducer_flattener varchar(255),
            filters varchar(500), -- JSON array
            filter_wheel varchar(255),
            
            -- Guidage
            guide_scope varchar(255),
            guide_camera varchar(255),
            guiding_software varchar(100),
            
            -- Acquisition
            total_exposure_time int(11), -- minutes
            sub_exposure_time int(11), -- seconds
            number_of_subs int(11),
            iso_gain varchar(20),
            offset varchar(20),
            temperature decimal(4,1),
            
            -- Données par filtre (JSON)
            filter_data text, -- {\"Ha\": {\"subs\": 20, \"exposure\": 600}, \"OIII\": {...}}
            
            -- Traitement
            acquisition_software varchar(100),
            processing_software varchar(500),
            darks_count int(11),
            flats_count int(11),
            bias_count int(11),
            
            -- Météo
            seeing varchar(50),
            transparency varchar(50),
            moon_phase decimal(3,1),
            
            -- Métadonnées
            fov_width decimal(8,4), -- degrés
            fov_height decimal(8,4), -- degrés
            pixel_scale decimal(6,3), -- arcsec/pixel
            orientation decimal(6,2), -- degrés
            
            -- Statut et visibilité
            status enum('draft','published','private','processing') DEFAULT 'draft',
            featured boolean DEFAULT 0,
            likes_count int(11) DEFAULT 0,
            views_count int(11) DEFAULT 0,
            comments_enabled boolean DEFAULT 1,
            
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY object_id (object_id),
            KEY status (status),
            KEY acquisition_date (acquisition_date),
            KEY telescope_aperture (telescope_aperture),
            KEY camera_type (camera_type),
            KEY total_exposure_time (total_exposure_time)
        ) $charset_collate;";
        
        // Table des équipements (réutilisables)
        $sql_equipment = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_equipment (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            type enum('telescope','camera','mount','filter','accessory','software') NOT NULL,
            brand varchar(100),
            model varchar(255),
            specifications text,
            image_url varchar(500),
            website_url varchar(500),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY type (type),
            KEY brand (brand)
        ) $charset_collate;";
        
        // Table des likes
        $sql_likes = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_likes (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            image_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY image_user (image_id, user_id),
            KEY image_id (image_id),
            KEY user_id (user_id)
        ) $charset_collate;";
        
        // Table des commentaires spécialisés
        $sql_comments = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}astro_comments (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            image_id mediumint(9) NOT NULL,
            user_id bigint(20) NOT NULL,
            comment text NOT NULL,
            parent_id mediumint(9) DEFAULT NULL,
            status enum('approved','pending','spam') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY image_id (image_id),
            KEY user_id (user_id),
            KEY parent_id (parent_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Exécuter les requêtes
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_catalogs);
        dbDelta($sql_objects);
        dbDelta($sql_cross_refs);
        dbDelta($sql_images);
        dbDelta($sql_equipment);
        dbDelta($sql_likes);
        dbDelta($sql_comments);
        
        update_option('astro_db_version', self::DB_VERSION);
    }
    
    public static function import_default_data() {
        // Créer les catalogues
        Astro_Catalogs::create_default_catalogs();
        
        // Importer les objets de tous les catalogues
        Astro_Catalogs::import_messier_catalog();
        Astro_Catalogs::import_ngc_sample();
        Astro_Catalogs::import_ic_sample();
        Astro_Catalogs::import_caldwell_sample();
        Astro_Catalogs::import_sharpless_sample();
    }
    
    public static function force_reinstall_tables() {
        // Supprimer toutes les tables existantes
        self::drop_tables();
        
        // Les recréer avec le nouveau schéma
        self::create_tables_sql();
        
        error_log('AstroPortfolio: Tables réinstallées avec le nouveau schéma');
    }
    
    public static function drop_tables() {
        global $wpdb;
        
        $tables = array(
            $wpdb->prefix . 'astro_comments',
            $wpdb->prefix . 'astro_likes',
            $wpdb->prefix . 'astro_equipment',
            $wpdb->prefix . 'astro_images',
            $wpdb->prefix . 'astro_objects',
            $wpdb->prefix . 'astro_catalogs'
        );
        
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS $table");
        }
        
        delete_option('astro_db_version');
    }
    
    /**
     * Corriger la table des métadonnées problématique
     */
    public static function fix_metadata_table() {
        global $wpdb;
        
        // Supprimer l'ancienne table corrompue si elle existe
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        
        // Inclure la classe des métadonnées si pas déjà fait
        $metadata_file = dirname(__FILE__) . '/class-anc-image-metadata.php';
        if (file_exists($metadata_file)) {
            require_once $metadata_file;
            
            // Créer la nouvelle table avec la bonne structure
            if (class_exists('ANC_Image_Metadata')) {
                ANC_Image_Metadata::create_metadata_table();
            }
        }
    }
}