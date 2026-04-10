# Production deployment sketch (AWS / GCP / Kubernetes)

This is a **reference layout** for scaling Phase 3. Tune to your compliance and regions.

## Kubernetes (generic)

| Deployment | Replicas | Notes |
|------------|----------|--------|
| `laravel-api` | 3+ | Horizon for queues; `php-fpm` + nginx ingress. |
| `laravel-reverb` or `soketi` | 2+ | WebSocket; sticky sessions or Redis adapter. |
| `ai-gpu` | 1–N node pool **g4dn / A10G** | CUDA image; **HPA** on queue depth, not HTTP QPS. |
| `worker-gpu` | Same pool | `ProcessClothSimulationJob` style workers. |
| `redis` | Managed ElastiCache / Memorystore | Queues + pub/sub for WS. |
| `postgres` | RDS / Cloud SQL + read replica | Migrations from Laravel. |

## AWS (example)

- **EKS** + **node groups**: `system`, `cpu`, `gpu` (taints: `nvidia.com/gpu=true`).
- **S3** buckets: `meshes`, `textures`, `user-recordings`; **CloudFront** with signed URLs.
- **API Gateway** or **ALB** → Laravel; **ALB sticky** for WebSockets or use **Reverb with Redis** adapter.
- **SQS** optional instead of Redis for cross-region (Laravel supports both patterns).

## GPU container

- Base: `nvidia/cuda:12.x-runtime-ubuntu22.04`.
- Install Python + PyTorch **cu121** wheel matching driver on node.
- **TensorRT**: convert ONNX after training; load in Triton or custom FastAPI sidecar.

## CI/CD

- Build **three** images: `laravel`, `ai-cpu` (dev), `ai-gpu` (prod).
- **Separate** deploy pipelines: API daily; GPU only on model change.

## Observability

- OpenTelemetry traces: Laravel → AI calls; GPU **NVML** metrics in Prometheus.
- Log **p95** pose latency and **GPU util**; alert on queue backlog.
