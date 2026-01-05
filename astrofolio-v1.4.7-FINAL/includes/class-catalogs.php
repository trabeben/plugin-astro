<?php
/**
 * =============================================================================
 * CLASSE DE GESTION DES CATALOGUES ASTRONOMIQUES
 * =============================================================================
 * 
 * Cette classe gère tous les catalogues d'objets astronomiques intégrés
 * 
 * 📚 CATALOGUES SUPPORTÉS :
 * - NGC (New General Catalogue) : ~8000 objets
 * - IC (Index Catalogue) : ~5000 objets 
 * - Messier : 110 objets les plus brillants
 * - Caldwell : 109 objets complémentaires à Messier
 * - Sharpless : Nébuleuses d'émission H-alpha
 * - Abell : Nébuleuses planétaires et amas de galaxies
 * - UGC : Uppsala General Catalogue of Galaxies
 * - PGC : Principal Galaxies Catalogue
 * 
 * 🔗 RÉFÉRENCES CROISÉES :
 * - Correspondances entre catalogues (NGC 7000 = Sh2-117)
 * - Noms communs (Nébuleuse du Voile, Andromède, etc.)
 * - Synonymes et variantes orthographiques
 * - Mapping automatique lors des recherches
 * 
 * 🔍 FONCTIONNALITÉS DE RECHERCHE :
 * - Recherche rapide par nom/désignation
 * - Recherche floue avec tolérance aux fautes
 * - Autocomplétion en temps réel
 * - Filtrage par type d'objet (galaxie, nébuleuse, amas)
 * - Recherche par coordonnées (RA/Dec)
 * 
 * 💾 PERFORMANCE ET CACHE :
 * - Indexation en mémoire pour les recherches rapides
 * - Mise en cache des résultats fréquents
 * - Chargement paresseux des gros catalogues
 * - Optimisation des requêtes avec LIMIT/OFFSET
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Includes
 */
class Astro_Catalogs {
    
    public static function create_default_catalogs() {
        global $wpdb;
        
        $catalogs = array(
            array(
                'name' => 'Catalogue de Messier',
                'code' => 'M',
                'description' => 'Catalogue complet de 110 objets du ciel profond établi par Charles Messier (1730-1817) - Source: DeepSkyCatalogs',
                'total_objects' => 110,
                'source_url' => 'https://en.wikipedia.org/wiki/Messier_object'
            ),
            array(
                'name' => 'New General Catalogue',
                'code' => 'NGC',
                'description' => 'Catalogue complet des objets NGC (plus de 7800 objets) - Source: DeepSkyCatalogs/OpenNGC',
                'total_objects' => 7840,
                'source_url' => 'https://en.wikipedia.org/wiki/New_General_Catalogue'
            ),
            array(
                'name' => 'Index Catalogue',
                'code' => 'IC',
                'description' => 'Catalogue complet des objets IC (plus de 5000 objets) - Source: DeepSkyCatalogs/OpenNGC',
                'total_objects' => 5386,
                'source_url' => 'https://en.wikipedia.org/wiki/Index_Catalogue'
            ),
            array(
                'name' => 'Caldwell Catalogue',
                'code' => 'C',
                'description' => 'Catalogue complet de 109 objets du ciel profond de Patrick Moore (1995)',
                'total_objects' => 109,
                'source_url' => 'https://en.wikipedia.org/wiki/Caldwell_catalogue'
            ),
            array(
                'name' => 'Sharpless Catalogue',
                'code' => 'Sh',
                'description' => 'Sélection étendue de 200 régions HII parmi les plus photographiées du catalogue Sharpless',
                'total_objects' => 200,
                'source_url' => 'https://en.wikipedia.org/wiki/Sharpless_catalog'
            )
        );
        
        foreach ($catalogs as $catalog) {
            $wpdb->replace(
                $wpdb->prefix . 'astro_catalogs',
                $catalog
            );
        }
    }
    
    public static function import_messier_catalog() {
        return self::import_catalog_from_csv('messier', 'Messier', 'M');
    }
    
