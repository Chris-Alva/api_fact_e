<?
class querys_model extends CI_Model
{
    function __construct(){
    	parent::__construct();
    }
    
    function GetDatosEmpresa(){
		$consulta = $this->db->query("SELECT * FROM financiero.admempresa WHERE empresa = 1");
		return $consulta->row_array();
	}    
    
    function GetDatosImpuesto($cons_where){
		$consulta = $this->db->query("SELECT * FROM xml_impuesto WHERE id_impuesto IS NOT NULL $cons_where");
		return $consulta->row_array();
	}    
    
    function GetDatosTipoComprobante($cons_where){
		$consulta = $this->db->query("SELECT * FROM xml_tipo_comprobante WHERE codigo IS NOT NULL $cons_where");
		return $consulta->row_array();
	}
	
    function GetDatosFactura($numeracion='', $sistema=''){
		if($sistema=="SIIS"):
				$consulta= null;
				if($numeracion!=''):		$cons_where= "and f.prefijo||'-'||f.factura_fiscal = '$numeracion'";
				endif;
				
				$consulta_datos_factura = $this->db->query("SELECT f.* 
				FROM public.fac_facturas f
				WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' $cons_where");
				
				if ($consulta_datos_factura->num_rows() > 0):		$datos_factura = $consulta_datos_factura->row();
				endif;
				
				if ($this->GetDatosSecuenciaFacturaSiis($numeracion)):
				if ($datos_factura->tipo_factura!="4"): 
					$consulta= $this->db->query("SELECT f.prefijo||'-'||f.factura_fiscal as id, 
					xml.clave_acceso, 
					xml.estado, 
					xml.envio_correo, 
					tc.tipo_id_tercero AS tipo_identif, 
					tc.tercero_id AS identif, 
					tc.nombre_tercero AS n_cliente, 
					tc.direccion, 
					to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, 
					to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, 
					f.prefijo, 
					f.factura_fiscal, 
					f.fe_prefijo1 as prefijo1, 
					f.fe_prefijo2 as prefijo2, 
					f.fe_secuencia as secuencia, 
					--abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0)*0.12) AS totalsinimp,
					--CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora)-(coalesce(fb.base_imponible, 0)*0.12)
						--WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora)-(coalesce(fb.base_imponible, 0)*0.12) 
						--ELSE abs(f.valor_cargos)-(coalesce(fb.base_imponible, 0)*0.12) 
						--END AS totalsinimp,
					CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora-f.descuento)-(coalesce(fb.base_imponible, 0) * 0.12)
						WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0) * 0.12) 
						ELSE abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0)*0.12) 
						END AS totalsinimp,
					CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cuota_paciente)
						WHEN f.tipo_factura=1 THEN abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)
						ELSE 0
					END AS descuento_adicional,
					CASE WHEN f.tipo_factura=0 AND abs(f.valor_cuota_paciente)>0 THEN 'CUOTA PACIENTE'
						WHEN f.tipo_factura=1 AND abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)>0 THEN UPPER(pl. 	nombre_cuota_moderadora) 
						ELSE UPPER(pl.nombre_cuota_moderadora) 
					END AS n_descuento_adicional, 
					abs(f.descuento) as totaldcto, 
					CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos + f.gravamen + f.valor_cuota_moderadora + f.valor_cuota_paciente - f.descuento)
						WHEN f.tipo_factura=1 THEN abs(f.valor_cargos + f.gravamen - f.valor_cuota_moderadora - f.valor_cuota_paciente - f.descuento)
						ELSE abs(f.valor_cargos + f.gravamen + f.valor_cuota_moderadora + f.valor_cuota_paciente - f.descuento)
					END AS totalconimp, 
					f.tipo_factura, 
					0 as propina, 
					tc.direccion as direccion_pto, 
					tc.telefono as tlf, 
					tc.email, 
					f.observacion, 
					ig.ingreso as ingreso, 
					ct.numerodecuenta as cuenta, 
					hptc.nombre_tercero as profesional_tratante, 
					e.descripcion as profesional_especialidad, 
					(trim(p.primer_nombre)||' '||trim(p.segundo_nombre)||' '||trim(p.primer_apellido)||' '||trim(p.segundo_apellido)) as n_paciente, 
					(p.tipo_id_paciente||' '||p.paciente_id) as hc_paciente, 
					to_char(ig.fecha_registro,'DD/MM/YYYY HH24:MI') as fechaingreso, 
					coalesce(to_char(igs.fecha_registro,'DD/MM/YYYY HH24:MI'),to_char(now(),'DD/MM/YYYY HH24:MI')) as fechasalida, 
					su.nombre as usuario, pl.dias_credito_cartera as dias_credito, 
					to_char((f.fecha_registro+cast(cast(pl.dias_credito_cartera as char(2))||' days' as interval)),'DD/MM/YYYY') as fechavencimiento 
					FROM public.fac_facturas f 
					LEFT JOIN public.system_usuarios su on su.usuario_id=f.usuario_id 
					LEFT JOIN public.fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal 
					LEFT JOIN public.fac_facturas_cuentas fc on fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal 
					LEFT JOIN public.planes pl on pl.plan_id=f.plan_id 
					LEFT JOIN public.cuentas ct on ct.numerodecuenta=fc.numerodecuenta 
					LEFT JOIN public.ingresos ig on ig.ingreso=ct.ingreso 
					LEFT JOIN public.ingresos_salidas igs on igs.ingreso=ct.ingreso 
					LEFT JOIN public.hc_profesional_tratante hpt on hpt.ingreso=ct.ingreso 
					LEFT JOIN public.profesionales_especialidades pe on pe.tipo_id_tercero=hpt.tipo_id_tercero and pe.tercero_id=hpt.tercero_id 
					LEFT JOIN public.especialidades e on e.especialidad=pe.especialidad 
					LEFT JOIN public.terceros as hptc on hptc.tipo_id_tercero=hpt.tipo_id_tercero and hptc.tercero_id=hpt.tercero_id 
					LEFT JOIN public.pacientes p on p.tipo_id_paciente=ig.tipo_id_paciente and p.paciente_id=ig.paciente_id 
					LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
					JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id 
					WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' 
					AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') 
					AND (f.estado IN (0,1) 
					OR  (f.estado=3 AND row(f.empresa_id, f.prefijo, f.factura_fiscal) IN (SELECT empresa_id, prefijo_factura, factura_fiscal FROM public.notas_credito))) 
					$cons_where 
					ORDER BY f.fecha_registro, f.fe_prefijo1, f.fe_prefijo2, f.fe_secuencia");

				else:
					$consulta= $this->db->query("SELECT f.prefijo||'-'||f.factura_fiscal as id, 
					xml.clave_acceso, 
					xml.estado, 
					xml.envio_correo, 
					tc.tipo_id_tercero AS tipo_identif, 
					tc.tercero_id AS identif, 
					tc.nombre_tercero AS n_cliente, 
					tc.direccion, 
					to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, 
					to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, 
					f.prefijo, 
					f.factura_fiscal, 
					f.fe_prefijo1 as prefijo1, 
					f.fe_prefijo2 as prefijo2, 
					f.fe_secuencia as secuencia, 
					abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0)*0.12) AS totalsinimp,
					CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cuota_paciente)
						WHEN f.tipo_factura=1 THEN abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)
						ELSE 0
					END AS descuento_adicional,
					CASE WHEN f.tipo_factura=0 AND abs(f.valor_cuota_paciente)>0 THEN 'CUOTA PACIENTE'
						WHEN f.tipo_factura=1 AND abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)>0 THEN pl.nombre_cuota_moderadora 
						ELSE ''
					END AS n_descuento_adicional, abs(f.descuento) as totaldcto, 
					CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora+f.valor_cuota_paciente-f.descuento)
						WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora-f.valor_cuota_paciente-f.descuento)
						ELSE abs(f.valor_cargos-f.descuento)
					END AS totalconimp, 
					f.tipo_factura, 
					0 as propina, 
					tc.direccion as direccion_pto, 
					tc.telefono as tlf, 
					tc.email, 
					f.observacion, 
					0 as ingreso, 
					0 as cuenta, 
					'' as profesional_tratante, 
					'' as profesional_especialidad, 
					'' as n_paciente, 
					'' as hc_paciente, 
					'' as fechaingreso, 
					'' as fechasalida, 
					su.nombre as usuario, 
					pl.dias_credito_cartera as dias_credito, 
					to_char((f.fecha_registro+cast(cast(pl.dias_credito_cartera as char(2))||' days' as interval)),'DD/MM/YYYY') as fechavencimiento 
					FROM public.fac_facturas f 
					LEFT JOIN public.planes pl on pl.plan_id=f.plan_id 
					LEFT JOIN public.system_usuarios su on su.usuario_id=f.usuario_id 
					LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
					LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
					JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
					WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' 
					AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') 
					AND (f.estado IN (0,1) OR (f.estado=3 AND row(f.empresa_id, f.prefijo, f.factura_fiscal) IN (SELECT empresa_id, prefijo_factura, factura_fiscal FROM public.notas_credito))) 
					$cons_where 
					ORDER BY f.fecha_registro, f.fe_prefijo1, f.fe_prefijo2, f.fe_secuencia");
					
				endif;
				endif;
				
		else:	if($numeracion!=''):		$cons_where= "and f.prefijo1||'-'||f.prefijo2||'-'||f.factura_fiscal = '$numeracion'";
				endif;
				$consulta= $this->db->query("SELECT f.factura_id as id, 
				xml.clave_acceso, 
				xml.estado, 
				xml.envio_correo, 
				t.tipo_id_tercero AS tipo_identif, 
				t.tercero_id AS identif, 
				t.nombre_tercero AS n_cliente, 
				t.direccion, 
				to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, 
				to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, 
				f.prefijo1, 
				f.prefijo2, 
				f.factura_fiscal as secuencia, 
				CASE WHEN f.base_imponible > 0 THEN f.base_imponible
				ELSE f.subtotal END as totalsinimp,
				0 as descuento_adicional,
				'' as n_descuento_adicional,
				f.total_descuentos as totaldcto, 
				f.total_factura as totalconimp, 
				0 as tipo_factura, 
				0 as propina, 
				t.direccion as direccion_pto, 
				t.telefono1 as tlf, 
				t.email, 
				f.notas as observacion, 
				f.ordcompra_cliente,
				gr.serie||'-'||lpad(gr.secuencia,9,0) as guia_remision, f.forma_pago, f.dias_credito||' días' as dias_credito, vd.nombre as vendedor, (trim(ap.n_persona)||' '||trim(ap.a_paterno)||' '||trim(ap.a_materno)) as usuario 
				--tcd.direccion_envio as direccion_pto, tcd.telefono_contacto as tlf, tcd.email_contacto as email
				FROM financiero.fct_factura f 
				JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = f.cliente_id 
				JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id 
				LEFT JOIN financiero.fct_vendedores vd ON  vd.vendedor_id = f.vendedor_id 
				LEFT JOIN financiero.fct_factura_guia fg ON  fg.fct_id = f.factura_id 
				LEFT JOIN financiero.fct_guia_remision gr ON  gr.guia_id = fg.guia_id 
				LEFT JOIN financiero.admpersona ap on ap.persona = f.usuario_id 
				LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.prefijo1 AND xml.prefijo2=f.prefijo2 AND xml.secuencia=f.factura_fiscal
				--LEFT JOIN financiero.terceros_clientes_direccion tcd ON  tcd.id = f.fct_direccion_envio
				WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' and f.tipo_comprobante_id=1 AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') AND 
				(f.estado!=0 OR (f.estado=0 and factura_id IN (SELECT fct_id from financiero.fct_nota_credito where estado!=0 and fecha_emision>=f.fecha_emision))) $cons_where
				ORDER BY f.fecha_emision, f.prefijo1, f.prefijo2, f.factura_fiscal");
		endif; return $consulta;
	}
	
    function GetDatosFacturaDetalle($factura_id, $tipo_factura, $sistema=''){
		if($sistema=="SIIS"):
				if($tipo_factura!="4"):
						$consulta= $this->db->query("(SELECT cd.tarifario_id||'-'||cca.descripcion as det_id, cd.tarifario_id as codigoprincipal, '000' as codigoauxiliar, cca.descripcion, 1 as cantidad, 
						CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto)
							WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto)
							ELSE sum(cd.valor_cargo)
						END AS precio, 
						CASE WHEN f.tipo_factura=0 THEN sum(abs(cd.valor_descuento_paciente))
							WHEN f.tipo_factura=1 THEN sum(abs(cd.valor_descuento_empresa))
							ELSE sum(abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
						END AS descuento, 
						--CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto)
						--	WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto)
						--	ELSE sum(cd.valor_cargo)
						--END AS totalsinimp
						CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto - abs(cd.valor_descuento_paciente))
							WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto - abs(cd.valor_descuento_empresa))
							ELSE sum(cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
						END AS totalsinimp
						FROM public.fac_facturas f
						JOIN public.fac_facturas_cuentas fc ON fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal
						JOIN public.cuentas_detalle cd ON cd.numerodecuenta=fc.numerodecuenta and cd.facturado=1
						JOIN public.tarifarios_detalle td on td.tarifario_id=cd.tarifario_id and td.cargo=cd.cargo
						JOIN public.grupos_tipos_cargo gtc on gtc.grupo_tipo_cargo=td.grupo_tipo_cargo
						JOIN public.cuentas_codigos_agrupamiento cca on cca.codigo_agrupamiento_id=gtc.codigo_agrupamiento_id
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id' AND ((f.tipo_factura=0 AND cd.valor_nocubierto!=0) OR (f.tipo_factura=1 AND cd.valor_cubierto!=0) OR (f.tipo_factura>1 AND cd.valor_cargo!=0))
						GROUP BY cd.tarifario_id, cca.descripcion, f.tipo_factura
						ORDER BY totalsinimp DESC)
						
						UNION ALL
						
						(SELECT 'SYS-FEE HOSPITALARIO' as det_id, '000' as codigoprincipal, '000' as codigoauxiliar, 
						pl.nombre_cuota_moderadora as descripcion, 1 as cantidad, 
						f.valor_cuota_moderadora as precio, 0 as descuento, f.valor_cuota_moderadora as totalsinimp 
						FROM public.fac_facturas f 
						LEFT JOIN public.planes pl on pl.plan_id=f.plan_id 
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id' 
						AND (f.tipo_factura IN ('0','2') AND f.valor_cuota_moderadora!=0))");
				else:		
						$consulta= $this->db->query("SELECT f.prefijo||'-'||f.factura_fiscal||'-1' as det_id, '000' as codigoprincipal, '000' as codigoauxiliar, f.concepto as descripcion, 1 as cantidad, 
						fb.base_imponible AS precio, 0 as descuento, fb.base_imponible AS totalsinimp
						FROM public.fac_facturas f
						JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
						
						UNION ALL 
						
						select f.prefijo||'-'||f.factura_fiscal||'-2' as det_id, '000' as codigoprincipal, '000' as codigoauxiliar, f.concepto as descripcion, 1 as cantidad, 
						CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							ELSE abs(f.valor_cargos)-(coalesce(fb.base_imponible, 0)*1.12)
						END AS precio, abs(f.descuento) as descuento, 
						CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							ELSE abs(f.valor_cargos)-(coalesce(fb.base_imponible, 0)*1.12)
						END AS totalsinimp
						FROM public.fac_facturas f
						LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
						ORDER BY totalsinimp DESC");
				endif;
		else:	$consulta= $this->db->query("select cd.cuenta_id||'-'||cd.cta_det_id as det_id, coalesce(it.codigo_empresa, '001') as codigoprincipal, it.codigo_item as codigoauxiliar, 
				CASE WHEN cd.descripcion!='' THEN cd.descripcion
					ELSE it.descripcion
				END AS descripcion, cd.cantidad, 
				cd.precio, cd.cantidad*cd.precio*((cd.descuento)/100) as descuento, cd.cantidad*(cd.precio*((100-cd.descuento)/100)) as totalsinimp FROM financiero.fct_factura f
				JOIN financiero.fct_cuenta_detalle cd ON cd.cuenta_id= f.cuenta_id
				JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
				LEFT JOIN financiero.fct_cuenta_detalle_impuesto cdi ON cdi.cta_detalle_id= cd.cta_det_id
				LEFT JOIN financiero.adm_imptos i on i.impuesto=cdi.impuesto_id and i.codigo_sri!='332'
				WHERE f.factura_id='$factura_id'");
		endif;	return $consulta;
	}
	
    function GetDatosFacturaDetalleImp($factura_id, $tipo_factura, $sistema=''){
		if($sistema=="SIIS"):
				if($tipo_factura!="4"):
						$consulta= $this->db->query("(SELECT cd.tarifario_id||'-'||cca.descripcion as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						--CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto - abs(cd.valor_descuento_paciente))
						--	WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto - abs(cd.valor_descuento_empresa))
						--	ELSE sum(cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
						--END AS base_imponible, 
						CASE WHEN f.tipo_factura=0 OR f.tipo_factura=2 THEN sum(cd.valor_nocubierto - abs(cd.valor_descuento_paciente))
							WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto - abs(cd.valor_descuento_empresa))
							ELSE sum(cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
						END AS base_imponible,
						--CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto)
						--	WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto)
						--	ELSE sum(cd.valor_cargo)
						--END AS base_imponible, 
						--CASE WHEN f.tipo_factura=0 THEN sum((cd.valor_nocubierto - abs(cd.valor_descuento_paciente))*(cd.porcentaje_gravamen_paciente/100))
						--	WHEN f.tipo_factura=1 THEN sum((cd.valor_cubierto - abs(cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
						--	ELSE sum((cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
						--END AS valor
						CASE WHEN f.tipo_factura=0 OR f.tipo_factura=2 THEN sum((cd.valor_nocubierto - abs(cd.valor_descuento_paciente))*(cd.porcentaje_gravamen_paciente/100))
							WHEN f.tipo_factura=1 THEN sum((cd.valor_cubierto - abs(cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
							ELSE sum((cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
						END AS valor
						--CASE WHEN f.tipo_factura=0 THEN sum((cd.valor_nocubierto)*(cd.porcentaje_gravamen_paciente/100))
						--	WHEN f.tipo_factura=1 THEN sum((cd.valor_cubierto)*(cd.porcentaje_gravamen/100))
						--	ELSE sum((cd.valor_cargo)*(cd.porcentaje_gravamen/100))
						--END AS valor
						FROM public.fac_facturas f
						JOIN public.fac_facturas_cuentas fc ON fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal
						JOIN public.cuentas_detalle cd ON cd.numerodecuenta=fc.numerodecuenta and cd.facturado=1
						JOIN public.tarifarios_detalle td on td.tarifario_id=cd.tarifario_id and td.cargo=cd.cargo
						JOIN public.grupos_tipos_cargo gtc on gtc.grupo_tipo_cargo=td.grupo_tipo_cargo
						JOIN public.cuentas_codigos_agrupamiento cca on cca.codigo_agrupamiento_id=gtc.codigo_agrupamiento_id
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje=coalesce(cd.porcentaje_gravamen, 0)
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id' AND ((f.tipo_factura=0 AND cd.valor_nocubierto!=0) OR (f.tipo_factura=1 AND cd.valor_cubierto!=0) OR (f.tipo_factura>1 AND cd.valor_cargo!=0)) 
						GROUP BY cd.tarifario_id, cca.descripcion, i.ref_impuesto, i.id_porcentaje, i.porcentaje, f.tipo_factura
						ORDER BY base_imponible DESC)
						
						UNION ALL 
						
						(SELECT 'SYS-FEE HOSPITALARIO' as det_id, i.ref_impuesto as codigoimp, 
						i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						f.valor_cuota_moderadora as base_imponible, 0 as valor 
						FROM public.fac_facturas f 
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00' 
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id' 
						AND (f.tipo_factura IN ('0','2') AND f.valor_cuota_moderadora!=0))"); 
				else:		
						$consulta= $this->db->query("select f.prefijo||'-'||f.factura_fiscal||'-1' as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						fb.base_imponible, fb.base_imponible*0.12 AS valor
						FROM public.fac_facturas f
						JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
						JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='12.00'
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
						
						UNION ALL 
						
						select f.prefijo||'-'||f.factura_fiscal||'-2' as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora-f.valor_cuota_paciente)-(coalesce(fb.base_imponible, 0)*1.12)
							ELSE abs(f.valor_cargos)-(coalesce(fb.base_imponible, 0)*1.12)
						END AS base_imponible, 0 AS valor
						FROM public.fac_facturas f
						LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00'
						WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'");
				endif;
		else:	$consulta= $this->db->query("select cd.cuenta_id||'-'||cd.cta_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cdi.impuesto_valor_generado as valor 
				FROM financiero.fct_factura f
				JOIN financiero.fct_cuenta_detalle cd ON cd.cuenta_id= f.cuenta_id
				JOIN financiero.fct_cuenta_detalle_impuesto cdi ON cdi.cta_detalle_id= cd.cta_det_id and cdi.impuesto_porcentaje>0
				LEFT JOIN xml_impuesto i on i.id_impuesto= cdi.impuesto_id and i.porcentaje=cdi.impuesto_porcentaje
				WHERE f.factura_id='$factura_id'
				
				UNION ALL
				
				select cd.cuenta_id||'-'||cd.cta_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, 0 as valor 
				FROM financiero.fct_factura f
				JOIN financiero.fct_cuenta_detalle cd ON cd.cuenta_id= f.cuenta_id
				LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='0.00'
				WHERE f.factura_id='$factura_id' AND cd.cta_det_id NOT IN (SELECT cta_detalle_id FROM financiero.fct_cuenta_detalle_impuesto WHERE cuenta_id=f.cuenta_id and impuesto_porcentaje>0)");
		endif;	return $consulta;
	}
	
    function GetDatosGuiaRemision($numeracion='', $sistema=''){
				if($numeracion!=''):		$cons_where= "and f.serie||'-'||lpad(f.secuencia, 9, 0) = '$numeracion'";
				endif;
				$consulta= $this->db->query("SELECT f.guia_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, t.tipo_id_tercero AS tipo_identif, t.tercero_id AS identif, t.nombre_tercero AS n_cliente, t.direccion,
				to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, substring(f.serie, 1, 3) as prefijo1, substring(f.serie, 5, 3) as prefijo2, lpad(f.secuencia, 9, 0) as secuencia,
				to_char(f.traslado_finicio,'DD/MM/YYYY') AS traslado_finicio, to_char(f.traslado_ffin,'DD/MM/YYYY') AS traslado_ffin, f.transportista, f.ruc_transportista, f.placa_transportista, f.direccion_transportista as destino, f.motivo,
				xfc.tipo_doc as mod_tipo_doc, fct.prefijo1||'-'||fct.prefijo2||'-'||fct.factura_fiscal AS mod_numeracion, xfc.autorizacion as mod_autorizacion, to_char(fct.fecha_emision,'DD/MM/YYYY') AS mod_fechaemision, t.direccion as direccion_pto, t.telefono1 as tlf, t.email, fct.notas as observacion
				FROM financiero.fct_guia_remision f
				LEFT JOIN financiero.fct_factura_guia fg ON  fg.guia_id = f.guia_id
				LEFT JOIN financiero.fct_factura fct ON  fct.factura_id = fg.fct_id
				LEFT JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = fct.cliente_id
				LEFT JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id
				LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=substring(f.serie, 1, 3) AND xml.prefijo2=substring(f.serie, 5, 3) AND xml.secuencia=lpad(f.secuencia, 9, 0)
				LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.prefijo1 AND xfc.prefijo2=fct.prefijo2 AND xfc.secuencia=fct.factura_fiscal 
				WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
				ORDER BY f.fecha_emision, prefijo1, prefijo2, secuencia");
				return $consulta;
	}
	
    function GetDatosGuiaRemisionDetalle($guia_id, $sistema=''){
				$consulta= $this->db->query("select cd.guia_id as det_id, coalesce(it.codigo_empresa, '001') as codigoprincipal, it.codigo_item as codigoauxiliar, it.descripcion, cd.cantidad
				FROM financiero.fct_guia_remision_detalle cd
				JOIN public.inv_item it ON cd.item_id= it.codigo_item
				WHERE cd.guia_id='$guia_id'");
				return $consulta;
	}
	
    function GetDatosNotaCredito($numeracion='', $sistema=''){
		if($sistema=="SIIS"):
				if($numeracion!=''):		$cons_where1= "and f.prefijo||'-'||f.numero = '$numeracion'";
											$cons_where2= "and f.prefijo||'-'||f.nota_credito_ajuste = '$numeracion'";
											$cons_where3= "and f.prefijo||'-'||f.nota_credito_id = '$numeracion'";
				endif;
				
				if ($this->GetDatosSecuenciaNotaCreditoSiis($numeracion)):
					$consulta= $this->db->query("SELECT f.numero as id, xml.clave_acceso, xml.estado, xml.envio_correo, tc.tipo_id_tercero AS tipo_identif, tc.tercero_id AS identif, tc.nombre_tercero AS n_cliente, tc.direccion, 
					to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, f.fe_prefijo1 as prefijo1, f.fe_prefijo2 as prefijo2, f.fe_secuencia as secuencia, 
					CASE WHEN fct.fe_prefijo1 IS NULL THEN fct.prefijo||'-001-'||lpad(fct.factura_fiscal,9,0)
								ELSE fct.fe_prefijo1||'-'||fct.fe_prefijo2||'-'||fct.fe_secuencia
					END AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_registro,'DD/MM/YYYY') AS mod_fechaemision, tc.direccion as direccion_pto, tc.telefono as tlf, tc.email, f.observacion,
					f.valor_nota AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.valor_nota AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.tipo_factura
					FROM public.notas_contado_credito f
					JOIN public.fac_facturas fct ON f.empresa_id= fct.empresa_id and f.prefijo_factura= fct.prefijo and f.factura_fiscal= fct.factura_fiscal
					JOIN public.terceros tc ON tc.tipo_id_tercero= fct.tipo_id_tercero and  tc.tercero_id= fct.tercero_id
					LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
					LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.fe_prefijo1 AND xfc.prefijo2=fct.fe_prefijo2 AND xfc.secuencia=fct.fe_secuencia
					WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where1
					
					UNION ALL
					
					SELECT f.nota_credito_ajuste as id, xml.clave_acceso, xml.estado, xml.envio_correo, tc.tipo_id_tercero AS tipo_identif, tc.tercero_id AS identif, tc.nombre_tercero AS n_cliente, tc.direccion, 
					to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, f.fe_prefijo1 as prefijo1, f.fe_prefijo2 as prefijo2, f.fe_secuencia as secuencia, 
					CASE WHEN fct.fe_prefijo1 IS NULL THEN fct.prefijo||'-001-'||lpad(fct.factura_fiscal,9,0)
								ELSE fct.fe_prefijo1||'-'||fct.fe_prefijo2||'-'||fct.fe_secuencia
					END AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_registro,'DD/MM/YYYY') AS mod_fechaemision, tc.direccion as direccion_pto, tc.telefono as tlf, tc.email, f.observacion,
					f.total_nota_ajuste AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.total_nota_ajuste AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.tipo_factura
					FROM public.notas_credito_ajuste f
					JOIN public.notas_credito_ajuste_detalle_facturas nca ON nca.empresa_id= f.empresa_id and nca.prefijo= f.prefijo and nca.nota_credito_ajuste= f.nota_credito_ajuste
					JOIN public.fac_facturas fct ON nca.prefijo_factura= fct.prefijo and nca.factura_fiscal= fct.factura_fiscal
					JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
					LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
					LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.fe_prefijo1 AND xfc.prefijo2=fct.fe_prefijo2 AND xfc.secuencia=fct.fe_secuencia
					WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where2
					
					UNION ALL
					
					SELECT f.nota_credito_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, tc.tipo_id_tercero AS tipo_identif, tc.tercero_id AS identif, tc.nombre_tercero AS n_cliente, tc.direccion, 
					to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, f.fe_prefijo1 as prefijo1, f.fe_prefijo2 as prefijo2, f.fe_secuencia as secuencia, 
					CASE WHEN fct.fe_prefijo1 IS NULL THEN fct.prefijo||'-001-'||lpad(fct.factura_fiscal,9,0)
						ELSE fct.fe_prefijo1||'-'||fct.fe_prefijo2||'-'||fct.fe_secuencia
					END AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_registro,'DD/MM/YYYY') AS mod_fechaemision, tc.direccion as direccion_pto, tc.telefono as tlf, tc.email, f.observacion,
					CASE WHEN fct.tipo_factura = '4' THEN (f.valor_nota-fct.gravamen) 
						ELSE f.valor_nota 
					END AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.valor_nota AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.tipo_factura
					FROM public.notas_credito f
					JOIN public.fac_facturas fct ON f.empresa_id= fct.empresa_id and f.prefijo_factura= fct.prefijo and f.factura_fiscal= fct.factura_fiscal
					JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
					LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
					LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.fe_prefijo1 AND xfc.prefijo2=fct.fe_prefijo2 AND xfc.secuencia=fct.fe_secuencia
					WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where3
					ORDER BY prefijo1, prefijo2, secuencia");
				else: $consulta = null;
				endif;
				
		else:	if($numeracion!=''):		$cons_where= "and f.serie||'-'||f.secuencia = '$numeracion'";
				endif;
				$consulta= $this->db->query("SELECT f.ncredito_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, t.tipo_id_tercero AS tipo_identif, t.tercero_id AS identif, t.nombre_tercero AS n_cliente, t.direccion, f.secuencia as sec_nc,
				to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, substring(f.serie, 1, 3) as prefijo1, substring(f.serie, 5, 3) as prefijo2, lpad(f.secuencia, 9, 0) as secuencia, 
				fct.prefijo1||'-'||fct.prefijo2||'-'||fct.factura_fiscal AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_emision,'DD/MM/YYYY') AS mod_fechaemision, t.direccion as direccion_pto, t.telefono1 as tlf, t.email, f.motivo as observacion,
				f.total_nota_credito-f.impuesto AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.total_nota_credito AS totalconimp, 'NC' AS tipo_nc
				FROM financiero.fct_nota_credito f
				JOIN financiero.fct_factura fct ON f.fct_id= fct.factura_id
				JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = f.codigo_cliente
				JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id
				LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=substring(f.serie, 1, 3) AND xml.prefijo2=substring(f.serie, 5, 3) AND xml.secuencia=lpad(f.secuencia, 9, 0)
				WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."'
				AND f.serie IN (SELECT serie from financiero.fct_ptovta_documento where doc_id='4') AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
				ORDER BY f.fecha_emision, prefijo1, prefijo2, secuencia");
		endif; return $consulta;
	}
	
    function GetDatosNotaCreditoDetalle($nota_credito_id, $tipo_nota_credito, $sistema=''){
		if($sistema=="SIIS"):
				if($tipo_nota_credito=="NCC" || $tipo_nota_credito=="NEC"):
						$consulta= $this->db->query("select d.notas_contado_credito_d_id as det_id, d.nota_contado_concepto_id as codigoprincipal, '000' as codigoauxiliar, ncc.descripcion, 1 as cantidad, 
						valor AS precio, 0 AS descuento, valor AS totalsinimp
						FROM public.notas_contado_credito_d d
						JOIN public.notas_contado_conceptos ncc ON d.nota_contado_concepto_id=ncc.nota_contado_concepto_id
						WHERE d.numero='$nota_credito_id'");
				elseif($tipo_nota_credito=="NCE"):		
						$consulta= $this->db->query("select d.nc_ajuste_concepto_id as det_id, d.concepto_id as codigoprincipal, '000' as codigoauxiliar, ncc.descripcion, 1 as cantidad, 
						valor AS precio, 0 AS descuento, valor AS totalsinimp
						FROM public.notas_credito_ajuste_detalle_conceptos d
						JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
						WHERE d.nota_credito_ajuste='$nota_credito_id'");
				elseif($tipo_nota_credito=="NC" || $tipo_nota_credito=="NE"):		
						$consulta= $this->db->query("select d.nota_credito_concepto_id as det_id, d.concepto_id as codigoprincipal, '000' as codigoauxiliar, ncc.descripcion, 1 as cantidad, 
						valor AS precio, 0 AS descuento, valor AS totalsinimp
						FROM public.notas_credito_detalle_conceptos d
						JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
						WHERE d.nota_credito_id='$nota_credito_id'");
				endif;
		else:	$consulta= $this->db->query("select cd.ncredito_det_id as det_id, coalesce(it.codigo_empresa, '001') as codigoprincipal, it.codigo_item as codigoauxiliar, 
				CASE WHEN cd.descripcion!='' THEN cd.descripcion
					ELSE it.descripcion
				END AS descripcion, cd.cantidad, cd.precio, cd.cantidad*cd.precio*((cd.descuento)/100) as descuento, cd.cantidad*(cd.precio*((100-cd.descuento)/100)) as totalsinimp
				FROM financiero.fct_nota_credito_detalle cd
				JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
				WHERE cd.ncredito_id='$nota_credito_id'");
		endif; return $consulta;
	}
	
    function GetDatosNotaCreditoDetalleImp($nota_credito_id, $tipo_nota_credito, $sistema=''){
		if($sistema=="SIIS"):
				if($tipo_nota_credito=="NCC" || $tipo_nota_credito=="NEC"):
						$consulta= $this->db->query("select d.notas_contado_credito_d_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						valor AS base_imponible, 0 AS valor
						FROM public.notas_contado_credito_d d
						JOIN public.notas_contado_conceptos ncc ON d.nota_contado_concepto_id=ncc.nota_contado_concepto_id
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00'
						WHERE d.numero='$nota_credito_id'");
				elseif($tipo_nota_credito=="NCE"):		
						$consulta= $this->db->query("select d.nc_ajuste_concepto_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						valor AS base_imponible, 0 AS valor
						FROM public.notas_credito_ajuste_detalle_conceptos d
						JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00'
						WHERE d.nota_credito_ajuste='$nota_credito_id'");
				elseif($tipo_nota_credito=="NC" || $tipo_nota_credito=="NE"):		
						$consulta= $this->db->query("select d.nota_credito_concepto_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
						valor AS base_imponible, 0 AS valor
						FROM public.notas_credito_detalle_conceptos d
						JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
						LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00'
						WHERE d.nota_credito_id='$nota_credito_id'");
				endif;
		else:	$consulta= $this->db->query("select cd.ncredito_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cd.impuesto as valor 
				FROM financiero.fct_nota_credito_detalle cd
				JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
				LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='12.00'
				WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto>0
				
				UNION ALL
				
				select cd.ncredito_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cd.impuesto as valor 
				FROM financiero.fct_nota_credito_detalle cd
				JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
				LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='0.00'
				WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto=0");
		endif; return $consulta;
	}
	
    function GetDatosRetencion($numeracion='', $sistema=''){
				if($numeracion!=''):		$cons_where= "and r.ret_prefijo1||'-'||r.ret_prefijo2||'-'||r.ret_numero_fiscal = '$numeracion'";
				endif;
				$consulta= $this->db->query("SELECT f.cab_movimiento as id, xml.clave_acceso, xml.estado, xml.envio_correo, p.tipoidtercero AS tipo_identif, p.ruc AS identif, p.proveedor_nombre AS n_cliente, p.direccion, 
				to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, r.ret_prefijo1 as prefijo1, r.ret_prefijo2 as prefijo2, r.ret_numero_fiscal as secuencia, 
				p.direccion as direccion_pto, p.telefono_proveedor as tlf, p.email_proveedor as email, f.concepto as observacion
				FROM financiero.bnpagodoc f
				JOIN financiero.cpcretencion r on r.fac_prefijo1=f.prefijo1 AND r.fac_prefijo2=f.prefijo2 AND r.fac_numero_fiscal=f.factura_fiscal AND r.proveedor=f.proveedor AND r.tipo_doc=f.tipo_doc AND r.estado='A'
				JOIN public.inv_proveedor p on p.proveedor_id=f.proveedor
				LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=r.ret_prefijo1 AND xml.prefijo2=r.ret_prefijo2 AND xml.secuencia=r.ret_numero_fiscal
				WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
				--AND r.ret_prefijo1||'-'||r.ret_prefijo2='001-003'
				ORDER BY f.fecha_emision, r.ret_prefijo1, r.ret_prefijo2, r.ret_numero_fiscal");
				return $consulta;
	}
	
    function GetDatosRetencionDetalle($factura_id, $sistema=''){
				$consulta= $this->db->query("select i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
				CASE 	WHEN cdi.abv_reporte='RET. IVA' THEN 	CASE WHEN coalesce(fr.impuesto_ice, 0)!='0' THEN 	(fr.valor*1.15)*0.12 	ELSE fr.valor*0.12 	END 
						WHEN cdi.codigo_sri='322' THEN 			fr.valor*0.10
						ELSE									fr.valor
				END as base_imponible, d.cod_sri as coddocsustento, f.prefijo1||f.prefijo2||f.factura_fiscal as numdocsustento, to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision
				FROM financiero.bnpagodoc f 
				JOIN financiero.cpcfactura_rubros fr on fr.prefijo1=f.prefijo1 and fr.prefijo2=f.prefijo2 and fr.factura_fiscal=f.factura_fiscal and fr.proveedor=f.proveedor and fr.tipo_doc=f.tipo_doc
				JOIN financiero.admdocumen d on d.documento=fr.tipo_doc
				JOIN financiero.adm_imptos cdi on cdi.impuesto IN (fr.impuesto_rfte, fr.impuesto_riva)
				LEFT JOIN xml_impuesto i on i.id_impuesto= cdi.impuesto and i.porcentaje=coalesce(cdi.porcentaje, 0)
				WHERE f.cab_movimiento='$factura_id'");
				return $consulta;
	}
	
	function GetDatosHabitacionPaciente($cuenta){
		$consulta = $this->db->query("SELECT c.descripcion AS habitacion, c.ubicacion AS ubicacion
					FROM movimientos_habitacion a
					LEFT JOIN camas AS b ON (b.cama = a.cama)
					LEFT JOIN piezas AS c ON (c.pieza = b.pieza)
					WHERE a.numerodecuenta = " . $cuenta . "
					ORDER BY a.fecha_egreso DESC LIMIT 1");
		return $consulta->row_array();
	}
	
	function GetDatosSecuenciaFacturaSiis($numeracion){
		list($prefijo, $factura_fiscal) = explode("-", $numeracion);
		
		$consulta_documento = $this->db->query("SELECT documento_id, substring(texto1, 1, 3) as prefijo1, substring(texto1, 5, 3) as prefijo2 FROM public.documentos WHERE prefijo='".$prefijo."' AND documento_id IN (SELECT valor FROM public.xml_parametros_generales WHERE n_parametro='siis_factura_documento_id')");
		
		if ($consulta_documento->num_rows() > 0) { 
			$documento = $consulta_documento->row();
			
			$actualiza_secuencia_f = $this->db->query("UPDATE public.fac_facturas 
			SET fe_prefijo1='".$documento->prefijo1."', fe_prefijo2='".$documento->prefijo2."', fe_secuencia='".str_pad($factura_fiscal, 9, "0", STR_PAD_LEFT)."' 
			WHERE public.fac_facturas.prefijo='".$prefijo."' 
			AND public.fac_facturas.factura_fiscal=".$factura_fiscal." 
			AND public.fac_facturas.documento_id=".$documento->documento_id);
			
			if ($this->db->affected_rows() > 0) {
				$consulta_secuencia_f = $this->db->query("SELECT maximo FROM ((SELECT MAX(factura_fiscal) AS maximo FROM public.fac_facturas WHERE documento_id=".$documento->documento_id.") UNION ALL (SELECT COALESCE(MAX(CAST(factura_fiscal AS INTEGER)),0) AS maximo FROM financiero.fct_factura WHERE prefijo1='".$documento->prefijo1."' AND prefijo2='".$documento->prefijo2."')) AS f ORDER BY 1 DESC LIMIT 1 OFFSET 0");
				
				$secuencia = $consulta_secuencia_f->row();
				
				$actualiza_secuencia_d = $this->db->query("UPDATE financiero.fct_ptovta_documento 
				SET secuencia=".($secuencia->maximo+1)." WHERE doc_id = '1' AND substring(serie, 1, 3) = '".$documento->prefijo1."'");
				
				if ($this->db->affected_rows() > 0) {
					return true;
				} else {
					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	function GetDatosSecuenciaNotaCreditoSiis($numeracion){
		list($prefijo, $numero) = explode("-", $numeracion);
		
		$consulta_documento = $this->db->query("SELECT documento_id, substring(texto1, 1, 3) as prefijo1, substring(texto1, 5, 3) as prefijo2 FROM public.documentos WHERE prefijo='".$prefijo."' AND documento_id IN (SELECT valor FROM public.xml_parametros_generales WHERE n_parametro='siis_nota_credito_documento_id')");
		
		if ($consulta_documento->num_rows() > 0) { 
			$documento = $consulta_documento->row();
			
			if($prefijo=="NCC" || $prefijo=="NEC"):
				$actualiza_secuencia_nc = $this->db->query("UPDATE public.notas_contado_credito 
				SET fe_prefijo1='".$documento->prefijo1."', fe_prefijo2='".$documento->prefijo2."', fe_secuencia='".str_pad($numero, 9, "0", STR_PAD_LEFT)."' 
				WHERE public.notas_contado_credito.prefijo='".$prefijo."' 
				AND public.notas_contado_credito.numero=".$numero." 
				AND public.notas_contado_credito.documento_id=".$documento->documento_id);
			elseif($prefijo=="NCE" || $prefijo=="NEE"):		
				$actualiza_secuencia_nc = $this->db->query("UPDATE public.notas_credito_ajuste 
				SET fe_prefijo1='".$documento->prefijo1."', fe_prefijo2='".$documento->prefijo2."', fe_secuencia='".str_pad($numero, 9, "0", STR_PAD_LEFT)."' 
				WHERE public.notas_credito_ajuste.prefijo='".$prefijo."' 
				AND public.notas_credito_ajuste.nota_credito_ajuste=".$numero." 
				AND public.notas_credito_ajuste.documento_id=".$documento->documento_id);
			elseif($prefijo=="NC" || $prefijo=="NE"):		
				$actualiza_secuencia_nc = $this->db->query("UPDATE public.notas_credito 
				SET fe_prefijo1='".$documento->prefijo1."', fe_prefijo2='".$documento->prefijo2."', fe_secuencia='".str_pad($numero, 9, "0", STR_PAD_LEFT)."' 
				WHERE public.notas_credito.prefijo='".$prefijo."' 
				AND public.notas_credito.nota_credito_id=".$numero." 
				AND public.notas_credito.documento_id=".$documento->documento_id);
			endif;
			
			if ($this->db->affected_rows() > 0) {
				$actualiza_secuencia_nc = $this->db->query("UPDATE financiero.fct_ptovta_documento 
				SET secuencia=".($numero+1)." WHERE doc_id = '4' AND substring(serie, 1, 3) = '".$documento->prefijo1."' AND secuencia=".$numero);
				
				if ($this->db->affected_rows() > 0) {
					return true;
				} else {
					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
}
