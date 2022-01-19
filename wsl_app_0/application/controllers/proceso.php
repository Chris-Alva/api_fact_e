<?
class proceso extends CI_Controller{
	var $tipo_doc= "";		var $n_tipo_doc= "";	var $ruta_app= "";	var $arr_parametros= array();
	
    function __construct(){		parent::__construct();		}
    function index(){			$this->FuncionInicial();	}
    
    function FuncionInicial(){					$this->ruta_app = $_SERVER['DOCUMENT_ROOT'].str_replace(basename($_SERVER['SCRIPT_NAME']),"",$_SERVER['SCRIPT_NAME']);
												$dsn = $this->db->dbdriver."://".$this->db->username.":".$this->db->password."@".$this->db->hostname."/".$this->uri->segment(3)."?char_set=latin1&dbcollat=latin1_general_ci";
												$this->db = $this->load->database($dsn, TRUE);		$this->portal = $this->load->database("portal", TRUE);
		if(!$this->proceso_model->ValidaUso()):	$this->proceso_model->log_errores("USO APP", "NO TIENE DERECHO DE USO DE LA APLICACION");	die();
		endif;									
												$query= $this->db->query("set client_encoding to 'utf8'");
												$query= $this->db->query("SELECT * FROM xml_parametros_generales");
		foreach($query->result() as $row):		$this->arr_parametros[$row->n_parametro]= $row->valor;
		endforeach;	
												$cons_where= "and funcion IS NOT NULL";				$funcion_origen= $this->uri->segment(2);
		if($funcion_origen!=""):				$cons_where= "and funcion='$funcion_origen'";
		endif;
												$query= $this->db->query("SELECT * FROM xml_tipo_comprobante WHERE estado=1 $cons_where ORDER BY codigo");
		foreach($query->result() as $row):		$funcion= $row->funcion;	$this->tipo_doc= $row->codigo;		$this->n_tipo_doc= $row->descripcion;
												$this->arr_parametros["smtp_email_from_".$row->codigo]= $row->smtp_email_from;
												$this->arr_parametros["smtp_email_bcc_".$row->codigo]= $row->smtp_email_bcc;
					if($funcion_origen=="")		$this->$funcion();
		endforeach;
	}

    function generar_factura($db, $num='', $sys=''){		$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosFactura($num, $sys), 'factura');    				}
    function generar_nota_venta($db, $num='', $sys=''){		$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosNotaVenta($num, $sys), 'notaVenta');    			}
    function generar_nc($db, $num='', $sys=''){				$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosNotaCredito($num, $sys), 'notaCredito');   		}
    function generar_nd($db, $num='', $sys=''){				$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosNotaDebito($num, $sys), 'notaDebito');    		}
    function generar_guia($db, $num='', $sys=''){			$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosGuiaRemision($num, $sys), 'guiaRemision');   		}
    function generar_retencion($db, $num='', $sys=''){		$this->FuncionInicial();	$this->gestion_xml($this->proceso_model->DatosRetencion($num, $sys), 'comprobanteRetencion');   }

	function gestion_xml($arr_datos, $tag){			$datos_emp= $this->querys_model->GetDatosEmpresa();					
		for($i=0; $i<count($arr_datos); $i++):		$infoTributaria= $arr_datos[$i]["infoTributaria"];
				$nombre_archivo= $infoTributaria["codDoc"]."_".$infoTributaria["estab"]."_".$infoTributaria["ptoEmi"]."_".$infoTributaria["secuencial"];
				$this->proceso_model->ManejoXml("$nombre_archivo.xml", $arr_datos[$i], $tag);				$infoCorreo= $arr_datos[$i]["infoCorreo"];
				
									$WSSRI= $this->proceso_model->WSSRI($infoTributaria["claveAcceso"]);
				if($WSSRI):			$this->proceso_model->actualiza_comprobante_declarado($infoCorreo);		$this->proceso_model->generar_pdf("$nombre_archivo.pdf", $arr_datos[$i]);	
									$result_copy= $this->proceso_model->copia_archivos_ftp();				$this->proceso_model->envio_correo($infoCorreo);
				endif;	break;
		endfor;
	}
}
