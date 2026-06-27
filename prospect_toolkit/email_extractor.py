from __future__ import annotations

import json
import re
import time
from urllib.parse import urljoin, urlparse
from urllib.robotparser import RobotFileParser

import requests
from bs4 import BeautifulSoup

from prospect_toolkit.config import IndustryConfig
from prospect_toolkit.unique_emails import UniqueEmailRegistry

EMAIL_RE = re.compile(r"[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}", re.I)
OBFUSCATED_RE = re.compile(
    r"([A-Z0-9._%+-]+)\s*(?:@|\[at\]|\(at\)|&#64;| at )\s*([A-Z0-9.-]+\.[A-Z]{2,})",
    re.I,
)
BAD_EXTENSIONS = (".png", ".jpg", ".jpeg", ".gif", ".svg", ".webp", ".css", ".js")
BAD_DOMAINS = {
    "example.com",
    "wixpress.com",
    "sentry.io",
    "schema.org",
    "gravatar.com",
    "facebook.com",
    "google.com",
    "twitter.com",
    "instagram.com",
    "youtube.com",
    "linkedin.com",
    "tpos.co.uk",
    "starberry.tv",
    "homeflow.co.uk",
    "jotform.com",
    "builtin.com",
}
BAD_LOCAL_PREFIXES = ("noreply", "no-reply", "donotreply", "do-not-reply", "bounce")
HEADERS = {"User-Agent": "ZapTaskProspectingBot/1.0 (+https://www.zaptask.co.uk)"}


