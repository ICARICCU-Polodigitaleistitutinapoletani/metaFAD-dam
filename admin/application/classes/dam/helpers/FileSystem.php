<?php

use League\Flysystem\Filesystem;

class dam_helpers_FileSystem extends PinaxObject
{
    private $root;
    private $my_file_system;
    private $localAdapter;

    public function __construct()
    {
        $resourceFolder = __Config::get('UPLOAD_DIR');
        $this->root = $resourceFolder{0}==='/' ? '/' : getcwd();
        $this->localAdapter = __ObjectFactory::createObject('dam.adapters.LocalAdapter', $this->root);
        $this->my_file_system = new Filesystem($this->localAdapter);
        $this->my_file_system->addPlugin(new \League\Flysystem\Plugin\ListWith());
    }

    public function getRoot()
    {
        return $this->root;
    }

    public function getUploadDir()
    {
        $tempUploadsDir = __Config::get('TEMPORARY_UPLOAD_DIR');
        if ($tempUploadsDir{0}!=='/') {
            // se il path della cartella delle risorse è assoluty
            // fileSystem è registrato sulla / quindi bisogna usare il path assoluto della cache
            // altrimenti usare il path relativo
            $resourceFolder = __Config::get('UPLOAD_DIR');
            $cachePath = $resourceFolder{0}==='/' ? __Paths::getRealPath('CACHE') : __Paths::get('CACHE');
            $tempUploadsDir = $cachePath.$tempUploadsDir;
        }

        return $tempUploadsDir;
    }

    public function write($path, $contents)
    {
        umask( 0 );
        $this->my_file_system->write($path, $contents);
    }

    public function update($path, $newContents)
    {
        $this->my_file_system->update($path, $newContents);
    }

    public function has($path)
    {
        return $this->my_file_system->has($path);
    }

    public function read($path)
    {
        return $this->my_file_system->read($this->getOriginalPath($path));
    }

    public function getMimeType($path)
    {
        return $this->my_file_system->getMimetype($this->getOriginalPath($path));
    }

    public function getSize($path)
    {
        return $this->my_file_system->getSize($this->getOriginalPath($path));
    }

    public function delete($path)
    {
        if (is_link($path)) {
            unlink($path);
        } else {
            $this->my_file_system->delete($path);
        }
    }

    public function deleteDir($path)
    {
        $this->my_file_system->deleteDir($path);
    }

    public function listContents($path = null)
    {
        $jsonResult = $this->my_file_system->listWith(array('mimetype', 'size', 'timestamp'), $path);
        for ($i = 0; $i < count($jsonResult); $i++) {
            $date = date('d-m-y', $jsonResult[$i]['timestamp']);
            $jsonResult[$i]['date'] = $date;
        }
        return $jsonResult;
    }

    public function rename($oldNamePath, $newNamePath)
    {
        $folder = @mkdir((pathinfo($newNamePath, PATHINFO_DIRNAME)), 0755, true);

        if (is_link($oldNamePath) && __Config::get('dam.allowedRoot.createSymLink')===true) {
            $target = readlink($oldNamePath);
            symlink($target, $newNamePath);
            unlink($oldNamePath);
        } else if (is_link($oldNamePath)) {
            $this->my_file_system->rename($oldNamePath, $newNamePath);
        } else { //https://ubuntuforums.org/showthread.php?t=1272466
            $this->my_file_system->copy($oldNamePath, $newNamePath);
            $this->my_file_system->delete($oldNamePath);

        }
    }

    public function copy($originPath, $duplicatePath)
    {
        $this->my_file_system->copy($originPath, $duplicatePath);
    }

    public function md5($path)
    {
        // NOTE: non viene utilizzata la classe di astrazione perché non ha il metodo md5
        return md5_file($path);
    }

    /**
     * @param $filePath
     * @return string
     */
    public function moveFileToUploadDir($filePath)
    {
        if (strpos($filePath, __Config::get('dam.allowedRoot')) !== 0) {
            return "";
        }

        //md5 per basename univoco se aggiungo bytestream da path (per evitare ambiguità)
        $baseName = md5($filePath) . pathinfo($filePath, PATHINFO_BASENAME);
        $destPath = $this->getUploadDir() . $baseName;
        @mkdir($this->getUploadDir());
        @unlink($destPath);
        copy($filePath, $destPath);
        return $baseName;
    }

    private function getOriginalPath($path) {
        return is_link($path) ? readlink($path) : $path;
    }
}
