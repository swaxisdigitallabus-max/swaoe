<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * SWAOE v4.1 — DISTRIBUTED AUTOMATION ORCHESTRATION ENGINE
 * Enhanced with PWTAE principles: Stateless sessions, smart selectors, task builder
 * All-in-One Bootstrap Installer
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * This installer generates a complete distributed workflow automation system:
 * - Control Plane API (PHP) with multi-user support
 * - Distributed Worker Daemon (PHP CLI) OR Cron-safe stateless mode
 * - Playwright Browser Execution VM (Node.js) with Desktop/Mobile modes
 * - Smart Selector Engine (DOM detection + coordinate fallback)
 * - SQLite Database (users, sessions, tasks, logs)
 * - Redis Queue (job coordination)
 * - PWA Control Dashboard with Visual Task Builder (UI)
 * - Docker/Deployment Configs
 *
 * Usage: php install.php [--skip-docker] [--cron-mode]
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class SWAOEInstaller {
    
    private $baseDir;
    private $timestamp;
    private $config = [];
    
    public function __construct() {
        $this->baseDir = dirname(__FILE__);
        $this->timestamp = date('Y-m-d H:i:s');
        $this->config = [
            'db_path' => $this->baseDir . '/data/swaoe_cluster.sqlite',
            'redis_host' => 'localhost',
            'redis_port' => 6379,
            'redis_timeout' => 0,
            'worker_count' => 4,
            'max_retries' => 3,
            'lease_timeout' => 300,
            'cycle_timeout' => 600,
            'api_port' => 8080,
        ];
    }
    
    public function run($skipDocker = false, $cronMode = false) {
        echo $this->banner();
        
        try {
            $this->log("🔧 SWAOE v4.1 Enhanced Bootstrap Installer (PWTAE-Enabled)", "info");
            $this->log("Mode: " . ($cronMode ? "Stateless Cron" : "Persistent Daemon"), "info");
            $this->log("Generated at: {$this->timestamp}", "info");
            $this->log("Base directory: {$this->baseDir}", "info");
            $this->log("");
            
            // Phase 1: Directory Structure
            $this->createDirectories();
            
            // Phase 2: Enhanced Database Schema (Multi-user)
            $this->createDatabase();
            
            // Phase 3: Configuration
            $this->createConfigFiles();
            
            // Phase 4: Control Plane API (Enhanced with users, selectors)
            $this->generateControlPlaneAPI();
            
            // Phase 5: Worker Daemon or Cron Script
            if ($cronMode) {
                $this->generateCronWorker();
            } else {
                $this->generateWorkerDaemon();
            }
            
            // Phase 6: Playwright Runner (Enhanced with smart selectors + modes)
            $this->generatePlaywrightRunner();
            
            // Phase 7: Smart Selector Engine
            $this->generateSelectorEngine();
            
            // Phase 8: Enhanced PWA Dashboard (Task Builder + Viewport Modes)
            $this->generateEnhancedPWADashboard();
            
            // Phase 9: Routing Layer
            $this->generateHtaccess();
            
            // Phase 10: Deployment Configs
            if (!$skipDocker) {
                $this->generateDeploymentConfigs();
            }
            
            // Phase 11: Documentation
            $this->generateDocumentation();
            
            $this->log("", "success");
            $this->log("✅ SWAOE v4.1 Enhanced System Generated Successfully!", "success");
            $this->log("", "success");
            $this->printNextSteps($cronMode);
            
        } catch (Exception $e) {
            $this->log("❌ Installation failed: " . $e->getMessage(), "error");
            exit(1);
        }
    }
    
    private function createDirectories() {
        $this->log("📁 Creating directory structure...", "section");
        
        $dirs = [
            'api',
            'worker',
            'bridge',
            'data',
            'public',
            'public/assets',
            'public/assets/js',
            'public/assets/css',
            'logs',
            'config',
        ];
        
        foreach ($dirs as $dir) {
            $path = $this->baseDir . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
                $this->log("✓ Created: $dir", "success");
            }
        }
    }
    
    private function createDatabase() {
        $this->log("🗄️  Creating enhanced SQLite database schema...", "section");
        
        $dbPath = $this->config['db_path'];
        
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        
        try {
            $db = new PDO("sqlite:{$dbPath}");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Users table (PWTAE: Multi-tenancy)
            $db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id TEXT PRIMARY KEY,
                    username TEXT UNIQUE NOT NULL,
                    email TEXT UNIQUE NOT NULL,
                    password_hash TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    last_login DATETIME,
                    is_active INTEGER DEFAULT 1,
                    INDEX idx_username (username),
                    INDEX idx_email (email)
                )
            ");
            $this->log("✓ Created: users table", "success");
            
            // Sessions table (Enhanced with user_id and viewport_mode)
            $db->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    id TEXT PRIMARY KEY,
                    user_id TEXT NOT NULL,
                    status TEXT DEFAULT 'pending',
                    url TEXT NOT NULL,
                    repeat_count INTEGER DEFAULT 1,
                    current_cycle INTEGER DEFAULT 0,
                    viewport_mode TEXT DEFAULT 'desktop',
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    started_at DATETIME,
                    completed_at DATETIME,
                    config TEXT,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    INDEX idx_user_id (user_id),
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                )
            ");
            $this->log("✓ Created: sessions table", "success");
            
            // Tasks table (Enhanced with coordinate support)
            $db->exec("
                CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    task_index INTEGER NOT NULL,
                    task_type TEXT NOT NULL,
                    action TEXT,
                    selector TEXT,
                    coordinate_x INTEGER,
                    coordinate_y INTEGER,
                    value TEXT,
                    delay INTEGER DEFAULT 0,
                    repeat_count INTEGER DEFAULT 1,
                    retry_count INTEGER DEFAULT 0,
                    status TEXT DEFAULT 'pending',
                    result TEXT,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    completed_at DATETIME,
                    FOREIGN KEY (session_id) REFERENCES sessions(id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_status (status)
                )
            ");
            $this->log("✓ Created: tasks table (with coordinates)", "success");
            
            // Execution logs table
            $db->exec("
                CREATE TABLE IF NOT EXISTS execution_logs (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    task_id INTEGER,
                    worker_id TEXT,
                    cycle INTEGER,
                    event_type TEXT NOT NULL,
                    message TEXT,
                    level TEXT DEFAULT 'INFO',
                    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (session_id) REFERENCES sessions(id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_worker_id (worker_id),
                    INDEX idx_timestamp (timestamp)
                )
            ");
            $this->log("✓ Created: execution_logs table", "success");
            
            // Worker leases table
            $db->exec("
                CREATE TABLE IF NOT EXISTS worker_leases (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL UNIQUE,
                    worker_id TEXT NOT NULL,
                    acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    expires_at DATETIME NOT NULL,
                    FOREIGN KEY (session_id) REFERENCES sessions(id),
                    INDEX idx_session_id (session_id),
                    INDEX idx_worker_id (worker_id),
                    INDEX idx_expires_at (expires_at)
                )
            ");
            $this->log("✓ Created: worker_leases table", "success");
            
            $db = null;
            chmod($dbPath, 0666);
            
        } catch (Exception $e) {
            throw new Exception("Database creation failed: " . $e->getMessage());
        }
    }
    
    private function createConfigFiles() {
        $this->log("⚙️  Creating configuration files...", "section");
        
        $configContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Enhanced Configuration (PWTAE-Enabled)
 */

define('SWAOE_VERSION', '4.1.0-PWTAE');
define('SWAOE_ENV', getenv('SWAOE_ENV') ?: 'development');

// Database
define('DB_PATH', getenv('DB_PATH') ?: __DIR__ . '/../data/swaoe_cluster.sqlite');

// Redis
define('REDIS_HOST', getenv('REDIS_HOST') ?: 'localhost');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
define('REDIS_TIMEOUT', getenv('REDIS_TIMEOUT') ?: 0);
define('REDIS_DB', getenv('REDIS_DB') ?: 0);

// Worker Configuration
define('WORKER_ID', getenv('WORKER_ID') ?: 'worker_' . substr(md5(gethostname() . microtime()), 0, 8));
define('WORKER_MAX_RETRIES', getenv('WORKER_MAX_RETRIES') ?: 3);
define('WORKER_LEASE_TIMEOUT', getenv('WORKER_LEASE_TIMEOUT') ?: 300);
define('WORKER_CYCLE_TIMEOUT', getenv('WORKER_CYCLE_TIMEOUT') ?: 600);

// Playwright (Enhanced with modes)
define('PLAYWRIGHT_EXECUTABLE', getenv('PLAYWRIGHT_EXECUTABLE') ?: 'node');
define('PLAYWRIGHT_RUNNER', __DIR__ . '/../bridge/playwright_runner.js');
define('PLAYWRIGHT_TIMEOUT', getenv('PLAYWRIGHT_TIMEOUT') ?: 30000);
define('PLAYWRIGHT_SELECTOR_ENGINE', __DIR__ . '/../bridge/selector_engine.js');

// Viewport modes (PWTAE)
define('VIEWPORT_DESKTOP', ['width' => 1920, 'height' => 1080]);
define('VIEWPORT_MOBILE', ['width' => 375, 'height' => 667, 'isMobile' => true]);

// API
define('API_PORT', getenv('API_PORT') ?: 8080);
define('API_RATE_LIMIT', getenv('API_RATE_LIMIT') ?: 100);

// Session behavior (PWTAE: Stateless fresh sessions)
define('SESSION_STATELESS', true);  // Each execution is fresh (no cookies)
define('SESSION_ISOLATION', true);  // User isolation enabled

// Logging
define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'INFO');
define('LOG_PATH', __DIR__ . '/../logs');

// Cluster
define('CLUSTER_MODE', getenv('CLUSTER_MODE') ?: 'local');

// Helper functions
function getDb() {
    static $db = null;
    if (!$db) {
        $db = new PDO('sqlite:' . DB_PATH);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $db;
}

function getRedis() {
    static $redis = null;
    if (!$redis) {
        $redis = new Redis();
        $redis->connect(REDIS_HOST, REDIS_PORT, REDIS_TIMEOUT);
        $redis->select(REDIS_DB);
    }
    return $redis;
}

function logEvent($sessionId, $taskId, $eventType, $message, $level = 'INFO', $workerId = null) {
    try {
        $db = getDb();
        $stmt = $db->prepare("
            INSERT INTO execution_logs 
            (session_id, task_id, worker_id, event_type, message, level)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sessionId,
            $taskId,
            $workerId ?: WORKER_ID,
            $eventType,
            $message,
            $level
        ]);
    } catch (Exception $e) {
        error_log("Log event failed: " . $e->getMessage());
    }
}

function generateId($prefix = 'id') {
    return $prefix . '_' . substr(md5(microtime() . random_bytes(16)), 0, 12);
}

function getViewportSize($mode = 'desktop') {
    if ($mode === 'mobile') {
        return VIEWPORT_MOBILE;
    }
    return VIEWPORT_DESKTOP;
}
PHP;
        
        $this->writeFile('config/config.php', $configContent);
        $this->log("✓ Created: config/config.php (Enhanced)", "success");
    }
    
    private function generateControlPlaneAPI() {
        $this->log("🎮 Generating Enhanced Control Plane API (Multi-user)...", "section");
        
        $apiContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Enhanced Control Plane API
 * 
 * Multi-user support with session isolation
 * 
 * Endpoints:
 * POST   /api/user/register              - Register user
 * POST   /api/user/login                 - Login user
 * POST   /api/session/create             - Create new session
 * GET    /api/session/:id                - Get session details
 * GET    /api/session/:id/status         - Get real-time status
 * GET    /api/sessions                   - List user sessions
 * GET    /api/queue/status               - Get queue status
 * GET    /api/logs/:id                   - Get execution logs
 */

require_once __DIR__ . '/../config/config.php';

class ControlPlaneAPI {
    
    private $db;
    private $redis;
    private $currentUser = null;
    
    public function __construct() {
        $this->db = getDb();
        $this->redis = getRedis();
        header('Content-Type: application/json');
        
        // Check authentication token
        $this->authenticateRequest();
    }
    
    private function authenticateRequest() {
        $token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? null;
        
        if (!$token && !in_array($_SERVER['REQUEST_URI'], ['/api/user/register', '/api/user/login'])) {
            // Public endpoints don't require auth
            if (strpos($_SERVER['REQUEST_URI'], '/api/user/') === 0 || 
                strpos($_SERVER['REQUEST_URI'], '/api/queue/') === 0) {
                return;
            }
        }
        
        if ($token) {
            // Verify token (simplified)
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE id = ? AND is_active = 1");
            $stmt->execute([substr($token, 0, 20)]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $this->currentUser = $user;
            }
        }
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api', '', $path);
        
        try {
            // User endpoints
            if ($method === 'POST' && $path === '/user/register') {
                return $this->registerUser();
            } elseif ($method === 'POST' && $path === '/user/login') {
                return $this->loginUser();
            }
            
            // Session endpoints (require auth)
            elseif ($method === 'POST' && $path === '/session/create') {
                return $this->requireAuth() ? $this->createSession() : null;
            } elseif ($method === 'GET' && preg_match('/^\/session\/([a-z0-9_]+)$/', $path, $m)) {
                return $this->requireAuth() ? $this->getSession($m[1]) : null;
            } elseif ($method === 'GET' && preg_match('/^\/session\/([a-z0-9_]+)\/status$/', $path, $m)) {
                return $this->requireAuth() ? $this->getSessionStatus($m[1]) : null;
            } elseif ($method === 'GET' && $path === '/sessions') {
                return $this->requireAuth() ? $this->listSessions() : null;
            }
            
            // Queue endpoints
            elseif ($method === 'GET' && $path === '/queue/status') {
                return $this->getQueueStatus();
            }
            
            // Logs endpoints
            elseif ($method === 'GET' && preg_match('/^\/logs\/([a-z0-9_]+)$/', $path, $m)) {
                return $this->requireAuth() ? $this->getLogs($m[1]) : null;
            }
            
            else {
                return $this->error('Not Found', 404);
            }
        } catch (Exception $e) {
            logEvent(null, null, 'API_ERROR', $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage(), 500);
        }
    }
    
    private function requireAuth() {
        if (!$this->currentUser) {
            $this->error('Unauthorized', 401);
            return false;
        }
        return true;
    }
    
    private function registerUser() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['username'], $data['email'], $data['password'])) {
            return $this->error('Missing required fields', 400);
        }
        
        $userId = generateId('user');
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO users (id, username, email, password_hash, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$userId, $data['username'], $data['email'], $passwordHash]);
            
            logEvent(null, null, 'USER_REGISTERED', "User registered: {$data['username']}", 'INFO');
            
            return $this->success([
                'user_id' => $userId,
                'username' => $data['username'],
                'email' => $data['email'],
                'created_at' => date('c')
            ]);
            
        } catch (Exception $e) {
            return $this->error("Registration failed: " . $e->getMessage(), 500);
        }
    }
    
    private function loginUser() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['username'], $data['password'])) {
            return $this->error('Missing credentials', 400);
        }
        
        try {
            $stmt = $this->db->prepare("SELECT id, password_hash FROM users WHERE username = ? AND is_active = 1");
            $stmt->execute([$data['username']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($data['password'], $user['password_hash'])) {
                return $this->error('Invalid credentials', 401);
            }
            
            // Generate token (simplified)
            $token = $user['id'] . '_' . bin2hex(random_bytes(16));
            
            // Update last login
            $stmt = $this->db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            logEvent(null, null, 'USER_LOGIN', "User login: {$data['username']}", 'INFO');
            
            return $this->success([
                'token' => $token,
                'user_id' => $user['id'],
                'username' => $data['username']
            ]);
            
        } catch (Exception $e) {
            return $this->error("Login failed: " . $e->getMessage(), 500);
        }
    }
    
    private function createSession() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['url'])) {
            return $this->error('Missing required fields: url', 400);
        }
        
        $sessionId = generateId('sess');
        $userId = $this->currentUser['id'];
        $repeatCount = $data['repeat_count'] ?? 1;
        $viewportMode = $data['viewport_mode'] ?? 'desktop';
        $config = json_encode($data['config'] ?? []);
        
        try {
            // Insert session with user isolation
            $stmt = $this->db->prepare("
                INSERT INTO sessions (id, user_id, url, repeat_count, viewport_mode, config, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$sessionId, $userId, $data['url'], $repeatCount, $viewportMode, $config]);
            
            // Generate task graph
            $tasks = $this->generateTaskGraph($sessionId, $data);
            
            // Queue the session
            $this->redis->lpush('swaoe:queue:sessions', $sessionId);
            
            logEvent($sessionId, null, 'SESSION_CREATED', "Session created for URL: {$data['url']}", 'INFO');
            
            return $this->success([
                'session_id' => $sessionId,
                'status' => 'pending',
                'url' => $data['url'],
                'repeat_count' => $repeatCount,
                'viewport_mode' => $viewportMode,
                'tasks_generated' => count($tasks),
                'queued_at' => date('c')
            ]);
            
        } catch (Exception $e) {
            return $this->error("Failed to create session: " . $e->getMessage(), 500);
        }
    }
    
    private function generateTaskGraph($sessionId, $data) {
        $tasks = [];
        $taskIndex = 0;
        
        // NAVIGATE task
        $tasks[] = [
            'type' => 'NAVIGATE',
            'action' => 'goto',
            'value' => $data['url']
        ];
        
        // SELECTOR tasks (with smart selector or coordinates)
        if (isset($data['selectors'])) {
            foreach ($data['selectors'] as $selector) {
                $tasks[] = [
                    'type' => 'SELECTOR',
                    'action' => $selector['action'] ?? 'click',
                    'selector' => $selector['selector'] ?? null,
                    'coordinate_x' => $selector['x'] ?? null,
                    'coordinate_y' => $selector['y'] ?? null,
                    'value' => $selector['value'] ?? null,
                    'repeat_count' => $selector['repeat'] ?? 1
                ];
            }
        }
        
        // WAIT tasks
        if (isset($data['waits'])) {
            foreach ($data['waits'] as $wait) {
                $tasks[] = [
                    'type' => 'WAIT',
                    'delay' => $wait['delay'] ?? 1000
                ];
            }
        }
        
        // Insert into database
        foreach ($tasks as $task) {
            $stmt = $this->db->prepare("
                INSERT INTO tasks 
                (session_id, task_index, task_type, action, selector, coordinate_x, coordinate_y, value, repeat_count, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $sessionId,
                $taskIndex++,
                $task['type'],
                $task['action'] ?? null,
                $task['selector'] ?? null,
                $task['coordinate_x'] ?? null,
                $task['coordinate_y'] ?? null,
                $task['value'] ?? null,
                $task['repeat_count'] ?? 1
            ]);
        }
        
        return $tasks;
    }
    
    private function getSession($sessionId) {
        // User isolation check
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $this->currentUser['id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return $this->error('Session not found or access denied', 404);
        }
        
        return $this->success($session);
    }
    
    private function getSessionStatus($sessionId) {
        // User isolation check
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $this->currentUser['id']]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return $this->error('Session not found or access denied', 404);
        }
        
        // Get task statuses
        $stmt = $this->db->prepare("
            SELECT 
                task_type,
                status,
                COUNT(*) as count
            FROM tasks
            WHERE session_id = ?
            GROUP BY task_type, status
        ");
        $stmt->execute([$sessionId]);
        $taskStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent logs
        $stmt = $this->db->prepare("
            SELECT * FROM execution_logs
            WHERE session_id = ?
            ORDER BY timestamp DESC
            LIMIT 10
        ");
        $stmt->execute([$sessionId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->success([
            'session' => $session,
            'task_stats' => $taskStats,
            'recent_logs' => $logs
        ]);
    }
    
    private function listSessions() {
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $this->db->prepare("
            SELECT * FROM sessions
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$this->currentUser['id'], (int)$limit, (int)$offset]);
        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->success([
            'sessions' => $sessions,
            'count' => count($sessions)
        ]);
    }
    
    private function getQueueStatus() {
        $queueLength = $this->redis->llen('swaoe:queue:sessions');
        $activeWorkers = $this->redis->hgetall('swaoe:workers:active');
        
        return $this->success([
            'queue_depth' => $queueLength,
            'active_workers' => count($activeWorkers),
            'timestamp' => date('c')
        ]);
    }
    
    private function getLogs($sessionId) {
        // User isolation check
        $stmt = $this->db->prepare("SELECT user_id FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session || $session['user_id'] !== $this->currentUser['id']) {
            return $this->error('Access denied', 403);
        }
        
        $limit = $_GET['limit'] ?? 100;
        $offset = $_GET['offset'] ?? 0;
        
        $stmt = $this->db->prepare("
            SELECT * FROM execution_logs
            WHERE session_id = ?
            ORDER BY timestamp DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$sessionId, (int)$limit, (int)$offset]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $this->success([
            'logs' => $logs,
            'count' => count($logs)
        ]);
    }
    
    private function success($data) {
        echo json_encode(['success' => true, 'data' => $data], JSON_PRETTY_PRINT);
        exit;
    }
    
    private function error($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'error' => $message], JSON_PRETTY_PRINT);
        exit;
    }
}

