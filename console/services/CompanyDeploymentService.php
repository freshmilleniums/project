<?php

namespace console\components;

use Yii;
use common\models\Companies;
use common\models\LogsCompany;
use common\models\LogsCompanyDetails;

/**
 * Simplified CRM Deployment Service
 * Uses same database as admin panel, filtered by company_id
 */
class CompanyDeploymentService
{
    private $company;
    private $companyId;
    private $crmPath;
    private $domain;
    private $templatePath;

    public function __construct(Companies $company)
    {
        $this->company = $company;
        $this->companyId = $company->id;

        // Define paths
        $this->domain = $this->sanitizeDomain($company->url ?: $company->name);
        $this->crmPath = "/var/www/sites/crm-{$this->domain}";
        $this->templatePath = "/var/www/templates/crm-base";
    }

    /**
     * Deploy CRM system with shared database
     */
    public function deployCRM()
    {
        try {
            $this->createStepLog("Starting CRM deployment", 5, [
                'domain' => $this->domain,
                'target_path' => $this->crmPath,
                'shared_database' => true
            ]);

            $this->validateEnvironment();
            $this->createDirectories();
            $this->copyCRMFiles();
            $this->configureCRM();
            $this->installDependencies();
            $this->runMigrations();
            $this->createNginxConfig();
            $this->finalizeDeployment();

            $this->createStepLog("CRM deployment completed successfully", 100, [
                'status' => 'completed',
                'crm_url' => "http://crm-{$this->domain}.crm-delivery.site",
                'success' => true,
                'company_id' => $this->companyId
            ]);

        } catch (\Exception $e) {
            $this->createErrorLog("CRM deployment failed", $e->getMessage(), [
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'stack_trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function validateEnvironment()
    {
        $this->createStepLog("Validating deployment environment", 10);

        // Check if template exists
        if (!is_dir($this->templatePath)) {
            throw new \Exception("CRM template not found at: {$this->templatePath}");
        }

        // Check required commands with auto-installation
        $commands = ['composer', 'php', 'mysql', 'nginx'];
        foreach ($commands as $cmd) {
            $output = [];
            exec("which {$cmd} 2>/dev/null", $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Required command not found: {$cmd}");
            }
        }

        // Check WP-CLI with auto-installation (for future WordPress integration)
        $this->ensureWPCLI();

        $this->createStepLog("Environment validation completed", 15);
    }

    /**
     * Ensure WP-CLI is installed (copied from TestDeployController)
     */
    private function ensureWPCLI()
    {
        $this->createStepLog("Checking WP-CLI availability", 12);

        // Check if WP-CLI exists AND works
        exec("wp --info 2>&1", $output, $returnCode);

        if ($returnCode === 0 && !empty($output)) {
            $this->createStepLog("WP-CLI found and working", 13);
            return;
        }

        $this->createStepLog("Installing WP-CLI", 13);

        // Remove broken installation if exists
        exec("sudo rm -f /usr/local/bin/wp 2>/dev/null");

        // Try package manager first (most reliable)
        exec("sudo apt update -qq 2>/dev/null && sudo apt install -y wp-cli 2>/dev/null", $output, $returnCode);

        exec("wp --info 2>&1", $testOutput, $testReturn);
        if ($testReturn === 0) {
            $this->createStepLog("WP-CLI installed via package manager", 14);
            return;
        }

        // Try manual download
        $downloadCommands = [
            "cd /tmp",
            "sudo wget -q --timeout=30 -O wp-cli.phar 'https://github.com/wp-cli/wp-cli/releases/download/v2.8.1/wp-cli-2.8.1.phar'",
            "php wp-cli.phar --info", // Test if downloaded file works
            "sudo chmod +x wp-cli.phar",
            "sudo mv wp-cli.phar /usr/local/bin/wp"
        ];

        $success = true;
        foreach ($downloadCommands as $i => $command) {
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                $success = false;
                break;
            }

            if ($i === 2) { // After testing downloaded file
                $this->createStepLog("WP-CLI downloaded and verified", 13.5);
            }
        }

        if ($success) {
            exec("wp --info 2>&1", $finalTest, $finalReturn);
            if ($finalReturn === 0) {
                $this->createStepLog("WP-CLI installed manually", 14);
                return;
            }
        }

        // WP-CLI installation failed - not critical for CRM-only deploy
        $this->createStepLog("WP-CLI installation failed (not critical for CRM)", 14, [
            'warning' => true,
            'message' => 'WP-CLI not available - WordPress features will be disabled'
        ]);
    }

    private function createDirectories()
    {
        $this->createStepLog("Creating project directories", 20);

        $directories = [
            $this->crmPath,
            $this->crmPath . '/runtime',
            $this->crmPath . '/frontend/runtime',
            $this->crmPath . '/backend/runtime',
            $this->crmPath . '/console/runtime',
            $this->crmPath . '/logs'
        ];

        foreach ($directories as $dir) {
            exec("sudo mkdir -p {$dir}", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \Exception("Failed to create directory: {$dir}");
            }
        }

        exec("sudo chown -R www-data:www-data {$this->crmPath}");
        exec("sudo chmod -R 755 {$this->crmPath}");

        $this->createStepLog("Project directories created", 25);
    }

    private function copyCRMFiles()
    {
        $this->createStepLog("Copying CRM files from template", 30);

        // Copy all files except vendor and runtime
        $excludes = [
            '--exclude=vendor',
            '--exclude=frontend/runtime',
            '--exclude=backend/runtime',
            '--exclude=console/runtime',
            '--exclude=.git',
            '--exclude=.env',
            '--exclude=*.log'
        ];

        $rsyncCommand = "sudo rsync -av " . implode(' ', $excludes) . " {$this->templatePath}/ {$this->crmPath}/";

        exec($rsyncCommand, $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception("Failed to copy CRM files: " . implode("\n", $output));
        }

        // Set ownership
        exec("sudo chown -R www-data:www-data {$this->crmPath}");

        $this->createStepLog("CRM files copied successfully", 40);
    }

    private function configureCRM()
    {
        $this->createStepLog("Configuring CRM with shared database", 45);

        // Copy database config from admin panel (same database!)
        $this->generateDatabaseConfig();
        $this->generateParamsConfig();

        $this->createStepLog("CRM configuration completed", 50);
    }

    private function generateDatabaseConfig()
    {
        // Get current admin database config
        $currentDb = Yii::$app->db;

        $templatePath = $this->templatePath . '/common/config/main-local.php.template';
        $configPath = $this->crmPath . '/common/config/main-local.php';

        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);

            $config = str_replace([
                '{{DB_DSN}}',
                '{{DB_USERNAME}}',
                '{{DB_PASSWORD}}',
                '{{DB_CHARSET}}'
            ], [
                $currentDb->dsn,
                $currentDb->username,
                $currentDb->password,
                'utf8mb4'
            ], $template);
        } else {
            // Generate basic config with same database as admin
            $config = "<?php
// CRM database config - SAME database as admin panel
// Company data filtered by company_id = {$this->companyId}
// Generated: " . date('Y-m-d H:i:s') . "

return [
    'components' => [
        'db' => [
            'class' => 'yii\\db\\Connection',
            'dsn' => '{$currentDb->dsn}',
            'username' => '{$currentDb->username}',
            'password' => '{$currentDb->password}',
            'charset' => 'utf8mb4',
            // Uses same tables as admin, filtered by company_id
        ],
        'mailer' => [
            'class' => 'yii\\swiftmailer\\Mailer',
            'viewPath' => '@common/mail',
            'useFileTransport' => true,
        ],
    ],
];";
        }

        if (!file_put_contents($configPath, $config)) {
            throw new \Exception("Failed to write database config");
        }

        exec("sudo chmod 644 {$configPath}");
        exec("sudo chown www-data:www-data {$configPath}");
    }

    private function generateParamsConfig()
    {
        $templatePath = $this->templatePath . '/common/config/params-local.php.template';
        $paramsPath = $this->crmPath . '/common/config/params-local.php';

        if (file_exists($templatePath)) {
            $template = file_get_contents($templatePath);

            $crmDomain = "crm-{$this->domain}.crm-delivery.site";
            $websocketSecret = 'crm_' . $this->companyId . '_' . Yii::$app->security->generateRandomString(16);
            $cookieKey = Yii::$app->security->generateRandomString(32);

            $config = str_replace([
                '{{COOKIE_VALIDATION_KEY}}',
                '{{COMPANY_ID}}',
                '{{COMPANY_NAME}}',
                '{{DOMAIN}}',
                '{{CRM_DOMAIN}}',
                '{{LANDING_API_KEY}}',
                '{{WEBSOCKET_SECRET}}',
                '{{WEBSOCKET_URL}}',
                '{{ADMIN_EMAIL}}',
                '{{SUPPORT_EMAIL}}',
                '{{SENDER_EMAIL}}',
                '{{SENDER_NAME}}'
            ], [
                $cookieKey,
                $this->companyId,
                addslashes($this->company->name),
                $this->domain,
                $crmDomain,
                $this->company->landing_api_key,
                $websocketSecret,
                "wss://{$crmDomain}/websocket",
                "admin@{$this->domain}",
                "support@{$this->domain}",
                "noreply@{$this->domain}",
                addslashes($this->company->name) . ' CRM'
            ], $template);
        } else {
            // Generate basic params config
            $cookieKey = Yii::$app->security->generateRandomString(32);
            $crmDomain = "crm-{$this->domain}.crm-delivery.site";

            $config = "<?php
// CRM params config for company {$this->companyId}
// Generated: " . date('Y-m-d H:i:s') . "

return [
    'cookieValidationKey' => '{$cookieKey}',
    'company_id' => {$this->companyId}, // КЛЮЧЕВОЙ ПАРАМЕТР для фильтрации
    
    'aftership' => [
        'apiKey' => 'asat_d28497cd46d840ee9d1827f5db9079ee'
    ],
    
    'adminEmail' => 'admin@{$this->domain}',
    'supportEmail' => 'support@{$this->domain}',
    'senderEmail' => 'noreply@{$this->domain}',
    'senderName' => '" . addslashes($this->company->name) . " CRM',
    
    'websocketSecret' => 'crm_{$this->companyId}_" . Yii::$app->security->generateRandomString(16) . "',
    'websocketUrl' => 'wss://{$crmDomain}/websocket',
    'websocketSSL' => [
        'enabled' => false,
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => false,
    ],
    
    'company' => [
        'id' => {$this->companyId},
        'name' => '" . addslashes($this->company->name) . "',
        'domain' => '{$this->domain}',
        'crm_domain' => '{$crmDomain}',
        'landing_api_key' => '{$this->company->landing_api_key}',
    ],
];";
        }

        if (!file_put_contents($paramsPath, $config)) {
            throw new \Exception("Failed to write params config");
        }

        exec("sudo chmod 644 {$paramsPath}");
        exec("sudo chown www-data:www-data {$paramsPath}");
    }

    private function installDependencies()
    {
        $this->createStepLog("Installing Composer dependencies", 55);

        $commands = [
            "cd {$this->crmPath}",
            "sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction"
        ];

        $command = implode(' && ', $commands);
        exec($command . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("Composer install failed: " . implode("\n", $output));
        }

        $this->createStepLog("Dependencies installed successfully", 70);
    }

    private function runMigrations()
    {
        $this->createStepLog("Running database migrations (shared database)", 75);

        // Note: Migrations run on shared database but data will be filtered by company_id
        $command = "cd {$this->crmPath} && sudo -u www-data php yii migrate --interactive=0";
        exec($command . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            // Check if it's just "no new migrations" - not an error
            $outputText = implode("\n", $output);
            if (strpos($outputText, 'No new migrations found') === false) {
                throw new \Exception("Database migrations failed: " . $outputText);
            }
        }

        $this->createStepLog("Database migrations completed", 80);
    }

    private function createNginxConfig()
    {
        $this->createStepLog("Creating Nginx configuration", 85);

        $domain = "crm-{$this->domain}.crm-delivery.site";
        $nginxConfig = $this->generateNginxConfig($domain);

        $configFile = "/etc/nginx/conf.d/00-{$domain}.conf";

        if (!file_put_contents($configFile, $nginxConfig)) {
            throw new \Exception("Failed to write Nginx config");
        }

        // Test and reload nginx
        exec("sudo nginx -t 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception("Nginx configuration test failed: " . implode("\n", $output));
        }

        exec("sudo systemctl reload nginx 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception("Nginx reload failed: " . implode("\n", $output));
        }

        // Wait for nginx to fully reload (like in TestDeployController)
        sleep(2);

        $this->createStepLog("Nginx configuration applied", 90, [
            'domain' => $domain,
            'config_file' => $configFile
        ]);
    }

    private function finalizeDeployment()
    {
        $this->createStepLog("Finalizing CRM deployment", 95);

        // Create admin user if needed
        $this->createAdminUser();

        // Update company URL
        $crmUrl = "http://crm-{$this->domain}.crm-delivery.site";
        $this->company->url = $crmUrl;
        $this->company->save(false);

        // Test if site is accessible
        sleep(2);
        $testResponse = $this->testSiteAccess($crmUrl);

        $this->createStepLog("CRM finalization completed", 98, [
            'crm_url' => $crmUrl,
            'site_accessible' => $testResponse,
            'admin_created' => true,
            'uses_shared_database' => true,
            'filtered_by_company_id' => $this->companyId
        ]);
    }

    private function createAdminUser()
    {
        $adminEmail = $this->company->admin_email ?? 'admin@' . $this->domain;
        $adminPassword = $this->company->admin_password ?? 'admin123';

        $command = "cd {$this->crmPath} && sudo -u www-data php yii user/create-admin '{$adminEmail}' '{$adminPassword}'";
        exec($command . " 2>&1", $output, $returnCode);

        // Don't fail if user already exists
        $outputText = implode("\n", $output);
        if ($returnCode !== 0 && strpos($outputText, 'already exists') === false) {
            Yii::warning("Admin user creation warning: " . $outputText);
        }
    }

    private function generateNginxConfig($domain)
    {
        $publicPath = $this->crmPath . '/frontend/web';
        $logPath = $this->crmPath . '/logs';

        return "# Auto-generated CRM config for {$domain}
# Company ID: {$this->companyId}
# Created: " . date('Y-m-d H:i:s') . "

server {
    listen 80;
    server_name {$domain};
    
    root {$publicPath};
    index index.php index.html;
    
    # Logs
    access_log {$logPath}/access.log;
    error_log {$logPath}/error.log;
    
    # Main location
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # PHP handling
    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        try_files \$uri =404;
    }
    
    # Static files
    location ~* \\.(css|js|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1h;
        access_log off;
    }
    
    # Security
    location ~ /\\. {
        deny all;
    }
    
    # CRM site marker
    add_header X-CRM-Site \"company-{$this->companyId}\" always;
    add_header X-Shared-Database \"true\" always;
}";
    }

