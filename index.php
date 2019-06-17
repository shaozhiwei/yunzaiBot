<?php
include 'config.php';
include 'base.php';
include 'app.php';

//设置中国时区 
date_default_timezone_set('PRC'); 

//接收酷q事件 HTTP上报数据
$Event = json_decode(file_get_contents('php://input'), true);

//测试数据
// $Event['message'] = '';
// $Event['user_id'] = '';
// $Event['message_id'] = -1;

//基础数据
$msg       = $Event['message'];
$user_id   = $Event['user_id'];

if (empty($Event['group_id'])) {
	$group_id = $user_id;
	$Event['group_id'] = $user_id;
}else{
	$group_id  = $Event['group_id'];
}

//加载配置config
if (isset($_config[$group_id])) {
    $config = $_config[$group_id];
}else{
    $config = $_config['default'];
}

$app = new app($Event,$config,$app_config);

//广告
$app->check_ad();

//黑名单qq
$app->check_qq();

//屏蔽关键字
$app->ban_word();

//回复间隔
$app->check_time();

//复读
$app->repeat(3);

//识图
if (strpos($msg, 'CQ:image')) {
	$app->baidu_ocr();
}

//感叹号
$app->exclamatory();


