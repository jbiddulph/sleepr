from __future__ import annotations

import csv
import re
import zipfile
from pathlib import Path
from urllib.request import urlretrieve

from prospect_toolkit.sources import ProspectRecord

BULK_ZIP_URL = "https://download.companieshouse.gov.uk/BasicCompanyDataAsOneFile-2026-06-01.zip"
CACHE_DIR = Path(__file__).resolve().parents[1] / "storage" / "companies_house"

SOFTWARE_SIC_PREFIXES = (
    "58210",
    "58290",
    "62011",
    "62012",
    "62020",
    "62090",
    "63110",
    "63120",
)

PROPERTY_MANAGEMENT_SIC_PREFIXES = (
    "68310",
    "68320",
    "68209",
    "68201",
    "68202",
)

CONSTRUCTION_SIC_PREFIXES = (
    "41201",
    "41202",
    "42110",
    "42120",
    "42130",
    "42210",
    "42220",
    "42910",
    "42990",
    "43110",
    "43120",
    "43210",
    "43220",
    "43290",
    "43310",
    "43320",
    "43330",
    "43341",
    "43342",
    "43910",
    "43991",
    "43999",
)

SIC_PROFILES: dict[str, tuple[str, ...]] = {
    "software": SOFTWARE_SIC_PREFIXES,
    "property_management": PROPERTY_MANAGEMENT_SIC_PREFIXES,
    "construction": CONSTRUCTION_SIC_PREFIXES,
}

SIC_KEYWORDS: dict[str, tuple[str, ...]] = {
    "software": (
        "software",
        "computer programming",
        "information technology",
        "data processing",
        "web portal",
        "computer consultancy",
    ),
    "property_management": (
        "management of real estate",
        "property management",
        "real estate agencies",
        "letting and operating",
        "renting and operating",
        "buying and selling of own real estate",
        "real estate",
    ),
    "construction": (
        "construction",
        "building",
        "civil engineering",
        "demolition",
        "site preparation",
        "electrical installation",
        "plumbing",
        "plastering",
        "joinery installation",
        "roofing",
        "scaffold",
        "specialised construction",
    ),
}

COMPANY_SUFFIXES = re.compile(
    r"\b(LTD|LIMITED|PLC|LLP|CIC|CYFYNGEDIG|CYF|GROUP|HOLDINGS|HOLDING|UK|SERVICES|SERVICE|"
    r"SOLUTIONS|SOLUTION|SYSTEMS|SYSTEM|TECHNOLOGIES|TECHNOLOGY|TECH|SOFTWARE|CONSULTING|"
    r"CONSULTANCY|DIGITAL|INTERACTIVE|MEDIA|LABS|LAB|WORKS|PARTNERS|PARTNER|INC|CORP)\b\.?$",
    re.I,
)


def normalise_company_name(name: str) -> str:
    cleaned = name.upper().strip()
    cleaned = re.sub(r"[^\w\s&]", " ", cleaned)
    cleaned = COMPANY_SUFFIXES.sub("", cleaned)
    cleaned = re.sub(r"\s+", " ", cleaned).strip()
    return cleaned


def sic_matches(sic_text: str, industry: str = "software") -> bool:
    if not sic_text:
        return False
    prefixes = SIC_PROFILES.get(industry, SOFTWARE_SIC_PREFIXES)
    keywords = SIC_KEYWORDS.get(industry, SIC_KEYWORDS["software"])
    for prefix in prefixes:
        if sic_text.strip().startswith(prefix):
            return True
    lowered = sic_text.lower()
    return any(keyword in lowered for keyword in keywords)


def ensure_bulk_csv() -> Path:
    CACHE_DIR.mkdir(parents=True, exist_ok=True)
    zip_path = CACHE_DIR / "BasicCompanyDataAsOneFile.zip"
    csv_path = CACHE_DIR / "BasicCompanyDataAsOneFile.csv"

    if csv_path.exists() and csv_path.stat().st_size > 0:
        return csv_path

    if not zip_path.exists() or zip_path.stat().st_size < 1_000_000:
        print(f"companies_house_bulk: downloading {BULK_ZIP_URL}")
        urlretrieve(BULK_ZIP_URL, zip_path)

    print("companies_house_bulk: extracting CSV from zip")
    with zipfile.ZipFile(zip_path) as archive:
        members = [name for name in archive.namelist() if name.lower().endswith(".csv")]
        if not members:
            raise RuntimeError("Companies House zip did not contain a CSV file")
        with archive.open(members[0]) as source, csv_path.open("wb") as target:
            target.write(source.read())

    return csv_path


def collect_companies_house_bulk(target: int, industry: str = "software") -> list[ProspectRecord]:
    csv_path = ensure_bulk_csv()
    records: list[ProspectRecord] = []

    with csv_path.open(newline="", encoding="utf-8", errors="replace") as handle:
        reader = csv.DictReader(handle)
        for row in reader:
            if row.get("CompanyStatus", "").strip().lower() != "active":
                continue

            sic_values = [
                row.get("SICCode.SicText_1", ""),
                row.get("SICCode.SicText_2", ""),
                row.get("SICCode.SicText_3", ""),
                row.get("SICCode.SicText_4", ""),
            ]
            if not any(sic_matches(value, industry) for value in sic_values):
                continue

            town = (
                row.get("RegAddress.PostTown", "").strip()
                or row.get("RegAddress.County", "").strip()
                or row.get("RegAddress.PostCode", "").strip()
                or "UK"
            )

            records.append(
                ProspectRecord(
                    agency_name=row.get("CompanyName", "").strip(),
                    town=town,
                    website="",
                    region_focus="UK",
                    notes=f"Companies House active {industry.replace('_', ' ')} SIC registration.",
                )
            )

            if len(records) >= target:
                break

    print(f"companies_house_bulk: selected {len(records)} active {industry.replace('_', ' ')} companies")
    return records


def build_name_lookup(records: list[ProspectRecord]) -> dict[str, ProspectRecord]:
    lookup: dict[str, ProspectRecord] = {}
    for record in records:
        if not record.website:
            continue
        key = normalise_company_name(record.agency_name)
        if key and key not in lookup:
            lookup[key] = record
    return lookup


def attach_websites_from_lookup(
    records: list[ProspectRecord],
    lookup: dict[str, ProspectRecord],
) -> int:
    attached = 0
    for record in records:
        if record.website:
            continue
        key = normalise_company_name(record.agency_name)
        match = lookup.get(key)
        if not match:
            continue
        record.website = match.website
        if match.contact_page_url:
            record.contact_page_url = match.contact_page_url
        attached += 1
    return attached
