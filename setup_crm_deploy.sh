#!/bin/bash
# Enhanced secure CRM deployment setup with SSL support

# Auto-detect current project path
CURRENT_DIR=$(pwd)
PROJECT_PATH=""

# Check if we're in admin CRM directory
if [ -f "$CURRENT_DIR/yii" ] && [ -d "$CURRENT_DIR/console" ]; then
    PROJECT_PATH="$CURRENT_DIR"
    echo "✓ Auto-detected project path: $PROJECT_PATH"
elif [ -f "$CURRENT_DIR/../yii" ] && [ -d "$CURRENT_DIR/../console" ]; then
    PROJECT_PATH="$(realpath $CURRENT_DIR/..)"
    echo "✓ Auto-detected project path: $PROJECT_PATH"
else
    echo "✗ Cannot detect project path. Please run from admin directory."
    exit 1
fi

# Validate project path
if [ ! -f "$PROJECT_PATH/yii" ]; then
    echo "✗ ERROR: Yii console not found at $PROJECT_PATH/yii"
    exit 1
fi

echo "Using project path: $PROJECT_PATH"

# Create ENHANCED root wrapper
sudo tee /usr/local/bin/crm-deploy-root << EOF
#!/bin/bash

# Enhanced wrapper for CRM deployment
# Usage: crm-deploy-root <company_id> [log_id] [action] [user_id]

# Check arguments (now supports up to 4 arguments)
if [ \$# -lt 1 ] || [ \$# -gt 4 ]; then
    echo "Usage: \$0 <company_id> [log_id] [action] [user_id]"
    echo "Actions: start, stop, delete, update-config"
    exit 1
fi

COMPANY_ID="\$1"
LOG_ID="\$2"
ACTION="\$3"
USER_ID="\$4"

# Validate company_id is numeric and in range
if ! [[ "\$COMPANY_ID" =~ ^[0-9]+\$ ]]; then
    echo "Error: company_id must be a number"
    exit 1
fi

# Extended range validation (1-10000000)
if [ "\$COMPANY_ID" -lt 1 ] || [ "\$COMPANY_ID" -gt 10000000 ]; then
    echo "Error: company_id must be between 1 and 10000000"
    exit 1
fi

# Validate user_id if provided (must be numeric)
if [ -n "\$USER_ID" ] && ! [[ "\$USER_ID" =~ ^[0-9]+\$ ]]; then
    echo "Error: user_id must be a number"
    exit 1
fi

# Project path (auto-detected)
PROJECT_PATH="$PROJECT_PATH"

# Verify project exists
if [ ! -f "\$PROJECT_PATH/yii" ]; then
    echo "Error: Yii console not found at \$PROJECT_PATH/yii"
    exit 1
fi

# Create deployment directories with proper permissions
echo "Creating deployment directories..."
mkdir -p /var/www/sites
chown www-data:www-data /var/www/sites
chmod 755 /var/www/sites

# Function to disable nginx configs (STOP)
disable_nginx_configs() {
    local company_id="\$1"
    cd "\$PROJECT_PATH"
    /usr/bin/php yii company-deploy/stop-services "\$company_id"
}

# Function to enable nginx configs (START)
enable_nginx_configs() {
    local company_id="\$1"
    cd "\$PROJECT_PATH"
    /usr/bin/php yii company-deploy/start-services "\$company_id"
}

# Function for COMPLETE COMPANY DELETION
delete_company_complete() {
    local company_id="\$1"

    echo "=== WARNING: COMPLETE COMPANY DELETION ==="
    echo "This will permanently remove:"
    echo "- All CRM files"
    echo "- All databases"
    echo "- All nginx configurations"
    echo "- All database records"
    echo "- Company and admin user"
    echo ""
    echo "Company ID: \$company_id"
    echo "Starting deletion in 3 seconds..."
    sleep 3

    cd "\$PROJECT_PATH"
    /usr/bin/php yii company-deploy/delete "\$company_id"
}

# Function to update configuration files after domain change
update_company_config() {
    local company_id="\$1"
    local log_id="\$2"
    local user_id="\$3"

    echo "=== Configuration Update ==="
    echo "Company ID: \$company_id"
    echo "Log ID: \$log_id"
    echo "User ID: \$user_id"
    echo ""

    cd "\$PROJECT_PATH"

    if [ -n "\$log_id" ] && [ -n "\$user_id" ]; then
        /usr/bin/php yii company-deploy/update-config "\$company_id" "\$log_id" "\$user_id"
    elif [ -n "\$log_id" ]; then
        /usr/bin/php yii company-deploy/update-config "\$company_id" "\$log_id"
    else
        /usr/bin/php yii company-deploy/update-config "\$company_id"
    fi
}

# Change to project directory
cd "\$PROJECT_PATH"

# Determine which operation to perform
case "\$ACTION" in
    "update-config")
        # Update configuration files after domain change
        update_company_config "\$COMPANY_ID" "\$LOG_ID" "\$USER_ID"
        if [ \$? -eq 0 ]; then
            echo "Company \$COMPANY_ID configuration updated successfully"
        else
            echo "Error updating configuration for company \$COMPANY_ID"
            exit 1
        fi
        ;;
    "stop")
        # Stop company services (disable nginx configs)
        disable_nginx_configs "\$COMPANY_ID"
        if [ \$? -eq 0 ]; then
            echo "Company \$COMPANY_ID services stopped successfully"
        else
            echo "Error stopping services for company \$COMPANY_ID"
            exit 1
        fi
        ;;
    "start")
        # Start company services (enable nginx configs)
        enable_nginx_configs "\$COMPANY_ID"
        if [ \$? -eq 0 ]; then
            echo "Company \$COMPANY_ID services started successfully"
        else
            echo "Error starting services for company \$COMPANY_ID"
            exit 1
        fi
        ;;
    "delete")
        # COMPLETE COMPANY DELETION - IRREVERSIBLE!
        delete_company_complete "\$COMPANY_ID"
        ;;
    *)
        # CRM-only deployment (default)
        if [ -n "\$LOG_ID" ]; then
            exec /usr/bin/php yii company-deploy/bootstrap-crm "\$COMPANY_ID" "\$LOG_ID"
        else
            exec /usr/bin/php yii company-deploy/bootstrap-crm "\$COMPANY_ID"
        fi
        ;;
