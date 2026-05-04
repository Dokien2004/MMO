"""Shopee slider captcha solver using Computer Vision.

Public API:
    solve_captcha(page, platform, max_attempts) -> bool
"""
from __future__ import annotations

from .playwright_handler import solve_captcha

__all__ = ["solve_captcha"]
