#!/bin/bash

set -e

# ============================================================
# CRM Mail Server Setup Script
# Installs and configures: Exim4, Dovecot, SSL (Let's Encrypt)
# Requires: Ubuntu 22.04/24.04, MySQL already configured
# Multi-domain support: all domains read from mail_domains table
#
# Run from admin panel root directory:
#   cd /path/to/admin-panel
#   sudo bash setup_mail_server.sh
# ============================================================

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()    { echo -e "${GREEN}[OK]${NC} $1"; }
warn()   { echo -e "${YELLOW}[WARN]${NC} $1"; }
error()  { echo -e "${RED}[ERROR]${NC} $1"; exit 1; }

# ============================================================
# Check root
# ============================================================

if [ "$EUID" -ne 0 ]; then
    error "Run as root: sudo bash setup_mail_server.sh"
fi

# ============================================================
# Auto-detect project path
# ============================================================

CURRENT_DIR=$(pwd)

if [ -f "$CURRENT_DIR/yii" ] && [ -d "$CURRENT_DIR/common/config" ]; then
    PROJECT_PATH="$CURRENT_DIR"
else
    error "Run this script from the admin panel root directory (where yii file is located)"
fi

PARAMS_FILE="$PROJECT_PATH/common/config/params-local.php"
MAIN_FILE="$PROJECT_PATH/common/config/main-local.php"

[ -f "$PARAMS_FILE" ] || error "params-local.php not found: $PARAMS_FILE"
[ -f "$MAIN_FILE" ]   || error "main-local.php not found: $MAIN_FILE"

# Auto-detect web user
WEB_USER=$(stat -c '%U' "$PROJECT_PATH")
if [ -z "$WEB_USER" ] || [ "$WEB_USER" = "root" ]; then
    WEB_USER="www-data"
    warn "Could not detect web user, using: www-data"
fi

# ============================================================
# Auto-detect DB params from main-local.php (mailDb block)
# ============================================================

MAIL_DB_BLOCK=$(awk "/'mailDb'/,/\].*\]/" "$MAIN_FILE" 2>/dev/null || true)

if [ -z "$MAIL_DB_BLOCK" ]; then
    MAIL_DB_BLOCK=$(awk "/'db'/,/\].*\]/" "$MAIN_FILE" 2>/dev/null || true)
    warn "mailDb block not found, reading from main db block"
fi

DB_HOST=$(echo "$MAIL_DB_BLOCK" | grep -oP "host=\K[^;'\"]+" | head -1)
DB_NAME=$(echo "$MAIL_DB_BLOCK" | grep -oP "dbname=\K[^;'\"]+" | head -1)
DB_USER=$(echo "$MAIL_DB_BLOCK" | grep -oP "'username'\s*=>\s*'\K[^']+" | head -1)
DB_PASS=$(echo "$MAIL_DB_BLOCK" | grep -oP "'password'\s*=>\s*'\K[^']+" | head -1)

[ -z "$DB_HOST" ] && DB_HOST="localhost"
[ -z "$DB_NAME" ] && error "Could not detect DB name from main-local.php"
[ -z "$DB_USER" ] && error "Could not detect DB user from main-local.php"
[ -z "$DB_PASS" ] && error "Could not detect DB password from main-local.php"

# ============================================================
# 1. Collect parameters (only what cannot be auto-detected)
# ============================================================

echo ""
echo "============================================"
echo " CRM Mail Server Setup"
echo "============================================"
echo ""

read -p "Enter mail domain (e.g. example.com): " MAIL_DOMAIN
[[ -z "$MAIL_DOMAIN" ]] && error "Domain cannot be empty"

read -p "Enter email for Let's Encrypt notifications: " LE_EMAIL
[[ -z "$LE_EMAIL" ]] && error "Email cannot be empty"

# Derived values
IMAP_HOST="imap.${MAIL_DOMAIN}"
SMTP_HOST="smtp.${MAIL_DOMAIN}"
MAIL_HOST="mail.${MAIL_DOMAIN}"
VMAIL_USER="vmail"
VMAIL_GROUP="vmail"
VMAIL_UID=5000
VMAIL_GID=5000
MAIL_STORAGE="/var/mail/vhosts"
CERT_PATH="/etc/letsencrypt/live/$IMAP_HOST"

