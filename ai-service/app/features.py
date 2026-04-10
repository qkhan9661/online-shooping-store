"""
Feature vector for ML calibration (must match training script column order).
"""

from __future__ import annotations

import numpy as np


def build_feature_vector(
    base: dict[str, float],
    front_meta: dict,
    height_cm: float,
    n_aux_valid: int,
    shoulder_px_max: float,
    shoulder_px_std: float,
    hips_px_mean: float,
) -> np.ndarray:
    """Fixed-order features for Ridge residual prediction."""
    bh = max(1.0, float(front_meta.get("body_height_px", 1.0)))
    sh_px = float(front_meta.get("shoulder_px", 0))
    hip_px = float(front_meta.get("hips_px", 0))
    scale = float(front_meta.get("cm_per_px", 0))

    x = np.array(
        [
            height_cm / 100.0,
            base["shoulder_width_cm"] / 100.0,
            base["chest_cm"] / 100.0,
            base["waist_cm"] / 100.0,
            base["hips_cm"] / 100.0,
            sh_px / bh,
            hip_px / bh,
            shoulder_px_max / max(1.0, sh_px) if sh_px > 1 else 1.0,
            shoulder_px_std / max(1.0, sh_px) if sh_px > 1 else 0.0,
            hips_px_mean / max(1.0, hip_px) if hip_px > 1 else 1.0,
            float(n_aux_valid),
            scale * 1000.0,
            float(front_meta.get("visibility_min", 0)),
            float(front_meta.get("blur_score", 0)) / 500.0,
            float(front_meta.get("elbow_cm", 0)) / 100.0,
        ],
        dtype=np.float64,
    )
    return x.reshape(1, -1)
