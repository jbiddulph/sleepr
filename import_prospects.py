#!/usr/bin/env python3
"""Import a prospect or extracted-email CSV into Supabase sleepr_estate_agent_prospects."""
from __future__ import annotations

import argparse
import csv
import os
import sys
import uuid
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


def map_outreach_status(review_status: str, has_email: bool = False) -> str:
    review_status = (review_status or "").strip().lower()
    if review_status == "review before outreach":
        return "ready"
    if "no town match" in review_status:
        return "reviewing"
    if review_status in {"no email found", "no website"}:
        return "no_email"
    if has_email:
        return "ready"
    return "pending"


def api_headers(key: str) -> dict[str, str]:
    return {
        "apikey": key,
        "Authorization": f"Bearer {key}",
        "Content-Type": "application/json",
    }


def fetch_group_id(url: str, key: str, group_name: str) -> str | None:
    response = requests.get(
        f"{url}/rest/v1/{GROUPS_TABLE}",
        headers=api_headers(key),
        params={"name": f"eq.{group_name}", "select": "id"},
        timeout=30,
    )
    response.raise_for_status()
    rows = response.json()
    return rows[0]["id"] if rows else None


def ensure_group_id(url: str, key: str, group_name: str) -> str:
    existing = fetch_group_id(url, key, group_name)
    if existing:
        return existing

    group_id = str(uuid.uuid4())
    response = requests.post(
        f"{url}/rest/v1/{GROUPS_TABLE}",
        headers={**api_headers(key), "Prefer": "return=minimal"},
        json={"id": group_id, "name": group_name},
        timeout=30,
    )
    if response.status_code not in (200, 201):
        raise RuntimeError(
            f"Failed to create group '{group_name}': {response.status_code} {response.text}"
        )

    print(f"Created group '{group_name}' ({group_id})")
    return group_id


def load_email_lookup(path: Path) -> dict[tuple[str, str], dict[str, str]]:
    if not path.exists():
        return {}

    lookup: dict[tuple[str, str], dict[str, str]] = {}
    with path.open(newline="", encoding="utf-8") as handle:
        for row in csv.DictReader(handle):
            key = (row.get("agency_name", "").strip(), row.get("town", "").strip())
            lookup[key] = row
    return lookup


def row_to_record(
    row: dict[str, str],
    group_id: str | None,
    email_lookup: dict[tuple[str, str], dict[str, str]] | None = None,
) -> dict:
    key = (row.get("agency_name", "").strip(), row.get("town", "").strip())
    email_row = None
    if "best_emails" in row or "review_status" in row:
        email_row = row
    elif email_lookup:
        email_row = email_lookup.get(key)

    best = parse_emails((email_row or {}).get("best_emails", ""))
    other = parse_emails((email_row or {}).get("other_business_emails", ""))
    found = parse_emails((email_row or {}).get("emails_found", ""))
    review_status = (email_row or {}).get("review_status", row.get("status", "")).strip()

    record = {
        "agency_name": key[0],
        "town": key[1],
        "website": row.get("website", "").strip() or None,
        "contact_page_url": row.get("contact_page_url", "").strip() or None,
        "best_emails": best,
        "other_business_emails": other,
        "emails_found": found,
        "pages_checked": (email_row or {}).get("pages_checked", "").strip() or None,
        "review_status": review_status or None,
        "outreach_status": map_outreach_status(review_status, has_email=bool(best or found)),
        "selected_email": best[0] if best else (found[0] if found else None),
    }
    if group_id:
        record["group_id"] = group_id
    return record


def default_emails_path(input_path: Path) -> Path:
    if input_path.stem.endswith("_prospects"):
        return input_path.with_name(input_path.stem.replace("_prospects", "_emails") + ".csv")
    return input_path.with_name("extracted_" + input_path.stem + "_emails.csv")


def main() -> int:
    parser = argparse.ArgumentParser(description="Import prospects into Supabase.")
    parser.add_argument("--input", type=Path, required=True, help="Prospect or extracted CSV path")
    parser.add_argument(
        "--emails",
        type=Path,
        help="Optional extracted-emails CSV to merge (defaults to sibling *_emails.csv)",
    )
    parser.add_argument(
        "--group",
        default="Software agencies",
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

    emails_path = args.emails or default_emails_path(args.input)
    email_lookup = load_email_lookup(emails_path)
    if email_lookup:
        print(f"Merging email data from {emails_path} ({len(email_lookup)} rows)")

    group_id = ensure_group_id(url, key, args.group) if args.group else None

    with args.input.open(newline="", encoding="utf-8") as handle:
        rows = [row_to_record(row, group_id, email_lookup) for row in csv.DictReader(handle)]

    endpoint = f"{url}/rest/v1/{TABLE}?on_conflict=agency_name,town"
    headers = {
        **api_headers(key),
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

    print(f"Done. Upserted {imported} prospects into {TABLE} (group: {args.group}).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
