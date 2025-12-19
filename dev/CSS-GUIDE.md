# CSS Architecture - Guide de Référence

**Version finale** : Utilisation de `@apply` avec Tailwind

## Structure des Fichiers

```
public/css/
├── app.css (5.6 KB)        → Commun : navigation, forms, badges, layout
├── dashboard.css (5.2 KB)  → Calendrier : header, grille, bookings
└── property.css (3.2 KB)   → Properties/Units : cartes, sources
```

## Principe : @apply pour la Cohérence

```css
/* ✅ BON : Utiliser @apply avec les classes Tailwind */
.nav-button {
    @apply inline-flex items-center justify-center aspect-square w-10 
           border border-gray-300 rounded-md text-sm font-medium 
           text-gray-700 bg-white hover:bg-gray-50 transition-colors;
}

/* ❌ MAUVAIS : Hardcoder les valeurs */
.nav-button {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem;
    border: 1px solid #d1d5db;  /* Incohérent avec le design system */
}
```

**Pourquoi @apply ?**
- Cohérence avec Tailwind
- Maintenance facile (changer une couleur = une ligne)
- Design tokens centralisés
- Évolutif (dark mode, themes)

## Layouts

### Trois largeurs de contenu

```blade
<!-- Full width (dashboard) -->
<div class="content-full-width dashboard-page">
    <!-- 100% width -->
</div>

<!-- Normal (pages de contenu) -->
<div class="content-normal properties-page">
    <!-- Max 1280px, centré -->
</div>

<!-- Small (login, formulaires) -->
<div class="content-small">
    <!-- Max 600px, centré -->
</div>
```

## Navigation

Structure du header :

```blade
<nav>
    <div class="nav-container">
        <div class="branding">
            <a href="/" class="logo">🏖️ Bokit</a>
        </div>
        
        <div class="nav-menu main-menu">
            <a href="/dashboard" class="nav-link">Calendar</a>
            <a href="/properties" class="nav-link">Properties</a>
        </div>
        
        <div class="nav-menu user-menu">
            <!-- Dropdowns, locale switcher, etc. -->
        </div>
    </div>
</nav>
```

**CSS automatique** : Pas de classes utilitaires nécessaires !

## Dashboard

### Header avec boutons carrés

```blade
<div class="content-full-width dashboard-page">
    <header>
        <div class="controls">
            <div class="nav-group">
                <a href="..." class="nav-button">«</a>
                <a href="..." class="nav-button">‹</a>
                <a href="..." class="nav-button today">
                    <span class="text">Today</span>
                    <span class="icon">🏠</span>
                </a>
            </div>
            
            <div class="period">
                <h2>December 2025</h2>
                <div class="week-info">Weeks 49-52 2025</div>
            </div>
            
            <div class="nav-group">
                <a href="..." class="nav-button">›</a>
                <a href="..." class="nav-button">»</a>
            </div>
        </div>
    </header>
</div>
```

**Points clés** :
- `.nav-button` = carré 40x40px (touch-friendly)
- `.nav-button.today` = rectangle auto-width
- Responsive : icône 🏠 sur mobile, "Today" sur desktop

## Properties

```blade
<div class="content-normal properties-page">
    <h1>Properties</h1>
    
    <section>
        <h2>Gîtes Mosaïques</h2>
        
        <div class="units">
            <article class="unit">
                <h3><a href="...">Moon</a></h3>
                <p class="description">Beautiful unit</p>
                <div class="actions">
                    <a href="...">View</a>
                    <span class="separator">|</span>
                    <a href="...">Edit</a>
                </div>
                <div class="meta">3 sources</div>
            </article>
        </div>
    </section>
</div>
```

**CSS gère** :
- Grille responsive (1 col mobile, 2 tablet, 3 desktop)
- Hover states
- Spacing
