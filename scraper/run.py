"""Dev runner: `python -m app` tidak dipakai. Pakai uvicorn langsung:

    uvicorn app.main:app --host 127.0.0.1 --port 8001 --reload

Atau via script root: `python run.py` (di folder scraper/).
"""
from __future__ import annotations

import uvicorn

from app.config import SERVICE_HOST, SERVICE_PORT


def main() -> None:
    uvicorn.run(
        "app.main:app",
        host=SERVICE_HOST,
        port=SERVICE_PORT,
        reload=True,
    )


if __name__ == "__main__":
    main()
