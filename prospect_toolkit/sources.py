from __future__ import annotations

import csv
import os
import re
import time
from dataclasses import dataclass
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

from prospect_toolkit.config import IndustryConfig

HEADERS = {"User-Agent": "ZapTaskProspectingBot/1.0 (+https://www.zaptask.co.uk)"}
UK_TOWNS = (
    "London",
    "Manchester",
    "Birmingham",
    "Leeds",
    "Glasgow",
    "Edinburgh",
    "Bristol",
    "Cambridge",
    "Oxford",
    "Reading",
    "Newcastle",
    "Sheffield",
    "Liverpool",
    "Nottingham",
    "Cardiff",
    "Belfast",
    "Brighton",
    "Southampton",
    "Leicester",
    "Coventry",
)

BUILTIN_SITES = {
    "London": "https://builtinlondon.uk",
    "Manchester": "https://builtinmanchester.uk",
    "Bristol": "https://builtinbristol.uk",
    "Edinburgh": "https://builtinedinburgh.uk",
    "Cambridge": "https://builtincambridge.uk",
    "Birmingham": "https://builtinbirmingham.uk",
}

BUILTIN_LISTINGS = (
    "/companies/type/software-companies?country=GBR",
    "/companies/type/information-technology-companies?country=GBR",
    "/companies/type/saas-companies?country=GBR",
)

SKIP_BUILTIN_SLUGS = {"benefits", "jobs", "culture", "tech-stack"}


@dataclass
class ProspectRecord:
    agency_name: str
    town: str
    website: str
    contact_page_url: str = ""
    region_focus: str = "UK"
    status: str = "Needs manual verification"
    notes: str = ""

    def key(self) -> tuple[str, str]:
        return (self.normalise_name(self.agency_name), self.normalise_name(self.town))

    def domain_key(self) -> str:
        host = urlparse(self.website).netloc.lower().removeprefix("www.")
        return host

    @staticmethod
    def normalise_name(value: str) -> str:
        return re.sub(r"[^a-z0-9]+", "", value.lower())

    def to_row(self, industry: IndustryConfig) -> dict[str, str]:
        return {
            "agency_name": self.agency_name,
            "town": self.town,
            "website": self.website,
            "contact_page_url": self.contact_page_url,
            "region_focus": self.region_focus or industry.region_focus,
            "status": self.status or industry.status,
            "notes": self.notes or industry.notes,
        }


def merge_records(records: list[ProspectRecord]) -> list[ProspectRecord]:
    seen_domains: set[str] = set()
    seen_keys: set[tuple[str, str]] = set()
    merged: list[ProspectRecord] = []

    ordered = sorted(
        records,
        key=lambda item: (
            not item.website,
            ProspectRecord.normalise_name(item.agency_name),
            item.town.lower(),
        ),
    )

    for record in ordered:
        if not record.agency_name:
            continue

        domain = record.domain_key()
        name_key = record.key()

        if domain and domain in seen_domains:
            continue
        if name_key in seen_keys:
            continue

        if domain:
            seen_domains.add(domain)
        seen_keys.add(name_key)
        merged.append(record)

    return sorted(merged, key=lambda item: (item.town.lower(), item.agency_name.lower()))


