<?php

class ImportDatabase
{
    private $nomFichier = "data.csv";
    public $conn;

    public function connect()
    {
        // 1. Configuration de la connexion à la base de données
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "db_zawaj24";

        // 2. Connexion à la base de données
        $conn = new mysqli($servername, $username, $password, $dbname);
        if ($conn->connect_error) {
            die("La connexion à la base de données a échoué : " . $conn->connect_error);
        }
        $this->conn = $conn;
    }

    // 3. Fonction de lecture du fichier CSV
    public function read_file()
    {
        $donnees = [];
        $handle = fopen($this->nomFichier, "r");
        while (($data = fgetcsv($handle, 0, ",")) !== false) {
            $donnees[] = $data;
        }
        fclose($handle);
        return $donnees;
    }

    public function migrate($donnees)
    {
        $conn = $this->conn;
        $email = "example@gmail.com";
        $password = "$2y$10$2YN4GPKtRETfWbdQuGTnhOmWYrfwsp.CJTtRojoCtOwCeOqOaCwty";
        $membership = 1;
        $user_type = "member";
        $approved = 1;

        // Gérer le code
        $codeQuery = "SELECT MAX(code) as max_code from users";
        $result = $conn->query($codeQuery);
        $row = $result->fetch_assoc();
        $lastCode = $row['max_code'];

        if ($lastCode !== null) {
            $newCode = $lastCode + 1;
        } else {
            $newCode = 1;
        }

        // 5. Controle imported data
        // 6. Ignorer la première ligne (en-têtes des colonnes CSV)
        $donnees = array_slice($donnees, 1);

        $main_path = "/storage/";

        foreach ($donnees as $ligne) {
            // Vérifier et traiter les champs vides
            $verifiechamps = array_map(function ($value) {
                return !empty($value) ? $value : null;
            }, $ligne);

            // 7. Construction de la requête SQL pour l'insertion
            $first_name = isset($verifiechamps[0]) ? $verifiechamps[0] : null;
            $last_name = isset($verifiechamps[1]) ? $verifiechamps[1] : null;
            $email = isset($verifiechamps[2]) ? $verifiechamps[2] : null;
            $photo = isset($verifiechamps[3]) ? $verifiechamps[3] : null;

            if (!empty($photo)) {
                $filename = $this->uploadimage($photo);
                if (!empty($filename)) {
                    $photo = $main_path . $filename;
                }
            }

            $user_id = isset($verifiechamps[4]) ? $verifiechamps[4] : null;
            $gender = isset($verifiechamps[5]) ? $verifiechamps[5] : null;
            $on_behalves_id = isset($verifiechamps[6]) ? $verifiechamps[6] : null;

            // 8. Insertion dans la table "users"
            if (preg_match('/@gmail\.com$/', $email)) {
                $query = "INSERT INTO users (user_type, membership, code, first_name, last_name, email, password, photo, approved) VALUES ('$user_type', '$membership', '$newCode', '$first_name', '$last_name', '$email', '$password', '$photo', '$approved')";

                // Exécuter la requête SQL
                if ($conn->query($query) === false) {
                    echo "Erreur lors de l'insertion dans la table users : " . $conn->error . "<br>";
                } else {
                    // Mise à jour du dernier code inséré
                    $lastCode = $newCode;
                    $newCode++; // Incrémenter la valeur pour la prochaine insertion
                }

                // 10. Récupérer l'ID de la dernière insertion dans la table users
                $user_id = $conn->insert_id;

                if (!empty($user_id)) {
                    // 10. Insertion dans la table "members"
                    $query = "INSERT INTO members (user_id, gender, on_behalves_id) VALUES ('$user_id', '$gender', '$on_behalves_id')";

                    // Exécuter la requête SQL
                    if ($conn->query($query) === false) {
                        echo "Erreur lors de l'insertion dans la table members : " . $conn->error . "<br>";
                    }
                }
            } else {
                // L'adresse e-mail ne contient pas '@gmail.com'
                echo "L'adresse e-mail doit être de la forme '@gmail.com'.<br>";
            }
        }

        echo "Migration des données terminée.";
    }

    public function uploadimage($photo)
    {
        $data = @file_get_contents($photo);

        if (empty($data)) {
            $ch = curl_init($photo);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            $data = curl_exec($ch);
            curl_close($ch);
        }

        if (empty($data)) {
            return "";
        }
        $main_path = "storage/";

        // Vérifier si le dossier de storage existe, sinon créer le dossfrie
        if (!file_exists($main_path) && !is_dir($main_path)) {
            mkdir($main_path, 0755, true);
        }

        $ext_file = pathinfo($photo, PATHINFO_EXTENSION);
        $ext_file = strtolower($ext_file);

        if (!in_array($ext_file, array('png', 'jpeg', 'jpg', 'gif', 'svg'))) {
            $ext_file = 'jpg';
        }

        $filename = uniqid(time()) . '.' . $ext_file;
        if (empty($filename)) {
            echo "Erreur lors de l'enregistrement du fichier.";
        }

        file_put_contents($main_path . $filename, $data);

        return $filename;
    }
}

// Utilisation de la classe ImportDatabase
$importDatabase = new ImportDatabase();
$resultat = $importDatabase->read_file();

$importDatabase->connect();
$importDatabase->migrate($resultat);
$importDatabase->conn->close();
?>
