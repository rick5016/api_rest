<?php

namespace App\ORM;

use App\ORM\ORM;

class Page extends ORM
{
    private $id;
    private $title;
    private $slug;
    private $content;
    private $vignette;
    private $created;
    private $updated;
    private $version;
    private $type;
    private $publied;
    private $user;
    public $own;
    public $similaires = array('list' => array());
    public $commentaires = array('list' => array(), 'nb_result' => 0, 'page' => 1);

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

    public function getTitle()
    {
        return $this->title;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getVignette()
    {
        return $this->vignette;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getUpdated()
    {
        return $this->updated;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getPublied()
    {
        return $this->publied;
    }

    public function getUser()
    {
        return $this->user;
    }


    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function setVignette($vignette)
    {
        $this->vignette = $vignette;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function setUpdated($updated)
    {
        $this->updated = $updated;
    }

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function setPublied($publied)
    {
        $this->publied = $publied;
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
        $page_commentaire = $_POST['where']['page'];
        unset($_POST['where']['page']);

        $results = parent::findAll($where, $select, $order, $distinct, $page, $nb_page);

        if (!empty($results['list'])) {
            foreach ($results['list'] as $key => $result) {
                $result->commentaires = ORM::factory('commentaire')->findAll(array('article' => $this->getId(), 'publied' => '1'), null, 'created desc', false, $page_commentaire, 10);
                $clauses = array();
                $others = array();
                // On va chercher toutes les relations de catégorie 'thème' de l'article
                $queryTheme = 'tag in (';
                $type = ORM::factory('pagetag')->find(array('article' => $this->getId(), 'categorie' => 8), array('tag'));
                $themes = ORM::factory('pagetag')->findAll(array('article' => $this->getId(), 'categorie' => 14), array('tag', 'categorie'));
                if (!empty($themes['list'])) {
                    foreach ($themes['list'] as $theme) {
                        $queryTheme .= $theme->getTag()->getId() . ', ';
                    }
                    $clauses[] = substr($queryTheme, 0, -2) . ')';
                    $clauses[] = 'article != ' . $this->getId();
                    $relations = ORM::factory('pagetag')->findAll($clauses, array('article'), null, true);
                } else if (!empty($type)) {
                    $clauses['tag'] = $type->getTag()->getId();
                    $clauses[] = 'article != ' . $this->getId();
                    $relations = ORM::factory('pagetag')->findAll($clauses, array('article'), null, true);
                }
                if (!empty($relations['list']) && !empty($type)) {
                    foreach ($relations['list'] as $relation) {
                        if (ORM::factory('pagetag')->find(array('article' => $relation->getArticle()->getId(), 'categorie' => 8, 'tag' => $type->getTag()->getId())) !== false) {
                            $result->similaires['list'][] = $relation->getArticle();
                        }
                    }
                    shuffle($result->similaires['list']);
                }
                
                $this->addUser($result);
            }
        }

        return $results;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (!empty($this->getTitle())) {
            $this->setSlug($this->string_to_slug($this->getTitle()));
        }

        if (empty($this->getTitle()) || empty($this->getSlug())) {
            throw new \Exception("Le titre de l'article est obligatoire.");
        }

        if (empty($this->getContent())) {
            throw new \Exception("Le contenu de l'article est obligatoire.");
        }

        if (empty($this->getType())) {
            throw new \Exception("Le type de l'article est obligatoire.");
        }

        // Si c'est un update et que les 2 slugs ne correspondent pas OU si c'est une insert : on vérifie que le slug n'existe pas déjà en BDD
        $slug_old = !empty($where['slug']) ? $where['slug'] : (!empty($_POST['where']['slug']) ? $_POST['where']['slug'] : '');
        if (((!empty($slug_old) && ($slug_old != $this->getSlug())) || !isset($slug_old)) && !empty($this->find(array('slug' => $this->getSlug())))) {
            throw new \Exception("Le titre de l'article existe déjà : " . $this->slug);
        }

        parent::save($where);

        // Catégories & tags
        preg_match_all('#<a href="[^>]+">[^<]+</a>#', $this->getContent(), $liens);
        ORM::factory('pagetag', array('connectedUser' => $this->connectedUser))->delete(array('article' => $this->getId(), 'user' => $this->user->getId()));
        foreach ($liens[0] as $lien) {
            $explode = explode('categories[]=', $lien);
            if (!empty($explode[1])) {
                $explode2 = explode(':', $explode[1]);
                if (!empty($explode2)) {
                    $explode3 = explode('" ', $explode2[1]);
                    ORM::factory('pagetag', array('connectedUser' => $this->connectedUser, 'tag' => $this->string_to_slug($explode3[0]), 'categorie' => $this->string_to_slug($explode2[0]), 'article' => $this->getId()))->save();
                }
            }
        }

        return $this->toArray();
    }

    public function delete(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($_POST['where']['slug']) && empty($where['slug'])) {
            throw new \Exception('Le slug est obligatoire');
        }

        $where = (!empty($where)) ? $where : array();
        if (empty($where) && !empty($_POST['where'])) {
            $where = $_POST['where'];
            unset($_POST['where']);
        }

        $article = $this->getQuery($where)->load()->fetch();
        parent::delete($where);

        return array('type' => $article['type']);
    }
}
