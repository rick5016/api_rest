<?php

namespace App\ORM;

use App\Query\BDD;
use App\Query\Query;
use App\ORM\Utils;

class ORM extends Utils
{
    protected $connectedUser;

    public function setConnectedUser($connectedUser)
    {
        $this->connectedUser = $connectedUser;
    }

    public function __construct(array $datas = null)
    {
        $this->fetch($datas);
    }

    public static function factory(string $class, array $datas = null): ORM
    {
        $class = 'App\ORM\\' . ucfirst($class);

        return new $class($datas);
    }

    public function findAll(array $where = array(), $select = null, $order = null, $distinct = false, $page = false)
    {
        $results = array();
        if ($page) {
            $where = (!empty($where)) ? $where : (!empty($_POST['where']) ? $_POST['where'] : array());
            unset($_POST['where']);
            $order = (!empty($order)) ? $order : (!empty($_POST['order']) ? $_POST['order'] : null);

            $search_nb_result = 'id';
            if (!empty($select) && is_array($select)) {
                $search_nb_result = $select[0];
            }
            if (!empty($distinct)) {
                $search_nb_result = 'distinct ' . $search_nb_result;
            }
            $nb_results = $this->getQuery($where, array('count(' . $search_nb_result . ') as nb'), $order, $distinct)->load()->fetch();

            $results['nb_result'] = $nb_results['nb'];
            $results['nb_page'] = ceil($nb_results['nb'] / 5);
        }
        $result = $this->getQuery($where, $select, $order, $distinct, $page)->load()->fetchAll();
        if (!empty($result)) {
            foreach ($result as $key => $data) {
                $this->fetch(array('connectedUser' => $this->connectedUser) + $data);
                $results['list'][$key] = clone $this;
            }
        }

        return $results;
    }

    public function findAllArray(array $where = array(), $select = null, $order = null, $distinct = false)
    {
        return array('list' => $this->getQuery($where, $select, $order, $distinct)->load()->fetchAll());
    }

    public function find(array $where = array(), $select = null, $order = null, $distinct = false)
    {
        $result = $this->getQuery($where, $select, $order, $distinct)->load()->fetch();
        if ($result) {
            $this->fetch($result);
            return $this;
        }

        return $result;
    }

    public function findArray(array $where = array(), $select = null, $order = null)
    {
        $result = $this->find($where, $select, $order);
        if ($result instanceof ORM) {
            return $result->toArray();
        }

        return $result;
    }

    public function save(array $where = array())
    {
        // Security: spoofing
        if (method_exists($this, 'setUser')) {
            $this->setUser($this->connectedUser->userid);
        }

        // TODO : pour un update il faut vérifier les droits pour écraser l'id de l'utilisateur
        $query = $this->getQuery($where);

        try {
            if (empty($query->getWhere())) {
                $query->beginTransaction();
            }

            if (!$query->save($this)) {
                throw new \Exception('Save failed');
            }

            if (empty($query->getWhere())) {
                $lastInsertId = $query->getLastInsertId();
                $query->commit();
                foreach ($this->getPrimaryKey() as $p) {
                    if ($p[1] == 'AI') {
                        $this->{'set' . ucfirst($p[0])}($lastInsertId);
                    }
                }
            }

            if (!empty($query->getWhere())) {
                $this->fetch($this->findArray($query->getWhere()));
            }
        } catch (\Exception $e) {
            if (empty($query->getWhere())) {
                $query->rollBack();
            }
            throw new \Exception($e->getMessage());
        }
    }

    public function delete(array $where = array())
    {
        if (!$this->getQuery($where)->delete()) {
            throw new \Exception('Delete failed');
        }

        return array();
    }

    public function getClassName()
    {
        return strtolower((new \ReflectionClass($this))->getShortName());
    }

    public function getProperties(ORM $obj = null): array
    {
        $result = array();
        $properties = (new \ReflectionClass($obj ?? $this))->getProperties();

        foreach ($properties as $propertie) {
            $result[] = $propertie->name;
        }

        return $result;
    }

    public function get($name, ORM $obj = null)
    {
        if (method_exists(get_class($obj ?? $this), 'get' . ucfirst($name))) {
            return ($obj ?? $this)->{'get' . ucfirst($name)}();
        }

        return null;
    }

    protected function fetch(array $datas = null)
    {
        if (!empty($datas) && is_array($datas)) {
            foreach ($datas as $key => $data) {
                if (method_exists('App\ORM\\' . ucfirst($this->getClassName()), 'set' . ucfirst($key))) {
                    $this->{'set' . ucfirst($key)}($data);
                }
            }
        }

        return $this;
    }

    protected function fetchAll(array $datas = null)
    {
        $results = array();
        if (!empty($datas) && is_array($datas)) {
            foreach ($datas as $data) {
                $obj = clone $this;
                foreach ($data as $key => $value) {
                    if (method_exists('App\ORM\\' . ucfirst($obj->getClassName()), 'set' . ucfirst($key))) {
                        $obj->{'set' . ucfirst($key)}($value);
                    }
                }
                $results[] = $obj;
            }
        }

        return $results;
    }

    protected function getQuery($where = array(), $select = null, $order = null, $distinct = false, $page = false)
    {
        $where = (!empty($where)) ? $where : array();
        if (empty($where) && !empty($_POST['where'])) {
            $where = $_POST['where'];
            unset($_POST['where']);
        }

        $order = (!empty($order)) ? $order : (!empty($_POST['order']) ? $_POST['order'] : null);

        return new Query($this, $where, $select, array(), $order, $distinct, $page);
    }

    public function toArray()
    {
        $result = array();
        foreach ((new \ReflectionClass($this))->getProperties() as $value) {
            if (method_exists(get_class($this), 'get' . ucfirst($value->name)))
            {
                $data = $this->{'get' . ucfirst($value->name)}();
            } else {
                $data = $this->{$value->name};
            }
            if ($data !== null) {
                if ($data instanceof ORM) {
                    $result[$value->name] = $data->toArray();
                } else if (is_array($data) && !empty($data['list'])) {
                    foreach ($data['list'] as $key => $list) {
                        $result[$value->name][$key] = $list->toArray();
                    }
                } else {
                    $result[$value->name] = $data;
                }
            }
        }

        return $result;
    }
}
