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
        if($numeracion!=''):    $cons_where= "and '001-'||f.prefijo||'-'||f.factura_fiscal = '$numeracion'";
        endif;

            $algo= ("SELECT f.prefijo||'-'||f.factura_fiscal as id, xml.clave_acceso, xml.estado, xml.envio_correo, tc.tipo_id_tercero AS tipo_identif,$
                                to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, f.prefijo, f.factura_fiscal, f.fe_prefijo1 as prefijo1$
                                abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0)*0.12) AS totalsinimp,
                                CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cuota_paciente)
                                        WHEN f.tipo_factura=1 THEN abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)
                                        ELSE 0
                                END AS descuento_adicional,
                                CASE WHEN f.tipo_factura=0 AND abs(f.valor_cuota_paciente)>0 THEN 'CUOTA PACIENTE'
                                        WHEN f.tipo_factura=1 AND abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)>0 THEN 'FEE HOSPITALARIO'
                                        ELSE ''
                                END AS n_descuento_adicional, abs(f.descuento) as totaldcto,
                                CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cargos+f.valor_cuota_moderadora+f.valor_cuota_paciente-f.descuento)
                                        WHEN f.tipo_factura=1 THEN abs(f.valor_cargos-f.valor_cuota_moderadora-f.valor_cuota_paciente-f.descuento)
                                        ELSE abs(f.valor_cargos-f.descuento)
                                END AS totalconimp, f.tipo_factura, 0 as propina, tc.direccion as direccion_pto, tc.telefono as tlf, tc.email, f.observacion

                                FROM public.fac_facturas f
                                LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
                                LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
                                JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
                                WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR $
                                (f.estado IN (0,1) OR (f.estado=3 AND row(f.empresa_id, f.prefijo, f.factura_fiscal) IN (SELECT empresa_id, prefijo_factura, factura_fiscal FROM public.notas_c$
                                ORDER BY f.fecha_registro, f.fe_prefijo1, f.fe_prefijo2, f.fe_secuencia");

            //abs(f.valor_cargos-f.descuento)-(coalesce(fb.base_imponible, 0)*0.12) AS totalsinimp,
            $consulta= $this->db->query("SELECT f.prefijo||'-'||f.factura_fiscal as id, 
                xml.clave_acceso, xml.estado,
                xml.envio_correo, 
                tc.tipo_id_tercero AS tipo_identif, 
                tc.tercero_id AS identif,
                tc.nombre_tercero AS n_cliente, 
                tc.direccion, 
                to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, 
                to_char(f.fecha_registro,'DDMMYYYY') AS fechaem,
                f.prefijo,
                f.factura_fiscal, 
                '001' as prefijo1, 
                f.prefijo as prefijo2, 
                lpad(f.factura_fiscal,9,0) as secuencia,
                f.total_factura - (CASE WHEN (SELECT sum(bdd.cantidad*bdd.total_iva_venta) FROM facturas_documentos_bodega fdb
                            INNER JOIN bodegas_documentos_d bdd ON fdb.bodegas_numeracion = bdd.numeracion WHERE f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
                        ) NOTNULL THEN (
                            SELECT sum(bdd.cantidad*bdd.total_iva_venta) FROM facturas_documentos_bodega fdb
                            INNER JOIN bodegas_documentos_d bdd ON fdb.bodegas_numeracion = bdd.numeracion WHERE f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
                          )
                      WHEN (
                              SELECT SUM(valor_cargo*(porcentaje_gravamen/100))
                              FROM cuentas_detalle cdsub
                              INNER JOIN fac_facturas_cuentas ffcsub ON cdsub.numerodecuenta = ffcsub.numerodecuenta
                              WHERE cdsub.facturado = '1'
                                AND ffcsub.prefijo = f.prefijo
                                AND ffcsub.factura_fiscal = f.factura_fiscal
                                AND cdsub.porcentaje_gravamen > 0
                                AND cdsub.porcentaje_gravamen_paciente > 0
                                AND cdsub.porcentaje_gravamen = cdsub.porcentaje_gravamen_paciente
                          ) NOTNULL
                            THEN (
                              SELECT SUM(valor_cargo*(porcentaje_gravamen/100))
                              FROM cuentas_detalle cdsub
                              INNER JOIN fac_facturas_cuentas ffcsub ON cdsub.numerodecuenta = ffcsub.numerodecuenta
                              WHERE cdsub.facturado = '1'
                                AND ffcsub.prefijo = f.prefijo
                                AND ffcsub.factura_fiscal = f.factura_fiscal
                                AND cdsub.porcentaje_gravamen > 0
                                AND cdsub.porcentaje_gravamen_paciente > 0
                                AND cdsub.porcentaje_gravamen = cdsub.porcentaje_gravamen_paciente
                          )
                      ELSE 0
                END) as totalsinimp,
                CASE WHEN f.tipo_factura=0 THEN abs(f.valor_cuota_paciente)
                  WHEN f.tipo_factura=1 THEN abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)
                  ELSE 0
                END AS descuento_adicional,
                CASE WHEN f.tipo_factura=0 AND abs(f.valor_cuota_paciente)>0 THEN 'CUOTA PACIENTE'
                  WHEN f.tipo_factura=1 AND abs(f.valor_cuota_moderadora+f.valor_cuota_paciente)>0 THEN 'FEE HOSPITALARIO'
                  ELSE ''
                END AS n_descuento_adicional, 
                abs(f.descuento) as totaldcto, 
                CASE WHEN f.tipo_factura=0 THEN abs(f.total_factura+f.valor_cuota_moderadora+f.valor_cuota_paciente)
                  WHEN f.tipo_factura=1 THEN abs(f.total_factura-f.valor_cuota_moderadora-f.valor_cuota_paciente)
                  ELSE abs(f.total_factura)
                END AS totalconimp,
                f.tipo_factura,
                0 as propina, 
                tc.direccion as direccion_pto, 
                tc.telefono as tlf, 
                CASE
                    WHEN (tc.email ISNULL OR tc.email = '') THEN 'info@cive.ec'
                    ELSE tc.email
                END as email,
                f.observacion,
                p.plan_descripcion AS plan,
                suc.direccion AS dir_estab,
                fdb.paciente_consulta
            FROM public.fac_facturas f
            LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
            LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1='001' AND xml.prefijo2=f.prefijo AND xml.secuencia=lpad(f.factura_fiscal,9,0)
            JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
            LEFT JOIN planes p ON f.plan_id = p.plan_id
            JOIN financiero.admsucursa suc ON suc.empresa = 1 and suc.sucursal = '001'
            INNER JOIN cg_mov_01.cg_mov_contable_01 cg ON f.prefijo = cg.prefijo AND f.factura_fiscal = cg.numero
            INNER JOIN cg_mov_01.documentos_siis_fragata dsf ON cg.documento_contable_id = dsf.documento_contable_id
            INNER JOIN financiero.ctccomprob ctc ON dsf.documento = ctc.documento AND dsf.num_docum = ctc.num_docum
            LEFT JOIN facturas_documentos_bodega fdb ON f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
        WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') AND
        (f.estado IN (0,1) OR (f.estado=3 AND row(f.empresa_id, f.prefijo, f.factura_fiscal) IN (SELECT empresa_id, prefijo_factura, factura_fiscal FROM public.notas_credito))) $cons_where
        ORDER BY f.fecha_registro--, f.fe_prefijo1, f.fe_prefijo2, f.fe_secuencia");
        
    else: if($numeracion!=''):    $cons_where= "and f.prefijo1||'-'||f.prefijo2||'-'||f.factura_fiscal = '$numeracion'";
        endif;
        $consulta= $this->db->query("SELECT f.factura_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, t.tipo_id_tercero AS tipo_identif, t.tercero_id AS identif, t.nombre_tercero AS n_cliente, 
        to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, f.prefijo1, f.prefijo2, f.factura_fiscal as secuencia, f.base_imponible as totalsinimp,
        f.total_descuentos as totaldcto, f.total_factura as totalconimp, 0 as tipo_factura, 0 as propina, f.observaciones as observacion, f.ordcompra_cliente,
        gr.serie||'-'||lpad(gr.secuencia,9,0) as guia_remision, f.forma_pago, f.dias_credito||' días' as dias_credito, vd.nombre as vendedor, f.exportacion, f.instancia_comp_elect as instancia,
        CASE WHEN tcd.id IS NULL THEN trim(coalesce(t.direccion, ''))||':::::'||trim(coalesce(pa.pais, ''))||':::::'||trim(coalesce(pa.n_pais, ''))||':::::'||trim(coalesce(pr.n_provincia, ''))||':::::'||trim(coalesce(cd.n_ciudad, ''))||':::::'||trim(coalesce(t.telefono1, ''))
        ELSE trim(coalesce(tcd.direccion_envio, ''))||':::::'||trim(coalesce(pa2.pais, ''))||':::::'||trim(coalesce(pa2.n_pais, ''))||':::::'||trim(coalesce(pr2.n_provincia, ''))||':::::'||trim(coalesce(cd2.n_ciudad, ''))||':::::'||trim(coalesce(tcd.telefono_contacto, ''))
        END AS datos_direccion, trim(coalesce(t.email, '')) AS email, sc.direccion as dir_estab, cs.n_ciudad as ciudad_estab, ps.n_pais as pais_estab, ps.codigo as cod_pais_estab,
        em.descripcion as embarcador, tp.descripcion as tipo_emb, pe.nombre as puerto_embarque, ppe.n_pais as pais_pe, ppe.codigo as cod_pais_pe, pd.nombre as puerto_destino, ppd.n_pais as pais_pd, ppd.codigo as cod_pais_pd, fx.flete_internacional, fx.seguro_internacional, fx.gastos_aduaneros, fx.gastos_transportes_otros
        
        FROM financiero.fct_factura f
        JOIN financiero.fct_punto_venta pv ON pv.pto_vta_id=f.ptovta_id
        JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = f.cliente_id
        JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id
        LEFT JOIN financiero.admsucursa sc ON sc.sucursal=pv.sucursal_id
        LEFT JOIN financiero.adm_ciudad cs ON cs.ciudad=sc.cod_ciudad
        LEFT JOIN financiero.adm_paises ps ON ps.pais=cs.pais
        LEFT JOIN financiero.fct_vendedores vd ON  vd.vendedor_id = f.vendedor_id
        LEFT JOIN financiero.fct_factura_guia fg ON  fg.fct_id = f.factura_id
        LEFT JOIN financiero.fct_guia_remision gr ON  gr.guia_id = fg.guia_id
        LEFT JOIN financiero.terceros_clientes_direccion tcd ON  tcd.id = f.fct_direccion_envio
        LEFT JOIN financiero.adm_paises pa on pa.pais=t.pais_id
        LEFT JOIN financiero.admprovinc pr on pr.pais=t.pais_id and pr.provincia=t.provincia_id
        LEFT JOIN financiero.adm_ciudad cd on cd.pais=t.pais_id and cd.provincia=t.provincia_id and cd.ciudad=t.ciudad_id
        LEFT JOIN financiero.adm_paises pa2 on pa2.pais=tcd.pais
        LEFT JOIN financiero.admprovinc pr2 on pr2.pais=tcd.pais and pr2.provincia=tcd.provincia
        LEFT JOIN financiero.adm_ciudad cd2 on cd2.pais=tcd.pais and cd2.provincia=tcd.provincia and cd2.ciudad=tcd.ciudad
        LEFT JOIN financiero.fct_factura_exportacion fx on fx.factura_id=f.factura_id
        LEFT JOIN public.inv_embarcador em on em.id=fx.embarcador
        LEFT JOIN public.inv_tipotransporte tp on tp.id=em.tipo
        LEFT JOIN public.inv_puerto pe on pe.id=fx.puerto_embarque
        LEFT JOIN financiero.adm_paises ppe on ppe.pais=pe.pais
        LEFT JOIN public.inv_puerto pd on pd.id=fx.puerto_destino
        LEFT JOIN financiero.adm_paises ppd on ppd.pais=pd.pais
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.prefijo1 AND xml.prefijo2=f.prefijo2 AND xml.secuencia=f.factura_fiscal
        WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' and f.tipo_comprobante_id=1 AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') AND
        (f.estado!=0 OR (f.estado=0 and f.factura_id IN (SELECT fct_id from financiero.fct_nota_credito where estado!=0 and fecha_emision>=f.fecha_emision))) $cons_where
        ORDER BY f.fecha_emision, f.prefijo1, f.prefijo2, f.factura_fiscal");
    endif;  return $consulta;
  }
    function GetDatosFacturaDetalle($factura_id, $tipo_factura, $sistema=''){
    if($sistema=="SIIS"):
        if($tipo_factura!="4")://FACTURA SIN AGRUPAR
            $consulta= $this->db->query("select cd.tarifario_id||'-'||cca.descripcion as det_id, cd.tarifario_id as codigoprincipal, '000' as codigoauxiliar, cca.descripcion, 1 as cantidad, 
            CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto)
              WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto)
              ELSE sum(cd.valor_cargo)
            END AS precio, 
            CASE WHEN f.tipo_factura=0 THEN sum(abs(cd.valor_descuento_paciente))
              WHEN f.tipo_factura=1 THEN sum(abs(cd.valor_descuento_empresa))
              ELSE sum(abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
            END AS descuento, 
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
            ORDER BY totalsinimp DESC");

                    $consulta= $this->db->query(
                        "
                            select
                            case
                              when bdd.codigo_producto ISNULL then cd.cargo  || '-' || cd.transaccion
                              else bdd.codigo_producto  || '-' || bdd.consecutivo
                            end as det_id,
                            case
                              when bdd.codigo_producto ISNULL then cd.cargo
                              else bdd.codigo_producto
                            end as codigoprincipal,
                            case
                              when bdd.codigo_producto ISNULL then cd.cargo
                              else bdd.codigo_producto
                            end as codigoauxiliar,
                            case
                              when ip.descripcion ISNULL then cu.descripcion
                              else ip.descripcion
                            end as descripcion,
                            case
                              when bdd.cantidad ISNULL then cd.cantidad::int
                              else bdd.cantidad::int
                            end as cantidad,
                            case
                              when bdd.total_costo ISNULL
                                THEN
                                  CASE WHEN cd.facturado = 1 THEN cd.valor_cargo
                                  ELSE 0
                                END
                              else bdd.total_costo
                            end as precio,
                            case
                              when (valor_descuento_empresa + cd.valor_descuento_paciente > 0) then valor_descuento_empresa + cd.valor_descuento_paciente
                              WHEN (valor_descuento_empresa ISNULL and cd.valor_descuento_paciente ISNULL) THEN
                                CASE
                                WHEN f.descuento > 0 AND bdd.aplica_descuento = TRUE THEN
                                  f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                                WHEN f.descuento ISNULL OR f.descuento = 0 OR bdd.aplica_descuento = FALSE THEN
                                  0
                                END
                            else 0
                            end as descuento,
                            CASE WHEN  bdd.total_costo ISNULL
                              THEN
                                    CASE
                                      WHEN cd.facturado = 1 THEN cd.valor_cargo*cd.cantidad - (valor_descuento_empresa + cd.valor_descuento_paciente)
                                      ELSE 0
                                    END
                              WHEN  cd.valor_cargo ISNULL
                                THEN  bdd.total_costo*bdd.cantidad - (
                                                                        CASE
                                                                          WHEN (f.descuento > 0 AND bdd.aplica_descuento = TRUE) THEN
                                                                            f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                                                                          WHEN f.descuento ISNULL OR f.descuento = 0 THEN 0
                                                                          ELSE 0
                                                                        END
                                                                      )
                            END AS totalsinimp
                            FROM public.fac_facturas f
                            LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
                            
                            LEFT JOIN facturas_documentos_bodega fdb ON f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
                            LEFT JOIN bodegas_documentos_d bdd ON fdb.bodegas_numeracion = bdd.numeracion
                            LEFT JOIN inventarios_productos ip on bdd.codigo_producto = ip.codigo_producto
                            
                            LEFT JOIN fac_facturas_cuentas ffc on f.prefijo = ffc.prefijo and f.factura_fiscal = ffc.factura_fiscal
                            LEFT JOIN cuentas_detalle cd on ffc.numerodecuenta = cd.numerodecuenta and cd.facturado=1
                            LEFT JOIN cups cu on cd.cargo = cu.cargo
                            
                            WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                            ORDER BY totalsinimp DESC
                        "
                    );
        else://FACTURA AGRUPADA
            $consulta= $this->db->query("select f.prefijo||'-'||f.factura_fiscal||'-1' as det_id, '000' as codigoprincipal, '000' as codigoauxiliar, f.concepto as descripcion, 1 as cantidad, 
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

                $consulta= $this->db->query(
        "
                SELECT 
                    f.prefijo||'-'||f.factura_fiscal as det_id,
                    'GRP0001' as codigoprincipal,
                    'GRP0001' as codigoauxiliar,
                    f.concepto as descripcion,
                    1::int as cantidad,
                    f.total_factura as precio,
                    f.descuento as descuento,
                    f.total_factura AS totalsinimp
                FROM public.fac_facturas f
                WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                ORDER BY totalsinimp DESC
                "
                );
        endif;
    else:
                $consulta= $this->db->query("select cd.fct_id||'-'||cd.fct_det_id as det_id, 
        CASE WHEN coalesce(it.codigo_empresa, '')!=''     THEN it.codigo_empresa    ELSE '001'        END AS codigoprincipal, 
        CASE WHEN cd.descripcion!=''            THEN cd.descripcion     ELSE it.descripcion   END AS descripcion, 
        CASE WHEN coalesce(uni1.abrev, '')!=''        THEN uni1.abrev       ELSE ''         END AS unidad_medida, 
        CASE WHEN coalesce(fcv.factor_conversion, 0)!=0   THEN (cd.cantidad*fcv.factor_conversion)::float||' '||uni2.abrev  ELSE ''   END AS conversion, 
        replace(coalesce(cd.peso_neto, ''), ';', ' ') as peso_neto, replace(coalesce(cd.peso_bruto, ''), ';', ' ') as peso_bruto, fb.fabricante_nombre as marca, 
        NULL as lote, it.codigo_arancel, cd.cantidad, cd.precio, cd.cantidad*cd.precio*((cd.descuento)/100) as descuento, cd.cantidad*(cd.precio*((100-cd.descuento)/100)) as totalsinimp 
        FROM financiero.fct_factura f
        JOIN financiero.fct_factura_detalle cd ON cd.fct_id= f.factura_id
        JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
        LEFT JOIN public.inv_fabricante fb ON fb.fabricante_id=it.fabricante_id
        LEFT JOIN public.inv_unidad uni1 ON uni1.id= it.idunidad
        LEFT JOIN public.inv_unidad uni2 ON uni2.id= it.unidad_contenida
        LEFT JOIN public.inv_unidad uni3 ON uni3.id= uni1.unidad_peso and uni3.sw_peso=1
        LEFT JOIN public.inv_factor_conversion fcv ON fcv.unidad_origen=uni1.id AND fcv.unidad_destino=uni2.id
        LEFT JOIN financiero.fct_factura_detalle_impuesto cdi ON cdi.fct_det_id= cd.fct_det_id
        LEFT JOIN financiero.adm_imptos i on i.impuesto=cdi.impuesto_id and i.codigo_sri!='332'
        WHERE f.factura_id='$factura_id'");

            $consulta= $this->db->query(
                "
                select f.prefijo||'-'||f.factura_fiscal||'-1' as det_id, '000' as codigoprincipal, '000' as codigoauxiliar, f.concepto as descripcion, 1 as cantidad,
                fb.base_imponible AS precio, 0 as descuento, fb.base_imponible AS totalsinimp
                FROM public.fac_facturas f
                JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
                WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                
                UNION ALL
                
                select
                case
                  when bdd.codigo_producto ISNULL then cd.cargo  || '-' || cd.transaccion
                  else bdd.codigo_producto  || '-' || bdd.consecutivo
                end as det_id,
                case
                  when bdd.codigo_producto ISNULL then cd.cargo
                  else bdd.codigo_producto
                end as codigoprincipal,
                case
                  when bdd.codigo_producto ISNULL then cd.cargo
                  else bdd.codigo_producto
                end as codigoauxiliar,
                case
                  when ip.descripcion ISNULL then cu.descripcion
                  else ip.descripcion
                end as descripcion,
                case
                  when bdd.cantidad ISNULL then cd.cantidad::int
                  else bdd.cantidad::int
                end as cantidad,
                 case
                  when bdd.total_costo ISNULL
                    THEN
                      CASE WHEN cd.facturado = 1 THEN cd.valor_cargo
                      ELSE 0
                    END
                  else bdd.total_costo
                end as precio,
                case
                  when (valor_descuento_empresa + cd.valor_descuento_paciente > 0) then valor_descuento_empresa + cd.valor_descuento_paciente
                  WHEN (valor_descuento_empresa ISNULL and cd.valor_descuento_paciente ISNULL) THEN
                    CASE
                      WHEN f.descuento > 0 THEN
                      f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                      WHEN f.descuento ISNULL OR f.descuento = 0 THEN
                      0
                    END
                else 0
                end as descuento,
                CASE WHEN  bdd.total_costo ISNULL
                  THEN
                        CASE
                          WHEN cd.facturado = 1 THEN cd.valor_cargo*cd.cantidad - (valor_descuento_empresa + cd.valor_descuento_paciente)
                          ELSE 0
                        END
                  WHEN  cd.valor_cargo ISNULL
                    THEN  bdd.total_costo*bdd.cantidad - (
                                                            CASE
                                                              WHEN f.descuento > 0 THEN
                                                                f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                                                              WHEN f.descuento ISNULL OR f.descuento = 0 THEN 0
                                                            END
                                                          )
                END AS totalsinimp
                FROM public.fac_facturas f
                LEFT JOIN fac_facturas_detalle_bases fb on fb.prefijo=f.prefijo and fb.factura_fiscal=f.factura_fiscal
                
                LEFT JOIN facturas_documentos_bodega fdb ON f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
                LEFT JOIN bodegas_documentos_d bdd ON fdb.bodegas_numeracion = bdd.numeracion
                LEFT JOIN inventarios_productos ip on bdd.codigo_producto = ip.codigo_producto
                
                LEFT JOIN fac_facturas_cuentas ffc on f.prefijo = ffc.prefijo and f.factura_fiscal = ffc.factura_fiscal
                LEFT JOIN cuentas_detalle cd on ffc.numerodecuenta = cd.numerodecuenta and cd.facturado=1
                LEFT JOIN cups cu on cd.cargo = cu.cargo
                
                WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                ORDER BY totalsinimp DESC
                "
            );
    endif;  return $consulta;
  }
    function GetDatosFacturaDetalleImp($factura_id, $tipo_factura, $sistema=''){
    if($sistema=="SIIS"):
        if($tipo_factura!="4")://FACTURA SIN AGRUPAR
            $consulta= $this->db->query("select cd.tarifario_id||'-'||cca.descripcion as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
            CASE WHEN f.tipo_factura=0 THEN sum(cd.valor_nocubierto - abs(cd.valor_descuento_paciente))
              WHEN f.tipo_factura=1 THEN sum(cd.valor_cubierto - abs(cd.valor_descuento_empresa))
              ELSE sum(cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))
            END AS base_imponible, 
            CASE WHEN f.tipo_factura=0 THEN sum((cd.valor_nocubierto - abs(cd.valor_descuento_paciente))*(cd.porcentaje_gravamen_paciente/100))
              WHEN f.tipo_factura=1 THEN sum((cd.valor_cubierto - abs(cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
              ELSE sum((cd.valor_cargo-abs(cd.valor_descuento_paciente+cd.valor_descuento_empresa))*(cd.porcentaje_gravamen/100))
            END AS valor
            FROM public.fac_facturas f
            JOIN public.fac_facturas_cuentas fc ON fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal
            JOIN public.cuentas_detalle cd ON cd.numerodecuenta=fc.numerodecuenta and cd.facturado=1
            JOIN public.tarifarios_detalle td on td.tarifario_id=cd.tarifario_id and td.cargo=cd.cargo
            JOIN public.grupos_tipos_cargo gtc on gtc.grupo_tipo_cargo=td.grupo_tipo_cargo
            JOIN public.cuentas_codigos_agrupamiento cca on cca.codigo_agrupamiento_id=gtc.codigo_agrupamiento_id
            LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje=coalesce(cd.porcentaje_gravamen, 0)
            WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
            GROUP BY cd.tarifario_id, cca.descripcion, i.ref_impuesto, i.id_porcentaje, i.porcentaje, f.tipo_factura
            ORDER BY base_imponible DESC");

                    $consulta= $this->db->query("
                        select
                            case
                              when bdd.codigo_producto ISNULL then cd.cargo  || '-' || cd.transaccion
                              else bdd.codigo_producto  || '-' || bdd.consecutivo
                            end as det_id,
                            i.ref_impuesto as codigoimp,
                            i.id_porcentaje as codigoporc,
                            i.porcentaje as tarifa,
                            CASE
                              WHEN  bdd.total_costo ISNULL THEN 
                                  CASE
                                    WHEN cd.facturado = 1 THEN cd.valor_cargo*cd.cantidad - (valor_descuento_empresa + cd.valor_descuento_paciente)
                                    ELSE 0
                                  END
                              WHEN  cd.valor_cargo ISNULL
                                THEN  bdd.total_costo*bdd.cantidad -
                                (
                                  CASE
                                      WHEN (f.descuento > 0 AND bdd.aplica_descuento = TRUE) THEN
                                        f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                                      WHEN f.descuento ISNULL OR f.descuento = 0 THEN 0
                                      ELSE 0
                                  END
                                )
                            END AS base_imponible,
                            (CASE
                              WHEN  bdd.total_costo ISNULL THEN 
                              CASE
                                WHEN cd.facturado = 1 THEN cd.valor_cargo*cd.cantidad - (valor_descuento_empresa + cd.valor_descuento_paciente)
                                ELSE 0
                              END
                              WHEN  cd.valor_cargo ISNULL
                              THEN  bdd.total_costo*bdd.cantidad -
                              (
                                CASE
                                    WHEN (f.descuento > 0 AND bdd.aplica_descuento = TRUE) THEN
                                      f.descuento / (select count(*) from bodegas_documentos_d where numeracion = bdd.numeracion and aplica_descuento = TRUE)
                                    WHEN f.descuento ISNULL OR f.descuento = 0 THEN 0
                                    ELSE 0
                                END
                              )
                            END) * ((CASE WHEN cd.porcentaje_gravamen ISNULL THEN bdd.iva_venta ELSE cd.porcentaje_gravamen END)/100)  as valor
                            
                            FROM public.fac_facturas f
                            LEFT JOIN public.fac_facturas_cuentas fc ON fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal
                            LEFT JOIN public.cuentas_detalle cd ON cd.numerodecuenta=fc.numerodecuenta and cd.facturado=1
                            LEFT JOIN cups cu on cd.cargo = cu.cargo
                            LEFT JOIN facturas_documentos_bodega fdb ON f.prefijo = fdb.prefijo AND f.factura_fiscal = fdb.factura_fiscal
                            LEFT JOIN bodegas_documentos_d bdd ON fdb.bodegas_numeracion = bdd.numeracion
                            LEFT JOIN inventarios_productos ip on bdd.codigo_producto = ip.codigo_producto
                            LEFT JOIN xml_impuesto i on i.id_impuesto=1 and (i.porcentaje = cd.porcentaje_gravamen::numeric(4,2) or i.porcentaje = bdd.iva_venta::numeric(4,2))
                            WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                            ORDER BY base_imponible DESC
                    ");
        else://FACTURA AGRUPADA
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

                    $consulta= $this->db->query("
                        SELECT DISTINCT
                            f.prefijo||'-'||f.factura_fiscal as det_id,
                            i.ref_impuesto as codigoimp,
                            i.id_porcentaje as codigoporc,
                            i.porcentaje as tarifa,
                            f.total_factura AS base_imponible,
                            f.total_factura::FLOAT * (i.porcentaje::FLOAT /100::FLOAT)  as valor
                        FROM public.fac_facturas f
                        LEFT JOIN public.fac_facturas_cuentas fc ON fc.prefijo=f.prefijo and fc.factura_fiscal=f.factura_fiscal
                        LEFT JOIN xml_impuesto i on i.id_impuesto=1 and (i.porcentaje = '0.00')
                        WHERE f.prefijo||'-'||f.factura_fiscal='$factura_id'
                        ORDER BY base_imponible DESC
                    "
                    );
        endif;
    else:
                $consulta= $this->db->query("select cd.fct_id||'-'||cd.fct_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cdi.impuesto_valor_generado as valor 
        FROM financiero.fct_factura f
        JOIN financiero.fct_factura_detalle cd ON cd.fct_id= f.factura_id
        JOIN financiero.fct_factura_detalle_impuesto cdi ON cdi.fct_det_id= cd.fct_det_id and cdi.impuesto_porcentaje>0
        LEFT JOIN xml_impuesto i on i.id_impuesto= cdi.impuesto_id and i.porcentaje=cdi.impuesto_porcentaje
        WHERE f.factura_id='$factura_id'
        
        UNION ALL
        
        select cd.fct_id||'-'||cd.fct_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, 0 as valor 
        FROM financiero.fct_factura f
        JOIN financiero.fct_factura_detalle cd ON cd.fct_id= f.factura_id
        LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='0.00'
        WHERE f.factura_id='$factura_id' AND cd.fct_det_id NOT IN (SELECT fct_det_id FROM financiero.fct_factura_detalle_impuesto WHERE fct_id=f.factura_id and impuesto_porcentaje>0)");
    endif;  return $consulta;
  }
    function GetDatosGuiaRemision($numeracion='', $sistema=''){
        if($numeracion!=''):    $cons_where= "and f.serie||'-'||lpad(f.secuencia, 9, 0) = '$numeracion'";
        endif;
        $consulta= $this->db->query("SELECT f.guia_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, t.tipo_id_tercero AS tipo_identif, t.tercero_id AS identif, t.nombre_tercero AS n_cliente,
        to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, substring(f.serie, 1, 3) as prefijo1, substring(f.serie, 5, 3) as prefijo2, lpad(f.secuencia, 9, 0) as secuencia,
        to_char(f.traslado_finicio,'DD/MM/YYYY') AS traslado_finicio, to_char(f.traslado_ffin,'DD/MM/YYYY') AS traslado_ffin, f.transportista, f.ruc_transportista, f.placa_transportista, f.destino, f.motivo, f.instancia_comp_elect as instancia,
        xfc.tipo_doc as mod_tipo_doc, fct.prefijo1||'-'||fct.prefijo2||'-'||fct.factura_fiscal AS mod_numeracion, xfc.autorizacion as mod_autorizacion, to_char(fct.fecha_emision,'DD/MM/YYYY') AS mod_fechaemision, fct.observaciones as observacion,
        CASE WHEN tcd.id IS NULL THEN trim(coalesce(t.direccion, ''))||':::::'||trim(coalesce(pa.pais, ''))||':::::'||trim(coalesce(pa.n_pais, ''))||':::::'||trim(coalesce(pr.n_provincia, ''))||':::::'||trim(coalesce(cd.n_ciudad, ''))||':::::'||trim(coalesce(t.telefono1, ''))
        ELSE trim(coalesce(tcd.direccion_envio, ''))||':::::'||trim(coalesce(pa2.pais, ''))||':::::'||trim(coalesce(pa2.n_pais, ''))||':::::'||trim(coalesce(pr2.n_provincia, ''))||':::::'||trim(coalesce(cd2.n_ciudad, ''))||':::::'||trim(coalesce(tcd.telefono_contacto, ''))
        END AS datos_direccion, trim(coalesce(t.email, '')) AS email, sc.direccion as dir_estab
        
        FROM financiero.fct_guia_remision f
        JOIN financiero.fct_punto_venta pv ON pv.pto_vta_id=f.ptovta_id
        JOIN financiero.admsucursa sc ON sc.sucursal=pv.sucursal_id
        LEFT JOIN financiero.fct_factura_guia fg ON  fg.guia_id = f.guia_id
        LEFT JOIN financiero.fct_factura fct ON  fct.factura_id = fg.fct_id
        LEFT JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = fct.cliente_id
        LEFT JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id
        LEFT JOIN financiero.terceros_clientes_direccion tcd ON  tcd.id = fct.fct_direccion_envio
        LEFT JOIN financiero.adm_paises pa on pa.pais=t.pais_id
        LEFT JOIN financiero.admprovinc pr on pr.pais=t.pais_id and pr.provincia=t.provincia_id
        LEFT JOIN financiero.adm_ciudad cd on cd.pais=t.pais_id and cd.provincia=t.provincia_id and cd.ciudad=t.ciudad_id
        LEFT JOIN financiero.adm_paises pa2 on pa2.pais=tcd.pais
        LEFT JOIN financiero.admprovinc pr2 on pr2.pais=tcd.pais and pr2.provincia=tcd.provincia
        LEFT JOIN financiero.adm_ciudad cd2 on cd2.pais=tcd.pais and cd2.provincia=tcd.provincia and cd2.ciudad=tcd.ciudad
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=substring(f.serie, 1, 3) AND xml.prefijo2=substring(f.serie, 5, 3) AND xml.secuencia=lpad(f.secuencia, 9, 0)
        LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.prefijo1 AND xfc.prefijo2=fct.prefijo2 AND xfc.secuencia=fct.factura_fiscal
        WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
        ORDER BY f.fecha_emision, prefijo1, prefijo2, secuencia");
        return $consulta;
  }
    function GetDatosGuiaRemisionDetalle($guia_id, $sistema=''){
        $consulta= $this->db->query("select cd.guia_id as det_id, 
        CASE WHEN coalesce(it.codigo_empresa, '')!=''   THEN it.codigo_empresa  ELSE '001'        END AS codigoprincipal, it.descripcion, fb.fabricante_nombre as marca, NULL as lote, it.codigo_arancel, cd.cantidad,
        CASE WHEN coalesce(uni1.abrev, '')!=''      THEN uni1.abrev     ELSE ''         END AS unidad_medida, 
        CASE WHEN coalesce(fcv.factor_conversion, 0)!=0   THEN (cd.cantidad*fcv.factor_conversion)::float||' '||uni2.abrev  ELSE ''   END AS conversion 
        FROM financiero.fct_guia_remision_detalle cd
        JOIN public.inv_item it ON cd.item_id= it.codigo_item
        LEFT JOIN public.inv_fabricante fb ON fb.fabricante_id=it.fabricante_id
        LEFT JOIN public.inv_unidad uni1 ON uni1.id= it.idunidad
        LEFT JOIN public.inv_unidad uni2 ON uni2.id= it.unidad_contenida
        LEFT JOIN public.inv_unidad uni3 ON uni3.id= uni1.unidad_peso and uni3.sw_peso=1
        LEFT JOIN public.inv_factor_conversion fcv ON fcv.unidad_origen=uni1.id AND fcv.unidad_destino=uni2.id
        WHERE cd.guia_id='$guia_id'");
        return $consulta;
  }
    function GetDatosNotaCredito($numeracion='', $sistema=''){
    if($sistema=="SIIS"):
        if($numeracion!=''):    $cons_where1= "and f.numero = '$numeracion'";
                      $cons_where2= "and f.nota_credito_ajuste = '$numeracion'";
                      $cons_where3= "and f.nota_credito_id = '$numeracion'";
        endif;
        $consulta= $this->db->query("SELECT f.numero as id, xml.clave_acceso, xml.estado, xml.envio_correo, tc.tipo_id_tercero AS tipo_identif, tc.tercero_id AS identif, tc.nombre_tercero AS n_cliente, tc.direccion, 
        to_char(f.fecha_registro,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_registro,'DDMMYYYY') AS fechaem, f.fe_prefijo1 as prefijo1, f.fe_prefijo2 as prefijo2, f.fe_secuencia as secuencia, 
        CASE WHEN fct.fe_prefijo1 IS NULL THEN fct.prefijo||'-001-'||lpad(fct.factura_fiscal,9,0)
              ELSE fct.fe_prefijo1||'-'||fct.fe_prefijo2||'-'||fct.fe_secuencia
        END AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_registro,'DD/MM/YYYY') AS mod_fechaemision, tc.direccion as direccion_pto, tc.telefono as tlf, tc.email, f.observacion,
        f.valor_nota AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.valor_nota AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.f.tipo_factura
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
        f.total_nota_ajuste AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.total_nota_ajuste AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.f.tipo_factura
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
        f.valor_nota AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.valor_nota AS totalconimp, f.prefijo AS tipo_nc, fct.prefijo||'-'||fct.factura_fiscal as factura_id, xfc.total as total_fac, fct.f.tipo_factura
        FROM public.notas_credito f
        JOIN public.fac_facturas fct ON f.empresa_id= fct.empresa_id and f.prefijo_factura= fct.prefijo and f.factura_fiscal= fct.factura_fiscal
        JOIN public.terceros tc ON tc.tipo_id_tercero= f.tipo_id_tercero and  tc.tercero_id= f.tercero_id
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=f.fe_prefijo1 AND xml.prefijo2=f.fe_prefijo2 AND xml.secuencia=f.fe_secuencia
        LEFT JOIN xml_comprobante xfc ON xfc.tipo_doc='01' AND xfc.prefijo1=fct.fe_prefijo1 AND xfc.prefijo2=fct.fe_prefijo2 AND xfc.secuencia=fct.fe_secuencia
        WHERE substring(f.fecha_registro, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where3
        ORDER BY prefijo1, prefijo2, secuencia");
        
    else: if($numeracion!=''):    $cons_where= "and f.serie||'-'||f.secuencia = '$numeracion'";
        endif;
        $consulta= $this->db->query("SELECT f.ncredito_id as id, xml.clave_acceso, xml.estado, xml.envio_correo, t.tipo_id_tercero AS tipo_identif, t.tercero_id AS identif, t.nombre_tercero AS n_cliente, f.secuencia as sec_nc,
        to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, substring(f.serie, 1, 3) as prefijo1, substring(f.serie, 5, 3) as prefijo2, lpad(f.secuencia, 9, 0) as secuencia, 
        fct.prefijo1||'-'||fct.prefijo2||'-'||fct.factura_fiscal AS mod_numeracion, '01' as mod_tipo_doc, to_char(fct.fecha_emision,'DD/MM/YYYY') AS mod_fechaemision, f.motivo as observacion,
        f.total_nota_credito-f.impuesto AS totalsinimp, 0 AS descuento_adicional, 0 as totaldcto, f.total_nota_credito AS totalconimp, 'NC' AS tipo_nc,
        CASE WHEN tcd.id IS NULL THEN trim(coalesce(t.direccion, ''))||':::::'||trim(coalesce(pa.pais, ''))||':::::'||trim(coalesce(pa.n_pais, ''))||':::::'||trim(coalesce(pr.n_provincia, ''))||':::::'||trim(coalesce(cd.n_ciudad, ''))||':::::'||trim(coalesce(t.telefono1, ''))
        ELSE trim(coalesce(tcd.direccion_envio, ''))||':::::'||trim(coalesce(pa2.pais, ''))||':::::'||trim(coalesce(pa2.n_pais, ''))||':::::'||trim(coalesce(pr2.n_provincia, ''))||':::::'||trim(coalesce(cd2.n_ciudad, ''))||':::::'||trim(coalesce(tcd.telefono_contacto, ''))
        END AS datos_direccion, trim(coalesce(t.email, '')) AS email, sc.direccion as dir_estab
        
        FROM financiero.fct_nota_credito f
        JOIN financiero.fct_punto_venta pv ON pv.pto_vta_id=f.ptovta_id
        JOIN financiero.admsucursa sc ON sc.sucursal=pv.sucursal_id
        JOIN financiero.fct_factura fct ON f.fct_id= fct.factura_id
        JOIN financiero.terceros_clientes tc ON  tc.codigo_cliente_id = f.codigo_cliente
        JOIN financiero.terceros t ON t.tipo_id_tercero = tc.tipo_id_tercero and t.tercero_id = tc.tercero_id
        LEFT JOIN financiero.terceros_clientes_direccion tcd ON  tcd.id = fct.fct_direccion_envio
        LEFT JOIN financiero.adm_paises pa on pa.pais=t.pais_id
        LEFT JOIN financiero.admprovinc pr on pr.pais=t.pais_id and pr.provincia=t.provincia_id
        LEFT JOIN financiero.adm_ciudad cd on cd.pais=t.pais_id and cd.provincia=t.provincia_id and cd.ciudad=t.ciudad_id
        LEFT JOIN financiero.adm_paises pa2 on pa2.pais=tcd.pais
        LEFT JOIN financiero.admprovinc pr2 on pr2.pais=tcd.pais and pr2.provincia=tcd.provincia
        LEFT JOIN financiero.adm_ciudad cd2 on cd2.pais=tcd.pais and cd2.provincia=tcd.provincia and cd2.ciudad=tcd.ciudad
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=substring(f.serie, 1, 3) AND xml.prefijo2=substring(f.serie, 5, 3) AND xml.secuencia=lpad(f.secuencia, 9, 0)
        WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."'
        AND f.serie IN (SELECT serie from financiero.fct_ptovta_documento where doc_id='4') AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
        ORDER BY f.fecha_emision, prefijo1, prefijo2, secuencia");

            $consulta= $this->db->query("SELECT f.ncredito_id as id,
                                            xml.clave_acceso,
                                            xml.estado,
                                            xml.envio_correo,
                                            t.tipo_id_tercero AS tipo_identif,
                                            t.tercero_id AS identif,
                                            t.nombre_tercero AS n_cliente,
                                            f.secuencia as sec_nc,
                                            to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision,
                                            to_char(f.fecha_emision,'DDMMYYYY') AS fechaem,
                                            substring(f.serie, 1, 3) as prefijo1,
                                            substring(f.serie, 5, 3) as prefijo2,
                                            lpad(f.secuencia, 9, 0) as secuencia,
                                            '001'||'-'||fct.prefijo||'-'||lpad(fct.factura_fiscal, 9, 0) AS mod_numeracion,
                                            '01' as mod_tipo_doc,
                                            to_char(fct.fecha_registro,'DD/MM/YYYY') AS mod_fechaemision,
                                            f.motivo as observacion,
                                            f.total_nota_credito-f.impuesto AS totalsinimp,
                                            0 AS descuento_adicional,
                                            0 as totaldcto,
                                            f.total_nota_credito AS totalconimp,
                                            'NC' AS tipo_nc,
                                            trim(coalesce(t.email, '')) AS email,
                                            sc.direccion as dir_estab
        FROM financiero.fct_nota_credito f
                JOIN financiero.fct_punto_venta pv ON pv.pto_vta_id=f.ptovta_id
                JOIN financiero.admsucursa sc ON sc.sucursal=pv.sucursal_id
                INNER JOIN financiero.ctccomprob ctc ON f.fct_documento = ctc.documento AND f.fct_num_docum = ctc.num_docum
                INNER JOIN cg_mov_01.documentos_siis_fragata dsf ON ctc.documento = dsf.documento AND ctc.num_docum = dsf.num_docum
                INNER JOIN cg_mov_01.cg_mov_contable_01 cmc ON dsf.documento_contable_id = cmc.documento_contable_id
                INNER JOIN fac_facturas fct ON cmc.prefijo = fct.prefijo AND cmc.numero = fct.factura_fiscal
                INNER JOIN financiero.ctmauxilia aux ON f.codigo_cliente = aux.auxiliar
                INNER JOIN public.terceros t ON aux.tercero_id = t.tercero_id
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=substring(f.serie, 1, 3) AND xml.prefijo2=substring(f.serie, 5, 3) AND xml.secuencia=lpad(f.secuencia, 9, 0)
        WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."'
        AND f.serie IN (SELECT serie from financiero.fct_ptovta_documento where doc_id='4') AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
        ORDER BY f.fecha_emision, prefijo1, prefijo2, secuencia");
    endif;  return $consulta;
  }
    function GetDatosNotaCreditoDetalle($nota_credito_id, $tipo_nota_credito, $sistema=''){
    if($sistema=="SIIS"):
        if($tipo_nota_credito=="NCC"):
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
        elseif($tipo_nota_credito=="NC"):   
            $consulta= $this->db->query("select d.nota_credito_concepto_id as det_id, d.concepto_id as codigoprincipal, '000' as codigoauxiliar, ncc.descripcion, 1 as cantidad, 
            valor AS precio, 0 AS descuento, valor AS totalsinimp
            FROM public.notas_credito_detalle_conceptos d
            JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
            WHERE d.nota_credito_id='$nota_credito_id'");
        endif;
    else: $consulta= $this->db->query("select cd.ncredito_det_id as det_id, 
        CASE WHEN coalesce(it.codigo_empresa, '')!=''     THEN it.codigo_empresa    ELSE '001'        END AS codigoprincipal, 
        CASE WHEN cd.descripcion!=''            THEN cd.descripcion     ELSE it.descripcion   END AS descripcion, 
        CASE WHEN coalesce(uni1.abrev, '')!=''        THEN uni1.abrev       ELSE ''         END AS unidad_medida, 
        CASE WHEN coalesce(fcv.factor_conversion, 0)!=0   THEN (cd.cantidad*fcv.factor_conversion)::float||' '||uni2.abrev  ELSE ''   END AS conversion, 
        fb.fabricante_nombre as marca, NULL as lote, it.codigo_arancel, cd.cantidad, cd.precio, cd.cantidad*cd.precio*((cd.descuento)/100) as descuento, cd.cantidad*(cd.precio*((100-cd.descuento)/100)) as totalsinimp
        FROM financiero.fct_nota_credito_detalle cd
        JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
        LEFT JOIN public.inv_fabricante fb ON fb.fabricante_id=it.fabricante_id
        LEFT JOIN public.inv_unidad uni1 ON uni1.id= it.idunidad
        LEFT JOIN public.inv_unidad uni2 ON uni2.id= it.unidad_contenida
        LEFT JOIN public.inv_unidad uni3 ON uni3.id= uni1.unidad_peso and uni3.sw_peso=1
        LEFT JOIN public.inv_factor_conversion fcv ON fcv.unidad_origen=uni1.id AND fcv.unidad_destino=uni2.id
        WHERE cd.ncredito_id='$nota_credito_id'");

                $consulta= $this->db->query("select
                                            cd.ncredito_det_id as det_id,
                                            cd.cargo AS codigoprincipal,
                                            CASE
                                              WHEN cu.descripcion ISNULL THEN ip.descripcion
                                              ELSE cu.descripcion
                                            END AS descripcion,
                                            '' AS unidad_medida,
                                            '' AS conversion,
                                            '' as marca,
                                            cd.lote as lote,
                                            '' as codigo_arancel,
                                            cd.cantidad,
                                            cd.precio,
                                            cd.descuento as descuento,
                                            (cd.cantidad*cd.precio)-cd.descuento as totalsinimp
                                            FROM financiero.fct_nota_credito_detalle cd
                                            LEFT JOIN public.inventarios_productos ip ON cd.cargo = ip.codigo_producto
                                            LEFT JOIN public.cups cu ON cd.cargo = cu.cargo
                                            WHERE cd.ncredito_id='$nota_credito_id'");
    endif;  return $consulta;
  }
    function GetDatosNotaCreditoDetalleImp($nota_credito_id, $tipo_nota_credito, $sistema=''){
    if($sistema=="SIIS"):
        if($tipo_nota_credito=="NCC"):
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
        elseif($tipo_nota_credito=="NC"):   
            $consulta= $this->db->query("select d.nota_credito_concepto_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
            valor AS base_imponible, 0 AS valor
            FROM public.notas_credito_detalle_conceptos d
            JOIN public.notas_credito_ajuste_conceptos ncc ON d.concepto_id=ncc.concepto_id
            LEFT JOIN xml_impuesto i on i.id_impuesto=1 and i.porcentaje='0.00'
            WHERE d.nota_credito_id='$nota_credito_id'");
        endif;
    else: $consulta= $this->db->query("select cd.ncredito_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cd.impuesto as valor 
        FROM financiero.fct_nota_credito_detalle cd
        JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
        $porc_iva %
        WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto>0
        
        UNION ALL
        
        select cd.ncredito_det_id as det_id, i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (cd.precio-(cd.precio*(cd.descuento/100)))*cd.cantidad as base_imponible, cd.impuesto as valor 
        FROM financiero.fct_nota_credito_detalle cd
        JOIN public.inv_item it ON cd.codigo_item= it.codigo_item
        LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='0.00'
        WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto=0");

            $consulta= $this->db->query("select cd.ncredito_det_id as det_id,
                                        i.ref_impuesto as codigoimp,
                                        i.id_porcentaje as codigoporc,
                                        i.porcentaje as tarifa,
                                        (cd.precio*cd.cantidad)-cd.descuento as base_imponible,
                                        cd.impuesto as valor
                                        FROM financiero.fct_nota_credito_detalle cd
                                        left join public.inventarios_productos ip ON cd.cargo = ip.codigo_producto
                                        left join public.cups cu ON cd.cargo = cu.cargo
                                        LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='12.00'
                                        WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto>0
                                        UNION ALL
                                        select cd.ncredito_det_id as det_id,
                                        i.ref_impuesto as codigoimp,
                                        i.id_porcentaje as codigoporc,
                                        i.porcentaje as tarifa,
                                        (cd.precio*cd.cantidad)-cd.descuento as base_imponible,
                                        cd.impuesto as valor
                                        FROM financiero.fct_nota_credito_detalle cd
                                        left join public.inventarios_productos ip ON cd.cargo = ip.codigo_producto
                                        left join public.cups cu ON cd.cargo = cu.cargo
                                        LEFT JOIN xml_impuesto i on i.id_impuesto= 1 and i.porcentaje='0.00'
                                        WHERE cd.ncredito_id='$nota_credito_id' and cd.impuesto=0");
    endif;  return $consulta;
  }
  
    function GetDatosRetencion($numeracion='', $sistema=''){
        if($numeracion!=''):    $cons_where= "and r.ret_prefijo1||'-'||r.ret_prefijo2||'-'||r.ret_numero_fiscal = '$numeracion'";
        endif;
        
        $this->db->query("delete from xml_impuesto where n_impuesto IN ('RET. FTE.', 'RET. IVA');

              insert into xml_impuesto
              select impuesto, abv_reporte, porcentaje, '1', 2 from financiero.adm_imptos where abv_reporte='RET. IVA' and porcentaje=30
        UNION ALL select impuesto, abv_reporte, porcentaje, '2', 2 from financiero.adm_imptos where abv_reporte='RET. IVA' and porcentaje=70
        UNION ALL select impuesto, abv_reporte, porcentaje, '3', 2 from financiero.adm_imptos where abv_reporte='RET. IVA' and porcentaje=100
        UNION ALL   select impuesto, abv_reporte, porcentaje, '9', 2 from financiero.adm_imptos where abv_reporte='RET. IVA' and porcentaje=10
        UNION ALL   select impuesto, abv_reporte, porcentaje, '10', 2 from financiero.adm_imptos where abv_reporte='RET. IVA' and porcentaje=20
        UNION ALL select impuesto, abv_reporte, porcentaje, codigo_sri, 1 from financiero.adm_imptos where abv_reporte='RET. FTE.'");
        
        $consulta= $this->db->query("SELECT f.cab_movimiento as id, xml.clave_acceso, xml.estado, xml.envio_correo, p.tipoidtercero AS tipo_identif, p.ruc AS identif, p.proveedor_nombre AS n_cliente, p.direccion, 
        to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision, to_char(f.fecha_emision,'DDMMYYYY') AS fechaem, r.ret_prefijo1 as prefijo1, r.ret_prefijo2 as prefijo2, r.ret_numero_fiscal as secuencia, 
                p.direccion as direccion_pto, p.telefono_proveedor as tlf, p.email_proveedor AS email, sc.direccion as dir_estab, ' ' as observacion
        FROM financiero.bnpagodoc f
        JOIN financiero.cpcretencion r on r.fac_prefijo1=f.prefijo1 AND r.fac_prefijo2=f.prefijo2 AND r.fac_numero_fiscal=f.factura_fiscal AND r.proveedor=f.proveedor AND r.tipo_doc=f.tipo_doc AND r.estado='A'
        JOIN financiero.admsucursa sc ON sc.sucursal=r.ret_prefijo1
        JOIN public.inv_proveedor p on p.proveedor_id=f.proveedor
        LEFT JOIN xml_comprobante xml ON xml.tipo_doc='$this->tipo_doc' AND xml.prefijo1=r.ret_prefijo1 AND xml.prefijo2=r.ret_prefijo2 AND xml.secuencia=r.ret_numero_fiscal
        WHERE substring(f.fecha_emision, 1, 10) between '".$this->arr_parametros["fecha_minima"]."' and '".date("Y-m-d")."' AND (coalesce(xml.autorizacion, '')='' OR coalesce(xml.envio_correo, '0')='2') $cons_where
        --AND r.ret_prefijo1||'-'||r.ret_prefijo2='001-003'
        ORDER BY f.fecha_emision, r.ret_prefijo1, r.ret_prefijo2, r.ret_numero_fiscal");
        return $consulta;
  }
    function GetDatosRetencionDetalle($factura_id, $sistema=''){
        $consulta= $this->db->query("select i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, 
        CASE  WHEN cdi.abv_reporte='RET. IVA' THEN  CASE WHEN coalesce(fr.impuesto_ice, 0)!='0' THEN  (fr.valor*(1+(ice.porcentaje/100)))*(iva.porcentaje/100)  ELSE fr.valor*(iva.porcentaje/100)  END 
            WHEN cdi.codigo_sri='322' THEN      fr.valor*0.10
            ELSE                  fr.valor
        END as base_imponible, d.cod_sri as coddocsustento, f.prefijo1||f.prefijo2||f.factura_fiscal as numdocsustento, to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision
        FROM financiero.bnpagodoc f 
        JOIN financiero.cpcfactura_rubros fr on fr.prefijo1=f.prefijo1 and fr.prefijo2=f.prefijo2 and fr.factura_fiscal=f.factura_fiscal and fr.proveedor=f.proveedor and fr.tipo_doc=f.tipo_doc
        JOIN financiero.admdocumen d on d.documento=fr.tipo_doc
        JOIN financiero.adm_imptos cdi on cdi.impuesto IN (fr.impuesto_rfte, fr.impuesto_riva) and cdi.codigo_sri!='332'
        LEFT JOIN financiero.adm_imptos iva on iva.impuesto=fr.impuesto_iva
        LEFT JOIN financiero.adm_imptos ice on ice.impuesto=fr.impuesto_ice
        LEFT JOIN xml_impuesto i on i.id_impuesto= cdi.impuesto and i.porcentaje=coalesce(cdi.porcentaje, 0)
        WHERE f.cab_movimiento='$factura_id'
        /*
        UNION ALL
        
        select i.ref_impuesto as codigoimp, i.id_porcentaje as codigoporc, i.porcentaje as tarifa, (f.total_factura + sum(coalesce(fci.valor,0))) - sum(coalesce(fcr.base_imponible,0)) as base_imponible, 
        d.cod_sri as coddocsustento, f.prefijo1||f.prefijo2||f.factura_fiscal as numdocsustento, to_char(f.fecha_emision,'DD/MM/YYYY') AS fechaemision
        FROM financiero.bnpagodoc b
        JOIN financiero.cpcfacturas f on f.prefijo1=b.prefijo1 and f.prefijo2=b.prefijo2 and f.factura_fiscal=b.factura_fiscal and f.proveedor=b.proveedor and f.tipo_doc=b.tipo_doc
        JOIN financiero.admdocumen d on d.documento=f.tipo_doc
        LEFT JOIN financiero.cpcfactura_cargos fcr on fcr.prefijo1=f.prefijo1 and fcr.prefijo2=f.prefijo2 and fcr.factura_fiscal=f.factura_fiscal and fcr.proveedor=f.proveedor and fcr.tipo_doc=f.tipo_doc
        AND fcr.impuesto IN (SELECT impuesto from financiero.adm_imptos WHERE abv_reporte='RET. FTE.' and porcentaje>0)
        LEFT JOIN financiero.cpcfactura_cargos fci on fci.prefijo1=f.prefijo1 and fci.prefijo2=f.prefijo2 and fci.factura_fiscal=f.factura_fiscal and fci.proveedor=f.proveedor and fci.tipo_doc=f.tipo_doc
        AND fci.impuesto IN (SELECT impuesto from financiero.adm_imptos WHERE abv_reporte='ICE' and porcentaje>0)
        LEFT JOIN xml_impuesto i on i.id_porcentaje='332'
        WHERE b.cab_movimiento='$factura_id' and (SELECT count(*) from financiero.cpcfactura_cargos WHERE
        prefijo1=b.prefijo1 and prefijo2=b.prefijo2 and factura_fiscal=b.factura_fiscal and proveedor=b.proveedor and tipo_doc=b.tipo_doc and impuesto IN
        (SELECT impuesto from financiero.adm_imptos WHERE abv_reporte='RET. FTE.'))>0
        GROUP BY i.ref_impuesto, i.id_porcentaje, i.porcentaje, f.total_factura, d.cod_sri, f.prefijo1, f.prefijo2, f.factura_fiscal, f.fecha_emision
        HAVING (f.total_factura + sum(coalesce(fci.valor,0))) - sum(coalesce(fcr.base_imponible,0)) > 0*/");
        return $consulta;
  }
}
