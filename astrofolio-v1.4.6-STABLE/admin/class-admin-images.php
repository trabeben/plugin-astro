<?php
/**
 * =============================================================================
 * CLASSE DE GESTION DES IMAGES EN ADMINISTRATION
 * =============================================================================
 * 
 * Cette classe gère toutes les opérations sur les images dans l'interface d'administration
 * 
 * 🎯 FONCTIONNALITÉS PRINCIPALES :
 * - Upload d'images avec validation de format
 * - Suppression sécurisée d'images et métadonnées
 * - Recherche d'objets astronomiques via AJAX
 * - Gestion des métadonnées associées
 * - Interface de modification des images existantes
 * 
 * 🔐 SÉCURITÉ :
 * - Vérification des nonces AJAX pour toutes les actions
 * - Validation des permissions utilisateur
 * - Sanitisation de tous les inputs
 * - Protection contre les uploads malveillants
 * 
 * 📡 ACTIONS AJAX GÉRÉES :
 * - astro_search_objects : Recherche d'objets dans les catalogues
 * - astro_delete_image : Suppression d'une image et ses métadonnées
 * - astro_upload_image : Upload et traitement d'une nouvelle image
 * 
 * @since 1.4.6
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Admin
 */
class Astro_Admin_Images {
    
    /**
     * Constructeur - Enregistrement des hooks AJAX
     * 
     * Toutes les actions AJAX sont protégées par des nonces et
     * des vérifications de permissions appropriées
     * 
     * @since 1.4.6
     */
    public function __construct() {
        // Action AJAX pour rechercher des objets astronomiques
        add_action('wp_ajax_astro_search_objects', array($this, 'search_objects_ajax'));
        
        // Action AJAX pour supprimer une image
        add_action('wp_ajax_astro_delete_image', array($this, 'delete_image_ajax'));
        
        // Action AJAX pour uploader une nouvelle image
        add_action('wp_ajax_astro_upload_image', array($this, 'upload_image_ajax'));
    }
    
    /**
     * Recherche d'objets astronomiques via AJAX
     * 
     * Permet la recherche en temps réel dans les catalogues
     * pour l'autocomplétion des noms d'objets
     * 
     * SÉCURITÉ :
     * - Vérification du nonce AJAX
     * - Validation de la longueur de la requête (min 2 caractères)
     * - Sanitisation de l'input utilisateur
     * 
     * @since 1.4.6
     * @return void Retourne JSON via wp_send_json_success/error
     */
    public function search_objects_ajax() {
        // Vérification de sécurité CSRF via nonce WordPress
        check_ajax_referer('astro_admin_nonce', 'nonce');
        
        // Récupération et sanitisation de la requête de recherche
        $query = sanitize_text_field($_POST['query']);
        
        // Validation : minimum 2 caractères pour éviter les recherches trop larges
        if (strlen($query) < 2) {
            wp_send_json_error(array('message' => 'Terme de recherche trop court'));
        }
        
        // Recherche dans les catalogues via la classe dédiée
        $objects = Astro_Catalogs::search_objects($query);
        
        // Retour des résultats au format JSON
        wp_send_json_success($objects);
    }
    
