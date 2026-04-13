<?php
// ============================================================
// CRUMBLY - Configuration & Database Connection
// ============================================================

define('CRUMBLY_VERSION', '1.0.0');
define('SITE_NAME', 'Crumbly');
define('SITE_URL', 'http://localhost/crumbly');
define('SITE_TAGLINE', 'Fresh Baked. Delivered Fast.');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'bakery_data');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// File Upload Config
define('UPLOAD_PATH', rtrim(str_replace('\\', '/', dirname(__DIR__)), '/') . '/uploads/');
define('UPLOAD_URL',  SITE_URL . '/uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp', 'image/gif']);

// Session Config
define('SESSION_LIFETIME', 86400 * 30); // 30 days
define('SESSION_SECURE', false); // Set true in production with HTTPS

// Pagination
define('PRODUCTS_PER_PAGE', 20);
define('ORDERS_PER_PAGE', 15);

// Currency
define('CURRENCY', 'INR');
define('CURRENCY_SYMBOL', '₹');

class Database {
    private static ?PDO $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log("Database connection failed: " . $e->getMessage());
                die(json_encode(['error' => 'Database connection failed.']));
            }
        }
        return self::$instance;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $sql, array $params = []): int {
        self::query($sql, $params);
        return (int) self::getInstance()->lastInsertId();
    }

    public static function lastId(): int {
        return (int) self::getInstance()->lastInsertId();
    }

    public static function beginTransaction(): void {
        self::getInstance()->beginTransaction();
    }

    public static function commit(): void {
        self::getInstance()->commit();
    }

    public static function rollback(): void {
        self::getInstance()->rollBack();
    }
}

// Initialize session securely
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        if (SESSION_SECURE) {
            ini_set('session.cookie_secure', 1);
        }
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

init_session();

// ============================================================
// ONE-TIME DB COMPATIBILITY PATCHES
// Fixes issues with older MySQL (5.7) where JSON type not supported
// ============================================================
if (($_SESSION['_db_patched'] ?? '') !== 'v3') {
    try {
        $db = Database::getInstance();
        // MySQL 5.7 compatibility fixes
        @$db->exec("ALTER TABLE orders        MODIFY COLUMN delivery_address TEXT");
        @$db->exec("ALTER TABLE notifications MODIFY COLUMN data TEXT");
        // Add missing columns
        @$db->exec("ALTER TABLE coupons ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
        @$db->exec("ALTER TABLE shops   ADD COLUMN banner VARCHAR(255) DEFAULT NULL");
        @$db->exec("ALTER TABLE shops   ADD COLUMN logo   VARCHAR(255) DEFAULT NULL");
        // Fix reviews table - make product_id optional (shop reviews don't need it)
        @$db->exec("ALTER TABLE reviews MODIFY COLUMN product_id INT UNSIGNED DEFAULT NULL");
        @$db->exec("ALTER TABLE reviews DROP FOREIGN KEY reviews_ibfk_1");
        @$db->exec("ALTER TABLE reviews DROP INDEX one_review_per_order_product");
        @$db->exec("ALTER TABLE reviews ADD UNIQUE KEY one_review_per_order (user_id, shop_id, order_id)");
    } catch (Exception $e) {
        // Silently ignore — already patched or table doesn't exist yet
    }
    $_SESSION['_db_patched'] = 'v3';
}

// ============================================================
// AUTH HELPERS
// ============================================================

function crumbly_get_current_user(): ?array {
    if (!isset($_SESSION['user_id'])) return null;
    return Database::fetch("SELECT * FROM users WHERE id = ? AND is_active = 1", [$_SESSION['user_id']]);
}

function require_auth(string $redirect = '/auth/login.php'): array {
    $user = crumbly_get_current_user();
    if (!$user) {
        header("Location: " . SITE_URL . $redirect . "?next=" . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    return $user;
}

function require_seller(): array {
    $user = require_auth();
    if ($user['role'] !== 'seller') {
        header("Location: " . SITE_URL . "/index.php");
        exit;
    }
    return $user;
}

function require_buyer(string $redirect = '/index.php'): array {
    $user = require_auth();
    if ($user['role'] === 'seller') {
        // Sellers cannot shop — redirect to their dashboard
        header("Location: " . SITE_URL . "/seller/dashboard.php?notice=sellers_cannot_buy");
        exit;
    }
    return $user;
}

function is_buyer(): bool {
    if (!is_logged_in()) return false;
    return ($_SESSION['user_role'] ?? '') !== 'seller';
}

function is_seller(): bool {
    return is_logged_in() && ($_SESSION['user_role'] ?? '') === 'seller';
}

function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

function login_user(array $user): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['full_name'];
}

function logout_user(): void {
    session_unset();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

// ============================================================
// UTILITY HELPERS
// ============================================================

function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function format_price(float $amount): string {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

function format_price_raw(float $amount): string {
    return number_format($amount, 2);
}

function generate_slug(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/[\s-]+/', '-', $text);
    return trim($text, '-');
}

function generate_order_number(): string {
    return 'CRB-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8)) . '-' . date('ymd');
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function get_cart_count(): int {
    if (!is_logged_in()) {
        return array_sum(array_column($_SESSION['guest_cart'] ?? [], 'quantity'));
    }
    $row = Database::fetch("SELECT SUM(quantity) as cnt FROM cart WHERE user_id = ?", [$_SESSION['user_id']]);
    return (int)($row['cnt'] ?? 0);
}

function get_star_rating(float $rating, int $max = 5): string {
    $html = '<span class="stars">';
    for ($i = 1; $i <= $max; $i++) {
        if ($rating >= $i) $html .= '<span class="star filled">★</span>';
        elseif ($rating >= $i - 0.5) $html .= '<span class="star half">★</span>';
        else $html .= '<span class="star empty">☆</span>';
    }
    $html .= '</span>';
    return $html;
}

function upload_image(array $file, string $folder = 'products'): ?string {
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_FILE_SIZE) return null;
    if ($file['size'] === 0) return null;

    // Use server-side MIME detection — never trust $file['type'] (browser-supplied, unreliable)
    $finfo    = finfo_open(FILEINFO_MIME_TYPE);
    $mime     = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Map mime → safe extension
    $mime_to_ext = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/pjpeg'=> 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($mime_to_ext[$mime])) return null;
    $ext = $mime_to_ext[$mime];

    // Ensure upload directory exists and is writable
    $dir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (!is_writable($dir)) return null;

    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = $dir . $filename;

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'uploads/' . $folder . '/' . $filename;
    }
    return null;
}

function get_product_image(array $product): string {
    $img = Database::fetch("SELECT image_path FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1", [$product['id']]);
    if ($img) return SITE_URL . '/' . $img['image_path'];
    return SITE_URL . '/assets/images/placeholder.svg';
}

function get_shop_by_user(int $user_id): ?array {
    return Database::fetch("SELECT * FROM shops WHERE user_id = ?", [$user_id]);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirect(string $path): void {
    header("Location: " . SITE_URL . $path);
    exit;
}

function flash_set(string $type, string $message): void {
    $_SESSION['flash'][$type] = $message;
}

function flash_get(string $type): ?string {
    $msg = $_SESSION['flash'][$type] ?? null;
    unset($_SESSION['flash'][$type]);
    return $msg;
}
