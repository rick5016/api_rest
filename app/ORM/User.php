<?php

namespace App\ORM;

use App\JWT;
use App\ORM\ORM;

class User extends ORM
{
    private $id;
    private $login;
    private $password;
    private $type;
    private $created;
    private $updated;
    private $publied;

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

    public function getLogin()
    {
        return $this->login;
    }

    public function getPassword()
    {
        return $this->password;
    }

    public function getType()
    {
        return $this->type;
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


    protected function setId($id)
    {
        $this->id = $id;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function setLogin($login)
    {
        $this->login = $login;
    }

    public function setPassword($password)
    {
        $this->password = $password;
    }

    public function setType($type)
    {
        $this->type = $type;
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

    public function login()
    {
        $user = $this->find(array('login' => $this->login));
        if (isset($user)) {
            if (password_verify($_POST['password'], $user->getPassword())) {
                $issuedAt = time();
                $payload = array(
                    'userid' => $user->getId(),
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + TOKEN_EXP
                );
                $jwt = JWT::encode($payload, JWT_KEY, JWT_ALGO);
                return array('token' => $jwt);
            } else {
                throw new \Exception('Mot de passe incorrect');
            }
        } else {
            throw new \Exception('Login introuvable');
        }
    }

    public function save(array $where = array())
    {
        if (!empty($this->user)) {
            return parent::save($where);
        } else {
            throw new \Exception('Aucun utilisateur trouvé');
        }
    }

    public function delete(array $where = array())
    {
        if (!empty($this->user)) {
            return parent::delete($where);
        } else {
            throw new \Exception('Aucun utilisateur trouvé');
        }
    }

    public function inscription()
    {
        // TODO
        /*$query = new Query('user', array('login' => $_POST['login'], 'password' => $_POST['password']));

        if (!$query->save()) {
            throw new \Exception("L'inscription a échoué");
        }*/ }
}
