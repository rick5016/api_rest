<?php

namespace App\ORM;

class Utils
{
    protected function string_to_slug(string $str): string
    {
        $str = strtolower(trim($str));
        $str = str_replace(array('à', 'á', 'ä', 'â'), 'a', $str);
        $str = str_replace(array('è', 'é', 'ë', 'ê'), 'e', $str);
        $str = str_replace(array('ì', 'í', 'ï', 'î'), 'i', $str);
        $str = str_replace(array('ò', 'ó', 'ö', 'ô'), 'o', $str);
        $str = str_replace(array('ù', 'ú', 'ü', 'û'), 'u', $str);
        $str = str_replace('ç', 'c', $str);

        $str = str_replace(
            array(' ', '·', '/', '_', ',', ':', ';', "'"),
            '-',
            $str
        );
        $str = preg_replace('/[^0-9a-z-]/', '', $str);

        return $str;
    }

    protected function addUser(ORM $result)
    {
        if (!empty($result->getUser())) {
            $result->own = false;
            if (!empty($this->connectedUser) && $this->connectedUser->userid == $result->getUser()->getId()) {
                $result->own = true;
            }
        }
    }
}
