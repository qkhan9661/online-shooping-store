"""
Shared MediaPipe pose extraction and geometric baseline measurements.
Used by the multiview pipeline and ML feature builder.
"""

from __future__ import annotations

import math
from typing import Any

import numpy as np

try:
    import mediapipe as mp  # type: ignore
except ImportError:  # pragma: no cover
    mp = None


def _dist_px(a: tuple[float, float], b: tuple[float, float]) -> float:
    return float(math.hypot(a[0] - b[0], a[1] - b[1]))


def process_pose_frame(
    rgb: np.ndarray,
    height_cm: float,
    blur_score: float,
    min_dimension: int,
) -> tuple[dict[str, float], float, dict[str, Any]] | None:
    """
    Run pose on a single RGB frame. Returns (measurements_dict, confidence, meta) or None if no pose.
    """
    if mp is None:
        raise RuntimeError("mediapipe is not installed")

    h, w = rgb.shape[:2]
    with mp.solutions.pose.Pose(
        static_image_mode=True,
        model_complexity=1,
        enable_segmentation=False,
    ) as pose:
        result = pose.process(rgb)

    if not result.pose_landmarks:
        return None

    lm = result.pose_landmarks.landmark

    def p(idx: int) -> tuple[float, float, float]:
        v = lm[idx]
        return float(v.x), float(v.y), float(v.visibility)

    nose = p(0)
    l_sh = p(11)
    r_sh = p(12)
    l_hip = p(23)
    r_hip = p(24)
    l_ank = p(27)
    r_ank = p(28)
    l_elb = p(13)
    r_elb = p(14)

    visibles = [nose[2], l_sh[2], r_sh[2], l_hip[2], r_hip[2], l_ank[2], r_ank[2], l_elb[2], r_elb[2]]
    visibility_conf = float(min(visibles))

    def px(pt: tuple[float, float, float]) -> tuple[float, float]:
        return pt[0] * w, pt[1] * h

    px_l_sh = px(l_sh)
    px_r_sh = px(r_sh)
    px_l_hip = px(l_hip)
    px_r_hip = px(r_hip)
    px_l_ank = px(l_ank)
    px_r_ank = px(r_ank)
    px_nose = px(nose)

    shoulder_px = _dist_px(px_l_sh, px_r_sh)
    hips_px = _dist_px(px_l_hip, px_r_hip)
    top_y = min(px_nose[1], px_l_sh[1], px_r_sh[1])
    bottom_y = max(px_l_ank[1], px_r_ank[1])
    body_px = max(1.0, bottom_y - top_y)
    scale = float(height_cm) / body_px

    shoulder_cm = shoulder_px * scale
    hips_cm = hips_px * scale
    chest_cm = shoulder_cm * 1.10
    waist_cm = hips_cm * 0.93

    # Elbow span proxy (helps chest estimate when arms not fully down)
    elbow_px = _dist_px(px(l_elb), px(r_elb))
    elbow_cm = elbow_px * scale

    measurements = {
        "chest_cm": round(chest_cm, 2),
        "waist_cm": round(waist_cm, 2),
        "hips_cm": round(hips_cm, 2),
        "shoulder_width_cm": round(shoulder_cm, 2),
    }

    quality_ok = min_dimension >= 480 and blur_score >= 80.0
    conf = visibility_conf
    if not quality_ok:
        conf *= 0.65
    if shoulder_px < 20 or hips_px < 20:
        conf *= 0.55
    conf = float(max(0.05, min(1.0, round(conf, 4))))

    meta = {
        "shoulder_px": shoulder_px,
        "hips_px": hips_px,
        "body_height_px": body_px,
        "cm_per_px": scale,
        "elbow_px": elbow_px,
        "elbow_cm": elbow_cm,
        "visibility_min": visibility_conf,
        "blur_score": blur_score,
        "min_dimension": min_dimension,
    }
    return measurements, conf, meta
