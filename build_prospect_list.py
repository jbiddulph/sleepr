#!/usr/bin/env python3
"""
Build a prospect CSV for any supported industry.

Output columns match uk_estate_agency_prospects_350.csv:
agency_name,town,website,contact_page_url,region_focus,status,notes

Examples:
  python build_prospect_list.py --industry software --target 400
  python build_prospect_list.py --industry estate_agents --source seed --seed prospect_seeds/estate_agents_uk.csv
"""
from __future__ import annotations

import argparse
from pathlib import Path

from prospect_toolkit.config import get_industry
from prospect_toolkit.csv_io import write_prospects
from prospect_toolkit.sources import build_prospect_list

DEFAULT_SOURCES = ("builtin", "wikipedia", "seed")


def default_output(industry_slug: str) -> str:
    return f"uk_{industry_slug}_prospects.csv"


def default_seed(industry_slug: str) -> Path:
    return Path(__file__).parent / "prospect_seeds" / f"{industry_slug}_uk.csv"


def main() -> int:
    parser = argparse.ArgumentParser(description="Build a prospect CSV for outreach.")
    parser.add_argument(
        "--industry",
        default="software",
        help="Industry preset (estate_agents, software)",
    )
    parser.add_argument(
        "--output",
        help="Output CSV path (defaults to uk_<industry>_prospects.csv)",
    )
    parser.add_argument(
        "--target",
        type=int,
        default=10000,
        help="Minimum number of unique prospects to collect",
    )
    parser.add_argument(
        "--source",
        action="append",
        choices=["builtin", "wikipedia", "companies_house", "seed"],
        help="Data source to use (repeatable). Defaults to builtin,wikipedia,seed",
    )
    parser.add_argument(
        "--seed",
        type=Path,
        help="Optional seed CSV with agency_name,town,website columns",
    )
    parser.add_argument(
        "--delay",
        type=float,
        default=0.35,
        help="Delay between Built In profile requests",
    )
    parser.add_argument(
        "--enrich-contacts",
        action="store_true",
        help="Probe each website to discover contact page URLs",
    )
    args = parser.parse_args()

    industry = get_industry(args.industry)
    sources = args.source or list(DEFAULT_SOURCES)
    seed_path = args.seed or default_seed(args.industry)
    output_path = Path(args.output or default_output(args.industry))

    rows = build_prospect_list(
        industry=industry,
        target=args.target,
        sources=sources,
        seed_path=seed_path,
        delay=args.delay,
        enrich_contacts=args.enrich_contacts,
    )

    write_prospects(output_path, rows)
    print(f"Saved {len(rows)} prospects to {output_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
