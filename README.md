# **Xew Xew**

Xew Xew est une application de gestion d'événements en ligne. Elle permet aux utilisateurs de créer, modifier, supprimer et restaurer des événements. Les utilisateurs peuvent également acheter des billets pour des événements, gérer leurs portefeuilles, et consulter les transactions liées à leurs achats de billets. L'application prend en charge la gestion de différents types d'utilisateurs (utilisateurs anonymes et enregistrés).

---

## **Table des matières**

- [Description du projet](#description-du-projet)
- [Fonctionnalités](#fonctionnalités)
- [Technologies utilisées](#technologies-utilisées)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [API Endpoints](#api-endpoints)
- [Utilisation](#utilisation)
- [Contributeurs](#contributeurs)


---

## **Description du projet**

Xew Xew est une application complète pour la gestion d'événements avec un système de gestion de billets. Elle permet aux utilisateurs de s'inscrire, de consulter des événements, d'acheter des billets, de suivre leurs transactions et de gérer leur portefeuille. Un système de suppression douce (soft delete) permet de restaurer des événements qui ont été temporairement supprimés. Les événements sont classés par catégories, facilitant ainsi leur gestion et leur recherche.

---

## **Fonctionnalités**

- Création, modification, suppression (soft delete) et restauration d'événements.
- Gestion de billets pour chaque événement.
- Système d'authentification et de gestion des utilisateurs (anonymes et enregistrés).
- Gestion des portefeuilles pour le suivi des transactions.
- Catégorisation des événements pour un meilleur filtrage.
- API REST pour toutes les opérations CRUD.

---

## **Technologies utilisées**

- **Backend** : Laravel 10.x (avec API REST)
- **Base de données** : PostgreSQL
- **Frontend** : (si applicable)
- **API Test** : Postman
- **Autres** : PHP 8.x, Composer, SCSS

---

## **Prérequis**

- PHP >= 8.1
- Composer
- PostgreSQL
- Laravel >= 10.x
- Node.js et NPM (si tu utilises un frontend)

---

## **Installation**

### 1. **Cloner le dépôt :**

```bash
git clone https://github.com/ton-username/xew-xew.git
cd xew-xew
```

### 2. **Installer les dépendances :**

```bash
composer install
npm install
```

### 3. **Configurer l'environnement :**

Copie le fichier `.env.example` et renomme-le en `.env`, puis configure les paramètres de la base de données :

```bash
cp .env.example .env
```

Met à jour les informations PostgreSQL dans le fichier `.env` :

```plaintext
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=xew_xew_db
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### 4. **Générer la clé de l'application :**

```bash
php artisan key:generate
```

### 5. **Exécuter les migrations et les seeders :**

```bash
php artisan migrate --seed
```

### 6. **Lancer l'application :**

```bash
php artisan serve
```

---

## **API Endpoints**

### Routes principales de gestion des événements :

- **GET** `/api/events` : Récupérer la liste des événements (y compris les supprimés).
- **POST** `/api/events` : Créer un nouvel événement.
- **GET** `/api/events/{id}` : Récupérer un événement spécifique.
- **PUT** `/api/events/{id}` : Mettre à jour un événement.
- **DELETE** `/api/events/{id}` : Supprimer un événement (soft delete).
- **POST** `/api/events/{id}/restore` : Restaurer un événement supprimé.

### Routes de gestion des billets :

- **POST** `/api/tickets` : Créer un billet pour un événement.
- **GET** `/api/tickets/{id}` : Récupérer les détails d'un billet.
- **DELETE** `/api/tickets/{id}` : Supprimer un billet.

### Routes de gestion des utilisateurs :

- **POST** `/api/register` : Enregistrer un nouvel utilisateur.
- **POST** `/api/login` : Authentifier un utilisateur.
- **GET** `/api/users/{id}` : Obtenir les détails d'un utilisateur.

---

## **Utilisation**

Utilise Postman ou tout autre client API pour interagir avec les différents endpoints.

### Exemples de commandes Curl :

- **Créer un événement :**

```bash
curl -X POST -H "Content-Type: application/json" -d '{"nom": "Concert", "description": "Concert de musique", "quantite_billet": 100, "prix_billet": 25.50, "date": "2024-10-10", "heure": "20:00", "lieu": "Paris"}' http://localhost:8000/api/events
```

- **Supprimer un événement :**

```bash
curl -X DELETE http://localhost:8000/api/events/{id}
```

- **Restaurer un événement supprimé :**

```bash
curl -X POST http://localhost:8000/api/events/{id}/restore
```

---

## **Contributeurs**

- **[Aminata Assane Ndiaye](https://github.com/Aminata-A)**

---


