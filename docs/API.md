# REST API reference

Base URL: `{APP_URL}/api` (e.g. `http://localhost:8000/api`).

Authentication: `Authorization: Bearer {token}` (Laravel Sanctum personal access token), except where noted.

## Auth

### `POST /register`

**Request (JSON)**

```json
{
  "name": "Alex Doe",
  "email": "alex@example.com",
  "password": "secret1234"
}
```

**Response `201`**

```json
{
  "user": { "id": 1, "name": "Alex Doe", "email": "alex@example.com" },
  "token": "1|xxxxxxxxxxxxxxxx",
  "token_type": "Bearer"
}
```

### `POST /login`

**Request (JSON)**

```json
{
  "email": "test@example.com",
  "password": "password"
}
```

**Response `200`**: same shape as register.

### `POST /logout` (auth)

**Response `200`**

```json
{ "message": "Logged out" }
```

### `GET /user` (auth)

**Response `200`**: user object.

---

## Body scan

### `POST /scans` (auth)

`multipart/form-data`

| Field        | Type   | Required |
|-------------|--------|----------|
| `height_cm` | number | yes      |
| `front_image` | file | yes   |
| `side_image`  | file | no    |
| `right_shoulder_image` | file | no |
| `left_shoulder_image` | file | no |
| `chest_detail_image` | file | no |

Auxiliary shots are forwarded to the AI service for **multiview fusion** (shoulder span, confidence). Omit any you did not capture.

**Response `202`**

```json
{
  "message": "Scan queued",
  "scan": {
    "id": 3,
    "status": "pending",
    "height_cm": 175,
    "ai_confidence": null,
    "error_message": null,
    "processed_at": null,
    "front_image_url": "http://localhost:8000/storage/scans/3/front.jpg",
    "side_image_url": null,
    "right_shoulder_image_url": null,
    "left_shoulder_image_url": null,
    "chest_detail_image_url": null,
    "measurement": null
  }
}
```

The API returns immediately; **`ScanBodyJob`** (queue **`scans`**) calls **`POST {GPU_SERVICE_URL}/infer/pose`** (multipart) and writes a **`measurements`** row, then broadcasts **`PoseUpdated`** on **`user.{id}.pose`**.

### `GET /scans/{id}` (auth)

**Response `200` (completed example)**

```json
{
  "id": 3,
  "status": "completed",
  "height_cm": 175,
  "ai_confidence": 0.8125,
  "error_message": null,
  "processed_at": "2026-04-10T16:00:00+00:00",
  "front_image_url": "http://localhost:8000/storage/scans/3/front.jpg",
  "side_image_url": null,
  "measurement": {
    "id": 1,
    "user_id": 1,
    "scan_id": 3,
    "chest_cm": "98.50",
    "waist_cm": "82.10",
    "hips_cm": "101.20",
    "shoulder_width_cm": "44.30",
    "confidence": "0.8125",
    "meta": { "image_quality": { "blur_score": 120.4, "is_acceptable": true } }
  }
}
```

---

## Measurements

### `GET /measurements/latest` (auth)

**Response `200`**: latest measurement row.

**Response `404`**: `{ "message": "No measurements yet" }`

### `GET /measurements` (auth)

Paginated list (`data`, `links`, `meta`).

---

## Catalog & fit

### `GET /garments` (auth)

Paginated garments.

### `GET /garments/{id}` (auth)

**Response `200`**

```json
{
  "id": 1,
  "sku": "TEE-001",
  "name": "Essential Cotton Tee",
  "description": "Relaxed everyday tee with soft hand-feel.",
  "price": 29.99,
  "image_url": null,
  "size_chart": {
    "S": { "chest": 88, "waist": 72, "hips": 94, "shoulder": 40 },
    "M": { "chest": 94, "waist": 78, "hips": 100, "shoulder": 42 }
  },
  "fit_recommendation": null,
  "vton": {
    "asset_pipeline_status": "pending",
    "mesh_glb_url": null,
    "mesh_ar_lod_url": null,
    "mesh_cdn_url": null,
    "physics_profile": null
  }
}
```

### `POST /garments/{id}/vton/rebuild` (auth)

Queues **`VtonAssetPipelineJob`** on Redis queue **`assets`**: writes `garments/{id}/model.glb` (stub) + `physics_profile.json` to the default filesystem disk (`FILESYSTEM_DISK=s3` + CloudFront in production). Broadcasts **`VtonProgress`** on private channel `user.{id}.cloth` when triggered by an authenticated user.

**Response `202`**

```json
{
  "message": "VTON asset pipeline queued",
  "garment_id": 1,
  "queue": "assets"
}
```

### `POST /garments/{id}/gpu-fit` (auth)

