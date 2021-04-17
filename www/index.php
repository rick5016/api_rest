<?php
require '../vendor/autoload.php';

use App\Api;
use App\ORM\ORM;
use App\ORM\User;

//header('Access-Control-Allow-Headers: Authorization');
if ($_SERVER['HTTP_HOST'] == '127.0.0.1:8081') {
    $blog_ROOT = '../../blog';
    header('Access-Control-Allow-Origin: *');
} else {
    $blog_ROOT = '../../www/blog';
    header('Access-Control-Allow-Origin: https://rick5016.net');
}
//header('Access-Control-Allow-Credentials: true');

const DEBUG_PROD = false;

const TOKEN_EXP = 60 * 60; // en seconde

try {
    $api = new Api();

    /**
     * /!\ Les traitements et retours peuvent être surchargé dans les objets d'ORM
     */
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!empty($_POST['find'])) {
            /**
             * string $_POST['find']   (obligatoire) Nom de la table
             * array  $_POST['where']  (optionnel)      Valeurs de la clause where
             * array  $_POST['select'] (optionnel)      Valeurs du select (* par défaut)
             *
             * /!\ Retourne un tableau de tableau : les propriété dont la valeur est à null ne sont pas ramené
             */
            $api->setResult(ORM::factory($_POST['find'], array('connectedUser' => $api->getUser()))->find());
        } else if (!empty($_POST['findAll'])) {
            /**
             * string $_POST['find']   (obligatoire) Nom de la table
             * array  $_POST['where']  (optionnel)      Valeurs de la clause where
             * array  $_POST['select'] (optionnel)      Valeurs du select (* par défaut)
             *
             * /!\ Retourne un tableau de tableau : les propriété dont la valeur est à null ne sont pas ramené
             */
            $api->setResult(ORM::factory($_POST['findAll'], array('connectedUser' => $api->getUser()))->findAll());
        } else if (!empty($_POST['save'])) {
            $values = (!empty($_POST['values'])) ? $_POST['values'] : array();
            /**
             * string $_POST['save']      (obligatoire) Nom de la table
             * array  $_POST['values']     (obligatoire) Valeurs des values pour une insertion, Valeurs des SET pour un update
             * array  $_POST['where']   (optionnel)   Valeurs de la clause where pour l'update
             *
             * /!\ Si $_POST['where'] existe, alors la requête sera un update, sinon un insert
             * /!\ Retourne l'objet sauvegardé sous forme de tableau : les propriété dont la valeur est à null ne sont pas ramené
             */
            $api->setResult(ORM::factory($_POST['save'], $values + array('connectedUser' => $api->getUser()))->save());
        } else if (!empty($_POST['delete'])) {
            /**
             * string $_POST['delete'] (obligatoire) Nom de la table
             * array  $_POST['where'] (obligatoire) Valeurs de la clause where
             *
             * /!\ Ne retourne rien
             */
            $api->setResult(ORM::factory($_POST['delete'], array('connectedUser' => $api->getUser()))->delete());
        } else if (!empty($_POST['login']) && !empty($_POST['password'])) {
            $user = ORM::factory('user', array('login' => $_POST['login'], 'password' => $_POST['password']));
            /** @var User $user */
            if (!empty($_POST['inscription'])) {
                $api->setResult($user->inscription());
            } else {
                $api->setResult($user->login());
            }
        }
    } else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!empty($_GET['find'])) {
            /**
             * string $_POST['find']   (obligatoire) Nom de la table
             * array  $_POST['where']  (optionnel)      Valeurs de la clause where
             * array  $_POST['select'] (optionnel)      Valeurs du select (* par défaut)
             */
            $api->setResult(ORM::factory($_GET['find'], array('user' => $api->getUser()))->find());
        }
    }
    
    /** @var Statistiquereferertmp $log */
    $log = ORM::factory('statistiquereferertmp');
    $log->log();

    $api->response();
} catch (\Exception $e) {
    Api::error(utf8_decode($e->getMessage()));
}
