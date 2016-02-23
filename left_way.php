<?php

//自定义加密class
include_once "./safer/wxBizMsgCrypt.php";
include      "translate.php";
include      "templet.php";
include      "link_my.php";
include      "words.php";// 随机短句
include      "tool.php";// 截取字符串的工具

$appId          =  " ";    // 来自公众平台
$encodingAesKey =  " ";
$token          =  "pamtest";

$timeStamp     =  $_GET["timestamp"];
$nonce         =  $_GET["nonce"];
// 是msg_signature    不是signature
$Msg_signature =  $_GET["msg_signature"];
$encrypt_type  =  $_GET["encrypt_type"];

$non="sd";


// 消息提取
$postStr          = $GLOBALS["HTTP_RAW_POST_DATA"];
if ($encrypt_type == 'aes'){
    $pc          = new WXBizMsgCrypt( $token, $encodingAesKey, $appId );                
    $decryptMsg  = "";  // 解密后的明文
    $errCode     = $pc->decryptMsg( $msg_signature, $timeStamp, $nonce, $postStr, $decryptMsg );

    if ($errCode == 0) {
	$post_decryt = $decryptMsg;
	}
	else {
	// 	print($errCode . "\n");
	}
}


// 得到对应的内容
$post_Msg     = simplexml_load_string( $post_decryt );
$ToUserName   = $post_decryt->ToUserName;
$FromUserName = $post_decryt->FromUserName;
$CreateTime   = $post_decryt->CreateTime;
$MsgType      = $post_decryt->MsgType;
$Content      = $post_decryt->Content;    // 内容   各种设置的基础，字符串 （正则）
$fromMsg      = array( '$ToUserName', '$FromUserName', '$CreateTime', '$MsgType' );    //组成数组，不包含内容


/**
 * 大步骤
 *    content 提取
 * -> 正则判断，返回标志符号
 * -> 查询--重点是ID
 * -> 计算
 * -> 结果写回
 * -> 查询回复类型消息
 * -> 回传
 */
// 通用方法
$getstr      = new tool;

// 用户是否存在，通常是关注时候产生用户
// 查询相应的ID对应值，然后形成人物主要信息 -- 可以if   flag  判断用户
$query_player    = tr_user::get_mysql_way();
$info_for_player = $query_player->query_user_info($FromUserName);
$content_base    = array('dice_is' => '', 'place_is' => 0, 'flag_content' => '');

if (empty($info_for_player)) {
	$creat           = $query_player->creat_user_info($FromUserName);
	$info_for_player = $query_player->query_user_info($FromUserName);
}
$event_cache_for_player = $query_player->query_event_cache( $FromUserName );//提取事件缓存

// 回复内容置空
$re_content_is      = '';// 回复内容，title 
$flag_content       = '';// 标签内容，状态之类的
$important_words_is = '';// 特殊内容

// 时间戳部分判定，是否存在事件，有是什么事件
$co_match_class = new co_match;
$event_tag      = $co_match_class->time_match( $timeStamp,$info_for_player['Ptimestamp'], $info_for_player['event_tag'], $event_cache_for_player );//对话

if ($event_tag['time']!=0) {
// 与之对应的对话状态  1  2  3
// 事件中，就执行事件相关。    不是事件中，就执行日常相关。
	$event_is   = new event_now($info_for_player['event_step']);
	$event_word = preg_match_all(/[A-Z]/, $event_flag, $event_num);
	$result     = $event_is -> event_sort( $Content, $event_tag['event'] );
}
else
{
// 内容判断   句首关键字--日常
// 初始化
$action_is='';


$cut_content = $getstr->getstrings($Content, 2,$lens);//之后的计算同样包含进计算类之中，不出现在当前页面中

switch ($cut_content) {//字段的判断
	case '掷骰':
		//掷骰行动
		//运算
		$cacu                    = new dicerun;
		$content_base['dice_is'] = $cacu->dice_base(6,1);//骰子大小，骰子数量

		//位置
		$place_cacu               = new place_change;
		$content_base['place_is'] = $place_cacu->place_ch($content_base['dice_is'],$Pplace);
		// ID信息写回
		$update_info = $query_player->info_write($FromUserName, $content_base['place_is']);//更新位置
		$action_is   = 'dice';
		break;
	
	case '翻译':
		$lens_to_explain = mb_strlen($Content,'UTF8')-2;
		$need_explains   = $getstr->getstrings($Content, 2,$lens_to_explain);
		break;

	case '暗号':
		$flag_content = "天空盖地虎";
		break;

	default:
		$re_content = 'Nothing';
		break;
}
}

//消息回写部分
//事件对数据库结果（后操作）
// if ( !empty($this->adition_cache_mysql) ) {
// 	//非空回写
// 	for ($i=0; $i <count($this->adition_cache_mysql); $i++) { 
// 	$cache_words=$event_is->adition_cache_mysql[$i]."".$info_for_player['Pid'];
// 	$cache_result=$query_player->event_change($cache_words);
// 	}
// }
// //缓存回写（前操作）
// $event_A_Z = $query_player->event_cache( $info_for_player['Pid'], $event_is->adition_cache_before );
// //event 回写
// $event_all_back=$query_player->event_write( $FromUserName, $event_is->adition_event, $event_is->adition_step_after );


// 生成随机名言
$important_words    = new words_to_player;
$important_words_is = $important_words->words_creat();

// 回传信息构成
// 消息回复样式
// [title] 昵称
// [event] 事件，消息，时间限制
// [action] 行动?
// [result] 行动结果，效果优先
// [error] out of think--log
// [words]
// 


$re_content_is = $co_match_class->content_reply($content_base, $action_is);
$re_content    = "".$re_content_is."\n".$flag_content."\n".$important_words_is;
// 构成回复消息
// 纯文本
$re_timestamp = time();
$re_text_tem  = new templet;
$re_text      = $re_text_tem->templet($ToUserName, $FromUserName, $re_timestamp, $re_content);
$ReMsg        = $re_text;

// 回复信息加密
$encryptMsg = '';
$errCode    = $pc->encryptMsg($ReMsg, $timeStamp, $nonce, $encryptMsg);

echo $encryptMsg;

?>