Queues **`FitPredictionJob`** on **`vton-gpu`**: HTTP `POST {GPU_SERVICE_URL}/infer/fit` (stub today). Broadcasts **`FitUpdated`** (`kind`: `gpu_fit`) on private channel `user.{id}`.

**Response `202`**

```json
{
  "message": "GPU fit prediction queued",
  "garment_id": 1,
  "queue": "vton-gpu"
}
```

### `POST /garments/{id}/vton/try-on` (auth)

JSON body (optional):

```json
{ "scan_id": 12 }
```

Queues **`VtonGpuJob`** on **`vton-gpu`**: HTTP `POST {GPU_SERVICE_URL}/infer/tryon`. Broadcasts **`VtonProgress`** on `user.{id}.cloth`.

**Response `202`**

```json
{
  "message": "VTON try-on inference queued",
  "garment_id": 1,
  "queue": "vton-gpu"
}
```

### `POST /garments/{id}/recommend` (auth)

Queues `GenerateFitRecommendationJob` (non-blocking). On completion, broadcasts **`FitUpdated`** with `kind` **`size_chart`** on `user.{id}`.

**Response `202`**

```json
{
  "message": "Fit recommendation queued (size chart); listen for FitUpdated kind size_chart",
  "fit_recommendation": {
    "id": 2,
    "user_id": 1,
    "garment_id": 1,
    "status": "pending",
    "recommended_size": null,
    "fit_notes": null,
    "confidence": null,
    "measurement_snapshot": null
  }
}
```

### `GET /garments/{id}/fit-recommendation` (auth)

**Response `200` (completed)**

```json
{
  "id": 2,
  "user_id": 1,
  "garment_id": 1,
  "status": "completed",
  "recommended_size": "M",
  "fit_notes": "Chest: good fit; Waist: slightly loose; Hips: good fit; Shoulders: good fit",
  "confidence": "0.8542",
  "measurement_snapshot": {
    "chest_cm": 98.5,
    "waist_cm": 82.1,
    "hips_cm": 101.2,
    "shoulder_width_cm": 44.3,
    "details": { "score_by_size": { "S": 12.4, "M": 3.1 }, "best_score": 3.1 }
  }
}
```

---

## Real-time pose (Phase 3)

### `POST /ai/realtime-pose` (auth)

**Orchestration only:** dispatches **`RealtimePoseInferenceJob`** on queue **`default`**. The job calls **`POST {GPU_SERVICE_URL}/infer/pose`** with JSON (no inference in PHP). Subscribe (Laravel Echo + Reverb) to private channel **`user.{id}.pose`** for event **`pose.updated`** (`PoseUpdated`).

JSON body (optional fields):

```json
{
  "frame_base64": null,
  "timestamp_ms": 1710000000000,
  "device_fps": 30
}
```

**Response `202`**

```json
{
  "message": "Pose inference queued; subscribe to private channel user.{id}.pose for PoseUpdated"
}
```

### WebSocket auth

- **POST `/broadcasting/auth`** — Sanctum (`auth:sanctum`); send Bearer token or session cookie per Laravel broadcasting docs.
- **Private channels:** `user.{userId}`, `user.{userId}.pose`, `user.{userId}.cloth`

### Broadcast events (server → client)

| Event name (client) | Laravel class | Typical channel |
|---------------------|---------------|----------------|
| `fit.updated` | `FitUpdated` | `private-user.{id}` |
| `pose.updated` | `PoseUpdated` | `private-user.{id}.pose` |
| `vton.progress` | `VtonProgress` | `private-user.{id}.cloth` |

---

## Python AI service

### GPU contract (Phase 3)

| Method | Path | Role |
|--------|------|------|
| POST | `/infer/pose` | JSON: realtime skeleton stub; **multipart**: same as body scan → measurements (CPU MediaPipe path today; swap internals for CUDA). |
| POST | `/infer/tryon` | Virtual try-on stub (wire PyTorch + TensorRT later). |
| POST | `/infer/fit` | Fit / heatmap stub. |

`GPU_SERVICE_URL` defaults to `AI_SERVICE_URL` for local monolith-style dev.

### `POST /v1/realtime-pose`

Legacy JSON stub; prefer **`POST /infer/pose`** with JSON for new Laravel GPU client.

### `POST /v1/estimate-measurements`

`multipart/form-data`: `height_cm` (string/number), `front_image` (file), optional `side_image` (file).

**Response `200`**

```json
{
  "measurements": {
    "chest_cm": 98.5,
    "waist_cm": 82.1,
    "hips_cm": 101.2,
    "shoulder_width_cm": 44.3
  },
  "confidence": 0.8125,
  "image_quality": {
    "blur_score": 120.4,
    "min_dimension": 720,
    "is_acceptable": true
  },
  "debug": { "landmarks": { "shoulder_px": 210.5, "hips_px": 198.2 }, "side": null }
}
```
