<?php

class SSP {
	/**
	* Crear la matriz de salida de datos para las filas de DataTables
	*
	* @param array $columns Matriz de información de columnas
	* @param array $data Se obtienen datos del SQL
	* @return array Datos formateados en un formato basado en filas
	*/
	static function data_output ( $columns, $data )
	{
		$out = array();

		for ( $i=0, $ien=count($data) ; $i<$ien ; $i++ ) {
			$row = array();

			for ( $j=0, $jen=count($columns) ; $j<$jen ; $j++ ) {
				$column = $columns[$j];

				// Validar si hay un formateador
				if ( isset( $column['formatter'] ) ) {
                    if(empty($column['db'])){
                        $row[ $column['dt'] ] = $column['formatter']( $data[$i] );
                    }
                    else{
                        $row[ $column['dt'] ] = $column['formatter']( $data[$i][ $column['db'] ], $data[$i] );
                    }
				}
				else {
                    if(!empty($column['db'])){
                        $row[ $column['dt'] ] = $data[$i][ $columns[$j]['db'] ];
                    }
                    else{
                        $row[ $column['dt'] ] = "";
                    }
				}
			}

			$out[] = $row;
		}

		return $out;
	}


	/**
	* Conexión a base de datos
	*
	* Obtener una conexión PHP PDO a partir de una matriz de detalles de conexión
	*
	* @param array $conn Detalles de conexión SQL. La matriz debería tener
	* las siguientes propiedades
	* * host - nombre del host
	* * db - nombre de la base de datos
	* * usuario - nombre de usuario
	* * pasar - contraseña de usuario
	* Conexión PDO de recurso @return
	*/
	static function db ( $conn )
	{
		if ( is_array( $conn ) ) {
			return self::sql_connect( $conn );
		}

		return $conn;
	}


	/**
	* paginación
	*
	* Construir la cláusula LIMIT para el procesamiento de consultas SQL del lado del servidor
	*
	* @param array $request Datos enviados al servidor por DataTables
	* @param array $columns Matriz de información de columnas
	* @return cadena cláusula de límite SQL
	*/
	static function limit ( $request, $columns )
	{
		$limit = '';

		if ( isset($request['start']) && $request['length'] != -1 ) {
			$limit = "LIMIT ".intval($request['start']).", ".intval($request['length']);
		}

		return $limit;
	}


	/**
	* Realizar ordenamiento
	*
	* Construir la cláusula ORDER BY para el procesamiento de consultas SQL del lado del servidor
	*
	* @param array $request Datos enviados al servidor por DataTables
	* @param array $columns Matriz de información de columnas
	* @return cadena SQL orden por cláusula
	*/
	static function order ( $request, $columns )
	{
		$order = '';

		if ( isset($request['order']) && count($request['order']) ) {
			$orderBy = array();
			$dtColumns = self::pluck( $columns, 'dt' );

			for ( $i=0, $ien=count($request['order']) ; $i<$ien ; $i++ ) {
				// Convert the column index into the column data property
				$columnIdx = intval($request['order'][$i]['column']);
				$requestColumn = $request['columns'][$columnIdx];

				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['orderable'] == 'true' ) {
					$dir = $request['order'][$i]['dir'] === 'asc' ?
						'ASC' :
						'DESC';

					$orderBy[] = '`'.$column['db'].'` '.$dir;
				}
			}

			if ( count( $orderBy ) ) {
				$order = 'ORDER BY '.implode(', ', $orderBy);
			}
		}