echo ""
echo "============================================"
echo " Configuration summary:"
echo "  Domain:       $MAIL_DOMAIN"
echo "  IMAP host:    $IMAP_HOST"
echo "  SMTP host:    $SMTP_HOST"
echo "  Project:      $PROJECT_PATH"
echo "  Web user:     $WEB_USER"
echo "  DB host:      $DB_HOST"
echo "  DB user:      $DB_USER"
echo "  DB name:      $DB_NAME"
echo "  params-local: $PARAMS_FILE"
echo "  main-local:   $MAIN_FILE"
echo "============================================"
echo ""
read -p "Continue? (y/n): " CONFIRM
[[ "$CONFIRM" != "y" ]] && error "Aborted"

# ============================================================
# 2. Install packages
# ============================================================

log "Updating packages..."
apt-get update -qq

log "Installing Exim4, Dovecot, Certbot..."
DEBIAN_FRONTEND=noninteractive apt-get install -y \
    exim4 \
    exim4-daemon-heavy \
    dovecot-core \
    dovecot-imapd \
    dovecot-mysql \
    certbot \
    openssl \
    mailutils 2>/dev/null || true

# Detect PHP version and install imap extension
PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo "8.1")
log "Installing php${PHP_VER}-imap..."
DEBIAN_FRONTEND=noninteractive apt-get install -y php${PHP_VER}-imap
systemctl restart php${PHP_VER}-fpm 2>/dev/null || true
log "php${PHP_VER}-imap installed"


# ============================================================
# 3. Create vmail user and directories
# ============================================================

log "Creating vmail user..."
if ! id "$VMAIL_USER" &>/dev/null; then
    groupadd -g $VMAIL_GID $VMAIL_GROUP
    useradd -g $VMAIL_GROUP -u $VMAIL_UID -d $MAIL_STORAGE -s /sbin/nologin $VMAIL_USER
fi

mkdir -p "$MAIL_STORAGE/$MAIL_DOMAIN"

log "Creating mail directories for all existing domains..."
ALL_DOMAINS=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" \
    -se "SELECT name FROM mail_domains WHERE is_active=1" 2>/dev/null || true)

if [[ -n "$ALL_DOMAINS" ]]; then
    for domain in $ALL_DOMAINS; do
        mkdir -p "$MAIL_STORAGE/$domain"
        log "  Directory: $MAIL_STORAGE/$domain"
    done
fi

chown -R $VMAIL_USER:$VMAIL_GROUP $MAIL_STORAGE
chmod -R 770 $MAIL_STORAGE

# ============================================================
# 4. Create mail DB tables and fix existing records
# ============================================================

log "Creating mail DB tables..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" <<SQL
CREATE TABLE IF NOT EXISTS mail_domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS mail_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    home VARCHAR(255) NOT NULL DEFAULT '',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at INT UNSIGNED NOT NULL,
    FOREIGN KEY (domain_id) REFERENCES mail_domains(id)
);

INSERT IGNORE INTO mail_domains (name, is_active) VALUES ('$MAIL_DOMAIN', 1);

UPDATE mail_users
SET home = CONCAT('/var/mail/vhosts/',
    SUBSTRING_INDEX(email, '@', -1), '/',
    SUBSTRING_INDEX(email, '@', 1))
WHERE home = '' OR home IS NULL;
SQL

log "Mail DB tables ready, empty home paths fixed"

# ============================================================
# 5. Create SSL certificate
# ============================================================

log "Checking DNS records before requesting certificate..."
for HOST in $IMAP_HOST $SMTP_HOST $MAIL_HOST; do
    RESOLVED=$(dig +short "$HOST" 2>/dev/null | head -1)
    if [ -z "$RESOLVED" ]; then
        warn "DNS record not found for $HOST — certbot may fail"
    else
        log "  $HOST -> $RESOLVED"
    fi
done

if [ -f "$CERT_PATH/fullchain.pem" ]; then
    log "Certificate already exists at $CERT_PATH — skipping certbot"
