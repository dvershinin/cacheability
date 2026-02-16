#!/usr/bin/env bash
set -euo pipefail

wp() {
  docker compose run --rm wpcli --path=/var/www/html "$@"
}

until docker compose exec -T wordpress curl -sSf http://localhost/wp-admin/install.php >/dev/null 2>&1; do
  echo "Waiting for WordPress to be reachable..."
  sleep 3
done

PORT="${TEST_PORT:-8089}"

if ! wp core is-installed --url="http://localhost:${PORT}"; then
  wp core install \
    --url="http://localhost:${PORT}" \
    --title="Cacheability Test" \
    --admin_user=admin \
    --admin_password=admin \
    --admin_email=admin@example.com \
    --skip-email
fi

# Ensure uploads directory is writable
docker compose exec -T wordpress bash -lc 'mkdir -p /var/www/html/wp-content/uploads && chown -R www-data:www-data /var/www/html/wp-content/uploads'

# Set pretty permalinks
wp rewrite structure '/%postname%/' --hard
wp rewrite flush --hard

# Activate our plugin
wp plugin activate cacheability

# Create a sample post for cache header testing
if ! wp post list --post_type=post --format=ids | grep -qE '^[0-9]+'; then
  wp post create --post_title="Test Post" --post_content="Test content" --post_status=publish
fi

# Create a sample category with posts for testing
wp term create category "test-category" --slug=test-category || true

echo "Setup complete. Site at http://localhost:${PORT}"