		return $order;
	}


	/**
	* Búsqueda / Filtrado
	*
	* Construya la cláusula WHERE para el procesamiento de consultas SQL del lado del servidor.
	*
	* NOTA: esto no coincide con el filtrado de DataTables incorporado que sí lo hace.
	* palabra por palabra en cualquier campo. Es posible hacer aquí el rendimiento en grandes
	* las bases de datos serían muy pobres
	*
	* @param array $request Datos enviados al servidor por DataTables
	* @param array $columns Matriz de información de columnas
	* @param array $bindings Matriz de valores para enlaces PDO, utilizados en el
	* función sql_exec()
	* @return string SQL donde cláusula
	*/

	static function filter ( $request, $columns, &$bindings )
	{
		$globalSearch = array();
		$columnSearch = array();
		$dtColumns = self::pluck( $columns, 'dt' );

		if ( isset($request['search']) && $request['search']['value'] != '' ) {
			$str = $request['search']['value'];

			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				if ( $requestColumn['searchable'] == 'true' ) {
					if(!empty($column['db'])){
						$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
						$globalSearch[] = "`".$column['db']."` LIKE ".$binding;
					}
				}
			}
		}

		// Filtrado de columnas individuales
		if ( isset( $request['columns'] ) ) {
			for ( $i=0, $ien=count($request['columns']) ; $i<$ien ; $i++ ) {
				$requestColumn = $request['columns'][$i];
				$columnIdx = array_search( $requestColumn['data'], $dtColumns );
				$column = $columns[ $columnIdx ];

				$str = $requestColumn['search']['value'];

				if ( $requestColumn['searchable'] == 'true' &&
				 $str != '' ) {
					if(!empty($column['db'])){
						$binding = self::bind( $bindings, '%'.$str.'%', PDO::PARAM_STR );
						$columnSearch[] = "`".$column['db']."` LIKE ".$binding;
					}
				}
			}
		}

		// Combina los filtros en una sola cadena
		$where = '';

		if ( count( $globalSearch ) ) {
			$where = '('.implode(' OR ', $globalSearch).')';
		}

		if ( count( $columnSearch ) ) {
			$where = $where === '' ?
				implode(' AND ', $columnSearch) :
				$where .' AND '. implode(' AND ', $columnSearch);
		}

		if ( $where !== '' ) {
			$where = 'WHERE '.$where;
		}

		return $where;
	}


	/**
	* Realizar las consultas SQL necesarias para un procesamiento del lado del servidor solicitado,
	* utilizando las funciones auxiliares de esta clase, limit(), order() y
	* filtro() entre otros. La matriz devuelta está lista para codificarse como JSON
	* en respuesta a una solicitud de SSP, o puede modificarse si es necesario antes
	*envío de vuelta al cliente.
	*
	* @param array $request Datos enviados al servidor por DataTables
	* @param array|PDO $conn Recurso de conexión PDO o matriz de parámetros de conexión
	* @param string $table Tabla SQL para consultar
	* @param string $primaryKey Clave primaria de la tabla
	* @param array $columns Matriz de información de columnas
	* @return array Matriz de respuesta de procesamiento del lado del servidor
	*/

	static function simple ( $request, $conn, $table, $primaryKey, $columns )
	{
		$bindings = array();
		$db = self::db( $conn );

		// Construye la cadena de consulta SQL a partir de la solicitud
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );

		// Consulta principal para obtener los datos.
		$data = self::sql_exec( $db, $bindings,
			"SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
		);

		// Longitud del conjunto de datos después del filtrado
		$resFilterLength = self::sql_exec( $db, $bindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
		);
		$recordsFiltered = $resFilterLength[0][0];

		// Longitud total del conjunto de datos
		$resTotalLength = self::sql_exec( $db,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`"
		);
		$recordsTotal = $resTotalLength[0][0];

		/*
		 * Salida
		 */
		return array(
			"draw"            => isset ( $request['draw'] ) ?
				intval( $request['draw'] ) :
				0,
			"recordsTotal"    => intval( $recordsTotal ),
			"recordsFiltered" => intval( $recordsFiltered ),
			"data"            => self::data_output( $columns, $data )
		);
	}


	/**
	* La diferencia entre este método y el `simple`, es que puedes
	* aplicar condiciones `dónde` adicionales a las consultas SQL. Estos pueden estar en
	* una de dos formas:
	*
	* * 'Condición de resultado': se aplica al conjunto de resultados, pero no al
	* consulta de información de paginación general, es decir, no afectará el número
	* de registros a los que un usuario ve que puede tener acceso. Esto debería ser
	*se utiliza cuando se quiere aplicar una condición de filtrado que el usuario ha enviado.
	* * 'Todas las condiciones' - Se aplica a todas las consultas que se realizan y
	* reduce la cantidad de registros a los que el usuario puede acceder. Esto debería ser
	* utilizado en condiciones en las que no desea que el usuario tenga acceso a
	* registros particulares (por ejemplo, restringir mediante una identificación de inicio de sesión).
	*
	* @param array $request Datos enviados al servidor por DataTables
	* @param array|PDO $conn Recurso de conexión PDO o matriz de parámetros de conexión
	* @param string $table Tabla SQL para consultar
	* @param string $primaryKey Clave primaria de la tabla
	* @param array $columns Matriz de información de columnas
	* @param string $whereResult WHERE condición que se aplicará al conjunto de resultados
	* @param string $whereAll WHERE condición que se aplicará a todas las consultas
	* @return array Matriz de respuesta de procesamiento del lado del servidor
	*/
	static function complex ( $request, $conn, $table, $primaryKey, $columns, $whereResult=null, $whereAll=null )
	{
		$bindings = array();
		$db = self::db( $conn );
		$localWhereResult = array();
		$localWhereAll = array();
		$whereAllSql = '';

		// Construye la cadena de consulta SQL a partir de la solicitud
		$limit = self::limit( $request, $columns );
		$order = self::order( $request, $columns );
		$where = self::filter( $request, $columns, $bindings );

		$whereResult = self::_flatten( $whereResult );
		$whereAll = self::_flatten( $whereAll );

		if ( $whereResult ) {
			$where = $where ?
				$where .' AND '.$whereResult :
				'WHERE '.$whereResult;
		}

		if ( $whereAll ) {
			$where = $where ?
				$where .' AND '.$whereAll :
				'WHERE '.$whereAll;

			$whereAllSql = 'WHERE '.$whereAll;
		}

		// Consulta principal para obtener los datos.
		$data = self::sql_exec( $db, $bindings,
			"SELECT `".implode("`, `", self::pluck($columns, 'db'))."`
			 FROM `$table`
			 $where
			 $order
			 $limit"
		);

		// Longitud del conjunto de datos después del filtrado
		$resFilterLength = self::sql_exec( $db, $bindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table`
			 $where"
		);
		$recordsFiltered = $resFilterLength[0][0];

		// Longitud total del conjunto de datos
		$resTotalLength = self::sql_exec( $db, $bindings,
			"SELECT COUNT(`{$primaryKey}`)
			 FROM   `$table` ".
			$whereAllSql
		);
		$recordsTotal = $resTotalLength[0][0];

		/*
		 * Salida
		 */
		return array(
			"draw"            => isset ( $request['draw'] ) ?
				intval( $request['draw'] ) :
				0,
			"recordsTotal"    => intval( $recordsTotal ),
			"recordsFiltered" => intval( $recordsFiltered ),
			"data"            => self::data_output( $columns, $data )
		);
	}

	 /**
	* Conectarse a la base de datos
	*
	* @param array $sql_details Matriz de detalles de conexión del servidor SQL, con las
	*   propiedades:
	*     * host - host name
	*     * db   - database name
	*     * user - user name
	*     * pass - user password
	* @return resource Identificador de conexión de la base de datos
	*/

	static function sql_connect ( $sql_details )
	{
		try {
			$db = @new PDO(
				"mysql:host={$sql_details['host']};dbname={$sql_details['db']}",
				$sql_details['user'],
				$sql_details['pass'],
				array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
			);
		}
		catch (PDOException $e) {
			self::fatal(
				"Se produjo un error al conectarse a la base de datos".
				"El error reportado por el servidor fue: ".$e->getMessage()
			);
		}

		return $db;
	}

	/**
	* Ejecutar una consulta SQL sobre la base de datos.
	*
	* @param resource $db Controlador de base de datos
	* @param array $bindings arrays de valores de enlace PDO de bind() a ser
	* utilizado para limpiar strings de forma segura. Tenga en cuenta que esto se puede dar como
	* Cadena de consulta SQL si no se requieren enlaces.
	* @param string $sql Consulta SQL a ejecutar.
	* @return array Resultado de la consulta (todas las filas)
	*/
	static function sql_exec ( $db, $bindings, $sql=null )
	{
		// Cambio de argumento
		if ( $sql === null ) {
			$sql = $bindings;
		}

		$stmt = $db->prepare( $sql );
		//echo $sql;

		// Parametros de enlace
		if ( is_array( $bindings ) ) {
			for ( $i=0, $ien=count($bindings) ; $i<$ien ; $i++ ) {
				$binding = $bindings[$i];
				$stmt->bindValue( $binding['key'], $binding['val'], $binding['type'] );
			}
		}

		// Ejecutar
		try {
			$stmt->execute();
		}
		catch (PDOException $e) {
			self::fatal( "Se produjo un error de SQL: ".$e->getMessage() );
		}

		// Return all
		return $stmt->fetchAll( PDO::FETCH_BOTH );
	}

	 /* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	* Métodos internos
	*/

	/**
	* Lanza un error fatal.
	*
	* Esto escribe un mensaje de error en una cadena JSON que DataTables
	* ver y mostrar al usuario en el navegador.
	*
	* @param string $msg Mensaje para enviar al cliente
	*/

	static function fatal ( $msg )
	{
		echo json_encode( array( 
			"error" => $msg
		) );

		exit(0);
	}

	 /**
	* Cree una clave de enlace PDO que pueda usarse para escapar de variables de forma segura
	* al ejecutar una consulta con sql_exec()
	*
	* @param array &$a Matriz de enlaces
	* @param * $val Valor a vincular
	* @param int $type Tipo de campo PDO
	* @return string Clave vinculada que se utilizará en el SQL donde se encuentra este parámetro
	*   sera usado.
	*/
	static function bind ( &$a, $val, $type )
	{
		$key = ':binding_'.count( $a );

		$a[] = array(
			'key' => $key,
			'val' => $val,
			'type' => $type
		);

		return $key;
	}

	/**
	* Extraer una propiedad particular de cada asociación. matriz en una matriz numérica,
	* devolución y matriz de los valores de propiedad de cada elemento.
	*
	* @param array $a Array del que obtener datos
	* @param string $prop Propiedad a leer
	* @return array Matriz de valores de propiedad
	*/
	static function pluck ( $a, $prop )
	{
		$out = array();

		for ( $i=0, $len=count($a) ; $i<$len ; $i++ ) {
            if(empty($a[$i][$prop])){
                continue;
			}
			//eliminar el índice de la matriz $out confunde al método de filtro al realizar el enlace adecuado,
			//agregarlo asegura que los datos de la matriz estén asignados correctamente
			$out[$i] = $a[$i][$prop];
		}

		return $out;
	}
	/**
	* Devuelve una cadena de una matriz o una cadena
	*
	* @param array|string $a Array para unir
	* @param string $join Pegamento para la concatenación
	* @return string Cadena unida
	*/
	static function _flatten ( $a, $join = ' AND ' )
	{
		if ( ! $a ) {
			return '';
		}
		else if ( $a && is_array($a) ) {
			return implode( $join, $a );
		}
		return $a;
	}
}