else
    log "Stopping nginx temporarily for certbot..."
    systemctl stop nginx 2>/dev/null || true

    log "Requesting SSL certificate..."
    certbot certonly --standalone \
        --non-interactive \
        --agree-tos \
        --email "$LE_EMAIL" \
        -d "$IMAP_HOST" \
        -d "$SMTP_HOST" \
        -d "$MAIL_HOST" || error "Certbot failed — check DNS records for $IMAP_HOST, $SMTP_HOST, $MAIL_HOST"

    log "Starting nginx..."
    systemctl start nginx 2>/dev/null || true
fi

log "Setting certificate permissions..."
chown root:Debian-exim "$CERT_PATH/privkey.pem"
chmod 640 "$CERT_PATH/privkey.pem"

# ============================================================
# 6. Configure Dovecot
# ============================================================

log "Configuring Dovecot..."

cat > /etc/dovecot/dovecot.conf <<EOF
protocols = imap
listen = *, ::
!include conf.d/*.conf
EOF

cat > /etc/dovecot/conf.d/10-auth.conf <<EOF
disable_plaintext_auth = no
auth_mechanisms = plain login
!include auth-sql.conf.ext
EOF

cat > /etc/dovecot/conf.d/auth-sql.conf.ext <<EOF
passdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}

userdb {
  driver = sql
  args = /etc/dovecot/dovecot-sql.conf.ext
}
EOF

cat > /etc/dovecot/dovecot-sql.conf.ext <<EOF
driver = mysql
connect = host=$DB_HOST dbname=$DB_NAME user=$DB_USER password=$DB_PASS
default_pass_scheme = SHA512-CRYPT
password_query = SELECT email AS user, password FROM mail_users WHERE email = '%u' AND is_active = 1
user_query = SELECT CONCAT('$MAIL_STORAGE/', mail_domains.name, '/', SUBSTRING_INDEX(mail_users.email, '@', 1)) AS home, $VMAIL_UID AS uid, $VMAIL_GID AS gid FROM mail_users JOIN mail_domains ON mail_domains.id = mail_users.domain_id WHERE mail_users.email = '%u' AND mail_users.is_active = 1
iterate_query = SELECT email AS user FROM mail_users WHERE is_active = 1
EOF

chmod 640 /etc/dovecot/dovecot-sql.conf.ext
chown root:dovecot /etc/dovecot/dovecot-sql.conf.ext

cat > /etc/dovecot/conf.d/10-mail.conf <<EOF
mail_location = maildir:$MAIL_STORAGE/%d/%n
mail_privileged_group = $VMAIL_GROUP

namespace inbox {
  inbox = yes
}
EOF

cat > /etc/dovecot/conf.d/10-ssl.conf <<EOF
ssl = required
ssl_cert = <$CERT_PATH/fullchain.pem
ssl_key = <$CERT_PATH/privkey.pem
EOF

cat > /etc/dovecot/conf.d/20-lmtp.conf <<EOF
protocol lmtp {
  postmaster_address = postmaster@$MAIL_DOMAIN
}
EOF

cat > /etc/dovecot/conf.d/10-master.conf <<EOF
service imap-login {
  inet_listener imap {
    port = 143
  }
  inet_listener imaps {
    port = 993
    ssl = yes
  }
}

service lmtp {
  unix_listener /var/spool/exim4/dovecot-lmtp {
    mode = 0600
    user = Debian-exim
    group = Debian-exim
  }
}

service auth {
  unix_listener /var/spool/exim4/dovecot-auth {
    mode = 0660
    user = Debian-exim
    group = Debian-exim
  }
  unix_listener auth-userdb {
    mode = 0660
    user = $VMAIL_USER
    group = $VMAIL_GROUP
  }
}
EOF

log "Restarting Dovecot..."
systemctl enable dovecot
systemctl restart dovecot

# ============================================================
# 7. Create mailbox-manager.sh
# ============================================================

log "Creating mailbox-manager.sh..."
cat > /usr/local/bin/mailbox-manager.sh <<'SCRIPT'
#!/bin/bash
set -e

ACTION=$1
EMAIL=$2
HASH=$3
PASSWD_FILE="/etc/exim4/passwd"

if [[ -z "$ACTION" || -z "$EMAIL" ]]; then
    echo "Usage: $0 create|update|delete email [hash]"
    exit 1
fi

if [[ ! "$EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo "ERROR: Invalid email format"
    exit 1
fi

LOCALPART=$(echo "$EMAIL" | cut -d@ -f1)
DOMAIN=$(echo "$EMAIL" | cut -d@ -f2)
HOME_PATH="/var/mail/vhosts/$DOMAIN/$LOCALPART"

case "$ACTION" in
    create)
        [[ -z "$HASH" ]] && { echo "Hash required for create"; exit 1; }
        grep -v "^${EMAIL}:" "$PASSWD_FILE" > "${PASSWD_FILE}.tmp" 2>/dev/null || true
        echo "${EMAIL}:${HASH}:5000:5000:${HOME_PATH}:0::" >> "${PASSWD_FILE}.tmp"
        mv "${PASSWD_FILE}.tmp" "$PASSWD_FILE"
        mkdir -p "$HOME_PATH"
        chown -R vmail:vmail "$HOME_PATH"
        chmod 700 "$HOME_PATH"
        ;;
    update)
        [[ -z "$HASH" ]] && { echo "Hash required for update"; exit 1; }
        grep -v "^${EMAIL}:" "$PASSWD_FILE" > "${PASSWD_FILE}.tmp" 2>/dev/null || true
        echo "${EMAIL}:${HASH}:5000:5000:${HOME_PATH}:0::" >> "${PASSWD_FILE}.tmp"
        mv "${PASSWD_FILE}.tmp" "$PASSWD_FILE"
        ;;
    delete)
        grep -v "^${EMAIL}:" "$PASSWD_FILE" > "${PASSWD_FILE}.tmp" 2>/dev/null || true
        mv "${PASSWD_FILE}.tmp" "$PASSWD_FILE"
        ;;
    *)
        echo "Unknown action: $ACTION"
        exit 1
        ;;
esac

chown Debian-exim:Debian-exim "$PASSWD_FILE"
chmod 640 "$PASSWD_FILE"
echo "OK: $ACTION $EMAIL"
SCRIPT

chmod +x /usr/local/bin/mailbox-manager.sh

# ============================================================
# 8. Configure sudoers
# ============================================================

log "Configuring sudoers..."
cat > /etc/sudoers.d/mailbox-manager <<EOF
www-data ALL=(root) NOPASSWD: /usr/local/bin/mailbox-manager.sh
$WEB_USER ALL=(root) NOPASSWD: /usr/local/bin/mailbox-manager.sh
EOF
chmod 440 /etc/sudoers.d/mailbox-manager

# ============================================================
# 9. Configure Exim4
# ============================================================

log "Configuring Exim4..."

touch /etc/exim4/passwd
chown Debian-exim:Debian-exim /etc/exim4/passwd
chmod 640 /etc/exim4/passwd

cat > /etc/exim4/certs_map <<EOF
$SMTP_HOST:$CERT_PATH/fullchain.pem:$CERT_PATH/privkey.pem
$MAIL_HOST:$CERT_PATH/fullchain.pem:$CERT_PATH/privkey.pem
$MAIL_DOMAIN:$CERT_PATH/fullchain.pem:$CERT_PATH/privkey.pem
EOF

cat > /etc/exim4/conf.d/custom.conf <<EOF
hide mysql_servers = $DB_HOST/$DB_NAME/$DB_USER/$DB_PASS
EOF
chmod 640 /etc/exim4/conf.d/custom.conf
chown root:Debian-exim /etc/exim4/conf.d/custom.conf

# Switch Exim to single-file config mode so exim4.conf.template is used
sed -i "s/^CONFTYPE=.*/CONFTYPE=single/" /etc/default/exim4 2>/dev/null || true
if ! grep -q "^CONFTYPE=" /etc/default/exim4 2>/dev/null; then
    echo "CONFTYPE=single" >> /etc/default/exim4