    /**
     * Suppression d'une image via AJAX
     * 
     * Supprime de manière sécurisée une image et toutes ses métadonnées
     * associées de la base de données
     * 
     * ÉTAPES DE SUPPRESSION :
     * 1. Vérification du nonce et des permissions
     * 2. Validation de l'ID d'image
     * 3. Suppression du fichier physique
     * 4. Suppression des métadonnées en base
     * 5. Nettoyage des références croisées
     * 
     * @since 1.4.6
     * @return void Retourne JSON de confirmation ou d'erreur
     */
    public function delete_image_ajax() {
        // Vérification de sécurité CSRF via nonce WordPress
        check_ajax_referer('astro_admin_nonce', 'nonce');
        
        // Vérification des permissions avec alternatives de secours
        if (!current_user_can('manage_astro_portfolio') && !current_user_can('delete_posts') && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permission insuffisante pour supprimer des images'));
        }
        
        $image_id = intval($_POST['id']);
        
        if (!$image_id) {
            wp_send_json_error(array('message' => 'ID d\'image invalide'));
        }
        
        $result = Astro_Images::delete_image($image_id);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Image supprimée'));
        } else {
            wp_send_json_error(array('message' => 'Erreur lors de la suppression'));
        }
    }
    
    public function upload_image_ajax() {
        check_ajax_referer('astro_admin_nonce', 'nonce');
        
        // Vérification des permissions avec alternatives de secours
        if (!current_user_can('edit_astro_images') && !current_user_can('upload_files') && !current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => 'Permission insuffisante pour uploader des images'));
        }
        
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => 'Erreur d\'upload : fichier non reçu ou corrompu'));
        }
        
        // Vérification du type de fichier
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $file_type = $_FILES['image']['type'];
        
        if (!in_array($file_type, $allowed_types)) {
            wp_send_json_error(array('message' => 'Type de fichier non autorisé. Utilisez JPG, PNG, GIF ou WebP.'));
        }
        
        // Vérification de la taille (max 10MB)
        if ($_FILES['image']['size'] > 10 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'Fichier trop volumineux (max 10MB)'));
        }
        
        // Upload WordPress
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $uploaded_file = wp_handle_upload($_FILES['image'], array('test_form' => false));
        
        if (isset($uploaded_file['error'])) {
            wp_send_json_error(array('message' => 'Erreur upload : ' . $uploaded_file['error']));
        }
        
        // Créer l'attachement WordPress
        $attachment = array(
            'guid'           => $uploaded_file['url'], 
            'post_mime_type' => $uploaded_file['type'],
            'post_title'     => sanitize_text_field($_POST['title'] ?? 'Image d\'astrophotographie'),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment($attachment, $uploaded_file['file']);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error(array('message' => 'Erreur lors de la création de l\'attachement'));
        }
        
        // Générer les métadonnées d'image
        $metadata = wp_generate_attachment_metadata($attachment_id, $uploaded_file['file']);
        wp_update_attachment_metadata($attachment_id, $metadata);
        
        // Préparer les données d'image pour la base de données
        $image_data = array(
            'title' => sanitize_text_field($_POST['title'] ?? ''),
            'description' => sanitize_textarea_field($_POST['description'] ?? ''),
            'object_id' => !empty($_POST['object_id']) ? intval($_POST['object_id']) : null,
            'image_url' => $uploaded_file['url'],
            'thumbnail_url' => wp_get_attachment_thumb_url($attachment_id),
            'acquisition_date' => !empty($_POST['acquisition_date']) ? sanitize_text_field($_POST['acquisition_date']) : null,
            'location' => sanitize_text_field($_POST['location'] ?? ''),
            'telescope' => sanitize_text_field($_POST['telescope'] ?? ''),
            'camera_name' => sanitize_text_field($_POST['camera_name'] ?? ''),
            'total_exposure_time' => !empty($_POST['total_exposure_time']) ? intval($_POST['total_exposure_time']) : 0,
            'status' => 'published'
        );
        
        // Ajouter les métadonnées techniques optionnelles
        $technical_fields = array(
            'telescope_type', 'focal_length', 'f_number', 'camera_type', 'filter_type',
            'exposure_time', 'iso_value', 'gain', 'binning', 'sub_count',
            'temperature', 'moon_phase', 'bortle_scale'
        );
        
        foreach ($technical_fields as $field) {
            if (!empty($_POST[$field])) {
                $image_data[$field] = sanitize_text_field($_POST[$field]);
            }
        }
        
        // Créer l'entrée en base de données
        $image_id = Astro_Images::create_image($image_data);
        
        if (!$image_id) {
            // Supprimer l'attachement en cas d'échec
            wp_delete_attachment($attachment_id, true);
            wp_send_json_error(array('message' => 'Erreur lors de la sauvegarde en base de données'));
        }
        
        wp_send_json_success(array(
            'message' => 'Image uploadée avec succès',
            'image_id' => $image_id,
            'attachment_id' => $attachment_id,
            'url' => $uploaded_file['url']
        ));
    }
}