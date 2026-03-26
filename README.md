# 🏦 AlMadar Bank — API REST

> Back-end e-banking · Laravel 12 · JWT · MySQL

API REST sécurisée pour la plateforme AlMadar Bank. Pas d'interface web — uniquement des endpoints JSON consommables par les équipes front-end web et mobile.

---

## ⚙️ Prérequis

| Outil | Version minimale |
|---|---|
| PHP | 8.2+ |
| Composer | 2.x |
| MySQL | 8.0+ |
| Laravel | 12.x |

---

## 🚀 Installation complète

```bash
# 1. Cloner le dépôt
git clone https://github.com/a-oirgari/sprint7brief2
cd almadar-bank

# 2. Installer les dépendances PHP
composer install

# 3. Créer le fichier d'environnement
cp .env.example .env

# 4. Générer la clé applicative Laravel
php artisan key:generate

# 5. Générer la clé secrète JWT  ← OBLIGATOIRE
php artisan jwt:secret

# 6. Créer la base de données MySQL puis lancer les migrations
php artisan migrate

# 7. Insérer les données de test (users, comptes, transactions)
php artisan db:seed

# 8. Démarrer le serveur de développement
php artisan serve
```

> L'API est disponible sur **`http://localhost:8000/api`**
>
> ⚠️ La racine `/` retourne un 404 — c'est normal, c'est une API pure. Tester avec Postman.

---

## 🔑 Variables `.env`

```env
APP_NAME=AlMadarBank
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=almadar_bank
DB_USERNAME=root
DB_PASSWORD=

# JWT
JWT_SECRET=           # généré automatiquement par php artisan jwt:secret
JWT_TTL=60            # durée du token en minutes (1h)
JWT_REFRESH_TTL=20160 # durée refresh token en minutes (14 jours)

# Règles métier configurables
OVERDRAFT_LIMIT=1000          # Découvert max compte COURANT (MAD)
DAILY_TRANSFER_LIMIT=10000    # Limite journalière de virements (MAD)
SAVINGS_MAX_WITHDRAWALS=3     # Retraits max/mois — compte EPARGNE
MINOR_MAX_WITHDRAWALS=2       # Retraits max/mois — compte MINEUR
MONTHLY_FEE_AMOUNT=50         # Frais de tenue compte COURANT (MAD)
```

---

## 👥 Comptes de test

Disponibles après `php artisan db:seed` :

| Email | Mot de passe | Rôle |
|---|---|---|
| `admin@almadar.ma` | `password` | Administrateur |
| `karim@example.ma` | `password` | Tuteur (adulte) |
| `youssef@example.ma` | `password` | Mineur (14 ans) |
| `fatima@example.ma` | `password` | Client standard |

---

## 📡 Endpoints

### 🔓 Authentification (public)

| Méthode | URL | Description | Body requis |
|---|---|---|---|
| `POST` | `/api/auth/register` | Inscription | `first_name, last_name, email, password, password_confirmation, date_of_birth` |
| `POST` | `/api/auth/login` | Connexion → retourne JWT | `email, password` |
| `POST` | `/api/auth/refresh` | Rafraîchir le token | — (token dans header) |
| `POST` | `/api/auth/logout` | Déconnexion | — |

### 👤 Profil utilisateur (auth requis)

| Méthode | URL | Description |
|---|---|---|
| `GET` | `/api/users/me` | Voir son profil |
| `PUT` | `/api/users/me` | Modifier son profil |
| `PATCH` | `/api/users/me/password` | Changer son mot de passe |

### 🏦 Comptes bancaires (auth requis)

| Méthode | URL | Description |
|---|---|---|
| `GET` | `/api/accounts` | Lister mes comptes |
| `POST` | `/api/accounts` | Créer un compte (`type`: COURANT / EPARGNE / MINEUR) |
| `GET` | `/api/accounts/{id}` | Détail d'un compte |
| `DELETE` | `/api/accounts/{id}` | Demande de clôture |
| `POST` | `/api/accounts/{id}/co-owners` | Ajouter un co-titulaire |
| `DELETE` | `/api/accounts/{id}/co-owners/{userId}` | Retirer un co-titulaire |
| `POST` | `/api/accounts/{id}/guardian` | Assigner un tuteur (compte mineur) |
| `PATCH` | `/api/accounts/{id}/convert` | Convertir MINEUR → COURANT (à la majorité) |

### 💸 Virements & Transactions (auth requis)

| Méthode | URL | Description |
|---|---|---|
| `POST` | `/api/transfers` | Initier un virement |
| `GET` | `/api/transfers/{id}` | Détail d'un virement |
| `GET` | `/api/accounts/{id}/transactions` | Historique (filtres: `type`, `from`, `to`) |
| `GET` | `/api/transactions/{id}` | Détail d'une transaction |

### 🔐 Administration (rôle admin requis)

| Méthode | URL | Description | Body requis |
|---|---|---|---|
| `GET` | `/api/admin/accounts` | Tous les comptes (paginé) | — |
| `PATCH` | `/api/admin/accounts/{id}/block` | Bloquer un compte | `reason` |
| `PATCH` | `/api/admin/accounts/{id}/unblock` | Débloquer un compte | — |
| `PATCH` | `/api/admin/accounts/{id}/close` | Clôturer un compte | — |

---

## 🔐 Authentification JWT

Toutes les routes protégées nécessitent le header suivant :

