<?php    
include "qrlib.php";

QRcode::png(rawurldecode($_GET['url']), false, 'L', 4, 2);
?>