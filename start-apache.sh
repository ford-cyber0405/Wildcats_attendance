
#!/bin/sh
set -e

# Load Apache environment variables
if [ -f /etc/apache2/envvars ]; then
    . /etc/apache2/envvars
fi

# Use Railway's assigned port
PORT="${PORT:-10000}"

# Configure Apache's listening port and virtual host
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Ensure only the prefork MPM is enabled
for m in mpm_event mpm_worker mpm_prefork; do
    rm -f "/etc/apache2/mods-enabled/$m.load"
    rm -f "/etc/apache2/mods-enabled/$m.conf"
done

ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf

echo "[start] Apache port: ${PORT}"
echo "[start] Loaded MPM modules:"
apache2ctl -M 2>&1 | grep -i mpm || true

# Verify configuration before starting
apache2ctl configtest

exec apache2 -DFOREGROUND
