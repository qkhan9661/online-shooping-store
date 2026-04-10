"""
Backward-compatible entry: single front image, no auxiliary views.
Prefer app.pipeline.estimate_multiview for production.
"""

from __future__ import annotations

import cv2
import numpy as np

from app.pipeline import estimate_multiview


def laplacian_blur_score(bgr: np.ndarray) -> float:
    gray = cv2.cvtColor(bgr, cv2.COLOR_BGR2GRAY)
    return float(cv2.Laplacian(gray, cv2.CV_64F).var())


def estimate_from_front(bgr: np.ndarray, height_cm: float) -> tuple[dict[str, float], float, dict]:
    """Delegates to multiview pipeline with no auxiliary images."""
    return estimate_multiview(bgr, height_cm, {})
