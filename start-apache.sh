#!/bin/sh
set -e
# Load Apache environment variables (APACHE_RUN_DIR, APACHE_PID_FILE, etc.),
# which apache2-foreground normally sources before launching apache2.
if [ -f /etc/apache2/envvars ]; then . /etc/apache2/envvars; fi
echo "[start] mods-enabled before guard:"; ls -la /etc/apache2/mods-enabled | grep -i mpm || true
# Remove every MPM from the enabled set, then enable exactly one
for m in mpm_event mpm_worker mpm_prefork; do rm -f /etc/apache2/mods-enabled/$m.load /etc/apache2/mods-enabled/$m.conf; done
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
echo "[start] MPM modules loaded:"; apache2ctl -M 2>&1 | grep -i mpm || true
rm -f /var/run/apache2/apache2.pid
exec apache2 -DFOREGROUND
