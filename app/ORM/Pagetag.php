<?php

namespace App\ORM;

use App\ORM\ORM;
use App\Query\Query;

class Pagetag extends ORM
{
    protected $id;
    protected $tag;
    protected $categorie;
    protected $page;
    protected $user;

    public function getTag()
    {
        return $this->tag;
    }

    public function getPage()
    {
        return $this->page;
    }

    public function save(array $where = array())
    {
        if (empty($this->tag)) {
            throw new \Exception("Le tag est obligatoire.");
        }

        if (empty($this->categorie)) {
            throw new \Exception("La catégorie est obligatoire.");
        }

        if (empty($this->page)) {
            throw new \Exception("L'article est obligatoire.");
        }

        // Vérification de l'existance de l'article
        $article = ORM::factory('page', array('connectedUser' => $this->connectedUser))->find(array('where' => array('slug' => $this->page)));
        if (empty($article)) {
            throw new \Exception("L'article est introuvable.");
        }
        $this->page = $article->getId();

        // Enregistrement du tag
        $tag = ORM::factory('tag', array('connectedUser' => $this->connectedUser))->find(array('where' => array('slug' => $this->tag)));
        if (empty($tag)) {
            $tag = ORM::factory('tag', array('connectedUser' => $this->connectedUser, 'slug' => $this->tag))->save();
        }
        $this->tag = $tag->getId();

        // Enregistrement de la catégorie
        $categorie = ORM::factory('categorie', array('connectedUser' => $this->connectedUser))->find(array('where' => array('slug' => $this->categorie)));
        if (empty($categorie)) {
            $categorie = ORM::factory('categorie', array('connectedUser' => $this->connectedUser, 'slug' => $this->categorie))->save();
        }
        $this->categorie = $categorie->getId();

        if (empty($this->find(array('where' => array('tag' => $this->tag, 'categorie' => $this->categorie, 'page' => $this->page))))) {
            return parent::save($where);
        }

        return parent::toArray();
    }

    public function findAll(array $properties = array(), $cascade = array())
    {
        $accueil = false;
        if (!empty($_POST['accueil'])) {
            $accueil = true;
        }

        if (!empty($properties)) {
            return parent::findAll($properties, $cascade);
        }

        $results['list'] = array();

        // Nombre d'articles par page
        $nbResultByPage = '10';
        if (!empty($_POST['nbResultByPage']) && $_POST['nbResultByPage'] <= 50) {
            $nbResultByPage = $_POST['nbResultByPage'];
        }

        // Page courante
        $page = '1';
        if (!empty($_POST['page'])) {
            $page = $_POST['page'];
        }

        // Construction de la clause WHERE
        $clauses = array();

        // Recherche par type d'article (champ type dans page)
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
                and blog_page.id = t2.page) > " . (count($search_categories) - 1);
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

        // On récupère tous les articles sans pagination afin de pouvoir récupérer uniquement les catégories necessaires.
        $query_relations = new Query(ORM::factory('page'), $clauses, array('blog_page.id', 'title', 'blog_page.slug', 'content', 'vignette', 'blog_page.created', 'blog_page.updated', 'blog_page.user'), array(), 'created desc', true);
        $query_relations->addLeftjoin('pagetag', 'page = blog_page.id');
        $query_relations->addLeftjoin('categorie', 'blog_pagetag.categorie = blog_categorie.id');
        $query_relations->addLeftjoin('tag', 'blog_pagetag.tag = blog_tag.id');
        $articles = $query_relations->load()->fetchAll();

        // On récupère les catégories et leurs tags en fonciton des articles retournés (on en profite pour générer les informations de pagination).
        $results['list']['categories']['list'] = array();
        $results['list']['articles']['list'] = array();
        $results['list']['articles']['nb_result'] = count($articles);
        $results['list']['articles']['nb_page'] = ceil($results['list']['articles']['nb_result'] / $nbResultByPage);

        foreach ($articles as $article) {
            $relations = ORM::factory('pagetag')->findAllArray(array('where' => array('page' => $article['id']), 'select' => array('categorie', 'tag'), 'distinct' => true), array('categorie', 'tag'));
            if (!empty($relations['list'])) {
                foreach ($relations['list'] as $relation) {
                    // Récupération de la catégorie
                    $categorie = $relation['categorie'];
                    if (array_key_exists($categorie['slug'], $results['list']['categories']['list'])) {
                        $categorie = $results['list']['categories']['list'][$categorie['slug']];
                    }

                    //Récupération du tag
                    $tag = $relation['tag'];
                    $exist = false;
                    if (!empty($categorie['tags']['list'])) {
                        foreach ($categorie['tags']['list'] as $key => $tagCategorie) {
                            if ($tag['slug'] == $tagCategorie['slug']) {
                                $exist = true;
                                $categorie['tags']['list'][$key]['count']++;
                            }
                        }
                    }
                    if (!$exist) {
                        $tag['count'] = 1;
                        $tag['lastArticle'] = $article;
                        $categorie['tags']['list'][] = $tag;
                    }

                    $results['list']['categories']['list'][$categorie['slug']] = $categorie;
                }
            }
        }

        // Si les catégories selectionnées n'existe pas dans le jeu de résultat on les récupère avec leur tags
        if (!empty($search_categories)) {
            foreach ($search_categories as $search_categorie) {
                $exist = false;
                foreach ($results['list']['categories']['list'] as $categorie) {
                    if ($search_categorie == $categorie['slug']) {
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
        foreach ($results['list']['categories']['list'] as $categorie_key => $categorie) {
            if (!empty($results['list']['categories']['list'][$categorie_key]['tags']['list'])) {
                usort($results['list']['categories']['list'][$categorie_key]['tags']['list'], array($this, "cmp"));
            }
        }

        // Résultat final
        $query_relations = new Query(ORM::factory('page'), $clauses, array('blog_page.id', 'title', 'blog_page.slug', 'content', 'vignette', 'blog_page.created', 'blog_page.updated', 'blog_page.user'), array(), 'created desc', true, $page, $nbResultByPage);
        $query_relations->addLeftjoin('pagetag', 'page = blog_page.id');
        $query_relations->addLeftjoin('categorie', 'blog_pagetag.categorie = blog_categorie.id');
        $query_relations->addLeftjoin('tag', 'blog_pagetag.tag = blog_tag.id');
        $datas = $query_relations->load()->fetchAll();

        // Récupération du créateur de l'article
        if (!empty($datas)) {
            foreach ($datas as $result) {
                if (!empty($result['user'])) {
                    $result['user'] = ORM::factory('user')->findArray(array('where' => array('id' => (int) $result['user'])));
                }
                $result['own'] = false;
                if (!empty($result['user'])) {
                    if (!empty($this->connectedUser) && $this->connectedUser->userid == $result['user']['id']) {
                        $result['own'] = true;
                    }
                }
                $results['list']['articles']['list'][] = $result;
            }
        }
    
        if (!$accueil) {
            /** @var Statistiquesearchtmp $log */
            $log = ORM::factory('statistiquesearchtmp');
            $log->log();
        }

        return $results;
    }

    public function cmp($a, $b)
    {
        if ($a == $b) {
            return 0;
        }

        if ($a['tag'] < $b['tag']) {
            if (is_numeric($a['tag'])) {
                return 1;
            }
            return -1;
        } else {
            if (is_numeric($a['tag'])) {
                return -1;
            }
            return 1;
        }
    }
}
