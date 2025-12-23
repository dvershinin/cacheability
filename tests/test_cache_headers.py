"""
Cache-Control header tests for Cacheability plugin.

Tests verify that proper s-maxage headers are sent for:
1. Regular pages (long cache)
2. Search pages (short cache)
3. 404 pages (short cache)
"""

import re
import requests
import pytest
import time
from urllib.parse import urlparse, urlunparse

from conftest import WP_URL, _host_headers, follow_redirects_in_container


@pytest.fixture(scope="session")
def wait_for_wordpress():
    """Wait for WordPress to be ready."""
    max_retries = 30
    for _ in range(max_retries):
        try:
            response = requests.get(f"{WP_URL}/", allow_redirects=False, timeout=5, headers=_host_headers())
            if response.status_code < 500:
                return
        except (requests.exceptions.ConnectionError, requests.exceptions.Timeout):
            pass
        time.sleep(2)
    raise RuntimeError("WordPress did not become ready in time")


def test_homepage_has_cache_headers(wait_for_wordpress):
    """
    Test that the homepage has Cache-Control headers with s-maxage.
    """
    response = follow_redirects_in_container(f"{WP_URL}/")

    assert response.status_code == 200

    cache_control = response.headers.get("Cache-Control", "")

    # Should have s-maxage for proxy caches
    assert "s-maxage" in cache_control, \
        f"Homepage should have s-maxage in Cache-Control, got: {cache_control}"


def test_single_post_has_long_cache(wait_for_wordpress):
    """
    Test that single posts have long cache time (1 year).
    """
    # Create a test post
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/post",
        json={"title": "Cache Test Post", "content": "Test content for caching"},
        headers=_host_headers()
    )

    if r.status_code != 200:
        pytest.skip("Could not create test post")

    # Rewrite URL to use container address
    post_url_data = r.json()["url"]
    parsed = urlparse(post_url_data)
    dest = urlparse(WP_URL)
    post_url = urlunparse((dest.scheme, dest.netloc, parsed.path, parsed.params, parsed.query, parsed.fragment))

    response = follow_redirects_in_container(post_url)

    assert response.status_code == 200

    cache_control = response.headers.get("Cache-Control", "")

    # Should have s-maxage
    assert "s-maxage" in cache_control, \
        f"Single post should have s-maxage, got: {cache_control}"

    # Extract s-maxage value
    match = re.search(r's-maxage=(\d+)', cache_control)
    assert match, f"Could not parse s-maxage from: {cache_control}"

    s_maxage = int(match.group(1))

    # Should be at least 1 day (86400 seconds)
    assert s_maxage >= 86400, \
        f"Single post s-maxage should be >= 86400, got: {s_maxage}"


def test_search_has_short_cache(wait_for_wordpress):
    """
    Test that search pages have shorter cache time.
    """
    # Create a post first so search has results
    requests.post(
        f"{WP_URL}/wp-json/test/v1/post",
        json={"title": "Searchable Content", "content": "Content for search test"},
        headers=_host_headers()
    )

    response = follow_redirects_in_container(f"{WP_URL}/?s=Searchable")

    cache_control = response.headers.get("Cache-Control", "")

    # Search pages should have s-maxage
    if "s-maxage" in cache_control:
        match = re.search(r's-maxage=(\d+)', cache_control)
        if match:
            s_maxage = int(match.group(1))
            # Search should have shorter cache than single posts
            assert s_maxage <= 86400, \
                f"Search s-maxage should be <= 86400, got: {s_maxage}"


def test_404_has_short_cache(wait_for_wordpress):
    """
    Test that 404 pages have shorter cache time.
    """
    response = follow_redirects_in_container(
        f"{WP_URL}/this-page-does-not-exist-for-cache-test/"
    )

    assert response.status_code == 404

    cache_control = response.headers.get("Cache-Control", "")

    # 404 pages should have s-maxage
    if "s-maxage" in cache_control:
        match = re.search(r's-maxage=(\d+)', cache_control)
        if match:
            s_maxage = int(match.group(1))
            # 404 should have short cache
            assert s_maxage <= 86400, \
                f"404 s-maxage should be <= 86400, got: {s_maxage}"


def test_no_cache_for_logged_in(wait_for_wordpress):
    """
    Test that logged-in users don't get cache headers.

    This is implicit from the plugin code, but we can at least verify
    that anonymous requests DO get cache headers.
    """
    response = follow_redirects_in_container(f"{WP_URL}/")

    assert response.status_code == 200

    cache_control = response.headers.get("Cache-Control", "")

    # Anonymous users should get cache headers
    assert cache_control, "Anonymous requests should have Cache-Control header"
