<?php

use League\Flysystem\Adapter\Local as Adapter;

class dam_adapters_LocalAdapter extends Adapter
{
    public function copy($path, $newpath)
    {
        $location = $this->applyPathPrefix($path);
        $destination = $this->applyPathPrefix($newpath);
        $this->ensureDirectory(dirname($destination));
        $location = escapeshellarg($location);
        $destination = escapeshellarg($destination);
        exec("cp $location $destination");
    }
}
