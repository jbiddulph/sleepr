from __future__ import annotations

import time
from concurrent.futures import ThreadPoolExecutor, as_completed

import requests
from bs4 import BeautifulSoup

from prospect_toolkit.sources import ProspectRecord

HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ZapTaskProspectingBot/1.0)"}
BASE_URL = "https://www.thomsonlocal.com"

UK_CITIES = (
    "london",
    "manchester",
    "birmingham",
    "leeds",
    "glasgow",
    "edinburgh",
    "bristol",
    "brighton",
    "reading",
    "cambridge",
    "oxford",
    "newcastle",
    "sheffield",
    "liverpool",
    "nottingham",
    "cardiff",
    "belfast",
    "southampton",
    "leicester",
    "coventry",
)

THOMSON_CATEGORIES: dict[str, tuple[str, ...]] = {
    "property_management": ("property-management",),
    "construction": ("builders", "building-contractors", "construction-companies"),
}


def parse_listing_page(html: str, default_town: str, notes: str) -> list[ProspectRecord]:
    soup = BeautifulSoup(html, "html.parser")
    records: list[ProspectRecord] = []

    for listing in soup.select("li.listing"):
        name_el = listing.select_one(".businessName")
        if not name_el:
            continue

        name = name_el.get_text(strip=True)
        town_el = listing.select_one('[itemprop="addressLocality"]')
        town = town_el.get_text(strip=True) if town_el else default_town.title()

        website = ""
        website_link = listing.select_one("li.website a[href^='http']")
        if website_link:
            website = website_link.get("href", "").split("?")[0].strip()

        if not website:
            continue

        records.append(
            ProspectRecord(
                agency_name=name,
                town=town,
                website=website,
                region_focus="UK",
                notes=notes,
            )
        )

    return records


def fetch_city_page(category: str, city: str, page: int, notes: str) -> list[ProspectRecord]:
    path = f"/search/{category}/{city}"
    url = f"{BASE_URL}{path}" if page == 1 else f"{BASE_URL}{path}?page={page}"
    try:
        response = requests.get(url, headers=HEADERS, timeout=30)
    except requests.RequestException:
        return []

    if response.status_code >= 400:
        return []

    return parse_listing_page(response.text, default_town=city, notes=notes)


def max_pages_for_city(category: str, city: str) -> int:
    url = f"{BASE_URL}/search/{category}/{city}"
    try:
        response = requests.get(url, headers=HEADERS, timeout=30)
    except requests.RequestException:
        return 1

    if response.status_code >= 400:
        return 1

    soup = BeautifulSoup(response.text, "html.parser")
    max_page = 1
    for anchor in soup.select('a[href*="page="]'):
        label = anchor.get_text(strip=True)
        if label.isdigit():
            max_page = max(max_page, int(label))
    return max_page


def collect_thomsonlocal(
    target: int,
    industry: str = "property_management",
    workers: int = 8,
    delay: float = 0.05,
) -> list[ProspectRecord]:
    categories = THOMSON_CATEGORIES.get(industry, THOMSON_CATEGORIES["property_management"])
    notes = f"Thomson Local {industry.replace('_', ' ')} listing."

    jobs: list[tuple[str, str, int]] = []
    for category in categories:
        for city in UK_CITIES:
            pages = max_pages_for_city(category, city)
            for page in range(1, pages + 1):
                jobs.append((category, city, page))

    records: list[ProspectRecord] = []
    with ThreadPoolExecutor(max_workers=workers) as executor:
        futures = {
            executor.submit(fetch_city_page, category, city, page, notes): (category, city, page)
            for category, city, page in jobs
        }
        for future in as_completed(futures):
            records.extend(future.result())
            if delay:
                time.sleep(delay)
            if len(records) >= target:
                break

    print(
        f"thomsonlocal ({industry}): scraped {len(records)} rows "
        f"across {len(categories)} categories and {len(UK_CITIES)} cities"
    )
    return records[:target]
