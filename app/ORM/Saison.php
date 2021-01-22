<?php

namespace App\ORM;

use App\Query\BDD;
use App\ORM\ORM;

class Saison extends ORM
{
    private $id;
    private $saison;
    private $description;
    private $created;
    private $updated;
    private $publied;
    private $user;
    private $serie;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI') // Nom (AI = Auto increment)
        );
    }

    public function getId()
    {
        return $this->id;
    }

    public function getSaison()
    {
        return $this->saison;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getUpdated()
    {
        return $this->updated;
    }

    public function getPublied()
    {
        return $this->publied;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getSerie()
    {
        return $this->serie;
    }


    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setSaison($saison)
    {
        $this->saison = $saison;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    public function setPublied($publied)
    {
        $this->publied = $publied;
    }

    public function setUser($user)
    {
        if ($user instanceof \stdClass) {
            if (!empty($user->userid)) {
                $user = $user->userid;
            }
        }
        $this->user = $user;
    }

    public function setSerie($serie)
    {
        $this->serie = $this->getSerieId($serie);
    }

    private function getSerieId($serie)
    {
        if (!is_numeric($serie)) {
            $result = ORM::factory('serie')->find(array('slug' => $serie));
            $serie = $result['id'];
        }
        return (int) $serie;
    }

    public function find(array $where = array(), $select = null, $order = null)
    {
        $result = parent::find($where, $select, $order);
        if ($result) {
            $result = $this->addUser($result);
        }

        return $result;
    }

    public function findAll(array $where = array(), $select = null, $order = null)
    {
        if (!empty($_POST['where']) && !empty($_POST['where']['serie'])) {
            $_POST['where']['serie'] = $this->getSerieId($_POST['where']['serie']);
        }
        $results = parent::findAll($where, $select, $order);

        foreach ($results['list'] as $key => $result) {
            $results['list'][$key] = $this->addUser($result);
        }

        return $results;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($this->getSaison())) {
            throw new \Exception("Le numéro de la saison est obligatoire.");
        }

        if ($this->find(array('saison' => $this->getSaison(), 'serie' => $this->getSerie())) !== false) {
            $serie = ORM::factory('serie')->find(array('id' => $this->getSerie()));
            throw new \Exception('La saison "' . $this->getSaison() . '" existe déjà pour la série"' . $serie['title'] . '"');
        }

        if (empty($this->getId())) {
            $this->setCreated(date("Y-m-d H:i:s"));
        }

        return parent::save($where);
    }

    public function delete(array $where = array(), $transaction = true)
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        if (empty($_POST['where']) || (empty($_POST['where']['id']) && empty($where['id']))) {
            throw new \Exception("L'identifiant de la saison est obligatoire");
        }
        if (!empty($where['id'])) {
            $id = $where['id'];
        } else {
            $id = $_POST['where']['id'];
        }

        if (!empty($_POST['where']) && !empty($_POST['where']['serie'])) {
            $_POST['where']['serie'] = $this->getSerieId($_POST['where']['serie']);
        }

        try {
            if ($transaction) {
                BDD::getConnection()->beginTransaction();
            }

            $saisons = ORM::factory('saison', array('connectedUser' => $this->connectedUser))->findAll(array('id' => $id));

            if (empty($saisons['list']) || empty($saisons['list'][0])) {
                throw new \Exception("La saison n'existe pas");
            }

            $saison = $saisons['list'][0];

            // Suppression des épisodes liés
            $episodes = ORM::factory('episode', array('connectedUser' => $this->connectedUser))->findAll(array('saison' => $saison['saison'], 'serie' => $saison['serie']));
            if (!empty($episodes['list']) && is_array($episodes['list']) && count($episodes['list']) > 0) {
                foreach ($episodes['list'] as $episode) {
                    if (!empty($episode['own']) && $episode['own'] === true) {
                        ORM::factory('episode', array('connectedUser' => $this->connectedUser))->delete(array('id' => $episode['id']));
                    } else {
                        throw new \Exception("L'épisode a été créé par une autre personne et ne peux pas être supprimé");
                    }
                }
            }

            parent::delete($where);
            if ($transaction) {
                BDD::getConnection()->commit();
            }
        } catch (\Exception $e) {
            if ($transaction) {
                BDD::getConnection()->rollBack();
            }
            throw new \Exception($e->getMessage());
        }

        return array();
    }
}
