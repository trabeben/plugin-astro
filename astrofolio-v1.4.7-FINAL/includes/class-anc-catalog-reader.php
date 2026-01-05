<?php
/**
 * Lecture directe des catalogues CSV (sans import)
 */
class ANC_Catalog_Reader {
    
    private static $csv_file = null;
    
    /**
     * Rechercher des objets directement dans le CSV
     */
    public static function search_objects($search_term, $limit = 50) {
        $csv_file = self::get_csv_file();
        
        if (!file_exists($csv_file)) {
            return array();
        }
        
        $results = array();
        $search_term = strtolower(trim($search_term));
        
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            // Ignorer l'en-tête
            fgetcsv($handle, 1000, ';');
            
            $count = 0;
            while (($data = fgetcsv($handle, 1000, ';')) !== FALSE && $count < $limit) {
                if (count($data) >= 4) {
                    $designation = strtolower($data[0] ?? '');
                    $name = strtolower($data[1] ?? '');
                    
                    // Recherche dans designation ou nom
                    if (strpos($designation, $search_term) !== false || 
                        strpos($name, $search_term) !== false) {
                        
                        $results[] = array(
                            'designation' => $data[0],
                            'name' => $data[1],
                            'type' => $data[2],
                            'constellation' => $data[3],
                            'ra' => $data[4] ?? '',
                            'dec' => $data[5] ?? '',
                            'magnitude' => $data[6] ?? '',
                            'size' => $data[7] ?? ''
                        );
                        $count++;
                    }
                }
            }
            fclose($handle);
        }
        
        return $results;
    }
    
    /**
     * Obtenir un objet par designation exacte
     */
    public static function get_object($designation) {
        $csv_file = self::get_csv_file();
        
        if (!file_exists($csv_file)) {
            return null;
        }
        
        $designation = trim($designation);
        
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            // Ignorer l'en-tête
            fgetcsv($handle, 1000, ';');
            
            while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
                if (count($data) >= 4 && trim($data[0]) === $designation) {
                    fclose($handle);
                    return array(
                        'designation' => $data[0],
                        'name' => $data[1],
                        'type' => $data[2],
                        'constellation' => $data[3],
                        'ra' => $data[4] ?? '',
                        'dec' => $data[5] ?? '',
                        'magnitude' => $data[6] ?? '',
                        'size' => $data[7] ?? ''
                    );
                }
            }
            fclose($handle);
        }
        
        return null;
    }
    
    /**
     * Obtenir les statistiques du catalogue
     */
    public static function get_stats() {
        $csv_file = self::get_csv_file();
        
        if (!file_exists($csv_file)) {
            return array(
                'total' => 0,
                'file_exists' => false,
                'file_size' => 0
            );
        }
        
        $line_count = 0;
        if (($handle = fopen($csv_file, 'r')) !== FALSE) {
            // Compter les lignes (sans l'en-tête)
            fgetcsv($handle, 1000, ';'); // ignorer en-tête
            while (fgetcsv($handle, 1000, ';') !== FALSE) {
                $line_count++;
            }
            fclose($handle);
        }
        
        return array(
            'total' => $line_count,
            'file_exists' => true,
            'file_size' => filesize($csv_file),
            'file_path' => $csv_file
        );
    }
    
    /**
     * Obtenir le chemin du fichier CSV
     */
    private static function get_csv_file() {
        if (self::$csv_file === null) {
            $plugin_dir = plugin_dir_path(dirname(__FILE__));
            self::$csv_file = $plugin_dir . 'data/NGC copie.csv';
        }
        return self::$csv_file;
    }
}