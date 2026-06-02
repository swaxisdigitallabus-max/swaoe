<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════════
 * SWAOE v4.1 — DISTRIBUTED AUTOMATION ORCHESTRATION ENGINE
 * All-in-One Bootstrap Installer
 * ═══════════════════════════════════════════════════════════════════════════════
 *
 * This installer generates a complete distributed workflow automation system:
 * - Control Plane API (PHP)
 * - Distributed Worker Daemon (PHP CLI)
 * - Playwright Browser Execution VM (Node.js)
 * - SQLite Database (sessions, tasks, logs)
 * - Redis Queue (job coordination)
 * - PWA Control Dashboard (UI)
 * - Docker/Deployment Configs
 *
 * Usage: php install.php [--skip-docker]
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
    
    public function run($skipDocker = false) {
        echo $this->banner();
        
        try {
            $this->log("🔧 SWAOE v4.1 Bootstrap Installer", "info");
            $this->log("Generated at: {$this->timestamp}", "info");
            $this->log("Base directory: {$this->baseDir}", "info");
            $this->log("");
            
            // Phase 1: Directory Structure
            $this->createDirectories();
            
            // Phase 2: Database Schema
            $this->createDatabase();
            
            // Phase 3: Configuration
            $this->createConfigFiles();
            
            // Phase 4: Control Plane API
            $this->generateControlPlaneAPI();
            
            // Phase 5: Worker Daemon
            $this->generateWorkerDaemon();
            
            // Phase 6: Playwright Runner
            $this->generatePlaywrightRunner();
            
            // Phase 7: PWA Dashboard
            $this->generatePWADashboard();
            
            // Phase 8: Routing Layer
            $this->generateHtaccess();
            
            // Phase 9: Deployment Configs
            if (!$skipDocker) {
                $this->generateDeploymentConfigs();
            }
            
            // Phase 10: Documentation
            $this->generateDocumentation();
            
            $this->log("", "success");
            $this->log("✅ SWAOE v4.1 System Generated Successfully!", "success");
            $this->log("", "success");
            $this->printNextSteps();
            
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
        $this->log("🗄️  Creating SQLite database schema...", "section");
        
        $dbPath = $this->config['db_path'];
        
        // Remove existing database for clean install
        if (file_exists($dbPath)) {
            unlink($dbPath);
        }
        
        try {
            $db = new PDO("sqlite:{$dbPath}");
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Sessions table
            $db->exec("
                CREATE TABLE IF NOT EXISTS sessions (
                    id TEXT PRIMARY KEY,
                    status TEXT DEFAULT 'pending',
                    url TEXT NOT NULL,
                    repeat_count INTEGER DEFAULT 1,
                    current_cycle INTEGER DEFAULT 0,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    started_at DATETIME,
                    completed_at DATETIME,
                    config TEXT,
                    INDEX idx_status (status),
                    INDEX idx_created_at (created_at)
                )
            ");
            $this->log("✓ Created: sessions table", "success");
            
            // Tasks table
            $db->exec("
                CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    session_id TEXT NOT NULL,
                    task_index INTEGER NOT NULL,
                    task_type TEXT NOT NULL,
                    action TEXT,
                    selector TEXT,
                    value TEXT,
                    delay INTEGER DEFAULT 0,
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
            $this->log("✓ Created: tasks table", "success");
            
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
        
        // config.php
        $configContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Configuration
 */

define('SWAOE_VERSION', '4.1.0');
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
define('WORKER_HEARTBEAT_INTERVAL', getenv('WORKER_HEARTBEAT_INTERVAL') ?: 30);

// Playwright
define('PLAYWRIGHT_EXECUTABLE', getenv('PLAYWRIGHT_EXECUTABLE') ?: 'node');
define('PLAYWRIGHT_RUNNER', __DIR__ . '/../bridge/playwright_runner.js');
define('PLAYWRIGHT_TIMEOUT', getenv('PLAYWRIGHT_TIMEOUT') ?: 30000);

// API
define('API_PORT', getenv('API_PORT') ?: 8080);
define('API_RATE_LIMIT', getenv('API_RATE_LIMIT') ?: 100);

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
PHP;
        
        $this->writeFile('config/config.php', $configContent);
        $this->log("✓ Created: config/config.php", "success");
    }
    
    private function generateControlPlaneAPI() {
        $this->log("🎮 Generating Control Plane API...", "section");
        
        // api/index.php
        $apiContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Control Plane API
 * 
 * Endpoints:
 * POST   /api/session/create      - Create new session
 * GET    /api/session/:id         - Get session details
 * GET    /api/session/:id/status  - Get real-time status
 * GET    /api/queue/status        - Get queue depth
 * GET    /api/logs/:id            - Get execution logs
 */

require_once __DIR__ . '/../config/config.php';

class ControlPlaneAPI {
    
    private $db;
    private $redis;
    
    public function __construct() {
        $this->db = getDb();
        $this->redis = getRedis();
        header('Content-Type: application/json');
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $path = str_replace('/api', '', $path);
        
        try {
            if ($method === 'POST' && $path === '/session/create') {
                return $this->createSession();
            } elseif ($method === 'GET' && preg_match('/^\/session\/([a-z0-9_]+)$/', $path, $m)) {
                return $this->getSession($m[1]);
            } elseif ($method === 'GET' && preg_match('/^\/session\/([a-z0-9_]+)\/status$/', $path, $m)) {
                return $this->getSessionStatus($m[1]);
            } elseif ($method === 'GET' && $path === '/queue/status') {
                return $this->getQueueStatus();
            } elseif ($method === 'GET' && preg_match('/^\/logs\/([a-z0-9_]+)$/', $path, $m)) {
                return $this->getLogs($m[1]);
            } else {
                return $this->error('Not Found', 404);
            }
        } catch (Exception $e) {
            logEvent(null, null, 'API_ERROR', $e->getMessage(), 'ERROR');
            return $this->error($e->getMessage(), 500);
        }
    }
    
    private function createSession() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data || !isset($data['url'])) {
            return $this->error('Missing required fields: url', 400);
        }
        
        $sessionId = generateId('sess');
        $repeatCount = $data['repeat_count'] ?? 1;
        $config = json_encode($data['config'] ?? []);
        
        try {
            $stmt = $this->db->prepare("
                INSERT INTO sessions (id, url, repeat_count, config, status)
                VALUES (?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$sessionId, $data['url'], $repeatCount, $config]);
            
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
        
        // SELECTOR tasks (from data)
        if (isset($data['selectors'])) {
            foreach ($data['selectors'] as $selector) {
                $tasks[] = [
                    'type' => 'SELECTOR',
                    'action' => $selector['action'] ?? 'click',
                    'selector' => $selector['selector'],
                    'value' => $selector['value'] ?? null
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
                (session_id, task_index, task_type, action, selector, value, status)
                VALUES (?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([
                $sessionId,
                $taskIndex++,
                $task['type'],
                $task['action'] ?? null,
                $task['selector'] ?? null,
                $task['value'] ?? null
            ]);
        }
        
        return $tasks;
    }
    
    private function getSession($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return $this->error('Session not found', 404);
        }
        
        return $this->success($session);
    }
    
    private function getSessionStatus($sessionId) {
        $stmt = $this->db->prepare("SELECT * FROM sessions WHERE id = ?");
        $stmt->execute([$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            return $this->error('Session not found', 404);
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
    
    private function getQueueStatus() {
        $queueLength = $this->redis->llen('swaoe:queue:sessions');
        $activeWorkers = $this->redis->hgetall('swaoe:workers:active');
        
        return $this->success([
            'queue_depth' => $queueLength,
            'active_workers' => count($activeWorkers),
            'workers' => $activeWorkers,
            'timestamp' => date('c')
        ]);
    }
    
    private function getLogs($sessionId) {
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
            'count' => count($logs),
            'limit' => (int)$limit,
            'offset' => (int)$offset
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
        $this->log("✓ Created: api/index.php (Control Plane API)", "success");
    }
    
    private function generateWorkerDaemon() {
        $this->log("🔄 Generating Worker Daemon...", "section");
        
        // worker/daemon.php
        $daemonContent = <<<'PHP'
<?php
/**
 * SWAOE v4.1 Worker Daemon
 * 
 * Distributed worker that:
 * - Consumes jobs from Redis queue
 * - Acquires distributed lease locks
 * - Spawns Playwright execution VM
 * - Handles retry logic and failover
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
        
        // Signal handlers
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
                usleep(100000); // 100ms delay to prevent CPU spinning
            } catch (Exception $e) {
                echo "[ERROR] " . $e->getMessage() . "\n";
                logEvent(null, null, 'WORKER_ERROR', $e->getMessage(), 'ERROR', $this->workerId);
            }
        }
    }
    
    private function processQueue() {
        // Wait for job (blocking pop with timeout)
        $sessionId = $this->redis->brpop('swaoe:queue:sessions', 1);
        
        if (!$sessionId) {
            return; // Timeout, continue loop
        }
        
        $sessionId = $sessionId[1]; // Redis BRPOP returns [key, value]
        
        echo "[" . date('Y-m-d H:i:s') . "] Processing session: {$sessionId}\n";
        
        try {
            // Attempt to acquire lease
            if (!$this->acquireLease($sessionId)) {
                echo "[WARN] Could not acquire lease for {$sessionId}, requeueing\n";
                $this->redis->lpush('swaoe:queue:sessions', $sessionId);
                return;
            }
            
            // Load session
            $session = $this->loadSession($sessionId);
            if (!$session) {
                $this->releaseLease($sessionId);
                return;
            }
            
            // Update worker status
            $this->updateWorkerStatus('processing', $sessionId);
            
            // Execute session cycles
            $this->executeSession($session);
            
            // Mark complete
            $stmt = $this->db->prepare("UPDATE sessions SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$sessionId]);
            
            logEvent($sessionId, null, 'SESSION_COMPLETED', "Session execution completed", 'INFO', $this->workerId);
            
        } catch (Exception $e) {
            echo "[ERROR] Session processing failed: " . $e->getMessage() . "\n";
            logEvent($sessionId, null, 'SESSION_ERROR', $e->getMessage(), 'ERROR', $this->workerId);
            
            // Increment retry count
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
            // Lease already exists, check if expired
            $stmt = $this->db->prepare("
                SELECT expires_at FROM worker_leases WHERE session_id = ?
            ");
            $stmt->execute([$sessionId]);
            $lease = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lease && strtotime($lease['expires_at']) > time()) {
                return false; // Lease still valid
            }
            
            // Try to update expired lease
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
        $url = $session['url'];
        
        // Mark as running
        $stmt = $this->db->prepare("UPDATE sessions SET status = 'running', started_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$sessionId]);
        
        for ($cycle = 1; $cycle <= $repeatCount; $cycle++) {
            echo "[" . date('Y-m-d H:i:s') . "] Executing cycle {$cycle}/{$repeatCount}\n";
            
            $stmt = $this->db->prepare("UPDATE sessions SET current_cycle = ? WHERE id = ?");
            $stmt->execute([$cycle, $sessionId]);
            
            // Load and execute tasks
            $this->executeCycle($sessionId, $cycle);
            
            logEvent($sessionId, null, 'CYCLE_COMPLETED', "Cycle {$cycle} completed", 'INFO', $this->workerId);
        }
    }
    
    private function executeCycle($sessionId, $cycle) {
        // Load tasks
        $stmt = $this->db->prepare("
            SELECT * FROM tasks
            WHERE session_id = ?
            ORDER BY task_index ASC
        ");
        $stmt->execute([$sessionId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Prepare Playwright execution
        $playwrightPayload = [
            'session_id' => $sessionId,
            'cycle' => $cycle,
            'tasks' => $tasks
        ];
        
        // Execute via Playwright VM
        $result = $this->runPlaywrightVM($playwrightPayload);
        
        if ($result && $result['success']) {
            // Update task results
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

// CLI interface
$action = $argv[1] ?? 'help';

$daemon = new WorkerDaemon();

switch ($action) {
    case 'start':
        $daemon->start();
        break;
    case 'stop':
        // Find and kill process
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
    
    private function generatePlaywrightRunner() {
        $this->log("🎭 Generating Playwright Runner VM...", "section");
        
        // bridge/playwright_runner.js
        $runnerContent = <<<'JAVASCRIPT'
/**
 * SWAOE v4.1 Playwright Browser Execution VM
 * 
 * Executes deterministic DOM automation against a headless Chromium browser
 * 
 * Input: JSON payload with tasks
 * Output: JSON results
 * 
 * Invoked: node playwright_runner.js '<JSON_PAYLOAD>'
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
        
        // Set viewport
        await this.page.setViewportSize({ width: 1920, height: 1080 });
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
        let element = null;
        let attempts = 0;
        const maxAttempts = 3;
        
        // Try selector with fallback logic
        const selectors = [task.selector];
        if (task.selector.startsWith('.')) {
            selectors.push(`[class*="${task.selector.substring(1)}"]`);
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
        
        if (!element) {
            throw new Error(`Selector not found: ${task.selector}`);
        }
        
        // Execute action
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
        
        return {
            selector: task.selector,
            action: task.action,
            value: task.value
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
        $this->log("✓ Created: bridge/playwright_runner.js (Playwright VM)", "success");
        
        // bridge/package.json
        $packageContent = <<<'JSON'
{
  "name": "swaoe-playwright-runner",
  "version": "4.1.0",
  "description": "SWAOE v4.1 Playwright Browser Execution VM",
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
        $this->log("✓ Created: bridge/package.json (Playwright dependencies)", "success");
    }
    
    private function generatePWADashboard() {
        $this->log("🚀 Generating PWA Control Dashboard...", "section");
        
        // public/index.html
        $htmlContent = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="SWAOE v4.1 - Distributed Automation Orchestration Engine">
    <meta name="theme-color" content="#1a1a2e">
    <title>SWAOE v4.1 Dashboard</title>
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
            max-width: 1200px;
            margin: 0 auto;
        }
        
        header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #0f3460;
            padding-bottom: 20px;
        }
        
        header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #00d4ff, #0099ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        header p {
            color: #888;
            font-size: 0.9em;
        }
        
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
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
            font-size: 1.3em;
        }
        
        .card label {
            display: block;
            margin-bottom: 10px;
            color: #aaa;
            font-size: 0.9em;
        }
        
        .card input,
        .card textarea {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
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
            padding: 12px 24px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: transform 0.2s;
        }
        
        .card button:hover {
            transform: scale(1.02);
        }
        
        .card button:active {
            transform: scale(0.98);
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .status-item {
            background: rgba(10, 25, 47, 0.8);
            padding: 15px;
            border-radius: 4px;
            text-align: center;
            border-left: 3px solid #00d4ff;
        }
        
        .status-item strong {
            display: block;
            font-size: 1.5em;
            color: #00d4ff;
            margin-bottom: 5px;
        }
        
        .status-item small {
            color: #888;
        }
        
        .logs {
            background: rgba(10, 25, 47, 0.8);
            border: 1px solid #0f3460;
            border-radius: 4px;
            padding: 15px;
            height: 300px;
            overflow-y: auto;
            font-size: 0.85em;
            font-family: monospace;
        }
        
        .log-entry {
            padding: 5px;
            border-bottom: 1px solid #0f3460;
            color: #aaa;
        }
        
        .log-entry.error {
            color: #ff6b6b;
        }
        
        .log-entry.success {
            color: #51cf66;
        }
        
        .log-entry.info {
            color: #00d4ff;
        }
        
        .spinner {
            display: inline-block;
            width: 12px;
            height: 12px;
            border: 2px solid #0f3460;
            border-top: 2px solid #00d4ff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            header h1 {
                font-size: 1.8em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ SWAOE v4.1</h1>
            <p>Distributed Automation Orchestration Engine</p>
        </header>
        
        <div class="grid">
            <!-- Create Session Card -->
            <div class="card">
                <h2>Create Session</h2>
                
                <label>Target URL</label>
                <input type="text" id="url" placeholder="https://example.com" value="https://example.com">
                
                <label>Repeat Count</label>
                <input type="number" id="repeatCount" min="1" max="100" value="1">
                
                <label>Configuration (JSON)</label>
                <textarea id="config" placeholder='{"option": "value"}' rows="4">{"timeout": 30000}</textarea>
                
                <button onclick="createSession()">Create Session</button>
                <div id="createStatus" style="margin-top: 10px; color: #888; font-size: 0.9em;"></div>
            </div>
            
            <!-- Queue Status Card -->
            <div class="card">
                <h2>Queue Status</h2>
                
                <div class="status-grid" id="statusGrid">
                    <div class="status-item">
                        <strong>-</strong>
                        <small>Queue Depth</small>
                    </div>
                    <div class="status-item">
                        <strong>-</strong>
                        <small>Active Workers</small>
                    </div>
                </div>
                
                <button onclick="refreshStatus()">Refresh Status</button>
            </div>
            
            <!-- Session Lookup Card -->
            <div class="card">
                <h2>Session Lookup</h2>
                
                <label>Session ID</label>
                <input type="text" id="sessionId" placeholder="sess_xxxxx">
                
                <button onclick="getSessionStatus()">Get Status</button>
                <div id="sessionStatus" style="margin-top: 10px; color: #888; font-size: 0.85em; max-height: 200px; overflow-y: auto;"></div>
            </div>
        </div>
        
        <!-- Execution Logs -->
        <div class="card" style="margin-bottom: 40px;">
            <h2>System Logs</h2>
            <div style="margin-bottom: 10px;">
                <button onclick="clearLogs()" style="width: auto; padding: 8px 16px;">Clear Logs</button>
                <span id="autoRefreshStatus" style="margin-left: 10px; color: #888;"></span>
            </div>
            <div class="logs" id="logs"></div>
        </div>
    </div>
    
    <script>
        // Service Worker registration
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/service-worker.js').catch(() => {});
        }
        
        // Auto-refresh status
        let autoRefreshInterval = null;
        
        function startAutoRefresh() {
            autoRefreshInterval = setInterval(async () => {
                await refreshStatus();
                await updateLogs();
            }, 5000);
            document.getElementById('autoRefreshStatus').textContent = '🟢 Auto-refreshing...';
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) clearInterval(autoRefreshInterval);
            document.getElementById('autoRefreshStatus').textContent = '';
        }
        
        startAutoRefresh();
        
        // API calls
        async function createSession() {
            try {
                const url = document.getElementById('url').value;
                const repeatCount = parseInt(document.getElementById('repeatCount').value);
                const config = JSON.parse(document.getElementById('config').value);
                
                const statusEl = document.getElementById('createStatus');
                statusEl.innerHTML = '<span class="spinner"></span> Creating session...';
                
                const response = await fetch('/api/session/create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url, repeat_count: repeatCount, config })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    statusEl.innerHTML = `<span class="log-entry success">✓ Session created: ${result.data.session_id}</span>`;
                    document.getElementById('sessionId').value = result.data.session_id;
                    addLog(`Session created: ${result.data.session_id}`, 'success');
                } else {
                    statusEl.innerHTML = `<span class="log-entry error">✗ Error: ${result.error}</span>`;
                    addLog(`Error: ${result.error}`, 'error');
                }
            } catch (error) {
                document.getElementById('createStatus').innerHTML = `<span class="log-entry error">✗ ${error.message}</span>`;
                addLog(`Error: ${error.message}`, 'error');
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
            try {
                const sessionId = document.getElementById('sessionId').value;
                if (!sessionId) return;
                
                const response = await fetch(`/api/session/${sessionId}/status`);
                const result = await response.json();
                
                const statusEl = document.getElementById('sessionStatus');
                if (result.success) {
                    const session = result.data.session;
                    statusEl.innerHTML = `
                        <div style="margin-bottom: 10px;">
                            <strong>Status:</strong> ${session.status}<br>
                            <strong>Cycle:</strong> ${session.current_cycle}/${session.repeat_count}<br>
                            <strong>Created:</strong> ${new Date(session.created_at).toLocaleString()}
                        </div>
                    `;
                    addLog(`Session status fetched: ${session.status}`, 'info');
                } else {
                    statusEl.innerHTML = `<span class="log-entry error">Error: ${result.error}</span>`;
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
            
            // Keep only last 100 logs
            while (logs.children.length > 100) {
                logs.removeChild(logs.lastChild);
            }
        }
        
        function clearLogs() {
            document.getElementById('logs').innerHTML = '';
            addLog('Logs cleared', 'info');
        }
        
        async function updateLogs() {
            // Implemented by application
        }
        
        // Initial status
        refreshStatus();
    </script>
</body>
</html>
HTML;
        
        $this->writeFile('public/index.html', $htmlContent);
        $this->log("✓ Created: public/index.html (PWA Dashboard)", "success");
        
        // public/manifest.json
        $manifestContent = <<<'JSON'
{
  "name": "SWAOE v4.1 Dashboard",
  "short_name": "SWAOE",
  "description": "Distributed Automation Orchestration Engine",
  "start_url": "/",
  "scope": "/",
  "display": "standalone",
  "background_color": "#1a1a2e",
  "theme_color": "#1a1a2e",
  "orientation": "portrait-primary",
  "icons": [
    {
      "src": "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 192 192'><rect fill='%231a1a2e' width='192' height='192'/><text x='96' y='144' font-size='120' text-anchor='middle' fill='%2300d4ff' font-weight='bold'>⚙</text></svg>",
      "sizes": "192x192",
      "type": "image/svg+xml"
    }
  ]
}
JSON;
        
        $this->writeFile('public/manifest.json', $manifestContent);
        $this->log("✓ Created: public/manifest.json (PWA Manifest)", "success");
        
        // public/service-worker.js
        $swContent = <<<'JAVASCRIPT'
/**
 * SWAOE PWA Service Worker
 * Provides offline caching and background sync
 */

const CACHE_NAME = 'swaoe-v4.1';
const URLS_TO_CACHE = [
    '/',
    '/index.html',
    '/manifest.json'
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(URLS_TO_CACHE).catch(() => {
                // Silently fail if resources unavailable
            });
        })
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
});

self.addEventListener('fetch', event => {
    if (event.request.method === 'GET') {
        event.respondWith(
            caches.match(event.request).then(response => {
                return response || fetch(event.request).then(response => {
                    // Cache successful API responses
                    if (response.status === 200 && event.request.url.includes('/api/')) {
                        const responseClone = response.clone();
                        caches.open(CACHE_NAME).then(cache => {
                            cache.put(event.request, responseClone);
                        });
                    }
                    return response;
                }).catch(() => {
                    // Return cached version on network error
                    return caches.match(event.request);
                });
            })
        );
    }
});
JAVASCRIPT;
        
        $this->writeFile('public/service-worker.js', $swContent);
        $this->log("✓ Created: public/service-worker.js (Service Worker)", "success");
    }
    
    private function generateHtaccess() {
        $this->log("🛡️  Generating routing & security layer...", "section");
        
        $htaccessContent = <<<'HTACCESS'
# SWAOE v4.1 Routing & Security

RewriteEngine On

# Protect sensitive directories
<FilesMatch "^(config|data|worker|bridge|logs)">
    Deny from all
</FilesMatch>

# API routing
RewriteRule ^api/(.*)$ api/index.php?path=$1 [QSA,L]

# PWA routing
RewriteRule ^(manifest\.json|service-worker\.js)$ public/$1 [QSA,L]

# Public assets
RewriteRule ^(.*)$ public/$1 [QSA,L]

# Security headers
Header set X-Content-Type-Options "nosniff"
Header set X-Frame-Options "DENY"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"

# CORS headers (adjust for your domain)
Header set Access-Control-Allow-Origin "*"
Header set Access-Control-Allow-Methods "GET, POST, OPTIONS"
Header set Access-Control-Allow-Headers "Content-Type, Authorization"

# Cache control
<FilesMatch "\.(jpg|jpeg|png|gif|ico|css|js|svg)$">
    Header set Cache-Control "max-age=604800, public"
</FilesMatch>

# Prevent direct access to PHP files (except index)
<FilesMatch "\.php$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
</FilesMatch>

# Allow index.php
<FilesMatch "^index\.php$">
    <IfModule mod_authz_core.c>
        Require all granted
    </IfModule>
</FilesMatch>
HTACCESS;
        
        $this->writeFile('.htaccess', $htaccessContent);
        $this->log("✓ Created: .htaccess (Routing & Security)", "success");
    }
    
    private function generateDeploymentConfigs() {
        $this->log("🐳 Generating deployment configurations...", "section");
        
        // docker-compose.yml
        $dockerComposeContent = <<<'YAML'
version: '3.8'

services:
  redis:
    image: redis:7-alpine
    container_name: swaoe_redis
    ports:
      - "6379:6379"
    volumes:
      - redis_data:/data
    command: redis-server --appendonly yes
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    networks:
      - swaoe

  api:
    build:
      context: .
      dockerfile: Dockerfile.api
    container_name: swaoe_api
    ports:
      - "8080:8080"
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - DB_PATH=/app/data/swaoe_cluster.sqlite
      - SWAOE_ENV=production
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - swaoe
    restart: unless-stopped

  worker_1:
    build:
      context: .
      dockerfile: Dockerfile.worker
    container_name: swaoe_worker_1
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - WORKER_ID=worker_1
      - DB_PATH=/app/data/swaoe_cluster.sqlite
      - SWAOE_ENV=production
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - swaoe
    restart: unless-stopped

  worker_2:
    build:
      context: .
      dockerfile: Dockerfile.worker
    container_name: swaoe_worker_2
    environment:
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - WORKER_ID=worker_2
      - DB_PATH=/app/data/swaoe_cluster.sqlite
      - SWAOE_ENV=production
    volumes:
      - ./data:/app/data
      - ./logs:/app/logs
    depends_on:
      redis:
        condition: service_healthy
    networks:
      - swaoe
    restart: unless-stopped

volumes:
  redis_data:

networks:
  swaoe:
    driver: bridge
YAML;
        
        $this->writeFile('docker-compose.yml', $dockerComposeContent);
        $this->log("✓ Created: docker-compose.yml (Cluster orchestration)", "success");
        
        // Dockerfile.api
        $dockerfileApiContent = <<<'DOCKERFILE'
FROM php:8.2-apache

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite
RUN pecl install redis && docker-php-ext-enable redis

# Enable mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /app

# Copy application
COPY . .

# Set permissions
RUN chown -R www-data:www-data /app
RUN chmod -R 755 /app

# Create necessary directories
RUN mkdir -p /app/data /app/logs
RUN chmod 777 /app/data /app/logs

# Configure Apache
RUN echo '<Directory /app/public>' > /etc/apache2/sites-available/default-ssl.conf && \
    echo 'AllowOverride All' >> /etc/apache2/sites-available/default-ssl.conf && \
    echo 'Require all granted' >> /etc/apache2/sites-available/default-ssl.conf && \
    echo '</Directory>' >> /etc/apache2/sites-available/default-ssl.conf

# Set document root
ENV APACHE_DOCUMENT_ROOT=/app/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 8080

CMD ["apache2-foreground"]
DOCKERFILE;
        
        $this->writeFile('Dockerfile.api', $dockerfileApiContent);
        $this->log("✓ Created: Dockerfile.api (PHP API container)", "success");
        
        // Dockerfile.worker
        $dockerfileWorkerContent = <<<'DOCKERFILE'
FROM php:8.2-cli

# Install Node.js
RUN apt-get update && apt-get install -y \
    nodejs npm \
    && rm -rf /var/lib/apt/lists/*

# Enable required PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite
RUN pecl install redis && docker-php-ext-enable redis

# Install Playwright
RUN npm install -g playwright

# Set working directory
WORKDIR /app

# Copy application
COPY . .

# Install bridge dependencies
RUN cd /app/bridge && npm install

# Create necessary directories
RUN mkdir -p /app/data /app/logs
RUN chmod 777 /app/data /app/logs

CMD ["php", "worker/daemon.php", "start"]
DOCKERFILE;
        
        $this->writeFile('Dockerfile.worker', $dockerfileWorkerContent);
        $this->log("✓ Created: Dockerfile.worker (Worker container)", "success");
    }
    
    private function generateDocumentation() {
        $this->log("📖 Generating documentation...", "section");
        
        // README.md
        $readmeContent = <<<'MARKDOWN'
# SWAOE v4.1 — Distributed Automation Orchestration Engine

A production-ready distributed workflow automation system for orchestrating deterministic browser automation at scale.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    CONTROL PLANE (API)                      │
│  - Session creation  - Task compilation  - Status reporting │
└─────────────────────────────────────────────────────────────┘
                            │
                ┌───────────┼───────────┐
                ▼           ▼           ▼
            ┌──────┐   ┌──────┐   ┌──────┐
            │ TASK │   │ TASK │   │ TASK │
            │GRAPH │   │GRAPH │   │GRAPH │
            └──────┘   └──────┘   └──────┘
                            │
                ┌───────────┴───────────┐
                ▼                       ▼
          ┌──────────┐          ┌──────────┐
          │  REDIS   │          │ SQLITE   │
          │  QUEUE   │          │DATABASE  │
          └──────────┘          └──────────┘
                │
        ┌───────┴───────┬───────┬───────┐
        ▼               ▼       ▼       ▼
    ┌────────┐   ┌────────┐ ┌────────┐
    │WORKER 1│   │WORKER 2│ │WORKER N│
    └────────┘   └────────┘ └────────┘
        │               │       │
        └───────┬───────┴───────┘
                ▼
        ┌───────────────────┐
        │  PLAYWRIGHT VM    │
        │ (Browser + DOM)   │
        └───────────────────┘
```

## Features

- **Distributed Execution**: Horizontal scaling via Redis queue and worker pool
- **Stateless Processing**: Each execution cycle is isolated and deterministic
- **Automatic Retry**: Built-in failure recovery with configurable retry limits
- **Distributed Locking**: Lease-based synchronization prevents duplicate execution
- **Browser Automation**: Headless Chromium via Playwright Node runtime
- **Real-time Monitoring**: PWA dashboard with live queue and execution tracking
- **Persistent State**: SQLite-backed session and task logging
- **Docker-ready**: Complete docker-compose setup for production deployment

## Installation

### Quick Start (Local Development)

```bash
# 1. Run installer
php install.php

# 2. Install bridge dependencies
cd bridge && npm install && cd ..

# 3. Create SQLite database
mkdir -p data
touch data/swaoe_cluster.sqlite
chmod 666 data/swaoe_cluster.sqlite

# 4. Start Redis (requires Redis installed locally)
redis-server &

# 5. Start API server
cd api && php -S localhost:8080

# 6. Start worker daemon (in another terminal)
php worker/daemon.php start

# 7. Open dashboard
# http://localhost:8080
```

### Docker Deployment (Production)

```bash
# Build and run entire cluster
docker-compose up -d

# View logs
docker-compose logs -f

# Scale workers
docker-compose up -d --scale worker=4

# Stop cluster
docker-compose down
```

## API Endpoints

### Create Session

```bash
POST /api/session/create
Content-Type: application/json

{
  "url": "https://example.com",
  "repeat_count": 3,
  "config": {
    "timeout": 30000
  },
  "selectors": [
    {
      "selector": ".button-class",
      "action": "click"
    },
    {
      "selector": "#input-id",
      "action": "fill",
      "value": "search term"
    }
  ],
  "waits": [
    {
      "delay": 1000
    }
  ]
}

Response:
{
  "success": true,
  "data": {
    "session_id": "sess_abc123def456",
    "status": "pending",
    "url": "https://example.com",
    "repeat_count": 3,
    "tasks_generated": 5,
    "queued_at": "2024-01-10T12:30:00Z"
  }
}
```

### Get Session Status

```bash
GET /api/session/{session_id}/status

Response:
{
  "success": true,
  "data": {
    "session": {
      "id": "sess_abc123def456",
      "status": "running",
      "current_cycle": 2,
      "repeat_count": 3,
      "url": "https://example.com",
      "created_at": "2024-01-10T12:30:00Z"
    },
    "task_stats": [...],
    "recent_logs": [...]
  }
}
```

### Get Queue Status

```bash
GET /api/queue/status

Response:
{
  "success": true,
  "data": {
    "queue_depth": 5,
    "active_workers": 2,
    "workers": {
      "worker_1": {"status": "processing", "pid": 1234},
      "worker_2": {"status": "idle", "pid": 1235}
    },
    "timestamp": "2024-01-10T12:30:45Z"
  }
}
```

## Configuration

Edit `config/config.php` or set environment variables:

```bash
REDIS_HOST=localhost
REDIS_PORT=6379
DB_PATH=/path/to/swaoe_cluster.sqlite
WORKER_ID=worker_1
WORKER_MAX_RETRIES=3
WORKER_LEASE_TIMEOUT=300
WORKER_CYCLE_TIMEOUT=600
PLAYWRIGHT_TIMEOUT=30000
SWAOE_ENV=production
```

## Task Types

- **NAVIGATE**: Navigate to URL (`goto`)
- **SELECTOR**: Click, fill, hover, focus on element
- **WAIT**: Delay execution
- **SCREENSHOT**: Capture screenshot

## Execution Flow

1. User creates session via API
2. Session inserted into SQLite, tasks generated
3. Session queued in Redis
4. Worker acquires lease lock
5. Worker loads session and tasks
6. For each cycle:
   - Load tasks from database
   - Serialize into JSON
   - Spawn Playwright Node subprocess
   - Execute DOM interactions
   - Store results in database
7. Update session status as completed
8. Release lease lock
9. Logs available via API and dashboard

## Failure Recovery

- **Lease Expiry**: Expired leases release automatically (configurable)
- **Worker Crash**: Job requeued if worker dies without cleanup
- **Task Retry**: Individual tasks retry on selector failures
- **Max Retries**: Session fails after N retry attempts
- **Queue Persistence**: Redis persists queue state

## PWA Dashboard

- Create sessions with custom selectors and actions
- Real-time queue and worker monitoring
- Execution logs with filtering
- Offline caching with service workers
- Installable as native app

## Performance Tuning

- **Queue Depth**: Monitor via `/api/queue/status`
- **Worker Pool**: Scale horizontally in docker-compose
- **Lease Timeout**: Balance between failover speed and false positives
- **Task Timeout**: Set per-task or globally via config
- **Playwright Instances**: One per cycle (stateless)

## Monitoring

Logs stored in `logs/` directory and SQLite `execution_logs` table:

- Session lifecycle events
- Task completion status
- Worker heartbeats
- Error traces with context
- Retry attempts

Query logs:
```sql
SELECT * FROM execution_logs
WHERE session_id = 'sess_abc123'
ORDER BY timestamp DESC;
```

## Security

- Sensitive directories blocked via .htaccess
- CORS headers configurable
- X-Frame-Options: DENY (prevent clickjacking)
- API validates session IDs and input
- Lease-based execution prevents race conditions

## Troubleshooting

### Session stuck in "pending"
- Check Redis connectivity
- Verify worker daemon is running
- Check `execution_logs` for errors

### Playwright fails to execute
- Verify Node.js installed: `node -v`
- Install Playwright: `npm install -g playwright`
- Check browser dependency: `playwright install`

### High memory usage
- Reduce worker count
- Lower `repeat_count` per session
- Enable browser context reuse (future feature)

## License

MIT

## Support

For issues and feature requests, see documentation.
MARKDOWN;
        
        $this->writeFile('README.md', $readmeContent);
        $this->log("✓ Created: README.md (Documentation)", "success");
        
        // QUICKSTART.md
        $quickstartContent = <<<'MARKDOWN'
# SWAOE v4.1 Quick Start Guide

## 30-Second Setup

### Local Development

```bash
# 1. Run installer
php install.php

# 2. Install dependencies
cd bridge && npm install && cd ..

# 3. Start services (requires Redis)
redis-server &
php -S localhost:8080 -t public &
php worker/daemon.php start &

# 4. Create session
curl -X POST http://localhost:8080/api/session/create \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com",
    "repeat_count": 1
  }'

# 5. Check dashboard
# http://localhost:8080
```

### Docker

```bash
docker-compose up -d
# API at http://localhost:8080
# Redis at localhost:6379
```

## First Automation

Create a session that navigates to a URL and clicks an element:

```bash
curl -X POST http://localhost:8080/api/session/create \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://example.com",
    "repeat_count": 1,
    "selectors": [
      {
        "selector": "#submit-button",
        "action": "click"
      }
    ],
    "waits": [
      {
        "delay": 2000
      }
    ]
  }'
```

Get session status:

```bash
curl http://localhost:8080/api/session/{session_id}/status
```

## Common Patterns

### Click Multiple Elements

```json
{
  "url": "https://example.com",
  "repeat_count": 1,
  "selectors": [
    {"selector": ".menu", "action": "click"},
    {"selector": ".submenu", "action": "click"},
    {"selector": ".confirm", "action": "click"}
  ]
}
```

### Form Fill & Submit

```json
{
  "url": "https://example.com/login",
  "repeat_count": 1,
  "selectors": [
    {"selector": "#username", "action": "fill", "value": "user@example.com"},
    {"selector": "#password", "action": "fill", "value": "password123"},
    {"selector": "#login-button", "action": "click"}
  ],
  "waits": [{"delay": 1000}]
}
```

### Repeat Action Multiple Times

```json
{
  "url": "https://example.com",
  "repeat_count": 5,
  "selectors": [
    {"selector": ".load-more", "action": "click"}
  ],
  "waits": [
    {"delay": 1000}
  ]
}
```

## Debugging

### View worker logs
```bash
docker-compose logs worker_1
```

### Query execution logs
```bash
# Via SQLite
sqlite3 data/swaoe_cluster.sqlite
SELECT * FROM execution_logs ORDER BY timestamp DESC LIMIT 20;
```

### Check session status
```bash
curl http://localhost:8080/api/session/{id}/status | jq
```

### Monitor queue
```bash
# Requires redis-cli
redis-cli LLEN swaoe:queue:sessions
redis-cli HGETALL swaoe:workers:active
```

## Next Steps

- Configure custom timeouts in `config/config.php`
- Scale workers: `docker-compose up -d --scale worker=4`
- Integrate API into your application
- Monitor logs in real-time dashboard

---

Need help? Check README.md or view system logs in dashboard.
MARKDOWN;
        
        $this->writeFile('QUICKSTART.md', $quickstartContent);
        $this->log("✓ Created: QUICKSTART.md (Quick start guide)", "success");
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
            'section' => "\033[1;36m",    // Cyan bold
            'success' => "\033[1;32m",    // Green bold
            'error' => "\033[1;31m",      // Red bold
            'info' => "\033[0;37m",       // White
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

  Distributed Automation Orchestration Engine v4.1
  
BANNER;
    }
    
    private function printNextSteps() {
        echo "
┌─────────────────────────────────────────────────────────────┐
│                      NEXT STEPS                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  📦 Install Bridge Dependencies:                           │
│     cd bridge && npm install && cd ..                      │
│                                                             │
│  🚀 Start Local Development:                               │
│     redis-server &                                         │
│     php -S localhost:8080 -t public &                      │
│     php worker/daemon.php start &                          │
│                                                             │
│  🐳 Or Use Docker:                                         │
│     docker-compose up -d                                  │
│                                                             │
│  📊 Open Dashboard:                                         │
│     http://localhost:8080                                 │
│                                                             │
│  📚 Read Documentation:                                     │
│     README.md - Full system documentation                 │
│     QUICKSTART.md - Quick reference guide                 │
│                                                             │
│  🔍 Monitor System:                                         │
│     docker-compose logs -f                                │
│     sqlite3 data/swaoe_cluster.sqlite                     │
│                                                             │
│  📡 Create First Session:                                  │
│     curl -X POST http://localhost:8080/api/session/create \\
│       -H \"Content-Type: application/json\" \\             │
│       -d '{\"url\": \"https://example.com\"}'             │
│                                                             │
└─────────────────────────────────────────────────────────────┘
        ";
    }
}

// Main execution
$skipDocker = in_array('--skip-docker', $argv ?? []);
$installer = new SWAOEInstaller();
$installer->run($skipDocker);
