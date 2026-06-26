from __future__ import annotations

import re
import time
from concurrent.futures import ThreadPoolExecutor, as_completed
from urllib.parse import urlparse

import requests
from bs4 import BeautifulSoup

from prospect_toolkit.sources import ProspectRecord, UK_TOWNS

HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ZapTaskProspectingBot/1.0)"}
LISTING_URL = "https://techbehemoths.com/companies/united-kingdom"


def _normalise_website(url: str) -> str:
    url = url.split("?")[0].strip()
    if url and not url.startswith("http"):
        url = "https://" + url.lstrip("/")
    return url


def _infer_town(text: str) -> str:
    for town in UK_TOWNS:
        if re.search(rf"\b{re.escape(town)}\b", text):
            return town
    if "United Kingdom" in text:
        return "UK"
    return "UK"


def parse_listing_page(html: str) -> list[ProspectRecord]:
    soup = BeautifulSoup(html, "html.parser")
    records: list[ProspectRecord] = []

    for article in soup.select("article"):
        profile_link = None
        name = ""
        for anchor in article.select('a[href^="/company/"]'):
            href = anchor.get("href", "")
            if href.count("/") == 2:
                profile_link = href
                text = anchor.get_text(" ", strip=True)
                name = re.sub(r"Verified Company$", "", text).strip()
                break

        if not name and profile_link:
            name = profile_link.rsplit("/", 1)[-1].replace("-", " ").title()

        website = ""
        for anchor in article.select('a[href^="http"]'):
            href = anchor.get("href", "")
            host = urlparse(href).netloc.lower()
            if "techbehemoths" in host:
                continue
            website = _normalise_website(href)
            break

        if not name:
            continue

        town = _infer_town(article.get_text(" ", strip=True))
        records.append(
            ProspectRecord(
                agency_name=name,
                town=town,
                website=website,
                region_focus="UK",
                notes="TechBehemoths UK software directory listing.",
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

    return parse_listing_page(response.text)


def collect_techbehemoths(
    target: int,
    workers: int = 12,
    delay: float = 0.05,
) -> list[ProspectRecord]:
    first = fetch_page(1)
    if not first:
        print("techbehemoths: listing unavailable")
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
            page_records = future.result()
            records.extend(page_records)
            if delay:
                time.sleep(delay)
            if len(records) >= target:
                break

    print(f"techbehemoths: scraped {len(records)} rows across up to {max_page} pages")
    return records[:target]
