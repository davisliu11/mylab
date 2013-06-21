<?php
if ($_SERVER["HTTP_HOST"] != 'remarkablespark.chrometoaster.com') {

	header("Location: http://stats.mydns.net.nz/".$_SERVER["HTTP_HOST"]."/");

}

exit;

?>

