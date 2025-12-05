<?php
namespace App\Services;

use PDO;
use PDOException;

class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo === null) {
            $cfg = require __DIR__ . '/../../config/db.php';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'], $cfg['port'], $cfg['database'], $cfg['charset']
            );
            $opt = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], $opt);
            // doble seguro:
           // self::$pdo->exec("SET NAMES {$cfg['charset']} COLLATE {$cfg['collation']}");
            self::$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
            self::$pdo->exec("SET time_zone = 'America/Mexico_City'");
        }
        return self::$pdo;
    }
}
