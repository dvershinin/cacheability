"""Exercise free features when Pro retains only ESI ownership."""

import re
from urllib.parse import quote

from conftest import WP_URL, follow_redirects_in_container


def test_pro_esi_only_keeps_free_features(fresh_post):
    """Verify real WordPress requests use the Pro mode selected at load time."""
    _, post_url = fresh_post

    full = follow_redirects_in_container(
        f"{post_url}?cacheability_pro_test_mode=full"
    )
    assert full.status_code == 200
    assert "s-maxage" not in full.headers.get("Cache-Control", "")

    esi_only = follow_redirects_in_container(
        f"{post_url}?cacheability_pro_test_mode=esi_only"
    )
    assert esi_only.status_code == 200
    assert "s-maxage=31536000" in esi_only.headers.get("Cache-Control", "")

    needle = quote("cacheability-esi-only-no-results-987654321")
    empty_search = follow_redirects_in_container(
        f"{WP_URL}/?s={needle}&cacheability_pro_test_mode=esi_only"
    )
    assert empty_search.status_code == 404
    robots_tags = re.findall(
        r'<meta[^>]+name=["\']robots["\'][^>]*>', empty_search.text, re.IGNORECASE
    )
    assert len(robots_tags) == 1
    assert "noindex" in robots_tags[0].lower()
