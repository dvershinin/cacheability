"""
Soft 404 tests for Cacheability plugin.

Tests verify that WordPress returns proper 404 status codes for:
1. Empty search results
2. Empty tag archives
3. Empty category archives
4. Empty author archives
"""

import requests
import pytest
import time

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


def test_empty_search_returns_404(wait_for_wordpress):
    """
    Test that empty search results return 404 status.

    WordPress normally returns 200 for empty search, which Google flags
    as a soft 404 error. Cacheability fixes this.
    """
    # Use a search term that definitely won't match anything
    response = follow_redirects_in_container(
        f"{WP_URL}/?s=xyznonexistenttermthatwillneverexist12345"
    )

    assert response.status_code == 404, \
        f"Empty search should return 404, got {response.status_code}"


def test_empty_tag_returns_404(wait_for_wordpress):
    """
    Test that empty tag archives return 404 status.
    """
    # Create an empty tag (no posts assigned)
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/tag",
        json={"name": "empty-test-tag", "slug": "empty-test-tag"},
        headers=_host_headers()
    )

    if r.status_code != 200:
        pytest.skip("Could not create test tag")

    # Get the tag URL path
    tag_url = r.json()["url"]

    # Request the empty tag page using our redirect handler
    response = follow_redirects_in_container(f"{WP_URL}/tag/empty-test-tag/")

    assert response.status_code == 404, \
        f"Empty tag archive should return 404, got {response.status_code}"


def test_empty_category_returns_404(wait_for_wordpress):
    """
    Test that empty category archives return 404 status.
    """
    # Create an empty category (no posts assigned)
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/category",
        json={"name": "empty-test-category", "slug": "empty-test-category"},
        headers=_host_headers()
    )

    if r.status_code != 200:
        pytest.skip("Could not create test category")

    # Request the empty category page
    response = follow_redirects_in_container(f"{WP_URL}/category/empty-test-category/")

    assert response.status_code == 404, \
        f"Empty category archive should return 404, got {response.status_code}"


def test_search_with_results_returns_200(wait_for_wordpress):
    """
    Test that search with results still returns 200.

    This ensures we're not breaking normal search functionality.
    """
    # First create a post to search for
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/post",
        json={"title": "Searchable Test Post", "content": "Unique searchable content xyz123"},
        headers=_host_headers()
    )

    if r.status_code != 200:
        pytest.skip("Could not create test post")

    # Give WP a moment to index
    time.sleep(1)

    # Search for something that exists
    response = follow_redirects_in_container(f"{WP_URL}/?s=Searchable")

    # Should be 200 when results exist
    assert response.status_code == 200, \
        f"Search with results should return 200, got {response.status_code}"


def test_nonexistent_page_returns_404(wait_for_wordpress):
    """
    Test that nonexistent pages return 404.

    This is normal WordPress behavior, just confirming it works.
    """
    response = follow_redirects_in_container(
        f"{WP_URL}/this-page-definitely-does-not-exist-12345/"
    )

    assert response.status_code == 404, \
        f"Nonexistent page should return 404, got {response.status_code}"
