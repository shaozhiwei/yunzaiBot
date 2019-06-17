<?php
//应用配置
$app_config = [
    //百度ocr
    'baidu_appid'     => '',
    'baidu_apiKey'    => '',
    'baidu_secretKey' => '',

    //机器人qq号
    'bot_qq'          => '',

    //管理群
    'admin_group'     => [],
];

//QQ群配置
$_config = [
    'default' => [
    	//开启斗图
    	'open' => 1,
    	//回复概率
    	'reply_rate' => 30,
    	//回复间隔
    	'reply_time' => 5000,
    	//屏蔽qq
    	'black_qq'=>[],
    ],
    //测试群
    '826824259' =>[
    	//开启斗图
    	'open' => 1,
    	//回复概率
    	'reply_rate' => 30,
    	//回复间隔
    	'reply_time' => 5000,
    	//屏蔽qq
    	'black_qq'=>[111,222],
    ],
];