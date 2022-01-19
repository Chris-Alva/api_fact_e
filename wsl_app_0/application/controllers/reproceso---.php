<?
class reproceso extends CI_Controller{
	var $tipo_doc= "";		var $n_tipo_doc= "";	var $ruta_app= "";	var $arr_parametros= array();
	
    function __construct(){		parent::__construct();		}
    function index(){			$this->FuncionInicial();	}
    
    function FuncionInicial(){					$this->ruta_app = $_SERVER['DOCUMENT_ROOT'].str_replace(basename($_SERVER['SCRIPT_NAME']),"",$_SERVER['SCRIPT_NAME']);
												$dsn = $this->db->dbdriver."://".$this->db->username.":".$this->db->password."@".$this->db->hostname."/".$this->uri->segment(3);
												$this->db = $this->load->database($dsn, TRUE);
		if(!$this->proceso_model->ValidaUso()):	$this->proceso_model->log_errores("USO APP", "NO TIENE DERECHO DE USO DE LA APLICACION");	die();
		endif;									
												$query= $this->db->query("SELECT * FROM xml_parametros_generales");
		foreach($query->result() as $row):		$this->arr_parametros[$row->n_parametro]= $row->valor;
		endforeach;	
												$query= $this->db->query("SELECT * FROM xml_tipo_comprobante WHERE estado=1 ORDER BY codigo");
		foreach($query->result() as $row):		$this->tipo_doc= $row->codigo;	$this->n_tipo_doc= $row->descripcion;
												$this->arr_parametros["smtp_email_from_".$row->codigo]= $row->smtp_email_from;
												$this->arr_parametros["smtp_email_bcc_".$row->codigo]= $row->smtp_email_bcc;
		endforeach;
	}

    function reproceso_info_comp(){										$this->FuncionInicial();	require($this->ruta_app."system/libraries/XML2Array.php");
		$datos_emp= $this->querys_model->GetDatosEmpresa();				$ambiente= "PRUEBAS";		
		if($this->arr_parametros["tipo_ambiente"]=="2"):				$ambiente= "PRODUCCION";
		endif;															$ruta_xml= $this->ruta_app.$this->arr_parametros["directorio_local_xml"];		
																		$ruta_xml= str_replace(array('$ambiente', '$ruc_empresa'), array($ambiente, $datos_emp["ruc"]), $ruta_xml);		
		
		$consulta= $this->db->query("select * from xml_comprobante where coalesce(autorizacion, '')=''");							
		foreach($consulta->result() as $row):							$tipo_doc= $row->tipo_doc;			$res= $row->tipo_doc."_".$row->prefijo1."_".$row->prefijo2."_".$row->secuencia.".xml";
				$contenido_xml= utf8_encode(file_get_contents($ruta_xml."/".$res));							$datos = XML2Array::createArray($contenido_xml);				
				if(isset($datos["RespuestaAutorizacionComprobante"])):	$datos= $datos["RespuestaAutorizacionComprobante"];
																		$datos_xml= XML2Array::createArray($datos["autorizaciones"]["autorizacion"]["comprobante"]["@cdata"]);
																		$datos_xml= $this->etiqueta_comprobante_xml($tipo_doc, $datos_xml);
																		unset($datos["autorizaciones"]["autorizacion"]["comprobante"]);			
																		$datos= array_merge($datos, $datos_xml);
				else:													$datos= $this->etiqueta_comprobante_xml($tipo_doc, $datos);
				endif;													$datos_aut= $datos["autorizaciones"]["autorizacion"];
																		$datos_adic= $datos["infoAdicional"]["campoAdicional"];
																		
				if(isset($datos["infoFactura"])):						$infoComp= $datos["infoFactura"];			$tercero= $infoComp["identificacionComprador"];
				elseif(isset($datos["infoNotaCredito"])):				$infoComp= $datos["infoNotaCredito"];		$tercero= $infoComp["identificacionComprador"];
				elseif(isset($datos["infoGuiaRemision"])):				$infoComp= $datos["infoGuiaRemision"];		$tercero= $infoComp["destinatarios"]["destinatario"]["identificacionDestinatario"];		$infoComp["fechaEmision"]= $infoComp["fechaIniTransporte"];
				elseif(isset($datos["infoCompRetencion"])):				$infoComp= $datos["infoCompRetencion"];		$tercero= $infoComp["identificacionSujetoRetenido"];
				endif;													$envio_correo= 0;	
				for($j=0; $j<count($datos_adic); $j++):
						if($datos_adic[$j]["@attributes"]["nombre"]=="Email"):		$address= str_replace(';',',',$datos_adic[$j]["@value"]);		$array_address= explode(",", $address);
								for($k=0; $k<count($array_address); $k++){			$email= trim($array_address[$k]);
									if($this->proceso_model->valida_mail($email)){	$envio_correo= 1;	}		
								}
						endif;
				endfor;
				list($dia, $mes, $ano)= explode("/", $infoComp["fechaEmision"]);							$fecha_emision=date("Y-m-d", mktime(0,0,0,$mes, $dia,$ano));		
				$xml_comprobante= array(	"tercero"		=>	$tercero,									"fecha_emision"		=>	$fecha_emision,
											"clave_acceso"	=> 	$datos["infoTributaria"]["claveAcceso"],	"total"				=>	$infoComp["importeTotal"],
											"autorizacion"	=>	$datos_aut["numeroAutorizacion"],			"fecha_autorizacion"=>	$datos_aut["fechaAutorizacion"],
											"estado"		=>	$datos_aut["estado"],						"ambiente"			=>	$ambiente,
											"contenido_xml"	=>	utf8_decode($contenido_xml),				"envio_correo"		=> 	$envio_correo);
				$this->db->where("tipo_doc", $datos["infoTributaria"]["codDoc"]);
				$this->db->where("prefijo1", $datos["infoTributaria"]["estab"]);
				$this->db->where("prefijo2", $datos["infoTributaria"]["ptoEmi"]);
				$this->db->where("secuencia", $datos["infoTributaria"]["secuencial"]);
				$this->db->update("xml_comprobante", $xml_comprobante);		//print_r($xml_comprobante);	return;
		endforeach;
	}
    function etiqueta_comprobante_xml($tipo_doc, $datos){
		switch($tipo_doc):
				case '01':		$datos= $datos["factura"];				break;
				case '02':		$datos= $datos["notaVenta"];			break;
				case '04':		$datos= $datos["notaCredito"];			break;
				case '05':		$datos= $datos["notaDebito"];			break;
				case '06':		$datos= $datos["guiaRemision"];			break;
				case '07':		$datos= $datos["comprobanteRetencion"];	break;
				default:		break;
		endswitch;				unset($datos["ds:Signature"]);			return $datos;
	}