def build_software_prospect_list(
    industry: IndustryConfig,
    target: int,
    delay: float = 0.1,
    enrich_contacts: bool = False,
) -> list[dict[str, str]]:
    from prospect_toolkit.companies_house_bulk import (
        attach_websites_from_lookup,
        build_name_lookup,
        collect_companies_house_bulk,
    )
    from prospect_toolkit.designrush import collect_designrush
    from prospect_toolkit.techbehemoths import collect_techbehemoths

    directory_records: list[ProspectRecord] = []
    directory_records.extend(collect_techbehemoths(target=target))
    directory_records.extend(collect_designrush(target=target))
    directory_records.extend(
        collect_builtin(
            target=min(2500, target),
            delay=delay,
            max_pages_per_listing=40,
        )
    )

    website_lookup = build_name_lookup(directory_records)
    print(f"directory sources: {len(directory_records)} rows, {len(website_lookup)} website lookups")

    ch_records = collect_companies_house_bulk(target=max(target + 5000, 15000), industry="software")
    attached = attach_websites_from_lookup(ch_records, website_lookup)
    print(f"companies_house_bulk: attached websites to {attached} records")

    merged = merge_records(directory_records + ch_records)
    if len(merged) < target:
        raise RuntimeError(
            f"Only collected {len(merged)} unique software companies; expected at least {target}."
        )

    with_website = [record for record in merged if record.website]
    without_website = [record for record in merged if not record.website]
    selected = (with_website + without_website)[:target]
    website_count = sum(1 for record in selected if record.website)
    print(f"selected {len(selected)} prospects ({website_count} with websites)")

    for record in selected:
        if record.website and not record.contact_page_url:
            record.contact_page_url = record.website.rstrip("/") + industry.contact_paths[0]

    if enrich_contacts:
        enrich_contact_pages(selected, industry)

    return [record.to_row(industry) for record in selected]


def build_property_management_prospect_list(
    industry: IndustryConfig,
    target: int,
    seed_path: Path | None = None,
    delay: float = 0.05,
    enrich_contacts: bool = False,
) -> list[dict[str, str]]:
    from prospect_toolkit.companies_house_bulk import (
        attach_websites_from_lookup,
        build_name_lookup,
        collect_companies_house_bulk,
    )
    from prospect_toolkit.thomsonlocal import collect_thomsonlocal

    directory_records: list[ProspectRecord] = []
    if seed_path and seed_path.exists():
        directory_records.extend(load_seed_file(seed_path, industry))
        print(f"seed: {len(directory_records)} records")

    directory_records.extend(
        collect_thomsonlocal(target=target, industry="property_management", delay=delay)
    )
    website_lookup = build_name_lookup(directory_records)
    print(f"thomsonlocal: {len(directory_records)} rows, {len(website_lookup)} website lookups")

    ch_records = collect_companies_house_bulk(
        target=max(target + 2000, 5000),
        industry="property_management",
    )
    attached = attach_websites_from_lookup(ch_records, website_lookup)
    print(f"companies_house_bulk: attached websites to {attached} records")

    merged = merge_records(directory_records + ch_records)
    with_website = [record for record in merged if record.website]
    without_website = [record for record in merged if not record.website]
    selected = (with_website + without_website)[:target]
    website_count = sum(1 for record in selected if record.website)
    print(f"selected {len(selected)} prospects ({website_count} with websites)")

    for record in selected:
        if record.website and not record.contact_page_url:
            record.contact_page_url = record.website.rstrip("/") + industry.contact_paths[0]

    if enrich_contacts:
        enrich_contact_pages(selected, industry)

    return [record.to_row(industry) for record in selected]


def build_construction_prospect_list(
    industry: IndustryConfig,
    target: int,
    seed_path: Path | None = None,
    delay: float = 0.05,
    enrich_contacts: bool = False,
) -> list[dict[str, str]]:
    from prospect_toolkit.companies_house_bulk import (
        attach_websites_from_lookup,
        build_name_lookup,
        collect_companies_house_bulk,
    )
    from prospect_toolkit.thomsonlocal import collect_thomsonlocal

    directory_records: list[ProspectRecord] = []
    if seed_path and seed_path.exists():
        directory_records.extend(load_seed_file(seed_path, industry))
        print(f"seed: {len(directory_records)} records")

    directory_records.extend(
        collect_thomsonlocal(target=target, industry="construction", delay=delay)
    )
    website_lookup = build_name_lookup(directory_records)
    print(f"thomsonlocal: {len(directory_records)} rows, {len(website_lookup)} website lookups")

    ch_records = collect_companies_house_bulk(
        target=max(target + 2000, 5000),
        industry="construction",
    )
    attached = attach_websites_from_lookup(ch_records, website_lookup)
    print(f"companies_house_bulk: attached websites to {attached} records")

    merged = merge_records(directory_records + ch_records)
    with_website = [record for record in merged if record.website]
    without_website = [record for record in merged if not record.website]
    selected = (with_website + without_website)[:target]
    website_count = sum(1 for record in selected if record.website)
    print(f"selected {len(selected)} prospects ({website_count} with websites)")

    for record in selected:
        if record.website and not record.contact_page_url:
            record.contact_page_url = record.website.rstrip("/") + industry.contact_paths[0]

    if enrich_contacts:
        enrich_contact_pages(selected, industry)

    return [record.to_row(industry) for record in selected]