fi

cat > /etc/exim4/exim4.conf.template <<EOF
primary_hostname = $MAIL_DOMAIN

# Multi-domain: read all active domains from MySQL
domainlist local_domains = \${lookup mysql{SELECT GROUP_CONCAT(name SEPARATOR ':') FROM mail_domains WHERE is_active=1}{\$value}{$MAIL_DOMAIN}}
domainlist relay_to_domains =
hostlist relay_from_hosts = localhost

acl_smtp_rcpt = acl_check_rcpt
acl_smtp_data = acl_check_data

tls_certificate = \${if exists{/etc/exim4/certs_map}\\
  {\${extract{1}{:}{\${lookup{\$tls_sni}lsearch{/etc/exim4/certs_map}{\$value}{\${lookup{\${sg{\$tls_sni}{^[^.]+\\.}{*.}}lsearch{/etc/exim4/certs_map}{\$value}{/etc/exim4/ssl/exim.crt:/etc/exim4/ssl/exim.key}}}}}}}}}\\
  {/etc/exim4/ssl/exim.crt}}

tls_privatekey = \${if exists{/etc/exim4/certs_map}\\
  {\${extract{2}{:}{\${lookup{\$tls_sni}lsearch{/etc/exim4/certs_map}{\$value}{\${lookup{\${sg{\$tls_sni}{^[^.]+\\.}{*.}}lsearch{/etc/exim4/certs_map}{\$value}{/etc/exim4/ssl/exim.crt:/etc/exim4/ssl/exim.key}}}}}}}}}\\
  {/etc/exim4/ssl/exim.key}}

