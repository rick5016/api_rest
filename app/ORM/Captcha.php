<?php

namespace App\ORM;

use App\ORM\ORM;

class Captcha extends ORM
{
    private $id;
    private $x;
    private $y;
    private $created;
    private $commentaire;

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

    public function getX()
    {
        return $this->x;
    }

    public function getY()
    {
        return $this->y;
    }

    public function getCreated()
    {
        return $this->created;
    }

    public function getCommentaire()
    {
        return $this->commentaire;
    }

    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setX($x)
    {
        $this->x = $x;
    }

    public function setY($y)
    {
        $this->y = $y;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function setCommentaire($commentaire)
    {
        $this->commentaire = $commentaire;
    }
}