def guess_contact_page(website: str, industry: IndustryConfig, timeout: int = 10) -> str:
    website = website.strip().rstrip("/")
    if not website:
        return ""

    for path in industry.contact_paths[:6]:
        candidate = website + path
        try:
            response = requests.head(
                candidate,
                headers=HEADERS,
                timeout=timeout,
                allow_redirects=True,
            )
            if response.status_code < 400:
                return candidate
        except requests.RequestException:
            continue

    return website + industry.contact_paths[0]


def enrich_contact_pages(
    records: list[ProspectRecord],
    industry: IndustryConfig,
    delay: float = 0.25,
) -> None:
    for record in records:
        if record.contact_page_url:
            continue
        record.contact_page_url = guess_contact_page(record.website, industry)
        time.sleep(delay)


def load_seed_file(path: Path, industry: IndustryConfig) -> list[ProspectRecord]:
    if not path.exists():
        return []

    records: list[ProspectRecord] = []
    with path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            website = (row.get("website") or "").strip()
            if website and not website.startswith("http"):
                website = "https://" + website.lstrip("/")

            records.append(
                ProspectRecord(
                    agency_name=(row.get("agency_name") or row.get("company_name") or "").strip(),
                    town=(row.get("town") or "").strip(),
                    website=website,
                    contact_page_url=(row.get("contact_page_url") or "").strip(),
                    region_focus=(row.get("region_focus") or industry.region_focus).strip(),
                    status=(row.get("status") or industry.status).strip(),
                    notes=(row.get("notes") or industry.notes).strip(),
                )
            )
    return records


def fetch_builtin_profile(site_base: str, slug: str) -> ProspectRecord | None:
    response = requests.get(f"{site_base}/company/{slug}", headers=HEADERS, timeout=20)
    if response.status_code >= 400:
        return None

    soup = BeautifulSoup(response.text, "html.parser")
    heading = soup.select_one("h1")
    name = heading.get_text(strip=True) if heading else slug.replace("-", " ").title()

    website = ""
    for anchor in soup.select('a[href^="http"]'):
        href = anchor.get("href", "")
        host = urlparse(href).netloc.lower()
        if any(
            token in host
            for token in ("builtin", "jotform", "linkedin", "twitter", "facebook", "instagram")
        ):
            continue
        website = href.split("?")[0]
        break

    if not website:
        return None

    town = infer_town_from_builtin_site(site_base)
    text = soup.get_text(" ", strip=True)
    for candidate in UK_TOWNS:
        if re.search(rf"\b{re.escape(candidate)}\b", text):
            town = candidate
            break

    return ProspectRecord(
        agency_name=name,
        town=town,
        website=website,
        region_focus="UK",
    )


def infer_town_from_builtin_site(site_base: str) -> str:
    host = urlparse(site_base).netloc.lower()
    mapping = {
        "builtinlondon": "London",
        "builtinmanchester": "Manchester",
        "builtinbristol": "Bristol",
        "builtinedinburgh": "Edinburgh",
        "builtincambridge": "Cambridge",
        "builtinbirmingham": "Birmingham",
    }
    for prefix, town in mapping.items():
        if host.startswith(prefix):
            return town
    return "London"


