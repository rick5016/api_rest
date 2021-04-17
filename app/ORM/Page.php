<?php

namespace App\ORM;

use App\ORM\ORM;

class Page extends ORM
{
    protected $id;
    protected $title;
    protected $slug;
    protected $content;
    protected $vignette;
    protected $created;
    protected $updated;
    protected $version;
    protected $type;
    protected $publied;
    protected $user;
    public $own;
    public $similaires;
    public $commentaires;

    public function find(array $properties = array(), $cascade = array())
    {
        $page = 1;
        if (isset($_POST['page'])) {
            $page = (int) $_POST['page'];
            unset($_POST['page']);
        }

        $result = parent::find($properties, array('user'));

        // On va chercher les 10 derniers commentaires
        //$result->commentaires = ORM::factory('commentaire', array('connectedUser' => $this->connectedUser))->findAll(array('where' => array('page' => $this->id), 'order' => 'created', 'page' => $page), array('user'));

        // On va cherche les articles similaires
        $clauses = array();

        $queryTheme = 'tag in (';
        // Type (8) de l'article
        $type = ORM::factory('pagetag')->find(array('where' => array('page' => $this->id, 'categorie' => 8), 'select' => array('tag')));

        // On va chercher toutes les relations de catégorie thème (14) de l'article
        $themes = ORM::factory('pagetag')->findAll(array('where' => array('page' => $this->id, 'categorie' => 14), 'select' => array('tag', 'categorie')));
        if (!empty($themes['list'])) {
            foreach ($themes['list'] as $theme) {
                $queryTheme .= $theme->getTag() . ', ';
            }
            $clauses[] = substr($queryTheme, 0, -2) . ')';
            $clauses[] = 'page != ' . $this->id;
            $relations = ORM::factory('pagetag')->findAll(array('where' => $clauses, 'select' => array('page'), 'distinct' => true), array('page'));
        } else if (!empty($type)) {
            $clauses['tag'] = $type->getTag();
            $clauses[] = 'page != ' . $this->id;
            $relations = ORM::factory('pagetag')->findAll(array('where' => $clauses, 'select' => array('page'), 'distinct' => true), array('page'));
        }
        if (!empty($relations['list']) && !empty($type)) {
            foreach ($relations['list'] as $relation) {
                if (ORM::factory('pagetag')->find(array('select' => array('page' => $relation->getPage()['id'], 'categorie' => 8, 'tag' => $type->getTag()))) !== false) {
                    $result->similaires['list'][] = $relation->getPage();
                }
            }
            shuffle($result->similaires['list']);
        }
    
        /** @var Statistiquepagetmp $log */
        $log = ORM::factory('statistiquepagetmp');
        $log->log($result->slug);

        return $result;
    }

    public function save(array $where = array())
    {
        if (empty($this->title)) {
            throw new \Exception("Le titre de l'article est obligatoire.");
        }

        if (!empty($this->title)) {
            $this->slug = $this->string_to_slug($this->title);
        }

        if (empty($this->slug)) {
            throw new \Exception("Une erreur est survenue lord de la transformation du titre en slug.");
        }

        if (empty($this->content)) {
            throw new \Exception("Le contenu de l'article est obligatoire.");
        }

        if (empty($this->type)) {
            throw new \Exception("Le type de l'article est obligatoire.");
        }

        // Si c'est un update et que les 2 slugs ne correspondent pas OU si c'est une insert : on vérifie que le slug n'existe pas déjà en BDD
        $slug_old = !empty($where['slug']) ? $where['slug'] : (!empty($_POST['where']['slug']) ? $_POST['where']['slug'] : '');
        if (((!empty($slug_old) && ($slug_old != $this->slug)) || !isset($slug_old)) && !empty($this->find(array('where' => array('slug' => $this->slug))))) {
            throw new \Exception("Le titre de l'article existe déjà : " . $this->slug);
        }

        parent::save($where);

        // Catégories & tags
        preg_match_all('#<a href="[^>]+">[^<]+</a>#', $this->content, $liens);
        ORM::factory('pagetag', array('connectedUser' => $this->connectedUser))->delete(array('page' => $this->id, 'user' => $this->user));
        foreach ($liens[0] as $lien) {
            $explode = explode('categories[]=', $lien);
            if (!empty($explode[1])) {
                $explode2 = explode(':', $explode[1]);
                if (!empty($explode2)) {
                    $explode3 = explode('" ', $explode2[1]);
                    ORM::factory('pagetag', array('connectedUser' => $this->connectedUser, 'tag' => $this->string_to_slug($explode3[0]), 'categorie' => $this->string_to_slug($explode2[0]), 'page' => $this->slug))->save();
                }
            }
        }

        return $this->toArray();
    }

    public function delete(array $where = array())
    {
        if (empty($_POST['where']['slug']) && empty($where['slug'])) {
            throw new \Exception('Le slug est obligatoire');
        }

        parent::delete($where);

        return array();
    }
}
