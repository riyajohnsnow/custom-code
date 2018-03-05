<?php
/*template name: downloadable_files */
//echo "111";
$linked_product = $_GET['link'];
$credeen = $_GET['X-Amz-Credential'];
$xamz = $_GET['X-Amz-Date'];
$expire = $_GET['X-Amz-Expires'];
$host_para = $_GET['X-Amz-SignedHeaders'];
$xsign = $_GET['X-Amz-Signature'];

 $final_linked = parse_url($linked_product);
$fin_url = preg_replace("(^https?://)", "", $linked_product );
$http_url = $final_linked['scheme'];
$final_http_url = str_replace($http_url,"http",$linked_product);

// echo $linked_product."&X-Amz-Credential=".$credeen."&X-Amz-Date=".$xamz."&X-Amz-Expires=".$expire."&X-Amz-SignedHeaders=".$host_para."&X-Amz-Signature=".$xsign;


 header("Location: ".$final_http_url."&X-Amz-Credential=".$credeen."&X-Amz-Date=".$xamz."&X-Amz-Expires=".$expire."&X-Amz-SignedHeaders=".$host_para."&X-Amz-Signature=".$xsign);

?>