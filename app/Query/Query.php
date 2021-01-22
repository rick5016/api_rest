<?php

namespace App\Query;

use App\ORM\ORM;

class Query
{
    const PREVENT_DELETING = 'Missing search parameter';

    private $connection;

    private $object;
    private $distinct;
    private $select = array();
    private $from;
    private $innerjoin = array();
    private $where = array();
    private $values = array();
    private $orderby;
    private $limit;
    private $offset;


    public function __construct(ORM $object, array $where = array(), $select = '*', array $values = array(), $orderby = null, $distinct = false, $page = false)
    {
        $this->connection = BDD::getConnection();

        $this->object   = $object;
        $this->from     = $object->getClassName();
        $this->where    = $where;
        $this->orderby    = $orderby;
        $this->select   = $select;
        $this->distinct = $distinct;
        if ($page) {
            $this->limit    = 5;
            $this->offset   = ($page - 1) * 5;
        }

        // TODO : les valeurs passées ici doivent prendre le dessus sur les valeurs de l'objet
        $this->values   = $values;
    }

    public function load(): \PDOStatement
    {
        $request = 'select ' . $this->getRequestDistinct() . $this->getRequestSelect() . ' from ' . $this->getRequestFrom() . $this->getRequestInnerjoin() . $this->getRequestWhere() . $this->getRequestOrderby() . $this->getRequestLimit() . $this->getRequestOffset();
        $stmt = $this->connection->prepare($request);
        $stmt = $this->bindRequestValues($stmt);

        $stmt->execute();

        return $stmt;
    }

    public function save(): Bool
    {
        if (!empty($this->where)) {
            $stmt = $this->connection->prepare('update ' . $this->getRequestFrom() . $this->getRequestUpdateValues() . $this->getRequestWhere());
        } else {
            $stmt = $this->connection->prepare('insert into ' . $this->getRequestFrom() . $this->getRequestInsertValues());
        }

        $stmt = $this->bindRequestValues($stmt, true);

        return $stmt->execute();
    }

    public function delete(): Bool
    {
        // Prevents deleting the entire table
        if (empty($this->where)) {
            throw new \Exception(self::PREVENT_DELETING);
        }

        $query = 'delete from ' . $this->getRequestFrom() . $this->getRequestWhere();

        $stmt = $this->connection->prepare($query);
        $stmt = $this->bindRequestValues($stmt);

        return $stmt->execute();
    }

