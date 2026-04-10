"""
Stub real-time pose API for Phase 3. Replace with GPU MediaPipe / MMPose / TensorRT model.

Target contract matches Laravel RealtimePoseGateway expectations.
"""

from __future__ import annotations

import time
from typing import Any


def build_stub_skeleton() -> list[list[float]]:
    """33 MediaPipe-like landmarks, normalized x,y,z in [0,1] (placeholder)."""
    pts: list[list[float]] = []
    for i in range(33):
        u = 0.5 + 0.02 * (i % 7)
        v = 0.3 + 0.01 * (i // 7)
        pts.append([min(1.0, u), min(1.0, v), 0.0])
    return pts


def realtime_pose_response(payload: dict[str, Any]) -> dict[str, Any]:
    ts = payload.get("timestamp_ms")
    now = time.time() * 1000.0
    return {
        "skeleton": build_stub_skeleton(),
        "confidence": 0.95,
        "body_state": "static_stub",
        "server_ts_ms": now,
        "client_ts_ms": ts,
        "mode": "cpu_stub",
        "note": "Swap for CUDA pose + temporal smoothing; prefer on-device ARKit/ARCore for <50ms.",
    }
