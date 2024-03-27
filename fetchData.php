<?php
include_once 'dbConnect.php';
// Database connection info
$dbDetails = array(
    'host' => DB_HOST,
    'user' => DB_USER,
    'pass' => DB_PASS,
    'db'   => DB_NAME
);
// tabla de base de datos a utilizar
$table = 'members';
// clave principal de la tabla
$primaryKey = 'id';
// Matriz de columnas de la base de datos que deben leerse y enviarse de vuelta a DataTables.
// El parámetro `db` representa el nombre de la columna en la base de datos.
// El parámetro `dt` representa el identificador de la columna DataTables.
$columns = array(
    array( 'db' => 'first_name', 'dt' => 0 ),
    array( 'db' => 'last_name',  'dt' => 1 ),
    array( 'db' => 'email',      'dt' => 2 ),
    array( 'db' => 'gender',     'dt' => 3 ),
    array( 'db' => 'country',    'dt' => 4 ),
    array(
        'db'        => 'created',
        'dt'        => 5,
        'formatter' => function( $d, $row ) {
            return date( 'jS M Y', strtotime($d));
        }
    ),
    array(
        'db'        => 'status',
        'dt'        => 6,
        'formatter' => function( $d, $row ) {
            return ($d == 1)?'Active':'Inactive';
        }
    ),
    array(
        'db'        => 'id',
        'dt'        => 7,
        'formatter' => function( $d, $row ) {
            return '
                <a href="javascript:void(0);" class="btn btn-warning" onclick="editData('.htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8').')">Editar</a>&nbsp;
                <a href="javascript:void(0);" class="btn btn-danger" onclick="deleteData('.$d.')">Eliminar</a>
            ';
        }
    )
);

// Incluir clase de procesamiento de consultas SQL
require 'ssp.class.php';

// Salida de datos en formato json
echo json_encode(
    SSP::simple( $_GET, $dbDetails, $table, $primaryKey, $columns )
);