<?php
require 'config/database.php';
$itemsPerPage=5; $currentPage=1; $offset=0;
$sql="SELECT a.*, COALESCE(p.first_name, a.first_name, '') AS first_name, COALESCE(p.middle_name, a.middle_name, '') AS middle_name, COALESCE(p.last_name, a.last_name, '') AS last_name FROM appointments a LEFT JOIN patients p ON a.patient_id = p.id ORDER BY a.appointment_date DESC, a.id DESC LIMIT :limit OFFSET :offset";
$stmt=$pdo->prepare($sql);
$stmt->bindValue(':limit',$itemsPerPage,PDO::PARAM_INT);
$stmt->bindValue(':offset',$offset,PDO::PARAM_INT);
$stmt->execute();
$appointments=$stmt->fetchAll(PDO::FETCH_ASSOC);
var_export($appointments);
?>
