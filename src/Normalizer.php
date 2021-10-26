<?php

namespace App;

class Normalizer
{

    public function username(string $username): string
    {
        return preg_replace('/[^\p{L}\p{N}\s]/u', '', $username);
    }

}