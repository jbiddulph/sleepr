#!/usr/bin/env python3
"""
Extract publicly listed business email addresses from estate agency websites/contact pages.

Input:  uk_estate_agency_prospects_350.csv
Output: extracted_estate_agent_emails.csv

Notes:
- Respects robots.txt via urllib.robotparser.
- Uses a polite delay between requests.
- Decodes Cloudflare-protected emails and common obfuscations.
- Follows branch/office links that match the prospect town.
- Prefers town-matched branch emails; also keeps generic business addresses.
- Review the output manually before sending any outreach.
"""
import argparse
import csv
import json
import re
import time
from urllib.parse import urlparse, urljoin
from urllib.robotparser import RobotFileParser

import requests
from bs4 import BeautifulSoup

EMAIL_RE = re.compile(r"[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}", re.I)
OBFUSCATED_RE = re.compile(
    r"([A-Z0-9._%+-]+)\s*(?:@|\[at\]|\(at\)|&#64;| at )\s*([A-Z0-9.-]+\.[A-Z]{2,})",
    re.I,
)
GENERIC_PREFIXES = {
    "info", "hello", "contact", "sales", "lettings", "enquiries", "enquiry",
    "office", "admin", "property", "properties", "customerservice", "mail",
}
BAD_EXTENSIONS = (".png", ".jpg", ".jpeg", ".gif", ".svg", ".webp", ".css", ".js")
BAD_DOMAINS = {
    "example.com", "wixpress.com", "sentry.io", "schema.org", "gravatar.com",
    "facebook.com", "google.com", "twitter.com", "instagram.com", "youtube.com",
    "tpos.co.uk", "starberry.tv", "homeflow.co.uk",
}
BAD_LOCAL_PREFIXES = ("noreply", "no-reply", "donotreply", "do-not-reply", "bounce")
CONTACT_PATHS = (
    "/branches", "/our-offices", "/find-us", "/estate-agents",
    "/contact-us", "/contact", "/get-in-touch", "/about/contact", "/about-us/contact",
)
LINK_KEYWORDS = (
    "contact", "branch", "office", "estate-agent", "estate-agents",
    "our-offices", "find-us", "get-in-touch",
)
HEADERS = {"User-Agent": "ZapTaskProspectingBot/1.0 (+https://www.zaptask.co.uk)"}
robots_cache = {}
html_cache = {}


def normalise_email(email: str) -> str:
    email = email.strip().strip(".,;:()[]<>\"'").lower()
    if email.startswith("u003e"):
        email = email[5:]
    return email


def decode_cfemail(encoded: str) -> str:
    key = int(encoded[:2], 16)
    return "".join(
        chr(int(encoded[i : i + 2], 16) ^ key)
        for i in range(2, len(encoded), 2)
    )


def looks_generic(email: str) -> bool:
    local = email.split("@", 1)[0].lower()
    return local in GENERIC_PREFIXES or any(local.startswith(p + ".") for p in GENERIC_PREFIXES)


def is_acceptable_email(email: str) -> bool:
    email = normalise_email(email)
    if not EMAIL_RE.fullmatch(email):
        return False
    if email.endswith(BAD_EXTENSIONS):
        return False
    local, _, domain = email.partition("@")
    if domain in BAD_DOMAINS:
        return False
    if any(local.startswith(prefix) for prefix in BAD_LOCAL_PREFIXES):
        return False
    if local.endswith(".png") or ".jpg@" in email:
        return False
    return True


def town_tokens(town: str) -> list[str]:
    town = town.lower().strip()
    tokens = {re.sub(r"[^a-z0-9]", "", town)}
    for part in re.split(r"[\s\-/,]+", town):
        part = re.sub(r"[^a-z0-9]", "", part)
        if len(part) >= 3:
            tokens.add(part)
    return sorted(tokens, key=len, reverse=True)


def email_matches_town(email: str, town: str) -> bool:
    target = email.lower()
    return any(token in target for token in town_tokens(town))


