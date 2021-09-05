<?php
/**
 * @fileOverview Eg2Renpy
 * HappyElements株式会社「あんさんぶるガールズ！」の
 * シナリオ(json)をRen'Py(6.99)で読み込める形式に変換します。
 * 
 * ※注意※
 * あくまで変換スクリプトです。シナリオや画像はありません。
 * 
 * ※他に必要なファイル※
 * ・あんさんぶるガールズ！用のRen'pyプロジェクト(命令定義済み)
 * ・story_expression.json
 * ・story_order.json
 * 
 * @author hatotank.net
 * @version 1.1 2021/09/05
 */
//error_reporting(E_ALL & ~E_NOTICE);

// define
$in_dir    = "json";
$in_ext    = "json";
$out_dir   = "scenario";
$out_ext   = "rpy";

$out_csv   = "scenario_list.csv";
$list_csv  = array();

$tab       = "    ";
$voicepath = "sounds/stories/";
$bgmpath   = "bgm/";
$version   = "1.1";

$filename_story_exp = "story_expression.json";
$filename_story_order = "story_order.json";

// delete bom
// http://unsolublesugar.com/20120919/223812/
function deleteBom($str)
{
    if (($str == NULL) || (mb_strlen($str) == 0)) {
        return $str;
    }
    if (ord($str{0}) == 0xef && ord($str{1}) == 0xbb && ord($str{2}) == 0xbf) {
        $str = substr($str, 3);
    }
    return $str;
}

