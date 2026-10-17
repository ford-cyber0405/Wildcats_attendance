
#!/bin/sh
set -e

# Use Railway's assigned port; fall back to 10000 if unset.
PORT="${PORT:-10000}"

# Configure Apache to listen on the assigned port.
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port ${PORT}"
exec apache2-foreground
