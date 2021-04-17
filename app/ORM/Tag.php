<?php

namespace App\ORM;

use App\ORM\ORM;

class Tag extends ORM
{
    protected $id;
    protected $tag;
    protected $slug;
    protected $created;
    protected $count;

    public function findAll(array $properties = array(), $cascade = array())
    {
        if (!empty($properties)) {
            return parent::findAll($properties, $cascade);
        }

        //if {isset($_POST['where']['categorie'])}
    }

    public function save(array $where = array())
    {
        if (empty($this->slug)) {
            throw new \Exception("Le slug est obligatoire.");
        }
        $this->slug = $this->string_to_slug($this->slug);

        if (empty($this->tag)) {
            $this->tag = $this->slug;
        }

        return parent::save($where);
    }
}