def collect_builtin_slugs(site_base: str, listing_path: str, max_pages: int) -> list[str]:
    slugs: list[str] = []
    seen_pages: set[int] = set()

    for page in range(1, max_pages + 1):
        if page in seen_pages:
            continue
        seen_pages.add(page)

        url = f"{site_base}{listing_path}&page={page}"
        try:
            response = requests.get(url, headers=HEADERS, timeout=20)
        except requests.RequestException:
            break

        if response.status_code >= 400:
            break

        soup = BeautifulSoup(response.text, "html.parser")
        page_slugs: list[str] = []
        for anchor in soup.select('a[href^="/company/"]'):
            href = anchor.get("href", "").strip("/")
            parts = href.split("/")
            if len(parts) != 2 or parts[0] != "company":
                continue
            slug = parts[1]
            if slug in SKIP_BUILTIN_SLUGS:
                continue
            if slug not in page_slugs:
                page_slugs.append(slug)

        if not page_slugs:
            break

        for slug in page_slugs:
            if slug not in slugs:
                slugs.append(slug)

        page_numbers = []
        for anchor in soup.select("a.page-link"):
            label = anchor.get_text(strip=True)
            if label.isdigit():
                page_numbers.append(int(label))

        if page_numbers and page >= max(page_numbers):
            break

    return slugs


def collect_builtin(
    target: int,
    delay: float = 0.35,
    max_pages_per_listing: int = 20,
) -> list[ProspectRecord]:
    records: list[ProspectRecord] = []
    seen_domains: set[str] = set()

    for town, site_base in BUILTIN_SITES.items():
        for listing_path in BUILTIN_LISTINGS:
            slugs = collect_builtin_slugs(site_base, listing_path, max_pages_per_listing)
            for slug in slugs:
                if len(records) >= target:
                    return records

                profile = fetch_builtin_profile(site_base, slug)
                time.sleep(delay)
                if not profile:
                    continue

                domain = profile.domain_key()
                if domain in seen_domains:
                    continue

                if not profile.town:
                    profile.town = town

                seen_domains.add(domain)
                records.append(profile)
                print(f"builtin: {len(records)} {profile.agency_name} ({profile.town})")

    return records


def collect_wikipedia_uk(target: int) -> list[ProspectRecord]:
    pages = [
        "List_of_largest_information_technology_companies",
        "Category:Software_companies_of_the_United_Kingdom",
    ]
    records: list[ProspectRecord] = []

    for page in pages:
        if len(records) >= target:
            break

        url = f"https://en.wikipedia.org/wiki/{page}"
        try:
            response = requests.get(url, headers=HEADERS, timeout=20)
        except requests.RequestException:
            continue

        if response.status_code >= 400:
            continue

        soup = BeautifulSoup(response.text, "html.parser")

        if page.startswith("Category:"):
            for anchor in soup.select("#mw-pages a[href^='/wiki/']"):
                title = anchor.get_text(strip=True)
                if len(records) >= target:
                    break
                if "software" not in title.lower() and "technology" not in title.lower():
                    continue
                records.append(
                    ProspectRecord(
                        agency_name=title,
                        town="London",
                        website="",
                        notes="Wikipedia category listing; website needs manual lookup.",
                    )
                )
            continue

        for table in soup.select("table.wikitable"):
            headers = [cell.get_text(strip=True).lower() for cell in table.select("tr th")]
            if "company" not in headers:
                continue

            company_idx = headers.index("company")
            country_idx = headers.index("country (origin)") if "country (origin)" in headers else None
            hq_idx = headers.index("headquarters") if "headquarters" in headers else None

            for row in table.select("tr")[1:]:
                cells = row.select("th, td")
                if len(cells) <= company_idx:
                    continue

                company_cell = cells[company_idx]
                company_name = company_cell.get_text(" ", strip=True)
                if not company_name:
                    continue

                country = cells[country_idx].get_text(" ", strip=True) if country_idx is not None else ""
                if country and "united kingdom" not in country.lower() and "uk" not in country.lower():
                    continue

                town = "London"
                if hq_idx is not None and len(cells) > hq_idx:
                    headquarters = cells[hq_idx].get_text(" ", strip=True)
                    for candidate in UK_TOWNS:
                        if candidate.lower() in headquarters.lower():
                            town = candidate
                            break

                website = ""
                for anchor in company_cell.select("a[href]"):
                    href = anchor.get("href", "")
                    if href.startswith("http"):
                        website = href
                        break

                if not website:
                    continue

                records.append(
                    ProspectRecord(
                        agency_name=company_name,
                        town=town,
                        website=website,
                    )
                )
                if len(records) >= target:
                    break

    return records


