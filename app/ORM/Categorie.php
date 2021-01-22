<?php

namespace App\ORM;

use App\ORM\Page;
use App\ORM\ORM;
use App\Query\Query;

class Categorie extends ORM
{
    private $id;
    private $categorie;
    private $slug;
    private $created;
    public  $tags;

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

    public function getCategorie()
    {
        return $this->categorie;
    }

    public function getSlug()
    {
        return $this->slug;
    }

    public function getCreated()
    {
        return $this->created;
    }

    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setCategorie($categorie)
    {
        $this->categorie = $categorie;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function findAll(array $where = array(), $select = null, $order = null, $distinct = false, $page = false)
    {
        $results['list'] = array();

        // On récupère la page courante
        $page = '1';
        if (!empty($_POST['page'])) {
            $page = $_POST['page'];
        }
        unset($_POST['page']);

        // On récupère la recherche par tags/catégories
        $search_categorie = '';
        if (!empty($_POST['where']['categories'])) {
            $search_categorie = $_POST['where']['categories'];
        }
        unset($_POST['where']['categories']);

        // On récupère la recherche par mots
        if (!empty($_POST['where']['search'])) {
            $results['search'] = htmlentities($_POST['where']['search']);
        }
        unset($_POST['where']['search']);

        // On récupère la recherche par mots (affine)
        if (!empty($_POST['where']['affine'])) {
            $results['affine'] = htmlentities($_POST['where']['affine']);
        }
        unset($_POST['where']['affine']);
        
        // Si aucune recherche n'est effectuées alors on récupère tous les articles
        $searchTag = array();
        if (empty($search_categorie) && empty($results['search']) && empty($results['affine'])) {
            $results['list']['articles'] = ORM::factory('page', array('connectedUser' => $this->connectedUser))->findAll(array('type' => 'article'), array(), 'created desc', false, $page);;
        } else {
            // Extraction des catégories/tags recherchés
            $searchCategories = array();
            foreach (explode('|', $search_categorie) as $param) {
                if (!empty($param)) {
                    $categorieTab = explode(':', $param);
                    $searchCategories[] = $categorieTab[0];
                    if (!empty($categorieTab[1])) {
                        $searchTag[]  = $categorieTab[0] . '-' . $categorieTab[1];
                    }
                }
            }
        }

        // Récupération de toutes les catégories
        $categories = parent::findAll($where, $select, $order, $distinct);

        // Récupération de tous les tags de chaque catégorie (uniquement si une relation existe) et création de la recherche par catégories/tags
        $searchQuery = '';
        foreach ($categories['list'] as $categorie) {
            $relations_tags_categorie = ORM::factory('pagetag')->findAll(array('categorie' => $categorie->getId()), array('tag'), null, true);
            if (!empty($relations_tags_categorie)) {
                foreach ($relations_tags_categorie['list'] as $relation_tag_categorie) {
                    $tag = $relation_tag_categorie->getTag();
                    $categorie->tags['list'][] = $tag; // Insertion du tag dans la catégorie

                    // Si le tag de cette catégories fait partie de la recherche un construit la requête SQL
                    if (in_array($categorie->getSlug() . '-' . $tag->getSlug(), $searchTag)) {
                        $searchQuery .= "(tag='" . $tag->getId() . "' AND categorie='" . $categorie->getId() . "' ) OR ";
                    }
                }
                $t = $categorie->tags['list'];
                $results['list']['categories']['list'][] = $categorie;
            }
        }

        foreach ($categories['list'] as $categorie) {
            $tags = $categorie->tags;
            if (!empty($tags['list'])) {
                usort($categorie->tags['list'], array($this, "cmp"));
            }
        }

        // Si la recherche est vide on s'arrête là et on retourne le résultat
        if (empty($search_categorie) && empty($results['search']) && empty($results['affine'])) {
            return $results;
        }

        // Construction de la clauses where
        $clauses = array();
        if (!empty($results['search'])) {
            $clauses[] = "content like '%" . $results['search'] . "%'";
        }
        if (!empty($results['affine'])) {
            $clauses[] = "content like '%" . $results['affine'] . "%'";
        }
        if (!empty($searchQuery)) {
            $clauses[] = '(' . substr($searchQuery, 0, -4) . ')';
        }

        // Requête permatant de récupérer le jeu de résultat
        $query_relations = new Query(ORM::factory('page'), $clauses + array('type' => 'article'), array('title', 'slug', 'content', 'vignette', 'created', 'updated', 'blog_page.user'), array(), 'created desc', true, $page);
        if (!empty($clauses)) {
            $query_relations->addInnerjoin('pagetag', 'article = blog_page.id');
        }
        $results['list']['articles']['list'] = ORM::factory('page')->fetchAll($query_relations->load()->fetchAll());

        // Requête permatant de récupérer les informations pour la pagination
        $query_nb_results = new Query(ORM::factory('page'), $clauses + array('type' => 'article'), array('count(distinct blog_page.id) as nb'));
        if (!empty($clauses)) {
            $query_nb_results->addInnerjoin('pagetag', 'article = blog_page.id');
        }
        $nb_results = $query_nb_results->load()->fetch();
        $results['list']['articles']['nb_result'] = $nb_results['nb'];
        $results['list']['articles']['nb_page'] = ceil($nb_results['nb'] / 5);

        return $results;
    }
    public function cmp($a, $b)
    {
        if ($a == $b) {
            return 0;
        }
        if ($a->getTag() < $b->getTag()) {
            if (is_numeric($a->getTag())) {
                return 1;
            }
            return -1;
        } else {
            if (is_numeric($a->getTag())) {
                return -1;
            }
            return 1;
        }
    }

    public function searchTagsArticlesInTagsSearch(Page $article, array $tagsSearch): bool {
        $tagsSearchIn = array();
        foreach ($tagsSearch['list'] as $tagSearch) {
            $tagsSearchIn[] = $tagSearch->getId();
        }
        if(ORM::factory('pagetag')->findAll(array('article' => $article->getId(), 'tag' => $tagsSearchIn))) {
            return true;
        }

        return false;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($this->getCategorie())) {
            throw new \Exception("La catégorie est obligatoire.");
        }

        $this->setSlug($this->string_to_slug($this->getCategorie()));
        
        return parent::save($where);
    }

    public function delete(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        return parent::delete($where);
    }
}
