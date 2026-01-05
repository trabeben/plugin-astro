<?php
/**
 * Extension des métadonnées d'images astrophotographiques
 * Comme AstroBin/Telescopius - données techniques complètes
 */
class ANC_Image_Metadata {
    
    /**
     * Créer/Mettre à jour les métadonnées complètes d'une image
     */
    public static function save_metadata($image_id, $metadata) {
        global $wpdb;
        
        // Table des métadonnées techniques
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        $defaults = array(
            // === ÉQUIPEMENT ===
            'telescope_brand' => '',
            'telescope_model' => '',
            'telescope_aperture' => 0,      // mm
            'telescope_focal_length' => 0,  // mm
            'telescope_focal_ratio' => '',  // f/5.6
            'mount_brand' => '',
            'mount_model' => '',
            'camera_brand' => '',
            'camera_model' => '',
            'camera_sensor' => '',          // Sony IMX571C
            'camera_cooling' => '',         // -10°C
            'camera_pixel_size' => 0,       // µm
            'reducer_corrector' => '',      // 0.8x reducer
            'filters' => '',                // L-RGB, Ha-OIII-SII
            'guiding_camera' => '',
            'guiding_scope' => '',
            'accessories' => '',            // Off-axis guider, etc.
            
            // === ACQUISITION ===
            'acquisition_dates' => '',      // 2024-01-15, 2024-01-16
            'location_name' => '',          // Observatoire du Pic du Midi
            'location_coords' => '',        // 42.9365, 0.1425
            'location_altitude' => 0,       // mètres
            'bortle_scale' => 0,           // 1-9
            'weather_conditions' => '',     // Clear, transparent
            'temperature' => '',           // -5°C
            'humidity' => '',              // 45%
            'wind_speed' => '',            // 5 km/h
            'seeing' => '',                // 2 arcsec
            'moon_illumination' => '',     // 15%
            
            // === POSES ===
            'lights_count' => 0,           // Nombre de poses utiles
            'lights_exposure' => 0,        // Temps par pose (secondes)
            'lights_total_time' => 0,      // Temps total (secondes)
            'lights_iso_gain' => '',       // ISO 800 ou Gain 139
            'lights_binning' => '',        // 1x1, 2x2
            'lights_temperature' => '',    // -10°C
            'darks_count' => 0,
            'darks_exposure' => 0,
            'flats_count' => 0,
            'bias_count' => 0,
            'filter_details' => '',        // Ha: 20x600s, OIII: 15x600s, SII: 18x600s
            
            // === TRAITEMENT ===
            'stacking_software' => '',     // PixInsight, DeepSkyStacker
            'processing_software' => '',   // PixInsight, Photoshop
            'preprocessing_steps' => '',   // Calibration, registration, integration
            'processing_steps' => '',     // DynamicBackgroundExtraction, HistogramTransformation
            'special_techniques' => '',    // HDR, Drizzle, Deconvolution
            
            // === RÉSULTAT ===
            'final_resolution' => '',     // 4096x4096
            'pixel_scale' => 0,           // arcsec/pixel
            'field_of_view' => '',        // 2.1° x 1.4°
            'north_orientation' => 0,     // degrés
            'processing_notes' => '',     // Notes libres sur le traitement
            'acquisition_notes' => '',    // Notes libres sur l'acquisition
            
            // === SOCIAL ===
            'awards' => '',               // APOD, contests
            'collaboration' => '',        // Co-auteurs
            'inspiration' => '',          // Source d'inspiration
            'challenges' => '',           // Difficultés rencontrées
            
            // === TECHNIQUE AVANCÉE ===
            'autoguiding' => '',          // PHD2, settings
            'focusing' => '',             // Autofocus, Bahtinov mask
            'plate_solving' => '',        // ASTAP, ANSVR
            'capture_software' => '',     // NINA, SGP, MaxIm DL
            'dithering' => '',            // Every 5 frames, 3 pixels
            'meridian_flip' => '',        // Yes, automatic
        );
        
        $metadata = wp_parse_args($metadata, $defaults);
        
        // Vérifier si les métadonnées existent déjà
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT image_id FROM $table_name WHERE image_id = %d",
            $image_id
        ));
        
        if ($existing) {
            // Mise à jour
            $result = $wpdb->update(
                $table_name,
                $metadata,
                array('image_id' => $image_id)
            );
        } else {
            // Insertion
            $metadata['image_id'] = $image_id;
            $result = $wpdb->insert($table_name, $metadata);
        }
        
        return $result !== false;
    }
    
    /**
     * Récupérer les métadonnées complètes d'une image
     */
    public static function get_metadata($image_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE image_id = %d",
            $image_id
        ), ARRAY_A);
    }
    
    /**
     * Afficher les métadonnées formatées (style AstroBin)
     */
    public static function display_metadata($image_id, $format = 'full') {
        $meta = self::get_metadata($image_id);
        
        if (!$meta) {
            return '<p><em>Aucune donnée technique disponible.</em></p>';
        }
        
        $html = '<div class="astro-metadata">';
        
        // === ACQUISITION ===
        if ($format === 'full' || $format === 'acquisition') {
            $html .= '<div class="meta-section">';
            $html .= '<h4>📷 Acquisition</h4>';
            $html .= '<div class="meta-grid">';
            
            if (!empty($meta['acquisition_dates'])) {
                $html .= '<div class="meta-item"><strong>Dates:</strong> ' . esc_html($meta['acquisition_dates'] ?? '') . '</div>';
            }
            
            if (!empty($meta['location_name'])) {
                $html .= '<div class="meta-item"><strong>Lieu:</strong> ' . esc_html($meta['location_name'] ?? '') . '</div>';
            }
            
            if (!empty($meta['lights_count']) && !empty($meta['lights_exposure'])) {
                $total_hours = round($meta['lights_total_time'] / 3600, 1);
                $html .= '<div class="meta-item"><strong>Poses:</strong> ' . $meta['lights_count'] . ' × ' . $meta['lights_exposure'] . 's (' . $total_hours . 'h total)</div>';
            }
            
            if (!empty($meta['lights_iso_gain'])) {
                $html .= '<div class="meta-item"><strong>ISO/Gain:</strong> ' . esc_html($meta['lights_iso_gain'] ?? '') . '</div>';
            }
            
            if ($meta['bortle_scale'] > 0) {
                $html .= '<div class="meta-item"><strong>Ciel:</strong> Bortle ' . $meta['bortle_scale'] . '</div>';
            }
            
            $html .= '</div></div>';
        }
        
        // === ÉQUIPEMENT ===
        if ($format === 'full' || $format === 'equipment') {
            $html .= '<div class="meta-section">';
            $html .= '<h4>🔭 Équipement</h4>';
            $html .= '<div class="meta-grid">';
            
            if (!empty($meta['telescope_brand']) || !empty($meta['telescope_model'])) {
                $telescope = ($meta['telescope_brand'] ?? '') . ' ' . ($meta['telescope_model'] ?? '');
                $telescope = trim($telescope);
                if ($meta['telescope_aperture'] > 0) {
                    $telescope .= ' (' . $meta['telescope_aperture'] . 'mm';
                    if (!empty($meta['telescope_focal_ratio'])) {
                        $telescope .= ' ' . $meta['telescope_focal_ratio'];
                    }
                    $telescope .= ')';
                }
                $html .= '<div class="meta-item"><strong>Télescope:</strong> ' . esc_html($telescope) . '</div>';
            }
            
            if (!empty($meta['camera_brand']) || !empty($meta['camera_model'])) {
                $camera = ($meta['camera_brand'] ?? '') . ' ' . ($meta['camera_model'] ?? '');
                $camera = trim($camera);
                if (!empty($meta['camera_sensor'])) {
                    $camera .= ' (' . $meta['camera_sensor'] . ')';
                }
                $html .= '<div class="meta-item"><strong>Caméra:</strong> ' . esc_html($camera) . '</div>';
            }
            
            if (!empty($meta['mount_brand']) || !empty($meta['mount_model'])) {
                $mount = ($meta['mount_brand'] ?? '') . ' ' . ($meta['mount_model'] ?? '');
                $mount = trim($mount);
                $html .= '<div class="meta-item"><strong>Monture:</strong> ' . esc_html($mount) . '</div>';
            }
            
            if (!empty($meta['filters'])) {
                $html .= '<div class="meta-item"><strong>Filtres:</strong> ' . esc_html($meta['filters'] ?? '') . '</div>';
            }
            
            $html .= '</div></div>';
        }
        
        // === TRAITEMENT ===
        if ($format === 'full' || $format === 'processing') {
            $html .= '<div class="meta-section">';
            $html .= '<h4>⚙️ Traitement</h4>';
            $html .= '<div class="meta-grid">';
            
            if (!empty($meta['stacking_software'])) {
                $html .= '<div class="meta-item"><strong>Empilement:</strong> ' . esc_html($meta['stacking_software'] ?? '') . '</div>';
            }
            
            if (!empty($meta['processing_software'])) {
                $html .= '<div class="meta-item"><strong>Traitement:</strong> ' . esc_html($meta['processing_software'] ?? '') . '</div>';
            }
            
            if (!empty($meta['processing_steps'])) {
                $html .= '<div class="meta-item"><strong>Étapes:</strong> ' . esc_html($meta['processing_steps'] ?? '') . '</div>';
            }
            
            $html .= '</div></div>';
        }
        
        $html .= '</div>';
        
        // CSS intégré
        $html .= '<style>
        .astro-metadata { margin: 20px 0; }
        .meta-section { margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 5px; }
        .meta-section h4 { margin: 0 0 10px 0; color: #333; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 8px; }
        .meta-item { font-size: 14px; }
        .meta-item strong { color: #666; }
        </style>';
        
        return $html;
    }
    
    /**
     * Créer la table des métadonnées si elle n'existe pas
     */
    public static function create_metadata_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'astro_image_metadata';
        
        // Vérifier si la table existe déjà
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if ($table_exists) {
            // Si la table existe déjà, ne pas la recréer pour éviter les erreurs
            return;
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            image_id bigint(20) NOT NULL,
            
            telescope_brand varchar(100) DEFAULT '',
            telescope_model varchar(100) DEFAULT '',
            telescope_aperture int(11) DEFAULT 0,
            telescope_focal_length int(11) DEFAULT 0,
            telescope_focal_ratio varchar(20) DEFAULT '',
            mount_brand varchar(100) DEFAULT '',
            mount_model varchar(100) DEFAULT '',
            camera_brand varchar(100) DEFAULT '',
            camera_model varchar(100) DEFAULT '',
            camera_sensor varchar(100) DEFAULT '',
            camera_cooling varchar(50) DEFAULT '',
            camera_pixel_size decimal(4,2) DEFAULT 0,
            reducer_corrector varchar(200) DEFAULT '',
            filters text,
            guiding_camera varchar(200) DEFAULT '',
            guiding_scope varchar(200) DEFAULT '',
            accessories text,
            
            acquisition_dates text,
            location_name varchar(200) DEFAULT '',
            location_coords varchar(100) DEFAULT '',
            location_altitude int(11) DEFAULT 0,
            bortle_scale tinyint(1) DEFAULT 0,
            weather_conditions text,
            temperature varchar(20) DEFAULT '',
            humidity varchar(20) DEFAULT '',
            wind_speed varchar(20) DEFAULT '',
            seeing varchar(20) DEFAULT '',
            moon_illumination varchar(20) DEFAULT '',
            
            lights_count int(11) DEFAULT 0,
            lights_exposure int(11) DEFAULT 0,
            lights_total_time int(11) DEFAULT 0,
            lights_iso_gain varchar(50) DEFAULT '',
            lights_binning varchar(10) DEFAULT '',
            lights_temperature varchar(20) DEFAULT '',
            darks_count int(11) DEFAULT 0,
            darks_exposure int(11) DEFAULT 0,
            flats_count int(11) DEFAULT 0,
            bias_count int(11) DEFAULT 0,
            filter_details text,
            
            stacking_software varchar(200) DEFAULT '',
            processing_software varchar(200) DEFAULT '',
            preprocessing_steps text,
            processing_steps text,
            special_techniques text,
            
            final_resolution varchar(50) DEFAULT '',
            pixel_scale decimal(4,2) DEFAULT 0,
            field_of_view varchar(50) DEFAULT '',
            north_orientation decimal(5,2) DEFAULT 0,
            processing_notes text,
            acquisition_notes text,
            
            awards text,
            collaboration text,
            inspiration text,
            challenges text,
            
            autoguiding text,
            focusing text,
            plate_solving text,
            capture_software text,
            dithering text,
            meridian_flip text,
            
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            
            PRIMARY KEY (image_id)
        ) $charset_collate;";
        
        // Utiliser directement la requête SQL au lieu de dbDelta qui pose problème
        $result = $wpdb->query($sql);
        
        if ($result === false) {
            error_log('Erreur création table astro_image_metadata: ' . $wpdb->last_error);
        }
    }
}