esac
EOF

# Set secure permissions
sudo chmod 755 /usr/local/bin/crm-deploy-root
sudo chown root:root /usr/local/bin/crm-deploy-root

echo "✓ Updated deployment wrapper with UPDATE-CONFIG support"

# Create mailbox manager script
sudo tee /usr/local/bin/mailbox-manager.sh << 'MAILEOF'
#!/bin/bash
set -e
ACTION=$1
EMAIL=$2
HASH=$3
PASSWD_FILE="/etc/exim4/passwd"
case "$ACTION" in
    create|update|delete) ;;
    *) echo "ERROR: Unknown action: $ACTION"; exit 1 ;;
esac
if [[ ! "$EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo "ERROR: Invalid email format"; exit 1
fi
LOCAL_PART="${EMAIL%%@*}"
DOMAIN="${EMAIL##*@}"
HOME_DIR="/var/mail/vhosts/${DOMAIN}/${LOCAL_PART}"
case "$ACTION" in
    create|update)
        if [ -z "$HASH" ]; then echo "ERROR: Hash required"; exit 1; fi
        grep -v "^${EMAIL}:" "$PASSWD_FILE" > "${PASSWD_FILE}.tmp" 2>/dev/null || true
        echo "${EMAIL}:${HASH}:5000:5000:${HOME_DIR}:0::" >> "${PASSWD_FILE}.tmp"
        mv "${PASSWD_FILE}.tmp" "$PASSWD_FILE"
        chown Debian-exim:Debian-exim "$PASSWD_FILE"
        chmod 640 "$PASSWD_FILE"
        echo "OK: Mailbox ${ACTION}d: ${EMAIL}"
        ;;
    delete)
        grep -v "^${EMAIL}:" "$PASSWD_FILE" > "${PASSWD_FILE}.tmp" 2>/dev/null || true
        mv "${PASSWD_FILE}.tmp" "$PASSWD_FILE"
        chown Debian-exim:Debian-exim "$PASSWD_FILE"
        chmod 640 "$PASSWD_FILE"
        echo "OK: Mailbox deleted: ${EMAIL}"
        ;;
esac
exit 0
MAILEOF
sudo chmod +x /usr/local/bin/mailbox-manager.sh
sudo chown root:root /usr/local/bin/mailbox-manager.sh
echo "✓ mailbox-manager.sh created and secured"

# Configure ENHANCED sudo access with DELETE and SSL permissions
sudo tee /etc/sudoers.d/crm-deployment << 'EOF'
# Enhanced sudo for CRM deployment with Start/Stop/DELETE/Update-Config/SSL - both web users
www-data ALL=(root) NOPASSWD: /usr/local/bin/crm-deploy-root
crm_delivery_usr ALL=(root) NOPASSWD: /usr/local/bin/crm-deploy-root

# Mailbox management for crm-employers
www-data ALL=(root) NOPASSWD: /usr/local/bin/mailbox-manager.sh
crm_delivery_usr ALL=(root) NOPASSWD: /usr/local/bin/mailbox-manager.sh

