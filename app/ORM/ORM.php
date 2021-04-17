<?php

namespace App\ORM;

use App\Acl;
use App\ORM\Utils;
use App\Query\Query;

class ORM extends Utils
{
    public $connectedUser;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'),
        );
    }

    public function getId()
    {
        return $this->id;
    }

    /** A revoir */
    public function setUser($user)
    {
        if ($user instanceof User) {
            $this->user = $user;
        } else if ($user instanceof \stdClass) {
            $this->user = ORM::factory('user')->find(array('id' => (int) $user->id));
        } else if (is_array($user)) {
            $this->user = $this->toObject('user', $user);
        } else if (is_int($user)) {
            $this->user = ORM::factory('user')->find(array('id' => (int) $user));
        }
    }

    public function getUser()
    {
        return $this->user;
    }

    /**
     * Instanciation de l'objet d'ORM.
     */
    public static function factory(string $class, array $datas = null): ORM
    {
        $class = 'App\ORM\\' . ucfirst($class);
        return new $class($datas);
    }

    /**
     * Constructeur
     */
    public function __construct(array $datas = null)
    {
        $this->fetch($datas);
    }

    /**
     * Construit l'objet courant avec les données fournies en paramètre.
     */
    protected function fetch(array $datas = null)
    {
        if (!empty($datas) && is_array($datas)) {
            foreach ($datas as $name => $value) {
                $this->{$name} = $value;
            }
        }

        return $this;
    }

    /**
     * Construit une liste d'objet (du type de l'objet courant) avec les données fournies en paramètre.
     */
    protected function fetchAll(array $datas = null)
    {
        $results = array();
        if (!empty($datas) && is_array($datas)) {
            foreach ($datas as $data) {
                $obj = clone $this;
                foreach ($data as $name => $value) {
                    $obj->{$name} = $value;
                }
                $results[] = $obj;
            }
        }

        return $results;
    }

    // Getter
    public function get($name)
    {
        if (method_exists(get_class($this), 'get' . ucfirst($name))) {
            return $this->{'get' . ucfirst($name)}();
        } else if (property_exists(get_class($this), $name)) {
            return $this->$name;
        }

        return null;
    }

    // Setter
    public function set($name, $data)
    {
        if (method_exists(get_class($this), 'set' . ucfirst($name))) {
            $this->{'set' . ucfirst($name)}($data);
        } else if (property_exists(get_class($this), $name)) {
            $this->$name = $data;
        }
    }

    /**
     * Retourne la liste des propriétés de l'objet enfant.
     */
    public function getProperties(ORM $obj = null): array
    {
        $result = array();
        $properties = (new \ReflectionClass($obj ?? $this))->getProperties();

        foreach ($properties as $propertie) {
            $result[] = $propertie->name;
        }

        return $result;
    }

    /**
     * Transforme l'objet courant et ses propriété en tableau.
     */
    public function toArray()
    {
        $result = array();
        foreach ((new \ReflectionClass($this))->getProperties() as $value) {
            $data = $this->{$value->name};
            if ($data !== null) {
                if ($data instanceof ORM) {
                    $result[$value->name] = $data->toArray();
                } else if (is_array($data)) {
                    foreach ($data as $key => $list) {
                        if (is_array($list) && $key == 'list') {
                            foreach ($list as $keyList => $dataList) {
                                if ($dataList instanceof ORM) {
                                    $result[$value->name]['list'][$keyList] = $dataList->toArray();
                                } else {
                                    $result[$value->name]['list'][$keyList] = $dataList;
                                }
                            }
                        } else {
                            $result[$value->name][$key] = $list;
                        }
                    }
                } else {
                    $result[$value->name] = $data;
                }
            }
        }
        return $result;
    }

    /**
     * Transforme un tableau en objet.
     */
    public function toObject($name, $datas)
    {
        $object = ORM::factory($name);
        foreach ($datas as $name => $value) {
            $object->{'set'}($name, $value);
        }

        return $object;
    }

    /**
     * Retourne, soit la valeur en paramètre, soit la valeur en POST (et la supprime).
     */
    private function getQueryProperties($name, $properties, $default = null)
    {
        if (!empty($properties[$name])) {
            return $properties[$name];
        } else if (!empty($_POST[$name])) {
            $value = $_POST[$name];
            unset($_POST[$name]);
            return $value;
        } else {
            return $default;
        }

        /*if (!empty($properties)) {
            $value = (!empty($properties[$name])) ? $properties[$name] : $default;
        } else {
            $value = (!empty($_POST[$name])) ? $_POST[$name] : $default;
            unset($_POST[$name]);
        }*/

        //return $value;
    }

    /**
     * Retourne une ressource (query).
     */
    private function getQuery(array $properties = array())
    {
        $where = $this->getQueryProperties('where', $properties, array());
        $select = $this->getQueryProperties('select', $properties, array());
        $values = $this->getQueryProperties('values', $properties, array());
        $order = $this->getQueryProperties('order', $properties);
        $distinct = $this->getQueryProperties('distinct', $properties);
        $page = $this->getQueryProperties('page', $properties, false);
        $nb_page = $this->getQueryProperties('nbResultByPage', $properties, '10');

        return new Query($this, $where, $select, $values, $order, $distinct, $page, $nb_page);
    }

    public function findAllArray(array $properties = array(), $cascade = array())
    {
        $results = array();
        if (!empty($properties['page'])) {
            $nbResultTotal = $this->findNbResultTotal($properties);
            $results['nb_result'] = $nbResultTotal['nb'];

            $nbResultByPage = (!empty($properties['nbResultByPage'])) ? $properties['nbResultByPage'] : 10; // TODO : A mettre dans la config
            $results['nb_page'] = ceil($nbResultTotal['nb'] / $nbResultByPage);
        }
        $datas = $this->getQuery($properties)->load()->fetchAll();
        if (!empty($datas)) {
            foreach ($datas as $result) {
                foreach ($cascade as $constraint) {
                    if (!empty($result[$constraint])) {
                        var_dump($constraint);
                        exit;
                        $result[$constraint] = ORM::factory($constraint)->findArray(array('where' => array('id' => (int) $result[$constraint])));
                    }
                    if ($constraint == 'user') {
                        $result['own'] = false;
                        if (!empty($result[$constraint])) {
                            if (!empty($this->connectedUser) && $this->connectedUser->userid == $result[$constraint]['id']) {
                                $result['own'] = true;
                            }
                        }
                    }
                }
                $results['list'][] = $result;
            }
        }

        return $results;
    }

    public function findNbResultTotal(array $properties = array())
    {
        $where = !empty($properties['where']) ? $properties['where'] : array();
        $select = (!empty($properties['select'])) ? $properties['select'][0] : 'id';
        $select = array((!empty($distinct)) ? 'count(distinct ' . $select . ') as nb' : 'count(' . $select . ') as nb');

        $query = new Query($this, $where, $select);
        return $query->load()->fetch();
    }

    public function findAll(array $properties = array(), $cascade = array())
    {
        $results = array();
        $datas = $this->findAllArray($properties, $cascade);
        if (!empty($datas) && !empty($datas['list'])) {
            if (!empty($datas['nb_result'])) {
                $results['nb_result'] = $datas['nb_result'];
            }
            if (!empty($datas['nb_page'])) {
                $results['nb_page'] = $datas['nb_page'];
            }
            foreach ($datas['list'] as $result) {
                if ($result) {
                    $this->fetch($result);
                    $results['list'][] = clone $this;
                }
            }
        }

        return $results;
    }

    public function findArray(array $properties = array(), $cascade = array())
    {
        $result = $this->getQuery($properties)->load()->fetch();
        foreach ($cascade as $constraint) {
            if (!empty($result[$constraint])) {
                $result[$constraint] = ORM::factory($constraint)->findArray(array('where' => array('id' => (int) $result[$constraint])));
            }
            if ($constraint == 'user') {
                $result['own'] = false;
                if (!empty($result[$constraint])) {
                    if (!empty($this->connectedUser) && $this->connectedUser->userid == $result[$constraint]['id']) {
                        $result['own'] = true;
                    }
                }
            }
        }

        return $result;
    }

    public function find(array $properties = array(), $cascade = array())
    {
        $result = $this->findArray($properties, $cascade);
        if ($result) {
            $this->fetch($result);
            return $this;
        }

        return $result;
    }

    public function save(array $properties = array())
    {
        $where = $this->getQueryProperties('where', $properties, array());
        if (!Acl::check($this, 'save', $where)) {
            throw new \Exception('Vous n\'avez pas les droits pour effectuer cette action.');
        }

        if (!empty($this->connectedUser)) {
            $this->user = $this->connectedUser->userid;
        }

        $query = $this->getQuery(array('where' => $where));

        try {
            if (empty($query->getWhere())) {
                $query->beginTransaction();
            }

            $connectedUser = $this->connectedUser;
            $this->connectedUser = null;

            if (!$query->save($this)) {
                throw new \Exception('Save failed');
            }

            $this->connectedUser = $connectedUser;

            if (empty($query->getWhere())) {
                $lastInsertId = $query->getLastInsertId();
                $query->commit();
                foreach ($this->getPrimaryKey() as $p) {
                    if ($p[1] == 'AI') {
                        $this->{$p[0]} = $lastInsertId;
                    }
                }
            }

            if (!empty($query->getWhere())) {
                $this->fetch($this->findArray(array('where' => $query->getWhere())));
            }

            return $this;
        } catch (\Exception $e) {
            if (empty($query->getWhere())) {
                $query->rollBack();
            }
            throw new \Exception($e->getMessage());
        }
    }

    public function delete(array $where = array())
    {
        $where = $this->getQueryProperties('where', array('where' => $where), array());
        if (!Acl::check($this, 'delete', $where)) {
            throw new \Exception("Vous n'avez pas les droits pour effectuer cette action.");
        }

        if (!$this->getQuery(array('where' => $where))->delete()) {
            throw new \Exception('Delete failed');
        }

        return array();
    }
}
