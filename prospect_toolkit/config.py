from __future__ import annotations

from dataclasses import dataclass, field

PROSPECT_CSV_FIELDS = [
    "agency_name",
    "town",
    "website",
    "contact_page_url",
    "region_focus",
    "status",
    "notes",
]

EXTRACTED_CSV_FIELDS = [
    "agency_name",
    "town",
    "website",
    "contact_page_url",
    "best_emails",
    "other_business_emails",
    "emails_found",
    "pages_checked",
    "review_status",
]


@dataclass(frozen=True)
class IndustryConfig:
    slug: str
    label: str
    region_focus: str
    status: str
    notes: str
    contact_paths: tuple[str, ...] = field(default_factory=tuple)
    link_keywords: tuple[str, ...] = field(default_factory=tuple)
    generic_prefixes: frozenset[str] = field(default_factory=frozenset)
    branch_paths: tuple[str, ...] = field(default_factory=tuple)
    use_town_branch_discovery: bool = True


COMMON_CONTACT_PATHS = (
    "/contact-us",
    "/contact",
    "/get-in-touch",
    "/about/contact",
    "/about-us/contact",
    "/support/contact",
    "/company/contact",
)

COMMON_LINK_KEYWORDS = (
    "contact",
    "get-in-touch",
    "reach-us",
    "talk-to-us",
)

COMMON_GENERIC_PREFIXES = frozenset({
    "info",
    "hello",
    "contact",
    "sales",
    "enquiries",
    "enquiry",
    "office",
    "admin",
    "support",
    "help",
    "customerservice",
    "mail",
    "team",
    "careers",
    "jobs",
    "press",
    "media",
    "partnerships",
    "business",
})


INDUSTRIES: dict[str, IndustryConfig] = {
    "estate_agents": IndustryConfig(
        slug="estate_agents",
        label="Estate agents",
        region_focus="UK",
        status="Needs manual verification",
        notes="Use published website/contact page; verify branch exists before outreach.",
        contact_paths=COMMON_CONTACT_PATHS + (
            "/branches",
            "/our-offices",
            "/find-us",
            "/estate-agents",
        ),
        link_keywords=COMMON_LINK_KEYWORDS + (
            "branch",
            "office",
            "estate-agent",
            "estate-agents",
            "our-offices",
            "find-us",
        ),
        generic_prefixes=COMMON_GENERIC_PREFIXES | frozenset({
            "lettings",
            "property",
            "properties",
        }),
        branch_paths=("/branches", "/our-offices", "/estate-agents"),
        use_town_branch_discovery=True,
    ),
    "software": IndustryConfig(
        slug="software",
        label="Software companies",
        region_focus="UK",
        status="Needs manual verification",
        notes="Use published website/contact page; verify company details before outreach.",
        contact_paths=COMMON_CONTACT_PATHS + (
            "/about",
            "/about-us",
            "/company",
            "/team",
            "/support",
            "/sales",
        ),
        link_keywords=COMMON_LINK_KEYWORDS + (
            "about",
            "team",
            "support",
            "sales",
            "demo",
            "book-a-demo",
        ),
        generic_prefixes=COMMON_GENERIC_PREFIXES | frozenset({
            "demo",
            "product",
            "billing",
            "security",
            "privacy",
            "legal",
            "hr",
            "recruitment",
        }),
        branch_paths=("/locations", "/offices", "/contact-us"),
        use_town_branch_discovery=False,
    ),
    "property_management": IndustryConfig(
        slug="property_management",
        label="Property management companies",
        region_focus="UK",
        status="Needs manual verification",
        notes="Use published website/contact page; verify company details before outreach.",
        contact_paths=COMMON_CONTACT_PATHS + (
            "/branches",
            "/our-offices",
            "/find-us",
            "/about-us",
            "/services",
            "/landlords",
            "/tenants",
        ),
        link_keywords=COMMON_LINK_KEYWORDS + (
            "branch",
            "office",
            "our-offices",
            "find-us",
            "landlords",
            "tenants",
            "services",
        ),
        generic_prefixes=COMMON_GENERIC_PREFIXES | frozenset({
            "lettings",
            "property",
            "properties",
            "rentals",
            "maintenance",
            "repairs",
            "facilities",
        }),
        branch_paths=("/branches", "/our-offices", "/contact-us"),
        use_town_branch_discovery=True,
    ),
    "construction": IndustryConfig(
        slug="construction",
        label="Construction companies",
        region_focus="UK",
        status="Needs manual verification",
        notes="Use published website/contact page; verify company details before outreach.",
        contact_paths=COMMON_CONTACT_PATHS + (
            "/about-us",
            "/about",
            "/our-work",
            "/projects",
            "/services",
            "/quote",
            "/get-a-quote",
            "/request-a-quote",
        ),
        link_keywords=COMMON_LINK_KEYWORDS + (
            "quote",
            "get-a-quote",
            "request-a-quote",
            "projects",
            "our-work",
            "services",
            "about",
        ),
        generic_prefixes=COMMON_GENERIC_PREFIXES | frozenset({
            "quotes",
            "estimating",
            "projects",
            "site",
            "contracts",
            "procurement",
            "healthandsafety",
            "hse",
        }),
        branch_paths=("/contact-us", "/locations", "/areas-we-cover"),
        use_town_branch_discovery=True,
    ),
}


def get_industry(slug: str) -> IndustryConfig:
    if slug not in INDUSTRIES:
        known = ", ".join(sorted(INDUSTRIES))
        raise ValueError(f"Unknown industry '{slug}'. Known industries: {known}")
    return INDUSTRIES[slug]
