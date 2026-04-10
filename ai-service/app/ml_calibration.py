"""
Residual calibration: learned linear (Ridge) adjustment on top of heuristics.

Artifact: models/size_residual.npz (weights W: (1+n_features, 4), bias in row 0).
Train with: python scripts/train_size_calibration.py

Optional: SIZE_ML_MODEL_PATH override; if no file, heuristic-only.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

import numpy as np

_NPZ: np.lib.npyio.NpzFile | None = None


def _npz_path() -> Path:
    raw = os.environ.get("SIZE_ML_NPZ_PATH", "")
    if raw:
        return Path(raw)
    return Path(__file__).resolve().parent.parent / "models" / "size_residual.npz"


def _load_npz() -> np.lib.npyio.NpzFile | None:
    global _NPZ
    if _NPZ is not None:
        return _NPZ
    path = _npz_path()
    if not path.is_file():
        return None
    _NPZ = np.load(path)
    return _NPZ


def apply_residual_adjustment(
    base: dict[str, float],
    features: np.ndarray,
) -> tuple[dict[str, float], dict[str, Any]]:
    """
    Adds learned residual to chest, waist, hips, shoulder (clamped).
    """
    meta: dict[str, Any] = {}
    data = _load_npz()
    if data is None:
        meta["ml_model_version"] = "heuristic_only"
        return base, meta

    try:
        W = data["W"]
        x = np.concatenate([[1.0], features.ravel()])
        residual = x @ W
        keys = ["chest_cm", "waist_cm", "hips_cm", "shoulder_width_cm"]
        out = {}
        for i, k in enumerate(keys):
            v = float(base[k]) + float(residual[i])
            out[k] = round(max(40.0, min(200.0, v)), 2)
        mv = data.get("model_version")
        if mv is not None:
            meta["ml_model_version"] = str(mv.item()) if hasattr(mv, "item") else str(mv)
        else:
            meta["ml_model_version"] = "size_residual_npz"
        meta["ml_residual"] = {keys[i]: round(float(residual[i]), 3) for i in range(len(keys))}
        return out, meta
    except Exception as e:  # pragma: no cover
        meta["ml_error"] = str(e)
        meta["ml_model_version"] = "error"
        return base, meta
