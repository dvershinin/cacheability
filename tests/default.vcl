vcl 4.1;

backend default {
    .host = "nginx";
    .port = "80";
}

sub vcl_recv {
    # Advertise ESI capability to the backend (W3C Edge Architecture standard)
    set req.http.Surrogate-Capability = {"varnish="ESI/1.0""};
    
    # Pass ESI sub-requests directly to backend (don't cache nonces)
    if (req.url ~ "[?&]cacheability_esi=") {
        return (pass);
    }
}

sub vcl_backend_response {
    # Enable ESI if backend requests it via Surrogate-Control header
    if (beresp.http.Surrogate-Control ~ "ESI/1.0") {
        unset beresp.http.Surrogate-Control;
        set beresp.do_esi = true;
    }
}
