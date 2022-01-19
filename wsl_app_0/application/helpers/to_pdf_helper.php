<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Try increasing memory available, mostly for PDF generation
 */
ini_set("memory_limit","128M");

function pdf_create($html, $filename, $page_size="a4", $orientation="portrait", $stream=TRUE, $mail_it=FALSE) 
{
	require_once(BASEPATH1."/application/helpers/dompdf/dompdf_config.inc.php"); 
//  require_once("dompdf/dompdf_config.inc.php");
	    
	$dompdf = new DOMPDF();	 
    $dompdf->set_paper($page_size, $orientation);
	$dompdf->load_html($html); 
	$dompdf->render();
	if ($stream) {
		$dompdf->stream("doc.pdf", array("Attachment" => 0)); //force the browser to open a download dialog, on (1) by default
	}
    //save to email it
    if($mail_it){
 	  write_file($filename, $dompdf->output());
    }
}
?>
