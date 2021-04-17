<?php

namespace App\ORM;

use App\ORM\ORM;

class Commentaire extends ORM
{
    protected $id;
    protected $content;
    protected $created;
    protected $updated;
    protected $publied;
    protected $page;
    protected $user;
    protected $own;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'),
        );
    }

    public function getUser()
    {
        return $this->user;
    }

    public function findAll(array $properties = array(), $cascade = array())
    {
        $properties['order'] = 'created desc';
        if (!in_array('user', $cascade)) {
            $cascade[] = 'user';
        }
        if (!in_array('page', $cascade)) {
            $cascade[] = 'page';
        }
        if (empty($properties['page'])) {
            $properties['page'] = 1;
            if (!empty($_POST['page'])) {
                $properties['page'] = $_POST['page'];
            }
        }
        if (empty($properties['nbResultByPage'])) {
            $properties['nbResultByPage'] = 10;
            if (!empty($_POST['nbResultByPage'])) {
                $properties['nbResultByPage'] = $_POST['nbResultByPage'];
            }
        }
        if (!empty($_POST['where']) && !empty($_POST['where']['page'])) {
            $properties['where'] = array('page' => $_POST['where']['page']);
        }

        return parent::findAll($properties, $cascade);
    }
    public function save(array $properties = array())
    {
        /*$commentairetmp = ORM::factory('commentairetmp', array('connectedUser' => $this->connectedUser))->findAll();
        if (!empty($commentairetmp['list']) && count($commentairetmp['list']) > 100) {
            return array('valid' => 'surcharge');
        }*/
        if (isset($this->validate) && $this->validate === true) {
            unset($this->validate);
            parent::save($properties);

            return array('valid' => true);
        }

        if (empty($_POST['coords'])) {
            throw new \Exception("Le commentaire ne peut être sauvegardié/modifié sans validation de captchas.");
        }

        if (empty($this->content)) {
            throw new \Exception("Le contenu du commentaire est obligatoire.");
        }

        $commentaire = $this;
        if (!empty($_POST['id'])) {
            $commentaire = ORM::factory('commentaire', array('connectedUser' => $this->connectedUser))->find(array('where' => array('id' => (int) $_POST['id'])));
            if (empty($commentaire->id)) {
                throw new \Exception("Le commentaire est introuvable.");
            }
            $properties['where']['id'] = (int) $_POST['id'];
            $commentaire->updated = date('Y-m-d H:i:s');
            $commentaire->content = $this->content;
        } else {
            if (empty($this->page) || is_int($this->page)) {
                throw new \Exception("Le slug de l'article est obligatoire.");
            }

            $page = ORM::factory('page', array('connectedUser' => $this->connectedUser))->findArray(array('where' => array('slug' => $this->page)));
            if (empty($page)) {
                throw new \Exception("L'article est introuvable.");
            }
            $this->page = $page['id'];
        }

        // Validation de la captcha
        /** @var Captcha $captcha */
        $captcha = ORM::factory('captcha');
        $validation = $captcha->validation();

        if ($validation === true) {
            // si l'id existe alors on met a jour le champ updated
            $commentaire->publied = 0;
            $commentaire->validate = true;
            return $commentaire->save($properties);
        }

        return $validation;
    }

    public function delete(array $properties = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($this->id) && empty($_POST['where']['id']) && empty($where['id'])) {
            throw new \Exception('L\'id est obligatoire');
        }

        if (empty($this->id)) {
            $commentaire = parent::find($properties);
            if (empty($commentaire)) {
                throw new \Exception('Commentaire introuvable.');
            }
            $commentaire->delete(array('id' => $this->id));
        } else {
            parent::delete($properties);
        }

        return array();
    }
}
