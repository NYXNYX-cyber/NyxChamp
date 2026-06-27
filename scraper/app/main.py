"""FastAPI app entrypoint untuk scraper service.

Endpoint yang tersedia:
- GET  /health   — liveness, tanpa auth
- POST /scrape   — endpoint utama, butuh token (lihat SERVICE_TOKEN)

Service ini adalah layanan terpisah dari Laravel (lihat Rancangan §2
& AGENTS.md §3.2). Berjalan di port 8001 by default, tidak dipublish
ke publik — hanya dipanggil dari Laravel via internal network.
"""
from __future__ import annotations

from fastapi import Depends, FastAPI, Header, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware

from app.config import SERVICE_TOKEN, TARGET_PORTALS
from app.schemas import ScrapeRequest, ScrapeResponse
from app.services.scraper import scrape_portal

app = FastAPI(
    title="NyxChamp Scraper",
    version="0.1.0",
    description="Layanan scraping multi-portal untuk NyxChamp. Lihat AGENTS.md.",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://localhost:8000", "http://127.0.0.1:8000"],
    allow_credentials=True,
    allow_methods=["GET", "POST"],
    allow_headers=["*"],
)


def require_token(authorization: str | None = Header(default=None)) -> None:
    """Validasi bearer token dari Laravel.

    Untuk dev, token cocok dengan SCRAPER_SERVICE_TOKEN di .env Laravel.
    Untuk prod, ganti dengan shared secret yang lebih kuat atau mTLS.
    """
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Missing bearer token",
        )
    token = authorization.removeprefix("Bearer ")
    if token != SERVICE_TOKEN:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail="Invalid token",
        )


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "service": "nyxchamp-scraper"}


@app.get("/portals")
def list_portals() -> dict[str, list[str]]:
    return {"targets": TARGET_PORTALS}


@app.post("/scrape", response_model=ScrapeResponse, dependencies=[Depends(require_token)])
def scrape(req: ScrapeRequest) -> ScrapeResponse:
    if req.portal not in TARGET_PORTALS:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=f"Portal '{req.portal}' tidak ada di TARGET_PORTALS",
        )
    return scrape_portal(req)
