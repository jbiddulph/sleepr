#!/usr/bin/env python3
"""Import an extracted prospect CSV into Supabase sleepr_estate_agent_prospects."""
from __future__ import annotations

import argparse
import csv
import os
import sys
from pathlib import Path

import requests

TABLE = "sleepr_estate_agent_prospects"
GROUPS_TABLE = "sleepr_estate_agent_prospect_groups"
BATCH_SIZE = 50


def load_env(path: Path) -> None:
    if not path.exists():
        return
    for line in path.read_text().splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        value = value.strip().strip('"').strip("'")
        os.environ.setdefault(key, value)


def parse_emails(value: str) -> list[str]:
    if not value or not value.strip():
        return []
    return [email.strip() for email in value.split(";") if email.strip()]


def map_outreach_status(review_status: str) -> str:
    review_status = (review_status or "").strip().lower()
    if review_status == "review before outreach":
        return "ready"
    if "no town match" in review_status:
        return "reviewing"
    if review_status == "no email found":
        return "no_email"
    return "pending"


def fetch_group_id(url: str, key: str, group_name: str) -> str | None:
    response = requests.get(
        f"{url}/rest/v1/{GROUPS_TABLE}",
        headers={
            "apikey": key,
            "Authorization": f"Bearer {key}",
        },
        params={"name": f"eq.{group_name}", "select": "id"},
        timeout=30,
    )
    response.raise_for_status()
    rows = response.json()
    return rows[0]["id"] if rows else None


def row_to_record(row: dict, group_id: str | None) -> dict:
    best = parse_emails(row.get("best_emails", ""))
    other = parse_emails(row.get("other_business_emails", ""))
    found = parse_emails(row.get("emails_found", ""))
    review_status = row.get("review_status", "").strip()

    record = {
        "agency_name": row.get("agency_name", "").strip(),
        "town": row.get("town", "").strip(),
        "website": row.get("website", "").strip() or None,
        "contact_page_url": row.get("contact_page_url", "").strip() or None,
        "best_emails": best,
        "other_business_emails": other,
        "emails_found": found,
        "pages_checked": row.get("pages_checked", "").strip() or None,
        "review_status": review_status or None,
        "outreach_status": map_outreach_status(review_status),
        "selected_email": best[0] if best else None,
    }
    if group_id:
        record["group_id"] = group_id
    return record


def main() -> int:
    parser = argparse.ArgumentParser(description="Import extracted prospects into Supabase.")
    parser.add_argument("--input", type=Path, required=True, help="Extracted CSV path")
    parser.add_argument(
        "--group",
        default="Agents",
        help="Prospect group name in sleepr_estate_agent_prospect_groups",
    )
    args = parser.parse_args()

    load_env(Path(__file__).parent / ".env")

    url = os.environ.get("SUPABASE_URL", "").rstrip("/")
    key = os.environ.get("SUPABASE_SERVICE_ROLE_KEY", "")
    if not url or not key:
        print("SUPABASE_URL and SUPABASE_SERVICE_ROLE_KEY are required in .env", file=sys.stderr)
        return 1

    if not args.input.exists():
        print(f"CSV not found: {args.input}", file=sys.stderr)
        return 1

    with args.input.open(newline="", encoding="utf-8") as handle:
        group_id = fetch_group_id(url, key, args.group) if args.group else None
        rows = [row_to_record(row, group_id) for row in csv.DictReader(handle)]

    endpoint = f"{url}/rest/v1/{TABLE}?on_conflict=agency_name,town"
    headers = {
        "apikey": key,
        "Authorization": f"Bearer {key}",
        "Content-Type": "application/json",
        "Prefer": "resolution=merge-duplicates,return=minimal",
    }

    imported = 0
    for index in range(0, len(rows), BATCH_SIZE):
        batch = rows[index : index + BATCH_SIZE]
        response = requests.post(endpoint, headers=headers, json=batch, timeout=60)
        if response.status_code not in (200, 201):
            print(
                f"Import failed at batch {index // BATCH_SIZE + 1}: "
                f"{response.status_code} {response.text}",
                file=sys.stderr,
            )
            return 1
        imported += len(batch)
        print(f"Imported {imported}/{len(rows)}")

    print(f"Done. Upserted {imported} prospects into {TABLE}.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
