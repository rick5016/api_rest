<?php

namespace App\ORM;

use App\ORM\ORM;

class Commentairetmp extends ORM
{
    private $id;
    private $content;
    private $created;
    private $updated;
    private $publied;
    private $article;
    private $try;
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

    public function getTry()
    {
        return $this->try;
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
        } else if (is_array($article) && !empty($article['id'])){
            $this->article = ORM::factory('page', array('connectedUser' => $this->connectedUser))->find(array('id' => $article['id']));
        } else {
            $this->article = ORM::factory('page', array('connectedUser' => $this->connectedUser))->find(array('slug' => $article));
        }
    }

    public function setTry($try)
    {
        $this->try = $try;
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
        $commentairetmp = ORM::factory('commentairetmp', array('connectedUser' => $this->connectedUser))->findAll();
        if (!empty($commentairetmp['list']) && count($commentairetmp['list']) > 20) {
            return array('valid' => 'surcharge');
        }

        // Validation de la captcha
        if (!empty($_POST['values']['coords'])) {
            $valid = true;
            $coords = json_decode($_POST['values']['coords']);
            foreach ($coords as $coord) {
                if ($valid) {
                    $id = explode('captcha', $coord->id);
                    $x = explode('px', $coord->x);
                    $y = explode('px', $coord->y);
                    $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser))->find(array('id' => $id[1]));
                    $xx = $captcha->getX();
                    $yy = $captcha->getY();
                    if (($x[0] < ($captcha->getX() - 25)) || ($x[0] > ($captcha->getX() + 25)) || ($y[0] < ($captcha->getY() - 25)) || ($y[0] > ($captcha->getY() + 25))) {
                        $valid = false;
                    }
                }
            }

            // On récupère le commentaire temporaire
            $commentairetmp = ORM::factory('commentairetmp', array('connectedUser' => $this->connectedUser))->find(array('id' => $captcha->getCommentaire()));

            // On supprime les captchas
            $captchas = ORM::factory('captcha', array('connectedUser' => $this->connectedUser))->findAll(array('commentaire' => $captcha->getCommentaire()));
            foreach ($captchas['list'] as $captcha) {
                unlink('../blog/img/tmp/captcha' . $captcha->getId() . '.png');
                $captcha->delete(array('id' => $captcha->getId()));
            }

            if ($valid) {
                $commentaire = ORM::factory('commentaire', array('connectedUser' => $this->connectedUser));
                $commentaire->setContent($commentairetmp->getContent());
                $commentaire->setArticle($commentairetmp->getArticle());
                $commentaire->save();

                // On supprime le commentaire temporaire
                unlink('../blog/img/tmp/' . $commentairetmp->getId() . '_captcha_' . $commentairetmp->getTry() . '.jpg');
                $commentairetmp->delete(array('id' => $commentairetmp->getId()));
                
                return array('valid' => true);
            } else if ($commentairetmp->getTry() < 3) {
                $this->setTry($commentairetmp->getTry() + 1);
                parent::save(array('id' => $commentairetmp->getId()));
                return $this->setCaptcha($this);
            } else {
                unlink('../blog/img/tmp/' . $commentairetmp->getId() . '_captcha_' . $commentairetmp->getTry() . '.jpg');
                $commentairetmp->delete(array('id' => $commentairetmp->getId()));
                return array('valid' => false);
            }
        }
        
        if (empty($this->getContent())) {
            throw new \Exception("Le contenu du commentaire est obligatoire.");
        }

        parent::save($where);

        return $this->setCaptcha($this);
    }

    private function setCaptcha($commentairetmp) {
        // Fabrication des captcha
        $captchas = array();
        // On la créé
        $width = $_POST['values']['width'];
        $iteration = opendir('img/captcha');
        $fichiers = array();
        while(($fichier = readdir($iteration)) !== false)  {
            if($fichier != "." && $fichier != ".."){
                $fichiers[] = $fichier;
            }
        }
        $picture = $fichiers[rand(0, (count($fichiers) - 1))];
        $im = imagecreatefromjpeg('img/captcha/' . $picture);
        $nb = rand(1, 2);
        $coords = array();
        $largeur_max = $_POST['values']['width'] - 110;
        if (imagesx($im) > $largeur_max) {
            $nb = 1;
            $im = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => $largeur_max, 'height' => imagesy($im)]);
        }
        for ($i = 0; $i < $nb; $i++) {
            // Création de l'image
            $coords = $this->setCoord($im, $coords);
            $im2 = imagecrop($im, ['x' => $coords[$i]['x'], 'y' => $coords[$i]['y'], 'width' => $coords[$i]['largeur'], 'height' => $coords[$i]['longueur']]);

            // Enregistrement des coordonnées
            $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser));
            $captcha->setX($coords[$i]['x']);
            $captcha->setY($coords[$i]['y']);
            $captcha->setCommentaire($commentairetmp->getId());
            $captcha->save();

            // Enregistrement du fichier
            if ($im2 !== false) {
                $coords[$i]['id'] = 'captcha' . $captcha->getId();
                $captchas['captcha'] = $coords;
                imagepng($im2, '../blog/img/tmp/captcha' . $captcha->getId() . '.png');
                imagedestroy($im2);
            }
        }

        $captchas['try'] = (empty($commentairetmp->getTry())) ? 1 : $commentairetmp->getTry();
        $captchas['picture'] = $commentairetmp->getId() . '_captcha_' . $captchas['try'] . '.jpg';
        imagejpeg($im, '../blog/img/tmp/' . $captchas['picture']);
        imagedestroy($im);

        foreach ($captchas['captcha'] as $key => $captcha)
        {
            unset($captcha['x']);
            unset($captcha['y']);
            $captchas['captcha'][$key] = $captcha;
        }

        return $captchas;
    }

    private function setCoord($im, $exists = array(), $i = 0)
    {
        $return = true;
        $tab = array();
        $tab['largeur'] = rand(75, 125);
        $tab['longueur'] = rand(75, 125);
        $tab['x'] = rand(1, imagesx($im) - $tab['largeur']);
        $tab['y'] = rand(1, imagesy($im) - $tab['longueur'] );

        foreach ($exists as $exist) {
            if (($tab['x'] < ($exist['x'] + $exist['largeur']) && (($tab['x'] + $tab['largeur']) > $exist['x'])) && 
            ($tab['y'] < ($exist['y'] + $exist['longueur']) && ($tab['y'] + $tab['longueur']) > $exist['y'])) {
                return $this->setCoord($im, $exists, $i);
            }
        }
        $exists[] = $tab;

        return $exists;
    }

    public function delete(array $where = array())
    {
        if (empty($_POST['where']['id']) && empty($where['id'])) {
            throw new \Exception('L\'id est obligatoire');
        }
        parent::delete($where);

        return array();
    }
}
