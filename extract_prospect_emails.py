#!/usr/bin/env python3
"""
Extract publicly listed business email addresses from prospect websites/contact pages.

Input CSV columns:
agency_name,town,website,contact_page_url,region_focus,status,notes

Output CSV columns:
agency_name,town,website,contact_page_url,best_emails,other_business_emails,
emails_found,pages_checked,review_status

Examples:
  python extract_prospect_emails.py --industry software --input uk_software_prospects.csv
  python extract_prospect_emails.py --industry software --input uk_software_prospects.csv --resume
"""
from __future__ import annotations

import argparse
import csv
from pathlib import Path

from prospect_toolkit.config import EXTRACTED_CSV_FIELDS, get_industry
from prospect_toolkit.csv_io import read_prospects, write_extracted
from prospect_toolkit.email_extractor import EmailExtractor
from prospect_toolkit.unique_emails import UniqueEmailRegistry


def default_output(industry_slug: str, input_path: Path) -> Path:
    if input_path.name.startswith("uk_") and input_path.name.endswith(".csv"):
        return input_path.with_name(input_path.stem.replace("_prospects", "") + "_emails.csv")
    return Path(f"extracted_{industry_slug}_emails.csv")


def load_checkpoint(path: Path) -> tuple[list[dict[str, str]], set[tuple[str, str]]]:
    if not path.exists():
        return [], set()

    rows: list[dict[str, str]] = []
    done: set[tuple[str, str]] = set()
    with path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            rows.append(row)
            done.add((row.get("agency_name", ""), row.get("town", "")))
    return rows, done


def save_checkpoint(path: Path, rows: list[dict[str, str]]) -> None:
    with path.open("w", newline="", encoding="utf-8") as handle:
        writer = csv.DictWriter(handle, fieldnames=EXTRACTED_CSV_FIELDS)
        writer.writeheader()
        writer.writerows(rows)


def main() -> int:
    parser = argparse.ArgumentParser(description="Extract emails from prospect websites.")
    parser.add_argument("--industry", default="software", help="Industry preset")
    parser.add_argument("--input", type=Path, required=True, help="Input prospect CSV")
    parser.add_argument("--output", type=Path, help="Output extracted CSV")
    parser.add_argument("--delay", type=float, default=0.75, help="Delay between requests")
    parser.add_argument(
        "--max-pages-per-site",
        type=int,
        default=4,
        help="Maximum pages to fetch per prospect",
    )
    parser.add_argument(
        "--resume",
        action="store_true",
        help="Resume from the output/checkpoint CSV if it already exists",
    )
    parser.add_argument(
        "--limit",
        type=int,
        help="Only process this many rows (useful for testing)",
    )
    args = parser.parse_args()

    industry = get_industry(args.industry)
    rows = read_prospects(args.input)
    if args.limit:
        rows = rows[: args.limit]

    output_path = args.output or default_output(industry.slug, args.input)
    checkpoint_path = output_path

    completed_rows, done_keys = load_checkpoint(checkpoint_path) if args.resume else ([], set())
    email_registry = UniqueEmailRegistry()

    for completed in completed_rows:
        for email in completed.get("best_emails", "").split(";"):
            email = email.strip()
            if email:
                email_registry.assign(email, completed.get("website", ""))
        for email in completed.get("other_business_emails", "").split(";"):
            email = email.strip()
            if email:
                email_registry.assign(email, completed.get("website", ""))

    extractor = EmailExtractor(
        industry=industry,
        delay=args.delay,
        max_pages_per_site=args.max_pages_per_site,
    )

    out_rows = list(completed_rows)
    for index, row in enumerate(rows, start=1):
        key = (row.get("agency_name", ""), row.get("town", ""))
        if key in done_keys:
            continue

        if not row.get("website", "").strip():
            result = {
                "agency_name": row.get("agency_name", ""),
                "town": row.get("town", ""),
                "website": "",
                "contact_page_url": row.get("contact_page_url", ""),
                "best_emails": "",
                "other_business_emails": "",
                "emails_found": "",
                "pages_checked": "",
                "review_status": "No website",
            }
        else:
            result = extractor.extract_row(row, email_registry=email_registry)

        out_rows.append(result)
        done_keys.add(key)

        best_count = len([part for part in result["best_emails"].split(";") if part.strip()])
        other_count = len([part for part in result["other_business_emails"].split(";") if part.strip()])
        print(
            f"{len(out_rows)}/{len(rows)} {row.get('agency_name')} {row.get('town')}: "
            f"{best_count} best, {other_count} other"
        )

        if len(out_rows) % 25 == 0:
            save_checkpoint(checkpoint_path, out_rows)

    write_extracted(output_path, out_rows)
    print(f"Saved {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
