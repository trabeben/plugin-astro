# AstroFolio v1.4.8-FILTRES 🎯

Version basée sur la v1.4.7-FINAL stable avec ajout d'un système de filtrage avancé pour la galerie.

## 🆕 Nouvelles fonctionnalités

### Système de filtres avancé
- **Interface moderne** : Design gradient avec animations fluides
- **Filtrage en temps réel** : Résultats instantanés sans rechargement de page
- **Multiple critères** : Recherche textuelle, type d'objet, télescope, caméra
- **UX optimisée** : Debounce sur la recherche, animations d'apparition/disparition
- **Persistance URL** : Les filtres sont conservés dans l'URL du navigateur

### Filtres disponibles
1. **🔍 Recherche textuelle** - Dans les titres et noms d'objets
2. **🌌 Type d'objet** - Galaxie, nébuleuse, amas, etc.
3. **🔭 Télescope** - Filtrage par instrument utilisé
4. **📷 Caméra** - Filtrage par appareil photo/caméra

### Interface utilisateur
- **Compteur en temps réel** - Affichage du nombre d'images trouvées
- **Bouton de réinitialisation** - Remise à zéro de tous les filtres
- **Responsive design** - Adaptation mobile/tablette/desktop
- **Animations fluides** - Transitions d'apparition/disparition
- **Feedback visuel** - Indicateurs de chargement et d'état

## 🛠️ Améliorations techniques

### Côté PHP
- `get_gallery_filters()` - Récupération sécurisée des paramètres URL
- `extract_filter_data()` - Extraction des valeurs disponibles pour les listes
- `render_gallery_filters()` - Génération de l'interface de filtrage
- Support défensif de différentes structures de base de données

### Côté JavaScript
- Filtrage côté client pour une réactivité maximale
- Gestion intelligente des URL avec `pushState`
- Debounce sur la recherche textuelle (500ms)
- Animations CSS personnalisées

### Styles CSS
- Design gradient moderne (bleu/violet)
- Variables CSS pour personnalisation facile
- Breakpoints responsive complets
- Animations d'interaction (hover, focus, transitions)

## 🔧 Installation

1. Sauvegarder votre base de données WordPress
2. Désactiver l'ancien plugin AstroFolio  
3. Supprimer l'ancien dossier du plugin
4. Installer cette version v1.4.8-FILTRES
5. Réactiver le plugin

## 📋 Compatibilité

- **Base sur** : AstroFolio v1.4.7-FINAL (base stable validée)
- **WordPress** : 5.0+ (testé jusqu'à 6.9)
- **PHP** : 7.4+
- **Navigateurs** : Chrome, Firefox, Safari, Edge (versions récentes)

## 🎨 Personnalisation

Les styles peuvent être personnalisés via les variables CSS :

```css
:root {
    --astro-filter-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --astro-filter-radius: 12px;
    --astro-animation-duration: 300ms;
}
```

## 📝 Notes techniques

- Le filtrage fonctionne sur les données déjà chargées (côté client)
- Pour les grandes galeries, considérer l'ajout d'un filtrage côté serveur
- Les filtres sont conservés lors de la navigation (URL persistante)
- Compatible avec le système de pagination existant

---

**Développé le :** 5 janvier 2026  
**Basé sur :** AstroFolio v1.4.7-FINAL  
**Auteur :** Benoist Degonne