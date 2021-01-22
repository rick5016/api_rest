<?php

namespace App\ORM;

use App\JWT;
use App\ORM\ORM;

class Reminder extends ORM
{
    private $id;
    private $rim_date;
    private $rim_commentaire_1;
    private $rim_commentaire_2;
    private $rim_type;
    private $rim_user;

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

    public function getRim_date()
    {
        return $this->rim_date;
    }

    public function getRim_commentaire_1()
    {
        return $this->rim_commentaire_1;
    }

    public function getRim_commentaire_2()
    {
        return $this->rim_commentaire_2;
    }

    public function getRim_type()
    {
        return $this->rim_type;
    }

    public function getRim_user()
    {
        return $this->rim_user;
    }


    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setRim_date($rim_date)
    {
        $this->rim_date = $rim_date;
    }

    public function setRim_commentaire_1($rim_commentaire_1)
    {
        $this->rim_commentaire_1 = $rim_commentaire_1;
    }

    public function setRim_commentaire_2($rim_commentaire_2)
    {
        $this->rim_commentaire_2 = $rim_commentaire_2;
    }

    public function setRim_type($rim_type)
    {
        $this->rim_type = $rim_type;
    }

    public function setRim_user($rim_user)
    {
        if ($rim_user instanceof \stdClass) {
            if (!empty($rim_user->userid)) {
                $rim_user = $rim_user->userid;
            }
        }
        $this->rim_user = $rim_user;
    }

    public function findAll(array $where = array(), $select = null)
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        
        $this->setRim_user($this->connectedUser);
        $where['rim_user'] = $this->getRim_user();

        return parent::findAll($where, $select);
    }

    public function find(array $where = array(), $select = null)
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        
        $this->setRim_user($this->connectedUser);
        $where['rim_user'] = $this->getRim_user();

        return parent::find($where, $select);
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        
        $this->setRim_user($this->connectedUser);

        return parent::save($where);
    }

    public function delete(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }
        
        $this->setRim_user($this->connectedUser);
        
        return parent::delete($where);
    }
}