    private function testSiteAccess($url)
    {
        $command = "curl -s -o /dev/null -w '%{http_code}' {$url}";
        $httpCode = trim(shell_exec($command));

        return $httpCode === '200';
    }

    /**
     * Deploy WordPress with Elementor and essential plugins (future use)
     */
    public function deployWordPress()
    {
        try {
            $this->createStepLog("Starting WordPress deployment", 50, [
                'includes_elementor' => true,
                'auto_install_plugins' => true
            ]);

            $this->createWordPressDirectories();
            $this->downloadWordPress();
            $this->configureWordPress();
            $this->installWordPressPlugins();
            $this->installElementor();
            $this->configureElementorSettings();
            $this->createBasicWordPressPages();

            $this->createStepLog("WordPress with Elementor deployed successfully", 85);

        } catch (\Exception $e) {
            $this->createErrorLog("WordPress deployment failed", $e->getMessage(), [
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine()
            ]);
            throw $e;
        }
    }

    private function createWordPressDirectories()
    {
        $this->createStepLog("Creating WordPress directories", 52);

        $wpPath = $this->crmPath . '-wp'; // Separate WordPress directory
        $directories = [
            $wpPath,
            $wpPath . '/logs'
        ];

        foreach ($directories as $dir) {
            exec("sudo mkdir -p {$dir}", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \Exception("Failed to create WordPress directory: {$dir}");
            }
        }

        exec("sudo chown -R www-data:www-data {$wpPath}");
        $this->wpPath = $wpPath; // Store for later use
    }

