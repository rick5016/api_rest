<?php

namespace App\ORM;

use App\ORM\ORM;

class Note extends ORM
{
    private $id;
    private $note;
    private $commentaire;
    private $created;
    private $updated;
    private $user;
    private $episode;
    private $saison;
    private $serie;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'), // Nom (AI = Auto increment)
        );
    }

    public function getId()
    {
        return $this->id;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function getCommentaire()
    {
        return $this->commentaire;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getUpdated()
    {
        return $this->updated;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getEpisode()
    {
        return $this->episode;
    }

    public function getSaison()
    {
        return $this->saison;
    }

    public function getSerie()
    {
        return $this->serie;
    }

    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setNote($note)
    {
        $this->note = $note;
    }

    public function setCommentaire($commentaire)
    {
        $this->commentaire = $commentaire;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setTypeId($type_id)
    {
        $this->type_id = $type_id;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function setUpdated($updated)
    {
        $this->updated = $updated;
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

    public function setEpisode($episode)
    {
        $this->episode = $episode;
    }

    public function setSaison($saison)
    {
        $this->saison = $saison;
    }

    public function setSerie($serie)
    {
        if (!is_numeric($serie)) {
            $this->serie = $this->getSerieId($serie);
            $this->saison = $this->getSaisonId($this->serie, $this->getSaison());
        } else {
            $this->serie = $serie;
        }
    }

    private function getSerieId($serie)
    {
        $result = ORM::factory('serie')->find(array('slug' => $serie));
        return (int) $result['id'];
    }

    private function getSaisonId($serie, $saison)
    {
        $result = ORM::factory('saison')->find(array('serie' => $serie, 'saison' => $saison));
        return (int) $result['id'];
    }

    public function find(array $where = array(), $select = null)
    {
        $result = parent::find($where, $select);
        if ($result) {
            $result = $this->addUser($result);
        }

        return $result;
    }

    public function findAll(array $where = array(), $select = null)
    {
        if (!empty($_POST['where']) && !empty($_POST['where']['serie'])) {
            if (!is_numeric($_POST['where']['serie'])) {
                $_POST['where']['serie'] = $this->getSerieId($_POST['where']['serie']);
                $_POST['where']['saison'] = $this->getSaisonId($_POST['where']['serie'], $_POST['where']['saison']);
            }
        }
        $results = parent::findAll($where, $select);

        foreach ($results['list'] as $key => $result) {
            if (isset($result['description'])) {
                /*$parsedown = new \Parsedown();
                $parsedown->setSafeMode(true);*/
                $result['description'] = $result['description'];
            }
            $results['list'][$key] = $this->addUser($result);
        }

        return $results;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($_POST['where']) && empty($where)) {
            // TODO : vérifier qu'il existe au moins un id (episode, saison ou série)
        }

        if (empty($this->getId())) {
            $this->setCreated(date("Y-m-d H:i:s"));
        }

        return parent::save($where);
    }

    public function delete(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        parent::delete($where);

        return array();
    }
}
