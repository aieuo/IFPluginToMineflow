<?php

namespace aieuo\mfconverter\converter;

use aieuo\mfconverter\Main;

abstract class Converter {

    /* @var Main */
    private $owner;
    /* @var string */
    private $baseDir;

    public function __construct(Main $owner, string $dir) {
        $this->owner = $owner;
        $this->baseDir = $dir;
        if (!file_exists($this->baseDir)) @mkdir($this->baseDir, 0777, true);
    }

    protected function getLogger() {
        return $this->owner->getLogger();
    }

    protected function getBaseDir(): string {
        return $this->baseDir;
    }
}