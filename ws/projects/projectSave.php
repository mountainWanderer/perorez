<?php
	require_once('../parameters.php');

	class PENKNIFEResponse {
		var $success;
		var $message;
		var $data = [];
	}
	$response = new PENKNIFEResponse;
	
	// Create connection
	$conn = new mysqli(SERVERNAME, USERNAME_DB, PWD_DB, DBNAMEMAIN);
	// Check connection
	if ($conn->connect_error) {
// 		die("Connection failed: " . $conn->connect_error);
		$response->success = false;
		$response->message = "Error: " . $conn->connect_error;
	} 

	$data = json_decode( file_get_contents('php://input') );
    
	//TODO controlli validita
	
	$idRecordForDescription = !$data -> id ? null : $data -> id;

	if( !$data -> id ) {	//INSERT
		$stmt = $conn->prepare(
			"INSERT INTO project (
				nomeprogetto, aziendaideatrice, settore,
				image_1, image_2, image_3, creator
			)
			VALUES ( ?, ?, ?, ?, ?, ?, ? )"
		);
		
		$stmt->bind_param("ssisssi",
			$data->nomeprogetto, $data->aziendaideatrice, $data->settore,
            $data->image_1, $data->image_2, $data->image_3, $data->creator
		);
		
		if ($stmt->execute()) {
			if ($stmt->insert_id) {
				$idRecordForDescription = $stmt->insert_id;
				array_push( $response->data, $stmt->insert_id);
				$response->message = 'Inserimento eseguito con successo!';
				$response->success = true;
			}
		} else {
			$response->success = false;
			$response->message = "Error: " . $conn->error;
		}
		
		$stmt->close();
	}else {	//UPDATE
		$idRecordForDescription = $data -> id;

		$stmtUpdate = $conn->prepare(
			"UPDATE project 
				SET
					nomeprogetto = ?,
					aziendaideatrice = ?,
					settore = ?,
					image_1 = ?,
					image_2 = ?,
					image_3 = ?
				WHERE id = ?
		");
		
		$stmtUpdate->bind_param("ssisssi",
			$data->nomeprogetto, $data->aziendaideatrice, $data->settore,
            $data->image_1, $data->image_2, $data->image_3, $idRecordForDescription
		);
		
		if ($stmtUpdate->execute()) {
			$response->message = 'Modifica eseguita con successo!';
			$response->success = true;
		} else {
			$response->success = false;
			$response->message = "Error: " . $conn->error;
		}
		
		$stmtUpdate->close();
	}

	//sezione salvataggio descrizioni
	if ($idRecordForDescription) {
		$languages = array('zh', 'it', 'en');
		$langFields = array($data->descrchina, $data->descritaly, $data->descrenglish);

		for ($i=0; $i < 3; $i++) { 
			$descriptionSelectSuccess = false;
			$descriptionUpdate = false;

			//verifico esistenza record
			$stmt = $conn->prepare("
				SELECT a.id
				FROM projectdescriptions a
				WHERE a.id_project = ?
				AND a.language = ?
			");
			$stmt->bind_param("is", $idRecordForDescription, $languages[$i]);

			if ($stmt->execute()) {
				$descriptionSelectSuccess = true;

				/* bind variables to prepared statement */
				mysqli_stmt_bind_result($stmt, $colId);
				
				/* fetch values */
				while (mysqli_stmt_fetch($stmt)) {
					$descriptionUpdate = $colId;
				}

			}
			$stmt->close();
			//FINE verifica esistenza record

			if ($descriptionSelectSuccess) {
				//se gia' esiste la descrizione si fa update ELSE insert
				if ($descriptionUpdate) {
					//UPDATE
					$stmt = $conn->prepare(
						"UPDATE projectdescriptions 
							SET description = ?
							WHERE id = ?
					");
					
					$stmt->bind_param("si",
						$langFields[$i], $descriptionUpdate
					);
				} else {
					//INSERT
					$stmt = $conn->prepare(
						"INSERT INTO projectdescriptions (
							id_project, language, description
						)
						VALUES ( ?, ?, ? )"
					);					
					$stmt->bind_param("iss", $idRecordForDescription, $languages[$i], $langFields[$i]);
				}

				$stmt->execute();
				$stmt->close();
			}
		}
	}


	$conn->close();
	echo json_encode($response);
?>