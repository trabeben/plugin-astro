<?php
/**
 * Gestion des catalogues en administration
 */
class Astro_Admin_Catalogs {
    
    public function __construct() {
        add_action('wp_ajax_astro_search_catalog', array($this, 'search_catalog_ajax'));
        add_action('wp_ajax_astro_get_catalog_stats', array($this, 'get_catalog_stats_ajax'));
        add_action('wp_ajax_astro_get_metadata_form', array($this, 'get_metadata_form_ajax'));
        // Conserver l'ancien système en backup
        add_action('wp_ajax_astro_import_catalog', array($this, 'import_catalog_ajax'));
        add_action('wp_ajax_astro_export_catalog', array($this, 'export_catalog_ajax'));
        add_action('wp_ajax_astro_get_import_progress', array($this, 'get_import_progress_ajax'));
        add_action('astro_cleanup_progress', array($this, 'cleanup_progress_data'));
    }
    
    /**
     * Charger le formulaire de métadonnées
     */
    public function get_metadata_form_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'astro_admin_nonce')) {
            wp_send_json_error(array('message' => 'Nonce invalide'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission insuffisante'));
        }
        
        // Inclure les classes nécessaires
        require_once(dirname(__DIR__) . '/includes/class-anc-image-metadata.php');
        require_once(dirname(__FILE__) . '/class-anc-image-metadata-form.php');
        
        // Créer la table si elle n'existe pas
        ANC_Image_Metadata::create_metadata_table();
        
        // Générer le formulaire
        ob_start();
        ANC_Image_Metadata_Form::display_form();
        $form_html = ob_get_clean();
        
        wp_send_json_success(array(
            'form_html' => $form_html,
            'message' => 'Formulaire chargé avec succès'
        ));
    }
    
    /**
     * Rechercher dans le catalogue directement (sans import)
     */
    public function search_catalog_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'astro_admin_nonce')) {
            wp_send_json_error(array('message' => 'Nonce invalide'));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission insuffisante'));
        }
        
        $search_term = sanitize_text_field($_POST['search'] ?? '');
        $limit = intval($_POST['limit'] ?? 50);
        
        if (strlen($search_term) < 2) {
            wp_send_json_error(array('message' => 'Terme de recherche trop court (minimum 2 caractères)'));
        }
        
        // Inclure le reader
        require_once(dirname(__DIR__) . '/includes/class-anc-catalog-reader.php');
        
        $results = ANC_Catalog_Reader::search_objects($search_term, $limit);
        
        wp_send_json_success(array(
            'objects' => $results,
            'count' => count($results),
            'search_term' => $search_term
        ));
    }
    
    /**
     * Obtenir les statistiques du catalogue
     */
    public function get_catalog_stats_ajax() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'astro_admin_nonce')) {
            wp_send_json_error(array('message' => 'Nonce invalide'));
        }
        
        // Inclure le reader
        require_once(dirname(__DIR__) . '/includes/class-anc-catalog-reader.php');
        
        $stats = ANC_Catalog_Reader::get_stats();
        
        wp_send_json_success($stats);
    }
    
    /**
     * Récupérer la progression d'un import en cours
     */
    public function get_import_progress_ajax() {
        check_ajax_referer('astro_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_astro_catalogs')) {
            wp_send_json_error(array('message' => 'Permission insuffisante'));
        }
        
        $progress_key = 'astro_import_progress_' . get_current_user_id();
        $progress = get_option($progress_key, null);
        
        if ($progress === null) {
            wp_send_json_error(array('message' => 'Aucun import en cours'));
        }
        
        // Calculer la durée écoulée
        if (isset($progress['start_time'])) {
            $progress['elapsed_time'] = time() - $progress['start_time'];
            $progress['elapsed_formatted'] = $this->format_duration($progress['elapsed_time']);
        }
        
        // Estimation du temps restant
        if ($progress['progress'] > 0 && $progress['progress'] < 100 && isset($progress['elapsed_time'])) {
            $estimated_total = ($progress['elapsed_time'] * 100) / $progress['progress'];
            $remaining = max(0, $estimated_total - $progress['elapsed_time']);
            $progress['estimated_remaining'] = $this->format_duration($remaining);
        }
        
        wp_send_json_success($progress);
    }
    
    /**
     * Nettoyer les données de progression anciennes
     */
    public function cleanup_progress_data($progress_key) {
        delete_option($progress_key);
    }
    
    /**
     * Formater une durée en secondes
     */
    private function format_duration($seconds) {
        if ($seconds < 60) {
            return $seconds . 's';
        } elseif ($seconds < 3600) {
            return floor($seconds / 60) . 'min ' . ($seconds % 60) . 's';
        } else {
            return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'min';
        }
    }
    
    public function import_catalog_ajax() {
        error_log('=== ANC IMPORT HANDLER START ===');
        error_log('ANC: import_catalog_ajax appelé - POST data: ' . print_r($_POST, true));
        error_log('ANC: Current user ID: ' . get_current_user_id());
        error_log('ANC: User can manage_options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
        
        // Force JSON response header
        header('Content-Type: application/json');
        
        if (!isset($_POST['nonce'])) {
            error_log('ANC: Nonce manquant dans la requête');
            wp_send_json_error(array('message' => 'Nonce manquant'));
            return;
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'astro_admin_nonce')) {
            error_log('ANC: Vérification du nonce échouée');
            wp_send_json_error(array('message' => 'Nonce invalide'));
            return;
        }
        
        if (!current_user_can('manage_options')) {
            error_log('ANC: Permission insuffisante pour l\'utilisateur ' . get_current_user_id());
            wp_send_json_error(array('message' => 'Permission insuffisante'));
            return;
        }
        
        $catalog_type = sanitize_text_field($_POST['catalog_type'] ?? '');
        error_log('ANC: Type de catalogue demandé: ' . $catalog_type);
        
        // Augmenter le timeout et la mémoire pour les gros imports
        set_time_limit(300); // 5 minutes
        ini_set('memory_limit', '256M');
        
        // Initialiser le suivi de progression
        $progress_key = 'astro_import_progress_' . get_current_user_id();
        update_option($progress_key, array(
            'status' => 'starting',
            'message' => 'Initialisation de l\'import...',
            'progress' => 0,
            'total' => 0,
            'imported' => 0,
            'errors' => 0,
            'start_time' => time(),
            'catalog_type' => $catalog_type
        ));
        
        try {
            $result = false;
            $stats = array('imported' => 0, 'errors' => 0, 'total' => 0);
            
            switch ($catalog_type) {
                case 'deepsky':
                    error_log('ANC: ===== DÉBUT IMPORT DEEPSKY =====');
                    
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'message' => 'Import du catalogue DeepSkyCatalogs (NGC + IC + Messier)...',
                        'progress' => 10
                    )));
                    
                    error_log('ANC: Appel de import_deepsky_with_progress...');
                    $result = $this->import_deepsky_with_progress($progress_key);
                    error_log('ANC: Retour de import_deepsky_with_progress: ' . ($result ? 'TRUE' : 'FALSE'));
                    error_log('ANC: import_deepsky_with_progress returned: ' . ($result ? 'true' : 'false'));
                    break;
                    
                case 'messier':
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'message' => 'Import du catalogue Messier...',
                        'progress' => 10
                    )));
                    
                    $result = Astro_Catalogs::import_messier_catalog();
                    break;
                    
                case 'caldwell':
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'message' => 'Import du catalogue Caldwell...',
                        'progress' => 10
                    )));
                    
                    $result = Astro_Catalogs::import_caldwell_catalog();
                    break;
                    
                case 'sharpless':
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'message' => 'Import du catalogue Sharpless...',
                        'progress' => 10
                    )));
                    
                    $result = Astro_Catalogs::import_sharpless_catalog();
                    break;
                    
                default:
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'status' => 'error',
                        'message' => 'Type de catalogue invalide: ' . $catalog_type,
                        'progress' => 0
                    )));
                    
                    wp_send_json_error(array(
                        'message' => 'Type de catalogue invalide',
                        'details' => 'Types supportés: deepsky, messier, caldwell, sharpless'
                    ));
                    return;
            }
            
            // Récupérer les statistiques finales
            $final_progress = get_option($progress_key);
            error_log('ANC: Final progress data: ' . print_r($final_progress, true));
            error_log('ANC: Import result: ' . ($result ? 'SUCCESS' : 'FAILURE'));
            
            if ($result) {
                update_option($progress_key, array_merge($final_progress, array(
                    'status' => 'completed',
                    'message' => 'Import terminé avec succès !',
                    'progress' => 100,
                    'end_time' => time()
                )));
                
                // Nettoyage automatique après 30 secondes
                wp_schedule_single_event(time() + 30, 'astro_cleanup_progress', array($progress_key));
                
                $response_data = array(
                    'message' => 'Catalogue importé avec succès',
                    'progress_key' => $progress_key,
                    'stats' => array(
                        'imported' => $final_progress['imported'] ?? 0,
                        'errors' => $final_progress['errors'] ?? 0,
                        'duration' => (time() - $final_progress['start_time']) . ' secondes'
                    )
                );
                
                error_log('ANC: Sending SUCCESS response: ' . json_encode($response_data));
                wp_send_json_success($response_data);
            } else {
                update_option($progress_key, array_merge($final_progress, array(
                    'status' => 'error',
                    'message' => 'Erreur lors de l\'importation - voir les logs pour plus de détails',
                    'progress' => 0,
                    'end_time' => time()
                )));
                
                $error_data = array(
                    'message' => 'Erreur lors de l\'importation',
                    'progress_key' => $progress_key,
                    'details' => 'Consultez les logs WordPress pour plus de détails'
                );
                
                error_log('ANC: Sending ERROR response: ' . json_encode($error_data));
                wp_send_json_error($error_data);
            }
            
        } catch (Exception $e) {
            $error_data = array(
                'message' => 'Exception lors de l\'import: ' . $e->getMessage(),
                'progress_key' => $progress_key,
                'details' => 'Fichier: ' . basename($e->getFile()) . ' ligne ' . $e->getLine()
            );
            
            error_log('ANC: Exception in import - Sending ERROR response: ' . json_encode($error_data));
            wp_send_json_error($error_data);
        }
        
        // Si on arrive ici sans avoir envoyé de réponse, c'est un problème
        error_log('ANC: WARNING - No response sent, sending generic error');
        wp_send_json_error(array('message' => 'Aucune réponse générée'));
    }
    
    /**
     * Import DeepSkyCatalogs avec suivi de progression
     */
    private function import_deepsky_with_progress($progress_key) {
        error_log('ANC: ===== ENTRÉE import_deepsky_with_progress =====');
        error_log('ANC: progress_key = ' . $progress_key);
        
        global $wpdb;
        
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        $csv_path = $plugin_path . 'data/NGC copie.csv';
        
        error_log('ANC: plugin_path = ' . $plugin_path);
        error_log('ANC: csv_path = ' . $csv_path);
        error_log('ANC: fichier existe = ' . (file_exists($csv_path) ? 'OUI' : 'NON'));
        
        // Vérifications préliminaires
        $progress = get_option($progress_key);
        
        if (!file_exists($csv_path)) {
            update_option($progress_key, array_merge($progress, array(
                'status' => 'error',
                'message' => 'Fichier DeepSkyCatalogs introuvable: ' . basename($csv_path),
                'progress' => 0
            )));
            return false;
        }
        
        $handle = fopen($csv_path, 'r');
        if ($handle === false) {
            update_option($progress_key, array_merge($progress, array(
                'status' => 'error',
                'message' => 'Impossible d\'ouvrir le fichier DeepSkyCatalogs',
                'progress' => 0
            )));
            return false;
        }
        
        // Compter les lignes pour la progression
        $total_lines = 0;
        while (fgets($handle) !== false) {
            $total_lines++;
        }
        $total_lines--; // Enlever l'en-tête
        rewind($handle);
        
        update_option($progress_key, array_merge($progress, array(
            'message' => 'Fichier analysé: ' . number_format($total_lines) . ' objets à traiter',
            'progress' => 20,
            'total' => $total_lines
        )));
        
        // Lire l'en-tête
        $header = str_getcsv(fgets($handle), ';');
        
        // Vérifier et créer les catalogues
        $catalogs = $this->ensure_catalogs_exist();
        if (empty($catalogs)) {
            fclose($handle);
            update_option($progress_key, array_merge($progress, array(
                'status' => 'error',
                'message' => 'Impossible de créer/trouver les catalogues en base de données',
                'progress' => 0
            )));
            return false;
        }
        
        update_option($progress_key, array_merge(get_option($progress_key), array(
            'message' => 'Catalogues vérifiés. Début de l\'import des objets...',
            'progress' => 30
        )));
        
        $success_count = 0;
        $error_count = 0;
        $line_number = 1;
        $last_update = time();
        
        // Import des objets avec progression
        while (($line = fgets($handle)) !== false) {
            $line_number++;
            $row = str_getcsv(trim($line), ';');
            
            if (count($row) >= count($header)) {
                $object = array();
                for ($i = 0; $i < count($header); $i++) {
                    $object[$header[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
                }
                
                // Traitement de l'objet (logique existante)
                if ($this->process_deepsky_object($object, $catalogs)) {
                    $success_count++;
                } else {
                    $error_count++;
                }
                
                // Mise à jour de la progression toutes les 100 lignes ou toutes les 2 secondes
                if (($line_number % 100 === 0) || (time() - $last_update >= 2)) {
                    $progress_percent = min(30 + (($line_number / $total_lines) * 60), 95);
                    
                    update_option($progress_key, array_merge(get_option($progress_key), array(
                        'message' => sprintf(
                            'Import en cours: %s/%s objets traités (%d réussites, %d erreurs)',
                            number_format($line_number - 1),
                            number_format($total_lines),
                            $success_count,
                            $error_count
                        ),
                        'progress' => $progress_percent,
                        'imported' => $success_count,
                        'errors' => $error_count,
                        'processed' => $line_number - 1
                    )));
                    
                    $last_update = time();
                }
            }
        }
        
        fclose($handle);
        
        // Finaliser la progression
        update_option($progress_key, array_merge(get_option($progress_key), array(
            'message' => sprintf(
                'Import terminé: %s objets importés avec succès, %s erreurs',
                number_format($success_count),
                number_format($error_count)
            ),
            'progress' => 95,
            'imported' => $success_count,
            'errors' => $error_count,
            'total_processed' => $line_number - 1
        )));
        
        error_log("ANC: Import DeepSkyCatalogs terminé: {$success_count} objets importés, {$error_count} erreurs");
        return $success_count > 0;
    }
    
    /**
     * Traiter un objet DeepSkyCatalogs
     */
    private function process_deepsky_object($object, $catalogs) {
        global $wpdb;
        
        // Logique existante de traitement des objets
        $name = $object['Name'];
        $catalog_id = null;
        $designation = $name;
        
        if (preg_match('/^NGC(\d+)/', $name, $matches)) {
            $catalog_id = isset($catalogs['NGC']) ? $catalogs['NGC'] : null;
        } elseif (preg_match('/^IC(\d+)/', $name, $matches)) {
            $catalog_id = isset($catalogs['IC']) ? $catalogs['IC'] : null;
        } elseif (!empty($object['M']) && isset($catalogs['M'])) {
            $catalog_id = $catalogs['M'];
            $designation = 'M' . $object['M'];
        } else {
            return false; // Objet ignoré
        }
        
        if (!$catalog_id) {
            return false;
        }
        
        // Conversion des coordonnées avec gestion sécurisée
        $ra_hours = 0.0;
        $dec_degrees = 0.0;
        
        try {
            if (!empty($object['RA'])) {
                $ra_hours = $this->parse_ra_to_decimal($object['RA']);
            }
            if (!empty($object['Dec'])) {
                $dec_degrees = $this->parse_dec_to_decimal($object['Dec']);
            }
        } catch (Exception $e) {
            error_log('ANC: Erreur parsing coordonnées: ' . $e->getMessage());
        }
        
        $magnitude = null;
        if (!empty($object['V-Mag']) && is_numeric($object['V-Mag'])) {
            $magnitude = floatval($object['V-Mag']);
        } elseif (!empty($object['B-Mag']) && is_numeric($object['B-Mag'])) {
            $magnitude = floatval($object['B-Mag']);
        }
        
        $size = '';
        if (!empty($object['MajAx']) && is_numeric($object['MajAx'])) {
            $maj_ax = floatval($object['MajAx']);
            if (!empty($object['MinAx']) && is_numeric($object['MinAx'])) {
                $min_ax = floatval($object['MinAx']);
                $size = $maj_ax . '×' . $min_ax . '\'';
            } else {
                $size = $maj_ax . '\'';
            }
        }
        
        $common_names = array();
        if (!empty($object['Common names'])) {
            $common_names[] = $object['Common names'];
        }
        if (!empty($object['M'])) {
            $common_names[] = 'M' . $object['M'];
        }
        if (!empty($object['NGC']) && $object['NGC'] != $name) {
            $common_names[] = 'NGC' . $object['NGC'];
        }
        if (!empty($object['IC']) && $object['IC'] != $name) {
            $common_names[] = 'IC' . $object['IC'];
        }
        
        $common_names_str = !empty($common_names) ? implode(', ', array_unique($common_names)) : $designation;
        
        // Insertion en base
        $result = $wpdb->replace(
            $wpdb->prefix . 'astro_objects',
            array(
                'designation' => $designation,
                'catalog_id' => $catalog_id,
                'object_type' => !empty($object['Type']) ? $object['Type'] : 'Unknown',
                'constellation' => !empty($object['Const']) ? $object['Const'] : '',
                'ra_hours' => $ra_hours,
                'dec_degrees' => $dec_degrees,
                'magnitude' => $magnitude,
                'size' => $size,
                'distance' => null,
                'common_names' => $common_names_str,
                'notes' => !empty($object['OpenNGC notes']) ? $object['OpenNGC notes'] : ''
            ),
            array('%s', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%s')
        );
        
        return $result !== false;
    }
    
    /**
     * S'assurer que les catalogues existent
     */
    private function ensure_catalogs_exist() {
        global $wpdb;
        
        $catalogs = array();
        $catalog_codes = array('NGC', 'IC', 'M');
        
        foreach ($catalog_codes as $code) {
            $catalog = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = %s", $code));
            if ($catalog) {
                $catalogs[$code] = $catalog->id;
            } else {
                // Créer le catalogue manquant
                if (class_exists('Astro_Catalogs') && method_exists('Astro_Catalogs', 'create_default_catalogs')) {
                    Astro_Catalogs::create_default_catalogs();
                    // Réessayer
                    $catalog = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = %s", $code));
                    if ($catalog) {
                        $catalogs[$code] = $catalog->id;
                    }
                }
            }
        }
        
        return $catalogs;
    }
    
    public function export_catalog_ajax() {
        check_ajax_referer('astro_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_astro_catalogs')) {
            wp_send_json_error(array('message' => 'Permission insuffisante'));
        }
        
        $catalog_name = sanitize_text_field($_POST['catalog_name'] ?? '');
        
        if (!$catalog_name) {
            wp_send_json_error(array('message' => 'Nom de catalogue requis'));
        }
        
        $objects = Astro_Catalogs::get_objects_by_catalog($catalog_name);
        
        if (empty($objects)) {
            wp_send_json_error(array('message' => 'Aucun objet trouvé dans ce catalogue'));
        }
        
        // Générer le CSV
        $csv_data = "object_name,catalog,object_type,coordinates_ra,coordinates_dec,magnitude,constellation\n";
        
        foreach ($objects as $object) {
            $csv_data .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $object->object_name,
                $object->catalog,
                $object->object_type ?? '',
                $object->coordinates_ra ?? '',
                $object->coordinates_dec ?? '',
                $object->magnitude ?? '',
                $object->constellation ?? ''
            );
        }
        
        wp_send_json_success(array(
            'csv_data' => $csv_data,
            'filename' => 'catalog_' . $catalog_name . '_' . date('Y-m-d') . '.csv'
        ));
    }
    
    /**
     * Parser RA au format HH:MM:SS.S en heures décimales (copie locale)
     */
    private function parse_ra_to_decimal($ra_string) {
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2}\.?\d*)$/', $ra_string, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = floatval($matches[3]);
            return $hours + ($minutes / 60) + ($seconds / 3600);
        }
        return 0.0;
    }
    
    /**
     * Parser Dec au format ±DD:MM:SS.S en degrés décimaux (copie locale)
     */
    private function parse_dec_to_decimal($dec_string) {
        if (preg_match('/^([+-]?)(\d{1,2}):(\d{2}):(\d{2}\.?\d*)$/', $dec_string, $matches)) {
            $sign = ($matches[1] === '-') ? -1 : 1;
            $degrees = intval($matches[2]);
            $minutes = intval($matches[3]);
            $seconds = floatval($matches[4]);
            return $sign * ($degrees + ($minutes / 60) + ($seconds / 3600));
        }
        return 0.0;
    }
}