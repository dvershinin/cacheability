import os
import time
import requests
import pytest
from urllib.parse import urlparse, urlunparse

WP_URL = os.environ.get("WP_URL", "http://localhost:8089")
_parsed = urlparse(WP_URL)
HOST_HEADER_VALUE = "localhost:8089" if _parsed.hostname == "wordpress" else _parsed.netloc


def _host_headers():
    """Return headers with the proper Host header for WordPress."""
    return {"Host": HOST_HEADER_VALUE}


def follow_redirects_in_container(url, headers=None, max_redirects=10):
    """
    Follow redirects while staying within the Docker network.
    WordPress may redirect to localhost:PORT but we need to follow to wordpress container.
    """
    if headers is None:
        headers = _host_headers()

    current_url = url
    for _ in range(max_redirects):
        response = requests.get(current_url, allow_redirects=False, timeout=10, headers=headers)
        if response.status_code not in (301, 302, 303, 307, 308):
            return response
        # Get redirect location and rewrite to use wordpress container
        location = response.headers.get('Location', '')
        if not location:
            return response
        parsed = urlparse(location)
        # Rewrite to use wordpress container URL
        dest = urlparse(WP_URL)
        current_url = urlunparse((dest.scheme, dest.netloc, parsed.path, parsed.params, parsed.query, parsed.fragment))
    return response


def wait_http_ok(url: str, timeout: float = 60.0, headers=None, accept_codes=None):
    """Wait until an HTTP endpoint responds with one of acceptable status codes."""
    if accept_codes is None:
        accept_codes = {200, 301, 302, 403, 503}
    start = time.time()
    while time.time() - start < timeout:
        try:
            r = requests.head(url, timeout=3, allow_redirects=False, headers=headers or _host_headers())
            if r.status_code in accept_codes:
                return
        except Exception:
            pass
        time.sleep(1)
    raise RuntimeError(f"Timeout waiting for {url}")


@pytest.fixture(scope="session", autouse=True)
def ensure_up():
    """Ensure WordPress is up before running tests."""
    if os.environ.get("CACHEABILITY_SKIP_ENSURE_UP") == "1":
        return

    try:
        wait_http_ok(WP_URL, timeout=120.0)
    except Exception:
        pass


@pytest.fixture(scope="session")
def wp_url():
    """Return the WordPress URL."""
    return WP_URL


@pytest.fixture()
def fresh_post():
    """Create a fresh post for testing via REST API."""
    r = requests.post(
        f"{WP_URL}/wp-json/test/v1/post",
        json={"title": f"Test Post {time.time()}", "content": "Test content"},
        headers=_host_headers()
    )
    r.raise_for_status()
    data = r.json()
    # Rewrite URL to use container address
    orig = urlparse(data["url"])
    dest = urlparse(WP_URL)
    new_url = urlunparse((dest.scheme, dest.netloc, orig.path, orig.params, orig.query, orig.fragment))
    return data["id"], new_url