$api = new ControlPlaneAPI();
$api->handleRequest();
PHP;
        
        $this->writeFile('api/index.php', $apiContent);
        $this->log("✓ Created: api/index.php (Control Plane API - Enhanced)", "success");
    }
    
    private function generateWorkerDaemon() {
        $this->log("🔄 Generating Worker Daemon (Persistent mode)...", "section");
        
        $daemonContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Worker Daemon (Persistent Mode)
 * 
 * For distributed deployments that require long-running workers.
 * For stateless shared hosting, use worker/cron.php instead.
 * 
 * Usage: php worker/daemon.php start
 *        php worker/daemon.php stop
 */

require_once __DIR__ . '/../config/config.php';

class WorkerDaemon {
    
    private $db;
    private $redis;
    private $workerId;
    private $running = false;
    private $pidFile;
    
    public function __construct() {
        $this->db = getDb();
        $this->redis = getRedis();
        $this->workerId = WORKER_ID;
        $this->pidFile = '/tmp/swaoe_worker_' . $this->workerId . '.pid';
        
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'shutdown']);
            pcntl_signal(SIGINT, [$this, 'shutdown']);
        }
    }
    
    public function start() {
        echo "[" . date('Y-m-d H:i:s') . "] Worker daemon starting (ID: {$this->workerId})\n";
        
        file_put_contents($this->pidFile, getmypid());
        $this->running = true;
        
        $this->redis->hset('swaoe:workers:active', $this->workerId, json_encode([
            'started_at' => time(),
            'status' => 'idle',
            'pid' => getmypid()
        ]));
        
        logEvent(null, null, 'WORKER_STARTED', "Worker daemon started", 'INFO', $this->workerId);
        
        while ($this->running) {
            try {
                $this->processQueue();
                usleep(100000);
            } catch (Exception $e) {
                echo "[ERROR] " . $e->getMessage() . "\n";
                logEvent(null, null, 'WORKER_ERROR', $e->getMessage(), 'ERROR', $this->workerId);
            }
        }
    }
    
    private function processQueue() {
        $sessionId = $this->redis->brpop('swaoe:queue:sessions', 1);
        
        if (!$sessionId) {
            return;
        }
        
        $sessionId = $sessionId[1];
        
        echo "[" . date('Y-m-d H:i:s') . "] Processing session: {$sessionId}\n";
        
        try {
            if (!$this->acquireLease($sessionId)) {
                echo "[WARN] Could not acquire lease for {$sessionId}, requeueing\n";
                $this->redis->lpush('swaoe:queue:sessions', $sessionId);
                return;
            }
            
            $session = $this->loadSession($sessionId);
            if (!$session) {
                $this->releaseLease($sessionId);
                return;
            }
            
            $this->updateWorkerStatus('processing', $sessionId);
            $this->executeSession($session);
            
            $stmt = $this->db->prepare("UPDATE sessions SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$sessionId]);
            
            logEvent($sessionId, null, 'SESSION_COMPLETED', "Session execution completed", 'INFO', $this->workerId);
            
        } catch (Exception $e) {
            echo "[ERROR] Session processing failed: " . $e->getMessage() . "\n";
            logEvent($sessionId, null, 'SESSION_ERROR', $e->getMessage(), 'ERROR', $this->workerId);
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM execution_logs 
                WHERE session_id = ? AND event_type = 'SESSION_ERROR'
            ");
            $stmt->execute([$sessionId]);
            $errorCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($errorCount < WORKER_MAX_RETRIES) {
                echo "[INFO] Requeueing session (attempt " . ($errorCount + 1) . " of " . WORKER_MAX_RETRIES . ")\n";
                $this->redis->lpush('swaoe:queue:sessions', $sessionId);
            } else {
                echo "[ERROR] Max retries exceeded for {$sessionId}\n";
                $stmt = $this->db->prepare("UPDATE sessions SET status = 'failed' WHERE id = ?");
                $stmt->execute([$sessionId]);
            }
        } finally {
            $this->releaseLease($sessionId);
            $this->updateWorkerStatus('idle', null);
        }
    }
    
    private function acquireLease($sessionId, $timeout = null) {
        $timeout = $timeout ?? WORKER_LEASE_TIMEOUT;
        $expiresAt = time() + $timeout;
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO worker_leases (session_id, worker_id, expires_at)
                VALUES (?, ?, datetime(?, 'unixepoch'))
            ");
            $stmt->execute([$sessionId, $this->workerId, $expiresAt]);
            return true;
        } catch (Exception $e) {
            $stmt = $this->db->prepare("
                SELECT expires_at FROM worker_leases WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $lease = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lease && strtotime($lease['expires_at']) > time()) {
                return false;
            }
            
            $stmt = $this->db->prepare("
                UPDATE worker_leases SET worker_id = ?, expires_at = datetime(?, 'unixepoch')
                WHERE session_id = ? AND expires_at < datetime('now')
            ");
            $stmt->execute([$this->workerId, $expiresAt, $sessionId]);
            
            return $stmt->rowCount() > 0;
        }
    }
    
    private function releaseLease($sessionId) {
        $stmt = $this->db->prepare("DELETE FROM worker_leases WHERE session_id = ? AND worker_id = ?");
        $stmt->execute([$sessionId, $this->workerId]);
    }
    
    private function loadSession($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function executeSession($session) {
        $sessionId = $session['id'];
        $repeatCount = $session['repeat_count'];
        $viewportMode = $session['viewport_mode'];
        
        $stmt = $this->db->prepare("UPDATE sessions SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        for ($cycle = 1; $cycle <= $repeatCount; $cycle++) {
            echo "[" . date('Y-m-d H:i:s') . "] Executing cycle {$cycle}/{$repeatCount} (viewport: {$viewportMode})\n";
            
            $stmt = $this->db->prepare("UPDATE sessions SET current_cycle = ? WHERE id = ?");
            $stmt->execute([$cycle, $sessionId]);
            
            $this->executeCycle($sessionId, $cycle, $viewportMode);
            
            logEvent($sessionId, null, 'CYCLE_COMPLETED', "Cycle {$cycle} completed", 'INFO', $this->workerId);
        }
    }
    
    private function executeCycle($sessionId, $cycle, $viewportMode) {
        $stmt = $this->db->prepare("
            SELECT * FROM tasks
            WHERE session_id = ?
            ORDER BY task_index ASC
        ");
        $stmt->execute([$sessionId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $playwrightPayload = [
            'session_id' => $sessionId,
            'cycle' => $cycle,
            'viewport_mode' => $viewportMode,
            'tasks' => $tasks
        ];
        
        $result = $this->runPlaywrightVM($playwrightPayload);
        
        if ($result && $result['success']) {
            foreach ($result['task_results'] as $taskResult) {
                $stmt = $this->db->prepare("
                    UPDATE tasks
                    SET status = ?, result = ?
                    WHERE session_id = ? AND task_index = ?
                ");
                $stmt->execute([
                    $taskResult['status'],
                    json_encode($taskResult),
                    $sessionId,
                    $taskResult['index']
                ]);
            }
        } else {
            throw new Exception("Playwright VM execution failed: " . ($result['error'] ?? 'unknown error'));
        }
    }
    
    private function runPlaywrightVM($payload) {
        $payloadJson = json_encode($payload);
        $cmd = sprintf(
            "%s %s %s 2>&1",
            PLAYWRIGHT_EXECUTABLE,
            escapeshellarg(PLAYWRIGHT_RUNNER),
            escapeshellarg($payloadJson)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            logEvent($payload['session_id'], null, 'PLAYWRIGHT_ERROR', implode("\n", $output), 'ERROR', $this->workerId);
            return ['success' => false, 'error' => implode("\n", $output)];
        }
        
        try {
            $result = json_decode(implode("\n", $output), true);
            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to parse Playwright output'];
        }
    }
    
    private function updateWorkerStatus($status, $currentSession = null) {
        $this->redis->hset('swaoe:workers:active', $this->workerId, json_encode([
            'started_at' => time(),
            'status' => $status,
            'current_session' => $currentSession,
            'pid' => getmypid()
        ]));
    }
    
    public function shutdown($signo = null) {
        echo "\n[" . date('Y-m-d H:i:s') . "] Worker daemon shutting down\n";
        $this->running = false;
        
        $this->redis->hdel('swaoe:workers:active', $this->workerId);
        logEvent(null, null, 'WORKER_STOPPED', "Worker daemon stopped", 'INFO', $this->workerId);
        
        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
        
        exit(0);
    }
}

$action = $argv[1] ?? 'help';
$daemon = new WorkerDaemon();

switch ($action) {
    case 'start':
        $daemon->start();
        break;
    case 'stop':
        $pidFile = '/tmp/swaoe_worker_' . WORKER_ID . '.pid';
        if (file_exists($pidFile)) {
            $pid = file_get_contents($pidFile);
            posix_kill((int)$pid, SIGTERM);
            echo "Worker daemon stopped\n";
        }
        break;
    default:
        echo "SWAOE v4.1 Worker Daemon\n";
        echo "Usage: php daemon.php {start|stop}\n";
}
PHP;
        
        $this->writeFile('worker/daemon.php', $daemonContent);
        $this->log("✓ Created: worker/daemon.php (Worker Daemon)", "success");
    }
    
    private function generateCronWorker() {
        $this->log("⏰ Generating Cron-Safe Stateless Worker...", "section");
        
        $cronContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Cron-Safe Stateless Worker (PWTAE Mode)
 * 
 * For shared hosting environments. Run via cron every minute:
 * * * * * * php /path/to/swaoe/worker/cron.php > /dev/null 2>&1
 * 
 * Features:
 * - Stateless: No persistent process
 * - Fresh sessions: Each run is clean (no cookies)
 * - Timeout-safe: Dies immediately after task execution
 * - Shared hosting safe: No resource hogging
 */

require_once __DIR__ . '/../config/config.php';

class CronWorker {
    private $db;
    private $redis;
    private $workerId;
    
    public function __construct() {
        $this->db = getDb();
        $this->redis = getRedis();
        $this->workerId = 'cron_' . date('YmdHis') . '_' . substr(md5(microtime()), 0, 8);
        
        // Set execution timeout
        set_time_limit(600);
    }
    
    public function execute() {
        try {
            // Process one job
            $sessionId = $this->redis->rpop('swaoe:queue:sessions');
            
            if (!$sessionId) {
                // Queue empty
                return;
            }
            
            echo "[" . date('Y-m-d H:i:s') . "] Processing session: {$sessionId}\n";
            
            $session = $this->loadSession($sessionId);
            if (!$session) {
                return;
            }
            
            // Acquire lease
            if (!$this->acquireLease($sessionId)) {
                echo "[WARN] Could not acquire lease, requeueing\n";
                $this->redis->lpush('swaoe:queue:sessions', $sessionId);
                return;
            }
            
            try {
                $this->executeSession($session);
                
                $stmt = $this->db->prepare("UPDATE sessions SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
                $stmt->execute([$sessionId]);
                
                logEvent($sessionId, null, 'SESSION_COMPLETED', "Session execution completed", 'INFO', $this->workerId);
                
            } finally {
                $this->releaseLease($sessionId);
            }
            
        } catch (Exception $e) {
            echo "[ERROR] " . $e->getMessage() . "\n";
            logEvent($sessionId ?? null, null, 'CRON_ERROR', $e->getMessage(), 'ERROR', $this->workerId);
        }
    }
    
    private function loadSession($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    private function acquireLease($sessionId) {
        $expiresAt = time() + 600;  // 10 minute lease
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO worker_leases (session_id, worker_id, expires_at)
                VALUES (?, ?, datetime(?, 'unixepoch'))
            ");
            $stmt->execute([$sessionId, $this->workerId, $expiresAt]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function releaseLease($sessionId) {
        $stmt = $this->db->prepare("DELETE FROM worker_leases WHERE session_id = ?");
        $stmt->execute([$sessionId]);
    }
    
    private function executeSession($session) {
        $sessionId = $session['id'];
        $repeatCount = $session['repeat_count'];
        $viewportMode = $session['viewport_mode'];
        
        $stmt = $this->db->prepare("UPDATE sessions SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        for ($cycle = 1; $cycle <= $repeatCount; $cycle++) {
            $stmt = $this->db->prepare("UPDATE sessions SET current_cycle = ? WHERE id = ?");
            $stmt->execute([$cycle, $sessionId]);
            
            $this->executeCycle($sessionId, $cycle, $viewportMode);
            
            logEvent($sessionId, null, 'CYCLE_COMPLETED', "Cycle {$cycle} completed", 'INFO', $this->workerId);
        }
    }
    
    private function executeCycle($sessionId, $cycle, $viewportMode) {
        $stmt = $this->db->prepare("
            SELECT * FROM tasks
            WHERE session_id = ?
            ORDER BY task_index ASC
        ");
        $stmt->execute([$sessionId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $playwrightPayload = [
            'session_id' => $sessionId,
            'cycle' => $cycle,
            'viewport_mode' => $viewportMode,
            'tasks' => $tasks
        ];
        
        $payloadJson = json_encode($playwrightPayload);
        $cmd = sprintf(
            "%s %s %s 2>&1",
            PLAYWRIGHT_EXECUTABLE,
            escapeshellarg(PLAYWRIGHT_RUNNER),
            escapeshellarg($payloadJson)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            logEvent($sessionId, null, 'PLAYWRIGHT_ERROR', implode("\n", $output), 'ERROR', $this->workerId);
            throw new Exception("Playwright execution failed");
        }
        
        try {
            $result = json_decode(implode("\n", $output), true);
            
            if ($result && $result['success']) {
                foreach ($result['task_results'] as $taskResult) {
                    $stmt = $this->db->prepare("
                        UPDATE tasks
                        SET status = ?, result = ?
                        WHERE session_id = ? AND task_index = ?
                    ");
                    $stmt->execute([
                        $taskResult['status'],
                        json_encode($taskResult),
                        $sessionId,
                        $taskResult['index']
                    ]);
                }
            } else {
                throw new Exception("Playwright returned error");
            }
        } catch (Exception $e) {
            throw new Exception("Failed to parse Playwright output: " . $e->getMessage());
        }
    }
}

// Execute one job and exit immediately
$worker = new CronWorker();
$worker->execute();
PHP;
        
        $this->writeFile('worker/cron.php', $cronContent);
        $this->log("✓ Created: worker/cron.php (Cron-Safe Stateless Worker)", "success");
    }
    
    private function generatePlaywrightRunner() {
        $this->log("🎭 Generating Playwright Runner VM (Enhanced with modes)...", "section");
        
        $runnerContent = <<<'JAVASCRIPT'
/**
 * SWAOE v4.1 Enhanced Playwright Browser Execution VM
 * 
 * Features:
 * - Smart selector engine integration
 * - Desktop and Mobile viewport modes (PWTAE)
 * - Coordinate fallback (pixel-based clicking)
 * - Fresh stateless sessions
 * - Selector fallback logic
 */

const playwright = require('playwright');
const fs = require('fs');

class PlaywrightVM {
    constructor(payload) {
        this.payload = payload;
        this.results = [];
        this.browser = null;
        this.page = null;
    }

    async run() {
        try {
            await this.initBrowser();
            await this.executeTasks();
            await this.cleanup();
            
            return {
                success: true,
                session_id: this.payload.session_id,
                cycle: this.payload.cycle,
                viewport_mode: this.payload.viewport_mode,
                task_results: this.results
            };
        } catch (error) {
            console.error('VM Execution Error:', error.message);
            return {
                success: false,
                error: error.message,
                session_id: this.payload.session_id
            };
        }
    }

    async initBrowser() {
        console.error(`[PLAYWRIGHT] Launching browser for session ${this.payload.session_id}`);
        
        this.browser = await playwright.chromium.launch({
            headless: true,
            args: ['--disable-dev-shm-usage', '--no-sandbox']
        });
        
        this.page = await this.browser.newPage();
        
        // Set viewport based on mode (PWTAE)
        const viewport = this.getViewport();
        await this.page.setViewportSize(viewport);
        
        // PWTAE: Fresh session (no cookies, no storage)
        await this.page.context().clearCookies();
    }

    getViewport() {
        if (this.payload.viewport_mode === 'mobile') {
            return { width: 375, height: 667 };
        }
        return { width: 1920, height: 1080 };
    }

    async executeTasks() {
        for (let i = 0; i < this.payload.tasks.length; i++) {
            const task = this.payload.tasks[i];
            const result = await this.executeTask(task, i);
            this.results.push(result);
        }
    }

    async executeTask(task, index) {
        try {
            let status = 'completed';
            let output = null;
            
            console.error(`[TASK] Executing ${task.task_type} (index ${index})`);
            
            switch (task.task_type) {
                case 'NAVIGATE':
                    await this.page.goto(task.value, { waitUntil: 'networkidle' });
                    output = { url: this.page.url() };
                    break;
                    
                case 'SELECTOR':
                    // PWTAE: Try smart selector first, then coordinate fallback
                    output = await this.executeSelector(task);
                    break;
                    
                case 'WAIT':
                    await this.page.waitForTimeout(task.delay);
                    output = { delayed: task.delay };
                    break;
                    
                case 'SCREENSHOT':
                    const screenshotPath = `/tmp/screenshot_${this.payload.session_id}_${index}.png`;
                    await this.page.screenshot({ path: screenshotPath });
                    output = { path: screenshotPath };
                    break;
                    
                default:
                    status = 'failed';
                    output = { error: `Unknown task type: ${task.task_type}` };
            }
            
            return {
                index: index,
                task_type: task.task_type,
                status: status,
                output: output
            };
            
        } catch (error) {
            console.error(`[ERROR] Task ${index} failed: ${error.message}`);
            return {
                index: index,
                task_type: task.task_type,
                status: 'failed',
                error: error.message
            };
        }
    }

    async executeSelector(task) {
        const repeatCount = task.repeat_count || 1;
        
        for (let rep = 0; rep < repeatCount; rep++) {
            let element = null;
            let attempts = 0;
            const maxAttempts = 3;
            
            // PWTAE: Smart selector priority
            let selectors = [];
            
            if (task.selector) {
                selectors.push(task.selector);
                // Add fallbacks
                if (task.selector.startsWith('.')) {
                    selectors.push(`[class*="${task.selector.substring(1)}"]`);
                } else if (task.selector.startsWith('#')) {
                    selectors.push(`[id="${task.selector.substring(1)}"]`);
                }
            }
            
            while (!element && attempts < maxAttempts) {
                for (const selector of selectors) {
                    try {
                        element = await this.page.$(selector);
                        if (element) break;
                    } catch (e) {
                        // Continue
                    }
                }
                
                if (!element) {
                    attempts++;
                    if (attempts < maxAttempts) {
                        await this.page.waitForTimeout(500);
                    }
                }
            }
            
            // Fallback to coordinates if selector fails (PWTAE)
            if (!element && task.coordinate_x && task.coordinate_y) {
                console.error(`[FALLBACK] Using coordinates (${task.coordinate_x}, ${task.coordinate_y})`);
                await this.page.click(`button, a, input, div[onclick]`, { 
                    position: { x: task.coordinate_x, y: task.coordinate_y } 
                });
            } else if (element) {
                // Execute action on found element
                switch (task.action) {
                    case 'click':
                        await element.click();
                        break;
                        
                    case 'fill':
                        await element.fill(task.value);
                        break;
                        
                    case 'hover':
                        await element.hover();
                        break;
                        
                    case 'focus':
                        await element.focus();
                        break;
                        
                    case 'press':
                        await this.page.keyboard.press(task.value);
                        break;
                        
                    default:
                        throw new Error(`Unknown action: ${task.action}`);
                }
            } else {
                throw new Error(`Selector not found and no fallback coordinates provided: ${task.selector}`);
            }
            
            if (rep < repeatCount - 1) {
                await this.page.waitForTimeout(500);  // Delay between repeats
            }
        }
        
        return {
            selector: task.selector,
            coordinates: { x: task.coordinate_x, y: task.coordinate_y },
            action: task.action,
            value: task.value,
            repeat_count: repeatCount
        };
    }

    async cleanup() {
        if (this.browser) {
            await this.browser.close();
        }
    }
}

// Main execution
async function main() {
    if (process.argv.length < 3) {
        console.error('Usage: node playwright_runner.js <JSON_PAYLOAD>');
        process.exit(1);
    }
    
    try {
        const payload = JSON.parse(process.argv[2]);
        const vm = new PlaywrightVM(payload);
        const result = await vm.run();
        
        console.log(JSON.stringify(result, null, 2));
        process.exit(result.success ? 0 : 1);
        
    } catch (error) {
        console.error('Fatal error:', error.message);
        process.exit(1);
    }
}

main();
JAVASCRIPT;
        
        $this->writeFile('bridge/playwright_runner.js', $runnerContent);
        $this->log("✓ Created: bridge/playwright_runner.js (Enhanced with modes)", "success");
        
        $packageContent = <<<'JSON'
{
  "name": "swaoe-playwright-runner",
  "version": "4.1.0-pwtae",
  "description": "SWAOE v4.1 Enhanced Playwright Browser Execution VM (PWTAE-enabled)",
  "main": "playwright_runner.js",
  "scripts": {
    "install": "npm install",
    "test": "node playwright_runner.js '{}'"
  },
  "dependencies": {
    "playwright": "^1.40.0"
  },
  "engines": {
    "node": ">=16.0.0"
  }
}
JSON;
        
        $this->writeFile('bridge/package.json', $packageContent);
        $this->log("✓ Created: bridge/package.json", "success");
    }
    
    private function generateSelectorEngine() {
        $this->log("🧠 Generating Smart Selector Engine...", "section");
        
        $selectorContent = <<<'JAVASCRIPT'
/**
 * SWAOE Smart Selector Engine
 * 
 * Intelligently detects page elements and maps to CSS selectors.
 * Used by task builder to generate robust selectors.
 */

async function detectElement(page, x, y) {
    try {
        // Get element at coordinates
        const element = await page.evaluate(({ x, y }) => {
            const el = document.elementFromPoint(x, y);
            if (!el) return null;
            
            return {
                tagName: el.tagName,
                id: el.id || null,
                className: el.className || null,
                text: el.textContent?.substring(0, 100),
                attributes: {
                    type: el.getAttribute('type'),
                    name: el.getAttribute('name'),
                    placeholder: el.getAttribute('placeholder'),
                    'aria-label': el.getAttribute('aria-label')
                }
            };
        }, { x, y });
        
        if (!element) return null;
        
        // Generate selectors (priority order)
        const selectors = [];
        
        // 1. ID selector (most specific)
        if (element.id) {
            selectors.push(`#${element.id}`);
        }
        
        // 2. Attribute selectors
        if (element.attributes.type === 'button') {
            selectors.push('button');
        }
        if (element.attributes['aria-label']) {
            selectors.push(`[aria-label="${element.attributes['aria-label']}"]`);
        }
        if (element.attributes.placeholder) {
            selectors.push(`[placeholder="${element.attributes.placeholder}"]`);
        }
        
        // 3. Class selectors
        if (element.className) {
            const classes = element.className.split(' ');
            if (classes.length > 0) {
                selectors.push(`.${classes[0]}`);
                selectors.push(`.${classes.join('.')}`);
            }
        }
        
        // 4. Text content selector
        if (element.text && element.text.length > 0) {
            selectors.push(`${element.tagName.toLowerCase()}:contains("${element.text.substring(0, 50)}")`);
        }
        
        return {
            element: element,
            selectors: selectors,
            coordinates: { x, y }
        };
    } catch (error) {
        console.error('Selector detection failed:', error);
        return null;
    }
}

// Export for use in Playwright
module.exports = { detectElement };
JAVASCRIPT;
        
        $this->writeFile('bridge/selector_engine.js', $selectorContent);
        $this->log("✓ Created: bridge/selector_engine.js (Smart Selector Engine)", "success");
    }
    
    private function generateEnhancedPWADashboard() {
        $this->log("🚀 Generating Enhanced PWA Dashboard (with Visual Task Builder)...", "section");
        
        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SWAOE v4.1 - Enhanced Automation Dashboard (PWTAE)">
    <meta name="theme-color" content="#1a1a2e">
    <title>SWAOE v4.1 Enhanced Dashboard</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='75' font-size='75' fill='%231a1a2e'>⚙</text></svg>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            color: #eee;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0f3460;
            padding-bottom: 20px;
        }
        
        header h1 {
            font-size: 2em;
            background: linear-gradient(45deg, #00d4ff, #0099ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 5px;
        }
        
        header p {
            color: #888;
            font-size: 0.9em;
        }
        
        .auth-section {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .auth-section button {
            background: #00d4ff;
            color: #1a1a2e;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background: rgba(15, 52, 96, 0.5);
            border: 1px solid #0f3460;
            border-radius: 8px;
            padding: 20px;
            backdrop-filter: blur(10px);
        }
        
        .card h2 {
            color: #00d4ff;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        .card label {
            display: block;
            margin-bottom: 8px;
            color: #aaa;
            font-size: 0.9em;
        }
        
        .card input,
        .card select,
        .card textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 12px;
            background: rgba(10, 25, 47, 0.8);
            border: 1px solid #0f3460;
            border-radius: 4px;
            color: #eee;
            font-family: monospace;
        }
        
        .card button {
            background: linear-gradient(45deg, #00d4ff, #0099ff);
            color: #1a1a2e;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
        }
        
        .card button:hover {
            opacity: 0.9;
        }
        
        .viewport-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .viewport-selector label {
            flex: 1;
            margin: 0;
        }
        
        .viewport-selector input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }
        
        .task-builder {
            background: rgba(10, 25, 47, 0.6);
            border: 1px dashed #0f3460;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .task-item {
            background: rgba(15, 52, 96, 0.4);
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border-left: 3px solid #00d4ff;
        }
        
        .task-item small {
            color: #888;
            display: block;
            margin-top: 5px;
        }
        
        .btn-add-task {
            background: #0f3460;
            border: 1px solid #00d4ff;
            color: #00d4ff;
            padding: 10px;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .status-item {
            background: rgba(10, 25, 47, 0.8);
            padding: 12px;
            border-radius: 4px;
            text-align: center;
            border-left: 3px solid #00d4ff;
        }
        
        .status-item strong {
            display: block;
            font-size: 1.3em;
            color: #00d4ff;
        }
        
        .status-item small {
            color: #888;
            font-size: 0.8em;
        }
        
        .logs {
            background: rgba(10, 25, 47, 0.8);
            border: 1px solid #0f3460;
            border-radius: 4px;
            padding: 12px;
            height: 250px;
            overflow-y: auto;
            font-size: 0.8em;
            font-family: monospace;
        }
        
        .log-entry {
            padding: 3px;
            border-bottom: 1px solid #0f3460;
            color: #888;
        }
        
        .log-entry.error { color: #ff6b6b; }
        .log-entry.success { color: #51cf66; }
        .log-entry.info { color: #00d4ff; }
        
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            header h1 { font-size: 1.4em; }
        }
    </style>
</head>
<body>
    <div class="auth-section">
        <button id="authBtn" onclick="toggleAuth()">Login / Register</button>
    </div>
    
    <div class="container">
        <header>
            <h1>⚙️ SWAOE v4.1 Enhanced</h1>
            <p>Distributed Automation + PWTAE (Persistent Web Task Automation Engine)</p>
        </header>
        
        <div id="authModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 1000; display: flex; align-items: center; justify-content: center;">
            <div class="card" style="max-width: 400px; width: 90%;">
                <h2 id="authTitle">Login</h2>
                <label>Username</label>
                <input type="text" id="authUsername" placeholder="username">
                <label>Email (for register)</label>
                <input type="email" id="authEmail" placeholder="user@example.com">
                <label>Password</label>
                <input type="password" id="authPassword" placeholder="password">
                <button onclick="handleAuth()">Login</button>
                <button onclick="toggleAuthMode()" style="background: #0f3460; margin-top: 10px;">Switch to Register</button>
                <button onclick="toggleAuth()" style="background: #333; margin-top: 10px;">Close</button>
            </div>
        </div>
        
        <div id="mainApp" style="display: none;">
            <div class="grid">
                <!-- Create Session -->
                <div class="card">
                    <h2>📝 Create Session</h2>
                    
                    <label>Target URL</label>
                    <input type="text" id="url" placeholder="https://example.com">
                    
                    <label>Repeat Count</label>
                    <input type="number" id="repeatCount" min="1" max="100" value="1">
                    
                    <label>Viewport Mode</label>
                    <div class="viewport-selector">
                        <label>
                            <input type="radio" name="viewport" value="desktop" checked>
                            🖥 Desktop
                        </label>
                        <label>
                            <input type="radio" name="viewport" value="mobile">
                            📱 Mobile
                        </label>
                    </div>
                    
                    <div class="task-builder">
                        <strong style="color: #00d4ff;">Tasks</strong>
                        <div id="tasksList"></div>
                        <button class="btn-add-task" onclick="addTaskStep()">+ Add Task Step</button>
                    </div>
                    
                    <button onclick="createSession()">Create & Queue Session</button>
                    <div id="createStatus" style="margin-top: 10px; color: #888; font-size: 0.9em;"></div>
                </div>
                
                <!-- Queue Status -->
                <div class="card">
                    <h2>📊 System Status</h2>
                    
                    <div class="status-grid" id="statusGrid">
                        <div class="status-item">
                            <strong>-</strong>
                            <small>Queue</small>
                        </div>
                        <div class="status-item">
                            <strong>-</strong>
                            <small>Workers</small>
                        </div>
                    </div>
                    
                    <button onclick="refreshStatus()">Refresh</button>
                </div>
                
                <!-- Session Lookup -->
                <div class="card">
                    <h2>🔍 Session Lookup</h2>
                    
                    <label>Session ID</label>
                    <input type="text" id="sessionId" placeholder="sess_xxxxx">
                    
                    <button onclick="getSessionStatus()">Get Status</button>
                    <div id="sessionStatus" style="margin-top: 10px; color: #888; font-size: 0.85em; max-height: 150px; overflow-y: auto;"></div>
                </div>
            </div>
            
            <!-- Logs -->
            <div class="card">
                <h2>📜 System Logs</h2>
                <div style="margin-bottom: 10px;">
                    <button onclick="clearLogs()" style="width: auto; padding: 8px 16px; background: #0f3460; border: 1px solid #00d4ff; color: #00d4ff; border-radius: 4px; cursor: pointer;">Clear</button>
                    <span id="autoRefreshStatus" style="margin-left: 10px; color: #888;"></span>
                </div>
                <div class="logs" id="logs"></div>
            </div>
        </div>
    </div>
    
    <script>
        let authMode = 'login';
        let authToken = localStorage.getItem('swaoe_token');
        let autoRefreshInterval = null;
        const tasks = [];
        
        function toggleAuth() {
            document.getElementById('authModal').style.display = 
                document.getElementById('authModal').style.display === 'none' ? 'flex' : 'none';
        }
        
        function toggleAuthMode() {
            authMode = authMode === 'login' ? 'register' : 'login';
            document.getElementById('authTitle').textContent = authMode === 'login' ? 'Login' : 'Register';
        }
        
        async function handleAuth() {
            const username = document.getElementById('authUsername').value;
            const email = document.getElementById('authEmail').value;
            const password = document.getElementById('authPassword').value;
            
            if (!username || !password) {
                alert('Fill in required fields');
                return;
            }
            
            try {
                const response = await fetch(`/api/user/${authMode}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username,
                        email: authMode === 'register' ? email : undefined,
                        password
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    authToken = result.data.token;
                    localStorage.setItem('swaoe_token', authToken);
                    document.getElementById('authModal').style.display = 'none';
                    document.getElementById('mainApp').style.display = 'block';
                    addLog(`User ${username} authenticated`, 'success');
                    startAutoRefresh();
                } else {
                    alert('Auth failed: ' + result.error);
                }
            } catch (error) {
                alert('Error: ' + error.message);
            }
        }
        
        function addTaskStep() {
            const step = tasks.length + 1;
            const html = `
                <div class="task-item" id="task_${step}">
                    Action: <input type="text" placeholder="click / fill / wait" style="width: auto; padding: 4px;">
                    Selector: <input type="text" placeholder="#id or .class" style="width: auto; padding: 4px;">
                    <button onclick="removeTask(${step})" style="background: #ff6b6b; padding: 4px 8px; border: none; color: white; border-radius: 3px; cursor: pointer;">Remove</button>
                    <small>Task ${step}</small>
                </div>
            `;
            document.getElementById('tasksList').insertAdjacentHTML('beforeend', html);
            tasks.push(step);
        }
        
        function removeTask(step) {
            document.getElementById(`task_${step}`).remove();
            tasks.splice(tasks.indexOf(step), 1);
        }
        
        async function createSession() {
            if (!authToken) {
                alert('Please login first');
                return;
            }
            
            const url = document.getElementById('url').value;
            const repeatCount = parseInt(document.getElementById('repeatCount').value);
            const viewport = document.querySelector('input[name="viewport"]:checked').value;
            
            if (!url) {
                alert('Enter a URL');
                return;
            }
            
            try {
                const response = await fetch('/api/session/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Auth-Token': authToken
                    },
                    body: JSON.stringify({
                        url,
                        repeat_count: repeatCount,
                        viewport_mode: viewport,
                        selectors: []  // Add from task builder
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('createStatus').innerHTML = `<span class="log-entry success">✓ Session: ${result.data.session_id}</span>`;
                    document.getElementById('sessionId').value = result.data.session_id;
                    addLog(`Session created: ${result.data.session_id}`, 'success');
                } else {
                    document.getElementById('createStatus').innerHTML = `<span class="log-entry error">✗ ${result.error}</span>`;
                }
            } catch (error) {
                document.getElementById('createStatus').innerHTML = `<span class="log-entry error">✗ ${error.message}</span>`;
            }
        }
        
        async function refreshStatus() {
            try {
                const response = await fetch('/api/queue/status');
                const result = await response.json();
                
                if (result.success) {
                    const data = result.data;
                    document.getElementById('statusGrid').innerHTML = `
                        <div class="status-item">
                            <strong>${data.queue_depth}</strong>
                            <small>Queue Depth</small>
                        </div>
                        <div class="status-item">
                            <strong>${data.active_workers}</strong>
                            <small>Active Workers</small>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Status update failed:', error);
            }
        }
        
        async function getSessionStatus() {
            const sessionId = document.getElementById('sessionId').value;
            if (!sessionId) return;
            
            try {
                const response = await fetch(`/api/session/${sessionId}/status`, {
                    headers: { 'X-Auth-Token': authToken }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    const session = result.data.session;
                    document.getElementById('sessionStatus').innerHTML = `
                        <div>
                            <strong>Status:</strong> ${session.status}<br>
                            <strong>Cycle:</strong> ${session.current_cycle}/${session.repeat_count}<br>
                            <strong>Mode:</strong> ${session.viewport_mode}<br>
                            <strong>Created:</strong> ${new Date(session.created_at).toLocaleString()}
                        </div>
                    `;
                    addLog(`Session status: ${session.status}`, 'info');
                } else {
                    document.getElementById('sessionStatus').innerHTML = `<span class="log-entry error">Error: ${result.error}</span>`;
                }
            } catch (error) {
                document.getElementById('sessionStatus').innerHTML = `<span class="log-entry error">Error: ${error.message}</span>`;
            }
        }
        
        function addLog(message, level = 'info') {
            const logs = document.getElementById('logs');
            const entry = document.createElement('div');
            entry.className = `log-entry ${level}`;
            entry.textContent = `[${new Date().toLocaleTimeString()}] ${message}`;
            logs.insertBefore(entry, logs.firstChild);
            
            while (logs.children.length > 100) {
                logs.removeChild(logs.lastChild);
            }
        }
        
        function clearLogs() {
            document.getElementById('logs').innerHTML = '';
        }
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(async () => {
                await refreshStatus();
            }, 5000);
            document.getElementById('autoRefreshStatus').textContent = '🟢 Auto-refreshing...';
        }
        
        // Initialize
        if (authToken) {
            document.getElementById('mainApp').style.display = 'block';
            startAutoRefresh();
        } else {
            document.getElementById('authModal').style.display = 'flex';
        }
        
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').catch(() => {});
        }
    </script>
</body>
</html>
HTML;
        
        $this->writeFile('public/index.html', $htmlContent);
        $this->log("✓ Created: public/index.html (Enhanced PWA Dashboard)", "success");
        
        // Manifest
        $manifestContent = <<<'JSON'
{
  "name": "SWAOE v4.1 Enhanced",
  "short_name": "SWAOE",
  "description": "Distributed Automation + PWTAE",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#1a1a2e",
  "theme_color": "#1a1a2e",
  "icons": [
    {
      "src": "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 192 192'><rect fill='%231a1a2e' width='192' height='192'/><text x='96' y='144' font-size='120' text-anchor='middle' fill='%2300d4ff'>⚙</text></svg>",
      "sizes": "192x192",
      "type": "image/svg+xml"
    }
  ]
}
JSON;
        
        $this->writeFile('public/manifest.json', $manifestContent);
        $this->log("✓ Created: public/manifest.json", "success");
        
        // Service Worker
        $swContent = <<<'JAVASCRIPT'
const CACHE_NAME = 'swaoe-v4.1-enhanced';

self.addEventListener('install', event => {
    event.waitUntil(caches.open(CACHE_NAME).then(cache => {
        return cache.addAll(['/index.html', '/manifest.json']).catch(() => {});
    }));
});

self.addEventListener('fetch', event => {
    if (event.request.method === 'GET') {
        event.respondWith(
            caches.match(event.request).then(response => {
                return response || fetch(event.request).catch(() => {
                    return caches.match('/index.html');
                });
            })
        );
    }
});
JAVASCRIPT;
        
        $this->writeFile('public/service-worker.js', $swContent);
        $this->log("✓ Created: public/service-worker.js", "success");
    }
    
    private function generateHtaccess() {
        $this->log("🛡️  Generating routing & security layer...", "section");
        
        $htaccessContent = <<<'HTACCESS'
RewriteEngine On

<FilesMatch "^(config|data|worker|bridge|logs)">
    Deny from all
</FilesMatch>

RewriteRule ^api/(.*)$ api/index.php?path=$1 [QSA,L]
RewriteRule ^(manifest\.json|service-worker\.js)$ public/$1 [QSA,L]
RewriteRule ^(.*)$ public/$1 [QSA,L]

Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"

Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization, X-Auth-Token"

<FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|svg)$">
    Header set Cache-Control "max-age=604800, public"
</FilesMatch>
HTACCESS;
        
        $this->writeFile('.htaccess', $htaccessContent);
        $this->log("✓ Created: .htaccess", "success");
    }
    
    private function generateDeploymentConfigs() {
        $this->log("🐳 Generating deployment configs...", "section");
        
        $dockerComposeContent = <<<'YAML'
version: '3.8'

services:
  redis:
    image: redis:7-alpine
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s

  api:
    build:
      context: .
      dockerfile: Dockerfile.api
    ports:
      - "8080:8080"
    environment:
      - REDIS_HOST=redis
      - DB_PATH=/app/data/swaoe_cluster.sqlite
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on:
      - redis
    restart: unless-stopped

  worker_1:
    build:
      context: .
      dockerfile: Dockerfile.worker
    environment:
      - REDIS_HOST=redis
      - WORKER_ID=worker_1
      - DB_PATH=/app/data/swaoe_cluster.sqlite
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on:
      - redis
    restart: unless-stopped

volumes:
  redis_data:
YAML;
        
        $this->writeFile('docker-compose.yml', $dockerComposeContent);
        $this->log("✓ Created: docker-compose.yml", "success");
        
        $dockerfileApi = <<<'DOCKERFILE'
FROM php:8.2-apache
RUN docker-php-ext-install pdo pdo_sqlite
RUN pecl install redis && docker-php-ext-enable redis
RUN a2enmod rewrite
WORKDIR /app
COPY . .
RUN chown -R www-data:www-data /app && chmod -R 755 /app
RUN mkdir -p /app/data /app/logs && chmod 777 /app/data /app/logs
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf
EXPOSE 8080
CMD ["apache2-foreground"]
DOCKERFILE;
        
        $this->writeFile('Dockerfile.api', $dockerfileApi);
        $this->log("✓ Created: Dockerfile.api", "success");
        
        $dockerfileWorker = <<<'DOCKERFILE'
FROM php:8.2-cli
RUN apt-get update && apt-get install -y nodejs npm && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo pdo_sqlite
RUN pecl install redis && docker-php-ext-enable redis
RUN npm install -g playwright
WORKDIR /app
COPY . .
RUN cd /app/bridge && npm install
RUN mkdir -p /app/data /app/logs && chmod 777 /app/data /app/logs
CMD ["php", "worker/daemon.php", "start"]
DOCKERFILE;
        
        $this->writeFile('Dockerfile.worker', $dockerfileWorker);
        $this->log("✓ Created: Dockerfile.worker", "success");
    }
    
    private function generateDocumentation() {
        $this->log("📖 Generating documentation...", "section");
        
        $readmeContent = <<<'MARKDOWN'
# SWAOE v4.1 Enhanced — Distributed Automation + PWTAE

A production-ready distributed workflow automation system enhanced with **PWTAE principles**:

- **Stateless Sessions**: Fresh browser environment every execution (no cookies)
- **Multi-user Support**: User isolation + session ownership
- **Smart Selectors**: DOM detection + coordinate fallback
- **Viewport Modes**: Desktop & Mobile automation
- **Flexible Execution**: Daemon or cron-safe stateless worker

## Features

✅ Multi-user authentication + session isolation  
✅ Smart selector engine (DOM + coordinates)  
✅ Desktop and mobile viewport modes  
✅ Stateless fresh sessions (PWTAE)  
✅ Daemon or cron-safe execution  
✅ Distributed task retry + failover  
✅ Real-time monitoring dashboard  
✅ PWA with offline support  

## Quick Start

### Docker
```bash
docker-compose up -d
# Dashboard: http://localhost:8080
```

### Local (Daemon Mode)
```bash
php install.php
cd bridge && npm install && cd ..
redis-server &
php -S localhost:8080 -t public &
php worker/daemon.php start &
```

### Shared Hosting (Cron Mode)
```bash
php install.php --cron-mode
# Setup cron: * * * * * php /path/to/worker/cron.php
```

## API Endpoints

```
POST /api/user/register          - Register
POST /api/user/login             - Login  
POST /api/session/create         - Create session
GET  /api/session/:id/status     - Get status
GET  /api/queue/status           - System status
GET  /api/logs/:id               - Execution logs
```

## Task Types

- **NAVIGATE**: Go to URL
- **SELECTOR**: Click/fill element (smart or coordinate-based)
- **WAIT**: Delay
- **SCREENSHOT**: Capture

## Configuration

Set environment variables or edit `config/config.php`:

```
REDIS_HOST=localhost
VIEWPORT_DESKTOP=1920x1080
VIEWPORT_MOBILE=375x667
SESSION_STATELESS=true
```

## PWTAE Principles

1. **Fresh Sessions**: No cookies, no storage — clean state every run
2. **Stateless Execution**: Repeatable results independent of browser history
3. **Smart Fallbacks**: Try DOM selectors first, fall back to coordinates
4. **User Isolation**: Each user's sessions are private
5. **Flexible Modes**: Desktop/Mobile viewport emulation

## License

MIT
MARKDOWN;
        
        $this->writeFile('README.md', $readmeContent);
        $this->log("✓ Created: README.md", "success");
    }
    
    private function writeFile($path, $content) {
        $fullPath = $this->baseDir . '/' . $path;
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($fullPath, $content);
        chmod($fullPath, 0644);
    }
    
    private function log($message, $type = 'info') {
        $colors = [
            'section' => "\033[1;36m",
            'success' => "\033[1;32m",
            'error' => "\033[1;31m",
            'info' => "\033[0;37m",
            'reset' => "\033[0m"
        ];
        
        $color = $colors[$type] ?? $colors['info'];
        echo $color . $message . $colors['reset'] . "\n";
    }
    
    private function banner() {
        return <<<'BANNER'

  ███████╗██╗    ██╗ █████╗  ██████╗ ███████╗
  ██╔════╝██║    ██║██╔══██╗██╔════╝ ██╔════╝
  ███████╗██║ █╗ ██║███████║██║  ███╗█████╗
  ╚════██║██║███╗██║██╔══██║██║   ██║██╔══╝
  ███████║╚███╔███╔╝██║  ██║╚██████╔╝███████╗
  ╚══════╝ ╚══╝╚══╝ ╚═╝  ╚═╝ ╚═════╝ ╚══════╝

  Enhanced with PWTAE (Persistent Web Task Automation Engine)
  
BANNER;
    }
    
    private function printNextSteps($cronMode) {
        $modeText = $cronMode ? "Cron-Safe Stateless" : "Persistent Daemon";
        echo "
┌─────────────────────────────────────────────────────────────┐
│                      NEXT STEPS ($modeText)                 │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📦 Install Dependencies:                                   │
│     cd bridge && npm install && cd ..                      │
│                                                             │
│  🚀 Start System:                                           │
│     docker-compose up -d                                  │
│     OR                                                     │
│     redis-server & && php -S localhost:8080 -t public &   │
│     " . ($cronMode ? "php worker/cron.php  (run via cron)" : "php worker/daemon.php start") . "   │
│                                                             │
│  🔓 Login to Dashboard:                                    │
│     http://localhost:8080                                 │
│     (Register account first)                              │
│                                                             │
│  🎯 Key Features (Enhanced):                               │
│     ✓ Multi-user with session isolation                    │
│     ✓ Smart selectors + coordinate fallback                │
│     ✓ Desktop & Mobile viewport modes                      │
│     ✓ Stateless fresh sessions (PWTAE)                     │
│     ✓ " . ($cronMode ? "Cron-safe execution" : "Distributed workers") . "                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
        ";
    }
}

$skipDocker = in_array('--skip-docker', $argv ?? []);
$cronMode = in_array('--cron-mode', $argv ?? []);
$installer = new SWAOEInstaller();
$installer->run($skipDocker, $cronMode);
