<?php
// =======================================
// Charneco Samuel
// M152 - I.DA-P4A
// =======================================

if(session_status() == PHP_SESSION_NONE){
    session_start();
}

// Création de l'object PDO (Singleton)
// Utilisation du même connecteur pour chaque requêtes
function ConnectDB(){
    static $db = null;

    if($db == null){
        try{
            $db = new PDO('mysql:host=localhost;dbname=m152;port=3306','root','root');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        catch(PDOException $e) {
            die('Échec lors de la connexion : ' . $e->getMessage());
        }
    }
    return $db;
}

// Insert les données du post dans la base de données
function addPost($commentaire, $filesContent){
    $db = ConnectDB();   
    $uniqId = uniqid();
    // Table post
    try{
        // permet d'annuler les requêtes executé en cas d'erreur (empêche les données orphelin)
        $db->beginTransaction();
        $sql = "INSERT INTO post (`commentaire`, `creationDate`, `modificationDate`) VALUES(:commentaire, :dateCrea, :dateModif)";
        $req = $db->prepare($sql, array(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL));
        $req->execute(
        array(
            'commentaire' => $commentaire,
            'dateCrea' => date("Y-m-d H:i:s"),
            'dateModif' => date("Y-m-d H:i:s")
            )
        );
        $id = $db->lastInsertId();
        echo $id . "lastinsertid";
        $_SESSION["lastInsertId"] = $id;

        // Table media
        $sql = "INSERT INTO media (`typeMedia`, `nomMedia`, `creationDate`, `modificationDate`, `post_idPost`) VALUES(:typeMedia, :nomMedia, :dateCrea, :dateModif, :post)";
        foreach ($filesContent["userFiles"]["error"] as $key => $error) {
            $req = $db->prepare($sql, array(PDO::ATTR_CURSOR, PDO::CURSOR_SCROLL));
            $uniqueFileName = $uniqId.$filesContent["userFiles"]["name"][$key];
            $uploadFolder = 'uploadedFiles';
            $req->execute(
                array(
                'typeMedia' => $filesContent["userFiles"]["type"][$key],
                'nomMedia' => $uniqueFileName,
                'dateCrea' => date("Y-m-d H:i:s"),
                'dateModif' => date("Y-m-d H:i:s"),
                'post' => $id
                )
            );
            if(!move_uploaded_file($filesContent["userFiles"]["tmp_name"][$key], "$uploadFolder/$uniqueFileName")){
                throw new Exception();
            }
            else{
                header("Location: index.php");
            }
        }
        
        // Confirme l'exécution des requêtes
       $db->commit(); 
    }
    catch (Exception $e){
        // Annule toute les requête situé entre "beginTransaction" et "commit"
        $db->rollback();
    }     
}

function GetIdPost(){
    $db = ConnectDB();
    $sql = $db->prepare("SELECT idPost FROM post");
    $sql->execute();
    $result = $sql->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}

function GetPost(){
    $db = ConnectDB();  
    $sql = $db->prepare("SELECT idPost, nomMedia, typeMedia, commentaire FROM media JOIN post ON media.post_idPost = post.idPost");
    $sql->execute();
    $result = $sql->fetchAll(PDO::FETCH_ASSOC);
    return $result;
}

function PostForm(){
    $post = GetPost();   
    $div = "<div class=\"col-sm-8\"><div class=\"well\"><div class=\"input-group text-center\" name=\"divImg\">";
    $currentPostId = 0;
    foreach ($post as $key => $value) {
      if($currentPostId != $value["idPost"]){
            $div .= "<h4>".$value["commentaire"]."</h4>";
            $div .= "<input type=\"button\" name=\"btnEdit\" value=\"Modifier\"><input type=\"button\" name=\"btnDelete\" value=\"Supprimer\"><br>";
            $currentPostId = $value["idPost"];                
        }
        if($value["typeMedia"] == "video/mp4"){
            $div .= "<br><video autoplay=\"true\" loop=\"true\" controls><source src=\"uploadedFiles/".$value["nomMedia"]."\" name=\"videoPost\"/></video>";
        }
        elseif($value["typeMedia"] == "audio/mp3"){
            $div .= "<audio controls><source src=\"uploadedFiles/".$value["nomMedia"]."\" name=\"audioPost\"/></audio>";
        }
        else{
            $div .= "<img src=\"uploadedFiles/".$value["nomMedia"]."\" name=\"imgPost\">";           
        }        
    }       
    $div .="</div></div></div>";   
    return $div;
}

// Appelle la méthode qui envoie le post dans la base de données
if(filter_has_var(INPUT_POST, "btnPost")){
    $commentaire = filter_input(INPUT_POST, "txtaCommentaire", FILTER_SANITIZE_STRING);
    /*$typeMedia = $_FILES['userFiles']['type'];
    $nomMedia = $_FILES['userFiles']['name'];  */    
    addPost($commentaire, $_FILES);

    // Pour vérifier le type de fichier réel (Lit dans le fichier)
    // Si .exe est renommé en .pdf -> mime_content_type sait que c'est un .exe
    /*if(mime_content_type($_FILES['userFiles']['type']) == "image/jpg"){                
        header("Location: index.php");
    }*/ 
}