    private function downloadWordPress()
    {
        $this->createStepLog("Downloading WordPress core", 55);

        $command = "cd {$this->wpPath} && sudo -u www-data wp core download --locale=en_US";
        exec($command . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            throw new \Exception("WordPress download failed: " . implode("\n", $output));
        }
    }

    private function configureWordPress()
    {
        $this->createStepLog("Configuring WordPress with separate database", 60);

        $wpUrl = "https://wp-{$this->domain}.crm-delivery.site";

        // WordPress will have its own database
        $wpDbName = "wp_{$this->domain}_" . $this->companyId;
        $wpDbUser = "wp_user_" . $this->companyId;
        $wpDbPass = 'wp_' . Yii::$app->security->generateRandomString(12);

        // Create separate database and user for WordPress
        $this->createWordPressDatabase($wpDbName, $wpDbUser, $wpDbPass);

        // Create wp-config.php with separate database
        $dbCommands = [
            "cd {$this->wpPath}",
            "sudo -u www-data wp core config --dbname={$wpDbName}" .
            " --dbuser={$wpDbUser} --dbpass='{$wpDbPass}'" .
            " --dbhost=localhost",

            "sudo -u www-data wp core install --url='{$wpUrl}'" .
            " --title='" . addslashes($this->company->name) . " Website'" .
            " --admin_user=admin --admin_password=wp-admin-{$this->companyId}" .
            " --admin_email='admin@{$this->domain}' --skip-email"
        ];

        foreach ($dbCommands as $i => $command) {
            exec($command . " 2>&1", $output, $returnCode);
            if ($returnCode !== 0) {
                throw new \Exception("WordPress configuration failed at step " . ($i + 1) . ": " . implode("\n", $output));
            }
        }

        // Save WordPress database credentials for future use
        $this->saveWordPressCredentials($wpDbName, $wpDbUser, $wpDbPass);
    }

