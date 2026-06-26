from __future__ import annotations

import re
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

from prospect_toolkit.sources import ProspectRecord, UK_TOWNS

HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ZapTaskProspectingBot/1.0)"}
LISTING_URL = "https://www.designrush.com/agency/software-development/uk"


def _infer_town(text: str) -> str:
    for town in UK_TOWNS:
        if re.search(rf"\b{re.escape(town)}\b", text, re.I):
            return town
    return "UK"


def parse_listing_page(html: str, page_url: str) -> list[ProspectRecord]:
    soup = BeautifulSoup(html, "html.parser")
    records: list[ProspectRecord] = []

    for block in soup.select("article, [class*='agency-card'], [class*='listing-item']"):
        name_el = block.select_one("h2, h3, h4, a[href*='/agency/']")
        if not name_el:
            continue

        name = name_el.get_text(" ", strip=True)
        if not name or len(name) < 2:
            continue

        website = ""
        for anchor in block.select('a[href^="http"]'):
            href = anchor.get("href", "")
            host = urlparse(href).netloc.lower()
            if "designrush" in host:
                continue
            website = href.split("?")[0]
            break

        profile = block.select_one("a[href*='/agency/']")
        if profile and not website:
            profile_url = urljoin(page_url, profile.get("href", ""))
            website = profile_url

        town = _infer_town(block.get_text(" ", strip=True))
        records.append(
            ProspectRecord(
                agency_name=name,
                town=town,
                website=website,
                region_focus="UK",
                notes="DesignRush UK software agency listing.",
            )
        )

    return records


def fetch_page(page: int) -> list[ProspectRecord]:
    url = LISTING_URL if page == 1 else f"{LISTING_URL}?page={page}"
    try:
        response = requests.get(url, headers=HEADERS, timeout=30)
    except requests.RequestException:
        return []

    if response.status_code >= 400:
        return []

    return parse_listing_page(response.text, url)


def collect_designrush(target: int, workers: int = 8) -> list[ProspectRecord]:
    first = fetch_page(1)
    if not first:
        print("designrush: listing unavailable")
        return []

    response = requests.get(LISTING_URL, headers=HEADERS, timeout=30)
    soup = BeautifulSoup(response.text, "html.parser")
    max_page = 1
    for anchor in soup.select('a[href*="page="]'):
        label = anchor.get_text(strip=True)
        if label.isdigit():
            max_page = max(max_page, int(label))

    records = list(first)
    pages = range(2, max_page + 1)

    with ThreadPoolExecutor(max_workers=workers) as executor:
        futures = {executor.submit(fetch_page, page): page for page in pages}
        for future in as_completed(futures):
            records.extend(future.result())
            if len(records) >= target:
                break

    print(f"designrush: scraped {len(records)} rows across up to {max_page} pages")
    return records[:target]
