<?
class proceso_model extends CI_Model
{	var $tipo_ambiente= 1;	var $tipo_emision= 1;		var $ruta_archivo_xml= "";		var $ruta_archivo_pdf= "";		var $CompElectronico= "";
	var $estado= "";		var $autorizacion= "";		var $fechaAutoriz= "";			var $ambiente= "";				var $consulta_autoriz= false;
	var $contenido_xml;		var $contenido_pdf;			var $suma_imp= array();			var $det_adic_pdf= array();		var $count_det_adic_pdf= 3;
	var $instancia= 2;		var $reenvio_correo= false;
	
    function __construct(){
    	parent::__construct();
    }

    function ValidaUso(){		return TRUE;
    }
      
    function GetNumeroAleatorio($num_digitos){
												$codigo_numerico="";
        for($i=0; $i<$num_digitos; $i++):		$codigo_numerico.= rand(0,9);            
        endfor;									return $codigo_numerico;
	}
	
    function ObtieneClaveAcceso($fEmision, $codComprobante, $rucEmp, $tipoAmbiente, $prefijo1, $prefijo2, $numFiscal, $tipoEmision){
		$numeroAleatorioOchoDigitos= $this->GetNumeroAleatorio(8);
		$claveAcceso = $fEmision.$codComprobante.$rucEmp.$tipoAmbiente.$prefijo1.$prefijo2.$numFiscal.$numeroAleatorioOchoDigitos.$tipoEmision;
		return $claveAcceso.= $this->GetDigitoVerificador($claveAcceso);
	}
	
	function GetDigitoVerificador($claveAcceso)
    {														$valorTotal=0;	$valorPonderado=2;
        for($i=strlen($claveAcceso)-1; $i>=0; $i--):		$valorTotal+= ((int)(substr($claveAcceso,$i,1))*$valorPonderado);	
																						$valorPonderado++;	
															if($valorPonderado==8)		$valorPonderado=2;
        endfor;
														$num= 11;	$valorTotal = $num-($valorTotal%$num);
        if($valorTotal==10 or $valorTotal==11):			$digitoVerificador= $num-$valorTotal;
        else:											$digitoVerificador=$valorTotal;
        endif;											return $digitoVerificador;
    }
	
	function ManejoXml($nombre_archivo, $arr_datos, $tag)
	{	
		$datos_emp= $this->querys_model->GetDatosEmpresa();				$this->ambiente= "PRUEBAS";
		if($this->tipo_ambiente==2)										$this->ambiente= "PRODUCCION";
		
		$infoCorreo= $arr_datos["infoCorreo"];		
		$this->ruta_archivo_xml= $this->ruta_app.$this->arr_parametros["directorio_local_xml"]."/$nombre_archivo";
		$this->ruta_archivo_xml= str_replace(array('$ambiente', '$ruc_empresa'), array($this->ambiente, $datos_emp["ruc"]), $this->ruta_archivo_xml);
													$arr_carpeta= explode("/", $this->ruta_archivo_xml);	$carpeta= "";			
		for($i=0; $i<count($arr_carpeta)-1; $i++):	$carpeta.= $arr_carpeta[$i]."/";	mkdir($carpeta,0777);
		endfor;
	
		if($this->reenvio_correo)		return;
		require($this->ruta_app."system/libraries/xmlseclibs.php");		unset($arr_datos["infoCorreo"]);					
		$xml= Array2XML::createXML($tag, $arr_datos);					$xml_string= $xml->saveXML();
		$xml_string= str_replace(array("<$tag>", 'encoding="UTF-8"'), array("<$tag id=\"comprobante\" version=\"1.0.0\">", 'encoding="ISO-8859-1"'), $xml_string);
		if(!write_file($this->ruta_archivo_xml, $xml_string)):			$this->log_errores("XML", "NO SE PUDO GENERAR EL XML");	return false;
		endif; 													
		
		$app_java = $this->ruta_app."resources/firma_electronica.jar";
		$ruta_de_la_firma = $this->ruta_app."resources/".$datos_emp["ruc"].".p12";
		$clave_de_la_firma = preg_replace( "/\r|\n/", "", trim(file_get_contents($this->ruta_app."resources/".$datos_emp["ruc"].".txt")));
		$ruta_carpeta_xml = str_replace($nombre_archivo, "", $this->ruta_archivo_xml);
		shell_exec("/usr/java/jdk1.7.0_71/bin/java -jar ".$app_java." ".$ruta_de_la_firma." ".$clave_de_la_firma." ".$this->ruta_archivo_xml." ".$ruta_carpeta_xml);
		
		$this->contenido_xml= file_get_contents($this->ruta_archivo_xml);	$this->estado= "NI";
		$this->proceso_model->actualiza_comprobante_declarado($infoCorreo);
	}
	