class EmailExtractor:
    def __init__(
        self,
        industry: IndustryConfig,
        delay: float = 1.5,
        max_pages_per_site: int = 5,
    ) -> None:
        self.industry = industry
        self.delay = delay
        self.max_pages_per_site = max_pages_per_site
        self.robots_cache: dict[str, RobotFileParser | None] = {}
        self.html_cache: dict[str, str] = {}

    def extract_row(
        self,
        row: dict[str, str],
        email_registry: UniqueEmailRegistry | None = None,
    ) -> dict[str, str]:
        website = row.get("website", "").strip()
        contact_page_url = row.get("contact_page_url", "").strip()
        town = row.get("town", "").strip()

        candidate_urls = self.discover_urls(website, contact_page_url, town)
        checked: list[str] = []
        html_by_url: dict[str, str] = {}
        found: set[str] = set()

        for url in candidate_urls:
            if len(checked) >= self.max_pages_per_site:
                break
            html = self.fetch(url)
            checked.append(url)
            if html:
                html_by_url[url] = html
                found.update(self.extract_emails_from_html(html))
            time.sleep(self.delay)

        if self.industry.use_town_branch_discovery:
            branch_urls = self.pick_branch_urls(checked, website, town, html_by_url)
            for branch_url in branch_urls:
                if len(checked) >= self.max_pages_per_site + 3:
                    break
                if branch_url in checked:
                    continue
                html = self.fetch(branch_url)
                checked.append(branch_url)
                if html:
                    found.update(self.extract_emails_from_html(html))
                time.sleep(self.delay)

        if not found:
            for path in self.industry.branch_paths:
                fallback = website.rstrip("/") + path
                if fallback in checked:
                    continue
                html = self.fetch(fallback)
                checked.append(fallback)
                if html:
                    html_by_url[fallback] = html
                    found.update(self.extract_emails_from_html(html))
                    if self.industry.use_town_branch_discovery:
                        for branch_url in self.pick_branch_urls(
                            [fallback], website, town, {fallback: html}
                        ):
                            if branch_url in checked:
                                continue
                            branch_html = self.fetch(branch_url)
                            checked.append(branch_url)
                            if branch_html:
                                found.update(self.extract_emails_from_html(branch_html))
                            time.sleep(self.delay)
                time.sleep(self.delay)
                if found:
                    break

        best, other = self.classify_emails(found, town, website)

        if email_registry is not None:
            best = email_registry.pick_unique(best, website)
            other = [
                email
                for email in other
                if email_registry.can_assign(email, website)
                and email not in best
            ]
            claimed_other: list[str] = []
            for email in other:
                if email_registry.can_assign(email, website):
                    email_registry.assign(email, website)
                    claimed_other.append(email)
            other = claimed_other

        other_display = other[:10]
        all_emails = best + other_display

        if best:
            status = "Review before outreach"
        elif other:
            status = "No town match; review generic emails"
        else:
            status = "No email found"

        return {
            "agency_name": row.get("agency_name", ""),
            "town": town,
            "website": website,
            "contact_page_url": contact_page_url,
            "best_emails": "; ".join(best),
            "other_business_emails": "; ".join(other_display),
            "emails_found": "; ".join(all_emails),
            "pages_checked": "; ".join(checked),
            "review_status": status,
        }

    def normalise_email(self, email: str) -> str:
        email = email.strip().strip(".,;:()[]<>\"'").lower()
        if email.startswith("u003e"):
            email = email[5:]
        return email

    def decode_cfemail(self, encoded: str) -> str:
        key = int(encoded[:2], 16)
        return "".join(
            chr(int(encoded[i : i + 2], 16) ^ key)
            for i in range(2, len(encoded), 2)
        )

    def looks_generic(self, email: str) -> bool:
        local = email.split("@", 1)[0].lower()
        prefixes = self.industry.generic_prefixes
        return local in prefixes or any(local.startswith(prefix + ".") for prefix in prefixes)

    def is_acceptable_email(self, email: str) -> bool:
        email = self.normalise_email(email)
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

    def town_tokens(self, town: str) -> list[str]:
        town = town.lower().strip()
        tokens = {re.sub(r"[^a-z0-9]", "", town)}
        for part in re.split(r"[\s\-/,]+", town):
            part = re.sub(r"[^a-z0-9]", "", part)
            if len(part) >= 3:
                tokens.add(part)
        return sorted(tokens, key=len, reverse=True)

    def email_matches_town(self, email: str, town: str) -> bool:
        if not town:
            return False
        target = email.lower()
        return any(token in target for token in self.town_tokens(town))

    def score_email(self, email: str, town: str, website: str) -> int:
        score = 0
        local, _, domain = email.partition("@")
        site_domain = urlparse(website).netloc.lower().removeprefix("www.")
        if site_domain and (domain == site_domain or domain.endswith("." + site_domain)):
            score += 3
        if self.email_matches_town(email, town):
            score += 10
        if self.looks_generic(email):
            score += 2
        if re.search(r"^[a-z]+\.[a-z]+@", email):
            score -= 1
        if any(token in local for token in ("complaint", "compliance", "pr@", "digital@")):
            score -= 4
        return score

    def allowed_by_robots(self, url: str) -> bool:
        parsed = urlparse(url)
        if not parsed.scheme or not parsed.netloc:
            return False
        root = f"{parsed.scheme}://{parsed.netloc}"
        if root not in self.robots_cache:
            robot_parser = RobotFileParser()
            robots_url = urljoin(root, "/robots.txt")
            robot_parser.set_url(robots_url)
            try:
                response = requests.get(robots_url, headers=HEADERS, timeout=5)
                if response.status_code < 400:
                    robot_parser.parse(response.text.splitlines())
                else:
                    robot_parser.parse([])
            except Exception:
                self.robots_cache[root] = None
                return True
            self.robots_cache[root] = robot_parser
        robot = self.robots_cache[root]
        if robot is None:
            return True
        return robot.can_fetch(HEADERS["User-Agent"], url)

    def fetch(self, url: str, timeout: int = 15) -> str:
        if url in self.html_cache:
            return self.html_cache[url]
        if not self.allowed_by_robots(url):
            self.html_cache[url] = ""
            return ""
        try:
            response = requests.get(url, headers=HEADERS, timeout=timeout)
            content_type = response.headers.get("content-type", "")
            if "text/html" not in content_type and "application/xhtml" not in content_type:
                self.html_cache[url] = ""
                return ""
            if response.status_code >= 400:
                self.html_cache[url] = ""
                return ""
            self.html_cache[url] = response.text
            return response.text
        except requests.RequestException:
            self.html_cache[url] = ""
            return ""

    def extract_emails_from_html(self, html: str) -> set[str]:
        emails: set[str] = set()
        soup = BeautifulSoup(html, "html.parser")

        for match in EMAIL_RE.findall(html):
            if self.is_acceptable_email(match):
                emails.add(self.normalise_email(match))

        for anchor in soup.select('a[href^="mailto:"]'):
            href = anchor.get("href", "").split("?", 1)[0]
            for match in EMAIL_RE.findall(href.replace("mailto:", " ")):
                if self.is_acceptable_email(match):
                    emails.add(self.normalise_email(match))

        for element in soup.select("[data-cfemail]"):
            decoded = self.decode_cfemail(element["data-cfemail"])
            if self.is_acceptable_email(decoded):
                emails.add(self.normalise_email(decoded))

        for script in soup.select('script[type="application/ld+json"]'):
            try:
                data = json.loads(script.string or "")
            except (json.JSONDecodeError, TypeError):
                continue
            blocks = data if isinstance(data, list) else [data]
            for block in blocks:
                blob = json.dumps(block)
                for match in EMAIL_RE.findall(blob):
                    if self.is_acceptable_email(match):
                        emails.add(self.normalise_email(match))

        for local, domain in OBFUSCATED_RE.findall(html):
            candidate = self.normalise_email(f"{local}@{domain}")
            if self.is_acceptable_email(candidate):
                emails.add(candidate)

        return emails

    def same_site(self, url: str, website: str) -> bool:
        return urlparse(url).netloc.lower() == urlparse(website).netloc.lower()

    def discover_urls(self, website: str, contact_page_url: str, town: str) -> list[str]:
        urls: list[str] = []
        for candidate in (contact_page_url, website):
            candidate = candidate.strip()
            if candidate and candidate not in urls:
                urls.append(candidate)

        base = website.strip().rstrip("/")
        for path in self.industry.contact_paths:
            candidate = base + path
            if candidate not in urls:
                urls.append(candidate)

        homepage = self.fetch(website)
        if homepage:
            soup = BeautifulSoup(homepage, "html.parser")
            tokens = self.town_tokens(town) if self.industry.use_town_branch_discovery else []
            for anchor in soup.select("a[href]"):
                href = anchor.get("href", "").strip()
                if not href or href.startswith("#"):
                    continue
                full = urljoin(website, href)
                if not self.same_site(full, website):
                    continue
                lower = full.lower()
                if any(keyword in lower for keyword in self.industry.link_keywords):
                    if full not in urls:
                        urls.append(full)
                if tokens and any(token in lower for token in tokens):
                    if full not in urls:
                        urls.append(full)

        return urls

    def pick_branch_urls(
        self,
        urls: list[str],
        website: str,
        town: str,
        html_by_url: dict[str, str],
    ) -> list[str]:
        branch_urls: list[str] = []
        tokens = self.town_tokens(town)
        for url in urls:
            html = html_by_url.get(url, "")
            if not html:
                continue
            soup = BeautifulSoup(html, "html.parser")
            for anchor in soup.select("a[href]"):
                href = anchor.get("href", "").strip()
                if not href:
                    continue
                full = urljoin(url, href)
                if not self.same_site(full, website):
                    continue
                lower = full.lower()
                if any(token in lower for token in tokens) and full not in branch_urls:
                    branch_urls.append(full)
        return branch_urls

    def classify_emails(
        self,
        emails: set[str],
        town: str,
        website: str,
    ) -> tuple[list[str], list[str]]:
        if not emails:
            return [], []

        ranked = sorted(emails, key=lambda email: (-self.score_email(email, town, website), email))
        if self.industry.use_town_branch_discovery:
            best = [email for email in ranked if self.email_matches_town(email, town)]
        else:
            best = [email for email in ranked if self.looks_generic(email)]

        if not best and self.industry.use_town_branch_discovery:
            best = [email for email in ranked if self.looks_generic(email)]

        other = [email for email in ranked if email not in best]
        return best, other
