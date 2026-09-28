<?php
session_start();

require __DIR__."/../incl/dashboardLib.php";
require __DIR__."/../../incl/lib/connection.php";

$dl = new dashboardLib();
$dl->title("Badges");

$q = $db->prepare("SELECT isAdmin FROM accounts WHERE accountID = :id");
$q->execute([":id" => $_SESSION["accountID"] ?? 0]);

if((int)$q->fetchColumn() !== 1){
    $dl->printPage('<div class="gd-card"><h1>Badges</h1><p>Administrator access required.</p></div>', true, "badges");
    $dl->printFooter("../");
    exit;
}

if(!isset($_SESSION["gdips_badges_csrf"])) $_SESSION["gdips_badges_csrf"] = bin2hex(random_bytes(32));

$configPath = __DIR__."/../../config/badges.php";
$uploadDir = __DIR__."/../incl/badges";

if(!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

$defaults = [
    1 => ["name"=>"Moderator","image"=>"https://raw.githubusercontent.com/Fenix668/GMDprivateServer/master/dashboard/modBadge_01_001.png"],
    2 => ["name"=>"Senior Moderator","image"=>"https://raw.githubusercontent.com/Fenix668/GMDprivateServer/master/dashboard/modBadge_02_001.png"],
    3 => ["name"=>"Administrator","image"=>"https://raw.githubusercontent.com/Fenix668/GMDprivateServer/master/dashboard/modBadge_03_001.png"]
];

$roleBadges = $defaults;
if(file_exists($configPath)) require $configPath;
if(!is_array($roleBadges)) $roleBadges=$defaults;

$h = static function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, "UTF-8"); };
$notice="";
$error="";

if($_SERVER["REQUEST_METHOD"]==="POST"){
    if(!hash_equals($_SESSION["gdips_badges_csrf"],(string)($_POST["csrf"]??""))){
        $error="Security token expired. Reload the page.";
    }else{
        try{
            for($level=1;$level<=3;$level++){
                $name=trim((string)($_POST["badge_name"][$level]??""));
                $image=trim((string)($_POST["badge_image"][$level]??""));

                if($name===""||strlen($name)>80) throw new RuntimeException("Badge ".$level." needs a name of 1-80 characters.");
                if(strlen($image)>1000) throw new RuntimeException("Badge ".$level." image URL is too long.");

                if(isset($_POST["badge_clear"][$level])) $image="";

                $fileKey="badge_file_".$level;
                if(isset($_FILES[$fileKey]) && (int)$_FILES[$fileKey]["error"]!==UPLOAD_ERR_NO_FILE){
                    if((int)$_FILES[$fileKey]["error"]!==UPLOAD_ERR_OK) throw new RuntimeException("Badge ".$level." upload failed.");
                    if((int)$_FILES[$fileKey]["size"]>1048576) throw new RuntimeException("Badge ".$level." image is larger than 1 MB.");

                    $info=@getimagesize($_FILES[$fileKey]["tmp_name"]);
                    if($info===false) throw new RuntimeException("Badge ".$level." is not a valid image.");

                    $types=[
                        "image/png"=>"png",
                        "image/jpeg"=>"jpg",
                        "image/webp"=>"webp",
                        "image/gif"=>"gif"
                    ];

                    $mime=$info["mime"]??"";
                    if(!isset($types[$mime])) throw new RuntimeException("Badge ".$level." must be PNG, JPG, WEBP or GIF.");
                    if((int)$info[0]>1024||(int)$info[1]>1024) throw new RuntimeException("Badge ".$level." dimensions are too large.");

                    $filename="role-badge-".$level."-".bin2hex(random_bytes(6)).".".$types[$mime];
                    if(!move_uploaded_file($_FILES[$fileKey]["tmp_name"],$uploadDir.DIRECTORY_SEPARATOR.$filename)){
                        throw new RuntimeException("Could not store badge ".$level.".");
                    }

                    $image="/dashboard/incl/badges/".$filename;
                }

                $roleBadges[$level]=["name"=>$name,"image"=>$image];
            }

            if(!is_writable($configPath)) throw new RuntimeException("Badge configuration file is not writable by PHP.");

            $source="<?php\n\n\$roleBadges = ".var_export($roleBadges,true).";\n";
            $tmp=$configPath.".tmp.".bin2hex(random_bytes(4));

            if(file_put_contents($tmp,$source,LOCK_EX)===false || !@rename($tmp,$configPath)){
                @unlink($tmp);
                throw new RuntimeException("Could not save badge configuration.");
            }

            $notice="Badge settings saved.";
        }catch(Throwable $e){
            $error=$e->getMessage();
        }
    }
}

$content='<div class="gd-pagehead">
    <p class="gd-eyebrow">ADMIN</p>
    <div class="gd-pagehead-row"><div>
        <h1 class="gd-display">Badge manager</h1>
        <p class="gd-pagehead-sub">Configure the three badge levels used by moderator roles.</p>
    </div></div>
</div>';

if($notice!=="") $content.='<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>'.$h($notice).'</strong></div>';
if($error!=="") $content.='<div class="gd-card" style="margin-bottom:var(--sp-4);"><strong>Save failed:</strong> '.$h($error).'</div>';

$content.='<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="'.$h($_SESSION["gdips_badges_csrf"]).'">
<div class="gd-grid gd-grid--3">';

for($level=1;$level<=3;$level++){
    $badge=$roleBadges[$level]??$defaults[$level];
    $content.='<div class="gd-card">
        <strong>Badge '.$level.'</strong>
        <div style="display:flex;justify-content:center;padding:var(--sp-4) 0;">
            <img src="'.$h($badge["image"]??"").'" alt="'.$h($badge["name"]??"").'" style="width:72px;height:72px;object-fit:contain;">
        </div>
        <div class="form-group">
            <label><strong>Name</strong></label>
            <input class="form-control gd-input" name="badge_name['.$level.']" value="'.$h($badge["name"]??"").'" maxlength="80" required>
        </div>
        <div class="form-group">
            <label><strong>Image URL</strong></label>
            <input class="form-control gd-input" name="badge_image['.$level.']" value="'.$h($badge["image"]??"").'">
        </div>
        <div class="form-group">
            <label><strong>Or upload</strong></label>
            <input class="form-control gd-input" type="file" name="badge_file_'.$level.'" accept="image/png,image/jpeg,image/webp,image/gif">
        </div>
        <label class="checkbox">
            <input type="checkbox" name="badge_clear['.$level.']" value="1">
            Clear image
        </label>
    </div>';
}

$content.='</div>
<div style="display:flex;justify-content:flex-end;margin-top:var(--sp-4);">
    <button class="gd-btn gd-btn--primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save badges</button>
</div>
</form>';

$dl->printPage($content,true,"badges");
$dl->printFooter("../");
?>