# Additional permissions for nginx management
www-data ALL=(root) NOPASSWD: /bin/systemctl reload nginx
www-data ALL=(root) NOPASSWD: /bin/systemctl stop nginx
www-data ALL=(root) NOPASSWD: /bin/systemctl start nginx
crm_delivery_usr ALL=(root) NOPASSWD: /bin/systemctl reload nginx
crm_delivery_usr ALL=(root) NOPASSWD: /bin/systemctl stop nginx
crm_delivery_usr ALL=(root) NOPASSWD: /bin/systemctl start nginx

# Additional permissions for MySQL operations (DELETE functionality)
www-data ALL=(root) NOPASSWD: /usr/bin/mysql
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/mysql

# Additional permissions for moving directories (UPDATE-CONFIG functionality)
www-data ALL=(root) NOPASSWD: /bin/mv
crm_delivery_usr ALL=(root) NOPASSWD: /bin/mv

# SSL/Certbot permissions
www-data ALL=(root) NOPASSWD: /usr/bin/certbot
www-data ALL=(root) NOPASSWD: /usr/bin/certbot *
www-data ALL=(root) NOPASSWD: /snap/bin/certbot
www-data ALL=(root) NOPASSWD: /snap/bin/certbot *
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/certbot
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/certbot *
crm_delivery_usr ALL=(root) NOPASSWD: /snap/bin/certbot
crm_delivery_usr ALL=(root) NOPASSWD: /snap/bin/certbot *

# OpenSSL for certificate expiry checks
www-data ALL=(root) NOPASSWD: /usr/bin/openssl
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/openssl

# APT/Snap for certbot installation if missing
www-data ALL=(root) NOPASSWD: /usr/bin/apt-get update
www-data ALL=(root) NOPASSWD: /usr/bin/apt-get install -y certbot
www-data ALL=(root) NOPASSWD: /usr/bin/snap install certbot --classic
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/apt-get update
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/apt-get install -y certbot
crm_delivery_usr ALL=(root) NOPASSWD: /usr/bin/snap install certbot --classic
EOF

# Set correct permissions for sudoers file
sudo chmod 440 /etc/sudoers.d/crm-deployment

echo "✓ Updated sudo access with UPDATE-CONFIG and SSL permissions"

# Validate sudoers syntax
sudo visudo -c
if [ $? -ne 0 ]; then
    echo "✗ ERROR: Invalid sudoers syntax!"
    sudo rm /etc/sudoers.d/crm-deployment
    exit 1
fi

echo "✓ Sudoers syntax validation passed"

# Test the ENHANCED setup
echo ""
echo "Testing enhanced deployment wrapper..."

echo "Testing UPDATE-CONFIG operation as www-data:"
sudo -u www-data sudo -n /usr/local/bin/crm-deploy-root 9999999 "" update-config 1 2>&1 | head -3

echo ""
echo "=== SETUP COMPLETE ==="
echo ""
echo "✓ Both www-data and crm_delivery_usr can now run:"
echo "  - CRM Deploy:      sudo /usr/local/bin/crm-deploy-root <company_id> [log_id]"
echo "  - Update Config:   sudo /usr/local/bin/crm-deploy-root <company_id> [log_id] update-config [user_id]"
echo "  - Stop Services:   sudo /usr/local/bin/crm-deploy-root <company_id> '' stop"
echo "  - Start Services:  sudo /usr/local/bin/crm-deploy-root <company_id> '' start"
echo "  - DELETE Company:  sudo /usr/local/bin/crm-deploy-root <company_id> '' delete"
echo ""
echo " UPDATE-CONFIG OPERATION:"
echo "   - Updates nginx configuration with new domain"
echo "   - Renames deployment directory if domain changed"
echo "   - Updates CRM config files (params-local.php)"
echo "   - Reloads nginx to apply changes"
echo "   - Clears need_config_update flag on success"
echo "   - Preserves flag on failure for retry"
echo ""
echo "⚠  DELETE OPERATION WARNING:"
echo "   - Completely removes ALL company data"
echo "   - Removes CRM directories"
echo "   - Drops company databases"
echo "   - Removes nginx configurations"
echo "   - Deletes all database records (packages, tasks, users, chats, etc.)"
echo "   - Removes company record and admin user"
echo "   - THIS OPERATION IS IRREVERSIBLE!"
echo ""
echo " SSL CERTIFICATES (automatic during deploy):"
echo "   - Certbot auto-installed if missing"
echo "   - HTTP config created first (for ACME validation)"
echo "   - Certificate obtained via webroot or standalone"
echo "   - HTTPS config applied after certificate obtained"
echo "   - Existing valid certificates reused (>30 days)"
echo "   - Auto-renewal via cron (twice daily)"
echo "   - Falls back to HTTP if SSL fails"
echo "   - Old certificate removed on domain change"
echo ""
echo "✓ Company ID range: 1-10000000"
echo "✓ User ID validation for audit logging"
echo "✓ Enhanced permissions for directory operations"
echo "✓ All operations include comprehensive logging"