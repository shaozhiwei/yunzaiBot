<?php
/**
 * 返回消息
 * $msg       回复消息
 * $block     是否拦截
 * $at_sender 是否@成员
 */
function return_send($msg = '',$block = true , $at_sender = false){
    if($msg){
        $return['reply'] = $msg;
    }
    
    $return['block']     = $block;
    $return['at_sender'] = $at_sender;
    $res = json_encode($return,JSON_UNESCAPED_UNICODE);
    die ($res);
}

/**
 * 记录日志
 */
function w_log($txt,$path){
    $path = './tmp/log/'.$path.'.txt';
    if (is_array($txt)) {
    	$txt  = date('H:i:s').' '.var_export($txt,true).PHP_EOL;
    }else{
    	$txt  = date('H:i:s').' '.$txt.PHP_EOL.PHP_EOL;
    }

    file_put_contents($path, $txt.PHP_EOL,FILE_APPEND);
}

/**
 * 下载图片
 */
function dlfile($file_url,$save_to){

    $content = file_get_contents($file_url);
    $res = file_put_contents($save_to, $content);
}

/**
 * 过滤特殊符号
 */
function replaceSpecialChar($strParam){
    $regex = "/\ |\/|\~|\!|\@|\#|\\$|\%|\^|\&|\*|\(|\)|\_|\+|\{|\}|\:|\<|\>|\?|\[|\]|\,|\.|\/|\;|\'|\`|\-|\=|\\\|\|/";
    return preg_replace($regex,"",$strParam);
}

/**
 * 模拟post进行url请求
 * @param string $url
 * @param string $param
 */
function _post($url,$post_data){
    //初始化
    $curl = curl_init();
    //设置抓取的url
    curl_setopt($curl, CURLOPT_URL, $url);
    //设置头文件的信息作为数据流输出
    curl_setopt($curl, CURLOPT_HEADER, 0);
    //设置获取的信息以文件流的形式返回，而不是直接输出。
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    //设置post方式提交
    curl_setopt($curl, CURLOPT_POST, 1);
    //设置post数据
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    //执行命令
    $data = curl_exec($curl);
    //关闭URL请求
    curl_close($curl);
    //显示获得的数据
    return $data;
}

function _get($url){
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    $data = curl_exec($curl);
    curl_close($curl);
    return  ($data); 
}

function file_lock($group){

    $file = fopen('./tmp/log/lock/lock_'.$group.'.txt','w+');
    //加锁
    if(flock($file,LOCK_EX|LOCK_NB)){
        fwrite($file,getMsecTime());
        //解锁
        flock($file,LOCK_UN);
        //关闭文件
        fclose($file);
    }else{
        //关闭文件
        fclose($file);
        return_send();
    }
    
}

/**
 * 获取毫秒级别的时间戳
 */
function getMsecTime()
{
    list($msec, $sec) = explode(' ', microtime());
    $msectime =  (float)sprintf('%.0f', (floatval($msec) + floatval($sec)) * 1000);
    return $msectime;
}