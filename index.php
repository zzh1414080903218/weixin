<?php

$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
libxml_disable_entity_loader(true);
$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
main();

function main(){
	//valid();
	listener();
}

function listener(){
	global $postObj;
	$user = getUser($postObj->FromUserName);
	$fromContent = "(未定义)";
	$content = "(未定义)";
	$type = "(未定义)";
	
	switch($postObj->MsgType){
		case "text":
			$type = "（文本）";
			$content = tuling($postObj->Content,$user['Id']);
			revText($content);
			$fromContent = $type.$postObj->Content;
		break;
		case "image":
			//$picUrl = $postObj->PicUrl;
			//$mediaId = $postObj->MediaId;
		break;
		case "voice":
			//$mediaId = $postObj->MediaId;
			//$format = $postObj-Format;
			//$recognition = $postObj->Recognition;
			$type = "（语音）";
			$fromContent = $type.$postObj->Recognition;
			$content = tuling($postObj->Recognition,$user['Id']);
			revText($content);
		break;
		case "video":
		case "shortvideo":
			//$mediaId = $postObj->MediaId;
			//$thumbMediaId = $postObj->ThumbMediaId;
		break;
		case "location":
			//$location_X = $postObj->Location_X;
			//$location_Y = $postObj->Location_Y;
			//$label = $postObj->Label;
			//$scale = $postObj->Scale;
			$type = "（位置）";
			$content = tuling($postObj->Label,$user['Id']);
			$fromContent = $type.$postObj->Label;
			revText($content);
		break;
		case "link":
			//$title = $postObj->Title;
			//$description = $postObj->Description;
			//$url = $postObj->Url;
		break;
		case "event":
			$type = "（事件）";
			switch($postObj->Event){
				case "subscribe":
					$content = "终于等到您啦！我是小墨，很高兴认识您，有空没空随时可以找我聊天噢";
					$fromContent = $type."我关注您啦！";
					revText($content);
				break;
				case "unsubscribe":
					//$content = "我哪里做得不对吗？欢迎再次关注或提出意见哦！";
					$fromContent = $type."我取消关注了！";
					revText($content);
				break;
				case "location":
				break;
				default:
					//$contentStr = "抱歉！功能完善中...";
			}
		break;
		default:
	}
	logText($user['Id'],$user['userName'],$fromContent,$content);
}

function revText($contentStr){
	global $postObj;
	$textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[text]]></MsgType>
		<Content><![CDATA[%s]]></Content>
		<FuncFlag>0</FuncFlag>
		</xml>";
	$resultStr = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $contentStr);
	echo $resultStr;
}

function revImage($contentStr){
	global $postObj;
	$textTpl = "<xml>
		<ToUserName><![CDATA[%s]]></ToUserName>
		<FromUserName><![CDATA[%s]]></FromUserName>
		<CreateTime>%s</CreateTime>
		<MsgType><![CDATA[image]]></MsgType>
		<MediaId><![CDATA[%s]]></MediaId>
		<FuncFlag>0</FuncFlag>
		</xml>";
	$resultStr = sprintf($textTpl, $postObj->FromUserName, $postObj->ToUserName, time(), $contentStr);
	echo $resultStr;
}

function file_post_contents($url,$data){
	$opts = array(  
		'http'=>array(  
		'method'=>"POST",  
		'header'=>"Content-type: application/x-www-form-urlencoded\r\n".  
			"Content-length:".strlen($data)."\r\n" .   
			"\r\n",  
			'content' => $data,  
		)  
	);  
	$cxContext = stream_context_create($opts);  
	$content = file_get_contents($url, false, $cxContext);  
	return $content;
}

function tuling($keyword,$userId){
	$appkey = getValue("Tuling_appkey");
	$url = getValue("Tuling_url");
	$data = "key=".$appkey."&info=".$keyword."&userid=".$userId;
	$result = file_post_contents($url,$data);
	$result = json_decode($result,true);
	$res = $result['text'];
	if($result['url'])$res .= "\n".$result['url'];
	return $res;
}

function logText($userId,$userName,$content,$receive)
{
	$log_filename = "..\\..\\log\\".date("Ymd")."_".$userId.".txt";
	file_put_contents($log_filename, date('H:i:s')."  ".$userName.": ".$content."\t\t:".$receive."\r\n", FILE_APPEND);
}

function getUser($name){
	$con = mysql_connect();
	if (!$con){
		return "连接数据库失败！";
	}
	$db_selected = mysql_select_db("weixin", $con);
	if (!$db_selected){
		return "选择数据库失败！";
	}
	mysql_query("SET NAMES 'utf8'");
	$result = mysql_query("SELECT * FROM user WHERE fromName='$name'");
	$row = mysql_fetch_array($result);
	//$id = 1;
	//if(!$row)mysql_query("INSERT INTO user(Id,fromName) VALUES('$id','$name')");
	if(!$row){
		mysql_query("INSERT INTO user(fromName) VALUES('$name')");
		$result = mysql_query("SELECT * FROM user WHERE fromName='$name'");
		$row = mysql_fetch_array($result);
	}
	mysql_close($con);
	return  $row;
}

function getValue($name){
	$con = mysql_connect();
	if (!$con){
		return "";
	}
	$db_selected = mysql_select_db("weixin", $con);
	if (!$db_selected){
		return "";
	}
	mysql_query("SET NAMES 'utf8'");
	$result = mysql_query("SELECT * FROM constant WHERE name='$name'");
	$row = mysql_fetch_array($result);
	mysql_close($con);
	return $row['value'];
}

function valid()
{
	$echoStr = $_GET["echostr"];

	//valid signature , option
	if(checkSignature()){
		echo $echoStr;
		exit;
	}
}
	
function checkSignature()
{
	// you must define TOKEN by yourself
	$token = getValue("Token");
	if (empty($token)) {
		throw new Exception('token is not defined!');
	}
	
	$signature = $_GET["signature"];
	$timestamp = $_GET["timestamp"];
	$nonce = $_GET["nonce"];
			
	$tmpArr = array($token, $timestamp, $nonce);
	// use SORT_STRING rule
	sort($tmpArr, SORT_STRING);
	$tmpStr = implode( $tmpArr );
	$tmpStr = sha1( $tmpStr );
	
	if( $tmpStr == $signature ){
		return true;
	}else{
		return false;
	}
}


?>