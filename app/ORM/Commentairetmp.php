<?php

namespace App\ORM;

use App\ORM\ORM;

class Commentairetmp extends ORM
{
    protected $id;
    protected $content;
    protected $created;
    protected $updated;
    protected $publied;
    protected $page;
    protected $try;
    protected $user;
    protected $own;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'),
        );
    }

    public function save(array $where = array())
    {
        $commentairetmp = ORM::factory('commentairetmp', array('connectedUser' => $this->connectedUser))->findAll();
        if (!empty($commentairetmp['list']) && count($commentairetmp['list']) > 100) {
            return array('valid' => 'surcharge');
        }

        // Validation de la captcha
        if (!empty($_POST['coords'])) {
            $blog_ROOT = ($_SERVER['HTTP_HOST'] == '127.0.0.1:8081') ? '../../blog' : '../../www/blog';
            $valid = true;
            $coords = json_decode($_POST['coords']);
            foreach ($coords as $coord) {
                if ($valid) {
                    $id = explode('captcha', $coord->id);
                    $x = explode('px', $coord->x);
                    $y = explode('px', $coord->y);
                    $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser))->find(array('where' => array('id' => $id[1])));
                    if (($x[0] < ($captcha->getX() - 25)) || ($x[0] > ($captcha->getX() + 25)) || ($y[0] < ($captcha->getY() - 25)) || ($y[0] > ($captcha->getY() + 25))) {
                        $valid = false;
                    }
                }
            }

            // On récupère le commentaire temporaire
            $commentairetmp = ORM::factory('commentairetmp', array('connectedUser' => $this->connectedUser))->find(array('where' => array('id' => $captcha->getCommentaire())));

            // On supprime les captchas
            $captchas = ORM::factory('captcha', array('connectedUser' => $this->connectedUser))->findAll(array('where' => array('commentaire' => $captcha->getCommentaire())));
            foreach ($captchas['list'] as $captcha) {
                unlink($blog_ROOT . '/img/tmp/captcha' . $captcha->getId() . '.png');
                $captcha->delete(array('id' => $captcha->getId()));
            }

            if ($valid) {
                ORM::factory('commentaire', array('connectedUser' => $this->connectedUser, 'content' => $commentairetmp->content, 'page' => $commentairetmp->page))->save();

                // On supprime le commentaire temporaire
                unlink($blog_ROOT . '/img/tmp/' . $commentairetmp->getId() . '_captcha_' . $commentairetmp->get('try') . '.jpg');
                $commentairetmp->delete(array('id' => $commentairetmp->id));

                return array('valid' => true);
            } else if ($commentairetmp->get('try') < 3) {
                $this->try = ($commentairetmp->try + 1);
                parent::save(array('where' => array('id' => $commentairetmp->id)));
                return $this->setCaptcha($this);
            } else {
                unlink($blog_ROOT . '/img/tmp/' . $commentairetmp->id . '_captcha_' . $commentairetmp->try . '.jpg');
                $commentairetmp->delete(array('where' => array('id' => $commentairetmp->id)));
                return array('valid' => false);
            }
        }

        if (empty($this->content)) {
            throw new \Exception("Le contenu du commentaire est obligatoire.");
        }

        if (!empty($this->page) && !is_int($this->page)) {
            $page = ORM::factory('page', array('connectedUser' => $this->connectedUser))->findArray(array('where' => array('slug' => $this->page)));
            if (!empty($page)) {
                $this->page = $page['id'];
            }
        }

        if (empty($this->page)) {
            throw new \Exception("L'article est obligatoire.");
        }

        parent::save($where);

        return $this->setCaptcha($this);
    }

    private function setCaptcha($commentairetmp)
    {
        $blog_ROOT = ($_SERVER['HTTP_HOST'] == '127.0.0.1:8081') ? '../../blog' : '../../www/blog';
        // Fabrication des captcha
        $captchas = array();
        // On la créé
        $iteration = opendir($blog_ROOT . '/img/captcha');
        $fichiers = array();
        while (($fichier = readdir($iteration)) !== false) {
            if ($fichier != "." && $fichier != "..") {
                $fichiers[] = $fichier;
            }
        }
        $picture = $fichiers[rand(0, (count($fichiers) - 1))];
        $im = imagecreatefromjpeg($blog_ROOT . '/img/captcha/' . $picture);
        $nb = rand(1, 2);
        $coords = array();
        $largeur_max = $_POST['width'] - 110;
        if (imagesx($im) > $largeur_max) {
            $nb = 1;
            $im = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => $largeur_max, 'height' => imagesy($im)]);
        }
        for ($i = 0; $i < $nb; $i++) {
            // Création de l'image
            $coords = $this->setCoord($im, $coords);
            $im2 = imagecrop($im, ['x' => $coords[$i]['x'], 'y' => $coords[$i]['y'], 'width' => $coords[$i]['largeur'], 'height' => $coords[$i]['longueur']]);

            // Enregistrement des coordonnées
            $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser, 'x' => $coords[$i]['x'], 'y' => $coords[$i]['y'], 'commentaire' => $commentairetmp->getId()))->save();

            // Enregistrement du fichier
            if ($im2 !== false) {
                $coords[$i]['id'] = 'captcha' . $captcha->getId();
                $captchas['captcha'] = $coords;
                imagepng($im2, $blog_ROOT . '/img/tmp/captcha' . $captcha->getId() . '.png');
                imagedestroy($im2);
            }
        }

        $captchas['try'] = (empty($commentairetmp->try)) ? 1 : $commentairetmp->try;
        $captchas['picture'] = $commentairetmp->id . '_captcha_' . $captchas['try'] . '.jpg';
        imagejpeg($im, $blog_ROOT . '/img/tmp/' . $captchas['picture']);
        imagedestroy($im);

        foreach ($captchas['captcha'] as $key => $captcha) {
            unset($captcha['x']);
            unset($captcha['y']);
            $captchas['captcha'][$key] = $captcha;
        }

        return $captchas;
    }

    private function setCoord($im, $exists = array(), $i = 0)
    {
        $tab = array();
        $tab['largeur'] = rand(75, 125);
        $tab['longueur'] = rand(75, 125);
        $tab['x'] = rand(1, imagesx($im) - $tab['largeur']);
        $tab['y'] = rand(1, imagesy($im) - $tab['longueur']);

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
