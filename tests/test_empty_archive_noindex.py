"""
Empty-archive noindex tests for Cacheability plugin.

Defense-in-depth complement to the soft-404 fix: even when fix_soft_404
returns HTTP 404, the wpseo_robots / wp_robots filters add `noindex, follow`
so that crawlers which ignore the status code (or sites where another plugin
overrides the 404 back to 200) still get a clean SEO signal.

Verifies exactly one `<meta name="robots">` tag in the response body and that
its content includes `noindex` for:

1. Empty tag archives
2. Empty category archives
3. Search with no results
"""

import re
import time
import requests
import pytest

from conftest import WP_URL, _host_headers, follow_redirects_in_container


ROBOTS_META_RE = re.compile(
    r'<meta[^>]+name=["\']robots["\'][^>]*>',
    re.IGNORECASE,
)


def _assert_single_noindex_meta(body):
    matches = ROBOTS_META_RE.findall(body)
    assert len(matches) == 1, (
        f"Expected exactly one <meta name=\"robots\"> tag, got {len(matches)}: {matches}"
    )
    assert "noindex" in matches[0].lower(), (
        f"Expected robots meta to include 'noindex', got: {matches[0]}"
    )


def _unique_slug(prefix):
    return f"{prefix}-{int(time.time() * 1000)}"


def test_empty_tag_emits_noindex():
    slug = _unique_slug("noindex-tag")
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/tag",
        json={"name": slug, "slug": slug},
        headers=_host_headers(),
    )
    if r.status_code != 200:
        pytest.skip(f"Could not create test tag (status {r.status_code})")

    response = follow_redirects_in_container(f"{WP_URL}/tag/{slug}/")
    _assert_single_noindex_meta(response.text)


def test_empty_category_emits_noindex():
    slug = _unique_slug("noindex-cat")
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/category",
        json={"name": slug, "slug": slug},
        headers=_host_headers(),
    )
    if r.status_code != 200:
        pytest.skip(f"Could not create test category (status {r.status_code})")

    response = follow_redirects_in_container(f"{WP_URL}/category/{slug}/")
    _assert_single_noindex_meta(response.text)


def test_empty_search_emits_noindex():
    needle = _unique_slug("zzznothingmatches")
    response = follow_redirects_in_container(f"{WP_URL}/?s={needle}")
    _assert_single_noindex_meta(response.text)
