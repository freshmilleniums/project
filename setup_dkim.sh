#!/bin/bash
set -e

# Check root
if [ "$EUID" -ne 0 ]; then
    echo "ERROR: Run as root"
    exit 1
fi

# Ask domain
read -p "Enter domain (e.g. example.com): " DOMAIN
if [ -z "$DOMAIN" ]; then
    echo "ERROR: Domain is required"
    exit 1
fi

echo ""
echo "=== Setting up DKIM for ${DOMAIN} ==="

# Check opendkim-tools for key generation (only tools, not daemon)
if ! command -v opendkim-genkey &> /dev/null; then
    echo "Installing opendkim-tools for key generation..."
    apt install -y opendkim-tools
    echo "✓ opendkim-tools installed"
else
    echo "✓ opendkim-tools already available"
fi

# DKIM key directory (Exim reads from here)
KEYDIR="/etc/exim4/dkim"
mkdir -p "$KEYDIR"

PRIVATE_KEY="${KEYDIR}/${DOMAIN}.private"
PUBLIC_KEY="${KEYDIR}/${DOMAIN}.key"

# Generate DKIM key if not exists
if [ ! -f "$PRIVATE_KEY" ]; then
    echo "Generating DKIM key..."

    cd /tmp
    opendkim-genkey -t -s dkim -d "$DOMAIN" -b 2048
    mv /tmp/dkim.private "$PRIVATE_KEY"
    mv /tmp/dkim.txt "${KEYDIR}/${DOMAIN}.txt"

    chown root:Debian-exim "$PRIVATE_KEY"
    chmod 640 "$PRIVATE_KEY"

    echo "✓ DKIM key generated: $PRIVATE_KEY"
else
    echo "✓ DKIM key already exists, skipping generation"
fi

# Update dkim_selector file
# Format: domain:selector (Exim reads this to find the right key)
SELECTOR_FILE="/etc/exim4/dkim_selector"
touch "$SELECTOR_FILE"

if ! grep -q "^${DOMAIN}:" "$SELECTOR_FILE"; then
    echo "${DOMAIN}:dkim" >> "$SELECTOR_FILE"
    echo "✓ dkim_selector updated"
else
    echo "✓ dkim_selector already has entry for ${DOMAIN}"
fi

# Restart Exim to pick up new key
systemctl restart exim4
echo "✓ Exim4 restarted"

# Get server IP
SERVER_IP=$(hostname -I | awk '{print $1}')

# Extract public key value for DNS
DKIM_TXT_FILE="${KEYDIR}/${DOMAIN}.txt"
if [ -f "$DKIM_TXT_FILE" ]; then
    DKIM_VALUE=$(cat "$DKIM_TXT_FILE" | grep -o '".*"' | tr -d '\n' | tr -d '"')
else
    # Fallback: extract from private key
    DKIM_VALUE=$(openssl rsa -in "$PRIVATE_KEY" -pubout 2>/dev/null | grep -v "PUBLIC KEY" | tr -d '\n')
fi

echo ""
echo "========================================"
echo "✓ DKIM setup complete for ${DOMAIN}"
echo "========================================"
echo ""
echo "Add the following records to your DNS panel:"
echo ""
echo "1. DKIM:"
echo "   Name:  dkim._domainkey"
echo "   Type:  TXT"
echo "   Value: v=DKIM1; k=rsa; p=${DKIM_VALUE}"
echo ""
echo "2. SPF (if not exists):"
echo "   Name:  @"
echo "   Type:  TXT"
echo "   Value: v=spf1 ip4:${SERVER_IP} ~all"
echo ""
echo "3. DMARC (if not exists):"
echo "   Name:  _dmarc"
echo "   Type:  TXT"
echo "   Value: v=DMARC1; p=quarantine; rua=mailto:admin@${DOMAIN}"
echo ""
echo "After adding DNS records, wait 5-10 min then verify:"
echo "  dig TXT dkim._domainkey.${DOMAIN}"
echo "  exim4 -bV | grep DKIM"
echo ""
echo "Test outgoing DKIM signing:"
echo "  echo 'Test' | mail -s 'DKIM test' test@gmail.com"
echo "  (check headers in received mail for DKIM-Signature)"
echo ""