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

    public function findAll(array $where = array(), $select = null, $order = null, $distinct = false, $page = false, $nb_page = '5')
    {
        $results['list'] = array();

        // Si on vient de l'accueil (pas utilisé)
        $accueil = false;
        if (!empty($_POST['accueil']) && $_POST['accueil'] === '1') {
            $accueil = true;
        }
        unset($_POST['accueil']);

        // Nombre d'articles par page
        $nb_page = '10';
        if (!empty($_POST['nb_page']) && $_POST['nb_page'] <= 50) {
            $nb_page = $_POST['nb_page'];
        }
        unset($_POST['nb_page']);

        // Page courante
        $page = '1';
        if (!empty($_POST['page'])) {
            $page = $_POST['page'];
        }
        unset($_POST['page']);
        
        $clauses = array();

        // Type d'article
        if (!empty($_POST['where']['type']) && in_array($_POST['where']['type'], array('article', 'news', 'tuto'))) {
            $clauses['type'] = $_POST['where']['type'];
        }
        unset($_POST['where']['type']);

        // Recherche par tags/catégories
        $search_categorie = '';
        $search_categories = array();
        $searchQuery = '';
        if (!empty($_POST['where']['categories'])) {
            $search_categorie = $_POST['where']['categories'];
            $searchCategories = array();
            foreach (explode('|', $search_categorie) as $param) {
                if (!empty($param)) {
                    $categorieTab = explode(':', $param);
                    $searchCategories[] = $categorieTab[0];
                    if (!empty($categorieTab[1])) {
                        $search_categories[] = $categorieTab[0];
                        $searchQuery .= "(t3.slug ='" . $categorieTab[0] . "' AND t4.slug ='" . $categorieTab[1] . "' ) OR ";
                    }
                }
            }
            if (!empty($searchQuery)) {
                $clauses[] = "(select count(*) as nb
                from blog_pagetag t2
                inner join blog_categorie t3 on t2.categorie = t3.id 
                inner join blog_tag t4 on t2.tag = t4.id 
                where (" . substr($searchQuery, 0, -4) . ")
                and blog_page.id = t2.article) > " . (count($search_categories) - 1);
            }
        }
        unset($_POST['where']['categories']);

        // Recherche par mots
        if (!empty($_POST['where']['search'])) {
            $results['search'] = htmlentities($_POST['where']['search']);
            $clauses[] = "content like '%" . $results['search'] . "%'";
        }
        unset($_POST['where']['search']);

        // Recherche affinée
        if (!empty($_POST['where']['affine'])) {
            $results['affine'] = htmlentities($_POST['where']['affine']);
            $clauses[] = "content like '%" . $results['affine'] . "%'";
        }
        unset($_POST['where']['affine']);

        // On récuoère tous les articles sans pagination
        $query_relations = new Query(ORM::factory('page'), $clauses, array('blog_page.id', 'title', 'blog_page.slug', 'content', 'vignette', 'blog_page.created', 'blog_page.updated', 'blog_page.user'), array(), 'created desc', true);
        $query_relations->addLeftjoin('pagetag', 'article = blog_page.id');
        $query_relations->addLeftjoin('categorie', 'blog_pagetag.categorie = blog_categorie.id');
        $query_relations->addLeftjoin('tag', 'blog_pagetag.tag = blog_tag.id');
        $articles = ORM::factory('page')->fetchAll($query_relations->load()->fetchAll());

        $results['list']['categories']['list']    = array();
        $results['list']['articles']['list']      = array();
        $results['list']['articles']['nb_result'] = count($articles);
        $results['list']['articles']['nb_page']   = ceil($results['list']['articles']['nb_result'] / $nb_page);

        // Récupération et compte des catégories et des tags
        $categories = array();
        foreach ($articles as $article) {
            $relations = ORM::factory('pagetag')->findAll(array('article' => $article->getId()), array('categorie', 'tag'), null, true);
            if (!empty($relations['list'])) {
                foreach ($relations['list'] as $relation) {
                    // Récupération de la catégorie
                    $categorie = $relation->getCategorie();
                    if (array_key_exists($categorie->getSlug(), $results['list']['categories']['list'])) {
                        $categorie = $results['list']['categories']['list'][$categorie->getSlug()];
                    }

                    //Récupération du tag
                    $tag = $relation->getTag();
                    $exist = false;
                    if (!empty($categorie->tags['list'])) {
                        foreach ($categorie->tags['list'] as $tag_categorie) {
                            if ($tag->getSlug() == $tag_categorie->getSlug()) {
                                $exist = true;
                                $tag_categorie->count++;
                            }
                        }
                    }
                    if (!$exist) {
                        $tag->count = 1;
                        $categorie->tags['list'][] = $tag;
                    }

                    $results['list']['categories']['list'][$categorie->getSlug()] = $categorie;
                }
            }
        }

        // Si les catégories selectionnées n'existe pas dans le jeu de résultat on les récupère avec leur tags
        if (!empty($search_categories)) {
            foreach ($search_categories as $search_categorie) {
                $exist = false;
                foreach ($results['list']['categories']['list'] as $categorie) {
                    if ($search_categorie == $categorie->getSlug()) {
                        $exist = true;
                    }
                }
                if (!$exist) {
                    $cat = ORM::factory('categorie')->find(array('categorie' => $search_categorie), array('id'));
                    $relations = ORM::factory('pagetag')->findAll(array('categorie' => $cat->getId()), array('categorie', 'tag'), null, true);
                    if (!empty($relations['list'])) {
                        foreach ($relations['list'] as $relation) {
                            // Récupération de la catégorie
                            $categorie = $relation->getCategorie();
                            if (array_key_exists($categorie->getSlug(), $results['list']['categories']['list'])) {
                                $categorie = $results['list']['categories']['list'][$categorie->getSlug()];
                            }
        
                            //Récupération du tag
                            $tag = $relation->getTag();
                            $exist = false;
                            if (!empty($categorie->tags['list'])) {
                                foreach ($categorie->tags['list'] as $tag_categorie) {
                                    if ($tag->getSlug() == $tag_categorie->getSlug()) {
                                        $exist = true;
                                    }
                                }
                            }
                            if (!$exist) {
                                $tag->count = 0;
                                $categorie->tags['list'][] = $tag;
                            }
        
                            $results['list']['categories']['list'][$categorie->getSlug()] = $categorie;
                        }
                    }
                }
            }
        }


        // Tri
        foreach ($results['list']['categories']['list'] as $categorie) {
            $tags = $categorie->tags;
            if (!empty($tags['list'])) {
                usort($categorie->tags['list'], array($this, "cmp"));
            }
        }

        // Résultat final
        $query_relations = new Query(ORM::factory('page'), $clauses, array('blog_page.id', 'title', 'blog_page.slug', 'content', 'vignette', 'blog_page.created', 'blog_page.updated', 'blog_page.user'), array(), 'created desc', true, $page, $nb_page);
        $query_relations->addLeftjoin('pagetag', 'article = blog_page.id');
        $query_relations->addLeftjoin('categorie', 'blog_pagetag.categorie = blog_categorie.id');
        $query_relations->addLeftjoin('tag', 'blog_pagetag.tag = blog_tag.id');
        $results['list']['articles']['list'] = ORM::factory('page')->fetchAll($query_relations->load()->fetchAll());





        // Si aucune recherche n'est effectuées alors on récupère tous les articles
        /*$searchTag = array();
        if (empty($search_categorie) && empty($results['search']) && empty($results['affine'])) {
            $results['list']['articles'] = ORM::factory('page', array('connectedUser' => $this->connectedUser))->findAll($where_type, array(), 'created desc', false, $page, $nb_page);
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
        $query_relations = new Query(ORM::factory('page'), $clauses + $where_type, array('title', 'slug', 'content', 'vignette', 'created', 'updated', 'blog_page.user'), array(), 'created desc', true, $page, $nb_page);
        if (!empty($clauses)) {
            $query_relations->addInnerjoin('pagetag', 'article = blog_page.id');
        }
        $results['list']['articles']['list'] = ORM::factory('page')->fetchAll($query_relations->load()->fetchAll());

        // Requête permatant de récupérer les informations pour la pagination
        $query_nb_results = new Query(ORM::factory('page'), $clauses + $where_type, array('count(distinct blog_page.id) as nb'));
        if (!empty($clauses)) {
            $query_nb_results->addInnerjoin('pagetag', 'article = blog_page.id');
        }
        $nb_results = $query_nb_results->load()->fetch();
        $results['list']['articles']['nb_result'] = $nb_results['nb'];
        $results['list']['articles']['nb_page'] = ceil($nb_results['nb'] / $nb_page);*/

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