def score_email(email: str, town: str, website: str) -> int:
    score = 0
    local, _, domain = email.partition("@")
    site_domain = urlparse(website).netloc.lower().removeprefix("www.")
    if site_domain and (domain == site_domain or domain.endswith("." + site_domain)):
        score += 3
    if email_matches_town(email, town):
        score += 10
    if looks_generic(email):
        score += 2
    if re.search(r"^[a-z]+\.[a-z]+@", email):
        score -= 1
    if any(x in local for x in ("complaint", "compliance", "pr@", "digital@")):
        score -= 4
    return score


def allowed_by_robots(url: str) -> bool:
    parsed = urlparse(url)
    if not parsed.scheme or not parsed.netloc:
        return False
    root = f"{parsed.scheme}://{parsed.netloc}"
    if root not in robots_cache:
        rp = RobotFileParser()
        rp.set_url(urljoin(root, "/robots.txt"))
        try:
            rp.read()
        except Exception:
            return True
        robots_cache[root] = rp
    return robots_cache[root].can_fetch(HEADERS["User-Agent"], url)


def fetch(url: str, timeout: int = 15) -> str:
    if url in html_cache:
        return html_cache[url]
    if not allowed_by_robots(url):
        html_cache[url] = ""
        return ""
    try:
        r = requests.get(url, headers=HEADERS, timeout=timeout)
        content_type = r.headers.get("content-type", "")
        if "text/html" not in content_type and "application/xhtml" not in content_type:
            html_cache[url] = ""
            return ""
        if r.status_code >= 400:
            html_cache[url] = ""
            return ""
        html_cache[url] = r.text
        return r.text
    except requests.RequestException:
        html_cache[url] = ""
        return ""


def extract_emails_from_html(html: str) -> set[str]:
    emails = set()
    soup = BeautifulSoup(html, "html.parser")

    for match in EMAIL_RE.findall(html):
        if is_acceptable_email(match):
            emails.add(normalise_email(match))

    for a in soup.select('a[href^="mailto:"]'):
        href = a.get("href", "").split("?", 1)[0]
        for match in EMAIL_RE.findall(href.replace("mailto:", " ")):
            if is_acceptable_email(match):
                emails.add(normalise_email(match))

    for el in soup.select("[data-cfemail]"):
        decoded = decode_cfemail(el["data-cfemail"])
        if is_acceptable_email(decoded):
            emails.add(normalise_email(decoded))

    for script in soup.select('script[type="application/ld+json"]'):
        try:
            data = json.loads(script.string or "")
        except (json.JSONDecodeError, TypeError):
            continue
        blocks = data if isinstance(data, list) else [data]
        for block in blocks:
            blob = json.dumps(block)
            for match in EMAIL_RE.findall(blob):
                if is_acceptable_email(match):
                    emails.add(normalise_email(match))

    for local, domain in OBFUSCATED_RE.findall(html):
        candidate = normalise_email(f"{local}@{domain}")
        if is_acceptable_email(candidate):
            emails.add(candidate)

    return emails


def same_site(url: str, website: str) -> bool:
    return urlparse(url).netloc.lower() == urlparse(website).netloc.lower()


def discover_urls(website: str, contact_page_url: str, town: str) -> list[str]:
    urls = []
    for candidate in (contact_page_url, website):
        candidate = candidate.strip()
        if candidate and candidate not in urls:
            urls.append(candidate)

    base = website.strip().rstrip("/")
    for path in CONTACT_PATHS:
        candidate = base + path
        if candidate not in urls:
            urls.append(candidate)

    homepage = fetch(website)
    if homepage:
        soup = BeautifulSoup(homepage, "html.parser")
        tokens = town_tokens(town)
        for a in soup.select("a[href]"):
            href = a.get("href", "").strip()
            if not href or href.startswith("#"):
                continue
            full = urljoin(website, href)
            if not same_site(full, website):
                continue
            lower = full.lower()
            if any(keyword in lower for keyword in LINK_KEYWORDS):
                if full not in urls:
                    urls.append(full)
            if any(token in lower for token in tokens):
                if full not in urls:
                    urls.append(full)

    return urls


