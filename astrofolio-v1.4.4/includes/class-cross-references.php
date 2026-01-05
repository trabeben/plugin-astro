<?php
/**
 * Widget pour afficher les références croisées d'un objet
 */
class Astro_Cross_References_Widget {
    
    /**
     * Affiche les références croisées pour un objet donné
     */
    public static function display_cross_references($object_designation) {
        $cross_refs_data = Astro_Catalogs::find_cross_references($object_designation);
        
        if (empty($cross_refs_data) || empty($cross_refs_data['cross_references'])) {
            return;
        }
        
        $main_object = $cross_refs_data['main_object'];
        $cross_refs = $cross_refs_data['cross_references'];
        
        ?>
        <div class="astro-cross-references-widget">
            <h3>🔗 Références croisées</h3>
            
            <div class="object-main-info">
                <h4><?php echo esc_html($main_object->designation); ?></h4>
                <?php if (!empty($main_object->common_names)): ?>
                    <p class="common-name"><?php echo esc_html($main_object->common_names); ?></p>
                <?php endif; ?>
                
                <div class="all-designations">
                    <strong>Toutes les désignations :</strong>
                    <div class="designation-tags">
                        <?php 
                        $designations = array();
                        if (!empty($main_object->messier_number)) $designations[] = $main_object->messier_number;
                        if (!empty($main_object->ngc_number)) $designations[] = $main_object->ngc_number;
                        if (!empty($main_object->ic_number)) $designations[] = $main_object->ic_number;
                        if (!empty($main_object->caldwell_number)) $designations[] = $main_object->caldwell_number;
                        if (!empty($main_object->sharpless_number)) $designations[] = $main_object->sharpless_number;
                        
                        foreach (array_filter($designations) as $designation): ?>
                            <span class="designation-tag"><?php echo esc_html($designation); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($cross_refs)): ?>
                <div class="cross-references-list">
                    <h5>Aussi présent dans :</h5>
                    <?php foreach ($cross_refs as $ref): ?>
                        <div class="cross-ref-item">
                            <span class="catalog-badge" title="<?php echo esc_attr($ref->catalog_name); ?>">
                                <?php echo esc_html($ref->catalog_code); ?>
                            </span>
                            <span class="object-designation"><?php echo esc_html($ref->designation); ?></span>
                            <?php if (!empty($ref->common_names) && $ref->common_names != $main_object->common_names): ?>
                                <span class="alt-name">(<?php echo esc_html($ref->common_names); ?>)</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .astro-cross-references-widget {
            background: #f8f9fa;
            border-left: 4px solid #0073aa;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .astro-cross-references-widget h3 {
            margin-top: 0;
            color: #0073aa;
            font-size: 18px;
        }
        .object-main-info h4 {
            margin: 0 0 5px 0;
            font-size: 16px;
        }
        .common-name {
            color: #666;
            font-style: italic;
            margin: 0 0 10px 0;
        }
        .designation-tags {
            margin-top: 5px;
        }
        .designation-tag {
            display: inline-block;
            background: #0073aa;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
            margin-right: 5px;
            margin-bottom: 3px;
        }
        .cross-references-list {
            margin-top: 15px;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        .cross-references-list h5 {
            margin: 0 0 8px 0;
            color: #555;
            font-size: 13px;
            text-transform: uppercase;
        }
        .cross-ref-item {
            margin: 5px 0;
            padding: 5px 0;
        }
        .catalog-badge {
            display: inline-block;
            background: #28a745;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
            margin-right: 8px;
        }
        .object-designation {
            font-weight: bold;
            color: #333;
        }
        .alt-name {
            color: #666;
            font-size: 12px;
            margin-left: 5px;
        }
        </style>
        <?php
    }
    
    /**
     * Shortcode pour afficher les références croisées
     * Utilisation: [astro_cross_refs object="M31"]
     */
    public static function cross_references_shortcode($atts) {
        $atts = shortcode_atts(array(
            'object' => '',
        ), $atts);
        
        if (empty($atts['object'])) {
            return '<p><em>Aucun objet spécifié pour les références croisées</em></p>';
        }
        
        ob_start();
        self::display_cross_references($atts['object']);
        return ob_get_clean();
    }
}

// Enregistrer le shortcode
add_shortcode('astro_cross_refs', array('Astro_Cross_References_Widget', 'cross_references_shortcode'));
?>