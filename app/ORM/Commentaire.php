<?php

namespace App\ORM;

use App\ORM\ORM;

class Commentaire extends ORM
{
    private $id;
    private $content;
    private $created;
    private $updated;
    private $publied;
    private $article;
    private $user;
    public $own;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI')
        );
    }

    public function getId()
    {
        return $this->id;
    }

    public function getContent()
    {
        return $this->content;
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

    public function getArticle()
    {
        return $this->article;
    }

    public function getUser()
    {
        return $this->user;
    }


    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setContent($content)
    {
        $this->content = $content;
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

    public function setArticle($article)
    {
        if ($article instanceof Page) {
            $this->article = $article;
        } else if (is_int($article)) {
            $this->article = ORM::factory('page', array('connectedUser' => $this->connectedUser))->find(array('id' => $article));
        } else {
            $this->article = ORM::factory('page', array('connectedUser' => $this->connectedUser))->find(array('slug' => $article));
        }
    }

    public function setUser($user)
    {
        if ($user instanceof User) {
            $this->user = $user;
        } else if (is_array($user) && !empty($user['id'])) {
            $this->user = ORM::factory('user', array('connectedUser' => $this->connectedUser))->find(array('id' => (int) $user['id']));
        } else {
            $this->user = ORM::factory('user', array('connectedUser' => $this->connectedUser))->find(array('id' => (int) $user));
        }
    }

    public function find(array $where = array(), $select = null, $order = null, $distinct = false)
    {
        $result = parent::find($where, $select, $order);

        if ($result) {
            $this->addUser($result);
        }

        return $result;
    }

    public function findAll(array $where = array(), $select = null, $order = null, $distinct = false, $page = false, $nb_page = '5')
    {
        $results = parent::findAll($where, $select, $order, $distinct, $page, $nb_page);

        if (!empty($results['list'])) {
            foreach ($results['list'] as $key => $result) {
                $this->addUser($result);
            }
        }
        
        return $results;
    }

    public function save(array $where = array())
    {
        $content = $this->getContent();
        if (empty($content)) {
            throw new \Exception("Le contenu du commentaire est obligatoire.");
        }

        $this->setContent(htmlentities($content));

        parent::save($where);

        return $this->toArray();
    }

    public function delete(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($_POST['where']['id']) && empty($where['id'])) {
            throw new \Exception('L\'id est obligatoire');
        }
        parent::delete($where);

        return array();
    }
}
