    PHP_ARG_ENABLE(webhost,  
            [Whether to enable the "webhost" extension],  
            [-enable-webhost  Enable "webhost" extension support])  
    if test $PHP_WEBHOST != "no"; then  
            PHP_SUBST(WEBHOST_SHARED_LIBADD)  
            PHP_NEW_EXTENSION(webhost,webhost.c,$ext_shared) // 1st argument declares the module  
                                                             // 2nd tells what all files to compile  
                                                             // $ext_shared is counterpart of PHP_SUBST()  
    fi  
