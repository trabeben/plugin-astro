# AstroFolio v1.4.7 - Upload Groupé d'Images

## Nouveautés de la version 1.4.7

### 🚀 Upload Groupé d'Images

La version 1.4.7 introduit une fonctionnalité majeure : **l'upload groupé d'images** qui permet aux utilisateurs d'envoyer plusieurs fichiers simultanément avec des métadonnées communes.

## Fonctionnalités

### ✨ Interface Utilisateur

- **Zone de drag & drop** intuitive avec animation
- **Sélection multiple** jusqu'à 20 fichiers simultanément
- **Prévisualisation** des fichiers avec titres personnalisables
- **Barre de progression** en temps réel
- **Gestion d'erreurs** détaillée par fichier

### 🔧 Fonctionnalités Techniques

- **Métadonnées communes** appliquées à tous les fichiers
- **Titres individualisables** pour chaque image
- **Validation des fichiers** (type, taille, nombre)
- **Upload sécurisé** avec nonces WordPress
- **Traitement en lot** optimisé

### 📸 Types de Fichiers Supportés

- **JPEG/JPG** - Format principal pour l'affichage web
- **PNG** - Transparence et qualité maximale
- **GIF** - Images animées
- **WebP** - Format moderne optimisé

### 🛡️ Sécurité

- **Vérification des permissions** WordPress
- **Protection CSRF** avec nonces
- **Validation côté serveur** de chaque fichier
- **Sanitisation** de tous les inputs utilisateur

## Utilisation

### 1. Accès à l'interface

Rendez-vous dans l'administration WordPress :
```
WordPress Admin > AstroFolio > 📤 Upload Groupé
```

### 2. Sélection des fichiers

Deux méthodes possibles :
- **Drag & Drop** : Glissez vos fichiers directement dans la zone
- **Sélection manuelle** : Cliquez sur "Sélectionner des fichiers"

### 3. Configuration des métadonnées

Remplissez les champs communs qui s'appliqueront à toutes les images :
- Description commune
- Objet astronomique (si identique pour toutes)
- Date d'acquisition
- Lieu d'observation
- Équipement (télescope, caméra)
- Paramètres techniques (optionnel)

### 4. Titres individuels

Pour chaque fichier, vous pouvez :
- Garder le nom de fichier par défaut
- Définir un titre personnalisé

### 5. Lancement de l'upload

Cliquez sur "🚀 Démarrer l'Upload Groupé" et suivez la progression en temps réel.

## Limites et Recommandations

### 📊 Limites Techniques

- **Maximum 20 fichiers** par envoi
- **10 MB maximum** par fichier
- **Timeout du serveur** à considérer pour de gros volumes

### 💡 Recommandations

- **Préparez vos fichiers** : Nommez-les correctement avant l'upload
- **Vérifiez la taille** : Optimisez vos images si nécessaire
- **Connexion stable** : Assurez-vous d'une bonne connexion Internet
- **Métadonnées complètes** : Plus vous renseignez, mieux c'est !

## Architecture Technique

### 🗂️ Fichiers Ajoutés/Modifiés

```
astrofolio-v1.4.7-STABLE/
├── astrofolio.php                     # Version et description mises à jour
├── admin/
│   ├── class-admin.php               # Nouvelle page d'upload groupé
│   ├── class-admin-images.php        # Méthode upload_bulk_images_ajax()
│   ├── css/
│   │   └── bulk-upload.css           # Styles pour l'interface
│   └── js/
│       └── bulk-upload.js            # Logique côté client
```

### 🔄 Flux de Traitement

1. **Côté Client** (JavaScript)
   - Validation des fichiers
   - Préparation FormData
   - Envoi AJAX avec progression

2. **Côté Serveur** (PHP)
   - Vérification des permissions
   - Validation sécurisée
   - Traitement en lot des fichiers
   - Création des entrées en base

3. **Retour Client**
   - Affichage des résultats
   - Gestion des erreurs
   - Actions post-upload

### 🎯 Performances

- **Upload en une seule requête** plutôt qu'individuellement
- **Barre de progression native** du navigateur
- **Gestion mémoire optimisée** côté serveur
- **Feedback temps réel** pour l'utilisateur

## Compatibilité

### ✅ Prérequis

- **WordPress** 5.0+
- **PHP** 7.4+
- **MySQL** 5.7+ ou MariaDB 10.2+
- **Navigateurs modernes** avec support FormData et XHR2

### 🌐 Navigateurs Testés

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

## Dépannage

### 🚨 Problèmes Courants

#### "Fichier trop volumineux"
- Vérifiez la limite PHP `upload_max_filesize`
- Ajustez `post_max_size` si nécessaire
- Vérifiez `max_execution_time` pour les gros volumes

#### "Timeout lors de l'upload"
- Réduisez le nombre de fichiers par lot
- Vérifiez la stabilité de la connexion
- Augmentez les limites de temps PHP si possible

#### "Erreur de permissions"
- Vérifiez les permissions utilisateur WordPress
- Contrôlez les droits d'écriture sur le serveur

### 🔧 Configuration Serveur Recommandée

```php
# php.ini recommandations
upload_max_filesize = 10M
post_max_size = 100M
max_execution_time = 300
max_input_vars = 3000
memory_limit = 256M
```

## Support et Contribution

### 📞 Support

Pour tout problème lié à l'upload groupé :
1. Vérifiez les logs d'erreur WordPress
2. Testez avec un petit nombre de fichiers
3. Vérifiez la configuration serveur

### 🤝 Contribution

Cette fonctionnalité est extensible. Vous pouvez :
- Ajouter de nouveaux formats de fichiers
- Personnaliser les validations
- Étendre les métadonnées communes
- Améliorer l'interface utilisateur

---

**AstroFolio v1.4.7** - Plugin WordPress pour la gestion d'images d'astrophotographie  
*Développé par Benoist Degonne - 2026*