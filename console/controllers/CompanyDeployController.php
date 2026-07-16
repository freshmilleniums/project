<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\Companies;
use common\models\LogsCompany;
use common\models\LogsCompanyDetails;

/**
 * Company deployment console controller
 */
class CompanyDeployController extends Controller
{
    /**
     * MySQL user for database operations
     */
    public $mysqlUser;

    /**
     * MySQL password for database operations
     */
    public $mysqlPassword;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['mysqlUser', 'mysqlPassword']);
    }

    public function optionAliases()
    {
        return array_merge(parent::optionAliases(), [
            'u' => 'mysqlUser',
            'p' => 'mysqlPassword'
        ]);
    }

    /**
     * Bootstrap CRM deployment
     * Usage: php yii company-deploy/bootstrap-crm <company_id>
     */
    public function actionBootstrapCrm($companyId, $logId = null)
    {
        try {
            $this->stdout("Bootstrapping CRM deployment for company {$companyId}\n");
            if ($logId) {
                $this->stdout("Using provided logId: {$logId}\n");
            }
            return $this->actionCrm($companyId, $logId);
        } catch (\Exception $e) {
            $this->stderr("Bootstrap failed: {$e->getMessage()}\n");
            return 1;
        }
    }

    /**
     * Deploy ONLY CRM system
     * Usage: php yii company-deploy/crm <company_id> [log_id]
     */
    public function actionCrm($companyId, $logId = null)
    {
        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $this->stdout("Starting CRM-ONLY deployment for company {$companyId}\n");

            $company = Companies::findOne($companyId);
            if (!$company) {
                $this->stderr("Company {$companyId} not found\n");
                return 1;
            }

            if (!$logId) {
                $deploymentLog = new LogsCompany();
                $deploymentLog->company_id = $companyId;
                $deploymentLog->user_id = 1;
                $deploymentLog->action_type = LogsCompany::ACTION_DEPLOY;

                if (!$deploymentLog->save(false)) {
                    $this->stderr("Failed to create deployment log\n");
                    return 1;
                }

                $logId = $deploymentLog->id;
                $this->stdout("Created deployment log ID: {$logId}\n");

                $company->status = Companies::STATUS_DEPLOYING;
                $company->save(false);
            } else {
                $deploymentLog = LogsCompany::findOne($logId);
                if (!$deploymentLog) {
                    $this->stderr("Deployment log {$logId} not found\n");
                    return 1;
                }

                $this->stdout("Continuing deployment with existing log ID: {$logId}\n");
            }

            // Step 1: Validate CRM prerequisites
            $this->updateProgress($deploymentLog, 'Checking CRM prerequisites...', 5);
            if (!$this->validateCRMPrerequisites($company)) {
                $this->updateProgress($deploymentLog, 'CRM prerequisites validation failed', 5, true, 'Missing required CRM components');
                $company->status = Companies::STATUS_STOPPED;
                $company->save(false);
                return 1;
            }
            $this->updateProgress($deploymentLog, 'CRM prerequisites validated successfully', 10);

            // Step 2: Create directories
            $this->updateProgress($deploymentLog, 'Creating CRM deployment directory...', 15);
            $deploymentPath = $this->determineDeploymentPath($company);
            $this->stdout("Selected deployment path: {$deploymentPath}\n");

            if (!$this->createCRMDirectoriesSudo($deploymentPath)) {
                $this->updateProgress($deploymentLog, 'Failed to create CRM directories', 15, true, "Could not create directory: {$deploymentPath}");
                return 1;
            }
            $this->updateProgress($deploymentLog, 'CRM deployment directory created successfully', 25);

            // Step 3: Copy CRM files
            $this->updateProgress($deploymentLog, 'Copying CRM files from deploy-storage...', 30);
            $crmSourcePath = $this->findCRMSourcePath();
            if (!$crmSourcePath || !$this->copyCRMFiles($crmSourcePath, $deploymentPath, $company)) {
                $this->updateProgress($deploymentLog, 'CRM files copy failed', 30, true, 'Failed to copy CRM files from deploy-storage');
                return 1;
            }
            $this->updateProgress($deploymentLog, 'CRM files copied successfully', 45);

            // Step 4: Prepare configuration files
            $this->updateProgress($deploymentLog, 'Preparing CRM configuration files...', 50);
            if (!$this->prepareCRMConfigSharedDB($deploymentPath, $company)) {
                $this->updateProgress($deploymentLog, 'CRM configuration failed', 50, true, 'Configuration file preparation error');
                return 1;
            }
            $this->updateProgress($deploymentLog, 'CRM configuration files prepared', 60);

            // Step 5: Dependencies
            if (is_dir($deploymentPath . '/vendor')) {
                $this->updateProgress($deploymentLog, 'CRM dependencies already installed (vendor exists)', 75);
                $this->stdout("Vendor directory exists, skipping composer install\n");
            } else {
                $this->updateProgress($deploymentLog, 'Installing CRM dependencies...', 65);
                if (!$this->installCRMDependencies($deploymentPath)) {
                    $this->updateProgress($deploymentLog, 'CRM dependencies installation failed', 65, true, 'Dependency installation error');
                    return 1;
                }
                $this->updateProgress($deploymentLog, 'CRM dependencies installed successfully', 75);
            }

            // Step 6: Configure HTTP Nginx (for ACME validation)
            $this->updateProgress($deploymentLog, 'Configuring HTTP virtual host...', 70);
            if (!$this->configureCRMNginxFixed($company, $deploymentPath)) {
                $this->updateProgress($deploymentLog, 'CRM nginx configuration failed', 70, true, 'Web server configuration error');
                return 1;
            }
            $this->updateProgress($deploymentLog, 'HTTP virtual host configured', 75);

            // Step 7: Obtain SSL certificate
            $this->updateProgress($deploymentLog, 'Obtaining SSL certificate...', 78);
            $sslResult = $this->setupSSLForDomain($company, $deploymentPath);

            if ($sslResult['success']) {
                $this->updateProgress($deploymentLog, 'SSL certificate obtained successfully', 85);

                // Step 8: Reconfigure Nginx with HTTPS
                $this->updateProgress($deploymentLog, 'Configuring HTTPS virtual host...', 88);
                if (!$this->configureCRMNginxWithSSL($company, $deploymentPath, $sslResult)) {
                    $this->updateProgress($deploymentLog, 'HTTPS configuration failed, keeping HTTP', 88, false, 'Site available via HTTP');
                } else {
                    $this->updateProgress($deploymentLog, 'HTTPS virtual host configured', 90);
                }
            } else {
                $this->updateProgress($deploymentLog, 'SSL failed, site available via HTTP: ' . $sslResult['message'], 85, false);
            }

            // Step 9: Set file permissions
            $this->updateProgress($deploymentLog, 'Setting CRM file permissions...', 95);
            if (!$this->setCRMPermissionsSudo($deploymentPath)) {
                $this->updateProgress($deploymentLog, 'CRM permission setting failed', 95, true, 'File permission error');
                return 1;
            }
            $this->updateProgress($deploymentLog, 'CRM file permissions set successfully', 98);

            // Final step: Complete CRM deployment
            $this->updateProgress($deploymentLog, 'CRM deployment completed successfully!', 100, false, "CRM site available at: {$company->url}");

            $company->status = Companies::STATUS_RUNNING;
            $company->save(false);

            $this->stdout("CRM deployment completed successfully for company {$companyId}\n");
            $this->stdout("CRM URL: {$company->url}\n");

            return 0;

        } catch (\Exception $e) {
            $this->stderr("CRM deployment failed: {$e->getMessage()}\n");

            if (isset($deploymentLog)) {
                $this->updateProgress($deploymentLog, 'Critical CRM deployment error', 0, true, $e->getMessage(), $e->getTraceAsString());
            }

            if (isset($company)) {
                $company->status = Companies::STATUS_STOPPED;
                $company->save(false);
            }

            return 1;
        }
    }

    private function determineDeploymentPath($company) {

        if (empty($company->url)) {
            throw new \Exception("Company URL is not set. Please configure Companies[url] field first.");
        }

        $domain = preg_replace('/^https?:\/\//', '', $company->url);
        $this->stdout("Using domain from Companies[url]: {$domain}\n");

        $pathConfigs = [
            [
                'path' => "/var/www/sites/{$domain}",
                'parent' => '/var/www/sites',
                'priority' => 1
            ],
        ];

        foreach ($pathConfigs as $config) {
            $parent = $config['parent'];
            $path = $config['path'];

            $this->stdout("Testing deployment path option {$config['priority']}: {$parent}\n");

            if (!is_dir($parent)) {
                $this->stdout("  ✗ Parent directory does not exist: {$parent}\n");
                continue;
            }

            $this->stdout("  ✓ Parent directory exists\n");

            if (!is_readable($parent)) {
                $this->stdout("  ✗ Parent directory not readable: {$parent}\n");
                continue;
            }

            if (is_writable($parent)) {
                $this->stdout("  ✓ Parent directory is writable directly (777 rights)\n");

                $testFile = $parent . '/test_write_' . time();

                if (touch($testFile)) {
                    $this->stdout("  ✓ Direct write test successful\n");
                    unlink($testFile);

                    $this->stdout("  🎯 SELECTED PATH: {$path} (direct write access)\n");
                    return $path;
                } else {
                    $this->stdout("  ✗ Direct write test failed despite writable flag\n");
                }
            } else {
                $this->stdout("  ⚠ Parent directory not writable directly, trying sudo...\n");
            }

            $testFile = $parent . '/test_write_sudo_' . time();
            $sudoTestCmd = "sudo touch {$testFile} && sudo rm {$testFile}";
            exec($sudoTestCmd . " 2>/dev/null", $output, $returnCode);

            if ($returnCode === 0) {
                $this->stdout("  ✓ SUDO write test successful\n");
                $this->stdout("  🎯 SELECTED PATH: {$path} (sudo access)\n");
                return $path;
            } else {
                $this->stdout("  ✗ SUDO write test failed (return code: {$returnCode})\n");
            }
        }

        if (!is_dir('/var/www/sites')) {
            $this->stdout("Attempting to create /var/www/sites directory...\n");

            exec("sudo mkdir -p /var/www/sites 2>/dev/null", $output, $createCode);
            if ($createCode === 0) {
                exec("sudo chown www-data:www-data /var/www/sites 2>/dev/null");
                exec("sudo chmod 755 /var/www/sites 2>/dev/null");

                $newPath = "/var/www/sites/{$domain}";
                $this->stdout("  ✓ Created /var/www/sites successfully\n");
                $this->stdout("  🎯 SELECTED PATH: {$newPath} (newly created)\n");
                return $newPath;
            } else {
                $this->stdout("  ✗ Failed to create /var/www/sites\n");
            }
        }

        if (is_dir('/var/www')) {
            $directPath = "/var/www/{$domain}";
            $this->stdout("Trying direct /var/www path: {$directPath}\n");

            if (is_writable('/var/www')) {
                $this->stdout("  ✓ /var/www is writable directly\n");
                $this->stdout("  🎯 SELECTED PATH: {$directPath} (direct /var/www)\n");
                return $directPath;
            } else {
                exec("sudo mkdir -p {$directPath} 2>/dev/null && sudo chown www-data:www-data {$directPath}", $output, $directCode);
                if ($directCode === 0) {
                    $this->stdout("  ✓ Created directory in /var/www via sudo\n");
                    $this->stdout("  🎯 SELECTED PATH: {$directPath} (sudo created)\n");
                    return $directPath;
                }
            }
        }

        $fallback = "/tmp/{$domain}";
        $this->stdout("⚠ WARNING: All paths failed, using fallback: {$fallback}\n");
        $this->stdout("  ℹ This will work but files should be moved to proper web directory!\n");

        return $fallback;
    }

    private function createCRMDirectoriesSudo($deploymentPath)
    {
        try {
            $this->stdout("Creating CRM deployment directory with sudo: {$deploymentPath}\n");

            if (is_dir($deploymentPath)) {
                $this->executeSudoCommand("rm -rf {$deploymentPath}");
            }

            $this->executeSudoCommand("mkdir -p {$deploymentPath}");
            $this->executeSudoCommand("mkdir -p {$deploymentPath}/logs");
            $this->executeSudoCommand("mkdir -p {$deploymentPath}/backups");
            $this->executeSudoCommand("chown -R www-data:www-data {$deploymentPath}");
            $this->executeSudoCommand("chmod -R 755 {$deploymentPath}");

            if (!is_dir($deploymentPath)) {
                throw new \Exception("Directory {$deploymentPath} was not created");
            }

            $testFile = $deploymentPath . '/test_write_' . time() . '.txt';
            $this->executeSudoCommand("touch {$testFile}");

            if (file_exists($testFile)) {
                $this->executeSudoCommand("rm {$testFile}");
                $this->stdout("Directory created and write test passed\n");
            } else {
                throw new \Exception("Write test failed for directory {$deploymentPath}");
            }

            return true;
        } catch (\Exception $e) {
            $this->stderr("Directory creation failed: {$e->getMessage()}\n");
            return false;
        }
    }

    private function executeSudoCommand($command)
    {
        $fullCommand = "sudo {$command}";
        $this->stdout("  Executing: {$fullCommand}\n");

        $output = [];
        $returnCode = null;

        $nonInteractiveCommand = "sudo -n {$command}";
        exec($nonInteractiveCommand . " 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $outputStr = implode("\n", $output);

            if (strpos($outputStr, 'password') !== false || strpos($outputStr, 'sorry') !== false) {
                throw new \Exception("Sudo access denied. User www-data needs NOPASSWD sudo rights or use direct file permissions.");
            }

            throw new \Exception("Command failed: {$fullCommand}\nOutput: {$outputStr}");
        }

        return $output;
    }

    /**
     * Configure Nginx for CRM with auto-detection of PHP-FPM socket
     */
    private function configureCRMNginxFixed($company, $deploymentPath)
    {
        try {
            if (empty($company->url)) {
                throw new \Exception("Company URL is not set. Please configure Companies[url] field first.");
            }

            $domain = preg_replace('/^https?:\/\//', '', $company->url);
            $this->stdout("Using CRM domain from Companies[url]: {$domain}\n");

            $backendPath = $deploymentPath . '/backend/web';
            $frontendPath = $deploymentPath . '/frontend/web';
            $logPath = $deploymentPath . '/logs';

            $serverIP = $this->detectServerIP();

            // Auto-detect PHP-FPM socket
            $socketPath = $this->detectPHPFPMSocket();
            $this->stdout("Using detected PHP-FPM socket: {$socketPath}\n");

            // Validate socket
            try {
                $this->validatePHPFPMSocket($socketPath);
            } catch (\Exception $e) {
                throw new \Exception("PHP-FPM socket validation failed: {$e->getMessage()}");
            }

            // Create missing index.php files
            $this->createYiiIndexFiles($deploymentPath, $company);

            // Nginx config with auto-detected socket
            $nginxConfig = "# CRM Config for Company {$domain} - {$company->name}
# Created: " . date('Y-m-d H:i:s') . "

server {
    server_name {$domain};
    listen {$serverIP}:80;

    charset utf-8;

    gzip on;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/css text/xml application/javascript text/plain application/json image/svg+xml image/x-icon;
    gzip_comp_level 1;

    set \$base_root {$deploymentPath};
    root \$base_root;
    index index.php index.html;

    disable_symlinks if_not_owner from=\$base_root;

    # Websocket support
    location /websocket {
        proxy_pass http://127.0.0.1:8901;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"Upgrade\";
        proxy_set_header Host \$host;
        proxy_read_timeout 86400;
    }

    # API endpoints
    location /api {
        root \$base_root;
        rewrite ^/api/(.*)\$ /api/web/index.php/\$1 break;

        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$base_root/api/web/index.php;
        fastcgi_param DOCUMENT_ROOT \$base_root/api/web;
    }

    # Frontend (main site)
    location / {
        root {$frontendPath};
        index index.php;
        try_files \$uri \$uri/ /frontend/web/index.php\$is_args\$args;

        # Static files optimization
        location ~* \\.(?:ico|css|js|gif|jpe?g|png|svg|woff2?|ttf|eot|otf|webp|mp4|mov|zip|rar|pdf)\$ {
            expires 6M;
            access_log off;
            log_not_found off;
        }

        location = / {
            try_files \$uri /frontend/web/index.php\$is_args\$args;
        }

        location ~ ^/assets/.+\\.php(/|\$) {
            deny all;
        }
    }

    # Backend (CRM admin panel)
    location = /crm-panel {
        return 301 /crm-panel/;
    }

    location /crm-panel/ {
        alias {$backendPath}/;

        location = /crm-panel/ {
            try_files \$uri /backend/web/index.php\$is_args\$args;
        }

        try_files \$uri \$uri/ /backend/web/index.php\$is_args\$args;

        location ~ ^/crm-panel/assets/.+\\.php(/|\$) {
            deny all;
        }
    }

    # PHP handling
    location ~ \\.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    # Logs
    error_log {$logPath}/error.log;
    access_log {$logPath}/access.log;
}";

            $confDir = '/etc/nginx/conf.d';
            $configFile = "00-{$domain}.conf";
            $fullConfigPath = "{$confDir}/{$configFile}";
            $parkingConf = "{$confDir}/parking.conf";
            $parkingBackup = "{$confDir}/parking.conf.backup";

            $this->stdout("Creating nginx config: {$configFile}\n");
            $this->stdout("All fastcgi_pass will use: {$socketPath}\n");

            // Parking.conf optimization
            $useParkingOptimization = file_exists($parkingConf);
            if ($useParkingOptimization) {
                $this->stdout("Using parking.conf optimization\n");
                exec("sudo mv {$parkingConf} {$parkingBackup} 2>/dev/null");
            }

            $tempConfig = "/tmp/nginx_{$domain}.conf";
            file_put_contents($tempConfig, $nginxConfig);

            $commands = [
                "sudo cp {$tempConfig} {$fullConfigPath}",
                "sudo nginx -t"
            ];

            foreach ($commands as $command) {
                exec($command . " 2>&1", $output, $returnCode);
                if ($returnCode !== 0) {
                    if ($useParkingOptimization && file_exists($parkingBackup)) {
                        exec("sudo mv {$parkingBackup} {$parkingConf} 2>/dev/null");
                    }
                    throw new \Exception("Nginx config failed: " . implode("\n", $output));
                }
            }

            // Reload nginx
            if ($useParkingOptimization) {
                exec("sudo systemctl reload nginx", $output, $returnCode);
                exec("sudo mv {$parkingBackup} {$parkingConf}");
            } else {
                exec("sudo systemctl reload nginx", $output, $returnCode);
            }

            if ($returnCode !== 0) {
                throw new \Exception("Nginx reload failed: " . implode("\n", $output));
            }

            exec("rm {$tempConfig}");

            $this->stdout("CRM Nginx configuration completed\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("    Config: {$fullConfigPath}\n");
            $this->stdout("    Domain: {$domain}\n");
            $this->stdout("    PHP-FPM: {$socketPath} (auto-detected)\n");
            $this->stdout("    Server IP: {$serverIP}\n");

            return true;

        } catch (\Exception $e) {
            $this->stderr("CRM Nginx configuration error: {$e->getMessage()}\n");
            return false;
        }
    }

    /**
     * Create missing Yii index.php files
     */
    private function createYiiIndexFiles($deploymentPath, $company)
    {
        $this->stdout("Creating Yii index.php files...\n");

        $indexTemplates = [
            'frontend/web/index.php' => $this->generateFrontendIndexPHP($company),
            'backend/web/index.php' => $this->generateBackendIndexPHP($company)
        ];

        foreach ($indexTemplates as $relativePath => $content) {
            $targetPath = $deploymentPath . '/' . $relativePath;
            $targetDir = dirname($targetPath);

            // Create directory if needed
            if (!is_dir($targetDir)) {
                $this->executeSudoCommand("mkdir -p {$targetDir}");
            }

            // Create file only if it doesn't exist
            if (!file_exists($targetPath)) {
                file_put_contents("/tmp/index_temp.php", $content);
                $this->executeSudoCommand("cp /tmp/index_temp.php {$targetPath}");
                $this->executeSudoCommand("chown www-data:www-data {$targetPath}");
                $this->executeSudoCommand("chmod 644 {$targetPath}");
                exec("rm /tmp/index_temp.php");

                $this->stdout("    Created {$relativePath}\n");
            } else {
                $this->stdout("    {$relativePath} already exists\n");
            }
        }
    }

    /**
     * Generate frontend index.php
     */
    private function generateFrontendIndexPHP($company)
    {
        return "<?php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';
require __DIR__ . '/../config/bootstrap.php';

\$config = yii\\helpers\\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/main.php',
    require __DIR__ . '/../../common/config/main-local.php',
    require __DIR__ . '/../../frontend/config/main.php',
    require __DIR__ . '/../../frontend/config/main-local.php'
);

(new yii\\web\\Application(\$config))->run();
";
    }

    /**
     * Generate backend index.php
     */
    private function generateBackendIndexPHP($company)
    {
        return "<?php
defined('YII_DEBUG') or define('YII_DEBUG', false);
defined('YII_ENV') or define('YII_ENV', 'prod');

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../vendor/yiisoft/yii2/Yii.php';
require __DIR__ . '/../../common/config/bootstrap.php';
require __DIR__ . '/../config/bootstrap.php';

\$config = yii\\helpers\\ArrayHelper::merge(
    require __DIR__ . '/../../common/config/main.php',
    require __DIR__ . '/../../common/config/main-local.php',
    require __DIR__ . '/../../backend/config/main.php',
    require __DIR__ . '/../../backend/config/main-local.php'
);

(new yii\\web\\Application(\$config))->run();
";
    }

    /**
     * Auto-detect PHP-FPM socket
     */
    private function detectPHPFPMSocket()
    {
        $this->stdout("Auto-detecting PHP-FPM socket...\n");

        $phpVersion = PHP_VERSION;
        $phpMajorMinor = substr($phpVersion, 0, 3);

        $this->stdout("    Current PHP version: {$phpVersion} (using {$phpMajorMinor})\n");

        $possibleSockets = [
            "/run/php/php{$phpMajorMinor}-fpm.sock",
            "/var/run/php/php{$phpMajorMinor}-fpm.sock",
            "/run/php/php-fpm.sock",
            "/var/run/php/php-fpm.sock",
            "/run/php/php8.2-fpm.sock",
            "/run/php/php8.1-fpm.sock",
            "/run/php/php8.0-fpm.sock",
            "/var/run/php/php8.2-fpm.sock",
            "/var/run/php/php8.1-fpm.sock",
            "/var/run/php/php8.0-fpm.sock",
        ];

        foreach ($possibleSockets as $socket) {
            if (file_exists($socket)) {
                if ($this->isSocketFile($socket)) {
                    if (is_readable($socket)) {
                        $this->stdout("    Found working PHP-FPM socket: {$socket}\n");
                        $perms = substr(sprintf('%o', fileperms($socket)), -4);
                        $this->stdout("    Socket permissions: {$perms}\n");
                        return $socket;
                    }
                }
            }
        }

        $fallbackSocket = "/run/php/php{$phpMajorMinor}-fpm.sock";
        $this->stdout("    No socket found, using fallback: {$fallbackSocket}\n");
        return $fallbackSocket;
    }

    /**
     * Check if file is socket
     */
    private function isSocketFile($filePath)
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $stat = stat($filePath);
        if ($stat === false) {
            return false;
        }

        $fileType = $stat['mode'] & 0170000;
        return $fileType === 0140000;
    }

    /**
     * Validate PHP-FPM socket
     */
    private function validatePHPFPMSocket($socketPath)
    {
        if (!file_exists($socketPath)) {
            throw new \Exception("PHP-FPM socket not found: {$socketPath}");
        }

        if (!$this->isSocketFile($socketPath)) {
            throw new \Exception("File exists but is not a socket: {$socketPath}");
        }

        if (!is_readable($socketPath)) {
            throw new \Exception("PHP-FPM socket not readable: {$socketPath}");
        }

        $this->stdout("    PHP-FPM socket validation passed\n");
        return true;
    }

    private function detectServerIP()
    {
        $output = shell_exec("sudo nginx -T 2>/dev/null | grep -oP 'listen \\K[0-9.]+(?=:80)' | head -1");
        if ($output && filter_var(trim($output), FILTER_VALIDATE_IP)) {
            return trim($output);
        }

        $output = shell_exec("hostname -I | awk '{print \$1}'");
        if ($output && filter_var(trim($output), FILTER_VALIDATE_IP)) {
            return trim($output);
        }

        return "0.0.0.0";
    }

    private function prepareCRMConfigSharedDB($deploymentPath, $company)
    {
        try {
            $configDir = $deploymentPath . '/common/config';

            // main-local.php
            $mainTemplate = $configDir . '/main-local.php.template';
            $mainTarget = $configDir . '/main-local.php';

            if (file_exists($mainTemplate)) {
                $content = file_get_contents($mainTemplate);
                $adminDbConfig = Yii::$app->db;

                $dbName = $this->extractFromDsn($adminDbConfig->dsn, 'dbname') ?: 'crm_employer';

                $replacements = [
                    '{{DB_HOST}}' => $this->extractFromDsn($adminDbConfig->dsn, 'host') ?: 'localhost',
                    '{{DB_NAME}}' => $dbName,
                    '{{MAIL_DB_NAME}}' => $dbName . '-mails',
                    '{{DB_USER}}' => $adminDbConfig->username,
                    '{{DB_PASSWORD}}' => $adminDbConfig->password,
                    '{{WEBSOCKET_SERVER_URL}}' => 'http://127.0.0.1:8901'
                ];

                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                file_put_contents($mainTarget, $content);
                $this->stdout("Created main-local.php with shared DB config\n");
            }

            // params-local.php
            $paramsTemplate = $configDir . '/params-local.php.template';
            $paramsTarget = $configDir . '/params-local.php';

            if (file_exists($paramsTemplate)) {
                $content = file_get_contents($paramsTemplate);
                $companyDomain = $this->generateCompanyURL($company, false);

                $replacements = [
                    '{{COMPANY_ID}}' => $company->id,
                    '{{EMAIL_ENCRYPTION_KEY}}' => base64_encode(random_bytes(32)),
                    '{{ADMIN_EMAIL}}' => "admin@{$companyDomain}",
                    '{{SUPPORT_EMAIL}}' => "support@{$companyDomain}",
                    '{{SENDER_EMAIL}}' => "noreply@{$companyDomain}",
                    '{{SENDER_NAME}}' => $company->name,
                    '{{WEBSOCKET_SECRET}}' => bin2hex(random_bytes(16)),
                    '{{WEBSOCKET_CLIENT_URL}}' => "wss://{$companyDomain}/websocket",
                    '{{SSL_ENABLED}}' => 'true',
                    '{{SSL_CERT_PATH}}' => "/etc/letsencrypt/live/{$companyDomain}/fullchain.pem",
                    '{{SSL_KEY_PATH}}' => "/etc/letsencrypt/live/{$companyDomain}/privkey.pem",
                    '{{COMPANY_DOMAIN}}' => $companyDomain,
                    '{{COMPANY_NAME}}' => addslashes($company->name),
                    '{{FRONTEND_URL}}' => "https://{$companyDomain}",
                    '{{BACKEND_URL}}' => "https://{$companyDomain}/crm-panel",
                ];

                $content = str_replace(array_keys($replacements), array_values($replacements), $content);
                file_put_contents($paramsTarget, $content);
                $this->stdout("Created params-local.php config\n");
            }

            return true;
        } catch (\Exception $e) {
            $this->stderr("Config preparation error: {$e->getMessage()}\n");
            return false;
        }
    }

    private function extractFromDsn($dsn, $param)
    {
        if (preg_match("/{$param}=([^;]+)/", $dsn, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function setCRMPermissionsSudo($deploymentPath)
    {
        try {
            $commands = [
                "chown -R www-data:www-data {$deploymentPath}",
                "find {$deploymentPath} -type d -exec chmod 755 {} \\;",
                "find {$deploymentPath} -type f -exec chmod 644 {} \\;",
                "chmod +x {$deploymentPath}/yii",
                "chmod 755 {$deploymentPath}/backend/web",
                "chmod 755 {$deploymentPath}/frontend/web",
                "chmod -R 777 {$deploymentPath}/backend/runtime",
                "chmod -R 777 {$deploymentPath}/frontend/runtime",
                "chmod -R 777 {$deploymentPath}/backend/web/assets",
                "chmod -R 777 {$deploymentPath}/frontend/web/assets"
            ];

            foreach ($commands as $command) {
                try {
                    $this->executeSudoCommand($command);
                } catch (\Exception $e) {
                    $this->stdout("Warning: {$e->getMessage()}\n");
                }
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function findCRMSourcePath()
    {
        $possiblePaths = [
            dirname(__DIR__, 2) . '/deploy-storage/crm-base',
            '/var/www/templates/crm-base',
        ];

        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                return realpath($path);
            }
        }

        return false;
    }

    private function validateCRMPrerequisites($company)
    {
        $crmSourcePath = $this->findCRMSourcePath();
        if (!$crmSourcePath) {
            $this->stderr("CRM source not found in deploy-storage/crm-base\n");
            return false;
        }

        $this->stdout("CRM source found at: {$crmSourcePath}\n");

        $requiredPaths = ['common/config', 'backend', 'frontend', 'console'];
        foreach ($requiredPaths as $path) {
            if (!is_dir($crmSourcePath . '/' . $path)) {
                $this->stderr("Required CRM directory missing: {$path}\n");
                return false;
            }
        }

        $configTemplates = [
            'common/config/main-local.php.template',
            'common/config/params-local.php.template'
        ];
        foreach ($configTemplates as $template) {
            if (!file_exists($crmSourcePath . '/' . $template)) {
                $this->stderr("Config template missing: {$template}\n");
                return false;
            }
        }

        return true;
    }

    private function copyCRMFiles($sourcePath, $deploymentPath, $company)
    {
        try {
            $this->executeSudoCommand("cp -r {$sourcePath}/* {$deploymentPath}/");

            if (!is_dir($deploymentPath . '/backend')) {
                throw new \Exception("Backend directory not found after copy");
            }

            $runtimeDirs = [
                'backend/runtime', 'frontend/runtime', 'console/runtime',
                'backend/web/assets', 'frontend/web/assets'
            ];

            foreach ($runtimeDirs as $dir) {
                $fullPath = $deploymentPath . '/' . $dir;
                if (!is_dir($fullPath)) {
                    $this->executeSudoCommand("mkdir -p {$fullPath}");
                }
            }

            $this->stdout("CRM files copied successfully\n");
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function installCRMDependencies($deploymentPath)
    {
        $oldDir = getcwd();

        try {
            chdir($deploymentPath);

            $lockFile = $deploymentPath . '/composer.lock';
            if (file_exists($lockFile)) {
                unlink($lockFile);
            }

            $composerCommands = ['composer', '/usr/local/bin/composer', 'php composer.phar'];
            $success = false;

            foreach ($composerCommands as $composerCmd) {
                $which = trim(shell_exec("which {$composerCmd} 2>/dev/null"));
                if (!$which && !file_exists(str_replace('php ', '', $composerCmd))) {
                    continue;
                }

                $cmd = "COMPOSER_ALLOW_SUPERUSER=1 {$composerCmd} install --no-dev --optimize-autoloader --no-interaction --no-progress 2>&1";
                $output = shell_exec($cmd);

                if (is_dir($deploymentPath . '/vendor')) {
                    $success = true;
                    break;
                }
            }

            chdir($oldDir);
            return $success;
        } catch (\Exception $e) {
            chdir($oldDir);
            return false;
        }
    }

    private function updateProgress($deploymentLog, $step, $progress, $isError = false, $message = '', $stackTrace = '')
    {
        try {
            $logData = [
                'step' => $step,
                'progress' => $progress,
                'timestamp' => date('Y-m-d H:i:s'),
                'console_deployment' => true
            ];

            if ($isError) {
                $logData['error'] = true;
                $logData['message'] = $message;
                if ($stackTrace) {
                    $logData['stack_trace'] = $stackTrace;
                }
                $this->stderr("{$step}: {$message}\n");
            } else {
                $this->stdout("[{$progress}%] {$step}\n");
            }

            $deploymentLog->addDetail($logData, LogsCompanyDetails::TYPE_JSON);
        } catch (\Exception $e) {
            $this->stderr("Failed to update progress: {$e->getMessage()}\n");
        }
    }

    /**
     * Generate company URL from company database field
     * @param Companies $company Company model
     * @param bool $withProtocol Include http:// protocol
     * @return string Company URL
     */
    private function generateCompanyURL($company, $withProtocol = true)
    {
        // Use company URL from database if available
        if (!empty($company->url)) {
            $url = $company->url;

            // Remove protocol if it exists
            $url = preg_replace('/^https?:\/\//', '', $url);

            return $withProtocol ? "http://{$url}" : $url;
        }
        return '';
    }

    /**
     * Stop company nginx services (disable configs)
     * @param int $companyId Company ID
     * @return int Exit code (0 = success)
     */
    public function actionStopServices($companyId)
    {
        try {
            $company = Companies::findOne($companyId);
            if (!$company) {
                echo "Error: Company not found\n";
                return 1;
            }

            echo "Disabling nginx configs for company {$companyId}...\n";

            // Get domains
            $domains = $this->getCompanyDomains($company);

            if (empty($domains)) {
                echo "Warning: No domains found for company {$companyId}\n";
                return 0;
            }

            echo "Found domains: " . implode(", ", $domains) . "\n";

            // Apply parking.conf optimization
            if (file_exists('/etc/nginx/conf.d/parking.conf')) {
                rename('/etc/nginx/conf.d/parking.conf', '/etc/nginx/conf.d/parking.conf.backup');
            }

            // Disable each domain config
            $disabledCount = 0;
            foreach ($domains as $domain) {
                $configFile = "/etc/nginx/conf.d/00-{$domain}.conf";
                $disabledFile = "/etc/nginx/conf.d/00-{$domain}.conf.disabled";

                if (file_exists($configFile)) {
                    if (rename($configFile, $disabledFile)) {
                      //  echo "Disabled: {$configFile} -> {$disabledFile}\n";
                        $disabledCount++;
                    } else {
                        echo "Warning: Failed to disable {$configFile}\n";
                    }
                }
            }

            // Reload nginx
            $output = [];
            $returnCode = 0;
            exec('systemctl reload nginx 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo "Nginx reloaded successfully\n";
            } else {
                echo "Warning: Failed to reload nginx: " . implode("\n", $output) . "\n";
            }

            // Restore parking.conf
            if (file_exists('/etc/nginx/conf.d/parking.conf.backup')) {
                rename('/etc/nginx/conf.d/parking.conf.backup', '/etc/nginx/conf.d/parking.conf');
            }

            echo "Company {$companyId} services stopped successfully ({$disabledCount} configs disabled)\n";
            return 0;

        } catch (\Exception $e) {
            echo "Error stopping services: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Start company nginx services (enable configs)
     * @param int $companyId Company ID
     * @return int Exit code (0 = success)
     */
    public function actionStartServices($companyId)
    {
        try {
            $company = Companies::findOne($companyId);
            if (!$company) {
                echo "Error: Company not found\n";
                return 1;
            }

            echo "Enabling nginx configs for company {$companyId}...\n";

            // Get domains
            $domains = $this->getCompanyDomains($company);

            if (empty($domains)) {
                echo "Warning: No domains found for company {$companyId}\n";
                return 0;
            }

            echo "Found domains: " . implode(", ", $domains) . "\n";

            // Apply parking.conf optimization
            if (file_exists('/etc/nginx/conf.d/parking.conf')) {
                rename('/etc/nginx/conf.d/parking.conf', '/etc/nginx/conf.d/parking.conf.backup');
            }

            // Enable each domain config
            $enabledCount = 0;
            foreach ($domains as $domain) {
                $disabledFile = "/etc/nginx/conf.d/00-{$domain}.conf.disabled";
                $configFile = "/etc/nginx/conf.d/00-{$domain}.conf";

                if (file_exists($disabledFile)) {
                    if (rename($disabledFile, $configFile)) {
                        echo "Enabled: {$disabledFile} -> {$configFile}\n";
                        $enabledCount++;
                    } else {
                        echo "Warning: Failed to enable {$disabledFile}\n";
                    }
                }
            }

            // Reload nginx
            $output = [];
            $returnCode = 0;
            exec('systemctl reload nginx 2>&1', $output, $returnCode);

            if ($returnCode === 0) {
                echo "Nginx reloaded successfully\n";
            } else {
                echo "Warning: Failed to reload nginx: " . implode("\n", $output) . "\n";
            }

            // Restore parking.conf
            if (file_exists('/etc/nginx/conf.d/parking.conf.backup')) {
                rename('/etc/nginx/conf.d/parking.conf.backup', '/etc/nginx/conf.d/parking.conf');
            }

            echo "Company {$companyId} services started successfully ({$enabledCount} configs enabled)\n";
            return 0;

        } catch (\Exception $e) {
            echo "Error starting services: " . $e->getMessage() . "\n";
            return 1;
        }
    }

    /**
     * Get all domains for a company
     * @param Companies $company
     * @return array
     */
    private function getCompanyDomains($company)
    {
        $domains = [];

        // Add CRM domain
        if (!empty($company->url)) {
            $domain = preg_replace('/^https?:\/\//', '', $company->url);
            $domain = preg_replace('/\/.*$/', '', $domain); // Remove path
            $domains[] = $domain;
        }
        // Remove duplicates
        return array_unique($domains);
    }

    /**
     * Complete company deletion - removes all traces of the company
     *
     * This action performs a complete cleanup including:
     * - CRM directory
     * - Database records
     * - Nginx configuration files
     * - Administrator user account
     * - Company record itself
     *
     * Usage: php yii company-deploy/delete <company_id>
     *
     * WARNING: This operation is irreversible!
     */
    public function actionDelete($companyId)
    {
        try {
            ini_set('memory_limit', '1024M');
            set_time_limit(0);

            $this->stdout("=== STARTING COMPLETE COMPANY DELETION ===\n", \yii\helpers\Console::FG_RED);
            $this->stdout("Company ID: {$companyId}\n");
            $this->stdout("THIS OPERATION IS IRREVERSIBLE!\n", \yii\helpers\Console::FG_RED);

            $company = Companies::findOne($companyId);
            if (!$company) {
                $this->stderr("ERROR: Company {$companyId} not found\n");
                return 1;
            }

            $this->stdout("Company found: " . $company->name . "\n");
            $this->stdout("CRM URL: " . $company->url . "\n");

            $deleteLog = new LogsCompany();
            $deleteLog->company_id = $companyId;
            $deleteLog->user_id = Yii::$app->user->id ?? 1;
            $deleteLog->action_type = 'DELETE';
            $deleteLog->save(false);

            $this->updateProgress($deleteLog, 'Company deletion started', 5);

            // PHASE 0: Remove SSL certificates (before nginx configs)
            $this->stdout("PHASE 0: Removing SSL certificates...\n");
            $this->updateProgress($deleteLog, 'Removing SSL certificates', 5);
            $this->removeSSLCertificates($company);

            // PHASE 1: Remove nginx configurations completely
            $this->stdout("PHASE 1: Removing nginx configurations...\n");
            $this->updateProgress($deleteLog, 'Removing nginx configurations', 10);
            $this->removeNginxConfigs($company);

            // PHASE 2: Remove CRM  directories
            $this->stdout("PHASE 2: Removing CRM directories...\n");
            $this->updateProgress($deleteLog, 'Removing deployment directories', 20);
            $this->removeCompanyDirectories($company);

            // PHASE 3: Final nginx configuration cleanup
            $this->stdout("PHASE 3: Final nginx cleanup...\n");
            $this->updateProgress($deleteLog, 'Final nginx cleanup', 55);
            $this->finalNginxCleanup($company);

            // PHASE 4: Clean database records
            $this->stdout("PHASE 4: Cleaning database records...\n");
            $this->updateProgress($deleteLog, 'Cleaning database records', 70);
            $deletedRecords = $this->cleanupDatabaseRecords($company);

            // PHASE 5: Remove company administrator
            $this->stdout("PHASE 5: Removing company administrator...\n");
            $this->updateProgress($deleteLog, 'Removing company administrator', 85);
            $this->removeCompanyAdministrator($company);

            // PHASE 6: Remove company record
            $this->stdout("PHASE 6: Removing company record...\n");
            $this->updateProgress($deleteLog, 'Removing company record', 95);
            $companyName = $company->name;
            $company->delete();

            // Final logging
            $this->updateProgress($deleteLog, 'Company deletion completed successfully', 100);
            $this->logCompanyDeletion($companyId, $companyName, $deletedRecords);

            $this->stdout("=== COMPANY DELETION COMPLETED SUCCESSFULLY ===\n", \yii\helpers\Console::FG_GREEN);
            $this->stdout("Company '{$companyName}' (ID: {$companyId}) has been completely removed.\n");
            $this->stdout("Total database records deleted: " . array_sum($deletedRecords) . "\n");

            return 0;

        } catch (\Exception $e) {
            $this->stderr("CRITICAL ERROR during deletion: " . $e->getMessage() . "\n");
            if (isset($deleteLog)) {
                $this->updateProgress($deleteLog, 'ERROR: ' . $e->getMessage(), null, true);
            }
            return 1;
        }
    }

    /**
     * Remove nginx configuration files
     */
    private function removeNginxConfigs($company)
    {
        try {
            $domains = $this->getCompanyDomains($company);

            if (empty($domains)) {
                $this->stdout("  No nginx configs to remove\n");
                return;
            }

            $confDir = '/etc/nginx/conf.d';
            $parkingConf = "{$confDir}/parking.conf";
            $parkingBackup = "{$confDir}/parking.conf.backup";

            // Apply parking.conf optimization for proper nginx operation
            $useParkingOptimization = file_exists($parkingConf);
            if ($useParkingOptimization) {
                $this->stdout("  Using parking.conf optimization during deletion\n");
                exec("sudo mv {$parkingConf} {$parkingBackup} 2>/dev/null");
            }

            foreach ($domains as $domain) {
                $configFile = "/etc/nginx/conf.d/00-{$domain}.conf";
                $disabledFile = "/etc/nginx/conf.d/00-{$domain}.conf.disabled";

                // Completely delete configs
                foreach ([$configFile, $disabledFile] as $file) {
                    if (file_exists($file)) {
                        exec("sudo rm -f {$file}", $output, $returnCode);
                        if ($returnCode === 0) {
                            $this->stdout("    Deleted: {$file}\n");
                        } else {
                            $this->stdout("    Warning: Failed to delete {$file}\n");
                        }
                    }
                }
            }

            // Reload nginx
            exec('sudo systemctl reload nginx 2>&1', $output, $returnCode);
            if ($returnCode === 0) {
                $this->stdout("  Nginx configurations deleted and reloaded\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("  Warning: Failed to reload nginx after deletion\n", \yii\helpers\Console::FG_YELLOW);
            }

            // Restore parking.conf
            if (file_exists($parkingBackup)) {
                exec("sudo mv {$parkingBackup} {$parkingConf} 2>/dev/null");
                $this->stdout("  Restored parking.conf\n");
            }

        } catch (\Exception $e) {
            $this->stdout("  Warning: Error removing nginx configs: {$e->getMessage()}\n");
        }
    }

    /**
     * Remove company directories with backup creation
     */
    private function removeCompanyDirectories($company)
    {
        $directories = [];

        // CRM directory
        if ($company->url) {
            $domain = parse_url($company->url, PHP_URL_HOST);
            if ($domain) {
                $directories[] = [
                    'path' => "/var/www/sites/{$domain}",
                    'type' => 'CRM'
                ];
            }
        }


        foreach ($directories as $dir) {
            if (is_dir($dir['path'])) {
                // Create backup before deletion
                $backupPath = "/tmp/company_{$company->id}_{$dir['type']}_backup_" . date('Y-m-d_H-i-s');
                exec("sudo cp -r {$dir['path']} {$backupPath} 2>/dev/null", $output, $returnCode);

                if ($returnCode === 0) {
                    $this->stdout("    Backup created: {$backupPath}\n");
                }

                // Completely remove directory
                exec("sudo rm -rf {$dir['path']}", $output, $returnCode);

                if ($returnCode === 0) {
                    $this->stdout("    Deleted: {$dir['path']}\n", \yii\helpers\Console::FG_GREEN);
                } else {
                    $this->stdout("    Warning: Failed to delete {$dir['path']}\n", \yii\helpers\Console::FG_YELLOW);
                }
            }
        }
    }

    /**
     * Final nginx configuration cleanup
     */
    private function finalNginxCleanup($company)
    {
        try {
            $domains = $this->getCompanyDomains($company);

            foreach ($domains as $domain) {
                // Find and remove ALL configs related to the domain
                $patterns = [
                    "/etc/nginx/conf.d/*{$domain}*",
                    "/etc/nginx/sites-available/*{$domain}*",
                    "/etc/nginx/sites-enabled/*{$domain}*"
                ];

                foreach ($patterns as $pattern) {
                    $baseDir = dirname($pattern);
                    $filePattern = basename($pattern);
                    exec("sudo find {$baseDir} -name '{$filePattern}' -delete 2>/dev/null");
                }
            }

            // Final nginx reload
            exec('sudo systemctl reload nginx 2>&1', $output, $returnCode);
            if ($returnCode === 0) {
                $this->stdout("    Final nginx cleanup completed\n", \yii\helpers\Console::FG_GREEN);
            }

        } catch (\Exception $e) {
            $this->stdout("    Warning: Error in final nginx cleanup: {$e->getMessage()}\n", \yii\helpers\Console::FG_YELLOW);
        }
    }

    /**
     * Completely clean database records related to the company
     */
    private function cleanupDatabaseRecords($company)
    {
        $deletedRecords = [
            'packages' => 0,
            'tasks' => 0,
            'urgent_calls' => 0,
            'chats' => 0,
            'chat_participants' => 0,
            'chat_messages' => 0,
            'users' => 0,
            'deployment_logs' => 0
        ];

        try {
            // Delete packages
            if (class_exists('common\\models\\Packages')) {
                $packagesCount = \common\models\Packages::deleteAll(['company_id' => $company->id]);
                $deletedRecords['packages'] = $packagesCount;
                $this->stdout("    Deleted {$packagesCount} packages\n");
            }

            // Delete tasks
            if (class_exists('common\\models\\Tasks')) {
                $tasksCount = \common\models\Tasks::deleteAll(['company_id' => $company->id]);
                $deletedRecords['tasks'] = $tasksCount;
                $this->stdout("    Deleted {$tasksCount} tasks\n");
            }

            // Delete urgent calls
            if (class_exists('common\\models\\UrgentCall')) {
                $urgentCallsCount = \common\models\UrgentCall::deleteAll(['company_id' => $company->id]);
                $deletedRecords['urgent_calls'] = $urgentCallsCount;
                $this->stdout("    Deleted {$urgentCallsCount} urgent calls\n");
            }

            // Delete chats and related data
            if (class_exists('backend\\models\\Chat')) {
                $chats = \backend\models\Chat::find()->where(['company_id' => $company->id])->all();

                foreach ($chats as $chat) {
                    // Delete chat messages
                    if (class_exists('backend\\models\\ChatMessage')) {
                        $messagesCount = \backend\models\ChatMessage::deleteAll(['chat_id' => $chat->id]);
                        $deletedRecords['chat_messages'] += $messagesCount;
                    }

                    // Delete chat participants
                    if (class_exists('backend\\models\\ChatParticipant')) {
                        $participantsCount = \backend\models\ChatParticipant::deleteAll(['chat_id' => $chat->id]);
                        $deletedRecords['chat_participants'] += $participantsCount;
                    }

                    // Delete the chat
                    $chat->delete();
                    $deletedRecords['chats']++;
                }

                $this->stdout("    Deleted {$deletedRecords['chats']} chats with {$deletedRecords['chat_messages']} messages and {$deletedRecords['chat_participants']} participants\n");
            }

            // Delete company users (except administrator - will delete separately)
            if (class_exists('common\\models\\User')) {
                $usersCount = \common\models\User::deleteAll(['and', ['company_id' => $company->id], ['!=', 'id', $company->administrator_id]]);
                $deletedRecords['users'] = $usersCount;
                $this->stdout("    Deleted {$usersCount} company users\n");
            }

            /*
            $deploymentsCount = LogsCompany::deleteAll(['and', ['company_id' => $company->id], ['!=', 'action_type', 'DELETE']]);
            $deletedRecords['deployment_logs'] = $deploymentsCount;
            $this->stdout("    Deleted {$deploymentsCount} deployment logs\n");*/

        } catch (\Exception $e) {
            $this->stdout("    Warning: Error cleaning database records: {$e->getMessage()}\n", \yii\helpers\Console::FG_YELLOW);
        }

        return $deletedRecords;
    }

    /**
     * Completely remove company administrator
     */
    private function removeCompanyAdministrator($company)
    {
        try {
            if (!$company->administrator_id) {
                $this->stdout("    No administrator assigned\n");
                return;
            }

            $admin = \common\models\User::findOne($company->administrator_id);
            if (!$admin) {
                $this->stdout("    Administrator not found\n");
                return;
            }

            $adminEmail = $admin->email;

            if (Yii::$app->authManager) {
                $assignments = Yii::$app->authManager->getAssignments($admin->id);
                foreach ($assignments as $assignment) {
                    Yii::$app->authManager->revoke($assignment, $admin->id);
                }
                $this->stdout("    Removed administrator roles\n");
            }

            if ($admin->delete()) {
                $this->stdout("    Deleted administrator: {$adminEmail}\n", \yii\helpers\Console::FG_GREEN);
            } else {
                $this->stdout("    Warning: Failed to delete administrator\n", \yii\helpers\Console::FG_YELLOW);
            }

        } catch (\Exception $e) {
            $this->stdout("    Warning: Error removing administrator: {$e->getMessage()}\n", \yii\helpers\Console::FG_YELLOW);
        }
    }

    /**
     * Log company deletion completion
     */
    private function logCompanyDeletion($companyId, $companyName, $deletedRecords)
    {
        try {
            $adminLog = new LogsAdmin();
            $adminLog->user_id = Yii::$app->user->id ?? 1;
            $adminLog->action = LogsAdmin::ACTION_COMPANY_DELETE;
            $adminLog->addDetail('deleted_company_id', $companyId);
            $adminLog->addDetail('deleted_company_name', $companyName);
            $adminLog->addDetail('deleted_records', $deletedRecords);
            $adminLog->addDetail('total_records_deleted', array_sum($deletedRecords));
            $adminLog->addDetail('deletion_timestamp', date('Y-m-d H:i:s'));
            $adminLog->addDetail('deletion_method', 'console_script');
            $adminLog->save();

            $this->stdout("Final deletion logged in admin logs\n", \yii\helpers\Console::FG_GREEN);

        } catch (\Exception $e) {
            $this->stdout("Warning: Failed to log final deletion: {$e->getMessage()}\n", \yii\helpers\Console::FG_YELLOW);
        }
    }

    /**
     * Update company configuration after domain change
     * Usage: php yii company-deploy/update-config <company_id> [log_id] [user_id]
     */
    public function actionUpdateConfig($companyId, $logId = null, $userId = null)
    {
        try {
            $this->stdout("=== CONFIGURATION UPDATE STARTED ===\n");
            $this->stdout("Company ID: {$companyId}\n");

            $company = Companies::findOne($companyId);
            if (!$company) {
                $this->stderr("ERROR: Company {$companyId} not found\n");
                return 1;
            }

            // Step 1: Validate
            $this->stdout("Validating configuration...\n");

            $oldDomain = Companies::extractDomain($company->previous_url);
            $newDomain = Companies::extractDomain($company->url);

            if (!$oldDomain || !$newDomain) {
                $this->stderr("Invalid domain configuration\n");
                return 1;
            }

            $this->stdout("Old domain: {$oldDomain}\n");
            $this->stdout("New domain: {$newDomain}\n");

            // Step 2: Check if directory rename needed
            $oldPath = "/var/www/sites/{$oldDomain}";
            $newPath = "/var/www/sites/{$newDomain}";

            if ($oldDomain !== $newDomain && is_dir($oldPath)) {
                $this->stdout("Renaming deployment directory...\n");
                $this->executeSudoCommand("mv {$oldPath} {$newPath}");
                $this->stdout("Directory renamed: {$oldPath} -> {$newPath}\n");
            }

            $deploymentPath = is_dir($newPath) ? $newPath : $oldPath;

            // Step 3: Update CRM config files
            $this->stdout("Updating CRM configuration files...\n");

            $paramsFile = "{$deploymentPath}/common/config/params-local.php";
            if (file_exists($paramsFile)) {
                $content = file_get_contents($paramsFile);

                // Replace old domain with new domain
                $content = str_replace($oldDomain, $newDomain, $content);

                // Update URLs
                $content = preg_replace(
                    "/('frontendUrl'\s*=>\s*')[^']+/",
                    "\${1}https://{$newDomain}",
                    $content
                );
                $content = preg_replace(
                    "/('backendUrl'\s*=>\s*')[^']+/",
                    "\${1}https://{$newDomain}/crm-panel",
                    $content
                );

                file_put_contents($paramsFile, $content);
                $this->stdout("Updated params-local.php\n");
            }

            // Step 4: Handle nginx configuration with parking.conf optimization
            $this->stdout("Updating nginx configuration...\n");

            $confDir = '/etc/nginx/conf.d';
            $parkingConf = "{$confDir}/parking.conf";
            $parkingBackup = "{$confDir}/parking.conf.backup";

            // Check if old config was disabled (company stopped)
            $oldConfActive = "{$confDir}/00-{$oldDomain}.conf";
            $oldConfDisabled = "{$confDir}/00-{$oldDomain}.conf.disabled";
            $wasDisabled = file_exists($oldConfDisabled);

            // Apply parking.conf optimization
            $useParkingOptimization = file_exists($parkingConf);
            if ($useParkingOptimization) {
                $this->stdout("Using parking.conf optimization\n");
                exec("sudo mv {$parkingConf} {$parkingBackup} 2>/dev/null");
            }

            try {
                // Remove old configs
                foreach ([$oldConfActive, $oldConfDisabled] as $oldConf) {
                    if (file_exists($oldConf)) {
                        $this->executeSudoCommand("rm -f {$oldConf}");
                        $this->stdout("Removed old config: {$oldConf}\n");
                    }
                }

                // Remove old SSL certificate
                if ($oldDomain !== $newDomain) {
                    $this->stdout("Removing old SSL certificate...\n");
                    $this->removeSSLCertificateForDomain($oldDomain);
                }

                // Create HTTP nginx config first
                $this->stdout("Creating HTTP nginx configuration...\n");
                if (!$this->configureCRMNginxFixed($company, $deploymentPath)) {
                    throw new \Exception('Could not create nginx config');
                }

                // Obtain new SSL certificate
                $this->stdout("Obtaining SSL certificate for new domain...\n");
                $sslResult = $this->setupSSLForDomain($company, $deploymentPath);

                if ($sslResult['success']) {
                    $this->stdout("SSL certificate obtained, configuring HTTPS...\n");
                    $this->configureCRMNginxWithSSL($company, $deploymentPath, $sslResult);
                } else {
                    $this->stdout("SSL failed, site available via HTTP: {$sslResult['message']}\n");
                }

                // If company was stopped, disable the new config too
                if ($wasDisabled || $company->status === Companies::STATUS_STOPPED) {
                    $newConfActive = "{$confDir}/00-{$newDomain}.conf";
                    $newConfDisabled = "{$confDir}/00-{$newDomain}.conf.disabled";

                    if (file_exists($newConfActive)) {
                        $this->executeSudoCommand("mv {$newConfActive} {$newConfDisabled}");
                        $this->stdout("Config disabled (company was stopped)\n");
                    }
                }

                // Reload nginx
                exec('sudo systemctl reload nginx 2>&1', $output, $returnCode);
                if ($returnCode !== 0) {
                    $this->stdout("Warning: Nginx reload returned code {$returnCode}\n");
                }

            } finally {
                // Always restore parking.conf
                if ($useParkingOptimization && file_exists($parkingBackup)) {
                    exec("sudo mv {$parkingBackup} {$parkingConf} 2>/dev/null");
                    $this->stdout("Restored parking.conf\n");
                }
            }

            // Step 5: Update Yii index files
            $this->stdout("Updating index files...\n");
            $this->createYiiIndexFiles($deploymentPath, $company);

            // Step 6: Mark config as updated
            $this->stdout("Finalizing...\n");
            $company->markConfigUpdated();

            $this->stdout("=== CONFIGURATION UPDATE COMPLETED ===\n");
            $this->stdout("New URL: {$company->url}\n");

            return 0;

        } catch (\Exception $e) {
            $this->stderr("Configuration update failed: " . $e->getMessage() . "\n");
            return 1;
        }
    }

    /**
     * Obtain Let's Encrypt SSL certificate
     * @param string $domain
     * @param string $webRoot Path to web root for ACME validation
     * @param string $adminEmail
     * @return array ['success' => bool, 'cert_path' => string, 'key_path' => string]
     */
    private function obtainLetsEncryptCertificate($domain, $webRoot, $adminEmail)
    {
        $this->stdout("  Obtaining SSL certificate for: {$domain}\n");

        $certPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
        $keyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";

        // Check existing certificate
        if (file_exists($certPath) && file_exists($keyPath)) {
            $this->stdout("    Certificate already exists\n");

            // Check expiration (renew if < 30 days)
            $certContent = file_get_contents($certPath);
            $certInfo = openssl_x509_parse($certContent);

            if ($certInfo && isset($certInfo['validTo_time_t'])) {
                $daysLeft = ($certInfo['validTo_time_t'] - time()) / 86400;

                if ($daysLeft > 30) {
                    $this->stdout("    Certificate valid for " . round($daysLeft) . " days\n", \yii\helpers\Console::FG_GREEN);
                    return [
                        'success' => true,
                        'cert_path' => $certPath,
                        'key_path' => $keyPath,
                        'existing' => true
                    ];
                }
                $this->stdout("    Certificate expires in " . round($daysLeft) . " days, renewing...\n", \yii\helpers\Console::FG_YELLOW);
            }
        }

        // Create ACME challenge directory
        $acmeDir = "{$webRoot}/.well-known/acme-challenge";
        if (!is_dir($acmeDir)) {
            $this->executeSudoCommand("mkdir -p {$acmeDir}");
            $this->executeSudoCommand("chown -R www-data:www-data " . dirname(dirname($acmeDir)));
        }

        // Try webroot method first
        $this->stdout("    Trying webroot validation...\n");

        $certbotCmd = "certbot certonly " .
            "--webroot " .
            "--webroot-path={$webRoot} " .
            "-d {$domain} " .
            "--email {$adminEmail} " .
            "--agree-tos " .
            "--non-interactive " .
            "--keep-until-expiring";

        exec("sudo {$certbotCmd} 2>&1", $output, $returnCode);

        if ($returnCode !== 0) {
            $errorOutput = implode("\n", $output);
            $this->stdout("    Webroot failed: {$errorOutput}\n", \yii\helpers\Console::FG_YELLOW);

            // Try standalone (requires stopping nginx temporarily)
            $this->stdout("    Trying standalone validation...\n");

            exec("sudo systemctl stop nginx");

            $standaloneCmd = "certbot certonly " .
                "--standalone " .
                "-d {$domain} " .
                "--email {$adminEmail} " .
                "--agree-tos " .
                "--non-interactive " .
                "--keep-until-expiring";

            exec("sudo {$standaloneCmd} 2>&1", $output, $returnCode);

            exec("sudo systemctl start nginx");

            if ($returnCode !== 0) {
                throw new \Exception("SSL certificate failed: " . implode("\n", $output));
            }
        }

        // Verify certificate files exist
        if (!file_exists($certPath) || !file_exists($keyPath)) {
            throw new \Exception("Certificate files not found after certbot");
        }

        return [
            'success' => true,
            'cert_path' => $certPath,
            'key_path' => $keyPath,
            'existing' => false
        ];
    }

    /**
     * Check and install SSL prerequisites
     * @return array ['ready' => bool, 'message' => string, 'installed' => array]
     */
    private function ensureSSLPrerequisites()
    {
        $this->stdout("Checking SSL prerequisites...\n");

        $installed = [];
        $errors = [];

        // 1. Check certbot
        exec("which certbot 2>/dev/null", $output, $returnCode);

        if ($returnCode !== 0) {
            $this->stdout("  Certbot not found, installing...\n");

            // Try to install certbot
            exec("sudo apt-get update -qq 2>&1", $output, $returnCode);
            exec("sudo apt-get install -y certbot 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                $errors[] = "Failed to install certbot: " . implode("\n", $output);
            } else {
                $installed[] = 'certbot';
                $this->stdout("  Certbot installed successfully\n", \yii\helpers\Console::FG_GREEN);
            }
        } else {
            $this->stdout("  Certbot: OK\n", \yii\helpers\Console::FG_GREEN);
        }

        // 2. Check certbot version
        exec("certbot --version 2>&1", $versionOutput, $returnCode);
        if ($returnCode === 0) {
            $this->stdout("  Version: " . implode(" ", $versionOutput) . "\n");
        }

        // 3. Check/create sudoers file for certbot
        $sudoersFile = '/etc/sudoers.d/certbot-deploy';
        $sudoersContent = "# Certbot deployment permissions for www-data
# Created by CRM deployment script

www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot
www-data ALL=(ALL) NOPASSWD: /usr/bin/certbot certonly *
www-data ALL=(ALL) NOPASSWD: /bin/systemctl stop nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl start nginx
www-data ALL=(ALL) NOPASSWD: /bin/systemctl reload nginx
";

        if (!file_exists($sudoersFile)) {
            $this->stdout("  Creating sudoers file for certbot...\n");

            // Write to temp file first
            $tempFile = '/tmp/certbot-deploy-sudoers';
            file_put_contents($tempFile, $sudoersContent);

            // Validate sudoers syntax
            exec("sudo visudo -c -f {$tempFile} 2>&1", $output, $returnCode);

            if ($returnCode === 0) {
                exec("sudo cp {$tempFile} {$sudoersFile} && sudo chmod 440 {$sudoersFile}", $output, $returnCode);

                if ($returnCode === 0) {
                    $installed[] = 'sudoers';
                    $this->stdout("  Sudoers file created successfully\n", \yii\helpers\Console::FG_GREEN);
                } else {
                    $errors[] = "Failed to copy sudoers file";
                }
            } else {
                $errors[] = "Invalid sudoers syntax";
            }

            exec("rm -f {$tempFile}");
        } else {
            $this->stdout("  Sudoers file: OK\n", \yii\helpers\Console::FG_GREEN);
        }

        // 4. Test sudo access for certbot
        exec("sudo -n certbot --version 2>&1", $output, $returnCode);
        if ($returnCode !== 0) {
            $errors[] = "Sudo access for certbot not working. Manual configuration may be required.";
            $this->stdout("  Warning: Sudo test failed\n", \yii\helpers\Console::FG_YELLOW);
        } else {
            $this->stdout("  Sudo access: OK\n", \yii\helpers\Console::FG_GREEN);
        }

        // 5. Check if ports 80/443 are available (for standalone mode)
        exec("sudo lsof -i :80 2>/dev/null | grep -v nginx", $output, $returnCode);
        if ($returnCode === 0 && !empty($output)) {
            $this->stdout("  Warning: Port 80 may have conflicting services\n", \yii\helpers\Console::FG_YELLOW);
        }

        // 6. Setup auto-renewal cron if not exists
        $cronFile = '/etc/cron.d/certbot-renew';
        if (!file_exists($cronFile)) {
            $this->stdout("  Setting up auto-renewal cron...\n");

            $cronContent = "# Certbot auto-renewal (twice daily)\n0 3,15 * * * root certbot renew --quiet --post-hook 'systemctl reload nginx'\n";

            file_put_contents('/tmp/certbot-renew-cron', $cronContent);
            exec("sudo cp /tmp/certbot-renew-cron {$cronFile} && sudo chmod 644 {$cronFile}", $output, $returnCode);
            exec("rm -f /tmp/certbot-renew-cron");

            if ($returnCode === 0) {
                $installed[] = 'cron';
                $this->stdout("  Auto-renewal cron created\n", \yii\helpers\Console::FG_GREEN);
            }
        } else {
            $this->stdout("  Auto-renewal cron: OK\n", \yii\helpers\Console::FG_GREEN);
        }

        // 7. Ensure www-data can read certificates
        $this->stdout("  Setting certificate directory permissions...\n");
        exec("sudo chmod 755 /etc/letsencrypt/live/ 2>/dev/null");
        exec("sudo chmod 755 /etc/letsencrypt/archive/ 2>/dev/null");

        $ready = empty($errors);

        return [
            'ready' => $ready,
            'message' => $ready ? 'SSL prerequisites ready' : implode('; ', $errors),
            'installed' => $installed,
            'errors' => $errors
        ];
    }

    /**
     * Remove SSL certificates for company domains
     * @param Companies $company
     */
    private function removeSSLCertificates($company)
    {
        $domains = $this->getCompanyDomains($company);

        foreach ($domains as $domain) {
            $certPath = "/etc/letsencrypt/live/{$domain}";

            if (is_dir($certPath)) {
                $this->stdout("  Removing SSL certificate for: {$domain}\n");

                // Certbot delete command (cleanest way)
                exec("sudo certbot delete --cert-name {$domain} --non-interactive 2>&1", $output, $returnCode);

                if ($returnCode === 0) {
                    $this->stdout("    Certificate removed\n", \yii\helpers\Console::FG_GREEN);
                } else {
                    // Fallback: manual removal
                    $this->stdout("    Certbot delete failed, removing manually...\n");
                    exec("sudo rm -rf /etc/letsencrypt/live/{$domain}");
                    exec("sudo rm -rf /etc/letsencrypt/archive/{$domain}");
                    exec("sudo rm -f /etc/letsencrypt/renewal/{$domain}.conf");
                }
            } else {
                $this->stdout("  No SSL certificate found for: {$domain}\n");
            }
        }
    }

    /**
     * Setup SSL for domain
     * @param Companies $company
     * @param string $deploymentPath
     * @return array ['success' => bool, 'cert_path' => string, 'key_path' => string, 'message' => string]
     */
    private function setupSSLForDomain($company, $deploymentPath)
    {
        try {
            // Check SSL prerequisites
            $prereq = $this->ensureSSLPrerequisites();
            if (!$prereq['ready']) {
                return [
                    'success' => false,
                    'message' => 'SSL prerequisites not met: ' . $prereq['message']
                ];
            }

            $domain = Companies::extractDomain($company->url);
            if (!$domain) {
                return [
                    'success' => false,
                    'message' => 'Invalid domain in company URL'
                ];
            }

            // Web root for ACME challenge
            $webRoot = $deploymentPath . '/frontend/web';

            // Admin email for Let's Encrypt
            $adminEmail = $company->administrator ? $company->administrator->email : "admin@{$domain}";

            // Obtain certificate
            $result = $this->obtainLetsEncryptCertificate($domain, $webRoot, $adminEmail);

            return $result;

        } catch (\Exception $e) {
            $this->stderr("SSL setup error: " . $e->getMessage() . "\n");
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Configure Nginx with SSL/HTTPS
     * @param Companies $company
     * @param string $deploymentPath
     * @param array $sslResult Result from obtainLetsEncryptCertificate()
     * @return bool
     */
    private function configureCRMNginxWithSSL($company, $deploymentPath, $sslResult)
    {
        try {
            $domain = Companies::extractDomain($company->url);
            $this->stdout("Configuring HTTPS for: {$domain}\n");

            $backendPath = $deploymentPath . '/backend/web';
            $frontendPath = $deploymentPath . '/frontend/web';
            $logPath = $deploymentPath . '/logs';

            $serverIP = $this->detectServerIP();
            $socketPath = $this->detectPHPFPMSocket();

            $certPath = $sslResult['cert_path'];
            $keyPath = $sslResult['key_path'];

            $nginxConfig = "# CRM Config for {$domain} - {$company->name} (HTTPS)
# Created: " . date('Y-m-d H:i:s') . "
# SSL: Let's Encrypt

# HTTP -> HTTPS redirect
server {
    listen {$serverIP}:80;
    server_name {$domain};
    
    # ACME challenge for certificate renewal
    location /.well-known/acme-challenge/ {
        root {$frontendPath};
    }
    
    location / {
        return 301 https://\$server_name\$request_uri;
    }
}

# HTTPS server
server {
    listen {$serverIP}:443 ssl;
    server_name {$domain};

    # SSL Configuration
    ssl_certificate {$certPath};
    ssl_certificate_key {$keyPath};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_timeout 1d;    
    ssl_stapling on;
    ssl_stapling_verify on;

    # Security headers
    add_header Strict-Transport-Security \"max-age=63072000\" always;
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;

    charset utf-8;

    gzip on;
    gzip_proxied expired no-cache no-store private auth;
    gzip_types text/css text/xml application/javascript text/plain application/json image/svg+xml image/x-icon;
    gzip_comp_level 1;

    set \$base_root {$deploymentPath};
    root \$base_root;
    index index.php index.html;

    disable_symlinks if_not_owner from=\$base_root;

    # Websocket support (WSS)
    location /websocket {
        proxy_pass http://127.0.0.1:8901;
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"Upgrade\";
        proxy_set_header Host \$host;
        proxy_read_timeout 86400;
    }

    # API endpoints
    location /api {
        root \$base_root;
        rewrite ^/api/(.*)\$ /api/web/index.php/\$1 break;

        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$base_root/api/web/index.php;
        fastcgi_param DOCUMENT_ROOT \$base_root/api/web;
    }

    # Frontend (main site)
    location / {
        root {$frontendPath};
        index index.php;
        try_files \$uri \$uri/ /frontend/web/index.php\$is_args\$args;

        location ~* \\.(?:ico|css|js|gif|jpe?g|png|svg|woff2?|ttf|eot|otf|webp|mp4|mov|zip|rar|pdf)\$ {
            expires 6M;
            access_log off;
            log_not_found off;
        }

        location = / {
            try_files \$uri /frontend/web/index.php\$is_args\$args;
        }

        location ~ ^/assets/.+\\.php(/|\$) {
            deny all;
        }
    }

    # Backend (CRM admin panel)
    location = /crm-panel {
        return 301 /crm-panel/;
    }

    location /crm-panel/ {
        alias {$backendPath}/;

        location = /crm-panel/ {
            try_files \$uri /backend/web/index.php\$is_args\$args;
        }

        try_files \$uri \$uri/ /backend/web/index.php\$is_args\$args;

        location ~ ^/crm-panel/assets/.+\\.php(/|\$) {
            deny all;
        }
    }

    # PHP handling
    location ~ \\.php\$ {
        include /etc/nginx/fastcgi_params;
        fastcgi_pass unix:{$socketPath};
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    # Logs
    error_log {$logPath}/error.log;
    access_log {$logPath}/access.log;
}";

            $confDir = '/etc/nginx/conf.d';
            $configFile = "00-{$domain}.conf";
            $fullConfigPath = "{$confDir}/{$configFile}";
            $parkingConf = "{$confDir}/parking.conf";
            $parkingBackup = "{$confDir}/parking.conf.backup";

            // Parking.conf optimization
            $useParkingOptimization = file_exists($parkingConf);
            if ($useParkingOptimization) {
                exec("sudo mv {$parkingConf} {$parkingBackup} 2>/dev/null");
            }

            $tempConfig = "/tmp/nginx_{$domain}_ssl.conf";
            file_put_contents($tempConfig, $nginxConfig);

            $commands = [
                "sudo cp {$tempConfig} {$fullConfigPath}",
                "sudo nginx -t"
            ];

            foreach ($commands as $command) {
                exec($command . " 2>&1", $output, $returnCode);
                if ($returnCode !== 0) {
                    if ($useParkingOptimization && file_exists($parkingBackup)) {
                        exec("sudo mv {$parkingBackup} {$parkingConf} 2>/dev/null");
                    }
                    throw new \Exception("Nginx HTTPS config failed: " . implode("\n", $output));
                }
            }

            // Reload nginx
            exec("sudo systemctl reload nginx", $output, $returnCode);

            // Restore parking.conf
            if ($useParkingOptimization && file_exists($parkingBackup)) {
                exec("sudo mv {$parkingBackup} {$parkingConf} 2>/dev/null");
            }

            exec("rm {$tempConfig}");

            if ($returnCode !== 0) {
                throw new \Exception("Nginx reload failed");
            }

            $this->stdout("HTTPS configuration completed for {$domain}\n", \yii\helpers\Console::FG_GREEN);
            return true;

        } catch (\Exception $e) {
            $this->stderr("HTTPS configuration error: {$e->getMessage()}\n");
            return false;
        }
    }

    /**
     * Remove SSL certificate for specific domain
     * @param string $domain
     */
    private function removeSSLCertificateForDomain($domain)
    {
        $certPath = "/etc/letsencrypt/live/{$domain}";

        if (is_dir($certPath)) {
            $this->stdout("  Removing SSL certificate for: {$domain}\n");

            exec("sudo certbot delete --cert-name {$domain} --non-interactive 2>&1", $output, $returnCode);

            if ($returnCode !== 0) {
                // Fallback: manual removal
                exec("sudo rm -rf /etc/letsencrypt/live/{$domain}");
                exec("sudo rm -rf /etc/letsencrypt/archive/{$domain}");
                exec("sudo rm -f /etc/letsencrypt/renewal/{$domain}.conf");
            }

            $this->stdout("  Certificate removed\n");
        }
    }


}