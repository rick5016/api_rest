<?php

namespace App\ORM;

use App\ORM\ORM;

class Captcha extends ORM
{
    protected $id;
    protected $x;
    protected $y;
    protected $created;
    protected $try;

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function getCommentaire()
    {
        return $this->commentaire;
    }

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'),
        );
    }

    public function  validation()
    {
        $blog_ROOT = ($_SERVER['HTTP_HOST'] == '127.0.0.1:8081') ? '../../blog' : '../../www/blog';
        $try = 3;
        $valid = true;
        $captchas = json_decode($_POST['coords']);
        $id_big_picture = '';
        foreach ($captchas as $captcha) {
            $id = explode('captcha', $captcha->id);
            $x = explode('px', $captcha->x);
            $y = explode('px', $captcha->y);
            $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser))->find(array('where' => array('id' => $id[1])));
            $id_big_picture .= $captcha->getId();
            $try = $captcha->try;
            if (($x[0] < ($captcha->getX() - 25)) || ($x[0] > ($captcha->getX() + 25)) || ($y[0] < ($captcha->getY() - 25)) || ($y[0] > ($captcha->getY() + 25))) {
                $valid = false;
            }
            // On supprime la captcha
            unlink($blog_ROOT . '/img/tmp/captcha' . $captcha->getId() . '.png');
            $captcha->delete(array('id' => $captcha->getId()));
        }

        // SUppression de l'image principal de des captchas
        unlink($blog_ROOT . '/img/tmp/' . $id_big_picture . '_captcha_' . $try . '.jpg');

        if (!$valid) {
            if ($try < 3) {
                return ORM::factory('captcha')->save(array('try' => $try + 1));
            } else {
                return array('valid' => false);
            }
        }

        return $valid;
    }

    public function save(array $where = array())
    {
        if (!empty($this->x) && !empty($this->y)) {
            return parent::save($where);
        } else {
            if (empty($where['try'])) {
                $where['try'] = 1;
            }
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
            $id_big_picture = '';
            for ($i = 0; $i < $nb; $i++) {
                // Création de l'image
                $coords = $this->setCoord($im, $coords);
                $im2 = imagecrop($im, ['x' => $coords[$i]['x'], 'y' => $coords[$i]['y'], 'width' => $coords[$i]['largeur'], 'height' => $coords[$i]['longueur']]);

                // Enregistrement des coordonnées
                $captcha = ORM::factory('captcha', array('connectedUser' => $this->connectedUser, 'x' => $coords[$i]['x'], 'y' => $coords[$i]['y'], 'try' => $where['try']))->save();
                $id_big_picture .= $captcha->getId();

                // Enregistrement du fichier
                if ($im2 !== false) {
                    $coords[$i]['id'] = 'captcha' . $captcha->getId();
                    $captchas['captcha'] = $coords;
                    imagepng($im2, $blog_ROOT . '/img/tmp/captcha' . $captcha->getId() . '.png');
                    imagedestroy($im2);
                }
            }

            $captchas['try'] = $where['try'];
            $captchas['picture'] = $id_big_picture . '_captcha_' . $captchas['try'] . '.jpg';
            imagejpeg($im, $blog_ROOT . '/img/tmp/' . $captchas['picture']);
            imagedestroy($im);

            foreach ($captchas['captcha'] as $key => $captcha) {
                unset($captcha['x']);
                unset($captcha['y']);
                $captchas['captcha'][$key] = $captcha;
            }

            return $captchas;
        }
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
                ($tab['y'] < ($exist['y'] + $exist['longueur']) && ($tab['y'] + $tab['longueur']) > $exist['y'])
            ) {
                return $this->setCoord($im, $exists, $i);
            }
        }
        $exists[] = $tab;

        return $exists;
    }
}
