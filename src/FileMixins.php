<?php

namespace Blueprint;

class FileMixins
{
    private array $stubs = [];

    public function stub(): \Closure
    {
        return function ($path) {
            if (!isset($this->stubs[$path])) {
                $customPath = CUSTOM_STUBS_PATH . '/' . $path;
                $defaultPath = STUBS_PATH . '/' . $path;
                
                $stubPath = file_exists($customPath)
                          ? $customPath
                          : STUBS_PATH . '/' . $path;
                          
                $this->stubs[$path] = $this->get($stubPath);
            }

            return $this->stubs[$path];
        };
    }
}
