<?php
namespace PicqerSync;

class DataKeeper {

    private $filesystem;

    public function __construct($filesystem) {
        $this->filesystem = $filesystem;
    }

    public function getData() {
        $file = $this->filesystem->read('data/data.json');
        return json_decode($file, true);
    }

    public function saveData($data) {
        $json = json_encode($data);
        $this->filesystem->put('data/data.json', $json);
    }

}