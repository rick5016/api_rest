<?php

namespace App\ORM;

use App\ORM\Page;
use App\ORM\ORM;

class Tag extends ORM
{
    private $id;
    private $tag;
    private $slug;
    private $created;
    public $count;

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

    public function getTag()
    {
        return $this->tag;
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

    public function setTag($tag)
    {
        $this->tag = $tag;
    }

    public function setSlug($slug)
    {
        $this->slug = $slug;
    }

    public function setCreated($created)
    {
        $this->created = $created;
    }

    public function save(array $where = array())
    {
        if (empty($this->connectedUser)) {
            throw new \Exception('Vous devez être connecté pour effectuer cette action.');
        }

        if (empty($this->getTag())) {
            throw new \Exception("Le tag est obligatoire.");
        }

        $this->setSlug($this->string_to_slug($this->getTag()));

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
