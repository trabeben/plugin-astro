<?php
/**
 * Formulaire avancé de métadonnées d'astrophotographie
 * Interface complète style AstroBin/Telescopius
 */
class ANC_Image_Metadata_Form {
    
    /**
     * Afficher le formulaire complet de métadonnées
     */
    public static function display_form($image_id = null) {
        $meta = array();
        if ($image_id) {
            $meta = ANC_Image_Metadata::get_metadata($image_id) ?: array();
        }
        
        ?>
        <div class="astro-metadata-form">
            <form id="astro-metadata-form" method="post">
                <?php wp_nonce_field('save_astro_metadata', 'astro_metadata_nonce'); ?>
                <input type="hidden" name="image_id" value="<?php echo esc_attr($image_id); ?>">
                
                <!-- === ONGLETS === -->
                <div class="nav-tab-wrapper">
                    <a href="#tab-equipment" class="nav-tab nav-tab-active">🔭 Équipement</a>
                    <a href="#tab-acquisition" class="nav-tab">📷 Acquisition</a>
                    <a href="#tab-processing" class="nav-tab">⚙️ Traitement</a>
                    <a href="#tab-conditions" class="nav-tab">🌤️ Conditions</a>
                    <a href="#tab-advanced" class="nav-tab">🚀 Avancé</a>
                </div>
                
                <!-- === ÉQUIPEMENT === -->
                <div id="tab-equipment" class="tab-content">
                    <table class="form-table">
                        <tr>
                            <th><label>🔭 Télescope</label></th>
                            <td>
                                <input type="text" name="telescope_brand" placeholder="Marque (Celestron, Sky-Watcher...)" 
                                       value="<?php echo esc_attr($meta['telescope_brand'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="text" name="telescope_model" placeholder="Modèle (EdgeHD 800, Newton 200/1000...)" 
                                       value="<?php echo esc_attr($meta['telescope_model'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="number" name="telescope_aperture" placeholder="Diamètre (mm)" 
                                       value="<?php echo esc_attr($meta['telescope_aperture'] ?? ''); ?>" class="small-text">
                                <input type="number" name="telescope_focal_length" placeholder="Focale (mm)" 
                                       value="<?php echo esc_attr($meta['telescope_focal_length'] ?? ''); ?>" class="small-text">
                                <input type="text" name="telescope_focal_ratio" placeholder="f/ratio (f/5.6)" 
                                       value="<?php echo esc_attr($meta['telescope_focal_ratio'] ?? ''); ?>" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>⚙️ Monture</label></th>
                            <td>
                                <input type="text" name="mount_brand" placeholder="Marque (Skywatcher, iOptron...)" 
                                       value="<?php echo esc_attr($meta['mount_brand'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="text" name="mount_model" placeholder="Modèle (HEQ5, CEM25P...)" 
                                       value="<?php echo esc_attr($meta['mount_model'] ?? ''); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>📸 Caméra</label></th>
                            <td>
                                <input type="text" name="camera_brand" placeholder="Marque (ZWO, QHY, Canon...)" 
                                       value="<?php echo esc_attr($meta['camera_brand'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="text" name="camera_model" placeholder="Modèle (ASI533MC, QHY294C...)" 
                                       value="<?php echo esc_attr($meta['camera_model'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="text" name="camera_sensor" placeholder="Capteur (Sony IMX571C...)" 
                                       value="<?php echo esc_attr($meta['camera_sensor'] ?? ''); ?>" class="regular-text">
                                <input type="text" name="camera_cooling" placeholder="Refroidissement (-10°C)" 
                                       value="<?php echo esc_attr($meta['camera_cooling'] ?? ''); ?>" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>🌈 Filtres</label></th>
                            <td>
                                <input type="text" name="filters" placeholder="L-RGB, Ha-OIII-SII, UV/IR Cut..." 
                                       value="<?php echo esc_attr($meta['filters'] ?? ''); ?>" class="large-text">
                                <p class="description">Séparez par des virgules. Ex: Ha, OIII, SII ou L, R, G, B</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>🔧 Accessoires</label></th>
                            <td>
                                <textarea name="accessories" placeholder="Réducteur 0.8x, correcteur de coma, OAG..."><?php echo esc_textarea($meta['accessories'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- === ACQUISITION === -->
                <div id="tab-acquisition" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th><label>🌟 Poses lumière</label></th>
                            <td>
                                <div class="field-group">
                                    <input type="number" name="lights_count" placeholder="Nombre" 
                                           value="<?php echo esc_attr($meta['lights_count'] ?? ''); ?>" class="small-text">
                                    <span>×</span>
                                    <input type="number" name="lights_exposure" placeholder="Temps (sec)" 
                                           value="<?php echo esc_attr($meta['lights_exposure'] ?? ''); ?>" class="small-text">
                                    <span>secondes</span>
                                </div>
                                <div class="field-group" style="margin-top: 12px;">
                                    <input type="text" name="lights_iso_gain" placeholder="ISO/Gain (ISO 800, Gain 139...)" 
                                           value="<?php echo esc_attr($meta['lights_iso_gain'] ?? ''); ?>" class="regular-text">
                                    <input type="text" name="lights_binning" placeholder="Binning (1x1, 2x2...)" 
                                           value="<?php echo esc_attr($meta['lights_binning'] ?? ''); ?>" class="small-text">
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label>📈 Détail par filtre</label></th>
                            <td>
                                <textarea name="filter_details" placeholder="Ha: 20×600s, OIII: 15×600s, SII: 18×600s..."><?php echo esc_textarea($meta['filter_details'] ?? ''); ?></textarea>
                                <p class="description">Détaillez les poses par filtre si applicable</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>🔧 Calibration</label></th>
                            <td>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                                    <div>
                                        <strong>Darks:</strong>
                                        <div class="field-group" style="margin-top: 5px;">
                                            <input type="number" name="darks_count" placeholder="Nombre" 
                                                   value="<?php echo esc_attr($meta['darks_count'] ?? ''); ?>" class="small-text">
                                            <span>×</span>
                                            <input type="number" name="darks_exposure" placeholder="Temps (sec)" 
                                                   value="<?php echo esc_attr($meta['darks_exposure'] ?? ''); ?>" class="small-text">
                                        </div>
                                    </div>
                                    <div>
                                        <strong>Flats & Bias:</strong>
                                        <div class="field-group" style="margin-top: 5px;">
                                            <input type="number" name="flats_count" placeholder="Flats" 
                                                   value="<?php echo esc_attr($meta['flats_count'] ?? ''); ?>" class="small-text">
                                            <input type="number" name="bias_count" placeholder="Bias" 
                                                   value="<?php echo esc_attr($meta['bias_count'] ?? ''); ?>" class="small-text">
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th><label>📅 Dates d'acquisition</label></th>
                            <td>
                                <input type="text" name="acquisition_dates" placeholder="2024-01-15, 2024-01-16..." 
                                       value="<?php echo esc_attr($meta['acquisition_dates'] ?? ''); ?>" class="large-text">
                                <p class="description">Séparez les dates multiples par des virgules</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- === TRAITEMENT === -->
                <div id="tab-processing" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th><label>💻 Logiciels</label></th>
                            <td>
                                <input type="text" name="stacking_software" placeholder="Empilement (PixInsight, DSS, APP...)" 
                                       value="<?php echo esc_attr($meta['stacking_software'] ?? ''); ?>" class="large-text">
                                <br><br>
                                <input type="text" name="processing_software" placeholder="Traitement (PixInsight, Photoshop...)" 
                                       value="<?php echo esc_attr($meta['processing_software'] ?? ''); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>🔄 Étapes principales</label></th>
                            <td>
                                <textarea name="processing_steps" rows="4" placeholder="DynamicBackgroundExtraction, HistogramTransformation, CurvesTransformation..."><?php echo esc_textarea($meta['processing_steps'] ?? ''); ?></textarea>
                                <p class="description">Décrivez les principales étapes de traitement</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Techniques spéciales</label></th>
                            <td>
                                <textarea name="special_techniques" rows="3" placeholder="HDR, Drizzle, Deconvolution, blend narrowband..."><?php echo esc_textarea($meta['special_techniques'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Notes de traitement</label></th>
                            <td>
                                <textarea name="processing_notes" rows="4" placeholder="Difficultés rencontrées, astuces utilisées..."><?php echo esc_textarea($meta['processing_notes'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- === CONDITIONS === -->
                <div id="tab-conditions" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th><label>🌍 Lieu d'observation</label></th>
                            <td>
                                <input type="text" name="location_name" placeholder="Nom du site (Observatoire du Pic du Midi...)" 
                                       value="<?php echo esc_attr($meta['location_name'] ?? ''); ?>" class="large-text">
                                <br><br>
                                <input type="text" name="location_coords" placeholder="Coordonnées (42.9365, 0.1425)" 
                                       value="<?php echo esc_attr($meta['location_coords'] ?? ''); ?>" class="regular-text">
                                <input type="number" name="location_altitude" placeholder="Altitude (m)" 
                                       value="<?php echo esc_attr($meta['location_altitude'] ?? ''); ?>" class="small-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>⭐ Qualité du ciel</label></th>
                            <td>
                                <select name="bortle_scale">
                                    <option value="">Échelle de Bortle</option>
                                    <?php for ($i = 1; $i <= 9; $i++): ?>
                                        <option value="<?php echo $i; ?>" <?php selected($meta['bortle_scale'] ?? '', $i); ?>>
                                            Classe <?php echo $i; ?> - <?php echo self::getBortleDescription($i); ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label>🌤️ Conditions météo</label></th>
                            <td>
                                <input type="text" name="weather_conditions" placeholder="Dégagé, transparence excellente..." 
                                       value="<?php echo esc_attr($meta['weather_conditions'] ?? ''); ?>" class="large-text">
                                <br><br>
                                <input type="text" name="temperature" placeholder="Température (-5°C)" 
                                       value="<?php echo esc_attr($meta['temperature'] ?? ''); ?>" class="small-text">
                                <input type="text" name="humidity" placeholder="Humidité (45%)" 
                                       value="<?php echo esc_attr($meta['humidity'] ?? ''); ?>" class="small-text">
                                <input type="text" name="wind_speed" placeholder="Vent (5 km/h)" 
                                       value="<?php echo esc_attr($meta['wind_speed'] ?? ''); ?>" class="small-text">
                                <br><br>
                                <input type="text" name="seeing" placeholder="Seeing (2 arcsec)" 
                                       value="<?php echo esc_attr($meta['seeing'] ?? ''); ?>" class="small-text">
                                <input type="text" name="moon_illumination" placeholder="Lune (15%)" 
                                       value="<?php echo esc_attr($meta['moon_illumination'] ?? ''); ?>" class="small-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- === AVANCÉ === -->
                <div id="tab-advanced" class="tab-content" style="display: none;">
                    <table class="form-table">
                        <tr>
                            <th><label>Guidage</label></th>
                            <td>
                                <input type="text" name="guiding_camera" placeholder="Caméra de guidage (ASI120MM...)" 
                                       value="<?php echo esc_attr($meta['guiding_camera'] ?? ''); ?>" class="large-text">
                                <br>
                                <input type="text" name="guiding_scope" placeholder="Lunette de guidage (50mm f/4)" 
                                       value="<?php echo esc_attr($meta['guiding_scope'] ?? ''); ?>" class="large-text">
                                <br>
                                <textarea name="autoguiding" placeholder="PHD2, réglages, performances..."><?php echo esc_textarea($meta['autoguiding'] ?? ''); ?></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Logiciel de capture</label></th>
                            <td>
                                <input type="text" name="capture_software" placeholder="NINA, SGP, MaxIm DL, ASCOM..." 
                                       value="<?php echo esc_attr($meta['capture_software'] ?? ''); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Techniques avancées</label></th>
                            <td>
                                <input type="text" name="dithering" placeholder="Dithering (toutes les 5 poses, 3 pixels)" 
                                       value="<?php echo esc_attr($meta['dithering'] ?? ''); ?>" class="large-text">
                                <br>
                                <input type="text" name="focusing" placeholder="Mise au point (autofocus, masque de Bahtinov)" 
                                       value="<?php echo esc_attr($meta['focusing'] ?? ''); ?>" class="large-text">
                                <br>
                                <input type="text" name="plate_solving" placeholder="Résolution de champ (ASTAP, ANSVR)" 
                                       value="<?php echo esc_attr($meta['plate_solving'] ?? ''); ?>" class="large-text">
                            </td>
                        </tr>
                        <tr>
                            <th><label>Résultat final</label></th>
                            <td>
                                <input type="text" name="final_resolution" placeholder="Résolution (4096×4096)" 
                                       value="<?php echo esc_attr($meta['final_resolution'] ?? ''); ?>" class="regular-text">
                                <input type="text" name="field_of_view" placeholder="Champ (2.1° × 1.4°)" 
                                       value="<?php echo esc_attr($meta['field_of_view'] ?? ''); ?>" class="regular-text">
                                <br>
                                <input type="number" step="0.01" name="pixel_scale" placeholder="Échelle (arcsec/pixel)" 
                                       value="<?php echo esc_attr($meta['pixel_scale'] ?? ''); ?>" class="small-text">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="save_metadata" class="button-primary" value="💾 Enregistrer les métadonnées">
                </p>
            </form>
        </div>
        
        <style>
        /* === CONTENEUR PRINCIPAL === */
        .astro-metadata-form {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 16px;
            padding: 30px;
            margin: 20px 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        /* === NAVIGATION ONGLETS === */
        .astro-metadata-form .nav-tab-wrapper {
            margin-bottom: 25px;
            border-bottom: 3px solid #e1e5e9;
            background: white;
            border-radius: 12px 12px 0 0;
            padding: 0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .astro-metadata-form .nav-tab {
            background: transparent;
            border: none;
            padding: 18px 25px;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-radius: 12px 12px 0 0;
            margin: 0;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .astro-metadata-form .nav-tab:hover {
            color: #0073aa;
            background: rgba(0,115,170,0.05);
            transform: translateY(-2px);
        }
        
        .astro-metadata-form .nav-tab.nav-tab-active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102,126,234,0.3);
        }
        
        /* === CONTENU ONGLETS === */
        .astro-metadata-form .tab-content { 
            display: none;
            background: white;
            border-radius: 0 0 16px 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .astro-metadata-form .tab-content.active { display: block; }
        
        /* === TABLEAUX DE FORMULAIRE === */
        .astro-metadata-form .form-table {
            border-spacing: 0;
            width: 100%;
        }
        
        .astro-metadata-form .form-table th {
            width: 180px;
            padding: 20px 15px 20px 0;
            vertical-align: top;
            font-weight: 700;
            color: #2c3e50;
            font-size: 15px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .astro-metadata-form .form-table td {
            padding: 20px 0;
            vertical-align: top;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .astro-metadata-form .form-table tr:last-child th,
        .astro-metadata-form .form-table tr:last-child td {
            border-bottom: none;
        }
        
        /* === LABELS AMÉLIORÉS === */
        .astro-metadata-form label {
            display: inline-flex;
            align-items: center;
            font-weight: 600;
            color: #34495e;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .astro-metadata-form label::before {
            content: '🔸';
            margin-right: 8px;
            font-size: 12px;
        }
        
        /* === CHAMPS DE SAISIE AMÉLIORÉS === */
        .astro-metadata-form input[type="text"], 
        .astro-metadata-form input[type="number"],
        .astro-metadata-form input[type="email"],
        .astro-metadata-form select {
            margin: 4px 8px 8px 0;
            padding: 12px 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .astro-metadata-form input[type="text"]:focus,
        .astro-metadata-form input[type="number"]:focus,
        .astro-metadata-form input[type="email"]:focus,
        .astro-metadata-form select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1), 0 4px 12px rgba(0,0,0,0.08);
            outline: none;
            transform: translateY(-1px);
        }
        
        /* === TEXTAREA AMÉLIORÉES === */
        .astro-metadata-form textarea {
            width: 100%;
            max-width: 600px;
            min-height: 80px;
            padding: 16px;
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            color: #2c3e50;
            background: white;
            resize: vertical;
            font-family: inherit;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
        }
        
        .astro-metadata-form textarea:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1), 0 4px 12px rgba(0,0,0,0.08);
            outline: none;
            transform: translateY(-1px);
        }
        
        /* === TAILLES DE CHAMPS === */
        .astro-metadata-form .small-text { width: 100px; }
        .astro-metadata-form .regular-text { width: 280px; }
        .astro-metadata-form .large-text { width: 450px; }
        
        /* === PLACEHOLDERS STYLISÉS === */
        .astro-metadata-form input::placeholder,
        .astro-metadata-form textarea::placeholder {
            color: #a0a7b4;
            font-style: italic;
            font-weight: 400;
        }
        
        /* === DESCRIPTIONS D'AIDE === */
        .astro-metadata-form .description {
            margin: 8px 0 0 0;
            padding: 8px 12px;
            background: rgba(102,126,234,0.05);
            color: #5a6c7d;
            font-size: 13px;
            font-style: italic;
            border-radius: 6px;
            border-left: 3px solid #667eea;
        }
        
        /* === GROUPES DE CHAMPS === */
        .astro-metadata-form .field-group {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
            margin-bottom: 8px;
        }
        
        .astro-metadata-form .field-group span {
            font-weight: 600;
            color: #667eea;
            font-size: 16px;
            margin: 0 4px;
            align-self: center;
        }
        
        /* === BOUTON PRINCIPAL === */
        .astro-metadata-form .button-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border: none !important;
            padding: 15px 30px !important;
            font-size: 16px !important;
            font-weight: 700 !important;
            border-radius: 12px !important;
            box-shadow: 0 6px 20px rgba(102,126,234,0.3) !important;
            transition: all 0.3s ease !important;
            text-shadow: none !important;
        }
        
        .astro-metadata-form .button-primary:hover {
            transform: translateY(-3px) !important;
            box-shadow: 0 8px 25px rgba(102,126,234,0.4) !important;
        }
        
        /* === EFFET HOVER SUR SECTIONS === */
        .astro-metadata-form .form-table tr:hover {
            background: rgba(102,126,234,0.02);
            border-radius: 8px;
        }
        
        /* === SELECT STYLISÉ === */
        .astro-metadata-form select {
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23666" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
            appearance: none;
        }
        
        /* === RESPONSIVITÉ === */
        @media (max-width: 768px) {
            .astro-metadata-form {
                padding: 20px;
                margin: 10px 0;
            }
            
            .astro-metadata-form .form-table th {
                width: 120px;
                font-size: 14px;
            }
            
            .astro-metadata-form .regular-text,
            .astro-metadata-form .large-text {
                width: 100%;
                max-width: none;
            }
            
            .astro-metadata-form .field-group {
                flex-direction: column;
                align-items: stretch;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Gestion des onglets
            $('.nav-tab').click(function(e) {
                e.preventDefault();
                var target = $(this).attr('href');
                
                // Activer l'onglet
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Afficher le contenu
                $('.tab-content').hide();
                $(target).show();
            });
            
            // Calculer automatiquement le temps total
            $('[name="lights_count"], [name="lights_exposure"]').on('input', function() {
                var count = parseInt($('[name="lights_count"]').val()) || 0;
                var exposure = parseInt($('[name="lights_exposure"]').val()) || 0;
                var total = count * exposure;
                $('[name="lights_total_time"]').val(total);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Description des classes Bortle
     */
    private static function getBortleDescription($class) {
        $descriptions = array(
            1 => 'Excellent (site pristine)',
            2 => 'Typique rural foncé',
            3 => 'Typique rural',
            4 => 'Rural/suburbain',
            5 => 'Suburbain',
            6 => 'Suburbain clair',
            7 => 'Périurbain/urbain',
            8 => 'Urbain',
            9 => 'Centre ville'
        );
        return $descriptions[$class] ?? '';
    }
}