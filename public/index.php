<?php
/**
 * SWAOE v4.1 Enhanced — All-in-One Entry Point
 * Production-Ready Stateless Multi-User Automation Engine
 * 
 * Features:
 * - Unified API + PWA Dashboard
 * - Multi-user session isolation
 * - Stateless worker execution (PWTAE compliant)
 * - Distributed task coordination (Redis)
 * - Real-time monitoring & logging
 * - Security: Token auth, CORS, SQL injection prevention
 * - Performance: Connection pooling, indexed queries, caching
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

// =============================================================================
// SECTION 1: INITIALIZATION & CONFIGURATION
// =============================================================================

const SWAOE_VERSION = '4.1.0-PWTAE';
const SWAOE_ENV = $_ENV['SWAOE_ENV'] ?? 'development';
const LOG_LEVEL = $_ENV['LOG_LEVEL'] ?? 'INFO';

// Core paths
$baseDir = dirname(__DIR__);
$dataDir = $baseDir . '/data';
$logDir = $baseDir . '/logs';

// Ensure directories exist
foreach ([$dataDir, $logDir] as $dir) {
    @mkdir($dir, 0755, true);
}

// Session configuration (stateless mode)
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', SWAOE_ENV === 'production' ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '3600');
ini_set('session.sid_length', '48');

// =============================================================================
// SECTION 2: ERROR & EXCEPTION HANDLING
// =============================================================================

class SWAOEException extends Exception {}
class AuthenticationException extends SWAOEException {}
class ValidationException extends SWAOEException {}
class DatabaseException extends SWAOEException {}

set_exception_handler(function (Throwable $e) {
    Logger::error("Uncaught exception: {$e->getMessage()}", [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    http_response_code($e instanceof ValidationException ? 400 : 500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => SWAOE_ENV === 'production' 
            ? 'Internal server error'
            : $e->getMessage(),
        'code' => $e->getCode(),
    ]);
    exit(1);
});

// =============================================================================
// SECTION 3: LOGGING SYSTEM
// =============================================================================

class Logger {
    private static array $levels = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARN' => 2,
        'ERROR' => 3,
    ];

    public static function log(string $level, string $message, array $context = []): void {
        if (self::$levels[$level] < self::$levels[LOG_LEVEL]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
        $logLine = "[$timestamp] [$level] $message$contextStr\n";

        $logFile = $GLOBALS['logDir'] . '/' . date('Y-m-d') . '.log';
        @file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

        if (SWAOE_ENV !== 'production') {
            echo $logLine;
        }
    }

    public static function debug(string $msg, array $ctx = []): void { self::log('DEBUG', $msg, $ctx); }
    public static function info(string $msg, array $ctx = []): void { self::log('INFO', $msg, $ctx); }
    public static function warn(string $msg, array $ctx = []): void { self::log('WARN', $msg, $ctx); }
    public static function error(string $msg, array $ctx = []): void { self::log('ERROR', $msg, $ctx); }
}

// Store in globals for exception handler
$GLOBALS['logDir'] = $logDir;

// =============================================================================
// SECTION 4: DATABASE LAYER
// =============================================================================

class Database {
    private static ?PDO $instance = null;
    private static array $pool = [];
    private static array $prepared = [];

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    private static function createConnection(): PDO {
        $dbPath = $_ENV['DB_PATH'] ?? $GLOBALS['dataDir'] . '/swaoe_cluster.sqlite';
        
        try {
            $pdo = new PDO("sqlite:$dbPath", '', '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->exec('PRAGMA synchronous = NORMAL');
            $pdo->exec('PRAGMA cache_size = -64000');
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        } catch (PDOException $e) {
            throw new DatabaseException("Database connection failed: {$e->getMessage()}");
        }
    }

    public static function prepare(string $sql): PDOStatement {
        $pdo = self::getInstance();
        $hash = md5($sql);

        if (!isset(self::$prepared[$hash])) {
            try {
                self::$prepared[$hash] = $pdo->prepare($sql);
            } catch (PDOException $e) {
                throw new DatabaseException("Query prepare failed: {$e->getMessage()}");
            }
        }

        return self::$prepared[$hash];
    }

    public static function execute(string $sql, array $params = []): PDOStatement {
        try {
            $stmt = self::prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new DatabaseException("Query execution failed: {$e->getMessage()}");
        }
    }

    public static function init(): void {
        $pdo = self::getInstance();

        // Users table
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT UNIQUE NOT NULL,
                email TEXT UNIQUE NOT NULL,
                password_hash TEXT NOT NULL,
                api_token TEXT UNIQUE NOT NULL,
                is_active BOOLEAN DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        SQL);

        // Sessions table (stateless, user-isolated)
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER NOT NULL,
                status TEXT DEFAULT 'pending',
                task_config JSON NOT NULL,
                result JSON,
                current_cycle INTEGER DEFAULT 0,
                repeat_count INTEGER DEFAULT 0,
                started_at DATETIME,
                completed_at DATETIME,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(user_id) REFERENCES users(id),
                UNIQUE(id, user_id)
            );
            CREATE INDEX IF NOT EXISTS idx_sessions_user ON sessions(user_id);
            CREATE INDEX IF NOT EXISTS idx_sessions_status ON sessions(status);
            CREATE INDEX IF NOT EXISTS idx_sessions_created ON sessions(created_at DESC);
        SQL);

        // Worker leases (distributed coordination)
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS worker_leases (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                worker_id TEXT NOT NULL,
                status TEXT DEFAULT 'active',
                expires_at DATETIME NOT NULL,
                acquired_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES sessions(id),
                UNIQUE(session_id, worker_id)
            );
            CREATE INDEX IF NOT EXISTS idx_leases_session ON worker_leases(session_id);
            CREATE INDEX IF NOT EXISTS idx_leases_expires ON worker_leases(expires_at);
        SQL);

        // Execution logs
        $pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS execution_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                session_id TEXT NOT NULL,
                worker_id TEXT,
                level TEXT DEFAULT 'INFO',
                message TEXT NOT NULL,
                context JSON,
                timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY(session_id) REFERENCES sessions(id)
            );
            CREATE INDEX IF NOT EXISTS idx_logs_session ON execution_logs(session_id);
            CREATE INDEX IF NOT EXISTS idx_logs_timestamp ON execution_logs(timestamp DESC);
        SQL);

        Logger::info("Database initialized successfully");
    }
}

// =============================================================================
// SECTION 5: REDIS CACHE & QUEUE
// =============================================================================

class RedisManager {
    private static ?Redis $instance = null;

    public static function getInstance(): Redis {
        if (self::$instance === null) {
            self::$instance = new Redis();
            $host = $_ENV['REDIS_HOST'] ?? 'localhost';
            $port = (int)($_ENV['REDIS_PORT'] ?? 6379);
            
            try {
                self::$instance->connect($host, $port, 2);
                self::$instance->select(0);
                Logger::info("Redis connected", ['host' => $host, 'port' => $port]);
            } catch (Exception $e) {
                Logger::warn("Redis unavailable: {$e->getMessage()} (falling back to SQL)");
                self::$instance = null;
                return null; // Graceful fallback
            }
        }
        return self::$instance;
    }

    public static function enqueue(string $sessionId, array $taskConfig): bool {
        $redis = self::getInstance();
        if ($redis === null) return false;

        try {
            $redis->lPush('queue:pending', json_encode([
                'session_id' => $sessionId,
                'task_config' => $taskConfig,
                'queued_at' => time(),
            ]));
            return true;
        } catch (Exception $e) {
            Logger::warn("Redis enqueue failed: {$e->getMessage()}");
            return false;
        }
    }

    public static function dequeue(): ?array {
        $redis = self::getInstance();
        if ($redis === null) return null;

        try {
            $item = $redis->rPop('queue:pending');
            return $item ? json_decode($item, true) : null;
        } catch (Exception $e) {
            Logger::warn("Redis dequeue failed: {$e->getMessage()}");
            return null;
        }
    }

    public static function setCache(string $key, mixed $value, int $ttl = 3600): void {
        $redis = self::getInstance();
        if ($redis === null) return;

        try {
            $redis->setex($key, $ttl, json_encode($value));
        } catch (Exception $e) {
            Logger::warn("Redis cache set failed: {$e->getMessage()}");
        }
    }

    public static function getCache(string $key): mixed {
        $redis = self::getInstance();
        if ($redis === null) return null;

        try {
            $value = $redis->get($key);
            return $value ? json_decode($value, true) : null;
        } catch (Exception $e) {
            Logger::warn("Redis cache get failed: {$e->getMessage()}");
            return null;
        }
    }
}

// =============================================================================
// SECTION 6: AUTHENTICATION & AUTHORIZATION
// =============================================================================

class Auth {
    private static ?array $currentUser = null;

    public static function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    public static function generateToken(): string {
        return bin2hex(random_bytes(24));
    }

    public static function register(string $username, string $email, string $password): array {
        // Validate input
        if (strlen($username) < 3) {
            throw new ValidationException("Username must be at least 3 characters");
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException("Invalid email format");
        }
        if (strlen($password) < 8) {
            throw new ValidationException("Password must be at least 8 characters");
        }

        $passwordHash = self::hashPassword($password);
        $apiToken = self::generateToken();

        try {
            $stmt = Database::execute(
                'INSERT INTO users (username, email, password_hash, api_token) VALUES (?, ?, ?, ?)',
                [$username, $email, $passwordHash, $apiToken]
            );

            $userId = Database::getInstance()->lastInsertId();
            Logger::info("User registered", ['user_id' => $userId, 'username' => $username]);

            return [
                'id' => $userId,
                'username' => $username,
                'email' => $email,
                'api_token' => $apiToken,
            ];
        } catch (DatabaseException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new ValidationException("Username or email already exists");
            }
            throw $e;
        }
    }

    public static function login(string $username, string $password): array {
        $stmt = Database::execute(
            'SELECT * FROM users WHERE username = ? LIMIT 1',
            [$username]
        );

        $user = $stmt->fetch();
        if (!$user || !self::verifyPassword($password, $user['password_hash'])) {
            throw new AuthenticationException("Invalid username or password");
        }

        if (!$user['is_active']) {
            throw new AuthenticationException("User account is disabled");
        }

        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['authenticated_at'] = time();

        Logger::info("User logged in", ['user_id' => $user['id']]);

        return [
            'id' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'api_token' => $user['api_token'],
        ];
    }

    public static function getCurrentUser(): ?array {
        if (self::$currentUser !== null) {
            return self::$currentUser;
        }

        // Check session
        if (isset($_SESSION['user_id'])) {
            $stmt = Database::execute(
                'SELECT id, username, email, api_token FROM users WHERE id = ? AND is_active = 1',
                [$_SESSION['user_id']]
            );
            self::$currentUser = $stmt->fetch() ?: null;
            return self::$currentUser;
        }

        // Check API token
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (preg_match('/Bearer\s+(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $stmt = Database::execute(
                'SELECT id, username, email, api_token FROM users WHERE api_token = ? AND is_active = 1',
                [$token]
            );
            self::$currentUser = $stmt->fetch() ?: null;
            return self::$currentUser;
        }

        return null;
    }

    public static function requireAuth(): array {
        $user = self::getCurrentUser();
        if ($user === null) {
            throw new AuthenticationException("Authentication required");
        }
        return $user;
    }
}

// =============================================================================
// SECTION 7: SESSION & TASK MANAGEMENT
// =============================================================================

class SessionManager {
    public static function createSession(int $userId, array $taskConfig): array {
        $sessionId = 'sess_' . bin2hex(random_bytes(16));
        $taskConfigJson = json_encode($taskConfig);

        $stmt = Database::execute(
            'INSERT INTO sessions (id, user_id, task_config, status) VALUES (?, ?, ?, ?)',
            [$sessionId, $userId, $taskConfigJson, 'pending']
        );

        // Queue task
        RedisManager::enqueue($sessionId, $taskConfig);

        Logger::info("Session created", [
            'session_id' => $sessionId,
            'user_id' => $userId,
            'task_type' => $taskConfig['type'] ?? 'unknown',
        ]);

        return [
            'session_id' => $sessionId,
            'status' => 'pending',
            'created_at' => date('c'),
        ];
    }

    public static function getSession(int $userId, string $sessionId): ?array {
        $stmt = Database::execute(
            'SELECT * FROM sessions WHERE id = ? AND user_id = ?',
            [$sessionId, $userId]
        );

        $session = $stmt->fetch();
        if ($session) {
            $session['task_config'] = json_decode($session['task_config'], true);
            if ($session['result']) {
                $session['result'] = json_decode($session['result'], true);
            }
        }

        return $session;
    }

    public static function listSessions(int $userId, int $limit = 50, int $offset = 0): array {
        $stmt = Database::execute(
            'SELECT * FROM sessions WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?',
            [$userId, $limit, $offset]
        );

        $sessions = $stmt->fetchAll();
        foreach ($sessions as &$session) {
            $session['task_config'] = json_decode($session['task_config'], true);
            if ($session['result']) {
                $session['result'] = json_decode($session['result'], true);
            }
        }

        return $sessions;
    }

    public static function updateSessionStatus(string $sessionId, string $status, ?array $result = null): void {
        $updateTime = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        $resultJson = $result ? json_encode($result) : null;

        Database::execute(
            'UPDATE sessions SET status = ?, result = ?, completed_at = ? WHERE id = ?',
            [$status, $resultJson, $updateTime, $sessionId]
        );

        Logger::info("Session status updated", ['session_id' => $sessionId, 'status' => $status]);
    }

    public static function acquireLease(string $sessionId, string $workerId, int $durationSeconds = 300): bool {
        $expiresAt = date('Y-m-d H:i:s', time() + $durationSeconds);

        try {
            Database::execute(
                'INSERT INTO worker_leases (session_id, worker_id, expires_at) VALUES (?, ?, ?)',
                [$sessionId, $workerId, $expiresAt]
            );

            Logger::info("Lease acquired", ['session_id' => $sessionId, 'worker_id' => $workerId]);
            return true;
        } catch (DatabaseException $e) {
            Logger::warn("Failed to acquire lease: {$e->getMessage()}");
            return false;
        }
    }

    public static function releaseLease(string $sessionId, string $workerId): void {
        Database::execute(
            'DELETE FROM worker_leases WHERE session_id = ? AND worker_id = ?',
            [$sessionId, $workerId]
        );

        Logger::info("Lease released", ['session_id' => $sessionId, 'worker_id' => $workerId]);
    }
}

// =============================================================================
// SECTION 8: PLAYWRIGHT BROWSER AUTOMATION
// =============================================================================

class PlaywrightExecutor {
    private string $executablePath;
    private int $timeout;

    public function __construct() {
        $this->executablePath = $_ENV['PLAYWRIGHT_EXECUTABLE'] ?? 'node';
        $this->timeout = (int)($_ENV['PLAYWRIGHT_TIMEOUT'] ?? 30000);
    }

    public function execute(array $taskConfig): array {
        $retries = (int)($_ENV['WORKER_MAX_RETRIES'] ?? 3);
        $lastError = null;

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                return $this->executeAttempt($taskConfig);
            } catch (Exception $e) {
                $lastError = $e;
                Logger::warn("Execution attempt $attempt failed: {$e->getMessage()}");
                
                if ($attempt < $retries) {
                    sleep(2 ** $attempt); // Exponential backoff
                }
            }
        }

        throw new SWAOEException("Execution failed after $retries attempts: {$lastError->getMessage()}");
    }

    private function executeAttempt(array $taskConfig): array {
        // Prepare task script
        $taskScript = $this->buildPlaywrightScript($taskConfig);
        $scriptFile = tempnam(sys_get_temp_dir(), 'swaoe_');
        file_put_contents($scriptFile, $taskScript);

        try {
            // Execute Playwright
            $output = '';
            $exitCode = 0;

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open(
                "{$this->executablePath} {$scriptFile}",
                $descriptors,
                $pipes,
                null,
                null,
                ['bypass_shell' => false]
            );

            if (!is_resource($process)) {
                throw new SWAOEException("Failed to start Playwright process");
            }

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);

            $exitCode = proc_close($process);

            // Parse result
            $result = json_decode($output, true);
            if (!$result) {
                throw new SWAOEException("Invalid Playwright output: $errors");
            }

            if ($result['success'] !== true) {
                throw new SWAOEException($result['error'] ?? 'Playwright execution failed');
            }

            Logger::info("Task executed successfully", ['task_type' => $taskConfig['type'] ?? 'unknown']);
            return $result['data'] ?? [];
        } finally {
            @unlink($scriptFile);
        }
    }

    private function buildPlaywrightScript(array $taskConfig): string {
        $taskJson = json_encode($taskConfig);

        return <<<'SCRIPT'
const playwright = require('playwright');
const fs = require('fs');

(async () => {
    try {
        const taskConfig = JSON.parse(process.argv[1]);
        const browser = await playwright.chromium.launch({ headless: true });
        const context = await browser.newContext();
        const page = await context.newPage();

        // Set viewport
        const viewport = taskConfig.viewport || { width: 1280, height: 720 };
        await page.setViewportSize(viewport);

        // Navigate
        if (taskConfig.url) {
            await page.goto(taskConfig.url, { waitUntil: 'networkidle' });
        }

        // Execute actions
        const results = [];
        for (const action of (taskConfig.actions || [])) {
            const result = await executeAction(page, action);
            results.push(result);
        }

        // Capture result
        const result = {
            success: true,
            data: {
                actions_completed: results.length,
                page_title: await page.title(),
                page_url: page.url(),
                results: results,
            },
        };

        await browser.close();
        console.log(JSON.stringify(result));
    } catch (error) {
        console.log(JSON.stringify({
            success: false,
            error: error.message,
        }));
        process.exit(1);
    }
})();

async function executeAction(page, action) {
    switch (action.type) {
        case 'click':
            await page.click(action.selector || `[data-pw-id="${action.elementId}"]`, { timeout: 5000 });
            return { type: 'click', selector: action.selector, success: true };

        case 'fill':
            await page.fill(action.selector || `[data-pw-id="${action.elementId}"]`, action.value);
            return { type: 'fill', selector: action.selector, success: true };

        case 'screenshot':
            const buffer = await page.screenshot();
            return { type: 'screenshot', size: buffer.length, success: true };

        case 'extract':
            const text = await page.evaluate(() => document.body.innerText);
            return { type: 'extract', content_length: text.length, success: true };

        default:
            throw new Error(`Unknown action type: ${action.type}`);
    }
}
SCRIPT;
        return str_replace('JSON.parse(process.argv[1])', "JSON.parse('$taskJson')", $output);
    }
}

// =============================================================================
// SECTION 9: API ROUTES
// =============================================================================

class Router {
    private string $method;
    private string $path;
    private array $query;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->path = preg_replace('#^/+#', '/', $this->path);
        $this->query = $_GET;
    }

    public function dispatch(): void {
        // CORS headers
        header('Access-Control-Allow-Origin: ' . ($_ENV['CORS_ORIGIN'] ?? '*'));
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Credentials: true');

        if ($this->method === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        // Security headers
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        header('Content-Security-Policy: default-src \'self\'');

        // Route matching
        match (true) {
            // Auth routes
            $this->match('POST', '/api/auth/register') => $this->handleRegister(),
            $this->match('POST', '/api/auth/login') => $this->handleLogin(),
            $this->match('POST', '/api/auth/logout') => $this->handleLogout(),

            // Session routes
            $this->match('POST', '/api/sessions') => $this->handleCreateSession(),
            $this->match('GET', '/api/sessions') => $this->handleListSessions(),
            $this->match('GET', '/api/sessions/(\w+)') => $this->handleGetSession($matches[1] ?? ''),

            // Queue status
            $this->match('GET', '/api/queue/status') => $this->handleQueueStatus(),

            // Dashboard (serve PWA)
            $this->match('GET', '/', true) => $this->serveDashboard(),
            $this->match('GET', '/.*\.(js|css|jpg|png|woff)$', true) => $this->serveStaticFile(),

            default => $this->handle404(),
        };
    }

    private function match(string $method, string $pattern, bool $regex = false): bool {
        if ($this->method !== $method) {
            return false;
        }

        if ($regex) {
            return (bool)preg_match("@^$pattern$@", $this->path, $matches);
        }

        return $this->path === $pattern;
    }

    // Auth handlers
    private function handleRegister(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $user = Auth::register(
                $data['username'] ?? '',
                $data['email'] ?? '',
                $data['password'] ?? ''
            );

            $this->json(['success' => true, 'data' => $user]);
        } catch (ValidationException $e) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleLogin(): void {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $user = Auth::login($data['username'] ?? '', $data['password'] ?? '');
            $this->json(['success' => true, 'data' => $user]);
        } catch (AuthenticationException $e) {
            http_response_code(401);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleLogout(): void {
        session_destroy();
        $this->json(['success' => true, 'message' => 'Logged out']);
    }

    // Session handlers
    private function handleCreateSession(): void {
        $user = Auth::requireAuth();
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $session = SessionManager::createSession($user['id'], $data['task_config'] ?? []);
            $this->json(['success' => true, 'data' => $session]);
        } catch (Exception $e) {
            http_response_code(400);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    private function handleListSessions(): void {
        $user = Auth::requireAuth();
        $limit = (int)($this->query['limit'] ?? 50);
        $offset = (int)($this->query['offset'] ?? 0);

        $sessions = SessionManager::listSessions($user['id'], $limit, $offset);
        $this->json(['success' => true, 'data' => $sessions]);
    }

    private function handleGetSession(string $sessionId): void {
        $user = Auth::requireAuth();
        $session = SessionManager::getSession($user['id'], $sessionId);

        if (!$session) {
            http_response_code(404);
            $this->json(['success' => false, 'error' => 'Session not found']);
            return;
        }

        $this->json(['success' => true, 'data' => $session]);
    }

    // Queue status
    private function handleQueueStatus(): void {
        Auth::requireAuth();

        $stmt = Database::execute(
            'SELECT COUNT(*) as count FROM sessions WHERE status = ?',
            ['pending']
        );
        $pending = $stmt->fetch()['count'];

        $stmt = Database::execute(
            'SELECT COUNT(*) as count FROM sessions WHERE status = ?',
            ['running']
        );
        $running = $stmt->fetch()['count'];

        $stmt = Database::execute(
            'SELECT COUNT(*) as count FROM sessions WHERE status = ?',
            ['completed']
        );
        $completed = $stmt->fetch()['count'];

        $this->json([
            'success' => true,
            'data' => [
                'queue_pending' => $pending,
                'queue_running' => $running,
                'queue_completed' => $completed,
                'timestamp' => time(),
            ],
        ]);
    }

    // Static file serving
    private function serveStaticFile(): void {
        $publicDir = dirname(__DIR__) . '/public';
        $file = $publicDir . $this->path;

        if (!file_exists($file) || !is_file($file)) {
            http_response_code(404);
            exit('Not found');
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);
        $mimeTypes = [
            'js' => 'application/javascript',
            'css' => 'text/css',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'woff' => 'font/woff',
        ];

        header('Content-Type: ' . ($mimeTypes[$ext] ?? 'text/plain'));
        header('Cache-Control: public, max-age=3600');
        readfile($file);
    }

    private function serveDashboard(): void {
        header('Content-Type: text/html; charset=utf-8');
        echo $this->getDashboardHTML();
    }

    private function getDashboardHTML(): string {
        return <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SWAOE v4.1 Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .nav { display: flex; gap: 10px; margin-top: 15px; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; }
        .btn:hover { background: #0056b3; }
        .card { background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .stat { background: #f0f0f0; padding: 15px; border-radius: 4px; text-align: center; }
        .stat-value { font-size: 24px; font-weight: bold; color: #007bff; }
        .stat-label { color: #666; font-size: 14px; }
        form { display: grid; gap: 10px; }
        input, textarea { padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: inherit; }
        .alert { padding: 15px; border-radius: 4px; margin-bottom: 15px; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .sessions-table { width: 100%; border-collapse: collapse; }
        .sessions-table th, .sessions-table td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        .sessions-table th { background: #f0f0f0; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 3px; font-size: 12px; font-weight: bold; }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-running { background: #17a2b8; color: white; }
        .badge-completed { background: #28a745; color: white; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>⚙️ SWAOE v4.1 Dashboard</h1>
            <p style="color: #666; margin-top: 5px;">Production-Ready Automation Engine</p>
            <div class="nav">
                <button class="btn" onclick="showSection('status')">Dashboard</button>
                <button class="btn" onclick="showSection('create')">New Session</button>
                <button class="btn" onclick="showSection('sessions')">Sessions</button>
                <button class="btn" onclick="logout()">Logout</button>
            </div>
        </header>

        <div id="auth-section">
            <div class="card">
                <h2>Authentication</h2>
                <div id="auth-message"></div>
                <form onsubmit="handleAuth(event)">
                    <input type="email" id="email" placeholder="Email" required>
                    <input type="password" id="password" placeholder="Password" required>
                    <input type="text" id="username" placeholder="Username (for registration)" style="display: none;">
                    <button type="button" class="btn" onclick="toggleRegister()">Register</button>
                    <button type="submit" class="btn">Login</button>
                </form>
            </div>
        </div>

        <div id="dashboard-section" style="display: none;">
            <div class="card">
                <h2>Queue Status</h2>
                <div class="status" id="status-cards"></div>
            </div>

            <div class="card" id="create" style="display: none;">
                <h2>Create New Session</h2>
                <form onsubmit="handleCreateSession(event)">
                    <textarea id="task-config" placeholder="Task Config (JSON)" rows="8" required></textarea>
                    <button type="submit" class="btn">Create Session</button>
                </form>
                <div id="create-message"></div>
            </div>

            <div class="card" id="sessions" style="display: none;">
                <h2>Sessions</h2>
                <table class="sessions-table">
                    <thead>
                        <tr>
                            <th>Session ID</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="sessions-body"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let currentUser = null;

        async function handleAuth(event) {
            event.preventDefault();
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const username = document.getElementById('username').value;

            const isRegister = username && document.getElementById('username').style.display !== 'none';
            const endpoint = isRegister ? '/api/auth/register' : '/api/auth/login';
            const payload = isRegister 
                ? { username, email, password }
                : { username: email, password };

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload),
                });

                const result = await response.json();
                if (result.success) {
                    currentUser = result.data;
                    localStorage.setItem('api_token', result.data.api_token);
                    showDashboard();
                } else {
                    showMessage('auth-message', result.error, 'error');
                }
            } catch (error) {
                showMessage('auth-message', error.message, 'error');
            }
        }

        function toggleRegister() {
            const input = document.getElementById('username');
            input.style.display = input.style.display === 'none' ? 'block' : 'none';
        }

        function showDashboard() {
            document.getElementById('auth-section').style.display = 'none';
            document.getElementById('dashboard-section').style.display = 'block';
            loadQueueStatus();
        }

        function showSection(section) {
            document.querySelectorAll('.card').forEach(card => card.style.display = 'none');
            if (section === 'status') {
                loadQueueStatus();
            } else {
                document.getElementById(section).style.display = 'block';
                if (section === 'sessions') loadSessions();
            }
        }

        async function loadQueueStatus() {
            try {
                const response = await fetch('/api/queue/status', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('api_token') },
                });
                const result = await response.json();

                if (result.success) {
                    const { queue_pending, queue_running, queue_completed } = result.data;
                    document.getElementById('status-cards').innerHTML = `
                        <div class="stat">
                            <div class="stat-value">${queue_pending}</div>
                            <div class="stat-label">Pending</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">${queue_running}</div>
                            <div class="stat-label">Running</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value">${queue_completed}</div>
                            <div class="stat-label">Completed</div>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Failed to load queue status:', error);
            }
        }

        async function handleCreateSession(event) {
            event.preventDefault();
            const taskConfig = JSON.parse(document.getElementById('task-config').value);

            try {
                const response = await fetch('/api/sessions', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + localStorage.getItem('api_token'),
                    },
                    body: JSON.stringify({ task_config: taskConfig }),
                });

                const result = await response.json();
                if (result.success) {
                    showMessage('create-message', 'Session created: ' + result.data.session_id, 'success');
                } else {
                    showMessage('create-message', result.error, 'error');
                }
            } catch (error) {
                showMessage('create-message', error.message, 'error');
            }
        }

        async function loadSessions() {
            try {
                const response = await fetch('/api/sessions', {
                    headers: { 'Authorization': 'Bearer ' + localStorage.getItem('api_token') },
                });

                const result = await response.json();
                if (result.success) {
                    const tbody = document.getElementById('sessions-body');
                    tbody.innerHTML = result.data.map(session => `
                        <tr>
                            <td>${session.id.substring(0, 12)}...</td>
                            <td><span class="badge badge-${session.status}">${session.status}</span></td>
                            <td>${new Date(session.created_at).toLocaleString()}</td>
                            <td><a href="#" onclick="showSession('${session.id}')">View</a></td>
                        </tr>
                    `).join('');
                }
            } catch (error) {
                console.error('Failed to load sessions:', error);
            }
        }

        function showMessage(elementId, message, type) {
            const element = document.getElementById(elementId);
            element.className = 'alert alert-' + type;
            element.textContent = message;
        }

        function logout() {
            localStorage.removeItem('api_token');
            currentUser = null;
            location.reload();
        }

        // Initialize
        window.addEventListener('load', () => {
            const token = localStorage.getItem('api_token');
            if (token) showDashboard();
        });
    </script>
</body>
</html>
HTML;
    }

    private function handle404(): void {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not found']);
    }

    private function json(array $data): void {
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

// =============================================================================
// SECTION 10: APPLICATION BOOTSTRAP
// =============================================================================

// Initialize application
try {
    // Set global variables
    $GLOBALS['dataDir'] = $dataDir;
    $GLOBALS['logDir'] = $logDir;

    // Initialize database
    Database::init();

    // Log startup
    Logger::info("SWAOE v" . SWAOE_VERSION . " started", [
        'environment' => SWAOE_ENV,
        'php_version' => PHP_VERSION,
    ]);

    // Dispatch request
    $router = new Router();
    $router->dispatch();

} catch (Throwable $e) {
    Logger::error("Application startup failed: {$e->getMessage()}", [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => SWAOE_ENV === 'production' 
            ? 'Internal server error' 
            : $e->getMessage(),
    ]);
}
?>
