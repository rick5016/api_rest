<?php

namespace App;

use App\JWT;
use App\ORM\ORM;

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

class Api
{
    private $newToken = array();

    /**
     *  $payload = array(
     *    'userid' => $user->getID(),
     *    'iat' => $issuedAt,
     *    'exp' => $expirationTime
     *   );
     */
    private $user;
    private $result = array();

    public function getUser()
    {
        return $this->user;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function setResult($result)
    {
        $this->result = $this->toArray($result);
    }

    public function toArray($result)
    {
        if ($result instanceof ORM) {
            $result = $result->toArray();
        } else if (!empty($result['list'])) {
            foreach ($result['list'] as $key => $data) {
                if ($data instanceof ORM) {
                    $result['list'][$key] = $data->toArray();
                } else if (is_array($data)) {
                    $result['list'][$key] = $this->toArray($data);
                }
            }
        }

        return $result;
    }

    public function __construct()
    {
        //$token = $this->getBearerToken();
        $token = $_POST['token'] ?? $_GET['token'] ?? null;
        if (!empty($token)) {
            $this->user = JWT::decode($token, JWT_KEY, array(JWT_ALGO));
            if (!empty($this->user)) {
                $issuedAt = time();
                $this->user->iat = $issuedAt;
                $this->user->exp = $issuedAt + TOKEN_EXP;
                $this->newToken = array('token' => JWT::encode($this->user, JWT_KEY, JWT_ALGO));
            }
        }
    }
    public function getAuthorizationHeader()
    {
        $headers = null;
        if (isset($_SERVER['Authorization'])) {
            $headers = trim($_SERVER["Authorization"]);
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) { //Nginx or fast CGI
            $headers = trim($_SERVER["HTTP_AUTHORIZATION"]);
        } elseif (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();
            // Server-side fix for bug in old Android versions (a nice side-effect of this fix means we don't care about capitalization for Authorization)
            $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
            //print_r($requestHeaders);
            if (isset($requestHeaders['Authorization'])) {
                $headers = trim($requestHeaders['Authorization']);
            }
        }
        return $headers;
    }

    /**
     * get access token from header
     * */
    public function getBearerToken()
    {
        $headers = $this->getAuthorizationHeader();
        // HEADER: Get the access token from the header
        if (!empty($headers)) {
            if (preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
                return $matches[1];
            }
        }
        return null;
    }

    public function response($code = 200): void
    {
        header('HTTP/1.1 ' . $code . ' ' . $this->get_status_message($code));
        header('Content-Type: application/json;charset=utf-8');

        echo json_encode(array_merge($this->result, $this->newToken) + array('error' => 0));
        exit;
    }

    public static function error(string $data): void
    {
        $message = 'Une erreur est survenue.';
        $t = $_SERVER['HTTP_HOST'];
        if ($_SERVER['HTTP_HOST'] == '127.0.0.1:8081' || DEBUG_PROD) {
            $message = $data;
        }
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type:application/json');
        echo json_encode(array('error' => utf8_encode($message)));
        exit;
    }

    private function get_status_message(int $code): string
    {
        $status = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
        );

        return ($status[$code]) ? $status[$code] : $status[500];
    }
}
