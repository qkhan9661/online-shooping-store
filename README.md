# AI-powered online shopping (MVP)

Monorepo with a **Laravel 12** REST API (your environment installed v12 because Laravel 13 requires PHP 8.3+), a **FastAPI** pose pipeline (MediaPipe + OpenCV), and an **Expo (React Native)** mobile client. Scan images are stored on the **local public disk** today; the codebase is structured so you can swap to **S3 + CloudFront** later via `FILESYSTEM_DISK=s3` and standard Laravel Flysystem configuration.

## Folder structure

```
online-shopping/
├── backend/                 # Laravel API, Sanctum, queues, Postgres/Redis config
├── ai-service/              # FastAPI + MediaPipe + multiview fusion + ML calibration
│   ├── models/              # size_residual.npz (train with scripts/train_size_calibration.py)
│   └── app/pipeline.py      # fusion + feature extraction
├── mobile/                  # Expo app + voice-guided capture (expo-speech)
├── docker-compose.yml       # postgres + redis + laravel + ai
└── docs/
    ├── API.md
    ├── ARCHITECTURE_VIRTUAL_TRYON.md   # Phase 3 target architecture (AR, GPU, WS)
    ├── PHASE3_BACKEND_ARCHITECTURE.md # Phase 3 Laravel ↔ GPU ↔ Reverb (implemented)
    ├── DEPLOYMENT_PRODUCTION.md        # K8s / cloud sketch
    └── WEBSOCKETS_AND_STREAMING.md     # Reverb / real-time channels
```

## Prerequisites: tools to install (Windows · macOS · Linux)

Install these on your machine (versions are minimums; newer is usually fine).

| Tool | Why you need it |
|------|------------------|
| **Git** | Clone and update the repo. |
| **PHP 8.2+** (8.3+ if you move to Laravel 13 later) | Laravel backend. Extensions: **OpenSSL**, **PDO**, **Mbstring**, **Tokenizer**, **XML**, **Ctype**, **JSON**, **fileinfo**, **bcmath**; plus **pdo_pgsql** *or* **pdo_sqlite**. |
| **Composer** 2.x | PHP dependencies (`backend/`). |
| **Node.js** 18+ LTS + **npm** | Expo mobile app (`mobile/`); optional Laravel front-end tooling (`backend/` uses Vite in `composer dev`). |
| **Python** 3.10–3.12 + **pip** | AI service (`ai-service/`): FastAPI, MediaPipe, OpenCV. Use a **venv** per project. |
| **Redis** 6+ | Laravel queues, cache (recommended). Required for the documented `QUEUE_CONNECTION=redis` flow. |
| **PostgreSQL** 14+ | Default DB in `.env.example`. **Optional:** use **SQLite** only for quick local trials (`DB_CONNECTION=sqlite`). |
| **Docker Desktop** + **Docker Compose** (optional) | Run Postgres + Redis + Laravel + AI from `docker compose up` without installing PHP/Postgres locally on the host. |

### OS-specific notes

