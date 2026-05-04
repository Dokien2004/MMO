"""Human-like mouse drag motion simulator using cubic Bézier curves.

Generates realistic drag paths with variable speed profiles and
slight overshoot/correction movements.
"""
from __future__ import annotations

import math
import random
from dataclasses import dataclass, field


@dataclass
class DragStep:
    """A single step in a drag movement sequence."""

    x: int
    y: int
    pause_ms: int = 0


@dataclass
class DragPath:
    """Complete drag path from start to end."""

    steps: list[DragStep] = field(default_factory=list)
    total_duration_ms: int = 0

    @property
    def end_x(self) -> int:
        return self.steps[-1].x if self.steps else 0

    @property
    def end_y(self) -> int:
        return self.steps[-1].y if self.steps else 0


def generate_drag_path(
    start_x: int,
    start_y: int,
    target_x: int,
    target_y: int,
    duration_range: tuple[int, int] = (400, 800),
    step_count_range: tuple[int, int] = (30, 60),
) -> DragPath:
    """Generate a human-like drag path using cubic Bézier curves.

    Args:
        start_x, start_y: Drag start position (slider handle center).
        target_x, target_y: Drag target position.
        duration_range: Min/max total drag duration in milliseconds.
        step_count_range: Min/max number of discrete movement steps.

    Returns:
        DragPath with all intermediate steps and timing info.
    """
    total_duration = random.randint(*duration_range)
    step_count = random.randint(*step_count_range)

    dx = target_x - start_x
    dy = target_y - start_y

    # Generate Bézier control points with slight randomness
    # CP1: 20-40% along path, with vertical jitter
    cp1_x = start_x + dx * random.uniform(0.2, 0.4)
    cp1_y = start_y + dy * random.uniform(0.2, 0.4) + random.randint(-8, 8)
    # CP2: 60-80% along path, with vertical jitter
    cp2_x = start_x + dx * random.uniform(0.6, 0.8)
    cp2_y = start_y + dy * random.uniform(0.6, 0.8) + random.randint(-5, 5)

    steps: list[DragStep] = []
    for i in range(step_count):
        t = _ease_in_out_sine(i / max(step_count - 1, 1))
        x = _bezier(t, start_x, cp1_x, cp2_x, target_x)
        y = _bezier(t, start_y, cp1_y, cp2_y, target_y)
        steps.append(DragStep(x=int(round(x)), y=int(round(y))))

    # Add slight overshoot then correction (last 6-10 steps)
    overshoot_steps = random.randint(6, 10)
    overshoot_px = random.randint(3, 8)
    for i, step_idx in enumerate(range(len(steps) - overshoot_steps, len(steps))):
        if step_idx < 0:
            continue
        progress = _ease_out_quad(i / max(overshoot_steps - 1, 1))
        overshoot = int(overshoot_px * (1.0 - progress))
        steps[step_idx].x = target_x + overshoot
        steps[step_idx].pause_ms = random.randint(6, 30)

    # Ensure final step lands exactly on target
    steps.append(DragStep(x=target_x, y=target_y, pause_ms=0))

    return DragPath(steps=steps, total_duration_ms=total_duration)


def calculate_step_delays(path: DragPath) -> list[int]:
    """Calculate the delay (ms) before each step to match total duration.

    Returns a list of delays in milliseconds, one per step.
    Delays follow a variable speed profile:
    - Slow at start (~20% of duration for first 15% of distance)
    - Fast in middle (~40% of duration for 60% of distance)
    - Slow at end (~40% of duration for last 25% of distance)
    """
    n = len(path.steps)
    if n <= 1:
        return [0]

    # Account for existing pause_ms in steps
    existing_pause = sum(s.pause_ms for s in path.steps)
    remaining = max(100, path.total_duration_ms - existing_pause)

    # Generate weight curve: sine-based variable speed
    raw_weights: list[float] = []
    for i in range(n):
        t = i / max(n - 1, 1)
        # Sine curve: slower at edges, faster in middle
        w = 0.3 + 0.7 * math.sin(t * math.pi)
        raw_weights.append(max(0.1, w))

    total_weight = sum(raw_weights)
    delays = [int(remaining * w / total_weight) for w in raw_weights]

    return delays


def _ease_in_out_sine(t: float) -> float:
    """Sine ease-in-out: smooth start and end."""
    return -(math.cos(math.pi * t) - 1) / 2


def _ease_out_quad(t: float) -> float:
    """Quadratic ease-out: fast start, slow end (for overshoot correction)."""
    return 1 - (1 - t) * (1 - t)


def _bezier(t: float, p0: float, p1: float, p2: float, p3: float) -> float:
    """Evaluate cubic Bézier curve at parameter t."""
    u = 1 - t
    return u * u * u * p0 + 3 * u * u * t * p1 + 3 * u * t * t * p2 + t * t * t * p3
