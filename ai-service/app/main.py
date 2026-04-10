from __future__ import annotations

from typing import Any

import cv2
import numpy as np
from fastapi import Body, FastAPI, File, Form, HTTPException, Request, UploadFile
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from starlette.datastructures import UploadFile as StarletteUploadFile

from app.pipeline import estimate_multiview
from app.realtime_pose import realtime_pose_response

app = FastAPI(title="Body Measurement AI", version="1.3.0")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)


def _decode_bgr(raw: bytes) -> np.ndarray:
    if not raw:
        raise ValueError("empty file")
    data = np.frombuffer(raw, dtype=np.uint8)
    img = cv2.imdecode(data, cv2.IMREAD_COLOR)
    if img is None:
        raise ValueError("could not decode image")
    return img


async def _optional_bgr(upload: UploadFile | None) -> np.ndarray | None:
    if upload is None:
        return None
    raw = await upload.read()
    if not raw:
        return None
    try:
        return _decode_bgr(raw)
    except ValueError:
        return None


async def _run_body_scan_multipart(
    height_cm: str,
    front_image: UploadFile,
    side_image: UploadFile | None,
    right_shoulder_image: UploadFile | None,
    left_shoulder_image: UploadFile | None,
    chest_detail_image: UploadFile | None,
) -> dict[str, Any]:
    try:
        h = float(height_cm)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail="height_cm must be a number") from exc

    if h < 120 or h > 230:
        raise HTTPException(status_code=422, detail="height_cm out of supported range")

    try:
        front_raw = await front_image.read()
        front_bgr = _decode_bgr(front_raw)
    except ValueError as exc:
        raise HTTPException(status_code=422, detail=f"front_image invalid: {exc}") from exc

    auxiliary: dict[str, np.ndarray] = {}
    rs = await _optional_bgr(right_shoulder_image)
    if rs is not None:
        auxiliary["right_shoulder"] = rs
    ls = await _optional_bgr(left_shoulder_image)
    if ls is not None:
        auxiliary["left_shoulder"] = ls
    ch = await _optional_bgr(chest_detail_image)
    if ch is not None:
        auxiliary["chest"] = ch
    side_bgr = await _optional_bgr(side_image)
    if side_bgr is not None:
        auxiliary["side_profile"] = side_bgr

    try:
        measurements, confidence, meta = estimate_multiview(front_bgr, h, auxiliary)
    except RuntimeError as exc:
        raise HTTPException(status_code=422, detail=str(exc)) from exc

    side_meta: dict[str, Any] | None = None
    if side_bgr is not None:
        try:
            side_meta = {
                "provided": True,
                "side_blur_score": float(
                    cv2.Laplacian(cv2.cvtColor(side_bgr, cv2.COLOR_BGR2GRAY), cv2.CV_64F).var()
                ),
            }
            if side_meta["side_blur_score"] < 60:
                confidence = round(max(0.05, confidence * 0.9), 4)
        except Exception:
            side_meta = {"provided": True, "error": "side processing"}

    return {
        "measurements": measurements,
        "confidence": confidence,
        "image_quality": meta.get("image_quality"),
        "debug": {
            "landmarks": meta.get("landmarks_used"),
            "fusion": meta.get("fusion"),
            "ml_model_version": meta.get("ml_model_version"),
            "ml_residual": meta.get("ml_residual"),
            "side": side_meta,
        },
    }


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok"}


class RealtimePoseIn(BaseModel):
    frame_base64: str | None = None
    timestamp_ms: int | None = Field(default=None, ge=0)
    device_fps: float | None = Field(default=None, ge=1, le=120)
    user_id: int | None = None


@app.post("/v1/realtime-pose")
def realtime_pose(body: RealtimePoseIn) -> dict[str, Any]:
    """Legacy JSON stub — prefer POST /infer/pose (JSON) via GPU client."""
    return realtime_pose_response(body.model_dump(exclude_none=True))


@app.post("/v1/estimate-measurements")
async def estimate_measurements(
    height_cm: str = Form(...),
    front_image: UploadFile = File(...),
    side_image: UploadFile | None = File(None),
    right_shoulder_image: UploadFile | None = File(None),
    left_shoulder_image: UploadFile | None = File(None),
    chest_detail_image: UploadFile | None = File(None),
) -> dict[str, Any]:
    return await _run_body_scan_multipart(
        height_cm,
        front_image,
        side_image,
        right_shoulder_image,
        left_shoulder_image,
        chest_detail_image,
    )


# --- Phase 3 GPU contract (PyTorch/CUDA + TensorRT later) ---


@app.post("/infer/pose")
async def infer_pose(request: Request) -> dict[str, Any]:
    """
    Multipart: same as body scan (measurements). JSON: lightweight realtime stub (skeleton).
    """
    ct = (request.headers.get("content-type") or "").lower()
    if "application/json" in ct:
        raw = await request.json()
        if not isinstance(raw, dict):
            raw = {}
        out = realtime_pose_response(raw)
        out["inference"] = "gpu_pose_json_stub"
        return out

    form = await request.form()
    height_cm = form.get("height_cm")
    if height_cm is None or not isinstance(height_cm, str):
        raise HTTPException(status_code=422, detail="height_cm required")

    front = form.get("front_image")
    if front is None or not isinstance(front, (StarletteUploadFile, UploadFile)):
        raise HTTPException(status_code=422, detail="front_image required")

    async def as_upload(x: Any) -> UploadFile | None:
        if x is None or not isinstance(x, (StarletteUploadFile, UploadFile)):
            return None
        return x  # type: ignore[return-value]

    side = await as_upload(form.get("side_image"))
    rs = await as_upload(form.get("right_shoulder_image"))
    ls = await as_upload(form.get("left_shoulder_image"))
    chest = await as_upload(form.get("chest_detail_image"))

    out = await _run_body_scan_multipart(height_cm, front, side, rs, ls, chest)
    out["skeleton"] = None
    out["body_state"] = "static_scan"
    out["inference"] = "gpu_pose_multipart_cpu_pipeline"
    return out


@app.post("/infer/tryon")
def infer_tryon(body: dict[str, Any] = Body(default_factory=dict)) -> dict[str, Any]:
    """Stub — replace with CUDA garment warping / deferred texture."""
    return {
        "status": "stub",
        "garment_id": body.get("garment_id"),
        "user_id": body.get("user_id"),
        "message": "Wire PyTorch try-on model; TensorRT export optional",
    }


@app.post("/infer/fit")
def infer_fit(body: dict[str, Any] = Body(default_factory=dict)) -> dict[str, Any]:
    """Stub — replace with learned fit / heatmap model."""
    measurements = body.get("measurements")
    return {
        "status": "stub",
        "garment_id": body.get("garment_id"),
        "heatmap": "not_computed",
        "score": None,
        "has_measurements": measurements is not None,
        "message": "Wire ML fit head; train on labeled try-on data",
    }
