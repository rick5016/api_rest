<?php

namespace App\ORM;

use App\ORM\ORM;

class Categorie extends ORM
{
    protected $id;
    protected $categorie;
    protected $slug;
    protected $created;
    protected $tags;

    public function getPrimaryKey()
    {
        return array(
            array('id', 'AI'),
        );
    }

    public function save(array $where = array())
    {
        if (empty($this->slug)) {
            throw new \Exception("La catégorie est obligatoire.");
        }
        $this->slug = $this->string_to_slug($this->slug);

        if (empty($this->categorie)) {
            $this->categorie = $this->slug;
        }

        return parent::save($where);
    }
}