def collect_companies_house(
    target: int,
    sic_codes: tuple[str, ...] = ("62012", "62020", "62090"),
) -> list[ProspectRecord]:
    api_key = os.environ.get("COMPANIES_HOUSE_API_KEY", "").strip()
    if not api_key:
        print("companies_house: skipped (COMPANIES_HOUSE_API_KEY not set)")
        return []

    records: list[ProspectRecord] = []
    start_index = 0
    page_size = 100

    while len(records) < target:
        params = {
            "company_status": "active",
            "sic_codes": ",".join(sic_codes),
            "size": page_size,
            "start_index": start_index,
        }
        try:
            response = requests.get(
                "https://api.company-information.service.gov.uk/advanced-search/companies",
                params=params,
                auth=(api_key, ""),
                timeout=30,
            )
        except requests.RequestException:
            break

        if response.status_code >= 400:
            print(f"companies_house: request failed ({response.status_code})")
            break

        payload = response.json()
        items = payload.get("items", [])
        if not items:
            break

        for item in items:
            address = item.get("registered_office_address", {})
            town = (
                address.get("locality")
                or address.get("region")
                or address.get("postal_code", "")[:20]
                or "UK"
            )
            records.append(
                ProspectRecord(
                    agency_name=item.get("company_name", "").strip(),
                    town=str(town).strip(),
                    website="",
                    notes="Companies House listing; website needs manual lookup.",
                )
            )
            if len(records) >= target:
                break

        start_index += page_size
        if start_index >= payload.get("hits", 0):
            break

    return records


def build_prospect_list(
    industry: IndustryConfig,
    target: int,
    sources: list[str],
    seed_path: Path | None = None,
    delay: float = 0.35,
    enrich_contacts: bool = True,
) -> list[dict[str, str]]:
    if industry.slug == "software" and target >= 1000:
        return build_software_prospect_list(
            industry=industry,
            target=target,
            delay=delay,
            enrich_contacts=enrich_contacts,
        )

    if industry.slug == "property_management":
        return build_property_management_prospect_list(
            industry=industry,
            target=target,
            seed_path=seed_path,
            delay=delay,
            enrich_contacts=enrich_contacts,
        )

    if industry.slug == "construction":
        return build_construction_prospect_list(
            industry=industry,
            target=target,
            seed_path=seed_path,
            delay=delay,
            enrich_contacts=enrich_contacts,
        )

    collected: list[ProspectRecord] = []

    if "seed" in sources and seed_path:
        seed_records = load_seed_file(seed_path, industry)
        collected.extend(seed_records)
        print(f"seed: {len(seed_records)} records")

    if "wikipedia" in sources:
        wiki_records = collect_wikipedia_uk(max(0, target - len(collected)))
        collected.extend(wiki_records)
        print(f"wikipedia: {len(wiki_records)} records")

    if "companies_house" in sources:
        ch_records = collect_companies_house(max(0, target - len(collected)))
        collected.extend(ch_records)
        print(f"companies_house: {len(ch_records)} records")

    if "builtin" in sources and len(collected) < target:
        builtin_records = collect_builtin(
            target=max(0, target - len(merge_records(collected))),
            delay=delay,
        )
        collected.extend(builtin_records)
        print(f"builtin total: {len(builtin_records)} records")

    merged = merge_records(collected)[:target]

    if enrich_contacts:
        enrich_contact_pages(merged, industry)

    return [record.to_row(industry) for record in merged]
