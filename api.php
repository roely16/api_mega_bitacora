<?php  

    header('Access-Control-Allow-Origin: *');
    header("Access-Control-Allow-Headers: X-API-KEY, Origin, X-Requested-With, Content-Type, Accept, Access-Control-Request-Method");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
    header("Allow: GET, POST, OPTIONS, PUT, DELETE");

    // include $_SERVER['DOCUMENT_ROOT'] . '/apps/api_mega_bitacora/functions.php';

    class  Api extends Rest
    {

        public $dbConn;

		public function __construct(){

			parent::__construct();

			$db = new Db();
			$this->dbConn = $db->connect();

        }

        public function obtenerMatriculas(){ 

            $pagina =  $this->param['pagina'];
            $items_pagina =  $this->param['items_pagina'];
            $busqueda = strtoupper($this->param['busqueda']);

            $data = [];

            try {
                
                $query = "  select concat(ma_matricula, concat('-', ma_terminacion)) as matricula, to_char(ma_fecha_sistema, 'dd/mm/yyyy hh24:mi:ss') as fecha_inscripcion, 
                ma_finca, ma_folio, ma_libro, ma_literal_libro, ma_tipo_libro, ma_numero_zona, ma_numero_manzana, ma_numero_predio, ma_numero_persona, 
                (select SGC.GN_NOMBRE_PERSONA(ma_numero_persona) from dual) as propietario, (select SGC.GN_DPI_NIT_PERSONA(ma_numero_persona) from dual) as cui
                            from 
                            (
                                select a.*, rownum r
                                from (
                                    select *
                                    from sgc.mat_ma_matricula
                                    where concat(ma_matricula, concat('-', ma_terminacion)) like '%$busqueda%'
                                    order by ma_fecha_sistema desc
                                ) a
                                where rownum < (($pagina * $items_pagina) + 1)
                            )
                            where r >= ((($pagina - 1) * $items_pagina) + 1)";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $matriculas = [];

				while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

					$matriculas [] = $data;

                }
                
                // Cantidad de Registros
                $query = "  select count(*) as total_registros
                            from sgc.mat_ma_matricula
                            where concat(ma_matricula, concat('-', ma_terminacion)) like '%$busqueda%'
                            order by ma_fecha_sistema desc";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $total = oci_fetch_array($stid, OCI_ASSOC);


            } catch (\Throwable $th) {
                //throw $th;
            }

            $data["total_registros"] = intval(intval($total["TOTAL_REGISTROS"]) / $items_pagina) + 1;
            
            $data["items"] = $matriculas;

            $data["headers"] = [
                [
                    "text" => "No. Matrícula",
                    "value" => "MATRICULA",
                    "sortable" => false,
                    "width" => "10%"
                ],
                [
                    "text" => "No. Registral",
                    "value" => "no_registral",
                    "sortable" => false,
                    "width" => "20%"
                ],
                [
                    "text" => "No. Catastral",
                    "value" => "no_catastral",
                    "sortable" => false,
                    "width" => "10%"
                ],
                [
                    "text" => "Propietario",
                    "value" => "PROPIETARIO",
                    "sortable" => false,
                    "width" => "35%"
                ],
                [
                    "text" => "CUI / NIT",
                    "value" => "CUI",
                    "sortable" => false,
                    "width" => "10%"
                ],
                [
                    "text" => "",
                    "value" => "actions",
                    "sortable" => false,
                    "width" => "5%"
                ],
                
            ];

            $this->returnResponse(SUCCESS_RESPONSE, $data);

        }

        public function obtenerDetalle(){

            $matricula = $this->param['matricula'];

            $array_matricula = explode("-", $matricula);

            $ma_matricula = $array_matricula[0];
            $ma_terminacion = $array_matricula[1];

            $data = [];

            try {
                
                // Obtener el detalle
                $query = "  SELECT CONCAT(MA_MATRICULA, CONCAT('-', MA_TERMINACION)) AS MATRICULA, MA_NUMERO_PERSONA, 
                            CONCAT(MA_NUMERO_ZONA, CONCAT('-', CONCAT(MA_NUMERO_MANZANA, CONCAT('-', MA_NUMERO_PREDIO)))) AS NO_CATASTRAL,
                            CONCAT(MA_FINCA, CONCAT('-', CONCAT(MA_FOLIO, CONCAT('-', CONCAT(MA_LIBRO, CONCAT('-', MA_TIPO_LIBRO)))))) AS NO_REGISTRAL, 
                            ma_numero_persona, 
                            (select SGC.GN_NOMBRE_PERSONA(ma_numero_persona) from dual) as propietario,
                            ma_codigo_municipio, ma_numero_zona, ma_numero_manzana, ma_numero_predio,
                            ma_finca, ma_folio, ma_libro, ma_literal_libro, ma_tipo_denominacion,
                            ma_literal_finca, ma_tipo_libro
                            FROM SGC.MAT_MA_MATRICULA
                            WHERE MA_MATRICULA = '$ma_matricula' 
                            AND MA_TERMINACION = '$ma_terminacion'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $detalle_matricula = oci_fetch_array($stid, OCI_ASSOC);

                // Obtener la dirección del propietario
                $ma_numero_persona = $detalle_matricula["MA_NUMERO_PERSONA"];

                $query = "
                            select DP_ID_DIRECCION 
                            from adm_gn_direcciones_persona
                            WHERE DP_CODIGO_TIPO_DIRECCION = 5
                            AND DP_ESTADO_DIRECCION IN ('ACT','ING')
                            AND DP_NUMERO_PERSONA = '$ma_numero_persona'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $id_direccion = oci_fetch_array($stid, OCI_ASSOC);

                $dp_id_direccion = $id_direccion["DP_ID_DIRECCION"];

                $query = "  select SGC.NOM_TRADUCE_DIRECCION($dp_id_direccion) as direccion
                            from dual";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $direccion_propietario = oci_fetch_array($stid, OCI_ASSOC);

                $detalle_matricula["DOMICILIO"] = $direccion_propietario["DIRECCION"];

                // Obtener la dirección del inmueble
                $zona = $detalle_matricula["MA_NUMERO_ZONA"];
                $manzana = $detalle_matricula["MA_NUMERO_MANZANA"];
                $predio = $detalle_matricula["MA_NUMERO_PREDIO"];

                $query = "  select nvl(pr_direccion_oficial,pr_direccion_ubicacion) as direccion
                            from cat_sc_predios
                            WHERE PR_NUMERO_ZONA = '$zona'
                            AND PR_NUMERO_MANZANA = '$manzana'
                            AND PR_NUMERO_PREDIO = '$predio'";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $id_direccion = oci_fetch_array($stid, OCI_ASSOC);

                $codigo_direccion = null;

                if (!$id_direccion) {
                    
                    $detalle_matricula["ID_DIRECCION"] = 'No existe';

                    $finca = $detalle_matricula["MA_FINCA"];
                    $folio = $detalle_matricula["MA_FOLIO"];
                    $libro = $detalle_matricula["MA_LIBRO"];
                    $tipo_denominacion = $detalle_matricula["MA_TIPO_DENOMINACION"];
                    $tipo_libro = $detalle_matricula["MA_TIPO_LIBRO"];
                    $literal_finca = $detalle_matricula["MA_LITERAL_FINCA"];
                    $literal_libro = $detalle_matricula["MA_LITERAL_LIBRO"];

                    $query = "  select nvl(fi_direccion_oficial,fi_direccion_ubicacion) as direccion
                                from cat_sc_fincas
                                WHERE FI_FINCA = '$finca'
                                AND FI_FOLIO = '$folio'
                                AND FI_LIBRO = '$libro'
                                AND FI_TIPO_DENOMINACION = '$tipo_denominacion'
                                AND FI_TIPO_LIBRO = '$tipo_libro'
                                AND FI_LITERAL_FINCA = '$literal_finca'
                                AND FI_LITERAL_LIBRO = '$literal_libro'";

                    $stid = oci_parse($this->dbConn, $query);
                    oci_execute($stid);
    
                    $id_direccion = oci_fetch_array($stid, OCI_ASSOC);

                    if ($id_direccion) {

                        $codigo_direccion = $id_direccion["DIRECCION"];

                    }
                    
                }else{

                    $codigo_direccion = $id_direccion["DIRECCION"];

                }

                // $codigo_direccion = $id_direccion["DIRECCION"];

                if ($codigo_direccion) {
                   
                    $query = "  select SGC.NOM_TRADUCE_DIRECCION($codigo_direccion) as direccion
                    from dual";

                    $stid = oci_parse($this->dbConn, $query);
                    oci_execute($stid);

                    $direccion_inmueble = oci_fetch_array($stid, OCI_ASSOC);

                    $detalle_matricula["DIRECCION"] = $direccion_inmueble["DIRECCION"];

                }else{

                    $detalle_matricula["DIRECCION"] = "NO DISPONIBLE";

                }

                // Obtener la bítacora 
                $query = "  select to_char(fecha_evento, 'dd/mm/yyyy hh24:mi:ss') as fecha, usuario, texto 
                            from sgc.MCA_BITACORA_GENERAL_VW
                            where matricula = '$matricula'
                            order by fecha_evento desc";

                $stid = oci_parse($this->dbConn, $query);
                oci_execute($stid);

                $bitacora = [];

                while ($data = oci_fetch_array($stid, OCI_ASSOC)) {

                    $bitacora [] = $data;

                }

                $data["detalle_matricula"] = $detalle_matricula;

                $data["items_bitacora"] = $bitacora;
                $data["headers_bitacora"] = [
                    [
                        "text" => "Fecha",
                        "value" => "FECHA",
                        "width" => "20%"
                    ],
                    [
                        "text" => "Descripción",
                        "value" => "TEXTO",
                        "width" => "60%"
                    ],
                    [
                        "text" => "Usuario",
                        "value" => "USUARIO",
                        "width" => "20%"
                    ]
                ];

            } catch (\Throwable $th) {
                //throw $th;
            }

            $this->returnResponse(SUCCESS_RESPONSE, $data);


        }

        public function buscarMatricula(){

            $busqueda = $this->param['busqueda'];

            try {
                
                $query = "
                
                select concat(ma_matricula, concat('-', ma_terminacion)) as matricula, to_char(ma_fecha_sistema, 'dd/mm/yyyy hh24:mi:ss') as fecha_inscripcion,
                ma_finca, ma_folio, ma_libro, ma_literal_libro, ma_tipo_libro, ma_numero_zona, ma_numero_manzana, ma_numero_predio, ma_numero_persona, 
                (select SGC.GN_NOMBRE_PERSONA(ma_numero_persona) from dual) as propietario, (select SGC.GN_DPI_NIT_PERSONA(ma_numero_persona) from dual) as cui
                from 
                (
                    select a.*, rownum r
                    from (
                        select *
                        from sgc.mat_ma_matricula
                        where concat(ma_matricula, concat('-', ma_terminacion)) like '%9%'
                        order by ma_fecha_sistema desc
                    ) a
                    where rownum < ((1 * 10) + 1)
                )
                where r >= (((1 - 1) * 10) + 1)";
                


            } catch (\Throwable $th) {
                
            }

        }
    }

?>