daemon_smtp_ports = 25 : 465 : 587
tls_on_connect_ports = 465

smtp_banner = \$primary_hostname ESMTP Exim \$version_number \$tod_full

begin acl

acl_check_rcpt:
    accept
        hosts = :
    deny
        domains = !+local_domains
        message = relay not permitted
    accept
        domains = +local_domains

acl_check_data:
    accept

begin routers

# Multi-domain: check recipient exists in MySQL
local_users:
    driver = accept
    domains = +local_domains
    condition = \${lookup mysql{SELECT COUNT(*) FROM mail_users WHERE email='\${quote_mysql:\$local_part@\$domain}' AND is_active=1}{\${if eq{\$value}{0}{no}{yes}}}{no}}
    transport = dovecot_lmtp

dnslookup:
    driver = dnslookup
    domains = !+local_domains
    transport = remote_smtp
    ignore_target_hosts = 0.0.0.0 : 127.0.0.0/8
    no_more

begin transports

dovecot_lmtp:
    driver = lmtp
    socket = /var/spool/exim4/dovecot-lmtp
    batch_max = 200

remote_smtp:
    driver = smtp
    message_size_limit = 0
    tls_certificate = $CERT_PATH/fullchain.pem
    tls_privatekey = $CERT_PATH/privkey.pem

begin retry
*   *   F,2h,15m; G,16h,1h,1.5; F,4d,6h

begin rewrite

begin authenticators

plain_server:
    driver = plaintext
    server_set_id = \$auth2
    server_prompts = :
    server_condition = \${if and {{!eq{\$auth2}{}}{!eq{\$auth3}{}}{crypteq{\$auth3}{\${lookup{\$auth2}lsearch{/etc/exim4/passwd}{\${extract{1}{:}{\$value}}}{*}}}}} {yes}{no}}
    server_advertise_condition = \${if def:tls_in_cipher}

login_server:
    driver = plaintext
    server_set_id = \$auth1
    server_prompts = Username:: : Password::
    server_condition = \${if and {{!eq{\$auth1}{}}{!eq{\$auth2}{}}{crypteq{\$auth2}{\${lookup{\$auth1}lsearch{/etc/exim4/passwd}{\${extract{1}{:}{\$value}}}{*}}}}} {yes}{no}}
    server_advertise_condition = \${if def:tls_in_cipher}
EOF

log "Updating Exim config..."
update-exim4.conf
systemctl enable exim4
systemctl restart exim4
# ============================================================
# 10. Update params-local.php
# ============================================================

