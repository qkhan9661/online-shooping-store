"""
Multiview fusion + ML residual calibration.

Front image provides the scale (cm/px) from full-body height span.
Auxiliary shots (shoulders/chest) can increase effective shoulder pixel span when
the torso fills enough of the frame (avoids meaningless zoomed crops).
"""

from __future__ import annotations

import statistics
from typing import Any

import cv2
import numpy as np

from app.features import build_feature_vector
from app.ml_calibration import apply_residual_adjustment
from app.pose_core import process_pose_frame


def _quality(bgr: np.ndarray) -> tuple[float, int]:
    gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
    blur = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    h, w = bgr.shape[:2]
    return blur, min(h, w)


def estimate_multiview(
    front_bgr: np.ndarray,
    height_cm: float,
    auxiliary: dict[str, np.ndarray],
) -> tuple[dict[str, float], float, dict[str, Any]]:
    blur, min_dim = _quality(front_bgr)
    rgb_front = cv2.cvtColor(front_bgr, cv2.COLOR_BGR2RGB)

    front_result = process_pose_frame(rgb_front, height_cm, blur, min_dim)
    if front_result is None:
        raise RuntimeError("No pose detected in front image")

    base_meas, conf, front_meta = front_result
    scale = float(front_meta["cm_per_px"])
    body_px_front = float(front_meta["body_height_px"])
    sh_front = float(front_meta["shoulder_px"])
    hip_front = float(front_meta["hips_px"])

    shoulder_candidates = [sh_front]
    hip_candidates = [hip_front]
    aux_debug: dict[str, Any] = {}
    n_aux_valid = 0

    for name, bgr in auxiliary.items():
        if bgr is None or bgr.size == 0:
            continue
        b_blur, b_min = _quality(bgr)
        rgb = cv2.cvtColor(bgr, cv2.COLOR_BGR2RGB)
        h_img = bgr.shape[0]
        res = process_pose_frame(rgb, height_cm, b_blur, b_min)
        if res is None:
            aux_debug[name] = {"pose": False}
            continue
        _m, _c, meta = res
        body_px = float(meta["body_height_px"])
        fill_ratio = body_px / max(1, h_img)
        # Only trust pixel spans when a large vertical body segment is visible
        if fill_ratio >= 0.28:
            shoulder_candidates.append(float(meta["shoulder_px"]))
            hip_candidates.append(float(meta["hips_px"]))
            n_aux_valid += 1
            aux_debug[name] = {"pose": True, "fill_ratio": round(fill_ratio, 3)}
        else:
            aux_debug[name] = {"pose": True, "fill_ratio": round(fill_ratio, 3), "ignored": "crop_too_tight"}

    shoulder_px_max = max(shoulder_candidates)
    hips_px_mean = float(statistics.mean(hip_candidates)) if hip_candidates else hip_front
    shoulder_px_std = float(statistics.pstdev(shoulder_candidates)) if len(shoulder_candidates) > 1 else 0.0

    fused = {
        "shoulder_width_cm": round(shoulder_px_max * scale, 2),
        "hips_cm": round(hips_px_mean * scale, 2),
        "chest_cm": round(shoulder_px_max * scale * 1.10, 2),
        "waist_cm": round(hips_px_mean * scale * 0.93, 2),
    }

    # Slight confidence boost when multiple angles agree
    if n_aux_valid >= 2:
        conf = min(1.0, round(conf * 1.06, 4))
    if shoulder_px_std > 0 and shoulder_px_std / max(1.0, sh_front) > 0.25:
        conf = max(0.05, round(conf * 0.92, 4))

    features = build_feature_vector(
        fused,
        {**front_meta, "shoulder_px": sh_front, "hips_px": hip_front},
        height_cm,
        n_aux_valid,
        shoulder_px_max,
        shoulder_px_std,
        hips_px_mean,
    )

    final_meas, ml_meta = apply_residual_adjustment(fused, features)

    meta_out: dict[str, Any] = {
        "image_quality": {
            "blur_score": blur,
            "min_dimension": min_dim,
            "is_acceptable": min_dim >= 480 and blur >= 80.0,
        },
        "landmarks_used": {
            "shoulder_px_front": round(sh_front, 2),
            "shoulder_px_max": round(shoulder_px_max, 2),
            "hips_px_mean": round(hips_px_mean, 2),
            "body_height_px": round(body_px_front, 2),
            "cm_per_px": round(scale, 6),
            "auxiliary_valid_count": n_aux_valid,
        },
        "fusion": {
            "heuristic_before_ml": fused,
            "auxiliary": aux_debug,
        },
        **ml_meta,
    }

    return final_meas, conf, meta_out
