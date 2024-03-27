<?php     
require_once 'dbConnect.php';

// Recuperar JSON de POST
$jsonStr = file_get_contents('php://input');
$jsonObj = json_decode($jsonStr);

if($jsonObj->request_type == 'addEditUser'){
    $user_data = $jsonObj->user_data;
    $first_name = !empty($user_data[0])?$user_data[0]:'';
    $last_name = !empty($user_data[1])?$user_data[1]:'';
    $email = !empty($user_data[2])?$user_data[2]:'';
    $gender = !empty($user_data[3])?$user_data[3]:'';
    $country = !empty($user_data[4])?$user_data[4]:'';
    $status = !empty($user_data[5])?$user_data[5]:0;
    $id = !empty($user_data[6])?$user_data[6]:0;

    $err = '';
    if(empty($first_name)){
        $err .= 'Por favor, introduzca su nombre.<br/>';
    }
    if(empty($last_name)){
        $err .= 'Por favor ingrese su apellido.<br/>';
    }
    if(empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)){
        $err .= 'Por favor, introduce una dirección de correo electrónico válida.<br/>';
    }
    
    if(!empty($user_data) && empty($err)){
        if(!empty($id)){
            //Actualizar los datos del usuario en la base de datos.
            $sqlQ = "UPDATE members SET first_name=?,last_name=?,email=?,gender=?,country=?,status=?,modified=NOW() WHERE id=?";
            $stmt = $conn->prepare($sqlQ);
            $stmt->bind_param("sssssii", $first_name, $last_name, $email, $gender, $country, $status, $id);
            $update = $stmt->execute();

            if($update){
                $output = [
                    'status' => 1,
                    'msg' => 'Actualizacion exitosa!'
                ];
                echo json_encode($output);
            }else{
                echo json_encode(['error' => '¡La solicitud de actualización fallida!']);
            }
        }else{
            // Insertar datos 
            $sqlQ = "INSERT INTO members (first_name,last_name,email,gender,country,status) VALUES (?,?,?,?,?,?)";
            $stmt = $conn->prepare($sqlQ);
            $stmt->bind_param("sssssi", $first_name, $last_name, $email, $gender, $country, $status);
            $insert = $stmt->execute();

            if($insert){
                $output = [
                    'status' => 1,
                    'msg' => '¡Nuevo registro agregado exitosamente!'
                ];
                echo json_encode($output);
            }else{
                echo json_encode(['error' => 'Error en la solicitud de insercion!']);
            }
        }
    }else{
        echo json_encode(['error' => trim($err, '<br/>')]);
    }
}elseif($jsonObj->request_type == 'deleteUser'){
    $id = $jsonObj->user_id;
// Eliminacion de los datos
    $sql = "DELETE FROM members WHERE id=$id";
    $delete = $conn->query($sql);
    if($delete){
        $output = [
            'status' => 1,
            'msg' => 'Registro eliminado existosamente!'
        ];
        echo json_encode($output);
    }else{
        echo json_encode(['error' => 'Solicitud de eliminación fallida!']);
    }
}