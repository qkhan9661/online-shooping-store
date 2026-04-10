# WebSockets & real-time streaming (integration guide)

## Recommended stack (Laravel ecosystem)

1. **Laravel Reverb** (first-party WebSocket server) + **Laravel Echo** on clients.
2. Alternative: **Soketi** (compatible Pusher protocol) behind **Redis** for multi-instance.

## What to broadcast

| Channel | Payload (example) | Producer |
|---------|---------------------|----------|
| `private-user.{id}.pose` | Normalized skeleton, timestamps | AI GPU service or edge worker |
| `private-user.{id}.cloth` | Mesh bone deltas, LOD | Unity/native runtime (optional relay) |

## Hot path rule

Do **not** route 60 FPS pose through **synchronous** Laravel HTTP. Options:

- **A)** Pose stays **on-device** (ARKit Body Tracking); WebSocket used for **catalog sync / fit scores** only.
- **B)** Binary frames to **Node/Go gateway** → Redis pub/sub → subscribers; Laravel **only** authenticates channel access via **signed tokens**.

## Repo status

- **`laravel/reverb`** is a Composer dependency; `config/reverb.php` is published. Run **`php artisan reverb:start`** alongside the API in development/production.
- Set **`BROADCAST_CONNECTION=reverb`** (and `VITE_REVERB_*` for Echo on web). For CI without sockets, use **`BROADCAST_CONNECTION=log`**.
- **`routes/channels.php`** authorizes **`user.{id}`**, **`user.{id}.pose`**, **`user.{id}.cloth`** (Echo names them `private-user.{id}` etc.).
- **`POST /api/ai/realtime-pose`** returns **202** and queues **`RealtimePoseInferenceJob`**; clients should subscribe to **`pose.updated`** on **`private-user.{id}.pose`** (not synchronous JSON).
- See **[PHASE3_BACKEND_ARCHITECTURE.md](PHASE3_BACKEND_ARCHITECTURE.md)** for jobs, queues, and GPU endpoints.