    /**
     * Create separate database for WordPress
     */
    private function createWordPressDatabase($dbName, $dbUser, $dbPass)
    {
        $this->createStepLog("Creating WordPress database: {$dbName}", 58);

        $adminDb = Yii::$app->db;

        // Get admin DB credentials for creating new database
        $adminUser = $adminDb->username;
        $adminPassword = $adminDb->password;
        $dbHost = $this->extractDbHost($adminDb->dsn);

        $sqlCommands = [
            "CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci",
            "CREATE USER IF NOT EXISTS '{$dbUser}'@'localhost' IDENTIFIED BY '{$dbPass}'",
            "GRANT ALL PRIVILEGES ON `{$dbName}`.* TO '{$dbUser}'@'localhost'",
            "FLUSH PRIVILEGES"
        ];

        foreach ($sqlCommands as $sql) {
            $command = "mysql -h{$dbHost} -u{$adminUser} -p{$adminPassword} -e \"{$sql}\"";
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Failed to execute SQL: {$sql}. Error: " . implode("\n", $output));
            }
        }

        $this->createStepLog("WordPress database and user created", 59);
    }

    /**
     * Save WordPress credentials to company config
     */
    private function saveWordPressCredentials($dbName, $dbUser, $dbPass)
    {
        $wpCredentials = [
            'wp_db_name' => $dbName,
            'wp_db_user' => $dbUser,
            'wp_db_password' => $dbPass,
            'wp_admin_user' => 'admin',
            'wp_admin_password' => 'wp-admin-' . $this->companyId,
            'wp_url' => "https://wp-{$this->domain}.crm-delivery.site",
            'created_at' => date('Y-m-d H:i:s')
        ];

        // Save to company config field
        $existingConfig = $this->company->config ? json_decode($this->company->config, true) : [];
        $existingConfig['wordpress'] = $wpCredentials;

        $this->company->config = json_encode($existingConfig, JSON_PRETTY_PRINT);
        $this->company->save(false);

        $this->createStepLog("WordPress credentials saved to company config", 61, [
            'wp_database' => $dbName,
            'wp_url' => $wpCredentials['wp_url']
        ]);
    }

    private function installWordPressPlugins()
    {
        $this->createStepLog("Installing essential WordPress plugins", 65);

        // Essential plugins list (minimal and focused)
        $essentialPlugins = [
            'wordfence' => 'Wordfence Security'
        ];

        foreach ($essentialPlugins as $plugin => $name) {
            $command = "cd {$this->wpPath} && sudo -u www-data wp plugin install {$plugin} --activate";
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                $this->createStepLog("Installed plugin: {$name}", 66);
            } else {
                // Log warning but don't fail deployment
                $this->createStepLog("Failed to install plugin: {$name}", 66, [
                    'warning' => true,
                    'plugin' => $plugin,
                    'error_output' => implode("\n", array_slice($output, -3))
                ]);
            }
        }
    }

    private function installElementor()
    {
        $this->createStepLog("Installing Elementor Page Builder", 70);

        // Install Elementor (free version)
        $elementorCommands = [
            "cd {$this->wpPath} && sudo -u www-data wp plugin install elementor --activate",
            "cd {$this->wpPath} && sudo -u www-data wp plugin install template-kit-import --activate" // For templates
        ];

        foreach ($elementorCommands as $i => $command) {
            exec($command . " 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                throw new \Exception("Elementor installation failed: " . implode("\n", $output));
            }
        }

        $this->createStepLog("Elementor installed successfully", 75);
    }

    private function configureElementorSettings()
    {
        $this->createStepLog("Configuring Elementor settings", 77);

        $elementorSettings = [
            // Enable Elementor for all post types
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_cpt_support '[\"page\",\"post\"]'",

            // Set default color scheme
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_scheme_color_1 '#0073aa'",
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_scheme_color_2 '#23282d'",

            // Enable safe mode off (for better performance)
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_safe_mode ''",

            // Set container width
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_container_width 1200",

            // Enable improved CSS loading
            "cd {$this->wpPath} && sudo -u www-data wp option update elementor_css_print_method 'internal'"
        ];

        foreach ($elementorSettings as $command) {
            exec($command . " 2>&1", $output, $returnCode);
            // Continue even if some settings fail
        }

        $this->createStepLog("Elementor configured with optimized settings", 78);
    }

    private function createBasicWordPressPages()
    {
        $this->createStepLog("Creating basic WordPress pages", 80);

        // Create basic pages that work well with Elementor
        $pages = [
            'Home' => 'Welcome to ' . $this->company->name,
            'About' => 'About Our Company',
            'Services' => 'Our Services',
            'Contact' => 'Get In Touch',
            'Blog' => 'Latest News'
        ];

        foreach ($pages as $title => $content) {
            $command = "cd {$this->wpPath} && sudo -u www-data wp post create" .
                " --post_type=page --post_title='{$title}'" .
                " --post_content='{$content}' --post_status=publish";

            exec($command . " 2>&1", $output, $returnCode);
        }

        // Set front page
        $homePageCmd = "cd {$this->wpPath} && sudo -u www-data wp post list --post_type=page --post_title='Home' --field=ID";
        $homePageId = trim(shell_exec($homePageCmd));

        if ($homePageId) {
            exec("cd {$this->wpPath} && sudo -u www-data wp option update show_on_front page");
            exec("cd {$this->wpPath} && sudo -u www-data wp option update page_on_front {$homePageId}");
        }

        // Set blog page
        $blogPageCmd = "cd {$this->wpPath} && sudo -u www-data wp post list --post_type=page --post_title='Blog' --field=ID";
        $blogPageId = trim(shell_exec($blogPageCmd));

        if ($blogPageId) {
            exec("cd {$this->wpPath} && sudo -u www-data wp option update page_for_posts {$blogPageId}");
        }

        $this->createStepLog("Basic pages created and configured", 82);
    }

    /**
     * Extract database host from DSN
     */
    private function extractDbHost($dsn)
    {
        if (preg_match('/host=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }
        return 'localhost';
    }

    /**
     * Extract database name from DSN
     */
    private function extractDbName($dsn)
    {
        if (preg_match('/dbname=([^;]+)/', $dsn, $matches)) {
            return $matches[1];
        }
        return '';
    }

    /**
     * Create WordPress Nginx configuration
     */
    private function createWordPressNginxConfig()
    {
        $this->createStepLog("Creating WordPress Nginx configuration", 88);

        $wpDomain = "wp-{$this->domain}.crm-delivery.site";
        $wpPublicPath = $this->wpPath;
        $wpLogPath = $this->wpPath . '/logs';

        $nginxConfig = "# Auto-generated WordPress config for {$wpDomain}
# With Elementor optimization + Separate Database
# Company ID: {$this->companyId}
# Created: " . date('Y-m-d H:i:s') . "

server {
    listen 80;
    server_name {$wpDomain};
    
    root {$wpPublicPath};
    index index.php index.html;
    
    # Logs
    access_log {$wpLogPath}/access.log;
    error_log {$wpLogPath}/error.log;
    
    # WordPress permalinks
    location / {
        try_files \$uri \$uri/ /index.php?\$args;
    }
    
    # PHP handling with Elementor optimization
    location ~ \\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_index index.php;
        try_files \$uri =404;
        
        # Elementor optimizations
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
        
        # Larger buffers for Elementor
        fastcgi_buffer_size 32k;
        fastcgi_buffers 4 32k;
        fastcgi_busy_buffers_size 64k;
    }
    
    # WordPress uploads security
    location ~* /(?:uploads|files)/.*\\.php$ {
        deny all;
    }
    
    # WordPress sensitive files
    location ~ /\\.(htaccess|htpasswd) {
        deny all;
    }
    
    location ~ /wp-config\\.php {
        deny all;
    }
    
    # Elementor static files optimization
    location ~* \\.(css|gif|ico|jpeg|jpg|js|png|webp|woff|woff2|ttf|svg|eot)$ {
        expires 1y;
        add_header Cache-Control \"public, immutable\";
        add_header Vary Accept-Encoding;
        access_log off;
        log_not_found off;
    }
    
    # WordPress xmlrpc protection
    location = /xmlrpc.php {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    # WordPress admin with larger limits for Elementor
    location ~ /wp-admin/.*\\.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        try_files \$uri =404;
        
        # Higher limits for Elementor editor
        client_max_body_size 100M;
        fastcgi_read_timeout 600;
    }
    
    # Elementor specific optimizations
    location ~ /wp-content/uploads/elementor/ {
        expires 1y;
        add_header Cache-Control \"public\";
        access_log off;
    }
    
    # Site markers
    add_header X-WordPress-Site \"company-{$this->companyId}\" always;
    add_header X-Elementor-Ready \"true\" always;
    add_header X-Separate-Database \"true\" always;
}";

        $configFile = "/etc/nginx/conf.d/00-{$wpDomain}.conf";

        if (!file_put_contents($configFile, $nginxConfig)) {
            throw new \Exception("Failed to write WordPress Nginx config");
        }

        // Test and reload nginx
        exec("sudo nginx -t 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception("WordPress Nginx configuration test failed: " . implode("\n", $output));
        }

        exec("sudo systemctl reload nginx 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            throw new \Exception("Nginx reload failed: " . implode("\n", $output));
        }

        // Wait for nginx to fully reload
        sleep(2);

        $this->createStepLog("WordPress Nginx configuration applied", 90, [
            'wp_domain' => $wpDomain,
            'elementor_optimized' => true,
            'config_file' => $configFile
        ]);
    }

    private function sanitizeDomain($domain)
    {
        return preg_replace('/[^a-zA-Z0-9\-]/', '', strtolower($domain));
    }

    /**
     * Create step log (NEW main log entry)
     */
    private function createStepLog($step, $progress = null, $additionalData = [])
    {
        try {
            $log = new LogsCompany();
            $log->company_id = $this->companyId;
            $log->user_id = 1;
            $log->action_type = LogsCompany::ACTION_DEPLOY;

            if (!$log->save()) {
                Yii::error('Failed to create step log: ' . json_encode($log->getErrors()), 'deployment');
                return;
            }

            $data = array_merge([
                'step' => $step,
                'timestamp' => date('Y-m-d H:i:s'),
                'service' => 'CRMDeploymentService',
                'shared_database' => true
            ], $additionalData);

            if ($progress !== null) {
                $data['progress'] = (int)$progress;
            }

            $log->addDetail($data, LogsCompanyDetails::TYPE_JSON);
            Yii::info("CRM Step: {$step} ({$progress}%)", 'deployment');

        } catch (\Exception $e) {
            Yii::error('Exception creating step log: ' . $e->getMessage(), 'deployment');
        }
    }

    /**
     * Create error log
     */
    private function createErrorLog($step, $errorMessage, $additionalData = [])
    {
        try {
            $log = new LogsCompany();
            $log->company_id = $this->companyId;
            $log->user_id = 1;
            $log->action_type = LogsCompany::ACTION_DEPLOY;
            $log->save(false);

            $data = array_merge([
                'step' => $step,
                'error' => true,
                'critical' => true,
                'message' => $errorMessage,
                'timestamp' => date('Y-m-d H:i:s'),
                'service' => 'CRMDeploymentService'
            ], $additionalData);

            $log->addDetail($data, LogsCompanyDetails::TYPE_JSON);
            Yii::error("CRM Error: {$step} - {$errorMessage}", 'deployment');

        } catch (\Exception $e) {
            Yii::error('Exception creating error log: ' . $e->getMessage(), 'deployment');
        }
    }
}