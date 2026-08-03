#!/bin/bash
# =============================================================================
# setup_cron.sh — Global CRM cron jobs setup
#
# Run as root from the admin panel root directory:
#   cd /path/to/your/admin-panel
#   sudo bash setup_cron.sh
#
# What this script does:
#   1. Uses current directory as admin panel root
#   2. Detects the correct PHP CLI binary
#   3. Verifies yii console commands exist
#   4. Adds cron jobs to root crontab
#   5. Sets up log rotation
# =============================================================================

set -e

# -----------------------------------------------------------------------------
# Colors
# -----------------------------------------------------------------------------
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

info()    { echo -e "${BLUE}[INFO]${NC}  $1"; }
success() { echo -e "${GREEN}[OK]${NC}    $1"; }
warning() { echo -e "${YELLOW}[WARN]${NC}  $1"; }
error()   { echo -e "${RED}[ERROR]${NC} $1"; }
die()     { error "$1"; exit 1; }

# -----------------------------------------------------------------------------
# Must run as root
# -----------------------------------------------------------------------------
if [ "$EUID" -ne 0 ]; then
    die "This script must be run as root: sudo bash setup_cron.sh"
fi

echo ""
echo "=============================================="
echo "  CRM Global Cron Setup"
echo "=============================================="
echo ""

# -----------------------------------------------------------------------------
# STEP 1: Use current directory as admin panel root
# -----------------------------------------------------------------------------
info "Checking admin panel in current directory..."

ADMIN_ROOT="$(pwd)"
ADMIN_YII_PATH="$ADMIN_ROOT/yii"

if [ ! -f "$ADMIN_YII_PATH" ]; then
    die "yii file not found in current directory: $ADMIN_ROOT
Please run this script from the admin panel root directory:
  cd /path/to/your/admin-panel
  sudo bash setup_cron.sh"
fi

success "Admin panel root: $ADMIN_ROOT"

# -----------------------------------------------------------------------------
# STEP 2: Detect PHP CLI binary
# -----------------------------------------------------------------------------
info "Detecting PHP CLI binary..."

PHP_BIN=""

PHP_CANDIDATES=(
    /usr/bin/php8.2
    /usr/bin/php8.1
    /usr/bin/php8.0
    /usr/bin/php
    /usr/local/bin/php8.2
    /usr/local/bin/php8.1
    /usr/local/bin/php
    /opt/php/8.2/bin/php
    /opt/php/8.1/bin/php
)

for candidate in "${PHP_CANDIDATES[@]}"; do
    if [ -x "$candidate" ]; then
        version_output=$("$candidate" -v 2>/dev/null || true)
        if echo "$version_output" | grep -q "cli"; then
            PHP_BIN="$candidate"
            php_version=$("$candidate" -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null)
            success "Found PHP CLI: $PHP_BIN (PHP $php_version)"
            break
        fi
    fi
done

if [ -z "$PHP_BIN" ]; then
    if command -v php &>/dev/null; then
        PHP_BIN=$(command -v php)
        warning "Using system php: $PHP_BIN"
    else
        die "PHP CLI not found. Please install PHP CLI."
    fi
fi

# -----------------------------------------------------------------------------
# STEP 3: Verify yii console commands exist
# -----------------------------------------------------------------------------
info "Verifying yii console commands..."

mkdir -p "$ADMIN_ROOT/runtime/logs"

MISSING_COMMANDS=()

check_command() {
    local cmd="$1"
    output=$("$PHP_BIN" "$ADMIN_YII_PATH" "$cmd" --help 2>&1 || true)
    if echo "$output" | grep -qi "unknown command\|not found\|no command"; then
        MISSING_COMMANDS+=("$cmd")
        warning "Command not found: $cmd"
    else
        success "Command OK: $cmd"
    fi
}

check_command "user-activity/track"
check_command "user-activity/reset-daily"
check_command "scheduled-call/notify"
check_command "email-sync/external"
check_command "email-sync/corporate"
check_command "reminders/process-all"
check_command "training-task/check-overdue"