```
Authorization: Bearer <access_token>
```

Le token est retourné par `/api/auth/login` et `/api/auth/register`.

**Durée de vie :** 60 minutes. Utiliser `/api/auth/refresh` pour en obtenir un nouveau sans se reconnecter.

---

## 📋 Règles métier implémentées

### Types de comptes

| Règle | COURANT | EPARGNE | MINEUR |
|---|---|---|---|
| Découvert | ✅ (configurable) | ❌ | ❌ |
| Retraits/mois | Illimité | Max 3 | Max 2 (tuteur uniquement) |
| Intérêts | ❌ | ✅ (configurable) | ✅ (configurable) |
| Frais mensuels | ✅ 50 MAD | ❌ | ❌ |

### Virements (règle 2.4)
- Solde insuffisant → `422` avec message explicite
- Virement vers le même compte → `422`
- Compte bloqué ou clôturé → `422`
- Limite journalière 10 000 MAD dépassée → `422`
- EPARGNE : plus de 3 retraits/mois → `422`
- MINEUR : seul le tuteur peut débiter → `403`

### Frais & intérêts (automatiques)
- **1er du mois 00:01** → frais de tenue prélevés sur les comptes COURANT
- Si solde insuffisant → compte `BLOCKED` + transaction `FEE_FAILED`
- **1er du mois 00:05** → intérêts crédités sur EPARGNE & MINEUR
- Calcul : `solde × (taux_annuel / 12)`

---

## 🕐 Task Scheduler

Les tâches planifiées sont définies dans `routes/console.php` (Laravel 12).

```bash
# Voir les tâches planifiées
php artisan schedule:list

# Tester manuellement (simule le 1er du mois)
php artisan schedule:run

# Tester un service directement via tinker
php artisan tinker
>>> app(App\Services\ScheduledBankingService::class)->chargeMonthlyFees();
>>> app(App\Services\ScheduledBankingService::class)->creditMonthlyInterests();
```

**En production**, ajouter une seule ligne dans le crontab du serveur :
```bash
* * * * * cd /chemin/vers/projet && php artisan schedule:run >> /dev/null 2>&1
```

---

## 🧪 Tests

```bash
# Lancer tous les tests
php artisan test

# Tests unitaires uniquement
php artisan test --testsuite=Unit

# Mode verbose (voir chaque test)
php artisan test --verbose

# Stopper au premier échec
php artisan test --stop-on-failure
```

### Règles métier couvertes

| Test | Règle vérifiée | Résultat attendu |
|---|---|---|
| `it_rejects_transfer_with_insufficient_balance` | Solde insuffisant | `ValidationException` |
| `it_allows_transfer_within_overdraft_limit` | Découvert COURANT autorisé | `COMPLETED` |
| `it_rejects_transfer_when_savings_monthly_limit_reached` | 3 retraits/mois EPARGNE | `ValidationException` |
| `it_rejects_transfer_from_minor_account_by_non_guardian` | Mineur sans tuteur | `ValidationException` |
| `it_allows_transfer_from_minor_account_by_guardian` | Mineur par tuteur | `COMPLETED` |
| `it_rejects_transfer_from_blocked_account` | Compte bloqué | `ValidationException` |
| `it_rejects_transfer_to_same_account` | Même compte source/dest | `ValidationException` |
| `it_rejects_transfer_exceeding_daily_limit` | Limite 10 000 MAD/jour | `ValidationException` |

---

## 🏗️ Architecture

```
app/
├── Http/
│   ├── Controllers/Api/         ← 6 controllers, délèguent aux Services
│   │   ├── AuthController.php
│   │   ├── UserController.php
│   │   ├── AccountController.php
│   │   ├── AdminAccountController.php
│   │   ├── TransferController.php
│   │   └── TransactionController.php
│   ├── Requests/                ← validation Form Requests (PSR-12)
│   │   ├── Auth/
│   │   ├── Account/
│   │   ├── Transfer/
│   │   └── User/
│   └── Middleware/
│       └── AdminMiddleware.php
├── Services/                    ← TOUTE la logique métier ici
│   ├── AuthService.php
│   ├── AccountService.php
│   ├── TransferService.php
│   └── ScheduledBankingService.php
├── Repositories/                ← accès base de données
│   ├── AccountRepository.php
│   └── TransactionRepository.php
└── Models/
    ├── User.php
    ├── Account.php
    └── Transaction.php
database/
├── migrations/                  ← 4 tables : users, accounts, account_user, transactions
└── seeders/
    └── DatabaseSeeder.php       ← 4 users, 5 comptes, 10 transactions
routes/
├── api.php                      ← toutes les routes groupées par middleware
└── console.php                  ← Task Scheduler (Laravel 12, sans Kernel)
config/
└── banking.php                  ← règles métier configurables via .env
tests/
└── Unit/
    └── TransferServiceTest.php  ← 8 tests PHPUnit
```

---

## 🔍 Commandes utiles

```bash
# Voir toutes les routes enregistrées
php artisan route:list

# Réinitialiser la base et reseed
php artisan migrate:fresh --seed

# Vider les caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

---

## 🚫 Notes importantes

- La racine `GET /` retourne **404** — c'est voulu, l'API n'a pas de page d'accueil.
- Ne jamais committer le fichier `.env` (contient `JWT_SECRET` et credentials DB).
- En production, passer `APP_DEBUG=false` pour ne pas exposer les stack traces.