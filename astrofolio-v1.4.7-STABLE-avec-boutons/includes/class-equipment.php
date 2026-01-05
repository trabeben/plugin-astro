<?php
/**
 * Gestion des équipements d'astrophotographie
 */
class Astro_Equipment {
    
    public static function create_default_equipment() {
        global $wpdb;
        
        // Télescopes populaires
        $telescopes = array(
            array('Celestron EdgeHD 8"', 'telescope', 'Celestron', 'EdgeHD 8"', 'Schmidt-Cassegrain, 203mm f/10'),
            array('William Optics RedCat 51', 'telescope', 'William Optics', 'RedCat 51', 'Réfracteur apochromatique, 51mm f/4.9'),
            array('Sky-Watcher Esprit 100ED', 'telescope', 'Sky-Watcher', 'Esprit 100ED', 'Réfracteur triplet ED, 100mm f/5.5'),
            array('Takahashi FSQ-106EDX4', 'telescope', 'Takahashi', 'FSQ-106EDX4', 'Réfracteur fluorite, 106mm f/5'),
            array('Celestron C11 EdgeHD', 'telescope', 'Celestron', 'C11 EdgeHD', 'Schmidt-Cassegrain, 280mm f/10'),
            array('Astro-Tech AT65EDQ', 'telescope', 'Astro-Tech', 'AT65EDQ', 'Réfracteur quadruplet, 65mm f/6.5'),
            array('William Optics GT81', 'telescope', 'William Optics', 'GT81', 'Réfracteur triplet, 81mm f/5.9'),
            array('Sky-Watcher Evostar 72ED', 'telescope', 'Sky-Watcher', 'Evostar 72ED', 'Réfracteur doublet ED, 72mm f/5.8')
        );
        
        // Caméras populaires
        $cameras = array(
            array('ZWO ASI2600MC-Pro', 'camera', 'ZWO', 'ASI2600MC-Pro', 'CMOS couleur, 26MP, refroidie'),
            array('ZWO ASI533MC-Pro', 'camera', 'ZWO', 'ASI533MC-Pro', 'CMOS couleur, 9MP, refroidie'),
            array('ZWO ASI1600MM-Pro', 'camera', 'ZWO', 'ASI1600MM-Pro', 'CMOS monochrome, 16MP, refroidie'),
            array('QHY268C', 'camera', 'QHYCCD', 'QHY268C', 'CMOS couleur, 26MP, refroidie'),
            array('Canon EOS R6', 'camera', 'Canon', 'EOS R6', 'DSLR plein format, 20MP'),
            array('Nikon D750', 'camera', 'Nikon', 'D750', 'DSLR plein format, 24MP'),
            array('ZWO ASI294MC-Pro', 'camera', 'ZWO', 'ASI294MC-Pro', 'CMOS couleur, 11.7MP, refroidie'),
            array('SBIG STF-8300M', 'camera', 'SBIG', 'STF-8300M', 'CCD monochrome, 8.3MP, refroidie')
        );
        
        // Montures populaires
        $mounts = array(
            array('Sky-Watcher EQ6-R Pro', 'mount', 'Sky-Watcher', 'EQ6-R Pro', 'Monture équatoriale GoTo, 20kg'),
            array('Celestron CGX', 'mount', 'Celestron', 'CGX', 'Monture équatoriale GoTo, 25kg'),
            array('iOptron CEM26', 'mount', 'iOptron', 'CEM26', 'Monture équatoriale center-balanced, 13kg'),
            array('Losmandy G11', 'mount', 'Losmandy', 'G11', 'Monture équatoriale, 27kg'),
            array('10Micron GM2000 HPS', 'mount', '10Micron', 'GM2000 HPS', 'Monture équatoriale haute précision, 35kg'),
            array('Software Bisque Paramount MX+', 'mount', 'Software Bisque', 'Paramount MX+', 'Monture équatoriale robotique, 45kg'),
            array('ZWO AM5', 'mount', 'ZWO', 'AM5', 'Monture équatoriale harmonique, 15kg'),
            array('Sky-Watcher HEQ5 Pro', 'mount', 'Sky-Watcher', 'HEQ5 Pro', 'Monture équatoriale GoTo, 13kg')
        );
        
        // Filtres populaires
        $filters = array(
            array('Astronomik Ha 6nm', 'filter', 'Astronomik', 'Ha 6nm', 'Filtre hydrogène alpha, bande passante 6nm'),
            array('Baader OIII 8.5nm', 'filter', 'Baader', 'OIII 8.5nm', 'Filtre oxygène III, bande passante 8.5nm'),
            array('Chroma SII 3nm', 'filter', 'Chroma', 'SII 3nm', 'Filtre soufre II, bande passante 3nm'),
            array('Optolong L-eNhance', 'filter', 'Optolong', 'L-eNhance', 'Filtre dual-band Ha/OIII'),
            array('Astronomik L-1 UV-IR Block', 'filter', 'Astronomik', 'L-1', 'Filtre de luminance, bloque UV/IR'),
            array('ZWO LRGB Set', 'filter', 'ZWO', 'LRGB', 'Set de filtres LRGB pour imagerie couleur'),
            array('Baader UHC-S', 'filter', 'Baader', 'UHC-S', 'Filtre nébulaire ultra haute contrast')
        );
        
        // Logiciels populaires
        $software = array(
            array('PixInsight', 'software', 'PixInsight', 'PixInsight', 'Logiciel de traitement d\'images astronomiques avancé'),
            array('Adobe Photoshop', 'software', 'Adobe', 'Photoshop', 'Logiciel de retouche photo professionnel'),
            array('DeepSkyStacker', 'software', 'DeepSkyStacker Team', 'DeepSkyStacker', 'Logiciel gratuit de stacking d\'images'),
            array('Astro Pixel Processor', 'software', 'Ivo Jager', 'APP', 'Logiciel de prétraitement et stacking'),
            array('Sequence Generator Pro', 'software', 'Main Sequence Software', 'SGP', 'Logiciel d\'acquisition automatisée'),
            array('PHD2 Guiding', 'software', 'PHD2 Team', 'PHD2', 'Logiciel de guidage gratuit'),
            array('NINA', 'software', 'NINA Team', 'NINA', 'Nighttime Imaging \'N\' Astronomy - suite d\'acquisition'),
            array('TheSkyX', 'software', 'Software Bisque', 'TheSkyX', 'Planétarium et contrôle de monture')
        );
        
        $all_equipment = array_merge($telescopes, $cameras, $mounts, $filters, $software);
        
        foreach ($all_equipment as $item) {
            $wpdb->replace(
                $wpdb->prefix . 'astro_equipment',
                array(
                    'name' => $item[0],
                    'type' => $item[1],
                    'brand' => $item[2],
                    'model' => $item[3],
                    'specifications' => $item[4]
                )
            );
        }
    }
    
