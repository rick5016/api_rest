<?php

namespace App\Query;

use PDO;

require_once dirname(__DIR__) . '/config.php';

class BDD
{
    protected static $instance = null;

    public static function getConnection()
    {
        if (self::$instance == null) {
            try {
                self::$instance = new PDO(PDO_DSN, PDO_LOGIN, PDO_PASSWORD, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                ]);
                self::$instance->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            } catch (Exception $e) {
                throw new Exception('Problème lors de la connexion à la base de données.', $e);
            }
        }
        return self::$instance;
    }
}