if [[ -f "$PARAMS_FILE" ]]; then
    log "Checking emailEncryptionKey in params-local.php..."

    if grep -q "'emailEncryptionKey'" "$PARAMS_FILE"; then
        EXISTING_KEY=$(grep "'emailEncryptionKey'" "$PARAMS_FILE" | grep -oP "=> '\K[^']+" | head -1)
        if [[ -z "$EXISTING_KEY" ]]; then
            NEW_KEY=$(openssl rand -base64 32)
            sed -i "s|'emailEncryptionKey' => ''|'emailEncryptionKey' => '$NEW_KEY'|" "$PARAMS_FILE"
            log "emailEncryptionKey was empty — generated and inserted"
        else
            log "emailEncryptionKey already set — not changing"
        fi
    else
        NEW_KEY=$(openssl rand -base64 32)
        sed -i "s|];|    'emailEncryptionKey' => '$NEW_KEY',\n];|" "$PARAMS_FILE"
        log "emailEncryptionKey not found — added"
    fi

    for PARAM in \
        "corporateEmailDomain|$MAIL_DOMAIN" \
        "corporateImapHost|$IMAP_HOST" \
        "corporateImapPort|993" \
        "corporateImapEncryption|ssl" \
        "corporateSmtpHost|$SMTP_HOST" \
        "corporateSmtpPort|587" \
        "corporateSmtpEncryption|tls"
    do
        KEY=$(echo $PARAM | cut -d'|' -f1)
        VAL=$(echo $PARAM | cut -d'|' -f2)
        if grep -q "'$KEY'" "$PARAMS_FILE"; then
            sed -i "s|'$KEY' => '[^']*'|'$KEY' => '$VAL'|" "$PARAMS_FILE"
            log "Updated $KEY => $VAL"
        else
            sed -i "s|];|    '$KEY' => '$VAL',\n];|" "$PARAMS_FILE"
            log "Added $KEY => $VAL"
        fi
    done
fi

# ============================================================
# 11. Update main-local.php (mailDb connection)
# ============================================================

if [[ -f "$MAIN_FILE" ]]; then
    log "Checking mailDb in main-local.php..."

    if grep -q "'mailDb'" "$MAIN_FILE"; then
        log "mailDb already configured — not changing"
    else
        MAIL_DB_SNIPPET="        'mailDb' => [\n            'class' => \\\\yii\\\\db\\\\Connection::class,\n            'dsn' => 'mysql:host=$DB_HOST;dbname=$DB_NAME',\n            'username' => '$DB_USER',\n            'password' => '$DB_PASS',\n            'charset' => 'utf8mb4',\n            'attributes' => [\n                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4;',\n            ],\n        ],"
        sed -i "s|'components' => \[|'components' => [\n$MAIL_DB_SNIPPET|" "$MAIN_FILE"
        log "mailDb connection added to main-local.php"
    fi
fi

# ============================================================
# Done
# ============================================================

echo ""
log "Setup complete!"
echo ""
echo "What was configured:"
echo "  - Exim4: multi-domain support, reads domains from MySQL"
echo "  - Dovecot: IMAP with MySQL auth, SSL"
echo "  - SSL certificate: $IMAP_HOST, $SMTP_HOST, $MAIL_HOST"
echo "  - mailbox-manager.sh: created and secured"
echo "  - mail_users.home: empty paths fixed"
echo "  - params-local.php: mail settings updated"
echo "  - php-imap extension: installed"
echo ""
echo "  1. Check DNS records for $MAIL_DOMAIN:"
echo "     Option A — wildcard (covers all subdomains automatically):"
echo "     *  IN  A  YOUR_SERVER_IP"
echo ""
echo "     Option B — individual records:"
echo "     imap.$MAIL_DOMAIN  IN  A  YOUR_SERVER_IP"
echo "     smtp.$MAIL_DOMAIN  IN  A  YOUR_SERVER_IP"
echo "     mail.$MAIL_DOMAIN  IN  A  YOUR_SERVER_IP"
echo ""
echo "     If wildcard (* IN A ...) already exists — skip this step."
echo ""
echo "  2. Run: php yii email-sync/provision-missing"
echo "  3. Run: php yii email-sync/fix-empty-passwords"
echo "  4. Test: php yii email-sync/corporate"
echo ""
echo "  5. For each domain configure DKIM:"
echo "     sudo bash setup_dkim.sh"
echo "     Add DNS records: SPF, DKIM, DMARC"
echo ""
echo "When adding new company with new domain:"
echo "  - mail_domains table updated automatically by CompanyDeployController"
echo "  - Run setup_dkim.sh for the new domain"
echo "  - Add SPF, DKIM, DMARC DNS records for the new domain"
echo ""