- **Windows**
  - Install PHP from [windows.php.net](https://windows.php.net/download/) or **XAMPP** / **Laragon**; enable **`extension=zip`** in `php.ini` if **Composer** fails to extract packages.
  - Add PHP and Composer to **PATH**; use **PowerShell** or **Git Bash** for the commands in this README.
  - **Redis:** use [Memurai](https://www.memurai.com/), WSL2 Redis, or Docker.
  - Python: install from [python.org](https://www.python.org/downloads/); OpenCV may need **Microsoft Visual C++ Redistributable**.

- **macOS**
  - **Homebrew** is convenient: `brew install php composer node python redis postgresql@16` (pick what you lack).
  - **Xcode Command Line Tools** (`xcode-select --install`) help native Node/Python builds.

- **Linux** (Debian/Ubuntu-style)
  - Example: `sudo apt install php-cli php-mbstring php-xml php-curl php-zip php-pgsql php-sqlite3 composer nodejs npm python3-venv redis-server postgresql`
  - For OpenCV in the AI service you may need `python3-dev` and `libgl1` (or distro equivalent).

### Optional / production-only tools

| Tool | When |
|------|------|
| **NVIDIA CUDA** + GPU drivers | Only when you replace CPU inference with a CUDA **PyTorch** build in `ai-service` (see Phase 3 GPU docs). |
| **ngrok** / **Cloudflare Tunnel** | Expose local API to a physical phone without LAN IP fiddling. |
| **EAS CLI** (`npm i -g eas-cli`) | When you build/store-submit the Expo app (Expo account). |

---

## Environment variables & third-party “API keys”

**Core MVP (local dev): no paid third-party API keys are required.** Auth is **Laravel Sanctum** (tokens issued by your own API). **MediaPipe / OpenCV** run inside your **Python** process, not as a billed cloud API.

### Required to generate locally (not from a vendor)

| Variable | How you get it |
|----------|----------------|
| **`APP_KEY`** | Run `php artisan key:generate` in `backend/` (encrypts sessions/cookies). |
| **`REVERB_APP_ID`**, **`REVERB_APP_KEY`**, **`REVERB_APP_SECRET`** | Run `php artisan reverb:install` (or copy from `.env.example` and customize). These identify **your** Reverb app, not an external SaaS. |

### URLs / services you point at (your own processes)

| Variable | Purpose |
|----------|---------|
| **`AI_SERVICE_URL`** | Base URL of the FastAPI app (default `http://127.0.0.1:8088`). In Docker Compose it is the internal service name. |
| **`GPU_SERVICE_URL`** | Phase 3 GPU HTTP API; defaults to **`AI_SERVICE_URL`** until you split a CUDA service. |
| **`APP_URL`** | Public URL of the Laravel app (used for links, storage URLs). |
| **`EXPO_PUBLIC_API_URL`** (mobile) | Where the app calls the API (e.g. `http://192.168.x.x:8000/api` on a real device). |

### Optional third-party credentials (only if you turn these features on)

| Feature | Env vars (examples) | Notes |
|---------|---------------------|--------|
| **AWS S3** (file storage / meshes) | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET` | Set `FILESYSTEM_DISK=s3`. Optional CloudFront URL in your asset pipeline docs. |
| **Transactional email** | `MAIL_MAILER`, `MAIL_HOST`, … or `POSTMARK_API_KEY` / **Resend** / **SES** keys in `config/services.php` | Default `log` driver does not send real mail. |
| **Pusher** (instead of self-hosted Reverb) | `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, `PUSHER_APP_CLUSTER` | Set `BROADCAST_CONNECTION=pusher` in `.env`. |
| **Ably** | `ABLY_KEY` | Set `BROADCAST_CONNECTION=ably` if you use it. |
| **Expo / EAS** | Log in with `eas login`; project IDs in `app.json` when you configure builds | No key needed for `expo start` against your own API. |

### Not used by this repo (unless you add them)

- **OpenAI**, **Google Cloud Vision**, **Azure**, **Stripe**, etc. — there are **no** hooks in the current codebase; add keys only if you integrate new features.

---

## Phase 3 — Virtual try-on, AR & GPU (what this repo adds now)

**Scope honesty:** A Zara/Nike-class system still needs **native AR** (ARKit / ARCore), a **cloth runtime** (Unity PhysX, NVIDIA Warp, or similar), and **artist pipelines** (Marvelous Designer / Blender). The **backend foundation** below is implemented so **Laravel only orchestrates**, **GPU work stays in Python**, and **real-time updates use WebSockets** (not HTTP polling).

### Implemented in-repo (backend foundation)

| Layer | Delivered |
|-------|-----------|
| **Architecture** | [docs/PHASE3_BACKEND_ARCHITECTURE.md](docs/PHASE3_BACKEND_ARCHITECTURE.md), [docs/ARCHITECTURE_VIRTUAL_TRYON.md](docs/ARCHITECTURE_VIRTUAL_TRYON.md), [docs/DEPLOYMENT_PRODUCTION.md](docs/DEPLOYMENT_PRODUCTION.md), [docs/WEBSOCKETS_AND_STREAMING.md](docs/WEBSOCKETS_AND_STREAMING.md) |
| **Laravel + Reverb** | **`laravel/reverb`**; **`config/broadcasting.php`**; **`bootstrap/app.php`** → `withBroadcasting(..., ['middleware' => ['auth:sanctum']])`; channels **`user.{id}`**, **`user.{id}.pose`**, **`user.{id}.cloth`** |
| **GPU client** | **`App\Services\GpuInferenceClient`** → `GPU_SERVICE_URL` (defaults to `AI_SERVICE_URL` for local dev) |
| **Jobs** | **`ScanBodyJob`** (`scans`), **`RealtimePoseInferenceJob`** (`default`), **`VtonGpuJob`** + **`FitPredictionJob`** (`vton-gpu`), **`VtonAssetPipelineJob`** (`assets`); **`GenerateFitRecommendationJob`** broadcasts **`FitUpdated`** |
| **Events** | **`PoseUpdated`** (`ShouldBroadcastNow`), **`FitUpdated`** (`ShouldBroadcastNow`), **`VtonProgress`** (`ShouldBroadcast`) |
| **API** | **`POST /api/ai/realtime-pose`** → **202** + queue (no sync inference); **`POST .../gpu-fit`**, **`POST .../vton/try-on`**, **`POST .../vton/rebuild`** → see [docs/API.md](docs/API.md) |
| **Python** | **`POST /infer/pose`** (JSON + multipart), **`POST /infer/tryon`**, **`POST /infer/fit`** (stubs + scan path wired) |
| **Mobile** | Prior **expo-camera** shells remain; **update clients** to Echo + **202** pose flow when you wire the UI. |

### Remaining (product / ML)

| Item | Why |
|------|-----|
| **Native AR + cloth** | Unity UaaL or Swift/Kotlin; not solvable in PHP/Python API alone. |
| **Real CUDA + TensorRT** | Replace stub branches inside **`ai-service`**; deploy **`GPU_SERVICE_URL`** on NVIDIA instances. |
| **Production Reverb** | Run **`php artisan reverb:start`** behind TLS; configure **`REVERB_*`** and **`BROADCAST_CONNECTION=reverb`**. |
| **DCC asset pipeline** | Replace stub **`model.glb`** bytes in **`VtonAssetPipelineJob`** with Blender/Marvelous automation; set **`mesh_cdn_url`** (CloudFront). |
| **SMPL / 3D avatar** | Still optional extension; not in Phase 1–2. |

**Queues (required worker):**  
`php artisan queue:work redis --queue=scans,fits,vton-gpu,assets,default`  

**Local dev (`composer dev`):** serves API, **queue worker**, **Reverb**, Vite, and Pail (see `backend/composer.json`).

## What was implemented

| Area | Details |
|------|---------|
| **Backend (Laravel 12)** | REST API under `/api`; **Sanctum** token auth (`register`, `login`, `logout`, `user`). |
| **Database** | Migrations for `users`, `scans`, `measurements`, `garments`, `fit_recommendations`, plus Sanctum `personal_access_tokens` and queue `jobs`. Postgres-ready; SQLite works for quick local use. |
| **Scan flow** | `POST /scans` saves **front**, optional **side**, and optional **right shoulder / left shoulder / chest** images; **`ScanBodyJob`** calls the GPU **`/infer/pose`** path and persists **`Measurement`** (meta may include fusion + ML info in `gpu_debug`). |
| **Fit recommendations** | **`GenerateFitRecommendationJob`** compares latest user measurements to each garment’s **JSON size chart**; `POST /garments/{id}/recommend` + poll `GET .../fit-recommendation`. |
| **AI service (FastAPI)** | **Multiview fusion** (`app/pipeline.py`): front image sets scale; extra angles contribute shoulder/hip pixel stats when pose + frame fill look valid. **ML calibration** (`models/size_residual.npz`): **Ridge-style linear residual** on 15 engineered features (trained on **synthetic** data — see script below). **MediaPipe Pose** + **OpenCV** + height scaling; blur / resolution checks. |
| **Mobile (Expo)** | **Voice-guided capture** (`expo-speech`): speaks steps for front → right shoulder → left shoulder → chest → optional side. **Capture** screen = stepper + “Repeat voice” + multipart upload matching API fields. **AsyncStorage** for token. |
| **Docker** | `docker-compose.yml`: **Postgres**, **Redis**, **python-ai-service**, **laravel-app** (migrations, **`storage` volume**, **`queue:work` + `artisan serve`** in one container via `backend/docker/entrypoint.sh`). |
| **Docs** | **[docs/API.md](docs/API.md)**; Phase 3 **[docs/ARCHITECTURE_VIRTUAL_TRYON.md](docs/ARCHITECTURE_VIRTUAL_TRYON.md)** + deployment + WebSocket guides. |
| **Seed data** | Test user `test@example.com` / `password`; sample **garments** with size charts. |
| **Phase 3 backend** | **Reverb**, **GPU `GpuInferenceClient`**, **ScanBodyJob / VtonGpuJob / FitPredictionJob / VtonAssetPipelineJob / RealtimePoseInferenceJob**, **FitUpdated / PoseUpdated / VtonProgress**, **`/infer/*`** on Python service (see Phase 3 section). |

**Phase 1–2 gap vs some briefs:** There was **no SMPL / 3D avatar** in the original MVP—only 2D pose + size charts. **Phase 3** documentation and garment fields are the extension point for avatars + GLB.

**Still not production-complete:** full **AR cloth**, **PhysX**, **checkout**, and **S3/CDN** (local disk first; Phase 3 docs describe how to add them).

## How this was built (step-by-step)

1. **Voice assistant (text-to-speech)**  
   The app uses **`expo-speech`** to read scripted instructions when each capture step opens. Phrases live in **`mobile/src/voice/guidedCapturePhrases.ts`**. This is **TTS guidance** (not speech recognition): the user still taps to open the camera.

2. **Guided multiview photos**  
   **`mobile/src/screens/CaptureScreen.tsx`** walks through: full front (required) → right shoulder → left shoulder → chest → optional side. Files are sent as `front_image`, `right_shoulder_image`, `left_shoulder_image`, `chest_detail_image`, `side_image`.

3. **Laravel storage & queue**  
   Migration adds **`right_shoulder_path`**, **`left_shoulder_path`**, **`chest_detail_path`** on **`scans`**. **`ScanController`** stores files under `storage/app/public/scans/{id}/`. **`ScanBodyJob`** sends images to the GPU service **`POST /infer/pose`** and saves the response into **`measurements.meta`**.

4. **Python fusion + ML**  
   **`ai-service/app/pipeline.py`** merges front geometry with auxiliary poses (ignores crops that are too tight). **`ai-service/app/features.py`** builds a fixed feature vector. **`ai-service/app/ml_calibration.py`** loads **`models/size_residual.npz`** (weight matrix **W**) and adds a learned **residual** to chest/waist/hips/shoulder. If the file is missing, predictions stay **heuristic-only**.

5. **Training the calibration weights**  
   Synthetic data only (no real body scans in-repo):

   ```powershell
   cd ai-service
   python scripts/train_size_calibration.py
   ```

   Regenerates **`ai-service/models/size_residual.npz`**. Optional env: **`SIZE_ML_NPZ_PATH`** to point at another weights file.

6. **Docker**  
   **`ai-service/Dockerfile`** copies **`models/`** so the container includes the NPZ.

## What’s remaining / roadmap

| Item | Notes |
|------|--------|
| **Real-world ML** | Current Ridge weights are trained on **synthetic** proportions. For production accuracy, collect **labeled** (images or landmarks → tape-measured cm), retrain, and version the NPZ (or export ONNX / sklearn). |
| **Hands-free voice** | Add **speech-to-text** (e.g. native APIs or a STT library) if you want “take picture” by voice command; today only **spoken prompts** are implemented. |
| **Laravel 13** | Needs **PHP 8.3+** on the host. |
| **S3 + CDN** | `FILESYSTEM_DISK=s3`, CloudFront/public URL. |
| **Docker / ops** | nginx + php-fpm, dedicated workers, Horizon, monitoring. |
| **Product & commerce** | Cart, checkout, inventory, admin. |
| **Auth & security** | Email verification, password reset, rate limits, CORS for Expo web. |
| **Tests & CI** | API + mobile E2E, `npm audit` follow-up. |
| **Mobile release** | EAS Build, store assets, optional on-device pose (faster preview). |

## Quick start (local, without Docker)

### 1. PostgreSQL + Redis

- Create database `online_shopping` (or use SQLite; see `backend/.env.example`).
- Start Redis for queues.

### 2. Laravel (`backend/`)

```powershell
cd backend
copy .env.example .env
php artisan key:generate
```

Set in `.env`:

- `DB_*` for PostgreSQL (or `DB_CONNECTION=sqlite` + `DB_DATABASE=database/database.sqlite`).
- `QUEUE_CONNECTION=redis`, `REDIS_CLIENT=predis`, `REDIS_HOST=127.0.0.1`.
- `AI_SERVICE_URL=http://127.0.0.1:8088`.
- `FILESYSTEM_DISK=public`.

Then:

```powershell
php artisan migrate
php artisan db:seed
php artisan storage:link
php artisan serve
```

In **another terminal** (and run **Reverb** if you use `BROADCAST_CONNECTION=reverb`):

```powershell
cd backend
php artisan queue:work redis --queue=scans,fits,vton-gpu,assets,default
# Optional WebSockets:
# php artisan reverb:start
```

### 3. AI service (`ai-service/`)

```powershell
cd ai-service
python -m venv .venv
.\.venv\Scripts\activate
pip install -r requirements.txt
uvicorn app.main:app --reload --host 0.0.0.0 --port 8088
```

After cloning, generate ML weights once (optional if `models/size_residual.npz` is already present):

```powershell
cd ai-service
python scripts/train_size_calibration.py
```

### 4. Mobile (`mobile/`)

```powershell
cd mobile
npm install
```

Set the API URL for your device:

- **Android emulator**: `http://10.0.2.2:8000/api` (already in `app.json` under `extra.apiUrl`).
- **Physical device**: use your PC LAN IP, e.g. `http://192.168.1.50:8000/api`, via env:

```powershell
$env:EXPO_PUBLIC_API_URL="http://192.168.1.50:8000/api"; npx expo start
```

Seed login (from `DatabaseSeeder`): `test@example.com` / `password`.

## Docker

From the repo root:

```powershell
docker compose up --build
```

- API: `http://localhost:8000/api`
- AI: `http://localhost:8088/health`
- The Laravel container runs `queue:work` in the background from `backend/docker/entrypoint.sh` so scan jobs stay non-blocking.

## Production notes

- Replace local disk with **S3-compatible** storage: configure `config/filesystems.php` `s3` disk, set `FILESYSTEM_DISK=s3`, and front URLs via `AWS_URL` / CloudFront.
- Run **multiple queue workers** and **horizon** when you outgrow `queue:work`.
- The stack is still **2D imaging** + statistical calibration; treat numbers as **MVP / fashion guidance**, not medical or bespoke tailoring.

Full request/response examples: [docs/API.md](docs/API.md).
