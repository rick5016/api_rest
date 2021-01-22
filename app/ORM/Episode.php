<?php

namespace App\ORM;

use App\ORM\ORM;

class Episode extends ORM
{
    private $id;
    private $saison;
    private $episode;
    private $title;
    private $description;
    private $created;
    private $updated;
    private $img;
    private $publied;
    private $user;
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

    public function getSaison()
    {
        return $this->saison;
    }

    public function getEpisode()
    {
        return $this->episode;
    }

    public function getTitle()
    {
        return $this->title;
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

    public function setEpisode($episode)
    {
        $this->episode = $episode;
    }

    public function setTitle($title)
    {
        $this->title = $title;
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
            if (!is_numeric($_POST['where']['serie'])) {
                $_POST['where']['serie'] = $this->getSerieId($_POST['where']['serie']);
                $_POST['where']['saison'] = $this->getSaisonId($_POST['where']['serie'], $_POST['where']['saison']);
            }
        }
        $results = parent::findAll($where, $select, $order);

        foreach ($results['list'] as $key => $result) {
            $results['list'][$key] = $this->addUser($result);

            if (!empty($this->connectedUser)) {
                $note = ORM::factory('note')->find(array('user' => $this->connectedUser->userid, 'episode' => $result['id']));
                if ($note) {
                    $results['list'][$key]['note'] = $note['note'];
                    $results['list'][$key]['commentaire'] = $note['commentaire'];
                }
            } else {
                $note = 0;
                $notes = ORM::factory('note')->findAll(array('episode' => $result['id']));
                if (!empty($notes['list'])) {
                    foreach ($notes['list'] as $n) {
                        $note += $n['note'];
                    }
                    $results['list'][$key]['note'] = ($note / count($notes));
                } else {
                    $results['list'][$key]['note'] = '';
                }
            }
        }

        return $results;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($_POST['where']) && empty($where)) {
            if (empty($this->getSaison())) {
                throw new \Exception("Le numéro de la saison est obligatoire.");
            }

            if (empty($this->getEpisode())) {
                throw new \Exception("Le numéro de l'épisode est obligatoire.");
            }

            if (empty($this->getTitle())) {
                throw new \Exception("Le titre de l'épisode est obligatoire.");
            }
        }

        if ((!empty($_POST['where']) || !empty($where)) && $this->find(array('saison' => $this->getSaison(), 'serie' => $this->getSerie(), 'episode' => $this->getEpisode())) !== false) {
            $serie = ORM::factory('serie')->find(array('id' => $this->getSerie()));
            throw new \Exception('La saison "' . $this->getSaison() . '" existe déjà pour la série"' . $serie['title'] . '"');
        }

        if (isset($_FILES['values'])) {
            $file = $_FILES['values'];
            $file_path_temp = $file['tmp_name']['img'];
            $typeMime = mime_content_type($file_path_temp);
            $fileSize = filesize($file_path_temp);

            if ($fileSize > 1024 * 1024 * 3) {
                throw new \Exception("Image trop volumineuse (" . (1024 * 3) . "Ko maxi) : " . ($fileSize / 1024) . 'Ko');
            }

            if (!empty($_POST['where']['id'])) {
                $episode = ORM::factory('episode')->find(array('id' => $_POST['where']['id']));
                $saison = ORM::factory('saison')->find(array('id' => $episode['saison']));
                $serie = ORM::factory('serie')->find(array('id' => $episode['serie']));
            } else {
                throw new \Exception("todo");
            }
            $fileName = $serie['slug'] . '_' . $saison['saison'] . '_' . $episode['episode'];
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
            if (!file_exists($file_path) && !mkdir($file_path, 0777)) {
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

        parent::delete($where);

        return array();
    }
}