if [ ${#MISSING_COMMANDS[@]} -gt 0 ]; then
    echo ""
    warning "The following commands were not found:"
    for cmd in "${MISSING_COMMANDS[@]}"; do
        echo "  - $cmd"
    done
    echo ""
    warning "Cron jobs for these commands will be created but will fail until implemented."
    echo ""
    read -r -p "Continue anyway? [y/N]: " confirm
    if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
        die "Aborted by user."
    fi
fi

# -----------------------------------------------------------------------------
# STEP 4: Set up log file
# -----------------------------------------------------------------------------
info "Setting up log file..."

CRON_LOG="/var/log/crm-cron.log"

touch "$CRON_LOG"
chmod 664 "$CRON_LOG"

success "Cron log: $CRON_LOG"

# -----------------------------------------------------------------------------
# STEP 5: Add cron jobs to root crontab
# -----------------------------------------------------------------------------
info "Adding cron jobs to root crontab..."

# Marker to identify our cron jobs — used for removal/update
MARKER="# crm-global"

# Remove existing CRM cron jobs if any
EXISTING=$(crontab -l 2>/dev/null || true)
CLEANED=$(echo "$EXISTING" | grep -v "$MARKER" || true)

# Build new cron jobs
NEW_JOBS=$(cat << EOF

# User activity tracking — global, no company_id filter needed
*/5 * * * * $PHP_BIN $ADMIN_YII_PATH user-activity/track > /dev/null 2>&1 $MARKER
0 0 * * * $PHP_BIN $ADMIN_YII_PATH user-activity/reset-daily > /dev/null 2>&1 $MARKER

# Scheduled call notifications — iterates over all running companies
*/5 * * * * $PHP_BIN $ADMIN_YII_PATH scheduled-call/notify > /dev/null 2>&1 $MARKER

# External email sync — iterates over all running companies
*/3 * * * * $PHP_BIN $ADMIN_YII_PATH email-sync/external >> $CRON_LOG 2>&1 $MARKER

# Corporate email sync — global, no company_id filter needed
*/5 * * * * $PHP_BIN $ADMIN_YII_PATH email-sync/corporate >> $CRON_LOG 2>&1 $MARKER

# Reminders processing — iterates over all running companies
*/5 * * * * $PHP_BIN $ADMIN_YII_PATH reminders/process-all > /dev/null 2>&1 $MARKER

# Overdue training tasks — iterates over all running companies
*/15 * * * * $PHP_BIN $ADMIN_YII_PATH training-task/check-overdue > /dev/null 2>&1 $MARKER
EOF
)

# Write to crontab
(echo "$CLEANED"; echo "$NEW_JOBS") | crontab -

success "Cron jobs added to root crontab"

# Verify
echo ""
info "Current root crontab (CRM jobs):"
crontab -l | grep "$MARKER" || true

# -----------------------------------------------------------------------------
# STEP 6: Log rotation
# -----------------------------------------------------------------------------
info "Setting up log rotation..."

cat > "/etc/logrotate.d/crm-cron" << EOF
$CRON_LOG {
    weekly
    rotate 4
    compress
    missingok
    notifempty
    create 664 root root
}
EOF

success "Log rotation configured: /etc/logrotate.d/crm-cron"

# -----------------------------------------------------------------------------
# Summary
# -----------------------------------------------------------------------------
echo ""
echo "=============================================="
echo "  Setup Complete"
echo "=============================================="
echo ""
success "Admin panel : $ADMIN_ROOT"
success "PHP binary  : $PHP_BIN"
success "Cron log    : $CRON_LOG"
echo ""
echo "Scheduled jobs:"
echo "  every 5 min  — user-activity/track"
echo "  00:00 daily  — user-activity/reset-daily"
echo "  every 5 min  — scheduled-call/notify"
echo "  every 3 min  — email-sync/external"
echo "  every 5 min  — email-sync/corporate"
echo "  every 5 min  — reminders/process-all"
echo "  every 15 min — training-task/check-overdue"
echo ""

if [ ${#MISSING_COMMANDS[@]} -gt 0 ]; then
    warning "Commands still to implement:"
    for cmd in "${MISSING_COMMANDS[@]}"; do
        echo "  - $cmd"
    done
    echo ""
fi

echo "Monitor email sync log:"
echo "  tail -f $CRON_LOG"
echo ""
echo "View crontab:"
echo "  crontab -l"
echo ""