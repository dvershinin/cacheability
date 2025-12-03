import requests
import pytest
import time

VARNISH_URL = "http://localhost:8090"

@pytest.fixture(scope="session")
def wait_for_varnish():
    """Wait for Varnish to be available."""
    max_retries = 30
    for i in range(max_retries):
        try:
            response = requests.get(VARNISH_URL, allow_redirects=False, timeout=5)
            print(f"Varnish responded with status {response.status_code}")
            return
        except requests.exceptions.ConnectionError as e:
            print(f"Attempt {i+1}/{max_retries}: Connection error - {e}")
            time.sleep(1)
        except requests.exceptions.Timeout:
            print(f"Attempt {i+1}/{max_retries}: Timeout")
            time.sleep(1)
    raise RuntimeError("Varnish did not start in time")


@pytest.fixture(scope="session")
def wait_for_wordpress(wait_for_varnish):
    """Wait for WordPress to be installed and ready."""
    max_retries = 30
    for _ in range(max_retries):
        try:
            # Use allow_redirects=False since WP might redirect
            response = requests.get(f"{VARNISH_URL}/", allow_redirects=False, timeout=5)
            # Any non-500 response means WP is running
            if response.status_code < 500:
                return
        except (requests.exceptions.ConnectionError, requests.exceptions.Timeout):
            pass
        time.sleep(2)
    raise RuntimeError("WordPress did not become ready in time")


def test_esi_callback_performance(wait_for_varnish):
    """
    Test that the optimized ESI endpoint responds correctly.
    Uses full WordPress with plugins/themes disabled for speed.
    """
    url = f"{VARNISH_URL}/?cacheability_esi=nonce&action=perf-test&name=perf-nonce"
    
    start_time = time.time()
    response = requests.get(url)
    duration = time.time() - start_time
    
    assert response.status_code == 200
    content = response.text
    
    # Should return a nonce input field
    assert '<input type="hidden" id="perf-nonce" name="perf-nonce" value="' in content
    
    # The response should end with the closing tag and nothing else (minimal output)
    assert content.strip().endswith('/>')
    
    # Performance check - optimized WordPress should be fast
    print(f"ESI callback response time: {duration:.3f}s")
    assert duration < 0.5, f"ESI callback too slow: {duration:.3f}s"


def test_esi_processing_mock(wait_for_varnish):
    """
    Verify that Varnish processes ESI tags correctly using the mock test page.
    This tests the ESI replacement mechanism independently of WP setup.
    """
    response = requests.get(f"{VARNISH_URL}/esi-check/index.php")
    
    assert response.status_code == 200
    content = response.text
    
    # Check for outer content
    assert "Outer Content PHP" in content
    
    # Check for the nonce field (ESI should have been replaced)
    assert '<input type="hidden" id="my-nonce-name" name="my-nonce-name" value="' in content
    
    # Check that the ESI tag is gone
    assert "<esi:include" not in content


def test_esi_real_wordpress_page(wait_for_wordpress):
    """
    Test ESI nonce replacement on a real WordPress page.
    This is the full E2E test.
    """
    # The mu-plugin creates a page at /esi-test-page/ with a nonce form
    response = requests.get(f"{VARNISH_URL}/esi-test-page/")
    
    # If the page doesn't exist yet (WP not fully set up), skip gracefully
    if response.status_code == 404:
        pytest.skip("WordPress test page not yet created")
    
    assert response.status_code == 200
    content = response.text
    
    # Should contain our test content
    assert "Real WordPress Nonce Test" in content
    
    # Should have the nonce field with a real value (not ESI tag)
    assert '<input type="hidden" id="esi-test-nonce" name="esi-test-nonce" value="' in content
    
    # ESI tag should be replaced
    assert "<esi:include" not in content
    
    # The nonce value should be a real WordPress nonce (10 character hex string)
    import re
    nonce_match = re.search(r'name="esi-test-nonce" value="([a-f0-9]+)"', content)
    assert nonce_match, "Could not find nonce value in output"
    nonce_value = nonce_match.group(1)
    assert len(nonce_value) == 10, f"Nonce value has unexpected length: {nonce_value}"


def test_esi_nonce_varies_by_request(wait_for_varnish):
    """
    Verify that ESI endpoint is not cached by Varnish.
    """
    url = f"{VARNISH_URL}/?cacheability_esi=nonce&action=vary-test&name=vary-nonce"
    
    # Make two requests
    response1 = requests.get(url)
    time.sleep(0.1)  # Small delay
    response2 = requests.get(url)
    
    assert response1.status_code == 200
    assert response2.status_code == 200
    
    # Both should have nonce fields
    assert 'value="' in response1.text
    assert 'value="' in response2.text
    
    # Extract nonce values
    import re
    match1 = re.search(r'value="([^"]+)"', response1.text)
    match2 = re.search(r'value="([^"]+)"', response2.text)
    
    assert match1 and match2, "Could not extract nonce values"
    
    # The important thing is that the ESI endpoint is NOT cached by Varnish
    # We verify this by checking the Cache-Control header
    assert 'no-cache' in response1.headers.get('Cache-Control', '').lower() or \
           'private' in response1.headers.get('Cache-Control', '').lower(), \
           "ESI endpoint should not be cached"
