<?php
spl_autoload_register(
    function ($className){
        $libName = 'Coinsnap';
        
        if(strpos($className, $libName) !== 0) {
            return;
        }

        else {
            $filePath = __DIR__ .'/'. str_replace([$libName, '\\'], ['', DIRECTORY_SEPARATOR], $className).'.php';
                
            
            if(file_exists($filePath)) {
                require_once($filePath);
                return;
            }
        }
    }
);