	function WSSRI($claveAcceso)
    {	
		if($this->reenvio_correo)		return true;
		if(!$this->consulta_autoriz):
		///////////////////////////////////////////  PARA CONOCER LA VALIDEZ DE LA ESTRUCTURA XML Y SUS VALIDACIONES /////////////////////////////////////
										$ws_recepcion_comprobante= $this->arr_parametros["ws_recepcion_comprobante_pruebas"];
		if($this->tipo_ambiente==2):	$ws_recepcion_comprobante= $this->arr_parametros["ws_recepcion_comprobante_produccion"];
		endif;							$oSoapClient = new nusoap_client($ws_recepcion_comprobante, true);				$oSoapClient->soap_defencoding = 'utf-8';
        
        $contenido_xml= base64_encode(file_get_contents($this->ruta_archivo_xml));
        $respuesta = $oSoapClient->call("validarComprobante", array("xml" => $contenido_xml));							$sError = $oSoapClient->getError();
        if($oSoapClient->fault):	$this->log_errores("OPERACION", $sError);	return false;
        else:	if($sError):		$this->log_errores("CONEXION", $sError);	return false;
				endif;
        endif;										echo "<pre>".print_r($respuesta)."</pre>";        
													$resp_sri = $respuesta["RespuestaRecepcionComprobante"];			
        if($resp_sri["estado"]!="RECIBIDA"):		$resp_sri = $resp_sri["comprobantes"]["comprobante"];	
													if(isset($resp_sri["mensajes"]["mensaje"][0])):		$this->log_errores("RECEPCIÓN XML", $resp_sri["mensajes"]["mensaje"][0]["mensaje"]."\n\n".$resp_sri["mensajes"]["mensaje"][0]["informacionAdicional"]);
													else:												$this->log_errores("RECEPCIÓN XML", $resp_sri["mensajes"]["mensaje"]["mensaje"]);
													endif;	return false;
        endif;
        endif;
        //if($this->instancia==1):		return $this->xml_final();
        //endif;
        
        ///////////////////////////////////////////  PARA OBTENER LA AUTORIZACION SRI /////////////////////////////////////
										$ws_autorizacion_comprobante= $this->arr_parametros["ws_autorizacion_comprobante_pruebas"];
		if($this->tipo_ambiente==2):	$ws_autorizacion_comprobante= $this->arr_parametros["ws_autorizacion_comprobante_produccion"];
		endif;							$oSoapClient = new nusoap_client($ws_autorizacion_comprobante, true);			$oSoapClient->soap_defencoding = 'utf-8';
        
        $respuesta = $oSoapClient->call("autorizacionComprobante", array("claveAccesoComprobante" => $claveAcceso));	$sError = $oSoapClient->getError();
        if($oSoapClient->fault):	$this->log_errores("OPERACION", $sError);	return false;
        else:	if($sError):		$this->log_errores("CONEXION", $sError);	return false;
				endif;
        endif;							echo "<pre>".print_r($respuesta)."</pre>";				$resp_sri= $respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"];
		if(isset($resp_sri["estado"])):															$autorizado= $this->valida_autorizacion($resp_sri);	
		elseif(isset($resp_sri[0]["estado"])):	for($i=0; $i<count($resp_sri);	$i++){			$autorizado= $this->valida_autorizacion($resp_sri[$i]);		if($autorizado){	$resp_sri= $resp_sri[$i];	break;	}		}
		else:																					$autorizado= true;	//contingecia
		endif;
		if(!$autorizado):	return false;
		else:				if(isset($respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"])):
									unset($respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"]);
									$respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"]= $resp_sri;
							endif;	return $this->xml_final($respuesta);
		endif;				
    }
	
	function valida_autorizacion($resp_sri)
    {		
			if($resp_sri["estado"]!="AUTORIZADO"):
					if(isset($resp_sri["mensajes"]["mensaje"][0])):		$this->log_errores("AUTORIZACIÓN XML", $resp_sri["mensajes"]["mensaje"][0]["mensaje"]."\n\n".$resp_sri["mensajes"]["mensaje"][0]["informacionAdicional"]);
					else:												$this->log_errores("AUTORIZACIÓN XML", $resp_sri["mensajes"]["mensaje"]["mensaje"]);
					endif;												return false;
			endif;														return true;
    }
	
	function xml_final($respuesta)
    {																									$cabecera_respuesta= $respuesta["RespuestaAutorizacionComprobante"];		
		if(isset($respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"])):	$detalle_respuesta= $respuesta["RespuestaAutorizacionComprobante"]["autorizaciones"]["autorizacion"];
		$contenido_xml= "<RespuestaAutorizacionComprobante>
	<claveAccesoConsultada>".$cabecera_respuesta["claveAccesoConsultada"]."</claveAccesoConsultada>
	<numeroComprobantes>".$cabecera_respuesta["numeroComprobantes"]."</numeroComprobantes>
	<autorizaciones>
		<autorizacion>
			<estado>".$detalle_respuesta["estado"]."</estado>
			<numeroAutorizacion>".$detalle_respuesta["numeroAutorizacion"]."</numeroAutorizacion>
			<fechaAutorizacion>".$detalle_respuesta["fechaAutorizacion"]."</fechaAutorizacion>
			<ambiente>".$this->ambiente."</ambiente>
			<comprobante><![CDATA[".file_get_contents($this->ruta_archivo_xml)."]]>
			</comprobante>
		</autorizacion>
	</autorizaciones>
</RespuestaAutorizacionComprobante>";
		else:		$contenido_xml= file_get_contents($this->ruta_archivo_xml);
		endif;		$this->contenido_xml= $contenido_xml;			

		$this->estado= $detalle_respuesta["estado"];					$this->autorizacion= $detalle_respuesta["numeroAutorizacion"];	
		$this->fechaAutoriz= $detalle_respuesta["fechaAutorizacion"];								
		if(!write_file($this->ruta_archivo_xml, $contenido_xml)):		$this->log_errores("XML", "NO SE PUDO GENERAR EL XML AUTORIZADO");	return false;
		endif;																																return true;
    }
    
    function generar_pdf($nombre_archivo, $arreglo)
	{	
		$datos_emp= $this->querys_model->GetDatosEmpresa();				
		$this->ruta_archivo_pdf= $this->ruta_app.$this->arr_parametros["directorio_local_pdf"]."/$nombre_archivo";
		$this->ruta_archivo_pdf= str_replace(array('$ambiente', '$ruc_empresa'), array($this->ambiente, $datos_emp["ruc"]), $this->ruta_archivo_pdf);
													$arr_carpeta= explode("/", $this->ruta_archivo_pdf);	$carpeta= "";			
		for($i=0; $i<count($arr_carpeta)-1; $i++):	$carpeta.= $arr_carpeta[$i]."/";	mkdir($carpeta,0777);
		endfor;
		
		if($this->reenvio_correo)		return;
		
		$html = "	<style>					
					td {
						font-family:	Arial,Helvetica,sans-serif;
						font-size:		9px;
						vertical-align:	middle;
					}
					p {
						padding-left:	20px;
						margin: 4px 0 4px 0;
					}
					</style>
					<table style='width: 100%' align='center'>
							<col style='width: 50%'>
							<col style='width: 50%'>
							<tr><td valign='bottom'><p><img src='".$this->ruta_app."resources/".$arreglo["infoTributaria"]["ruc"].".png' style='height: 80px;'></p></td>
								<td rowspan='2' style='border: 1px; border-radius: 6px'>
									<p><b>RUC: ".$arreglo["infoTributaria"]["ruc"]."</b></p>
									<p><b>".$this->n_tipo_doc." No. ".$arreglo["infoTributaria"]["estab"]."-".$arreglo["infoTributaria"]["ptoEmi"]."-".$arreglo["infoTributaria"]["secuencial"]."</b></p>
									<p>NÚMERO DE AUTORIZACIÓN:</p>
									<p>".$this->autorizacion."</p>
									<p>FECHA Y HORA DE AUTORIZACIÓN: ".$this->fechaAutoriz."</p>
									<p>AMBIENTE: ".$this->ambiente."</p>
									<p>EMISIÓN: NORMAL</p>
									<p>CLAVE DE ACCESO</p>
									<p><barcode type='CODABAR' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<!--p><barcode type='C39' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C39+' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C39E' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C39E+' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C93' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='S25' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='S25+' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='I25' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='I25+' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C128A' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='C128B' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='EAN2' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='EAN5' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='MSI' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='MSI+' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='POSTNET' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='PLANET' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='RMS4CC' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='KIX' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='IMB' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='CODABAR' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='CODE11' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='PHARMA' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p>
									<p><barcode type='PHARMA2T' value='".$arreglo["infoTributaria"]["claveAcceso"]."' style='width:90mm; height:10mm'></barcode></p-->";
		//$html .= '<tcpdf method="write1DBarcode" params=":::::" />';
		$html .= "
								</td>
							</tr>
							<tr><td>
									<p><b>".$arreglo["infoTributaria"]["razonSocial"]."</b></p>
									<p>Dir Matriz: ".$arreglo["infoTributaria"]["dirMatriz"]."</p>
									<p>Dir Sucursal: ".$arreglo["infoCorreo"]["dirEstablecimiento"]."</p>";
		if($arreglo["infoCorreo"]["contribuyenteEspecial"]!="")			$html .= "					<p>Contribuyente Especial No: ".$arreglo["infoCorreo"]["contribuyenteEspecial"]."</p>";
		$html .= "					<p>OBLIGADO A LLEVAR CONTABILIDAD: ".$arreglo["infoCorreo"]["obligadoContabilidad"]."</p>
								</td>
							</tr>
							<tr><td height='5'></td></tr>
							<tr><td colspan='2' style='border: 1px; border-radius: 6px'>
								<table style='width: 100%'>
									<col style='width: 25%'>
									<col style='width: 75%'>";
		if($this->tipo_doc=='06'):					
		$html .= "					<tr><td colspan='2'><p><b>INFORMACIÓN DEL TRANSPORTISTA </b></p></td></tr>
									<tr><td><p>Razón Social / Nombres y Apellidos:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["razonSocialTransportista"]."</p></td>
									</tr>
									<tr><td><p>Identificación:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["rucTransportista"]."</p></td>
									</tr>
									<tr><td><p>Placa:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["placa"]."</p></td>
									</tr>
									<tr><td><p>Punto de Partida:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["dirPartida"]."</p></td>
									</tr>
									<tr><td><p>Fecha Inicio Transporte:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["fechaIniTransporte"]."</p></td>
									</tr>
									<tr><td><p>Fecha Fin Transporte:</p></td>
										<td><p>".$arreglo["infoGuiaRemision"]["fechaFinTransporte"]."</p></td>
									</tr>
									<tr><td height='5'></td></tr>";
		endif;							
		$html .= "					<tr><td><p><b>INFORMACIÓN DEL ".strtoupper($arreglo["infoCorreo"]["tipo_tercero"])." </b></p></td></tr>";
					if($this->tipo_doc=='06'):	
						if(isset($arreglo["destinatarios"]["destinatario"]["codDocSustento"])):	
						$datos_cmp= $this->querys_model->GetDatosTipoComprobante($cons_where_cmp= "AND codigo='".$arreglo["destinatarios"]["destinatario"]["codDocSustento"]."'");
						$html.=	"	
									<tr><td><p>Comprobante de Venta:</p></td>
										<td><p>".$datos_cmp["descripcion"]." ".$arreglo["destinatarios"]["destinatario"]["numDocSustento"]."</p></td>
									</tr>
									<tr><td><p>Fecha Emisión:</p></td>
										<td><p>".$arreglo["destinatarios"]["destinatario"]["fechaEmisionDocSustento"]."</p></td>
									</tr>
									<tr><td><p>Autorización:</p></td>
										<td><p>".$arreglo["destinatarios"]["destinatario"]["numAutDocSustento"]."</p></td>
									</tr>";
						endif;
						$html.=	"	<tr><td><p>Motivo de Traslado:</p></td>
										<td><p>".$arreglo["destinatarios"]["destinatario"]["motivoTraslado"]."</p></td>
									</tr>
									<tr><td><p>Destino / Punto de llegada:</p></td>
										<td><p>".$arreglo["destinatarios"]["destinatario"]["dirDestinatario"]."</p></td>
									</tr>";
					endif;
						$html.=	"	<tr><td><p>Razón Social / Nombres y Apellidos:</p></td>
										<td><p>".$arreglo["infoCorreo"]["nombre"]."</p></td>
									</tr>
									<tr><td><p>Identificación:</p></td>
										<td><p>".$arreglo["infoCorreo"]["ruc"]."</p></td>
									</tr>";
					if($this->tipo_doc!='06'):
							$html.=	"<tr><td><p>Fecha Emisión:</p></td>
										<td><p>".$arreglo["infoCorreo"]["fechaEmision"]."</p></td>
									</tr>";
					endif;
					if($this->tipo_doc=='01' and $arreglo["infoCorreo"]["guiaRemision"]!=""):
							$html.=	"<tr><td><p>Guía de Remisión:</p></td>
										<td><p>".$arreglo["infoCorreo"]["guiaRemision"]."</p></td>
									</tr>";
					endif;
					if($this->tipo_doc=='04'):		$datos_cmp= $this->querys_model->GetDatosTipoComprobante($cons_where_cmp= "AND codigo='".$arreglo["infoNotaCredito"]["codDocModificado"]."'");
						$html.=	"	
									<tr><td height='5'></td></tr>
									<tr><td><p><b>DATOS DE MODIFICACIÓN</b></p></td></tr>
									<tr><td><p>Comprobante que modifica:</p></td>
										<td><p>".$datos_cmp["descripcion"]." ".$arreglo["infoNotaCredito"]["numDocModificado"]."</p></td>
									</tr>
									<tr><td><p>Fecha Emisión:</p></td>
										<td><p>".$arreglo["infoNotaCredito"]["fechaEmisionDocSustento"]."</p></td>
									</tr>
									<tr><td><p>Motivo de modificación:</p></td>
										<td><p>".$arreglo["infoNotaCredito"]["motivo"]."</p></td>
									</tr>";
					endif;
					$html.=	"	</table>
								</td>
							</tr>
							
							<tr><td height='5'></td></tr>
							</table>
							
								<table style='width: 100%' border='0.5px' cellpadding='0' cellspacing='0'>";
	
					if($this->tipo_doc=='01' or $this->tipo_doc=='04' or $this->tipo_doc=='06'):
							if($this->tipo_doc=='06'):
								$html.= "	<col style='width: 10%'>
											<col style='width: 65%'>
											<col style='width: 15%'>
											<col style='width: 10%'>
											<tr><td align='center' height='40'><b>Cantidad</b></td>
												<td align='center'><b>Descripción</b></td>
												<td align='center'><b>Cod Principal</b></td>
												<td align='center'><b>Cod Auxiliar</b></td>
											</tr>";					$detalle= $arreglo["destinatarios"]["destinatario"]["detalles"]["detalle"];	
							else:
								$html.= "	<col style='width: 12%'>
											<col style='width: 9%'>
											<col style='width: 9%'>
											<col style='width: 40%'>
											<col style='width: 10%'>
											<col style='width: 10%'>
											<col style='width: 10%'>
											<tr><td align='center' height='40'><b>Cod Principal</b></td>
												<td align='center'><b>Cod Auxiliar</b></td>
												<td align='center'><b>Cantidad</b></td>
												<td align='center'><b>Descripción</b></td>
												<td align='center'><b>Precio Unitario</b></td>
												<td align='center'><b>Descuento</b></td>
												<td align='center'><b>Precio Total</b></td>
											</tr>";					$detalle= $arreglo["detalles"]["detalle"];	
							endif;
										
							for($i=0; $i<count($detalle); $i++):	$html.= "	<tr>";
								if($this->tipo_doc=='01'):			$html.= "	<td align='center' height='20'>".$detalle[$i]["codigoPrincipal"]."</td>
																				<td align='center'>".$detalle[$i]["codigoAuxiliar"]."</td>";
								elseif($this->tipo_doc=='04'):		$html.= "	<td align='center' height='20'>".$detalle[$i]["codigoInterno"]."</td>
																				<td align='center'>".$detalle[$i]["codigoAdicional"]."</td>";
								elseif($this->tipo_doc=='06'):		$html.= "	<td align='center' height='20'>".$detalle[$i]["cantidad"]."</td>
																				<td align='center'>".$detalle[$i]["descripcion"]."</td>
																				<td align='center'>".$detalle[$i]["codigoInterno"]."</td>
																				<td align='center'>".$detalle[$i]["codigoAdicional"]."</td>
																				</tr>";	continue;
								endif;
								$html.= "		<td align='center'>".$detalle[$i]["cantidad"]."</td>
												<td align='left'>".$detalle[$i]["descripcion"]."</td>
												<td align='right'>".$detalle[$i]["precioUnitario"]."</td>
												<td align='right'>".$detalle[$i]["descuento"]."</td>";		
								$html.= "		<td align='right'>".$detalle[$i]["precioTotalSinImpuesto"]."</td>";
								$html.=	"		</tr>";
							endfor;
					elseif($this->tipo_doc=='07'):
							$html.= "		<col style='width: 15%'>
											<col style='width: 15%'>
											<col style='width: 15%'>
											<col style='width: 10%'>
											<col style='width: 15%'>
											<col style='width: 10%'>
											<col style='width: 10%'>
											<col style='width: 10%'>
											<tr><td align='center' height='40'><b>Comprobante</b></td>
												<td align='center'><b>Número</b></td>
												<td align='center'><b>Fecha Emisión</b></td>
												<td align='center'><b>Ejercicio Fiscal</b></td>
												<td align='center'><b>Base Imponible <br>para la Retención</b></td>
												<td align='center'><b>Impuesto</b></td>
												<td align='center'><b>Porcentaje</b></td>
												<td align='center'><b>Valor Retenido</b></td>
											</tr>";					$detalle= $arreglo["impuestos"]["impuesto"];				
							for($i=0; $i<count($detalle); $i++):	$datos_imp= $this->querys_model->GetDatosImpuesto($cons_where_imp= "AND ref_impuesto='".$detalle[$i]["codigo"]."' AND id_porcentaje='".$detalle[$i]["codigoRetencion"]."'");
																	$datos_cmp= $this->querys_model->GetDatosTipoComprobante($cons_where_cmp= "AND codigo='".$detalle[$i]["codDocSustento"]."'");
								if($datos_imp["n_impuesto"]=="RET. FTE."):			$datos_imp["n_impuesto"]= "RENTA";
								elseif($datos_imp["n_impuesto"]=="RET. IVA"):		$datos_imp["n_impuesto"]= "IVA";
								endif;
																	
								$html.= "	<tr><td align='center' height='20'>".$datos_cmp["descripcion"]."</td>
												<td align='center'>".$detalle[$i]["numDocSustento"]."</td>
												<td align='center'>".$detalle[$i]["fechaEmisionDocSustento"]."</td>
												<td align='center'>".substr($detalle[$i]["fechaEmisionDocSustento"],3,10)."</td>
												<td align='right'>".$detalle[$i]["baseImponible"]."</td>
												<td align='center'>".$datos_imp["n_impuesto"]."</td>
												<td align='right'>".$detalle[$i]["porcentajeRetener"]."</td>
												<td align='right'>".$detalle[$i]["valorRetenido"]."</td>
											</tr>";
							endfor;
					endif;
									
					$html.= "	</table>
							
					<table style='width: 100%' align='center'>
							<col style='width: 68%'>
							<col style='width: 2%'>
							<col style='width: 30%'>
							<tr><td height='5' colspan='3'></td></tr>
							<tr><td style='vertical-align:top; border: 1px; border-radius: 6px'>
								<table style='width: 100%'>
									<col style='width: 30%'>
									<col style='width: 70%'>
									<tr><td colspan='2'><p><b>INFORMACIÓN ADICIONAL</b></p></td></tr>";		$informacionAdicional= $arreglo["infoAdicional"]["campoAdicional"];
					for($i=0; $i<count($informacionAdicional); $i++):
					$html.= "		<tr><td><p>".$informacionAdicional[$i]["@attributes"]["nombre"].":</p></td>
										<td><p>".$informacionAdicional[$i]["@value"]."</p></td>
									</tr>";
					endfor;
					$html.= "	</table>
								</td>
								<td></td>
								<td style='vertical-align:top'>
								<table style='width: 100%' border='0.5px' cellpadding='0' cellspacing='0'>
									<col style='width: 65%'>
									<col style='width: 35%'>";
					
					if($this->tipo_doc=='01' or $this->tipo_doc=='04'):
					$html.= "		<tr><td>SUBTOTAL 12%</td>
										<td align='right'>".number_format($this->suma_imp["base_imp"]["2"]["2"], 2, '.',',')."</td>
									</tr>
									<tr><td>SUBTOTAL 0%</td>
										<td align='right'>".number_format($this->suma_imp["base_imp"]["2"]["0"], 2, '.',',')."</td>
									</tr>
									<tr><td>SUBTOTAL NO OBJETO DE IVA</td>
										<td align='right'>".number_format($this->suma_imp["base_imp"]["2"]["6"], 2, '.',',')."</td>
									</tr>
									<tr><td>SUBTOTAL EXENTO DE IVA</td>
										<td align='right'>".number_format($this->suma_imp["base_imp"]["2"]["7"], 2, '.',',')."</td>
									</tr>
									<tr><td>SUBTOTAL SIN IMPUESTOS</td>
										<td align='right'>".number_format(($arreglo["infoCorreo"]["totalSinImpuestos"]+$arreglo["infoCorreo"]["totalDescuento"]-$arreglo["infoCorreo"]["totalDescuentoAdicional"]), 2, '.',',')."</td>
									</tr>
									<tr><td>TOTAL DESCUENTOS</td>
										<td align='right'>".number_format($arreglo["infoCorreo"]["totalDescuento"], 2, '.',',')."</td>
									</tr>
									<tr><td>ICE</td>
										<td align='right'>".number_format($this->suma_imp["valor"]["3"]["0"], 2, '.',',')."</td>
									</tr>
									<tr><td>IVA 12%</td>
										<td align='right'>".number_format($this->suma_imp["valor"]["2"]["2"], 2, '.',',')."</td>
									</tr>
									<tr><td>IRBPNR</td>
										<td align='right'>".number_format($this->suma_imp["valor"]["5"]["0"], 2, '.',',')."</td>
									</tr>";
							if($this->tipo_doc=='01'):
							$html.= "<tr><td>PROPINA</td>
										<td align='right'>".number_format($arreglo["infoCorreo"]["propina"], 2, '.',',')."</td>
									</tr>";
							endif;
					endif;
					if($this->tipo_doc!='06'):
					$html.= "		<tr><td>VALOR TOTAL</td>
										<td align='right'>".number_format($arreglo["infoCorreo"]["importeTotal"], 2, '.',',')."</td>
									</tr>";
					endif;
					$html.= "	</table>
								</td>
							</tr>
					</table>";
															
		require_once($this->ruta_app."resources/html2pdf/html2pdf.class.php");
		try
		{
			$pdf = new HTML2PDF();
			//$params = $pdf->serializeTCPDFtagParameters(array($arreglo["infoTributaria"]["claveAcceso"], 'C39', '', '', 80, 30, 0.4, array('position'=>'S', 'border'=>true, 'padding'=>4, 'fgcolor'=>array(0,0,0), 'bgcolor'=>array(255,255,255), 'text'=>true, 'font'=>'helvetica', 'fontsize'=>8, 'stretchtext'=>4), 'N'));
			//$html = str_replace(":::::", $params, $html);
			$pdf->writeHTML($html);
			$pdf->Output($this->ruta_archivo_pdf, 'F');	
			
			list($tipo_doc, $prefijo1, $prefijo2, $secuencia)= explode("_", $this->CompElectronico);
			$this->db->where("tipo_doc", $tipo_doc);
			$this->db->where("prefijo1", $prefijo1);
			$this->db->where("prefijo2", $prefijo2);
			$this->db->where("secuencia", $secuencia);
			$this->db->update("xml_comprobante", array("contenido_pdf"	=> $html));
			
			if($this->autorizacion!=""):
					$consulta = $this->portal->query("SELECT * FROM ef_empresa WHERE ruc = '".$arreglo["infoTributaria"]["ruc"]."'");
					$consulta = $consulta->row_array();
					
					$this->portal->where("id_empresa", $consulta["id_empresa"]);
					$this->portal->where("tipo_doc", $tipo_doc);
					$this->portal->where("numero", "$prefijo1-$prefijo2-$secuencia");
					$this->portal->update("ef_comprobantes", array("html"	=> $html));
			endif;
		}
		catch(HTML2PDF_exception $e) {
			echo $e;
			exit;
		}
    }
    
    function copia_archivos_ftp()
    {	/*
		echo "<pre>".$this->arr_parametros["ftp_server"]."</pre>";
		echo "<pre>".$this->arr_parametros["ftp_port"]."</pre>";
		echo "<pre>".$this->arr_parametros["ftp_user"]."</pre>";
		echo "<pre>".$this->arr_parametros["ftp_password"]."</pre>";
		echo "<pre>".$this->ruta_app.$this->arr_parametros["ftp_origen"].$archivo."</pre>";
		echo "<pre>".$this->arr_parametros["ftp_destino"].$archivo."</pre>";
		
		///////////////////////////////   copia via ftp //////////////////////////////////
		$conn_id = ftp_connect($this->arr_parametros["ftp_server"], $this->arr_parametros["ftp_port"]);	
		$login_result = ftp_login($conn_id, $this->arr_parametros["ftp_user"], $this->arr_parametros["ftp_password"]);	
		print_r($conn_id);	print_r($login_result);
		if (!$conn_id or !$login_result):		$this->log_errores("FTP", "NO SE PUDO CONECTAR AL SERVIDOR DE DESTINO");	return false;
		endif;
												$upload = ftp_put($conn_id, $this->arr_parametros["ftp_destino"].$archivo, $this->ruta_app.$this->arr_parametros["ftp_origen"].$archivo, FTP_BINARY);		 
		if(!$upload):							$this->log_errores("FTP", "NO SE PUDO COPIAR AL SERVIDOR DE DESTINO");		return false;
		endif;									ftp_close($conn_id);	return true;
		
		///////////////////////////////   copia via ssh //////////////////////////////////
		$conn_id = ssh2_connect($this->arr_parametros["ftp_server"], $this->arr_parametros["ftp_port"]);
		$login_result = ssh2_auth_password($conn_id, $this->arr_parametros["ftp_user"], $this->arr_parametros["ftp_password"]);
		if (!$conn_id or !$login_result):		$this->log_errores("SSH", "NO SE PUDO CONECTAR AL SERVIDOR DE DESTINO");	return false;
		endif;
												$upload = ssh2_scp_send($conn_id, $this->ruta_app.$this->arr_parametros["ftp_origen"].$archivo, $this->arr_parametros["ftp_destino"].$archivo, 0644);
		if(!$upload):							$this->log_errores("SSH", "NO SE PUDO COPIAR AL SERVIDOR DE DESTINO");		return false;
		endif;									ssh2_close($conn_id);	return true;*/
		
		//rename($this->ruta_app.$this->arr_parametros["ftp_origen"].$archivo, $this->arr_parametros["ftp_destino"].$archivo);	
		return true;
	}
    
    function envio_correo($infoCorreo)
    {
		$datos_emp= $this->querys_model->GetDatosEmpresa();		
        require_once($this->ruta_app."resources/phpmailer/_lib/class.phpmailer.php");
        
		$mail = new PHPMailer();												$mail->IsSMTP();
		/*$mail->SMTPDebug= 1;*/												$mail->Host= $this->arr_parametros["smtp_host"];
		$mail->Port= $this->arr_parametros["smtp_port"];						$mail->SMTPAuth= false;
		if($this->arr_parametros["smtp_auth"]=="1"):							$mail->SMTPAuth= true;
		endif;
		
		$mail->Username= $this->arr_parametros["smtp_username"];				$mail->Password= $this->arr_parametros["smtp_password"];
        $mail->Timeout= $this->arr_parametros["smtp_timeout"];								$remitente= $this->arr_parametros["smtp_email_from"];
		if($this->valida_mail($this->arr_parametros["smtp_email_from_".$this->tipo_doc])):	$remitente= $this->arr_parametros["smtp_email_from_".$this->tipo_doc];
		endif;
											$subject = "Comprobante Electrónico - ".$datos_emp["n_empresa"];
		if($this->consulta_autoriz):		$subject = "REENVÍO de ".$subject;
			if($this->autorizacion=="" and !$this->reenvio_correo):		return;
			endif;
		endif;
	 
		$mail->From	= $remitente;
        $mail->FromName = $datos_emp["n_empresa"];
        $mail->Subject = $subject;
		
		
		$html= "	<div marginheight='0' marginwidth='0' bgcolor='#FFFFFF'>
			<table cellspacing='0' cellpadding='0' border='0' align='center' width='600'>
			<tbody>
				<!--tr><td><img height='120' src='".base_url("resources/".$infoCorreo["ruc"].".png")."'></td>
				</tr-->
				<tr><td><p style='font-size:12px;padding-left:28'>Estimado(a) ".$infoCorreo["tipo_tercero"]." <b>".$infoCorreo["nombre"]."</b>,</p>
						<p style='font-size:12px;padding-left:28'>Adjunto encontrará su comprobante electrónico <b>(archivo .XML Y .PDF)</b> correspondiente a:</p>
						<p style='font-size:12px;padding-left:28'><b>Tipo de Documento:</b> $this->n_tipo_doc</p>
						<p style='font-size:12px;padding-left:28'><b>No. de Documento:</b> ".$infoCorreo["estab"]."-".$infoCorreo["ptoEmi"]."-".$infoCorreo["secuencial"]."</p>
						<p style='font-size:12px;padding-left:28'><b>Fecha de emisión:</b> ".$infoCorreo["fechaEmision"]."</p>
						<p style='font-size:12px;padding-left:28'><b>Ambiente:</b> $this->ambiente</p>";
		if($this->tipo_doc!="06"):		$html.= "<p style='font-size:12px;padding-left:28'><b>Valor Total:</b> $ ".number_format($infoCorreo["importeTotal"], 2, '.','')."</p>";
		endif;
		$html.= "	</td>
				</tr>
				<tr><td><p style='font-size:12px;padding-left:28';padding-top:20'>Recuerde que este documento digital tiene validez tributaria, por lo que le sugerimos conservarlo para los fines fiscales pertinentes. Si requiere actualizar su correo electrónico para recepción de este documento por favor comuníquese con nosotros.</p>
					</td>
				</tr>
				<tr><td><p style='font-family:Arial,Helvetica,sans-serif;font-size:9px;padding-left:28;padding-top:20'>La información enviada a esta dirección de correo electrónico cumple con todas las normas legales según el REGLAMENTO GENERAL A LA LEY DE COMERCIO ELECTRÓNICO FIRMAS ELECTRÓNICAS Y MENSAJES DE DATOS.</p>
						<p style='font-family:Arial,Helvetica,sans-serif;font-size:9px;padding-left:28'><b>Nota de descargo:</b> La información contenida en este e-mail es confidencial y sólo puede ser utilizada por el individuo o la compañía a la cual está dirigido. Esta información no debe ser distribuida ni copiada total o parcialmente por ningún medio</p>
					</td>
				</tr>
			</tbody>
			</table>
		</div>";
		
		$mail->MsgHTML($html);		
															$envio_dest= false;
		if($this->tipo_ambiente==2):						$address= str_replace(';',',',$infoCorreo["mail_to"]);		$array_address= explode(",", $address);
				for($i=0; $i<count($array_address); $i++){	$email= trim($array_address[$i]);
					if($this->valida_mail($email)){			$mail->AddAddress($email);	$envio_dest= true;	}		
				}
		endif;												
		if(!$envio_dest):									$mail->AddAddress($remitente);
		endif;
		
		$smtp_email_bcc= $this->arr_parametros["smtp_email_bcc"].",".$this->arr_parametros["smtp_email_bcc_".$this->tipo_doc];
		$address= str_replace(';',',',$smtp_email_bcc);		$array_address= explode(",", $address);
		for($i=0; $i<count($array_address); $i++){			$email= trim($array_address[$i]);
			if($this->valida_mail($email)){					$mail->AddBCC($email);		}	
		}													$mail->AddBCC($remitente);
		
		$mail->AddAttachment($this->ruta_archivo_xml);		$mail->AddAttachment($this->ruta_archivo_pdf);
		if(!$mail->Send()):									$this->log_errores("CORREO", $mail->ErrorInfo);
		else:	if($envio_dest):	
						list($tipo_doc, $prefijo1, $prefijo2, $secuencia)= explode("_", $this->CompElectronico);
						$xml_comprobante= array(	"envio_correo"	=>	1);
						$this->db->where("tipo_doc", $tipo_doc);
						$this->db->where("prefijo1", $prefijo1);
						$this->db->where("prefijo2", $prefijo2);
						$this->db->where("secuencia", $secuencia);
						$this->db->update("xml_comprobante", $xml_comprobante);
				endif;
		endif;
    }
    
    function valida_mail($email)
    {	return eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email);
    }
    
    function log_errores($tipo, $detalle)
    {	
		list($tipo_doc, $prefijo1, $prefijo2, $secuencia)= explode("_", $this->CompElectronico);
		$xml_log_errores= array(	"tipo_error"	=>	$tipo,			"detalle"	=>	$detalle." ",
									"tipo_doc"		=>	$tipo_doc,		"prefijo1"	=>	$prefijo1,
									"prefijo2"		=>	$prefijo2,		"secuencia"	=>	$secuencia);
		$this->db->insert("xml_log_errores", $xml_log_errores);
    }
	
    function actualiza_comprobante_declarado($infoCorreo){		
		$query= $this->db->query("SELECT * FROM xml_tercero WHERE identificacion='".$infoCorreo["ruc"]."'");
		if($query->num_rows()==0):
				$tercero= array(	"identificacion"=>	$infoCorreo["ruc"],		
									"nombre"		=>	$infoCorreo["nombre"],
									"clave"			=>	md5($infoCorreo["ruc"]),
									"email"			=>	$infoCorreo["mail_to"]);
				$this->db->insert("xml_tercero", $tercero);
				
		else:	$tercero= array(	"nombre"		=>	$infoCorreo["nombre"],
									"email"			=>	$infoCorreo["mail_to"]);
				$this->db->where("identificacion", $infoCorreo["ruc"]);
				$this->db->update("xml_tercero", $tercero);
		endif;
		
		$query= $this->portal->query("SELECT * FROM ef_users WHERE id='".$infoCorreo["ruc"]."'");
		if($query->num_rows()==0):
				$tercero= array(	"id"			=>	$infoCorreo["ruc"],		
									"username"		=>	$infoCorreo["ruc"],		
									"name"			=>	$infoCorreo["nombre"],
									"password"		=>	md5($infoCorreo["ruc"]),
									"email"			=>	$infoCorreo["mail_to"]);
				$this->portal->insert("ef_users", $tercero);
				
		else:	$tercero= array(	"username"		=>	$infoCorreo["ruc"],
									"name"			=>	$infoCorreo["nombre"],
									"email"			=>	$infoCorreo["mail_to"]);
				$this->portal->where("id", $infoCorreo["ruc"]);
				$this->portal->update("ef_users", $tercero);
		endif;
		
		if($this->reenvio_correo)		return;
		
		list($tipo_doc, $prefijo1, $prefijo2, $secuencia)= explode("_", $this->CompElectronico);
		list($dia, $mes, $ano)= explode("/", $infoCorreo["fechaEmision"]);			$fecha_emision=date("Y-m-d", mktime(0,0,0,$mes, $dia,$ano));		
		$xml_comprobante= array(	"tipo_doc"		=>	$tipo_doc,					"prefijo1"			=>	$prefijo1,
									"prefijo2"		=>	$prefijo2,					"secuencia"			=>	$secuencia,
									"tercero"		=>	$infoCorreo["ruc"],			"fecha_emision"		=>	$fecha_emision,
									"clave_acceso"	=> 	$infoCorreo["claveAcceso"],	"total"				=>	$infoCorreo["importeTotal"],
									"autorizacion"	=>	$this->autorizacion,		"fecha_autorizacion"=>	$this->fechaAutoriz,
									"estado"		=>	$this->estado,				"ambiente"			=>	$this->ambiente,
									"contenido_xml"	=>	$this->contenido_xml);
		
		$query= $this->db->query("SELECT * FROM xml_comprobante WHERE tipo_doc='$tipo_doc' and prefijo1='$prefijo1' and prefijo2='$prefijo2' and secuencia='$secuencia'");
		if($query->num_rows()==0):
		//if(!$this->consulta_autoriz):
				$this->db->insert("xml_comprobante", $xml_comprobante);		
		else:	$this->db->where("tipo_doc", $tipo_doc);
				$this->db->where("prefijo1", $prefijo1);
				$this->db->where("prefijo2", $prefijo2);
				$this->db->where("secuencia", $secuencia);
				$this->db->update("xml_comprobante", $xml_comprobante);
		endif;
		
		$datos_emp= $this->querys_model->GetDatosEmpresa();
		$query = $this->portal->query("SELECT * FROM ef_empresa WHERE ruc = '".$datos_emp["ruc"]."'");
		if($query->num_rows()==0):
				$empresa= array(	"ruc"			=>	$datos_emp["ruc"],		
									"n_empresa"		=>	$datos_emp["n_empresa"],		
									"nombre_comercial"=>	$datos_emp["n_comercial"],
									"numero_contribuyente_especial"	=>	$datos_emp["numero_contribuyente_especial"]);
				$this->portal->insert("ef_empresa", $empresa);			$id_empresa= $this->portal->insert_id();
				
		else:	$empresa= array(	"n_empresa"		=>	$datos_emp["n_empresa"],		
									"nombre_comercial"=>	$datos_emp["n_comercial"],
									"numero_contribuyente_especial"	=>	$datos_emp["numero_contribuyente_especial"]);
				$this->portal->where("ruc", $datos_emp["ruc"]);
				$this->portal->update("ef_empresa", $empresa);			$consulta = $query->row_array();		$id_empresa= $consulta["id_empresa"];
		endif;
									
		$xml_comp_portal= array(	"id_empresa"	=>	$id_empresa,						"tipo_doc"			=>	$tipo_doc,
									"numero"		=>	"$prefijo1-$prefijo2-$secuencia",	"ambiente"			=>	$this->ambiente,
									"tercero_id"	=>	$infoCorreo["ruc"],					"fecha_emision"		=>	$fecha_emision,
									"clave_acceso"	=> 	$infoCorreo["claveAcceso"],			"importe_total"		=>	$infoCorreo["importeTotal"],
									"autorizacion"	=>	$this->autorizacion,				"fecha_autorizacion"=>	$this->fechaAutoriz,
									"xml"			=>	$this->contenido_xml);
		
		$query= $this->portal->query("SELECT * FROM ef_comprobantes WHERE id_empresa='$id_empresa' and tipo_doc='$tipo_doc' and numero='$prefijo1-$prefijo2-$secuencia'");
		if($this->autorizacion!="" and $query->num_rows()==0):
				$this->portal->insert("ef_comprobantes", $xml_comp_portal);	
		endif;
	}
	function ObtieneTipoIdentif($tipo_declaracion, $TipoIdentf, $Identf){
				if(!ereg("[^0-9]", $Identf) and strlen($Identf)==13 and !in_array($TipoIdentf, array("CE", "PA"))):		$TipoIdentf="ruc";			
				elseif(!ereg("[^0-9]", $Identf) and strlen($Identf)==10 and !in_array($TipoIdentf, array("CE", "PA"))):	$TipoIdentf="cedula";	
				elseif(in_array($TipoIdentf, array("CE", "PA"))):		$TipoIdentf="pasaporte";	
				endif;		
				if($tipo_declaracion=="compras"):
						switch($TipoIdentf):
							case "ruc":			$TipoIdentf="01";	break;
							case "cedula":		$TipoIdentf="02";	break;
							case "pasaporte":	$TipoIdentf="03";	break;
						endswitch;	
				else:	switch($TipoIdentf):
							case "ruc":			$TipoIdentf="04";	break;
							case "cedula":		$TipoIdentf="05";	break;
							case "pasaporte":	$TipoIdentf="06";	break;
						endswitch;	
				endif;
				if($Identf=="9999999999999"):	$TipoIdentf="07";
				endif;							return $TipoIdentf;
	}
    
    function DatosFactura($numeracion='', $sistema=''){
		$datos_emp= $this->querys_model->GetDatosEmpresa();
		$consulta_cabecera= $this->querys_model->GetDatosFactura($numeracion, $sistema);		
		$this->tipo_ambiente= $this->arr_parametros["tipo_ambiente"];	$i= 0;
		if($consulta_cabecera->num_rows() > 0):
		foreach($consulta_cabecera->result() as $row):											
					$tipoIdentif= $this->ObtieneTipoIdentif("ventas", $row->tipo_identif, $row->identif);
					$arreglo[$i]["infoTributaria"]["ambiente"]= $this->tipo_ambiente;			
					$arreglo[$i]["infoTributaria"]["tipoEmision"]= $this->tipo_emision;
					$arreglo[$i]["infoTributaria"]["razonSocial"]= $datos_emp["n_empresa"];		
					$arreglo[$i]["infoTributaria"]["nombreComercial"]= $datos_emp["n_empresa"];
					$arreglo[$i]["infoTributaria"]["ruc"]= $datos_emp["ruc"];
										
					if($row->clave_acceso==""):													
						$arreglo[$i]["infoTributaria"]["claveAcceso"]= $this->ObtieneClaveAcceso($row->fechaem, $this->tipo_doc, $datos_emp["ruc"], $this->tipo_ambiente, $row->prefijo1, $row->prefijo2, $row->secuencia, $this->tipo_emision);
					else:																		
						$arreglo[$i]["infoTributaria"]["claveAcceso"]= $row->clave_acceso;		
						if($row->estado!="NI"):			$this->consulta_autoriz= true;	endif;		
						if($row->envio_correo=="2"):	$this->reenvio_correo= true;	endif;
					endif;	
																						
					$arreglo[$i]["infoTributaria"]["codDoc"]= $this->tipo_doc;	
					$arreglo[$i]["infoTributaria"]["estab"]= $row->prefijo1;					
					$arreglo[$i]["infoTributaria"]["ptoEmi"]= $row->prefijo2;					
					$arreglo[$i]["infoTributaria"]["secuencial"]= $row->secuencia;				
					$arreglo[$i]["infoTributaria"]["dirMatriz"]= $datos_emp["direccion"];
					$this->CompElectronico= $this->tipo_doc."_".$row->prefijo1."_".$row->prefijo2."_".$row->secuencia;
					
					$arreglo[$i]["infoCorreo"]= $arreglo[$i]["infoTributaria"];					
					$arreglo[$i]["infoCorreo"]["ruc"]= $row->identif;
					$arreglo[$i]["infoCorreo"]["nombre"]= $row->n_cliente;						
					$arreglo[$i]["infoCorreo"]["mail_to"]= $row->email;
					$arreglo[$i]["infoCorreo"]["fechaEmision"]= $row->fechaemision;				
					$arreglo[$i]["infoCorreo"]["tipo_tercero"]= "Cliente";
					$arreglo[$i]["infoCorreo"]["propina"]= $row->propina;						
					$arreglo[$i]["infoCorreo"]["importeTotal"]= number_format($row->totalconimp+$row->propina, 2, '.','');
					$arreglo[$i]["infoCorreo"]["totalSinImpuestos"]= number_format($row->totalsinimp, 2, '.','');	
					$arreglo[$i]["infoCorreo"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];
					$arreglo[$i]["infoCorreo"]["totalDescuento"]= $row->totaldcto+$row->descuento_adicional; 
					$arreglo[$i]["infoCorreo"]["totalDescuentoAdicional"]= $row->descuento_adicional; 
					$arreglo[$i]["infoCorreo"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					$arreglo[$i]["infoCorreo"]["guiaRemision"]= $row->guia_remision;
					
					if(trim($row->direccion) == "")		$row->direccion= "NN";
					$arreglo[$i]["infoFactura"]["fechaEmision"]= $row->fechaemision;			
					$arreglo[$i]["infoFactura"]["dirEstablecimiento"]= $row->direccion;
					$arreglo[$i]["infoFactura"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];	
					$arreglo[$i]["infoFactura"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					$arreglo[$i]["infoFactura"]["tipoIdentificacionComprador"]= $tipoIdentif;	
					if(trim($row->direccion) == "")		$arreglo[$i]["infoFactura"]["guiaRemision"]= $row->guia_remision;
					$arreglo[$i]["infoFactura"]["razonSocialComprador"]= $row->n_cliente;		
					$arreglo[$i]["infoFactura"]["identificacionComprador"]= $row->identif;			
					$arreglo[$i]["infoFactura"]["totalSinImpuestos"]= number_format($row->totalsinimp, 2, '.','');
					//$arreglo[$i]["infoFactura"]["totalSinImpuestos"]= number_format($row->totalconimp, 2, '.','');
					$arreglo[$i]["infoFactura"]["totalDescuento"]= number_format($row->totaldcto+$row->descuento_adicional, 2, '.','');
					
					$this->suma_imp= array();													
					$consulta_detalle= $this->querys_model->GetDatosFacturaDetalle($row->id, $row->tipo_factura, $sistema);
					$consulta_detalle_imp= $this->querys_model->GetDatosFacturaDetalleImp($row->id, $row->tipo_factura, $sistema);
					foreach($consulta_detalle_imp->result() as $rows):							
							$arr_imp[$rows->det_id]["codigoimp"][]= $rows->codigoimp;
							$arr_imp[$rows->det_id]["codigoporc"][]= $rows->codigoporc;			
							$arr_imp[$rows->det_id]["tarifa"][]= $rows->tarifa;
							$arr_imp[$rows->det_id]["base_imponible"][]= $rows->base_imponible;	
							$arr_imp[$rows->det_id]["valor"][]= $rows->valor;
							
							$this->suma_imp["base_imp"][$rows->codigoimp][$rows->codigoporc]+= $rows->base_imponible;
							$this->suma_imp["valor"][$rows->codigoimp][$rows->codigoporc]+= $rows->valor;
					endforeach;
					
					$j=0;
					$arr_codigoimp= array_keys($this->suma_imp["base_imp"]);
					
					for($xxx=0; $xxx<count($arr_codigoimp); $xxx++):			
						$codigoimp= $arr_codigoimp[$xxx];		
						$arr_codigoporc= array_keys($this->suma_imp["base_imp"][$codigoimp]);
						
							for($yyy=0; $yyy<count($arr_codigoporc); $yyy++):	
								$codigoporc= $arr_codigoporc[$yyy];
								$arreglo[$i]["infoFactura"]["totalConImpuestos"]["totalImpuesto"][$j]["codigo"]= $codigoimp;
								$arreglo[$i]["infoFactura"]["totalConImpuestos"]["totalImpuesto"][$j]["codigoPorcentaje"]= $codigoporc;
								$arreglo[$i]["infoFactura"]["totalConImpuestos"]["totalImpuesto"][$j]["descuentoAdicional"]= number_format($row->descuento_adicional, 2, '.','');
								$arreglo[$i]["infoFactura"]["totalConImpuestos"]["totalImpuesto"][$j]["baseImponible"]= number_format(($this->suma_imp["base_imp"][$codigoimp][$codigoporc]), 2, '.','');
								$arreglo[$i]["infoFactura"]["totalConImpuestos"]["totalImpuesto"][$j]["valor"]= number_format($this->suma_imp["valor"][$codigoimp][$codigoporc], 2, '.',''); 
								$j++;
							endfor;
					endfor;	
					
					$arreglo[$i]["infoFactura"]["propina"]= $row->propina;		
					$arreglo[$i]["infoFactura"]["importeTotal"]= number_format($row->totalconimp+$row->propina, 2, '.','');
					$arreglo[$i]["infoFactura"]["moneda"]= "DOLAR";				
					
					$j=0; 
					$f=0;
					
					foreach($consulta_detalle->result() as $rows):
							/*if($rows->totalsinimp<0):							$totalsinimp= abs($rows->totalsinimp);
									while($totalsinimp>0):
									for($xx=0; $xx<count($arreglo[$i]["detalles"]["detalle"]); $xx++):							$dcto= $arreglo[$i]["detalles"]["detalle"][$xx]["precioTotalSinImpuesto"]-$arreglo[$i]["detalles"]["detalle"][$xx]["descuento"];
											if($dcto>$totalsinimp):																$dcto= $totalsinimp;
											endif;			$arreglo[$i]["detalles"]["detalle"][$xx]["descuento"]+= $dcto;		$arreglo[$i]["detalles"]["detalle"][$xx]["precioTotalSinImpuesto"]-= $dcto;	
															$arreglo[$i]["infoFactura"]["totalDescuento"]+= $dcto;				
															$arreglo[$i]["infoCorreo"]["totalDescuento"]+= $dcto;				$totalsinimp-= $dcto;
											
											for($xxx=0; $xxx<count($arreglo[$i]["detalles"]["detalle"][$xx]["impuestos"]["impuesto"]); $xxx++):
																		$arreglo[$i]["detalles"]["detalle"][$xx]["impuestos"]["impuesto"][$xxx]["baseImponible"]-= $dcto;
											endfor;
											if(!$totalsinimp>0):		break;
											endif;
									endfor;
									endwhile;		continue;
							endif;*/																$arreglo[$i]["detalles"]["detalle"][$j]["codigoPrincipal"]= $rows->codigoprincipal;
							if(trim($rows->codigoauxiliar)!=""):									$arreglo[$i]["detalles"]["detalle"][$j]["codigoAuxiliar"]= $rows->codigoauxiliar;			
							endif;																	$arreglo[$i]["detalles"]["detalle"][$j]["descripcion"]= preg_replace( "/\r|\n/", "", substr(trim($rows->descripcion), 0, 300));
							if($row->tipo_factura=="4"):											$detalle_factura_descripcion_arr = explode("|", trim($rows->descripcion));
									if (strlen(trim($detalle_factura_descripcion_arr[$f])) > 0):	$arreglo[$i]["detalles"]["detalle"][$j]["descripcion"]= preg_replace( "/\r|\n/", "", substr(trim($detalle_factura_descripcion_arr[$f]), 0, 300)); 
									endif;
							endif;
							$arreglo[$i]["detalles"]["detalle"][$j]["cantidad"]= $rows->cantidad;								$arreglo[$i]["detalles"]["detalle"][$j]["precioUnitario"]= number_format($rows->precio, 2, '.','');
							$arreglo[$i]["detalles"]["detalle"][$j]["descuento"]= number_format($rows->descuento,2, '.','');	$arreglo[$i]["detalles"]["detalle"][$j]["precioTotalSinImpuesto"]= number_format($rows->totalsinimp, 2, '.','');					
							
							for($k=0; $k<count($arr_imp[$rows->det_id]["codigoimp"]); $k++):
									if($arr_imp[$rows->det_id]["codigoimp"][$k]==""):		$arr_imp[$rows->det_id]["codigoimp"][$k]= "2";		
																							$arr_imp[$rows->det_id]["codigoporc"][$k]= "0";		
									endif;					
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["codigo"]= $arr_imp[$rows->det_id]["codigoimp"][$k];		
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["codigoPorcentaje"]= $arr_imp[$rows->det_id]["codigoporc"][$k];	
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["tarifa"]= number_format($arr_imp[$rows->det_id]["tarifa"][$k],2, '.','');			
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["baseImponible"]= number_format($arr_imp[$rows->det_id]["base_imponible"][$k],2, '.','');	
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["valor"]= number_format($arr_imp[$rows->det_id]["valor"][$k],2, '.','');
							endfor;	$j++; $f++;
					endforeach;
					
					
					if((int)$row->cuenta>0):		$datos_hab= $this->querys_model->GetDatosHabitacionPaciente($row->cuenta);
					endif;
					
					if($row->forma_pago=="Contado"):		$row->dias_credito= "";
					endif;
															$j=0;
					if(trim($row->direccion_pto!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Direccion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->direccion_pto;		$j++;
					endif;
					if(trim($row->tlf!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Telefono");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->tlf;				$j++;
					endif;
					if(trim($row->email!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Email");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->email;				$j++;
					endif;
					if(trim($row->observacion!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Observacion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= preg_replace( "/\r|\n/", "", $row->observacion);		$j++;
					endif;
					if(trim($row->fechavencimiento!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Fecha Vencimiento");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->fechavencimiento;		$j++;
					endif;
					if(trim($row->n_paciente!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Paciente");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->n_paciente;		$j++;
					endif;
					if(trim($row->hc_paciente!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Historia Clinica");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->hc_paciente;		$j++;
					endif;
					if(trim($row->fechaingreso!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Fecha Ingreso");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->fechaingreso;		$j++;
					endif;
					if(trim($row->fechasalida!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Fecha Salida");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->fechasalida;		$j++;
					endif;
					if(is_array($datos_hab)):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Habitacion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $datos_hab["ubicacion"]." - ".$datos_hab["habitacion"];		$j++;
					endif;
					if(trim($row->profesional_tratante!="")):			
					if(trim($row->profesional_especialidad!="")):		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Profesional - Especialidad");
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->profesional_tratante." - ".$row->profesional_especialidad;		
					else: 
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Profesional");
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->profesional_tratante;	
					endif; $j++;
					endif;
					if((int)$row->ingreso>0):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Ingreso");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->ingreso;		$j++;
					endif;
					if((int)$row->cuenta>0):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Cuenta");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->cuenta;		$j++;
					endif;
					if(trim($row->usuario!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Facturador");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->usuario;		$j++;
					endif;
					if(trim($row->ordcompra_cliente!="")):	$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "O/C Cliente");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->ordcompra_cliente;		$j++;
					endif;
					if((float)$row->descuento_adicional>0):	$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => ($row->n_descuento_adicional == 'FEE' ? 'FEE HOSPITALARIO' : $row->n_descuento_adicional));		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= preg_replace( "/\r|\n/", "", "$ ".$row->descuento_adicional);		$j++;
					endif;
					if(trim($row->forma_pago!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Forma de Pago");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->forma_pago." ".$row->dias_credito;		$j++;
					endif;
					if(trim($row->vendedor!="")):			$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Vendedor");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->vendedor;			$j++;
					endif;  $i++;
		endforeach; endif;	return $arreglo;
	}
    
    function DatosNotaVenta($numeracion='', $sistema=''){
	}
    
    function DatosNotaDebito($numeracion='', $sistema=''){
	}
    
    function DatosNotaCredito($numeracion='', $sistema=''){	
		$datos_emp= $this->querys_model->GetDatosEmpresa();
		$consulta_cabecera= $this->querys_model->GetDatosNotaCredito($numeracion, $sistema);	$this->tipo_ambiente= $this->arr_parametros["tipo_ambiente"];	$i= 0; 
		foreach($consulta_cabecera->result() as $row):											$tipoIdentif= $this->ObtieneTipoIdentif("ventas", $row->tipo_identif, $row->identif);
					$arreglo[$i]["infoTributaria"]["ambiente"]= $this->tipo_ambiente;			$arreglo[$i]["infoTributaria"]["tipoEmision"]= $this->tipo_emision;
					$arreglo[$i]["infoTributaria"]["razonSocial"]= $datos_emp["n_empresa"];		$arreglo[$i]["infoTributaria"]["nombreComercial"]= $datos_emp["n_empresa"];
					$arreglo[$i]["infoTributaria"]["ruc"]= $datos_emp["ruc"];					
					if($row->clave_acceso==""):													$arreglo[$i]["infoTributaria"]["claveAcceso"]= $this->ObtieneClaveAcceso($row->fechaem, $this->tipo_doc, $datos_emp["ruc"], $this->tipo_ambiente, $row->prefijo1, $row->prefijo2, $row->secuencia, $this->tipo_emision);
					else:																		$arreglo[$i]["infoTributaria"]["claveAcceso"]= $row->clave_acceso;		
							if($row->estado!="NI"):			$this->consulta_autoriz= true;	endif;		
							if($row->envio_correo=="2"):	$this->reenvio_correo= true;	endif;
					endif;																		$arreglo[$i]["infoTributaria"]["codDoc"]= $this->tipo_doc;	
					$arreglo[$i]["infoTributaria"]["estab"]= $row->prefijo1;					$arreglo[$i]["infoTributaria"]["ptoEmi"]= $row->prefijo2;					
					$arreglo[$i]["infoTributaria"]["secuencial"]= $row->secuencia;				$arreglo[$i]["infoTributaria"]["dirMatriz"]= $datos_emp["direccion"];
					$this->CompElectronico= $this->tipo_doc."_".$row->prefijo1."_".$row->prefijo2."_".$row->secuencia;
					
					$arreglo[$i]["infoCorreo"]= $arreglo[$i]["infoTributaria"];					$arreglo[$i]["infoCorreo"]["ruc"]= $row->identif;
					$arreglo[$i]["infoCorreo"]["nombre"]= $row->n_cliente;						$arreglo[$i]["infoCorreo"]["mail_to"]= $row->email;
					$arreglo[$i]["infoCorreo"]["fechaEmision"]= $row->fechaemision;				$arreglo[$i]["infoCorreo"]["tipo_tercero"]= "Cliente";
					$arreglo[$i]["infoCorreo"]["propina"]= $row->propina;						$arreglo[$i]["infoCorreo"]["importeTotal"]= number_format($row->totalconimp+$row->propina, 2, '.','');
					$arreglo[$i]["infoCorreo"]["totalSinImpuestos"]= number_format($row->totalsinimp, 2, '.','');	$arreglo[$i]["infoCorreo"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];
					$arreglo[$i]["infoCorreo"]["totalDescuento"]= $row->totaldcto+$row->descuento_adicional;		$arreglo[$i]["infoCorreo"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					$arreglo[$i]["infoCorreo"]["guiaRemision"]= "";
					
					if(trim($row->direccion) == "")		$row->direccion= "NN";
					$arreglo[$i]["infoNotaCredito"]["fechaEmision"]= $row->fechaemision;			
					$arreglo[$i]["infoNotaCredito"]["dirEstablecimiento"]= $row->direccion;
					$arreglo[$i]["infoNotaCredito"]["tipoIdentificacionComprador"]= $tipoIdentif;
					$arreglo[$i]["infoNotaCredito"]["razonSocialComprador"]= $row->n_cliente;		
					$arreglo[$i]["infoNotaCredito"]["identificacionComprador"]= $row->identif;	
					$arreglo[$i]["infoNotaCredito"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];	
					$arreglo[$i]["infoNotaCredito"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];		
					$arreglo[$i]["infoNotaCredito"]["codDocModificado"]= $row->mod_tipo_doc;			
					$arreglo[$i]["infoNotaCredito"]["numDocModificado"]= $row->mod_numeracion;			
					$arreglo[$i]["infoNotaCredito"]["fechaEmisionDocSustento"]= $row->mod_fechaemision;			
					$arreglo[$i]["infoNotaCredito"]["totalSinImpuestos"]= number_format($row->totalsinimp, 2, '.','');
					//$arreglo[$i]["infoNotaCredito"]["totalDescuento"]= $row->totaldcto+$row->descuento_adicional;
					$arreglo[$i]["infoNotaCredito"]["valorModificacion"]= number_format($row->totalconimp, 2, '.','');
					$arreglo[$i]["infoNotaCredito"]["moneda"]= "DOLAR";	

					$this->suma_imp= array();													$consulta_detalle= $this->querys_model->GetDatosNotaCreditoDetalle($row->id, $row->tipo_nc, $sistema);
																								$consulta_detalle_imp= $this->querys_model->GetDatosNotaCreditoDetalleImp($row->id, $row->tipo_nc, $sistema);
					if($row->totalconimp==$row->total_fac and $sistema=="SIIS"):				$consulta_detalle= $this->querys_model->GetDatosFacturaDetalle($row->factura_id, $row->tipo_factura, $sistema);
																								$consulta_detalle_imp= $this->querys_model->GetDatosFacturaDetalleImp($row->factura_id, $row->tipo_factura, $sistema);
					endif;
					foreach($consulta_detalle_imp->result() as $rows):							$arr_imp[$rows->det_id]["codigoimp"][]= $rows->codigoimp;
							$arr_imp[$rows->det_id]["codigoporc"][]= $rows->codigoporc;			$arr_imp[$rows->det_id]["tarifa"][]= $rows->tarifa;
							$arr_imp[$rows->det_id]["base_imponible"][]= $rows->base_imponible;	$arr_imp[$rows->det_id]["valor"][]= $rows->valor;
							
							$this->suma_imp["base_imp"][$rows->codigoimp][$rows->codigoporc]+= $rows->base_imponible;
							$this->suma_imp["valor"][$rows->codigoimp][$rows->codigoporc]+= $rows->valor;
					endforeach;
																				$j=0;									$arr_codigoimp= array_keys($this->suma_imp["base_imp"]);
					for($xxx=0; $xxx<count($arr_codigoimp); $xxx++):			$codigoimp= $arr_codigoimp[$xxx];		$arr_codigoporc= array_keys($this->suma_imp["base_imp"][$codigoimp]);
							for($yyy=0; $yyy<count($arr_codigoporc); $yyy++):	$codigoporc= $arr_codigoporc[$yyy];
																				$arreglo[$i]["infoNotaCredito"]["totalConImpuestos"]["totalImpuesto"][$j]["codigo"]= $codigoimp;
																				$arreglo[$i]["infoNotaCredito"]["totalConImpuestos"]["totalImpuesto"][$j]["codigoPorcentaje"]= $codigoporc;
																				//$arreglo[$i]["infoNotaCredito"]["totalConImpuestos"]["totalImpuesto"][$j]["descuentoAdicional"]= number_format($row->descuento_adicional, 2, '.','');
																				$arreglo[$i]["infoNotaCredito"]["totalConImpuestos"]["totalImpuesto"][$j]["baseImponible"]= number_format($this->suma_imp["base_imp"][$codigoimp][$codigoporc], 2, '.','');
																				$arreglo[$i]["infoNotaCredito"]["totalConImpuestos"]["totalImpuesto"][$j]["valor"]= number_format($this->suma_imp["valor"][$codigoimp][$codigoporc], 2, '.','');		$j++;
							endfor;
					endfor;	
					if(trim($row->observacion) == "")		$row->observacion= "NN";
					$arreglo[$i]["infoNotaCredito"]["motivo"]= preg_replace( "/\r|\n/", "", $row->observacion);			$j=0;
					
					foreach($consulta_detalle->result() as $rows):																$arreglo[$i]["detalles"]["detalle"][$j]["codigoInterno"]= $rows->codigoprincipal;
							if(trim($rows->codigoauxiliar)!=""):																$arreglo[$i]["detalles"]["detalle"][$j]["codigoAdicional"]= $rows->codigoauxiliar;			
							endif;																								$arreglo[$i]["detalles"]["detalle"][$j]["descripcion"]= preg_replace( "/\r|\n/", "", substr(trim($rows->descripcion), 0, 300));
							$arreglo[$i]["detalles"]["detalle"][$j]["cantidad"]= $rows->cantidad;								$arreglo[$i]["detalles"]["detalle"][$j]["precioUnitario"]= number_format($rows->precio, 2, '.','');
							$arreglo[$i]["detalles"]["detalle"][$j]["descuento"]= number_format($rows->descuento,2, '.','');	$arreglo[$i]["detalles"]["detalle"][$j]["precioTotalSinImpuesto"]= number_format($rows->totalsinimp, 2, '.','');					
							
							for($k=0; $k<count($arr_imp[$rows->det_id]["codigoimp"]); $k++):
									if($arr_imp[$rows->det_id]["codigoimp"][$k]==""):		$arr_imp[$rows->det_id]["codigoimp"][$k]= "2";		
																							$arr_imp[$rows->det_id]["codigoporc"][$k]= "0";		
									endif;					
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["codigo"]= $arr_imp[$rows->det_id]["codigoimp"][$k];		
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["codigoPorcentaje"]= $arr_imp[$rows->det_id]["codigoporc"][$k];	
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["tarifa"]= number_format($arr_imp[$rows->det_id]["tarifa"][$k],2, '.','');			
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["baseImponible"]= number_format($arr_imp[$rows->det_id]["base_imponible"][$k],2, '.','');	
									$arreglo[$i]["detalles"]["detalle"][$j]["impuestos"]["impuesto"][$k]["valor"]= number_format($arr_imp[$rows->det_id]["valor"][$k],2, '.','');
							endfor;	$j++;
					endforeach;
															$j=0;
					if(trim($row->direccion_pto!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Direccion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->direccion_pto;		$j++;
					endif;
					if(trim($row->tlf!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Telefono");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->tlf;				$j++;
					endif;
					if(trim($row->email!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Email");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->email;				$j++;
					endif;
					/*if(trim($row->observacion!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Observacion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= preg_replace( "/\r|\n/", "", $row->observacion);		$j++;
					endif;
					if(trim($row->ordcompra_cliente!="")):	$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Orden de Compra Cliente");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->ordcompra_cliente;		$j++;
					endif;*/  $i++;
		endforeach;	return $arreglo;
	}
    
    function DatosGuiaRemision($numeracion='', $sistema=''){
		$datos_emp= $this->querys_model->GetDatosEmpresa();
		$consulta_cabecera= $this->querys_model->GetDatosGuiaRemision($numeracion, $sistema);	$this->tipo_ambiente= $this->arr_parametros["tipo_ambiente"];	$i= 0;
		foreach($consulta_cabecera->result() as $row):	
					if($row->identif==""):								$row->tipo_identif= "RUC";
							$row->identif= $datos_emp["ruc"];			$row->n_cliente= $datos_emp["n_empresa"];
							$row->direccion= $datos_emp["direccion"];	$row->direccion_pto= $datos_emp["direccion"];
							$row->tlf= $datos_emp["telefono"];			$row->email= "";
					endif;			
					if($row->transportista==""):		$row->ruc_transportista= $datos_emp["ruc"];
														$row->transportista= $datos_emp["n_empresa"];
					endif;
					if($row->ruc_transportista=="")		$row->ruc_transportista= "9999999999999";
					if($row->placa_transportista=="")	$row->placa_transportista= "NN";
					if($row->destino=="")				$row->destino= $row->direccion;
					if($row->motivo=="")				$row->motivo= "NN";
					
																								$tipoIdentif= $this->ObtieneTipoIdentif("ventas", "", $row->identif);
					$arreglo[$i]["infoTributaria"]["ambiente"]= $this->tipo_ambiente;			$arreglo[$i]["infoTributaria"]["tipoEmision"]= $this->tipo_emision;
					$arreglo[$i]["infoTributaria"]["razonSocial"]= $datos_emp["n_empresa"];		$arreglo[$i]["infoTributaria"]["nombreComercial"]= $datos_emp["n_empresa"];
					$arreglo[$i]["infoTributaria"]["ruc"]= $datos_emp["ruc"];					
					if($row->clave_acceso==""):													$arreglo[$i]["infoTributaria"]["claveAcceso"]= $this->ObtieneClaveAcceso($row->fechaem, $this->tipo_doc, $datos_emp["ruc"], $this->tipo_ambiente, $row->prefijo1, $row->prefijo2, $row->secuencia, $this->tipo_emision);
					else:																		$arreglo[$i]["infoTributaria"]["claveAcceso"]= $row->clave_acceso;		
							if($row->estado!="NI"):			$this->consulta_autoriz= true;	endif;		
							if($row->envio_correo=="2"):	$this->reenvio_correo= true;	endif;
					endif;																		$arreglo[$i]["infoTributaria"]["codDoc"]= $this->tipo_doc;	
					$arreglo[$i]["infoTributaria"]["estab"]= $row->prefijo1;					$arreglo[$i]["infoTributaria"]["ptoEmi"]= $row->prefijo2;					
					$arreglo[$i]["infoTributaria"]["secuencial"]= $row->secuencia;				$arreglo[$i]["infoTributaria"]["dirMatriz"]= $datos_emp["direccion"];
					$this->CompElectronico= $this->tipo_doc."_".$row->prefijo1."_".$row->prefijo2."_".$row->secuencia;
					
					$arreglo[$i]["infoCorreo"]= $arreglo[$i]["infoTributaria"];					$arreglo[$i]["infoCorreo"]["ruc"]= $row->identif;
					$arreglo[$i]["infoCorreo"]["nombre"]= $row->n_cliente;						$arreglo[$i]["infoCorreo"]["mail_to"]= $row->email;
					$arreglo[$i]["infoCorreo"]["fechaEmision"]= $row->fechaemision;				$arreglo[$i]["infoCorreo"]["tipo_tercero"]= "Destinatario";
					$arreglo[$i]["infoCorreo"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];
					$arreglo[$i]["infoCorreo"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					
					if(trim($row->direccion) == "")		$row->direccion= "NN";
					$arreglo[$i]["infoGuiaRemision"]["dirEstablecimiento"]= $datos_emp["direccion"];			
					$arreglo[$i]["infoGuiaRemision"]["dirPartida"]= $datos_emp["direccion"];
					$arreglo[$i]["infoGuiaRemision"]["razonSocialTransportista"]= $row->transportista;
					$arreglo[$i]["infoGuiaRemision"]["tipoIdentificacionTransportista"]= $this->ObtieneTipoIdentif("ventas", "", $row->ruc_transportista);
					$arreglo[$i]["infoGuiaRemision"]["rucTransportista"]= $row->ruc_transportista;
					$arreglo[$i]["infoGuiaRemision"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					$arreglo[$i]["infoGuiaRemision"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];	
					$arreglo[$i]["infoGuiaRemision"]["fechaIniTransporte"]= $row->traslado_finicio;
					$arreglo[$i]["infoGuiaRemision"]["fechaFinTransporte"]= $row->traslado_ffin;
					$arreglo[$i]["infoGuiaRemision"]["placa"]= $row->placa_transportista;				
					
					
					$arreglo[$i]["destinatarios"]["destinatario"]["identificacionDestinatario"]= $row->identif;
					$arreglo[$i]["destinatarios"]["destinatario"]["razonSocialDestinatario"]= $row->n_cliente;		
					$arreglo[$i]["destinatarios"]["destinatario"]["dirDestinatario"]= $row->destino;			
					$arreglo[$i]["destinatarios"]["destinatario"]["motivoTraslado"]= $row->motivo;
					//$arreglo[$i]["destinatarios"]["destinatario"]["docAduaneroUnico"]= "";
					//$arreglo[$i]["destinatarios"]["destinatario"]["codEstabDestino"]= "";		
					//$arreglo[$i]["destinatarios"]["destinatario"]["ruta"]= "";		
					
					if($row->mod_numeracion!=""):
							$arreglo[$i]["destinatarios"]["destinatario"]["codDocSustento"]= $row->mod_tipo_doc;	
							$arreglo[$i]["destinatarios"]["destinatario"]["numDocSustento"]= $row->mod_numeracion;	
							if($row->mod_autorizacion!=""):	$arreglo[$i]["destinatarios"]["destinatario"]["numAutDocSustento"]= $row->mod_autorizacion;	
							endif;							$arreglo[$i]["destinatarios"]["destinatario"]["fechaEmisionDocSustento"]= $row->mod_fechaemision;	
					endif;
					
					$consulta_detalle= $this->querys_model->GetDatosGuiaRemisionDetalle($row->id, $sistema);		$j=0;
					foreach($consulta_detalle->result() as $rows):
							$arreglo[$i]["destinatarios"]["destinatario"]["detalles"]["detalle"][$j]["codigoInterno"]= $rows->codigoprincipal;
							$arreglo[$i]["destinatarios"]["destinatario"]["detalles"]["detalle"][$j]["codigoAdicional"]= $rows->codigoauxiliar;		
							$arreglo[$i]["destinatarios"]["destinatario"]["detalles"]["detalle"][$j]["descripcion"]= $rows->descripcion;			
							$arreglo[$i]["destinatarios"]["destinatario"]["detalles"]["detalle"][$j]["cantidad"]= $rows->cantidad;	$j++;		
					endforeach;
															$j=0;
					if(trim($row->direccion_pto!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Direccion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->direccion_pto;		$j++;
					endif;
					if(trim($row->tlf!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Telefono");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->tlf;				$j++;
					endif;
					if(trim($row->email!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Email");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->email;				$j++;
					endif;
					if(trim($row->observacion!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Observacion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= preg_replace( "/\r|\n/", "", $row->observacion);		$j++;
					endif;
					if(trim($row->ordcompra_cliente!="")):	$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Orden de Compra Cliente");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->ordcompra_cliente;		$j++;
					endif;  $i++;
		endforeach;	return $arreglo;
	}
    
    function DatosRetencion($numeracion='', $sistema=''){
		$datos_emp= $this->querys_model->GetDatosEmpresa();
		$consulta_cabecera= $this->querys_model->GetDatosRetencion($numeracion, $sistema);		$this->tipo_ambiente= $this->arr_parametros["tipo_ambiente"];	$i= 0;
		foreach($consulta_cabecera->result() as $row):											$tipoIdentif= $this->ObtieneTipoIdentif("ventas", $row->tipo_identif, $row->identif);
					$arreglo[$i]["infoTributaria"]["ambiente"]= $this->tipo_ambiente;			$arreglo[$i]["infoTributaria"]["tipoEmision"]= $this->tipo_emision;
					$arreglo[$i]["infoTributaria"]["razonSocial"]= $datos_emp["n_empresa"];		$arreglo[$i]["infoTributaria"]["nombreComercial"]= $datos_emp["n_empresa"];
					$arreglo[$i]["infoTributaria"]["ruc"]= $datos_emp["ruc"];					
					if($row->clave_acceso==""):													$arreglo[$i]["infoTributaria"]["claveAcceso"]= $this->ObtieneClaveAcceso($row->fechaem, $this->tipo_doc, $datos_emp["ruc"], $this->tipo_ambiente, $row->prefijo1, $row->prefijo2, $row->secuencia, $this->tipo_emision);
					else:																		$arreglo[$i]["infoTributaria"]["claveAcceso"]= $row->clave_acceso;		
							if($row->estado!="NI"):			$this->consulta_autoriz= true;	endif;		
							if($row->envio_correo=="2"):	$this->reenvio_correo= true;	endif;
					endif;																		$arreglo[$i]["infoTributaria"]["codDoc"]= $this->tipo_doc;					
					$arreglo[$i]["infoTributaria"]["estab"]= $row->prefijo1;					$arreglo[$i]["infoTributaria"]["ptoEmi"]= $row->prefijo2;					
					$arreglo[$i]["infoTributaria"]["secuencial"]= $row->secuencia;				$arreglo[$i]["infoTributaria"]["dirMatriz"]= $datos_emp["direccion"];
					$this->CompElectronico= $this->tipo_doc."_".$row->prefijo1."_".$row->prefijo2."_".$row->secuencia;
					
					$arreglo[$i]["infoCorreo"]= $arreglo[$i]["infoTributaria"];					$arreglo[$i]["infoCorreo"]["ruc"]= $row->identif;
					$arreglo[$i]["infoCorreo"]["nombre"]= $row->n_cliente;						$arreglo[$i]["infoCorreo"]["mail_to"]= $row->email;
					$arreglo[$i]["infoCorreo"]["fechaEmision"]= $row->fechaemision;				$arreglo[$i]["infoCorreo"]["tipo_tercero"]= "Proveedor";
																								$arreglo[$i]["infoCorreo"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];
																								$arreglo[$i]["infoCorreo"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					
					if(trim($row->direccion) == "")		$row->direccion= "NN";
					$arreglo[$i]["infoCompRetencion"]["fechaEmision"]= $row->fechaemision;			$arreglo[$i]["infoCompRetencion"]["dirEstablecimiento"]= $row->direccion;
					$arreglo[$i]["infoCompRetencion"]["contribuyenteEspecial"]= $datos_emp["numero_contribuyente_especial"];	
					$arreglo[$i]["infoCompRetencion"]["obligadoContabilidad"]= $datos_emp["obligado_contabilidad"];
					$arreglo[$i]["infoCompRetencion"]["tipoIdentificacionSujetoRetenido"]= $tipoIdentif;	//$arreglo[$i]["infoCompRetencion"]["guiaRemision"]= "";
					$arreglo[$i]["infoCompRetencion"]["razonSocialSujetoRetenido"]= $row->n_cliente;		$arreglo[$i]["infoCompRetencion"]["identificacionSujetoRetenido"]= $row->identif;			
					$arreglo[$i]["infoCompRetencion"]["periodoFiscal"]= substr($row->fechaemision, 3, 10);					$j=0;
					
					$this->suma_imp= array();													$consulta_detalle= $this->querys_model->GetDatosRetencionDetalle($row->id, $sistema);
					foreach($consulta_detalle->result() as $rows):								$this->suma_imp["codDocSustento"][$rows->codigoimp][$rows->codigoporc]= $rows->coddocsustento;
																								$this->suma_imp["numDocSustento"][$rows->codigoimp][$rows->codigoporc]= $rows->numdocsustento;
																								$this->suma_imp["fechaEmisionDoc"][$rows->codigoimp][$rows->codigoporc]= $rows->fechaemision;
																								$this->suma_imp["tarifa"][$rows->codigoimp][$rows->codigoporc]= $rows->tarifa;
																								$this->suma_imp["base_imp"][$rows->codigoimp][$rows->codigoporc]+= $rows->base_imponible;
																								$this->suma_imp["valor"][$rows->codigoimp][$rows->codigoporc]+= (($rows->base_imponible*$rows->tarifa)/100);
					endforeach;													$j=0;									$arr_codigoimp= array_keys($this->suma_imp["base_imp"]);
					for($xxx=0; $xxx<count($arr_codigoimp); $xxx++):			$codigoimp= $arr_codigoimp[$xxx];		$arr_codigoporc= array_keys($this->suma_imp["base_imp"][$codigoimp]);
							for($yyy=0; $yyy<count($arr_codigoporc); $yyy++):	$codigoporc= $arr_codigoporc[$yyy];
									$arreglo[$i]["impuestos"]["impuesto"][$j]["codigo"]= $codigoimp;		
									$arreglo[$i]["impuestos"]["impuesto"][$j]["codigoRetencion"]= $codigoporc;	
									$arreglo[$i]["impuestos"]["impuesto"][$j]["baseImponible"]= number_format($this->suma_imp["base_imp"][$codigoimp][$codigoporc], 2, '.','');			
									$arreglo[$i]["impuestos"]["impuesto"][$j]["porcentajeRetener"]= number_format($this->suma_imp["tarifa"][$codigoimp][$codigoporc], 2, '.','');	
									$arreglo[$i]["impuestos"]["impuesto"][$j]["valorRetenido"]= number_format($this->suma_imp["valor"][$codigoimp][$codigoporc], 2, '.','');
									$arreglo[$i]["impuestos"]["impuesto"][$j]["codDocSustento"]= $this->suma_imp["codDocSustento"][$codigoimp][$codigoporc];			
									$arreglo[$i]["impuestos"]["impuesto"][$j]["numDocSustento"]= $this->suma_imp["numDocSustento"][$codigoimp][$codigoporc];	
									$arreglo[$i]["impuestos"]["impuesto"][$j]["fechaEmisionDocSustento"]= $this->suma_imp["fechaEmisionDoc"][$codigoimp][$codigoporc];
									$arreglo[$i]["infoCorreo"]["importeTotal"]+= $arreglo[$i]["impuestos"]["impuesto"][$j]["valorRetenido"];	$j++;
							endfor;
					endfor;	
															$j=0;
					if(trim($row->direccion_pto!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Direccion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->direccion_pto;		$j++;
					endif;
					if(trim($row->tlf!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Telefono");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->tlf;				$j++;
					endif;
					if(trim($row->email!="")):				$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Email");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= $row->email;				$j++;
					endif;
					if(trim($row->observacion!="")):		$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@attributes"]= array("nombre" => "Observacion");		
															$arreglo[$i]["infoAdicional"]["campoAdicional"][$j]["@value"]= preg_replace( "/\r|\n/", "", $row->observacion);		$j++;
					endif; $i++;
		endforeach;	return $arreglo;
	}
}