    // Méthode générique pour importer un catalogue depuis un fichier CSV
    private static function import_catalog_from_csv($filename, $catalog_name, $catalog_code) {
        global $wpdb;
        
        $catalog = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = %s", $catalog_code));
        if (!$catalog) return false;
        
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        $csv_path = $plugin_path . 'data/' . $filename . '.csv';
        
        if (!file_exists($csv_path)) {
            error_log("Fichier CSV introuvable: {$csv_path}");
            return false;
        }
        
        $handle = fopen($csv_path, 'r');
        if ($handle === false) {
            error_log("Impossible d'ouvrir le fichier CSV: {$csv_path}");
            return false;
        }
        
        // Lire l'en-tête
        $header = fgetcsv($handle, 0, ',');
        if ($header === false) {
            fclose($handle);
            error_log("Impossible de lire l'en-tête du fichier CSV");
            return false;
        }
        
        $success_count = 0;
        $error_count = 0;
        
        // Lire les données
        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if (count($row) >= count($header)) {
                $object = array();
                for ($i = 0; $i < count($header); $i++) {
                    $object[$header[$i]] = isset($row[$i]) ? $row[$i] : '';
                }
                
                // Insérer l'objet dans la base de données
                $result = $wpdb->replace(
                    $wpdb->prefix . 'astro_objects',
                    array(
                        'designation' => $object['designation'],
                        'catalog_id' => $catalog->id,
                        'object_type' => $object['object_type'],
                        'constellation' => $object['constellation'],
                        'ra_hours' => floatval($object['ra_hours']),
                        'dec_degrees' => floatval($object['dec_degrees']),
                        'magnitude' => !empty($object['magnitude']) ? floatval($object['magnitude']) : null,
                        'size' => $object['size'],
                        'distance' => !empty($object['distance_ly']) ? floatval($object['distance_ly']) : null,
                        'common_names' => !empty($object['common_name']) ? $object['common_name'] : $object['designation'],
                        'notes' => isset($object['notes']) ? $object['notes'] : ''
                    ),
                    array('%s', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%s')
                );
                
                if ($result !== false) {
                    $success_count++;
                } else {
                    $error_count++;
                    error_log("Erreur lors de l'insertion de l'objet: " . $object['designation']);
                }
            }
        }
        
        fclose($handle);
        
        error_log("Import du catalogue {$catalog_name}: {$success_count} objets importés, {$error_count} erreurs");
        return $success_count > 0;
    }
    
    public static function import_ngc_catalog() {
        return self::import_catalog_from_csv('ngc', 'NGC', 'NGC');
    }
    
    public static function import_ic_catalog() {
        return self::import_catalog_from_csv('ic', 'IC', 'IC');
    }
    
    public static function import_caldwell_catalog() {
        return self::import_catalog_from_csv('caldwell', 'Caldwell', 'C');
    }
    
    public static function import_sharpless_catalog() {
        return self::import_catalog_from_csv('sharpless', 'Sharpless', 'Sh');
    }
    
