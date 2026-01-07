# Importation des utilisateurs

## Utilisation

1. Copiez `config/users.json.example` vers `tmp/users.json`
2. Modifiez le fichier avec vos utilisateurs :

```json
[
    {
        "name": "Admin User",
        "email": "admin@example.com",
        "password": "secure-password",
        "is_admin": true
    },
    {
        "name": "John Doe",
        "email": "john@example.com",
        "password": "another-password",
        "is_admin": false
    }
]
```

3. Importez les utilisateurs :

```bash
php artisan bokit:import-users tmp/users.json
```

## Format du fichier

- `name`: Nom de l'utilisateur (optionnel, défaut = email)
- `email`: Email (obligatoire)
- `password`: Mot de passe (obligatoire)
- `is_admin`: Admin ou non (optionnel, défaut = false)
- `roles`: Tableau de rôles (optionnel, ex: ["property_manager", "booking_manager"])

## Rôles suggérés

- `admin`: Accès complet
- `property_manager`: Gestion des propriétés
- `booking_manager`: Gestion des réservations
- `property_viewer`: Visualisation seule des propriétés
- `booking_viewer`: Visualisation seule des réservations

## Rôles par propriété (table property_user)

- `user`: Accès de base à la propriété
- `admin`: Gestion complète de la propriété
- `owner`: Propriétaire de la propriété
- `manager`: Gestionnaire de la propriété

## Notes

- Les mots de passe sont automatiquement hashés
- Les utilisateurs sont créés ou mis à jour basé sur l'email
- Le dossier `tmp/` est ignoré par git (parfait pour les configs sensibles)
