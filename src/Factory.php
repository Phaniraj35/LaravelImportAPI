<?php

namespace LadyBird\StreamImport;

class Factory
{
    public function __construct()
    {
        $this->namespace = config('import.namespace');
    }

    public function make($source)
    {
        $name = $this->namespace.'\\'.$source;

        if (class_exists($name)) {
            return new $name();
        }
    }
}
