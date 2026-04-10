#!/usr/bin/env python3
"""
Train multi-output Ridge (closed form) on synthetic data; saves models/size_residual.npz

No sklearn required — only NumPy. Run:
  cd ai-service && python scripts/train_size_calibration.py
"""

from __future__ import annotations

import sys
from pathlib import Path

import numpy as np

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT))

from app.features import build_feature_vector  # noqa: E402


def synthetic_batch(n: int, seed: int = 42) -> tuple[np.ndarray, np.ndarray]:
    rng = np.random.default_rng(seed)
    X_rows: list[np.ndarray] = []
    Y_rows: list[np.ndarray] = []

    for _ in range(n):
        height_cm = float(rng.uniform(155, 195))
        shoulder_cm = float(rng.normal(42 + (height_cm - 170) * 0.12, 2.2))
        hip_cm = float(rng.normal(98 + (height_cm - 170) * 0.18, 3.5))
        chest_cm = float(rng.normal(shoulder_cm * 1.08 + 4, 2.8))
        waist_cm = float(rng.normal(hip_cm * 0.88, 2.5))
        shoulder_cm = max(36, min(56, shoulder_cm))
        hip_cm = max(78, min(130, hip_cm))
        chest_cm = max(78, min(130, chest_cm))
        waist_cm = max(62, min(115, waist_cm))

        body_px = float(rng.uniform(320, 520))
        noise_s = float(rng.lognormal(0.0, 0.04))
        noise_h = float(rng.lognormal(0.0, 0.04))
        shoulder_px = (shoulder_cm / (height_cm / body_px)) * noise_s
        hips_px = (hip_cm / (height_cm / body_px)) * noise_h
        scale = height_cm / body_px

        base_shoulder = shoulder_px * scale
        base_hips = hips_px * scale
        heuristic = {
            "shoulder_width_cm": round(base_shoulder, 2),
            "chest_cm": round(base_shoulder * 1.10, 2),
            "waist_cm": round(base_hips * 0.93, 2),
            "hips_cm": round(base_hips, 2),
        }
        true_vec = np.array([chest_cm, waist_cm, hip_cm, shoulder_cm])
        heur_vec = np.array(
            [
                heuristic["chest_cm"],
                heuristic["waist_cm"],
                heuristic["hips_cm"],
                heuristic["shoulder_width_cm"],
            ]
        )
        residual = true_vec - heur_vec

        front_meta = {
            "body_height_px": body_px,
            "shoulder_px": shoulder_px,
            "hips_px": hips_px,
            "cm_per_px": scale,
            "visibility_min": float(rng.uniform(0.65, 1.0)),
            "blur_score": float(rng.uniform(90, 220)),
            "elbow_cm": float(rng.uniform(50, 75)),
        }

        n_aux = int(rng.integers(0, 4))
        sh_max = shoulder_px * float(rng.uniform(1.0, 1.12 if n_aux else 1.0))
        sh_std = float(rng.uniform(0, 8)) if n_aux else 0.0
        hip_mean = hips_px * float(rng.uniform(0.97, 1.05))

        fv = build_feature_vector(
            heuristic,
            front_meta,
            height_cm,
            n_aux,
            sh_max,
            sh_std,
            hip_mean,
        )
        X_rows.append(fv.ravel())
        Y_rows.append(residual)

    return np.stack(X_rows), np.stack(Y_rows)


def ridge_fit(X: np.ndarray, Y: np.ndarray, alpha: float) -> np.ndarray:
    """Y: (n, 4). Returns W: (d+1, 4) with bias in row 0."""
    n, d = X.shape
    Xb = np.concatenate([np.ones((n, 1)), X], axis=1)
    reg = alpha * np.eye(d + 1)
    reg[0, 0] = 0.0
    a = Xb.T @ Xb + reg
    b = Xb.T @ Y
    return np.linalg.solve(a, b)


def main() -> None:
    X, Y = synthetic_batch(3000)
    W = ridge_fit(X, Y, alpha=2.5)
    out_dir = ROOT / "models"
    out_dir.mkdir(exist_ok=True)
    path = out_dir / "size_residual.npz"
    np.savez(path, W=W, model_version="size_residual_numpy_v1", feature_dim=X.shape[1])
    print(f"Wrote {path} shape W={W.shape}")
    Xb = np.concatenate([np.ones((200, 1)), X[:200]], axis=1)
    pred = Xb @ W
    mae = np.mean(np.abs(pred - Y[:200]), axis=0)
    print("MAE per target (chest, waist, hips, shoulder) cm:", np.round(mae, 3))


if __name__ == "__main__":
    main()
