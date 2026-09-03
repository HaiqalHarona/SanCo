# SanCo - GitHub Actions CI/CD Pipeline Documentation

This document provides an overview of the Continuous Integration and Continuous Deployment (CI/CD) pipelines configured for the SanCo real-time encrypted messaging platform using **GitHub Actions**.

---

## Table of Contents

1. [Pipeline Architecture Overview](#1-pipeline-architecture-overview)
2. [Branching & Deployment Strategy](#2-branching--deployment-strategy)
3. [Workflow Specifications](#3-workflow-specifications)
   - [Continuous Integration (`ci.yml`)](#continuous-integration-ciyml)
   - [Staging Deployment (`deploy-staging.yml`)](#staging-deployment-deploy-stagingyml)
4. [Test Execution in CI (`tests/`)](#4-test-execution-in-ci-tests)
5. [Service Dependencies & Testing Matrix](#5-service-dependencies--testing-matrix)
6. [Required GitHub Secrets & Configuration](#6-required-github-secrets--configuration)
7. [Deployment Lifecycle](#7-deployment-lifecycle)
8. [Troubleshooting & Maintenance](#8-troubleshooting--maintenance)

---

## 1. Pipeline Architecture Overview

SanCo utilizes GitHub Actions workflows designed to enforce coding standards, execute automated tests against live database/cache services, and perform automated deployments to the staging environment:

```mermaid
flowchart TD
    subgraph Development & Feature Work
        PR[Pull Request to develop, alpha, main] --> CI[CI Pipeline]
        PUSH_DEV[Push to develop] --> CI
        CI --> CS[Code Style: Laravel Pint]
        CI --> BE[Backend Tests: PHP 8.4 + MongoDB + Redis - tests/]
        CI --> FE[Frontend Build Check: Node 22 + Vite]
    end

    subgraph Staging Pipeline
        PUSH_ALPHA[Push to alpha branch] --> STG_VAL[Staging Pre-flight Validation - tests/]
        STG_VAL --> STG_DEPLOY[SSH Deploy to Staging Server]
    end
```

---

## 2. Branching & Deployment Strategy

| Branch | Trigger Event | Target Environment | Pipeline File |
| :--- | :--- | :--- | :--- |
| `feature/**` | `push` | CI Validation | `.github/workflows/ci.yml` |
| `develop` | `push`, `pull_request` | CI Validation | `.github/workflows/ci.yml` |
| `alpha` | `pull_request` | CI Validation | `.github/workflows/ci.yml` |
| `alpha` | `push` | Staging Server | `.github/workflows/deploy-staging.yml` |
| `main` | `pull_request` | CI Validation | `.github/workflows/ci.yml` |

---

## 3. Workflow Specifications

### Continuous Integration (`ci.yml`)

The CI workflow runs on pull requests and pushes to `develop` and `feature/**` branches to catch code style regressions, backend unit/feature test failures in `tests/`, and frontend asset compilation errors.

* **Trigger**: 
  * `push` to `develop`, `'feature/**'`
  * `pull_request` to `develop`, `alpha`, `main`
* **Concurrency**: `ci-${{ github.ref }}` with `cancel-in-progress: true` (cancels superseded runs on subsequent pushes).
* **Jobs**:
  1. **`code-style` (Code Style - Pint)**:
     - Installs PHP 8.4 with `mbstring`, `json`, `openssl` extensions.
     - Restores cached Composer dependencies.
     - Prepares framework storage and bootstrap cache directories.
     - Executes `./vendor/bin/pint --test` to ensure strict PSR-12 and Laravel coding standard compliance.
  2. **`test-backend` (Backend Tests)**:
     - Provisions service containers:
       - **MongoDB 7.0** (`mongo:7.0`) on port `27017`
       - **Redis 7.0** (`redis:7.0`) on port `6379` with health checks (`redis-cli ping`)
     - Configures PHP 8.4 with extensions: `mbstring`, `json`, `openssl`, `mongodb`, `pdo_sqlite`, `redis`.
     - Generates application encryption key (`php artisan key:generate`).
     - Executes `php artisan test` to run all Unit and Feature tests in `tests/` with the following environment variables:
       - `APP_ENV=testing`
       - `DB_CONNECTION=sqlite` (`:memory:`)
       - `MONGODB_URI=mongodb://127.0.0.1:27017`
       - `MONGODB_DATABASE=testing`
       - `REDIS_CLIENT=phpredis`
       - `REDIS_HOST=127.0.0.1`
       - `REDIS_PORT=6379`
       - `CACHE_STORE=redis`
     - Seeds the test database (`php artisan db:seed --force`).
     - Executes the Redis caching query stress benchmark (`php tests/stress/query.php`).
  3. **`build-frontend` (Frontend Build Check)**:
     - Sets up Node.js 22 with npm dependency caching.
     - Executes `npm ci` and `npm run build` to verify Vite compilation of Tailwind CSS and JavaScript / WASM cryptographic assets.

---

### Staging Deployment (`deploy-staging.yml`)

Deploys automatically to the staging server whenever changes are merged into the `alpha` branch.

* **Trigger**: `push` on `alpha`
* **Concurrency**: `staging-deployment` with `cancel-in-progress: false` (prevents overlapping concurrent deployments).
* **Jobs**:
  1. **`validate` (Staging Pre-flight Validation)**:
     - Executes full backend test suite (`php artisan test` across all tests in `tests/`) with MongoDB & Redis services and verifies frontend asset compilation.
  2. **`deploy` (Deploy to Staging Server)**:
     - Depends on `validate` success.
     - Connects via SSH (`appleboy/ssh-action@v1.0.3`) using `STAGING_SSH_*` secrets.
     - Performs automated pull, dependency installation, build, database migration, and optimization caching.

---

## 4. Test Execution in CI (`tests/`)

The automated pipeline executes all tests housed in the [`tests/`](../tests) directory:

- **Unit Tests (`tests/Unit/`)**: Low-level isolated logic tests.
- **Feature Tests (`tests/Feature/`)**:
  - `AuthAndProfileTest.php`: User authentication, profile updates, and OAuth handling.
  - `BroadcastingTest.php`: WebSockets, channel authorization, and event broadcasting.
  - `ConcurrentLoginTest.php`: Multi-session detection, session invalidation, and Redis TTL management.
  - `ConversationTest.php`: Direct & group chat room creation, participant management, and inbox retrieval.
  - `FriendshipTest.php` & `FriendshipBlockTest.php`: Symmetric friend request workflows, acceptance, unfriend, and blocking.
  - `LivewireMessengerTest.php`: Volt single-file components, search contacts, avatar, and Livewire UI interactions.
  - `MessageTest.php` & `MessageReactionTest.php`: E2EE message payloads, reactions, and read receipts.
  - `RedisAndCacheTest.php`: Redis caching, key rotation, and cache invalidation.
- **Stress Testing Benchmark (`tests/stress/`)**:
  - `query.php`: Database seeding and Redis caching read vs. uncached query benchmark across 100 users.

---

## 5. Service Dependencies & Testing Matrix

The CI/CD runner environments are standardized across all workflows:

| Layer | Technology | Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Runtime** | PHP | `8.4` | Backend application execution |
| **PHP Extensions** | `mongodb`, `redis`, `pdo_sqlite`, `openssl`, `mbstring`, `json` | Latest compatible | Native drivers for MongoDB, Redis, and Libsodium |
| **Database** | MongoDB Service Container | `7.0` | NoSQL document storage & E2EE message store testing |
| **Cache/KV** | Redis Service Container | `7.0` | Presence tracking, sessions, and cache testing |
| **Node.js** | Node.js / NPM | `22` | Vite asset bundling and frontend dependencies |

---

## 6. Required GitHub Secrets & Configuration

To enable automated staging deployment, configure the following repository secrets in GitHub Settings (`Settings > Secrets and variables > Actions`):

### Staging Environment Secrets
| Secret Key | Description | Example |
| :--- | :--- | :--- |
| `STAGING_SSH_HOST` | Remote staging server IP address or hostname | `192.0.2.10` / `staging.sanco.app` |
| `STAGING_SSH_USER` | Remote SSH deployment user | `deployer` / `ubuntu` |
| `STAGING_SSH_KEY` | Private SSH key (ED25519 or RSA) with server access | `-----BEGIN OPENSSH PRIVATE KEY...` |
| `STAGING_SSH_PORT` | *(Optional)* SSH port (defaults to `22`) | `22` |
| `STAGING_TARGET_PATH` | Absolute path to application root on staging server | `/var/www/sanco-staging` |

---

## 7. Deployment Lifecycle

During staging deployment, the remote script executes the following structured sequence:

```bash
# 1. Navigate to deployment directory
cd <TARGET_PATH>

# 2. Put application into maintenance mode
php artisan down || true

# 3. Pull latest branch commits
git fetch origin alpha
git reset --hard origin/alpha

# 4. Install optimized production Composer dependencies
composer install --no-interaction --prefer-dist --optimize-autoloader --no-dev

# 5. Compile production frontend assets
npm ci
npm run build

# 6. Execute database migrations
php artisan migrate --force

# 7. Refresh and optimize Laravel caches (routes, config, views, events)
php artisan optimize:clear
php artisan optimize

# 8. Restart background queues & workers (e.g. Laravel Reverb, queues)
php artisan queue:restart

# 9. Bring application back online
php artisan up
```

---

## 8. Troubleshooting & Maintenance

### Common Issues and Resolutions

1. **Pint Code Style Failure**:
   - Run `./vendor/bin/pint` locally to automatically fix formatting before pushing.
2. **Backend Test Failures (`tests/`)**:
   - Run `php artisan test` locally to verify tests before creating pull requests.
3. **MongoDB / Redis Service Connection Failures**:
   - Ensure service container ports `27017` and `6379` are correctly mapped in GitHub Actions runners.
4. **SSH Action Host Verification / Key Errors**:
   - Ensure the public key corresponding to `STAGING_SSH_KEY` is added to `~/.ssh/authorized_keys` on the remote server and has proper file permissions (`chmod 600 ~/.ssh/authorized_keys`).
