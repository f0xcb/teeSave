<?php

namespace App;

use ArrayObject;

class Collection extends ArrayObject
{

    public function toArray(): array
    {
        return $this->getArrayCopy();
    }

    public function first(): array
    {
        return current($this->getArrayCopy());
    }

    public function latest(): array
    {
        $data = $this->getArrayCopy();
        return end($data);
    }

}