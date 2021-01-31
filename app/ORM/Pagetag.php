<?php

namespace App\ORM;

use App\ORM\Page;
use App\ORM\Categorie;
use App\ORM\Tag;
use App\ORM\ORM;

class Pagetag extends ORM
{
    private $id;
    private $tag;
    private $categorie;
    private $article;
    private $user;

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

    public function getTag()
    {
        return $this->tag;
    }

    public function getCategorie()
    {
        return $this->categorie;
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

    public function setTag($tag)
    {
        if ($tag instanceof Tag) {
            $this->tag = $tag;
        } else if (is_int($tag)) {
            $this->tag = ORM::factory('tag')->find(array('id' => $tag));
        } else {
            $this->tag = ORM::factory('tag')->find(array('slug' => $tag));
        }

        if (empty($this->tag)) {
            $this->tag = ORM::factory('tag', array('connectedUser' => $this->connectedUser, 'slug' => $tag, 'tag' => $tag));
        }
    }

    public function setCategorie($categorie)
    {
        if ($categorie instanceof Categorie) {
            $this->categorie = $categorie;
        } else if (is_int($categorie)) {
            $this->categorie = ORM::factory('categorie')->find(array('id' => $categorie));
        } else {
            $this->categorie = ORM::factory('categorie')->find(array('slug' => $categorie));
        }

        if (empty($this->categorie)) {
            $this->categorie = ORM::factory('categorie', array('connectedUser' => $this->connectedUser, 'slug' => $categorie, 'categorie' => $categorie));
        }
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
        if ($user instanceof \stdClass) {
            if (!empty($user->userid)) {
                $user = $user->userid;
            }
        }
        $this->user = $user;
    }

    public function find(array $where = array(), $select = null, $order = null, $distinct = false)
    {
        // Récupération des id si on passe les slugs en paramètre
        /*$categorie = (!empty($where['categorie'])) ? $where['categorie'] : (!empty($_POST['where']['categorie']) ? $_POST['where']['categorie'] : '');
        $tag       = (!empty($where['tag']))       ? $where['tag'] :       (!empty($_POST['where']['tag'])       ? $_POST['where']['tag'] :       '');

        if (!empty($categorie) && !is_int($categorie)) {
            $categorie = ORM::factory('categorie', array('connectedUser' => $this->connectedUser))->find(array('slug' => $where['categorie']));
            if ($categorie) {
                $where['categorie'] = $categorie['id'];
            }
        }
        if (!empty($tag) && !is_int($tag)) {
            $tag = ORM::factory('tag', array('connectedUser' => $this->connectedUser))->find(array('slug' => $where['tag']));
            if ($tag) {
                $where['tag'] = $tag['id'];
            }
        }*/

        $result = parent::find($where, $select, $order);
       /* if ($result) {
            $result = $this->addUser($result);
        }
        $categories = ORM::factory('categorie')->findAll();
        if (!empty($categories)) {
            foreach ($categories as $categorie) {
                $tag = ORM::factory('tag')->findAll(array('categorie' => $categorie->getId()), array('tag'), null, true);
            }
        }*/

        return $result;
    }

    public function findAll(array $where = array(), $select = null, $order = null, $distinct = false, $page = false, $nb_page = '5')
    {
        // Récupération des id si on passe les slugs en paramètre
        /*$categorie = (!empty($where['categorie'])) ? $where['categorie'] : (!empty($_POST['where']['categorie']) ? $_POST['where']['categorie'] : '');
        $tag       = (!empty($where['tag']))       ? $where['tag'] :       (!empty($_POST['where']['tag'])       ? $_POST['where']['tag'] :       '');

        if (!empty($categorie) && !is_int($categorie)) {
            $categorie = ORM::factory('categorie', array('connectedUser' => $this->connectedUser))->find(array('slug' => $categorie));
            if ($categorie) {
                $where['categorie'] = $categorie->getId();
            }
        }
        if (!empty($tag) && !is_int($tag)) {
            $tag = ORM::factory('tag', array('connectedUser' => $this->connectedUser))->find(array('slug' => $tag));
            if ($tag) {
                $where['tag'] = $tag->getId();
            }
        }

        $results = parent::findAll($where, $select, $order);
        if (!empty($results['list'])) {
            foreach ($results['list'] as $key => $result) {
                $this->addUser($result);
            }
        }*/

        $results = parent::findAll($where, $select, $order, $distinct, $page, $nb_page);
        /*if ($result) {
            $result = $this->addUser($result);
        }
        $categories = ORM::factory('categorie')->findAll();
        if (!empty($categories['list'])) {
            foreach ($categories['list'] as $categorie) {
                $tags = ORM::factory('PageTag')->findAll(array('categorie' => $categorie->getId()), array('tag'), null, true);
            }
        }*/

        return $results;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($this->getTag())) {
            throw new \Exception("Le tag est obligatoire.");
        }

        if (empty($this->getCategorie())) {
            throw new \Exception("La catégorie est obligatoire.");
        }
        
        if (empty($this->getArticle())) {
            throw new \Exception("L'article est obligatoire.");
        }
        
        if (empty($this->getTag()->getId())) {
            $tag = $this->tag->save();
        }
        if (empty($this->getCategorie()->getId())) {
            $categorie = $this->categorie->save();
        }
        
        if (empty($this->getArticle()->getId())) {
            throw new \Exception("L'article est introuvable.");
        }
        
        if (empty($this->find(array('tag' => $this->getTag()->getId(), 'categorie' => $this->getCategorie()->getId(), 'article' => $this->getArticle()->getId())))) {
            return parent::save($where);
        }

        return parent::toArray();
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