// convert ensemble girls! scenario to ren'py
function convertRenpy($i_file,$o_dir,$o_ext,$o_csv,$story_expression_data)
{
    $scenario = array();
    $json = array();
    $ch = array();
    $bg = array();
    $ev = array();
    $index = 1;
    $opev = false;
    $event_flg = false;
    $event_continue = false;
    $prev_card_id = 0;
    $prev_card_op = false;
    global $tab,$voicepath,$bgmpath,$version,$list_csv;
    
    try{        
        $ts  = deleteBom(file_get_contents($i_file));
        $ts2 = file_get_contents($story_expression_data);
        
        $json  = json_decode($ts ,true);
        $json2 = json_decode($ts2,true);
        
        // imageManifest analysis
        foreach((array)$json["animation"]["imageManifest"] as $v){
            // fix サブストーリー(2年C組) 斬れ斬れハート 第三話
            if(strpos($v["src"],"8_dogi-hajime")){
                if(strpos($v["src"],"8_base")){   
                    continue;
                }else{
                    $v["id"] = "1005_surprise";
                    $v["src"] = str_replace("8_dogi-hajime/8_surprise.png","1005_dogi-hajime/1005_surprise.png",$v["src"]);
                }
            }
            // pre
            if(stripos($v["src"],"http:__kmsk_native_hekk_org") !== false
              || stripos($v["src"],"http:__kmsk_gree_hekk_org") !== false){
                $v["src"] = str_replace("_","/",$v["src"]);
            }
            $t = explode("?", $v["src"]);
            $t = str_replace("http://kmsk.gree.hekk.org/","",$t);
            $t = str_replace("http://kmsk.native.hekk.org/","",$t);
            // background
            if(strpos($v["id"],"bg_") !== false){
                $bg = array_merge($bg,array($v["id"]=>$t[0]));
            }
            // event
            else if(strpos($v["id"],"http:__kmsk_native_hekk_org") !== false
                   || strpos($v["id"],"http:__kmsk_gree_hekk_org") !== false){
                $t2 = explode("_",$v["id"],-1);
                if(strpos($v["id"],"evolution") !== false){
                    $ev = array_merge($ev,array("ev_" . end($t2) . "e"=>$t[0]));
                }else{
                    $ev = array_merge($ev,array("ev_" . end($t2) . "n"=>$t[0]));
                }
            }
            // character
            else{
                $ch = array_merge($ch,array($v["id"]=>$t[0]));
            }
        }
    }catch(Exception $e){
        print "exception";
    }
    
    asort($ch);
    
    // convert start
    $scenario[] = "#Eg2Renpy Ver$version\n";
    
    // active character
    foreach((array)$ch as $k => $v){
        $t = explode("_",$k);
        if($t[1] != "base"){
            $t2 = explode("/",$v);
            $t3 = implode("/",explode("/",$v,-1));
            $pos_x = $json2["story_expression"]["$t2[4]"]["x"];
            $pos_y = $json2["story_expression"]["$t2[4]"]["y"];
            $scenario[] = "image c$t[0]_$t[1] = im.Composite((520,1000),(0,0),\"$t3/$t[0]_base.png\",($pos_x,$pos_y),\"$v\")\n";
        }else{
            $scenario[] = "image c$t[0]_base = \"$v\"\n";
        }
    }
    // not active character
    foreach((array)$ch as $k => $v){
        $t = explode("_",$k);
        if($t[1] != "base"){
            $t2 = explode("/",$v);
            $t3 = implode("/",explode("/",$v,-1));
            $pos_x = $json2["story_expression"]["$t2[4]"]["x"];
            $pos_y = $json2["story_expression"]["$t2[4]"]["y"];
            $scenario[] = "image c$t[0]_$t[1]_false = im.MatrixColor(im.Composite((520,1000),(0,0),\"$t3/$t[0]_base.png\",($pos_x,$pos_y),\"$v\"),im.matrix.brightness(-0.2))\n";
        }else{
            $scenario[] = "image c$t[0]_base_false = im.MatrixColor(\"$v\",im.matrix.brightness(-0.2))\n";
        }
    }
    foreach((array)$ev as $k => $v){
        $scenario[] = "image " . $k . " = " . '"' . $v . '"' . "\n";
    }
    foreach((array)$bg as $k => $v){
        // fix
        if($k != "bg_2_"){
            $scenario[] = "image " . $k . " = " . '"' . $v . '"' . "\n";
        }
    }
    
    $view_background = "";
    $view_left = "";
    $view_center = "";
    $view_center_flg = 0;
    $view_right = "";
    $effect = "";
    $story_bgm = "story_normal";
    $drama_flg = false;

    //start    
    $chapterName   = $json["animation"]["chapterName"];
    $storySubTitle = $json["animation"]["storySubTitle"];
    $storyTitle    = str_replace("[","[[",$json["animation"]["storyTitle"]);
    $writerName    = $json["animation"]["writerName"];

    // drama check
    if(isset($json["animation"]["drama"])){
        if($json["animation"]["drama"] == "true"){
            $drama_flg = true;
        }
    }
    
    $scenario[] = "\n#start\n";
    $scenario[] = "label L_" . pathinfo($i_file)['filename'] . ":\n";
    $scenario[] = "\n";
    $scenario[] = $tab . "stop music fadeout 1.0\n";
    $scenario[] = $tab . "scene black with dissolve\n";
    $scenario[] = $tab . "window hide\n";
    if($drama_flg){
        $scenario[] = $tab . "show img_curtain_l at tf_curtain_l\n";
        $scenario[] = $tab . "show img_curtain_r at tf_curtain_r\n";
        $scenario[] = $tab . "show img_bgbar at truecenter\n";
        $scenario[] = $tab . "pause(1.0)\n";
        $scenario[] = "\n";
        $scenario[] = $tab . "call screen dramasubtitle(" . '"' . $storyTitle . '",' . '"' . $storySubTitle . '",' . '"シナリオ：' . $writerName . '")' . "\n";
        $scenario[] = $tab . "hide img_bgbar\n";
        $scenario[] = $tab . "show img_curtain_l at tf_mv_curtain_l\n";
        $scenario[] = $tab . "show img_curtain_r at tf_mv_curtain_r\n";
        $scenario[] = $tab . "with None\n";
        $scenario[] = $tab . "pause(0.4)\n";
        $scenario[] = $tab . "show white\n";
        $scenario[] = $tab . "with flash\n";
        $scenario[] = $tab . "hide img_curtain_l\n";
        $scenario[] = $tab . "hide img_curtain_r\n";
    }else{
        $scenario[] = $tab . "show background\n";
        $scenario[] = $tab . "show bg_book at bookrotate\n";
        $scenario[] = $tab . "pause(1.0)\n";
        $scenario[] = "\n";
        $scenario[] = $tab . "call screen subtitle(" . '"' . $storyTitle . '",' . '"' . $storySubTitle . '",' . '"シナリオ：' . $writerName . '")' . "\n";
    }
    
    // list
    $list_csv[] = array(pathinfo($i_file)['filename'],$storyTitle,$storySubTitle);

    foreach((array)$json["animation"]["scenes"] as $cut){
        
        if($event_flg){
            if(isset($cut["card_id"])){

                $event_continue = true;
                // 連続スチル対応
                if($prev_card_id != $cut["card_id"]){
                    $scenario[] = $event_hide;
                    $event_continue = false;
                }
            }else{
                $scenario[] = $event_hide;
                $event_continue = false;
            }
        }
        
        // hide-left
        if(!isset($cut["left"])){
            if($view_left != ""){
                $scenario[] = $tab . "hide c$view_left\n";
                $view_left = "";
            }
        }
        // hide-center
        if(!isset($cut["center"])){
            if($view_center != ""){
                $scenario[] = $tab . "hide c$view_center\n";
                $view_center = "";
            }
        }
        // hide-right
        if(!isset($cut["right"])){
            if($view_right != ""){
                $scenario[] = $tab . "hide c$view_right\n";
                $view_right = "";
            }
        }
        
        // bgm
        if(isset($cut["bgm"]) && $cut["bgm"] != ""){
            // fix 1年A組 桃智あすか
            if($cut["bgm"] == "story_buzy"){
                $cut["bgm"] = "story_busy";
            }
            // fix 節分☆豆まきパーティー！ [節分] 八壁ひかる: 第三話
            if($cut["bgm"] == "ag_03"){
                $cut["bgm"] = "story_busy"; //合わない場合は"story_normal"
            }
            // fix 節分☆豆まきパーティー！ [節分] 藍乃あいか: 第一話
            // fix 節分☆豆まきパーティー！ [節分] 藍乃あいか: 第二話
            // fix 節分☆豆まきパーティー！ [節分] 八壁ひかる: 第二話
            // fix 節分☆豆まきパーティー！ [節分] 八壁ひかる: 第三話
            // fix 節分☆豆まきパーティー！ [節分] 安条まい: 第二話
            if($cut["bgm"] == "ag_04"){
                $cut["bgm"] = "story_normal";
            }
            // fix 節分☆豆まきパーティー！ [節分] 藍乃あいか: 第三話
            // fix 節分☆豆まきパーティー！ [節分] 安条まい: 第三話
            if($cut["bgm"] == "ag_05"){
                $cut["bgm"] = "story_sad"; //合わない場合は"story_normal"
            }
            if($index == 1){
                $story_bgm = $cut["bgm"];
            }else{
                // fix 第一部：紹介 第五話: 対立候補『生き神』
                // fix 第一部：紹介 第七話: 対立候補『堕天使』
                // fix 2年A組 星に願いを: 第三話
                // fix 3年C組 恋する電波: 第二話
                // fix 1年B組 陽だまりの詩: 第三話
                if($cut["bgm"] == "story_stop" || $cut["bgm"] == "soundless"){
                    $scenario[] = $tab . "stop music fadeout 1.0\n"; //合わない場合はこの行をコメント化
                }else{
                    $scenario[] = $tab . "play music " . '"' . $bgmpath . $cut["bgm"] . ".mp3" . '"' . " fadeout 0.5 loop\n";
                }
            }
        }
        
        // background
        if(isset($cut["background_key"])){
            $new_background = $cut["background_key"];
            if($view_background != $new_background){
                if($view_background != ""){
                    $scenario[] = $tab . "hide $view_background\n";
                }
                $view_background = $new_background;
                $scenario[] = $tab . "show $new_background\n";
                if($opev == true){
                    $opev = false;
                }else{
                        if($drama_flg || $index != 1){
                            $scenario[] = $tab . "show blackout at tf_mv_black_l\n";
                            $scenario[] = $tab . "with wipeleft2\n";
                        }else{
                            $scenario[] = $tab . "show blackout at tf_mv_black_r\n";
                            $scenario[] = $tab . "with wiperight2\n";
                        }
                        $scenario[] = $tab . "pause(0.8)\n";
                        $scenario[] = $tab . "hide blackout\n";
                        if($index != 1){
                            if($view_left != ""){
                                $scenario[] = $tab . "hide c" . $view_left . "\n";
                                $view_left = "";
                            }
                            if($view_right != ""){
                                $scenario[] = $tab . "hide c" . $view_right . "\n";
                                $view_right = "";
                            }
                            if($view_center != ""){
                                $scenario[] = $tab . "hide c" . $view_center . "\n";
                                $view_center = "";
                            }
                    }
                    if($index == 1){
                        // fix 初遭遇？夜明けの流れ星！ [賀正]時国そら: 第二話
                        // fix 初遭遇？夜明けの流れ星！ [賀正]天宮るり: 第一話
                        if($story_bgm == "story_stop"){
                            $scenario[] = $tab . "play music " . '"' . $bgmpath . "story_normal.mp3" . '" loop' . "\n"; //合わない場合はこの行をコメント化かmain.mp3に切り替え
                        }else{
                            $scenario[] = $tab . "play music " . '"' . $bgmpath . $story_bgm . ".mp3" . '" loop' . "\n";
                        }
                        $scenario[] = $tab . "hide background\n";
                        $scenario[] = $tab . "hide bg_book\n";
                        $scenario[] = "\n";
                    }
                }
            }
        }
        
        // event
        $event_flg = false;
        if(isset($cut["card_id"])){
            if($event_continue == false){
                if(isset($cut["card_option"]["evolution"])){
                    $event_hide = $tab . "hide ev_" . $cut["card_id"] . "e with dissolve\n";
                    $scenario[] = $tab . "show ev_" . $cut["card_id"] . "e with dissolve\n";
                }else{
                    $event_hide = $tab . "hide ev_" . $cut["card_id"] . "n with dissolve\n";
                    $scenario[] = $tab . "show ev_" . $cut["card_id"] . "n with dissolve\n";
                }
                if($index == 1){
                    $scenario[] = $tab . "play music " . '"' . $bgmpath . $story_bgm . ".mp3" . '" loop' . "\n";
                    $scenario[] = $tab . "hide background\n";
                    $scenario[] = $tab . "hide bg_book\n";
                    $scenario[] = "\n";
                    $opev = true;
                }
                $scenario[] = $tab . "pause\n";
            }
            $event_flg = true;
            $prev_card_id = $cut["card_id"]; // 連続スチル対応
        }
        
        // left-center-right
        if(!$event_flg){
            // left
            if(isset($cut["left"])){
                // fix サブストーリー(2年C組) 斬れ斬れハート 第三話
                if($cut["left"]["id"] == 8
                  && $cut["left"]["key"] == "dogi-hajime"){
                    $cut["left"]["base_key"] = "1005_base";
                    $cut["left"]["expression_key"] = "1005_surprise";
                }
                if(isset($cut["left"]["expression_key"])){
                    $new_left = $cut["left"]["expression_key"];
                }else{
                    $new_left = $cut["left"]["base_key"];
                }
                if($cut["left"]["active"] == false){
                    $new_left = $new_left . "_false";
                }
                if($view_left != $new_left){
                    if($view_left != ""){
                        $scenario[] = $tab . "hide c$view_left\n";
                    }
                    $scenario[] = $tab . "show c$new_left at left2\n";
                    $view_left = $new_left;
                }
            }
            // center
            if(isset($cut["center"])){
                if(isset($cut["center"]["expression_key"])){
                    $new_center = $cut["center"]["expression_key"];
                }else{
                    $new_center = $cut["center"]["base_key"];
                }
                if($cut["center"]["active"] == false){
                    $new_center = $new_center . "_false";
                }
                if($view_center != $new_center){
                    if($view_center != ""){
                        $scenario[] = $tab . "hide c$view_center\n";
                    }
                    $scenario[] = $tab . "show c$new_center at center2\n";
                    $view_center = $new_center;
                }
            }
            // right
            if(isset($cut["right"])){
                if(isset($cut["right"]["expression_key"])){
                    $new_right = $cut["right"]["expression_key"];
                }else{
                    if(isset($cut["right"]["base_key"])){
                        $new_right = $cut["right"]["base_key"];
                    }
                    // fix not found base_key
                    else{
                        $new_right = $cut["right"]["id"] . "_base";
                    }
                }
                if($cut["right"]["active"] == false){
                    $new_right = $new_right . "_false";
                }
                if($view_right != $new_right){
                    if($view_right != ""){
                        $scenario[] = $tab .  "hide c$view_right\n";
                    }
                    $scenario[] = $tab . "show c$new_right at right2\n";
                    $view_right = $new_right;
                }
            }
        }
        
        // voice
        if(isset($cut["voice"]) && $cut["voice"] != ""){
            $scenario[] = $tab . "voice " . '"' . $voicepath . $cut["voice"] . ".mp3" . '"' . "\n";
        }
        
        // effect
        if(isset($cut["effect"])){
            // fix Kimisaki Valentine's Day [バレンタイン] 星海こよい: 第一話
            // fix Kimisaki Valentine's Day [バレンタイン] 星海こよい: 第二話
            if($cut["effect"] == "quake" || $cut["effect"] == "puake"){
                $effect = " with vpunch";
            }else{
                $effect = "";
            }
        }
        
        // speaker
        if($cut["speaker"] == ""){
            $speaker = " ";
        }else{
            $speaker = $cut["speaker"];
        }
        
        // text
        $scenario[] = $tab . '"' . $speaker . '"' . ' "' . str_replace("[","[[",$cut["text"]) . '"' . $effect . "\n";
        $scenario[] = "\n";
        
        $index++;
    }
    
    $scenario[] = $tab . "window hide\n";
    $scenario[] = $tab . "scene black with fade2\n";
    $scenario[] = $tab . "stop music fadeout 1.0\n";
    $scenario[] = "\n";
    $scenario[] = $tab . "return\n";
    
    // write scenario
    $tmpfile = $o_dir . "/" . pathinfo($i_file)['filename'] . "." . $o_ext;
    $fp = fopen($tmpfile, "w");
    foreach($scenario as $v){
        $mbstr = mb_convert_encoding($v, "UTF-8", "auto");
        fwrite($fp,$mbstr);
    }
    fclose($fp);
}

