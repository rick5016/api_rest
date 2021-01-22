<?php

namespace App\ORM;

use App\ORM\ORM;
use App\Query\BDD;

class Serie extends ORM
{
    private $id;
    private $title;
    private $slug;
    private $description;
    private $created;
    private $updated;
    private $img;
    private $publied;
    private $user;

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

    public function getTitle()
    {
        return $this->title;
    }

    public function getSlug()
    {
        return $this->slug;
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

    public function getImg()
    {
        return $this->img;
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

    public function setImg($img)
    {
        if (!empty($img)) {
            $this->img = $img;
        }
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
        $results = parent::findAll($where, $select, $order);

        if (!empty($results['list'])) {
            foreach ($results['list'] as $key => $result) {
                $results['list'][$key] = $this->addUser($result);
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
            throw new \Exception("Le titre de la série est obligatoire.");
        }

        if (!empty($where['slug'])) {
            $slug_old = $where['slug'];
        } else if (!empty($_POST['where']['slug'])) {
            $slug_old = $_POST['where']['slug'];
        }
        // Si c'est un update et que les 2 slugs ne correspondent pas OU si c'est une insert : on vérifie que le slug n'existe pas déjà en BDD
        if (((isset($slug_old) && ($slug_old != $this->getSlug())) || !isset($slug_old)) && !empty($this->find(array('slug' => $this->getSlug())))) {
            throw new \Exception("Le titre de la série existe déjà : " . $this->slug);
        }

        if (isset($_FILES['values'])) {
            $file = $_FILES['values'];
            $file_path_temp = $file['tmp_name']['img'];
            $typeMime = mime_content_type($file_path_temp);
            $fileSize = filesize($file_path_temp);

            if ($fileSize > 1024 * 1024 * 3) {
                throw new \Exception("Image trop volumineuse (" . (1024 * 3) . "Ko maxi) : " . ($fileSize / 1024) . 'Ko');
            }

            $fileName = $this->getSlug();
            if ($typeMime === "image/png") {
                $fileName .= '.png';
            } elseif ($typeMime === "image/jpeg") {
                $fileName .= '.jpg';
            } else {
                throw new \Exception("Format d'image non autorisé");
            }
            if ($_SERVER['HTTP_HOST'] == '127.0.0.1') {
                $file_path = \dirname(__DIR__, 2) . '/www/img';
            } else {
                $file_path = \dirname(__DIR__, 3) . '/www/api_rest/img';
            }
            if (!file_exists($file_path) && !mkdir($file_path, 0757)) {
                throw new \Exception("Une erreur c'est produite lors de l'enregistrement de l'image");
            }

            if (file_exists($file_path . DIRECTORY_SEPARATOR . $fileName)) {
                unlink($file_path . DIRECTORY_SEPARATOR . $fileName);
            }
            rename($file_path_temp, $file_path . DIRECTORY_SEPARATOR . $fileName);
            chmod($file_path . DIRECTORY_SEPARATOR . $fileName, 0757);
            $this->setImg($fileName);
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
        if (empty($_POST['where']) || (empty($_POST['where']['id']) && empty($_POST['where']['slug']))) {
            throw new \Exception("L'identifiant de la série est obligatoire");
        }

        if (!empty($_POST['where']) && !empty($_POST['where']['slug'])) {
            $_POST['where']['id'] = $this->getSerieId($_POST['where']['slug']);
        }

        try {
            BDD::getConnection()->beginTransaction();

            // Suppression des saisons liés
            $saisons = ORM::factory('saison', array('connectedUser' => $this->connectedUser))->findAll(array('serie' => $_POST['where']['id']));
            if (!empty($saisons['list']) && is_array($saisons['list']) && count($saisons['list']) > 0) {
                foreach ($saisons['list'] as $saison) {
                    if (!empty($saison['own']) && $saison['own'] === true) {
                        ORM::factory('saison', array('connectedUser' => $this->connectedUser))->delete(array('id' => $saison['id']), false);
                    } else {
                        throw new \Exception("La saison a été créé par une autre personne et ne peux pas être supprimé");
                    }
                }
            }
            parent::delete($where);
            BDD::getConnection()->commit();
        } catch (\Exception $e) {
            BDD::getConnection()->rollBack();
            throw new \Exception($e->getMessage());
        }

        return array();
    }

    private function getSerieId($serie)
    {
        if (is_string($serie)) {
            $result = ORM::factory('serie')->find(array('slug' => $serie));
            $serie = $result['id'];
        }
        return (int) $serie;
    }
}