    public function getLastInsertId(): int
    {
        return (int) $this->connection->lastInsertId();
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

    // DISTINCT
    public function setDistinct(bool $distinct) {
        $this->distinct = $distinct;
    }
    public function getDistinct() {
        return $this->distinct;
    }

    private function getRequestDistinct() {
        if ($this->distinct) {
            return 'distinct ';
        }

        return '';
    }

    // SELECT
    public function setSelect(array $select) {
        $this->select = $select;
    }
    public function getSelect() {
        return $this->select;
    }
    private function getRequestSelect(): string
    {
        $select = '';
        if (!empty($this->select)) {
            foreach ($this->select as $data) {
                $select .= $data . ', ';
            }
            return substr($select, 0, -2);
        }
        return '*';
    }

    // FROM
    public function setFrom($from) {
        $this->from = $from;
    }
    public function getFrom() {
        return $this->from;
    }
    private function getRequestFrom() {
        return 'blog_' . $this->from;
    }

    // INNERJOIN
    public function addInnerjoin($table, $jointure) {
        $this->innerjoin[$table] = $jointure;
    }
    public function getInnerjoin() {
        return $this->innerjoin;
    }
    private function getRequestInnerjoin()
    {
        $query = '';
        foreach ($this->innerjoin as $table => $jointure) {
            $query .= " inner join blog_$table on $jointure";
        }

        return $query;
    }

    // WHERE
    public function setWhere(array $where) {
        $this->where = $where;
    }
    public function getWhere() {
        return $this->where;
    }
    private function getRequestWhere(): string
    {
        $properties = $this->object->getProperties();
        $query = '';
        if (!empty($this->where) && is_array($this->where)) {
            $query = ' where';
            foreach ($this->where as $key => $value) {
                if (!is_int($key) && in_array($key, $properties)) {
                    if (is_array($value)) {
                        $query .= " $key in (";
                        foreach ($value as $keyIn => $valueIn) {
                            $query .= ":where_in$keyIn,";
                        }
                        $query = substr($query, 0, -1);
                        $query .= ") and";
                    } else {
                        $query .= " $key = :where_$key and";
                    }
                } else {
                    $query .= " $value and";
                }
            }
            $query = substr($query, 0, -4);
        }

        return $query;
    }

    // VALUES
    public function setValues(array $values) {
        $this->values = $values;
    }
    public function getValues() {
        return $this->values;
    }
    private function getRequestInsertValues(): string
    {
        $properties = $this->object->getProperties();
        $query = ' (';
        foreach ($properties as $propertie) {
            // Do not insert the primary key and null values
            if (!in_array($propertie, $this->object->getPrimaryKey()) && $this->object->get($propertie) !== null) {
                $query .= '`' . $propertie . '`, ';
            }
        }
        $query = substr($query, 0, -2);
        $query .= ') VALUES (';
        foreach ($properties as $propertie) {
            // Do not insert the primary key and null values
            if (!in_array($propertie, $this->object->getPrimaryKey()) && $this->object->get($propertie) !== null) {
                $query .= ':' . $propertie . ', ';
            }
        }
        $query = substr($query, 0, -2) . ')';

        return $query;
    }
    private function getRequestUpdateValues(): string
    {
        $query = ' SET ';
        foreach ($this->object->getProperties() as $propertie) {
            // Do not update the primary key and null values
            if (!in_array($propertie, $this->object->getPrimaryKey()) && $this->object->get($propertie) !== null) {
                $query .= "$propertie = :$propertie, ";
            }
        }
        $query = substr($query, 0, -2);

        return $query;
    }
    private function bindRequestValues(\PDOStatement $stmt, $values = false): \PDOStatement
    {
        if ($values) { // Set VALUES (save) ou SET (update)
            foreach ($this->object->getProperties() as $propertie) {
                $data = $this->object->get($propertie);
                // Do not update/insert the primary key and null values
                if (!in_array($propertie, $this->object->getPrimaryKey()) && $data !== null) {
                    if ($data instanceof ORM) {
                        $data = $data->get('id');
                    }
                    $stmt->bindValue(":$propertie", $data);
                }
            }
        }

        if (!empty($this->where) && is_array($this->where)) { // Set Where (update)
            foreach ($this->where as $key => $value) {
                if (is_array($value)) {
                    foreach ($value as $keyIn => $valueIn) {
                        $stmt->bindValue(":where_in$keyIn", $valueIn);
                    }
                } else {
                    if ($value instanceof ORM) {
                        $value = $value->get('id');
                    } else if(!is_int($key)) {
                        $stmt->bindValue(":where_$key", $value);
                    }
                }
            }
        }

        return $stmt;
    }

    // ORDERBY
    public function setOrderby($orderby) {
        $this->orderby = $orderby;
    }
    public function getOrderby() {
        return $this->orderby;
    }
    public function getRequestOrderby() {
        $orderby = '';
        if (!empty($this->orderby)) {
            $orderby = ' ORDER BY ' . $this->orderby;
        }

        return $orderby;
    }

    // LIMIT
    public function setLimit($limit) {
        $this->limit = $limit;
    }
    public function getLimit() {
        return $this->limit;
    }
    public function getRequestLimit() {
        if (!empty($this->limit) && ($this->offset !== null)) {
            return ' LIMIT ' . $this->limit;
        }

        return '';
    }

    // OFFSET
    public function setOffset($offset) {
        $this->offset = $offset;
    }
    public function getOffset() {
        return $this->offset;
    }
    public function getRequestOffset() {
        if (!empty($this->limit) && ($this->offset !== null)) {
            return ' OFFSET ' . $this->offset;
        }

        return '';
    }
}
