<?php
/**
 * Centralized Database Connection (PDO)
 * Project: Laundry Management System (laundry-mgt)
 * Database: laundry_mgt
 */

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'laundry_mgt');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a centralized PDO Database connection instance
 *
 * @return PDO
 * @throws PDOException
 */
function getDBConnection(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci"
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Laundry Management DB Error: ' . $e->getMessage());
            
            if (defined('APP_ENV') && APP_ENV === 'development') {
                die('<div style="font-family: sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; color: #991b1b; max-width: 650px; margin: 40px auto;">' .
                    '<h3 style="margin-top: 0;">Database Connection Error</h3>' .
                    '<p>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>' .
                    '<p style="font-size: 13px; color: #7f1d1d;">Please verify that MySQL is running and that database <code>' . htmlspecialchars(DB_NAME, ENT_QUOTES, 'UTF-8') . '</code> has been imported from <code>database/phase_01_authentication.sql</code>.</p>' .
                    '</div>');
            } else {
                die('A database error occurred. Please contact the administrator.');
            }
        }
    }

    return $pdo;
}
