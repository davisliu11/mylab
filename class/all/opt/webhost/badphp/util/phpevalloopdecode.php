#!/usr/bin/php
<?php
$processUser = @posix_getpwuid(@posix_geteuid());
if ($processUser['name'] != "www-data") {
        $fp = fopen("php://stderr","w");
        fputs($fp,"Error: Script must be run as www-data\n");
        fclose($fp);
        exit;
}

$input = fgets(STDIN);

doloop($input);

$GLOBALS["loopcount"] = 0;

function doloop($input) {
        if (preg_match("/eval\(/", $input)) {
                $evalstr = preg_replace("/eval\(/","echo(",$input);
                //echo "\n\n".$evalstr."\n\n";

                ob_start();
                eval($evalstr);
                $evalout = ob_get_contents();
                ob_end_clean();

                //echo "\n\n".$evalout."\n";
                echo ++$GLOBALS["loopcount"]."\n";

                doloop($evalout);
        }
        else {
                echo "\nOutput:\n\n";
                echo $input;
        }
}
?>
