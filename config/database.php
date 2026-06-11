<?php

$host = "localhost";
$dbname = "adeptio_db";
$user = "root";
$password = "";

try{

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8",
        $user,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

}catch(PDOException $e){

    die("Erreur connexion : ".$e->getMessage());

}
?>