// main
$dh = opendir($in_dir);

// output scenario(*.rpy)
while(false !== ($filename = readdir($dh))){
    if(is_file("$in_dir/$filename")){
        print "convert:" . $filename . " -> " . pathinfo("$in_dir/$filename")['filename'] . "." . $out_ext . "\n";
        convertRenpy("$in_dir/$filename", $out_dir, $out_ext, $out_csv, $filename_story_exp);
    }
}

// output scenario_list.csv
$tmpfile = $out_dir . "/" . $out_csv;
$fp = fopen($tmpfile, "w");
$ts3 = file_get_contents($filename_story_order);
$json3 = json_decode($ts3,true);

foreach($list_csv as $v){
    $tmp1 = preg_replace("[^0-9_]","",$v[0]);
    $tmp2 = explode("_",$tmp1);
    
    $story_no = $tmp2[0] * 100;
    if(count($tmp2) > 1){
        $story_no += $tmp2[1];
    }
    
    $o_order = 0;
    $s_kbn = 0;
    foreach($json3["story_order"] as $j){
        if($j["story_no"] == $story_no){
            $o_order = $j["offical_order"];
            $s_kbn = $j["story_kbn"];
            break;
        }
    }
    $line = "L_" . $v[0] . "," . $v[1] . " " . $v[2] . "," . $s_kbn . "," . $o_order . "\n";
    $mbstr = mb_convert_encoding($line, "UTF-8", "auto");
    fwrite($fp,$mbstr);
}
fclose($fp);

closedir($dh);
?>
