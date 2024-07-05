<?php // -- \\

// Header HTML externe
include("webPart/header.php");

// Récupère identifiant de connexion à la db
$config = require "config.php";

// Initialise l'URL de l'API
$curl = curl_init('https://trefle.io/api/v1/plants?token=Egs6F25fX1PiCEq4CdcyqMF3G8SmdV0hS9I-UYSKl8w');

curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);

// Désactive la vérification SSL
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

// Exécute l'URL de l'API
$data = curl_exec($curl);

if (curl_errno($curl)) {
    // Affiche un message d'erreur
    echo 'Erreur cURL : ' . curl_error($curl);
} else {
    // Décodage du JSON en tableau associatif
    $JSON = json_decode($data, true);

    // Initialisation d'un tableau pour stocker les noms scientifiques
    $scientific_names = [];

    // Parcourir les données et extraire les noms scientifiques
    foreach ($JSON['data'] as $item) {
        $scientific_names[] = $item['scientific_name'];
    }

    // Afficher les noms scientifiques
    // print_r($scientific_names);

    try {
        // Connexion à la base de données en utilisant PDO
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8",
            $config['username'],
            $config['password']
        );

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Requête SQL pour vérifier l'existence d'une fleur
        $check_sql = "
            SELECT COUNT(*) FROM flowers WHERE scientific_name = :scientific_name
        ";

        // Requête SQL pour ajouter les données à la db
        $insert_sql = "
            INSERT INTO flowers (scientific_name, common_name, family, image_url)
            VALUES (:scientific_name, :common_name, :family, :image_url)
        ";

        // Préparation des requêtes
        $check_stmt = $pdo->prepare($check_sql);
        $insert_stmt = $pdo->prepare($insert_sql);

        // Parcourir les données et insérer dans la base de données si elles n'existent pas déjà
        foreach ($JSON['data'] as $item) {
            // Vérifier l'existence de la fleur
            $check_stmt->execute([':scientific_name' => $item['scientific_name']]);
            $exists = $check_stmt->fetchColumn();

            if (!$exists) {
                // Insérer la fleur si elle n'existe pas
                $insert_stmt->execute([
                    ':scientific_name' => $item['scientific_name'],
                    ':common_name' => $item['common_name'],
                    ':family' => $item['family'],
                    ':image_url' => $item['image_url']
                ]);
            }
        }

        echo "Données insérées avec succès.";
    } catch (PDOException $e) {
        echo "Erreur : " . $e->getMessage();
    }
}

curl_close($curl);

?>

<!doctype html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Liste déroulante de fleurs</title>
</head>
<body>
<form method="POST" action="">
    <label for="flowers">Fleurs :</label>
    <select name="flowers" id="flowers">
        <?php
        // Requête pour sélectionner les fleurs depuis la base de données
        $sql = "SELECT id, common_name FROM flowers";
        $result = $pdo->query($sql);

        // Vérifiez si des résultats ont été retournés
        if ($result->rowCount() > 0) {
            // Parcourez chaque ligne de résultats
            while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                echo '<option value="' . $row['id'] . '">' . $row['common_name'] . '</option>';
            }
        } else {
            echo '<option value="">Aucun résultat trouvé</option>';
        }
        ?>
    </select>
    <input type="submit" value="Afficher l'image">
</form>

<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["flowers"])) {
    $flower_id = $_POST["flowers"];

    // Requête pour récupérer l'URL de l'image correspondant à l'ID sélectionné
    $sql = "SELECT image_url FROM flowers WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $flower_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo '<img src="' . $row['image_url'] . '" alt="Image de la fleur">';
    } else {
        echo 'Image non trouvée pour l\'ID de fleur sélectionné.';
    }
}


// Footer HTML externe
include('webPart/footer.php');

