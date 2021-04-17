<?php

namespace App\ORM;

use App\JWT;
use App\ORM\ORM;

class User extends ORM
{
    protected $id;
    protected $login;
    protected $password;
    protected $type;
    protected $created;
    protected $updated;
    protected $publied;
    protected $roles;
    protected $email;

    public function find(array $properties = array(), $cascade = array())
    {
        $result = array();
        // S'il existe une clause where alors on va chercher les infos de ce user
        if (!empty($properties) || !empty($_POST['where'])) {
            $result = parent::find($properties, $cascade);
        }

        // Sinon on va chercher les infos de l'utilisateur connecté
        if (!empty($this->connectedUser)) {
            $result = parent::find(array('where' => array('id' => $this->connectedUser->userid)), $cascade);
        }

        return $result;
    }
    public function findArray(array $properties = array(), $cascade = array())
    {
        $result = parent::findArray($properties, $cascade);
        if (empty($properties['where']['login'])) {
            if (!empty($result['password'])) {
                $result['password'] = null;
            }

            if (!empty($result['connectedUser'])) {
                $result['connectedUser'] = null;
            }

            if (!empty($result['email'])) {
                $result['email'] = null;
            }
        }

        return $result;
    }

    public function login()
    {
        $user = $this->find(array('where' => array('login' => $this->login)));
        if (isset($user)) {
            if (password_verify($_POST['password'], $user->password)) {
                $issuedAt = time();
                $payload = array(
                    'userid' => $user->id,
                    'iat' => $issuedAt,
                    'exp' => $issuedAt + TOKEN_EXP,
                    'roles' => $user->roles,
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
        $this->login = htmlentities($_POST['login']);
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);

        if (!empty($_POST['email'])) {
            $this->email = $_POST['email'];
        }
        $user = $this->find(array('where' => array('login' => $this->login)));

        if (!empty($user)) {
            return array('inscription-error' => 'Ce login existe déjà.');
        }
        $this->save();

        return $this->login();
    }
}
