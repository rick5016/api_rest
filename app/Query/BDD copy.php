<?php

namespace App\Query;

use PDO;

require_once(dirname(__DIR__) . '/config.php');

class BDD
{

    protected static $instance    = null;
    private $connection = null;

    protected function __construct()
    {
        try {
            $this->connection = new PDO(PDO_DSN, PDO_LOGIN, PDO_PASSWORD, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
            $this->connection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (Exception $e) {
            throw new Exception('Problème lors de la connexion à la base de données.', $e);
        }
    }

    public static function getConnection()
    {
        if (self::$instance == null) {
            self::$instance = new BDD();
        }
        return self::$instance;
    }

    public function prepare($query)
    {
        return $this->connection->prepare($query);
    }

    public function getLastInsertId()
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction()
    {
        $this->connection->beginTransaction();
    }

    public function commit()
    {
        $this->connection->commit();
    }

    public function rollBack()
    {
        $this->connection->rollBack();
    }
}