def pick_branch_urls(urls: list[str], website: str, town: str, html_by_url: dict[str, str]) -> list[str]:
    branch_urls = []
    tokens = town_tokens(town)
    for url in urls:
        html = html_by_url.get(url, "")
        if not html:
            continue
        soup = BeautifulSoup(html, "html.parser")
        for a in soup.select("a[href]"):
            href = a.get("href", "").strip()
            if not href:
                continue
            full = urljoin(url, href)
            if not same_site(full, website):
                continue
            lower = full.lower()
            if any(token in lower for token in tokens) and full not in branch_urls:
                branch_urls.append(full)
    return branch_urls


def classify_emails(emails: set[str], town: str, website: str) -> tuple[list[str], list[str]]:
    if not emails:
        return [], []
    ranked = sorted(emails, key=lambda e: (-score_email(e, town, website), e))
    best = [e for e in ranked if email_matches_town(e, town)]
    if not best:
        best = [e for e in ranked if looks_generic(e)]
    other = [e for e in ranked if e not in best]
    return best, other


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", default="uk_estate_agency_prospects_350.csv")
    parser.add_argument("--output", default="extracted_estate_agent_emails.csv")
    parser.add_argument("--delay", type=float, default=1.5)
    parser.add_argument("--max-pages-per-site", type=int, default=5)
    args = parser.parse_args()

    with open(args.input, newline="", encoding="utf-8") as f:
        rows = list(csv.DictReader(f))

    out_rows = []
    for i, row in enumerate(rows, start=1):
        website = row.get("website", "").strip()
        contact_page_url = row.get("contact_page_url", "").strip()
        town = row.get("town", "").strip()

        candidate_urls = discover_urls(website, contact_page_url, town)
        checked = []
        html_by_url = {}
        found = set()

        for url in candidate_urls:
            if len(checked) >= args.max_pages_per_site:
                break
            html = fetch(url)
            checked.append(url)
            if html:
                html_by_url[url] = html
                found.update(extract_emails_from_html(html))
            time.sleep(args.delay)

        branch_urls = pick_branch_urls(checked, website, town, html_by_url)
        for branch_url in branch_urls:
            if len(checked) >= args.max_pages_per_site + 3:
                break
            if branch_url in checked:
                continue
            html = fetch(branch_url)
            checked.append(branch_url)
            if html:
                found.update(extract_emails_from_html(html))
            time.sleep(args.delay)

        if not found:
            for path in ("/branches", "/our-offices", "/estate-agents"):
                fallback = website.rstrip("/") + path
                if fallback in checked:
                    continue
                html = fetch(fallback)
                checked.append(fallback)
                if html:
                    html_by_url[fallback] = html
                    found.update(extract_emails_from_html(html))
                    for branch_url in pick_branch_urls([fallback], website, town, {fallback: html}):
                        if branch_url in checked:
                            continue
                        branch_html = fetch(branch_url)
                        checked.append(branch_url)
                        if branch_html:
                            found.update(extract_emails_from_html(branch_html))
                        time.sleep(args.delay)
                time.sleep(args.delay)
                if found:
                    break

        best, other = classify_emails(found, town, website)
        other_display = other[:10]
        all_emails = best + other_display

        if best:
            status = "Review before outreach"
        elif other:
            status = "No town match; review generic emails"
        else:
            status = "No email found"

        out_rows.append({
            "agency_name": row.get("agency_name", ""),
            "town": town,
            "website": website,
            "contact_page_url": contact_page_url,
            "best_emails": "; ".join(best),
            "other_business_emails": "; ".join(other_display),
            "emails_found": "; ".join(all_emails),
            "pages_checked": "; ".join(checked),
            "review_status": status,
        })
        print(
            f"{i}/{len(rows)} {row.get('agency_name')} {town}: "
            f"{len(best)} best, {len(other)} other"
        )

    with open(args.output, "w", newline="", encoding="utf-8") as f:
        writer = csv.DictWriter(f, fieldnames=list(out_rows[0].keys()))
        writer.writeheader()
        writer.writerows(out_rows)
    print(f"Saved {args.output}")


if __name__ == "__main__":
    main()