    public static function get_equipment_by_type($type, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}astro_equipment 
             WHERE type = %s 
             ORDER BY brand ASC, model ASC 
             LIMIT %d",
            $type,
            $limit
        ));
    }
    
    public static function search_equipment($search_term, $type = null) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        $where = "WHERE (name LIKE %s OR brand LIKE %s OR model LIKE %s)";
        $params = array($search, $search, $search);
        
        if ($type) {
            $where .= " AND type = %s";
            $params[] = $type;
        }
        
        $params[] = 20;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}astro_equipment 
             $where
             ORDER BY brand ASC, model ASC 
             LIMIT %d",
            ...$params
        ));
    }
    
    public static function get_popular_equipment() {
        global $wpdb;
        
        return array(
            'telescopes' => $wpdb->get_results("
                SELECT telescope as name, COUNT(*) as usage_count
                FROM {$wpdb->prefix}astro_images 
                WHERE telescope != '' AND status = 'published'
                GROUP BY telescope 
                ORDER BY usage_count DESC 
                LIMIT 10
            "),
            'cameras' => $wpdb->get_results("
                SELECT camera_name as name, COUNT(*) as usage_count
                FROM {$wpdb->prefix}astro_images 
                WHERE camera_name != '' AND status = 'published'
                GROUP BY camera_name 
                ORDER BY usage_count DESC 
                LIMIT 10
            "),
            'mounts' => $wpdb->get_results("
                SELECT mount_name as name, COUNT(*) as usage_count
                FROM {$wpdb->prefix}astro_images 
                WHERE mount_name != '' AND status = 'published'
                GROUP BY mount_name 
                ORDER BY usage_count DESC 
                LIMIT 10
            ")
        );
    }
    
    public static function add_equipment($data) {
        global $wpdb;
        
        $defaults = array(
            'name' => '',
            'type' => 'other',
            'brand' => '',
            'model' => '',
            'specifications' => '',
            'image_url' => '',
            'website_url' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'astro_equipment',
            $data
        );
        
        return $result ? $wpdb->insert_id : false;
    }
}