    function reprocesar_comprobantes(){			$this->FuncionInicial();
												$this->tipo_doc="01";	$query= $this->querys_model->GetDatosFactura();					//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_factura/".$this->db->database."/".$row->prefijo1."-".$row->prefijo2."-".$row->secuencia);	//break;
		endforeach;								echo "<p>Facturas de Fragata reprocesadas !</p>";
		/*										$this->tipo_doc="01";	$query= $this->querys_model->GetDatosFactura('', "SIIS");		//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_factura/".$this->db->database."/".$row->prefijo."-".$row->factura_fiscal."/SIIS");
		endforeach;								echo "<p>Facturas de SIIS reprocesadas !</p>";
												$this->tipo_doc="06";	$query= $this->querys_model->GetDatosGuiaRemision();			//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_guia/".$this->db->database."/".$row->prefijo1."-".$row->prefijo2."-".$row->secuencia);	//break;
		endforeach;								echo "<p>Guías de Remisión reprocesadas !</p>";
		*/										$this->tipo_doc="04";	$query= $this->querys_model->GetDatosNotaCredito();				//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_nc/".$this->db->database."/".$row->prefijo1."-".$row->prefijo2."-".$row->sec_nc);		//break;
		endforeach;								echo "<p>NC de Fragata reprocesadas !</p>";
		/*										$this->tipo_doc="04";	$query= $this->querys_model->GetDatosNotaCredito('', "SIIS");	//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_nc/".$this->db->database."/".$row->id."/SIIS");
		endforeach;								echo "<p>NC de SIIS reprocesadas !</p>";
		*/										$this->tipo_doc="07";	$query= $this->querys_model->GetDatosRetencion();				//print_r($this->db->last_query());	echo "________";
		foreach($query->result() as $row):		shell_exec("/usr/bin/curl -k ".site_url()."/proceso/generar_retencion/".$this->db->database."/".$row->prefijo1."-".$row->prefijo2."-".$row->secuencia);	//break;
		endforeach;								echo "<p>Retenciones reprocesadas !</p>";
	}
}