    // Méthode spéciale pour importer les données DeepSkyCatalogs (format semicolon-separated)
    public static function import_deepsky_catalogs() {
        global $wpdb;
        
        $plugin_path = plugin_dir_path(dirname(__FILE__));
        $csv_path = $plugin_path . 'data/NGC copie.csv';
        
        error_log("ANC: Début import DeepSkyCatalogs - Chemin: $csv_path");
        
        if (!file_exists($csv_path)) {
            error_log("ANC: Fichier DeepSkyCatalogs introuvable: {$csv_path}");
            return false;
        }
        
        $handle = fopen($csv_path, 'r');
        if ($handle === false) {
            error_log("ANC: Impossible d'ouvrir le fichier DeepSkyCatalogs: {$csv_path}");
            return false;
        }
        
        // Lire l'en-tête
        $header_line = fgets($handle);
        if ($header_line === false) {
            fclose($handle);
            error_log("ANC: Impossible de lire l'en-tête du fichier DeepSkyCatalogs");
            return false;
        }
        
        $header = str_getcsv(trim($header_line), ';');
        error_log("ANC: En-tête lu avec " . count($header) . " colonnes");
        
        // Obtenir les IDs des catalogues avec diagnostic
        $catalogs = array();
        $catalog_codes = array('NGC', 'IC', 'M');
        
        foreach ($catalog_codes as $code) {
            $catalog = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = %s", $code));
            if ($catalog) {
                $catalogs[$code] = $catalog->id;
                error_log("ANC: Catalogue $code trouvé avec ID: " . $catalog->id);
            } else {
                error_log("ANC: ERREUR - Catalogue $code non trouvé en base!");
                // Créer les catalogues manquants
                self::create_default_catalogs();
                // Réessayer
                $catalog = $wpdb->get_row($wpdb->prepare("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = %s", $code));
                if ($catalog) {
                    $catalogs[$code] = $catalog->id;
                    error_log("ANC: Catalogue $code créé avec ID: " . $catalog->id);
                }
            }
        }
        
        if (empty($catalogs)) {
            error_log("ANC: ERREUR CRITIQUE - Aucun catalogue trouvé!");
            fclose($handle);
            return false;
        }
        
        $success_count = 0;
        $error_count = 0;
        $line_number = 1;
        $max_errors = 10; // Limiter les logs d'erreur
        
        error_log("ANC: Début du parsing des objets...");
        
        // Lire les données ligne par ligne
        while (($line = fgets($handle)) !== false) {
            $line_number++;
            
            if ($line_number > 100 && $success_count == 0) {
                error_log("ANC: ATTENTION - 100 lignes traitées, aucun succès!");
                break;
            }
            
            $row = str_getcsv(trim($line), ';');
            
            if (count($row) >= count($header)) {
                $object = array();
                for ($i = 0; $i < count($header); $i++) {
                    $object[$header[$i]] = isset($row[$i]) ? trim($row[$i]) : '';
                }
                
                // Déterminer le catalogue principal basé sur le nom
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
                    // Objets non NGC/IC/Messier - on les ignore pour l'instant
                    continue;
                }
                
                if ($catalog_id) {
                    try {
                        // Convertir les coordonnées RA/Dec avec vérification
                        $ra_hours = 0.0;
                        $dec_degrees = 0.0;
                        
                        if (!empty($object['RA'])) {
                            $ra_hours = self::parse_ra_to_decimal($object['RA']);
                        }
                        
                        if (!empty($object['Dec'])) {
                            $dec_degrees = self::parse_dec_to_decimal($object['Dec']);
                        }
                        
                        // Obtenir la magnitude la plus appropriée
                        $magnitude = null;
                        if (!empty($object['V-Mag']) && is_numeric($object['V-Mag'])) {
                            $magnitude = floatval($object['V-Mag']);
                        } elseif (!empty($object['B-Mag']) && is_numeric($object['B-Mag'])) {
                            $magnitude = floatval($object['B-Mag']);
                        }
                        
                        // Calculer la taille en arcminutes
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
                        
                        // Construire les noms communs
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
                        
                        // Insérer l'objet dans la base de données
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
                                'distance' => null, // DeepSkyCatalogs ne contient pas de distance directement
                                'common_names' => $common_names_str,
                                'notes' => !empty($object['OpenNGC notes']) ? $object['OpenNGC notes'] : ''
                            ),
                            array('%s', '%d', '%s', '%s', '%f', '%f', '%f', '%s', '%f', '%s', '%s')
                        );
                        
                        if ($result !== false) {
                            $success_count++;
                            if ($success_count == 1) {
                                error_log("ANC: Premier objet importé avec succès: $designation");
                            }
                            if ($success_count % 1000 == 0) {
                                error_log("ANC: $success_count objets importés...");
                            }
                        } else {
                            $error_count++;
                            if ($error_count <= $max_errors) {
                                error_log("ANC: Erreur lors de l'insertion de l'objet: " . $designation . " (ligne $line_number)");
                                error_log("ANC: Erreur WordPress: " . $wpdb->last_error);
                            }
                        }
                        
                    } catch (Exception $e) {
                        $error_count++;
                        if ($error_count <= $max_errors) {
                            error_log("ANC: Exception lors du traitement de l'objet ligne $line_number: " . $e->getMessage());
                        }
                    }
                }
            } else {
                if ($error_count <= $max_errors) {
                    error_log("ANC: Ligne $line_number mal formée (" . count($row) . " colonnes vs " . count($header) . " attendues)");
                }
            }
        }
        
        fclose($handle);
        
        error_log("ANC: Import DeepSkyCatalogs terminé: {$success_count} objets importés, {$error_count} erreurs");
        return $success_count > 0;
    }
    
    // Fonction utilitaire pour convertir RA au format HH:MM:SS.SS en heures décimales
    public static function parse_ra_to_decimal($ra_string) {
        if (preg_match('/^(\d{1,2}):(\d{2}):(\d{2}\.?\d*)$/', $ra_string, $matches)) {
            $hours = intval($matches[1]);
            $minutes = intval($matches[2]);
            $seconds = floatval($matches[3]);
            return $hours + ($minutes / 60) + ($seconds / 3600);
        }
        return 0.0;
    }
    
    // Fonction utilitaire pour convertir Dec au format ±DD:MM:SS.S en degrés décimaux
    public static function parse_dec_to_decimal($dec_string) {
        if (preg_match('/^([+-]?)(\d{1,2}):(\d{2}):(\d{2}\.?\d*)$/', $dec_string, $matches)) {
            $sign = ($matches[1] === '-') ? -1 : 1;
            $degrees = intval($matches[2]);
            $minutes = intval($matches[3]);
            $seconds = floatval($matches[4]);
            return $sign * ($degrees + ($minutes / 60) + ($seconds / 3600));
        }
        return 0.0;
    }
    
    public static function get_all_catalogs() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}astro_catalogs ORDER BY name ASC"
        );
    }
    
    public static function get_catalog_stats() {
        global $wpdb;
        
        return $wpdb->get_results("
            SELECT c.id, c.name, c.code, c.description, c.total_objects,
                   COUNT(o.id) as actual_objects,
                   COUNT(i.id) as images_count
            FROM {$wpdb->prefix}astro_catalogs c
            LEFT JOIN {$wpdb->prefix}astro_objects o ON c.id = o.catalog_id
            LEFT JOIN {$wpdb->prefix}astro_images i ON o.id = i.object_id AND i.status = 'published'
            GROUP BY c.id
            ORDER BY c.name ASC
        ");
    }
    
    public static function get_catalog_objects($catalog_id, $limit = 50) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}astro_objects 
             WHERE catalog_id = %d 
             ORDER BY designation ASC 
             LIMIT %d",
            $catalog_id,
            $limit
        ));
    }
    
    public static function get_objects_by_catalog($catalog_name, $limit = 100) {
        global $wpdb;
        
        // Convertir le nom de catalogue en code pour la recherche
        $catalog_code = strtoupper($catalog_name);
        
        // Vérifier si les tables existent
        $catalog_table = $wpdb->prefix . 'astro_catalogs';
        $objects_table = $wpdb->prefix . 'astro_objects';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$objects_table'") != $objects_table) {
            return array();
        }
        
        // Récupérer les colonnes de la table objects
        $object_columns = $wpdb->get_col("SHOW COLUMNS FROM $objects_table");
        
        // Si pas de table catalogs, essayer la structure ancienne directement
        if ($wpdb->get_var("SHOW TABLES LIKE '$catalog_table'") != $catalog_table) {
            if (in_array('catalog_name', $object_columns)) {
                // Structure ancienne avec catalog_name
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT *, catalog_name, catalog_name as catalog_code 
                     FROM {$wpdb->prefix}astro_objects 
                     WHERE LOWER(catalog_name) = %s 
                     ORDER BY designation ASC 
                     LIMIT %d",
                    strtolower($catalog_name),
                    $limit
                ));
            } else {
                return array();
            }
        }
        
        // Les deux tables existent, vérifier les colonnes
        $catalog_columns = $wpdb->get_col("SHOW COLUMNS FROM $catalog_table");
        
        // Essayer de trouver le catalogue avec les colonnes disponibles
        $catalog = null;
        
        if (in_array('code', $catalog_columns) && in_array('name', $catalog_columns)) {
            // Structure complète
            $catalog = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE UPPER(code) = %s OR UPPER(name) LIKE %s",
                $catalog_code,
                '%' . $wpdb->esc_like($catalog_code) . '%'
            ));
        } elseif (in_array('name', $catalog_columns)) {
            // Seulement name
            $catalog = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE UPPER(name) LIKE %s",
                '%' . $wpdb->esc_like($catalog_code) . '%'
            ));
        } elseif (in_array('code', $catalog_columns)) {
            // Seulement code
            $catalog = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE UPPER(code) = %s",
                $catalog_code
            ));
        }
        
        if (!$catalog) {
            return array();
        }
        
        // Construire la requête pour les objets selon les colonnes disponibles
        if (in_array('catalog_id', $object_columns)) {
            // Structure avec catalog_id
            if (in_array('code', $catalog_columns) && in_array('name', $catalog_columns)) {
                // Structure complète
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT o.*, c.name as catalog_name, c.code as catalog_code 
                     FROM {$wpdb->prefix}astro_objects o
                     JOIN {$wpdb->prefix}astro_catalogs c ON o.catalog_id = c.id
                     WHERE o.catalog_id = %d 
                     ORDER BY o.designation ASC 
                     LIMIT %d",
                    $catalog->id,
                    $limit
                ));
            } elseif (in_array('name', $catalog_columns)) {
                // Avec name seulement
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT o.*, c.name as catalog_name, c.name as catalog_code 
                     FROM {$wpdb->prefix}astro_objects o
                     JOIN {$wpdb->prefix}astro_catalogs c ON o.catalog_id = c.id
                     WHERE o.catalog_id = %d 
                     ORDER BY o.designation ASC 
                     LIMIT %d",
                    $catalog->id,
                    $limit
                ));
            } else {
                // Structure minimale
                return $wpdb->get_results($wpdb->prepare(
                    "SELECT o.*, %s as catalog_name, %s as catalog_code 
                     FROM {$wpdb->prefix}astro_objects o
                     WHERE o.catalog_id = %d 
                     ORDER BY o.designation ASC 
                     LIMIT %d",
                    $catalog_name,
                    $catalog_name,
                    $catalog->id,
                    $limit
                ));
            }
        } elseif (in_array('catalog_name', $object_columns)) {
            // Structure ancienne avec catalog_name
            return $wpdb->get_results($wpdb->prepare(
                "SELECT *, catalog_name, catalog_name as catalog_code 
                 FROM {$wpdb->prefix}astro_objects 
                 WHERE LOWER(catalog_name) = %s 
                 ORDER BY designation ASC 
                 LIMIT %d",
                strtolower($catalog_name),
                $limit
            ));
        }
        
        return array();
    }
    
    public static function search_objects($search_term, $limit = 20) {
        global $wpdb;
        
        $search = '%' . $wpdb->esc_like($search_term) . '%';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT o.*, c.name as catalog_name, c.code as catalog_code 
             FROM {$wpdb->prefix}astro_objects o
             JOIN {$wpdb->prefix}astro_catalogs c ON o.catalog_id = c.id
             WHERE o.designation LIKE %s 
                OR o.common_names LIKE %s 
                OR o.ngc_number LIKE %s 
                OR o.ic_number LIKE %s 
                OR o.messier_number LIKE %s
             ORDER BY 
                CASE 
                    WHEN o.messier_number LIKE %s THEN 1
                    WHEN o.ngc_number LIKE %s THEN 2
                    WHEN o.ic_number LIKE %s THEN 3
                    ELSE 4
                END,
                o.designation ASC
             LIMIT %d",
            $search, $search, $search, $search, $search,
            $search, $search, $search,
            $limit
        ));
    }
    
    public static function import_caldwell_sample() {
        global $wpdb;
        
        $catalog = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = 'CAL'");
        if (!$catalog) return false;
        
        // Sélection des 40 objets Caldwell les plus populaires
        $caldwell_objects = array(
            array('C1', 'NGC 188', 'Open Cluster', 'Cepheus', 0.7833, 85.2500, 8.1, '14', 5400),
            array('C2', 'NGC 40', 'Planetary Nebula', 'Cepheus', 0.2208, 72.5317, 10.7, '0.6', 3500),
            array('C3', 'NGC 4236', 'Galaxy', 'Draco', 12.2833, 69.4667, 9.7, '19×7', 11000000),
            array('C4', 'NGC 7023', 'Nébuleuse de l\'Iris', 'Reflection Nebula', 'Cepheus', 21.0167, 68.1667, 6.8, '18×18', 1300),
            array('C5', 'IC 342', 'Galaxie Cachée', 'Galaxy', 'Camelopardalis', 3.7833, 68.1000, 8.4, '18×17', 10000000),
            array('C6', 'NGC 6543', 'Nébuleuse de l\'Œil de Chat', 'Planetary Nebula', 'Draco', 17.9583, 66.6333, 8.1, '0.3', 3300),
            array('C7', 'NGC 2403', 'Galaxie', 'Camelopardalis', 7.6167, 65.6000, 8.5, '18×11', 11000000),
            array('C8', 'NGC 559', 'Amas', 'Open Cluster', 'Cassiopeia', 1.4917, 63.3000, 9.5, '4', 3700),
            array('C9', 'Sh2-155', 'Nébuleuse de la Caverne', 'Emission Nebula', 'Cepheus', 22.9500, 62.6167, 7.7, '50×30', 2400),
            array('C10', 'NGC 663', 'Amas', 'Open Cluster', 'Cassiopeia', 1.7667, 61.2500, 7.1, '16', 7200),
            array('C11', 'NGC 7635', 'Nébuleuse de la Bulle', 'Emission Nebula', 'Cassiopeia', 23.3417, 61.2167, 10.0, '15×8', 7100),
            array('C12', 'NGC 6946', 'Galaxie du Feu d\'Artifice', 'Galaxy', 'Cepheus', 20.5833, 60.1533, 8.9, '11×10', 10000000),
            array('C13', 'NGC 457', 'Amas ET', 'Open Cluster', 'Cassiopeia', 1.3292, 58.2833, 6.4, '13', 7900),
            array('C14', 'NGC 869', 'Amas Double h Persei', 'Open Cluster', 'Perseus', 2.3292, 57.1333, 5.3, '30', 7600),
            array('C15', 'NGC 6826', 'Nébuleuse Clignotante', 'Planetary Nebula', 'Cygnus', 19.7458, 50.5258, 8.8, '0.4', 2200),
            array('C16', 'NGC 7243', 'Amas', 'Open Cluster', 'Lacerta', 22.2500, 49.8833, 6.4, '21', 2800),
            array('C17', 'NGC 147', 'Galaxie', 'Cassiopeia', 0.5583, 48.5167, 9.3, '13×8', 2380000),
            array('C18', 'NGC 185', 'Galaxie', 'Cassiopeia', 0.6500, 48.3333, 9.2, '12×10', 2050000),
            array('C19', 'IC 5146', 'Nébuleuse du Cocon', 'Emission Nebula', 'Cygnus', 21.8833, 47.2667, 10.0, '12×3', 4000),
            array('C20', 'NGC 7000', 'Nébuleuse de l\'Amérique du Nord', 'Emission Nebula', 'Cygnus', 21.0167, 44.3333, 4.0, '120×100', 2590),
            array('C21', 'NGC 4449', 'Galaxie', 'Canes Venatici', 12.4750, 44.0833, 9.4, '5×4', 12500000),
            array('C22', 'NGC 7662', 'Nébuleuse Boule de Neige Bleue', 'Planetary Nebula', 'Andromeda', 23.4292, 42.5500, 8.3, '0.5', 5600),
            array('C23', 'NGC 891', 'Galaxie du Couteau d\'Argent', 'Galaxy', 'Andromeda', 2.3750, 42.3500, 9.9, '14×3', 27300000),
            array('C24', 'NGC 1275', 'Galaxie de Persée A', 'Galaxy', 'Perseus', 3.3167, 41.5167, 11.6, '2×2', 237000000),
            array('C25', 'NGC 2419', 'Amas Globulaire Intergalactique', 'Globular Cluster', 'Lynx', 7.6333, 38.8833, 10.4, '6', 300000),
            array('C26', 'NGC 4244', 'Galaxie du Couteau d\'Argent', 'Galaxy', 'Canes Venatici', 12.2917, 37.8167, 10.2, '16×2', 14100000),
            array('C27', 'NGC 6888', 'Nébuleuse du Croissant', 'Emission Nebula', 'Cygnus', 20.2000, 38.3500, 7.4, '20×10', 5000),
            array('C28', 'NGC 752', 'Amas', 'Open Cluster', 'Andromeda', 1.9500, 37.6833, 5.7, '50', 1300),
            array('C29', 'NGC 5005', 'Galaxie', 'Canes Venatici', 13.1833, 37.0667, 9.8, '5×3', 69000000),
            array('C30', 'NGC 7331', 'Galaxie', 'Pegasus', 22.6167, 34.4167, 9.5, '10×4', 46000000),
            array('C31', 'IC 405', 'Nébuleuse de l\'Étoile Flamboyante', 'Emission Nebula', 'Auriga', 5.2667, 34.2667, 6.0, '30×19', 1500),
            array('C32', 'NGC 4631', 'Galaxie de la Baleine', 'Galaxy', 'Canes Venatici', 12.7000, 32.5333, 9.2, '15×3', 25000000),
            array('C33', 'NGC 6992', 'Nébuleuse du Voile Est', 'Supernova Remnant', 'Cygnus', 20.9417, 31.7167, 7.0, '78×8', 2100),
            array('C34', 'NGC 6960', 'Nébuleuse du Voile Ouest', 'Supernova Remnant', 'Cygnus', 20.7583, 30.7167, 7.0, '70×6', 2100),
            array('C35', 'NGC 4889', 'Galaxie', 'Coma Berenices', 13.0000, 27.9667, 11.4, '3×2', 308000000),
            array('C36', 'NGC 4559', 'Galaxie', 'Coma Berenices', 12.5833, 27.9667, 9.8, '11×4', 29000000),
            array('C37', 'NGC 6885', 'Amas', 'Open Cluster', 'Vulpecula', 20.2000, 26.5000, 5.7, '7', 1950),
            array('C38', 'NGC 4565', 'Galaxie de l\'Aiguille', 'Galaxy', 'Coma Berenices', 12.6083, 25.9833, 9.6, '16×2', 31000000),
            array('C39', 'NGC 2392', 'Nébuleuse du Clown', 'Planetary Nebula', 'Gemini', 7.4875, 20.9158, 9.2, '0.7', 4200),
            array('C40', 'NGC 3626', 'Galaxie', 'Leo', 11.3333, 18.3500, 10.9, '3×2', 70000000)
        );
        
        foreach ($caldwell_objects as $obj) {
            // Extraire les références NGC/IC du deuxième champ
            $ngc_ref = null;
            $ic_ref = null;
            if (!empty($obj[1])) {
                if (strpos($obj[1], 'NGC') === 0) {
                    $ngc_ref = $obj[1];
                } elseif (strpos($obj[1], 'IC') === 0) {
                    $ic_ref = $obj[1];
                }
            }
            
            $wpdb->replace(
                $wpdb->prefix . 'astro_objects',
                array(
                    'designation' => $obj[0],
                    'catalog_id' => $catalog->id,
                    'object_type' => $obj[3],
                    'constellation' => $obj[4],
                    'ra_hours' => $obj[5],
                    'dec_degrees' => $obj[6],
                    'magnitude' => $obj[7],
                    'size' => $obj[8],
                    'distance' => $obj[9],
                    'common_names' => !empty($obj[2]) ? $obj[1] . ' - ' . $obj[2] : $obj[1],
                    'caldwell_number' => $obj[0], // Référence Caldwell
                    'ngc_number' => $ngc_ref, // Référence NGC si elle existe
                    'ic_number' => $ic_ref // Référence IC si elle existe
                )
            );
        }
        
        return true;
    }
    
    public static function import_sharpless_sample() {
        global $wpdb;
        
        $catalog = $wpdb->get_row("SELECT id FROM {$wpdb->prefix}astro_catalogs WHERE code = 'SH'");
        if (!$catalog) return false;
        
        // Sélection des 30 régions HII Sharpless les plus populaires
        $sharpless_objects = array(
            array('Sh2-101', 'Nébuleuse de la Tulipe', 'Emission Nebula', 'Cygnus', 20.0167, 35.5000, 9.0, '30×13', 8000),
            array('Sh2-115', 'Nébuleuse de l\'Embryon', 'Emission Nebula', 'Cygnus', 21.0000, 50.0000, 7.0, '30×20', 1800),
            array('Sh2-119', 'Nébuleuse', 'Emission Nebula', 'Cygnus', 21.3333, 46.3333, 8.5, '15×10', 5200),
            array('Sh2-132', 'Nébuleuse du Lion', 'Emission Nebula', 'Cepheus', 22.3167, 56.1667, 10.0, '30×25', 10000),
            array('Sh2-155', 'Nébuleuse de la Caverne', 'Emission Nebula', 'Cepheus', 22.9500, 62.6167, 7.7, '50×30', 2400),
            array('Sh2-157', 'Nébuleuse du Homard', 'Emission Nebula', 'Cassiopeia', 23.2667, 60.0000, 8.5, '25×15', 8200),
            array('Sh2-171', 'Nébuleuse', 'Emission Nebula', 'Cassiopeia', 23.5000, 58.5000, 7.0, '20×15', 4300),
            array('Sh2-188', 'Nébuleuse', 'Emission Nebula', 'Cassiopeia', 0.1667, 58.2000, 9.0, '8×6', 1800),
            array('Sh2-202', 'Nébuleuse', 'Emission Nebula', 'Cassiopeia', 0.6000, 65.5000, 8.0, '12×8', 3200),
            array('Sh2-205', 'Nébuleuse', 'Emission Nebula', 'Cassiopeia', 0.8333, 67.8333, 7.5, '18×12', 2800),
            array('Sh2-216', 'Nébuleuse', 'Emission Nebula', 'Perseus', 1.5000, 51.0000, 10.0, '40×30', 4200),
            array('Sh2-220', 'Nébuleuse', 'Emission Nebula', 'Perseus', 2.0000, 56.5000, 8.5, '25×20', 6800),
            array('Sh2-235', 'Nébuleuse', 'Emission Nebula', 'Auriga', 5.6667, 35.5000, 7.8, '35×25', 7800),
            array('Sh2-240', 'Nébuleuse Simeis 147', 'Supernova Remnant', 'Taurus', 5.6667, 27.5000, 7.0, '180×180', 3000),
            array('Sh2-261', 'Nébuleuse du Petit Fantôme', 'Emission Nebula', 'Orion', 6.1667, 15.8333, 8.0, '10×8', 5800),
            array('Sh2-264', 'Nébuleuse de la Rosette', 'Emission Nebula', 'Monoceros', 6.5417, 5.0333, 6.0, '80×60', 5200),
            array('Sh2-276', 'Nébuleuse de Barnard\'s Loop', 'Emission Nebula', 'Orion', 5.7500, -1.0000, 5.0, '600×300', 1600),
            array('Sh2-284', 'Nébuleuse', 'Emission Nebula', 'Monoceros', 6.7833, 0.3333, 8.5, '20×15', 4500),
            array('Sh2-296', 'Nébuleuse de la Mouette', 'Emission Nebula', 'Monoceros', 7.0833, -10.7500, 12.0, '120×40', 3650),
            array('Sh2-308', 'Nébuleuse du Dauphin', 'Emission Nebula', 'Canis Major', 7.2000, -20.8333, 11.0, '60×55', 5200),
            array('Sh2-311', 'Nébuleuse', 'Emission Nebula', 'Puppis', 7.2833, -26.5000, 9.5, '30×25', 4100),
            array('Sh2-308', 'Nébuleuse', 'Emission Nebula', 'Canis Major', 7.3333, -23.9333, 10.5, '48×47', 5200),
            array('Sh2-54', 'Nébuleuse de l\'Aigle', 'Emission Nebula', 'Serpens', 18.3167, -13.8167, 6.4, '35×28', 7000),
            array('Sh2-68', 'Nébuleuse', 'Emission Nebula', 'Sagittarius', 18.8333, -29.0000, 8.0, '25×20', 5200),
            array('Sh2-86', 'Nébuleuse', 'Emission Nebula', 'Sagittarius', 19.1667, -6.8333, 7.5, '15×12', 4800),
            array('Sh2-101', 'Nébuleuse de la Tulipe', 'Emission Nebula', 'Cygnus', 20.0167, 35.5000, 9.0, '30×13', 8000),
            array('Sh2-106', 'Nébuleuse', 'Emission Nebula', 'Cygnus', 20.4500, 37.3333, 8.2, '2×1', 2000),
            array('Sh2-114', 'Nébuleuse de l\'Aigle', 'Emission Nebula', 'Cygnus', 20.8333, 44.3333, 7.0, '20×15', 6100),
            array('Sh2-129', 'Nébuleuse', 'Emission Nebula', 'Cepheus', 21.2000, 60.1667, 9.0, '10×8', 3200),
            array('Sh2-134', 'Nébuleuse', 'Emission Nebula', 'Cepheus', 22.2167, 56.1167, 8.5, '8×6', 4800)
        );
        
        foreach ($sharpless_objects as $obj) {
            $wpdb->replace(
                $wpdb->prefix . 'astro_objects',
                array(
                    'designation' => $obj[0],
                    'catalog_id' => $catalog->id,
                    'object_type' => $obj[2],
                    'constellation' => $obj[3],
                    'ra_hours' => $obj[4],
                    'dec_degrees' => $obj[5],
                    'magnitude' => $obj[6],
                    'size' => $obj[7],
                    'distance' => $obj[8],
                    'common_names' => !empty($obj[1]) ? $obj[1] : $obj[0],
                    'sharpless_number' => $obj[0] // Référence Sharpless
                )
            );
        }
        
        return true;
    }

    /**
     * Trouve tous les objets liés par leurs différentes désignations
     */
    public static function find_cross_references($designation) {
        global $wpdb;
        
        // Chercher l'objet par toutes ses désignations possibles
        $object = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}astro_objects 
            WHERE designation = %s 
               OR messier_number = %s 
               OR ngc_number = %s 
               OR ic_number = %s 
               OR caldwell_number = %s 
               OR sharpless_number = %s
               OR common_names LIKE %s
            LIMIT 1
        ", $designation, $designation, $designation, $designation, $designation, $designation, "%$designation%"));
        
        if (!$object) return array();
        
        // Trouver tous les objets qui partagent des désignations avec celui-ci
        $cross_refs = $wpdb->get_results($wpdb->prepare("
            SELECT o.*, c.name as catalog_name, c.code as catalog_code
            FROM {$wpdb->prefix}astro_objects o
            JOIN {$wpdb->prefix}astro_catalogs c ON o.catalog_id = c.id
            WHERE o.id != %d AND (
                (o.messier_number IS NOT NULL AND o.messier_number = %s)
                OR (o.ngc_number IS NOT NULL AND o.ngc_number = %s)
                OR (o.ic_number IS NOT NULL AND o.ic_number = %s)
                OR (o.caldwell_number IS NOT NULL AND o.caldwell_number = %s)
                OR (o.sharpless_number IS NOT NULL AND o.sharpless_number = %s)
                OR (o.messier_number IS NOT NULL AND %s IS NOT NULL AND o.messier_number = %s)
                OR (o.ngc_number IS NOT NULL AND %s IS NOT NULL AND o.ngc_number = %s)
                OR (o.ic_number IS NOT NULL AND %s IS NOT NULL AND o.ic_number = %s)
            )
            ORDER BY c.name ASC
        ", 
            $object->id,
            $object->messier_number, $object->ngc_number, $object->ic_number, $object->caldwell_number, $object->sharpless_number,
            $object->messier_number, $object->messier_number,
            $object->ngc_number, $object->ngc_number,
            $object->ic_number, $object->ic_number
        ));
        
        return array(
            'main_object' => $object,
            'cross_references' => $cross_refs
        );
    }
    
    /**
     * Récupère un objet avec toutes ses désignations alternatives
     */
    public static function get_object_with_all_designations($object_id) {
        global $wpdb;
        
        $object = $wpdb->get_row($wpdb->prepare("
            SELECT o.*, c.name as catalog_name, c.code as catalog_code
            FROM {$wpdb->prefix}astro_objects o
            JOIN {$wpdb->prefix}astro_catalogs c ON o.catalog_id = c.id
            WHERE o.id = %d
        ", $object_id));
        
        if (!$object) return null;
        
        // Construire un tableau de toutes les désignations
        $designations = array();
        
        if (!empty($object->designation)) $designations[] = $object->designation;
        if (!empty($object->messier_number)) $designations[] = $object->messier_number;
        if (!empty($object->ngc_number)) $designations[] = $object->ngc_number;
        if (!empty($object->ic_number)) $designations[] = $object->ic_number;
        if (!empty($object->caldwell_number)) $designations[] = $object->caldwell_number;
        if (!empty($object->sharpless_number)) $designations[] = $object->sharpless_number;
        
        $object->all_designations = $designations;
        
        // Trouver les références croisées
        $cross_refs_data = self::find_cross_references($object->designation);
        $object->cross_references = $cross_refs_data['cross_references'] ?? array();
        
        return $object;
    }
}