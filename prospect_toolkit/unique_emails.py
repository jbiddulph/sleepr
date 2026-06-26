from __future__ import annotations

from urllib.parse import urlparse


class UniqueEmailRegistry:
    """Ensure each email is only assigned to one company unless domains match."""

    def __init__(self) -> None:
        self.claimed_by_domain: dict[str, str] = {}

    @staticmethod
    def site_domain(website: str) -> str:
        return urlparse(website).netloc.lower().removeprefix("www.")

    def can_assign(self, email: str, website: str) -> bool:
        owner = self.claimed_by_domain.get(email)
        if owner is None:
            return True

        site_domain = self.site_domain(website)
        email_domain = email.split("@", 1)[1]
        return owner == site_domain or email_domain == site_domain

    def assign(self, email: str, website: str) -> None:
        self.claimed_by_domain[email] = self.site_domain(website)

    def pick_unique(self, ranked_emails: list[str], website: str) -> list[str]:
        selected: list[str] = []
        for email in ranked_emails:
            if not self.can_assign(email, website):
                continue
            selected.append(email)
            self.assign(email, website)
        return selected
