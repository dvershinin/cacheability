# ESI Testing Environment

This directory contains a Docker-based test environment to verify Varnish ESI support with WordPress.

## Prerequisites

- Docker and Docker Compose
- Python 3 and pip

## Setup and Running Tests

1.  **Start the environment:**
    ```bash
    docker-compose up -d --build
    ```
    Wait about 30-40 seconds for WordPress to initialize.

2.  **Install test dependencies:**
    ```bash
    pip install -r tests/requirements.txt
    ```

3.  **Run the tests:**
    ```bash
    pytest tests/test_esi.py -v
    ```

## W3C Edge Architecture Standard

This plugin uses the **W3C Edge Side Includes (ESI) Language Specification 1.0** standard for detecting ESI capability:

### Request Flow

```
┌─────────┐  Surrogate-Capability: varnish="ESI/1.0"  ┌─────────────┐
│ Varnish │ ────────────────────────────────────────► │  WordPress  │
│         │ ◄──────────────────────────────────────── │             │
└─────────┘  Surrogate-Control: content="ESI/1.0"     └─────────────┘
```

1. **Varnish → Backend**: Sends `Surrogate-Capability: varnish="ESI/1.0"` header
2. **Backend → Varnish**: Responds with `Surrogate-Control: content="ESI/1.0"` to enable ESI processing

### Required VCL Configuration

```vcl
sub vcl_recv {
    # Advertise ESI capability to backend
    set req.http.Surrogate-Capability = {"varnish="ESI/1.0""};
}

sub vcl_backend_response {
    # Enable ESI if backend requests it
    if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi = true;
    }
}
```

## ESI Callback Performance

The ESI callback (`/?cacheability_esi=nonce&...`) uses full WordPress with **optimizations**:

1. **Disable other plugins** during ESI requests
2. **Disable theme loading**
3. **Disable translations**
4. **Disable widgets and WP-Cron**
5. **Exit immediately** after generating nonce

### Performance (Docker)

| Metric | Value |
|--------|-------|
| Average response time | ~15-35ms |
| Typical range | 11-50ms |

## Test Cases

1. **test_esi_callback_performance**: Direct ESI endpoint test
2. **test_esi_processing_mock**: Simple ESI replacement test
3. **test_esi_real_wordpress_page**: Full E2E with real WordPress page
4. **test_esi_nonce_varies_by_request**: Verifies ESI responses aren't cached

## Architecture

```
cacheability/
├── cacheability.php              # Main plugin file
├── includes/
│   ├── pluggable-esi.php         # Overrides wp_nonce_field(), sends Surrogate-Control
│   └── esi-callback.php          # Handles ESI requests with optimizations
├── tests/
│   ├── default.vcl               # Varnish config with Surrogate-Capability
│   ├── nginx.conf                # Nginx config
│   ├── test_esi.py               # pytest tests
│   └── backend_content/          # Test fixtures
├── docker-compose.yml
└── Dockerfile
```
