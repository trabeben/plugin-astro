/**
 * =============================================================================
 * JAVASCRIPT POUR L'UPLOAD GROUPÉ D'IMAGES ASTROFOLIO v1.4.7
 * =============================================================================
 * 
 * Ce fichier gère toute l'interactivité de la page d'upload groupé :
 * - Drag & drop de fichiers multiples
 * - Prévisualisation des fichiers sélectionnés
 * - Upload via AJAX avec barre de progression
 * - Gestion des erreurs et succès individuels
 * 
 * @since 1.4.7
 * @author Benoist Degonne
 * @package AstroFolio
 * @subpackage Admin/JS
 */

(function($) {
    'use strict';

    // Variables globales
    let selectedFiles = [];
    let isUploading = false;
    
    // Configuration
    const MAX_FILES = 20;
    const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10MB
    const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    
    $(document).ready(function() {
        initializeBulkUpload();
    });

    /**
     * Initialisation de toutes les fonctionnalités d'upload groupé
     */
    function initializeBulkUpload() {
        setupDropZone();
        setupFileInput();
        setupFormHandlers();
        setupTechnicalFieldsToggle();
    }

    /**
     * Configuration de la zone de drag & drop
     */
    function setupDropZone() {
        const dropZone = $('#bulk-file-drop-zone');
        
        // Prévenir le comportement par défaut du navigateur
        $(document).on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
        
        // Gestion du drop sur la zone
        dropZone.on('dragover dragenter', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).addClass('dragover');
        });
        
        dropZone.on('dragleave', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
        });
        
        dropZone.on('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $(this).removeClass('dragover');
            
            const files = e.originalEvent.dataTransfer.files;
            handleFileSelection(files);
        });
        
        // Clic sur la zone = clic sur input file
        dropZone.on('click', function() {
            $('#bulk-file-input').click();
        });
    }

    /**
     * Configuration de l'input file
     */
    function setupFileInput() {
        $('#bulk-file-input').on('change', function() {
            const files = this.files;
            handleFileSelection(files);
        });
    }

    /**
     * Traitement des fichiers sélectionnés
     * 
     * @param {FileList} files Liste des fichiers sélectionnés
     */
    function handleFileSelection(files) {
        if (!files || files.length === 0) {
            return;
        }
        
        // Validation du nombre de fichiers
        if (selectedFiles.length + files.length > MAX_FILES) {
            showMessage(`Trop de fichiers. Maximum ${MAX_FILES} fichiers autorisés.`, 'error');
            return;
        }
        
        // Traitement de chaque fichier
        Array.from(files).forEach(file => {
            if (validateFile(file)) {
                // Vérifier si le fichier n'est pas déjà dans la liste
                const exists = selectedFiles.some(f => 
                    f.name === file.name && f.size === file.size
                );
                
                if (!exists) {
                    selectedFiles.push(file);
                }
            }
        });
        
        updateSelectedFilesList();
        updateUIVisibility();
    }

    /**
     * Validation d'un fichier individuel
     * 
     * @param {File} file Fichier à valider
     * @returns {boolean} True si le fichier est valide
     */
    function validateFile(file) {
        // Vérification du type
        if (!ALLOWED_TYPES.includes(file.type.toLowerCase())) {
            showMessage(`Type de fichier non autorisé: ${file.name}`, 'error');
            return false;
        }
        
        // Vérification de la taille
        if (file.size > MAX_FILE_SIZE) {
            showMessage(`Fichier trop volumineux: ${file.name} (max 10MB)`, 'error');
            return false;
        }
        
        return true;
    }

    /**
     * Met à jour l'affichage de la liste des fichiers sélectionnés
     */
    function updateSelectedFilesList() {
        const container = $('#selected-files-list');
        container.empty();
        
        selectedFiles.forEach((file, index) => {
            const fileItem = $(`
                <div class="file-item" data-index="${index}">
                    <div class="file-info">
                        <span class="dashicons dashicons-format-image"></span>
                        <div class="file-details">
                            <div class="file-name">${escapeHtml(file.name)}</div>
                            <div class="file-size">${formatFileSize(file.size)} • ${file.type}</div>
                            <div class="file-title-input" style="margin-top: 5px;">
                                <input type="text" placeholder="Titre personnalisé (optionnel)" 
                                       class="file-custom-title" data-index="${index}"
                                       value="${getFileNameWithoutExtension(file.name)}">
                            </div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="button-link remove-file" data-index="${index}">
                            <span class="dashicons dashicons-no"></span>
                        </button>
                    </div>
                </div>
            `);
            container.append(fileItem);
        });
        
        // Mettre à jour le compteur
        $('#files-count').text(`${selectedFiles.length} fichier(s) sélectionné(s)`);
    }

    /**
     * Configuration des gestionnaires de formulaire
     */
    function setupFormHandlers() {
        // Bouton vider la sélection
        $(document).on('click', '#clear-files', function() {
            selectedFiles = [];
            updateSelectedFilesList();
            updateUIVisibility();
            $('#bulk-file-input').val('');
        });
        
        // Bouton supprimer un fichier individuel
        $(document).on('click', '.remove-file', function() {
            const index = parseInt($(this).data('index'));
            selectedFiles.splice(index, 1);
            updateSelectedFilesList();
            updateUIVisibility();
        });
        
        // Soumission du formulaire
        $('#bulk-upload-form').on('submit', function(e) {
            e.preventDefault();
            if (!isUploading && selectedFiles.length > 0) {
                startBulkUpload();
            }
        });
    }

    /**
     * Configuration du toggle des champs techniques
     */
    function setupTechnicalFieldsToggle() {
        $('#toggle-technical-fields').on('click', function(e) {
            e.preventDefault();
            const fields = $('#technical-fields');
            const icon = $(this).find('.dashicons');
            
            if (fields.is(':visible')) {
                fields.slideUp();
                icon.removeClass('dashicons-arrow-down').addClass('dashicons-arrow-right');
            } else {
                fields.slideDown();
                icon.removeClass('dashicons-arrow-right').addClass('dashicons-arrow-down');
            }
        });
    }

    /**
     * Démarrer l'upload groupé
     */
    function startBulkUpload() {
        if (selectedFiles.length === 0) {
            showMessage('Aucun fichier sélectionné.', 'error');
            return;
        }
        
        isUploading = true;
        
        // Masquer le formulaire et afficher la progression
        $('#bulk-upload-form').hide();
        $('#upload-progress-section').show();
        
        // Initialiser la barre de progression
        updateProgress(0, selectedFiles.length);
        $('#upload-results').empty();
        
        // Démarrer l'upload
        uploadFiles();
    }

    /**
     * Upload des fichiers tous ensemble (méthode optimisée)
     */
    async function uploadFiles() {
        const results = {
            success: [],
            errors: []
        };
        
        // Récupérer les métadonnées communes du formulaire
        const commonMetadata = getFormData();
        
        // Préparer le nonce
        const nonce = $('#astro_nonce').val();
        
        try {
            updateProgress(0, selectedFiles.length, `Préparation de l'upload de ${selectedFiles.length} fichier(s)...`);
            
            // Créer FormData avec tous les fichiers
            const formData = new FormData();
            
            // Ajouter tous les fichiers
            selectedFiles.forEach((file, index) => {
                formData.append('images[]', file);
                const customTitle = $(`.file-custom-title[data-index="${index}"]`).val() || 
                                   getFileNameWithoutExtension(file.name);
                formData.append('titles[]', customTitle);
            });
            
            // Ajouter les métadonnées communes
            Object.keys(commonMetadata).forEach(key => {
                if (commonMetadata[key] !== '') {
                    formData.append(key, commonMetadata[key]);
                }
            });
            
            // Ajouter les données WordPress
            formData.append('action', 'astro_upload_bulk_images');
            formData.append('nonce', nonce);
            
            updateProgress(0, selectedFiles.length, 'Upload en cours...');
            
            // Envoyer la requête AJAX
            const response = await $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    const xhr = new window.XMLHttpRequest();
                    // Gestionnaire de progression upload
                    xhr.upload.addEventListener('progress', function(evt) {
                        if (evt.lengthComputable) {
                            const percentComplete = Math.round((evt.loaded / evt.total) * 100);
                            updateProgress(
                                Math.round((percentComplete / 100) * selectedFiles.length), 
                                selectedFiles.length, 
                                `Upload en cours... ${percentComplete}%`
                            );
                        }
                    }, false);
                    return xhr;
                }
            });
            
            if (response.success && response.data.results) {
                results.success = response.data.results.success || [];
                results.errors = response.data.results.errors || [];
                
                // Mettre à jour le statut de chaque fichier
                selectedFiles.forEach((file, index) => {
                    const successFile = results.success.find(s => s.file === file.name);
                    const errorFile = results.errors.find(e => e.file === file.name);
                    
                    if (successFile) {
                        updateFileStatus(index, 'success', 'Uploadé avec succès');
                    } else if (errorFile) {
                        updateFileStatus(index, 'error', errorFile.message);
                    }
                });
            } else {
                throw new Error(response.data?.message || 'Erreur serveur inconnue');
            }
            
        } catch (error) {
            console.error('Erreur upload groupé:', error);
            
            // Marquer tous les fichiers en erreur
            selectedFiles.forEach((file, index) => {
                results.errors.push({
                    file: file.name,
                    message: error.message || 'Erreur d\'upload'
                });
                updateFileStatus(index, 'error', error.message || 'Erreur d\'upload');
            });
        }
        
        // Upload terminé
        updateProgress(selectedFiles.length, selectedFiles.length, 'Upload terminé !');
        displayFinalResults(results);
        isUploading = false;
    }

    /**
     * Récupération des données du formulaire
     */
    function getFormData() {
        return {
            description: $('#bulk_description').val(),
            object_name: $('#bulk_object_name').val(),
            acquisition_date: $('#bulk_acquisition_date').val(),
            location: $('#bulk_location').val(),
            telescope: $('#bulk_telescope').val(),
            camera_name: $('#bulk_camera_name').val(),
            total_exposure_time: $('#bulk_total_exposure_time').val(),
            focal_length: $('#bulk_focal_length').val(),
            f_number: $('#bulk_f_number').val(),
            filter_type: $('#bulk_filter_type').val(),
            iso_value: $('#bulk_iso_value').val()
        };
    }

    /**
     * Met à jour la barre de progression
     */
    function updateProgress(current, total, message) {
        const percentage = Math.round((current / total) * 100);
        
        $('#progress-current').text(current);
        $('#progress-total').text(total);
        $('#upload-progress-fill').css('width', percentage + '%');
        
        if (message) {
            $('.progress-text').append(`<br><small>${message}</small>`);
        }
    }

    /**
     * Met à jour le statut d'un fichier dans la liste
     */
    function updateFileStatus(index, status, message) {
        const fileItem = $(`.file-item[data-index="${index}"]`);
        fileItem.addClass(status);
        
        const statusSpan = $(`<div class="file-status ${status}">${message}</div>`);
        fileItem.find('.file-details').append(statusSpan);
    }

    /**
     * Affiche les résultats finaux
     */
    function displayFinalResults(results) {
        const resultContainer = $('#upload-results');
        resultContainer.empty();
        
        const successCount = results.success.length;
        const errorCount = results.errors.length;
        const total = successCount + errorCount;
        
        // Message de résumé
        let summaryMessage = '';
        if (errorCount === 0) {
            summaryMessage = `✅ Tous les ${successCount} fichiers ont été uploadés avec succès !`;
        } else if (successCount === 0) {
            summaryMessage = `❌ Aucun fichier n'a pu être uploadé (${errorCount} erreurs).`;
        } else {
            summaryMessage = `⚠️ ${successCount} fichiers uploadés, ${errorCount} erreurs.`;
        }
        
        resultContainer.append(`
            <div class="upload-summary ${errorCount === 0 ? 'success' : errorCount === total ? 'error' : 'warning'}">
                <h4>${summaryMessage}</h4>
            </div>
        `);
        
        // Actions post-upload
        const actionsDiv = $(`
            <div class="upload-actions" style="margin-top: 20px;">
                <button type="button" id="reset-upload" class="button">🔄 Nouvel Upload</button>
                ${successCount > 0 ? '<a href="admin.php?page=astrofolio-gallery" class="button button-primary">🖼️ Voir la Galerie</a>' : ''}
            </div>
        `);
        
        resultContainer.append(actionsDiv);
        
        // Gestionnaire pour recommencer
        $('#reset-upload').on('click', function() {
            resetUploadForm();
        });
    }

    /**
     * Remet à zéro le formulaire d'upload
     */
    function resetUploadForm() {
        selectedFiles = [];
        isUploading = false;
        
        updateSelectedFilesList();
        updateUIVisibility();
        
        $('#bulk-upload-form')[0].reset();
        $('#bulk-file-input').val('');
        $('#upload-progress-section').hide();
        $('#bulk-upload-form').show();
        $('#bulk-upload-messages').empty();
    }

    /**
     * Met à jour la visibilité des sections selon l'état
     */
    function updateUIVisibility() {
        if (selectedFiles.length > 0) {
            $('#selected-files-section').show();
            $('#bulk-upload-form').show();
        } else {
            $('#selected-files-section').hide();
            $('#bulk-upload-form').hide();
        }
    }

    /**
     * Affiche un message à l'utilisateur
     */
    function showMessage(message, type) {
        const messageDiv = $(`
            <div class="notice notice-${type} is-dismissible">
                <p>${escapeHtml(message)}</p>
                <button type="button" class="notice-dismiss">
                    <span class="screen-reader-text">Fermer ce message.</span>
                </button>
            </div>
        `);
        
        $('#bulk-upload-messages').append(messageDiv);
        
        // Auto-dismiss après 5 secondes
        setTimeout(() => {
            messageDiv.fadeOut();
        }, 5000);
    }

    /**
     * Utilitaires
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function getFileNameWithoutExtension(filename) {
        return filename.replace(/\.[^/.]+$/, "");
    }

})(jQuery);