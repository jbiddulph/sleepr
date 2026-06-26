#!/usr/bin/env python3
"""Backward-compatible wrapper around extract_prospect_emails.py for estate agents."""
from __future__ import annotations

import sys

from extract_prospect_emails import main


if __name__ == "__main__":
    if "--industry" not in sys.argv:
        sys.argv[1:1] = ["--industry", "estate_agents"]
    if "--input" not in sys.argv:
        sys.argv.extend(["--input", "uk_estate_agency_prospects_350.csv"])
    if "--output" not in sys.argv:
        sys.argv.extend(["--output", "extracted_estate_agent_emails.csv"])
    raise SystemExit(main())
