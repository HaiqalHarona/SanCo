# SanCo - GitHub Actions CI/CD Pipeline Documentation

This document provides a comprehensive overview of the Continuous Integration and Continuous Deployment (CI/CD) pipelines configured for the SanCo real-time encrypted messaging platform using **GitHub Actions**.

---

## Table of Contents

1. [Pipeline Architecture Overview](#1-pipeline-architecture-overview)
2. [Branching & Deployment Strategy](#2-branching--deployment-strategy)
3. [Workflow Specifications](#3-workflow-specifications)
   - [Continuous Integration (`ci.yml`)](#continuous-integration-ciyml)
   - [Staging Deployment (`deploy-staging.yml`)](#staging-deployment-deploy-stagingyml)
   - [Production Deployment (`deploy-production.yml`)](#production-deployment-deploy-productionyml)
4. [Service Dependencies & Testing Matrix](#4-service-dependencies--testing-matrix)
5. [Required GitHub Secrets & Configuration](#5-required-github-secrets--configuration)
6. [Deployment Lifecycle & Zero-Downtime Execution](#6-deployment-lifecycle--zero-downtime-execution)
7. [Troubleshooting & Maintenance](#7-troubleshooting--maintenance)

---

## 1. Pipeline Architecture Overview

SanCo utilizes a multi-stage GitHub Actions workflow architecture designed to guarantee code quality, pass automated tests against real database/cache services, and perform automated deployments via secure SSH actions:

```mermaid
flowchart TD
    subgraph Development & Feature Work
        PR[Pull Request to develop, alpha, main] --> CI[CI Pipeline]
        PUSH_DEV[Push to develop] --> CI
        CI --> CS[Code Style: Laravel Pint]
        CI --> BE[Backend Tests: PHP 8.4 + MongoDB + Redis]
        CI --> FE[Frontend Build Check: Node 22 + Vite]
    end

    subgraph Staging Pipeline
        PUSH_ALPHA[Push to alpha branch] --> STG_VAL[Staging Pre-flight Validation]
        STG_VAL --> STG_DEPLOY[SSH Deploy to Staging Server]
    end

    subgraph Production Pipeline
        PUSH_MAIN[Push to main / Release Tag v*.*.*] --> PROD_VAL[Production Pre-flight Validation]
        PROD_VAL --> PROD_DEPLOY[SSH Deploy to Production Server]
    end
```

---

## 2. Branching & Deployment Strategy

| Branch / Tag | Trigger Event | Target Environment | Pipeline File |
| :--- | :--- | :--- | :--- |
| `develop` | `push`, `pull_request` | CI Validation | `.github/workflows/ci.yml` |
| `alpha` | `pull_request` | CI Validation | `.github/workflows/ci.yml` |
| `alpha` | `push` | Staging Server | `.github/workflows/deploy-staging.yml` |
| `main` | `pull_request` | CI Validation | `.github/workflows/ci.yml` |
| `main` | `push` | Production Server | `.github/workflows/deploy-production.yml` |
| `v*.*.*` (Tags) | `push` | Production Server | `.github/workflows/deploy-production.yml` |

---

## 3. Workflow Specifications

### Continuous Integration (`ci.yml`)

The CI workflow runs on pull requests and pushes to `develop` to catch code style regressions, backend unit/feature test failures, and frontend asset compilation errors.

* **Trigger**: 
  * `push` to `develop`
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
     - Executes `php artisan test` with testing environment variables:
       - `APP_ENV=testing`
       - `DB_CONNECTION=sqlite` (`:memory:`)
       - `MONGODB_URI=mongodb://127.0.0.1:27017`
       - `MONGODB_DATABASE=testing`
       - `REDIS_CLIENT=phpredis`
       - `CACHE_STORE=redis`
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
     - Executes full backend test suite with MongoDB & Redis services and frontend asset compilation.
  2. **`deploy` (Deploy to Staging Server)**:
     - Depends on `validate` success.
     - Connects via SSH (`appleboy/ssh-action@v1.0.3`) using `STAGING_SSH_*` secrets.
     - Performs automated pull, dependency installation, build, database migration, and optimization caching.

---

### Production Deployment (`deploy-production.yml`)

Deploys to the live production infrastructure upon pushing release tags matching `v*.*.*` or merging directly into `main`.

* **Trigger**: 
  * `push` with tags `v*.*.*` (e.g. `v1.0.0`, `v1.2.1`)
  * `push` on `main` branch
* **Environment**: `production`
* **Concurrency**: `production-deployment` with `cancel-in-progress: false`.
* **Jobs**:
  1. **`validate` (Production Pre-flight Validation)**:
     - Comprehensive pre-flight checks including tests against MongoDB/Redis and frontend build verification.
  2. **`deploy` (Deploy to Production Server)**:
     - Depends on `validate` success.
     - Connects via SSH (`appleboy/ssh-action@v1.0.3`) using `PROD_SSH_*` secrets.
     - Enables bypassable maintenance mode using `MAINTENANCE_SECRET`.
     - Pulls changes, installs production-optimized dependencies (`--no-dev`), builds assets, runs migrations, caches configurations/routes, and restarts queue workers.

---

## 4. Service Dependencies & Testing Matrix

The CI/CD runner environments are standardized across all workflows:

| Layer | Technology | Version | Purpose |
| :--- | :--- | :--- | :--- |
| **Runtime** | PHP | `8.4` | Backend application execution |
| **PHP Extensions** | `mongodb`, `redis`, `pdo_sqlite`, `openssl`, `mbstring`, `json` | Latest compatible | Native drivers for MongoDB, Redis, and Libsodium |
| **Database** | MongoDB Service Container | `7.0` | NoSQL document storage & E2EE message store testing |
| **Cache/KV** | Redis Service Container | `7.0` | Presence tracking, sessions, and cache testing |
| **Node.js** | Node.js / NPM | `22` | Vite asset bundling and frontend dependencies |

---

## 5. Required GitHub Secrets & Configuration

To enable automated staging and production deployments, ensure the following repository secrets are configured in GitHub Settings (`Settings > Secrets and variables > Actions`):

### Staging Environment Secrets
| Secret Key | Description | Example |
| :--- | :--- | :--- |
| `STAGING_SSH_HOST` | Remote staging server IP address or hostname | `192.0.2.10` / `staging.sanco.app` |
| `STAGING_SSH_USER` | Remote SSH deployment user | `deployer` / `ubuntu` |
| `STAGING_SSH_KEY` | Private SSH key (ED25519 or RSA) with server access | `-----BEGIN OPENSSH PRIVATE KEY...` |
| `STAGING_SSH_PORT` | *(Optional)* SSH port (defaults to `22`) | `22` |
| `STAGING_TARGET_PATH` | Absolute path to application root on staging server | `/var/www/sanco-staging` |

### Production Environment Secrets
| Secret Key | Description | Example |
| :--- | :--- | :--- |
| `PROD_SSH_HOST` | Remote production server IP address or hostname | `198.51.100.20` / `app.sanco.app` |
| `PROD_SSH_USER` | Remote SSH deployment user | `deployer` / `ubuntu` |
| `PROD_SSH_KEY` | Private SSH key (ED25519 or RSA) with server access | `-----BEGIN OPENSSH PRIVATE KEY...` |
| `PROD_SSH_PORT` | *(Optional)* SSH port (defaults to `22`) | `22` |
| `PROD_TARGET_PATH` | Absolute path to application root on production server | `/var/www/sanco-production` |
| `MAINTENANCE_SECRET` | *(Optional)* Secret token to bypass maintenance mode | `sanco-secure-deploy-bypass` |

---

## 6. Deployment Lifecycle & Zero-Downtime Execution

During deployment, the remote script executes the following structured sequence:

```bash
# 1. Navigate to deployment directory
cd <TARGET_PATH>

# 2. Put application into maintenance mode (with bypass secret in production)
php artisan down --secret="<MAINTENANCE_SECRET>"

# 3. Pull latest branch commits
git fetch origin <branch>
git reset --hard origin/<branch>

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

## 7. Troubleshooting & Maintenance

### Common Issues and Resolutions

1. **Pint Code Style Failure**:
   - Run `./vendor/bin/pint` locally to automatically fix formatting before pushing.
2. **MongoDB / Redis Service Connection Failures**:
   - Ensure service container ports `27017` and `6379` are correctly mapped in GitHub Actions runners.
3. **SSH Action Host Verification / Key Errors**:
   - Ensure the public key corresponding to `PROD_SSH_KEY` / `STAGING_SSH_KEY` is added to `~/.ssh/authorized_keys` on the remote server and has proper file permissions (`chmod 600 ~/.ssh/authorized_keys`).
4. **Post-Deployment Cache Issues**:
   - Run `php artisan optimize:clear && php artisan optimize` manually if cached route or config definitions do not reflect immediately.
