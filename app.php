<?php
use Grafika\Grafika;

/**
* app
*/
class app
{
    
    function __construct($Event,$config,$app_config)
    {
        $this->Event      = $Event;
        $this->config     = $config;
        $this->app_config = $app_config;
    }

    //忽略关键字
    public function ban_word(){
        $msg      = $this->Event['message'];

        $bad_word = ['rich','闪照','游戏','视频','礼物','分享','签到','红包','小程序'];

        foreach ($bad_word as $value) {
            if (strpos($msg, $value)) {
                return_send();
            }
        }
    }

    public function repeat($rep_num = 3){
        $msg      = $this->Event['message'];
        $group_id = $this->Event['group_id'];
        $txt_path = './tmp/log/repeat_'.$group_id.'.txt';

        //读取之前消息
        $qq_msg = file_get_contents($txt_path);
        $qq_msg = json_decode($qq_msg,true);

        //初始化
        if (empty($qq_msg) || !isset($qq_msg)) {
            $qq_msg['msg_num']  = 1;
            $qq_msg['last_msg'] = $msg;
            $qq_msg['send_msg'] = '';
            file_put_contents($txt_path, json_encode($qq_msg));
            return;
        }

        //消息相同次数加一
        if ($msg == $qq_msg['last_msg']) {
            $qq_msg['msg_num'] = $qq_msg['msg_num'] + 1;
        }else{
            //再次发送
            $qq_msg['msg_num']  =  1;
            $qq_msg['last_msg'] =  $msg;
        }

        if ($qq_msg['msg_num'] >= $rep_num && $msg != $qq_msg['send_msg']) {

            //记录消息
            $qq_msg['send_msg'] = $msg;
            file_put_contents($txt_path, json_encode($qq_msg));

            //复制消息回复
            return_send($msg);
            return;
        }

        file_put_contents($txt_path, json_encode($qq_msg));
    }

    public function baidu_ocr(){
        if (!$this->config['open']) {
            return_send();
        }
        $rand = rand(0,100);
 
        if ($rand > $this->config['reply_rate']) {
            return_send();
        }

        $APP_ID     = $this->app_config['baidu_appid'];
        $API_KEY    = $this->app_config['baidu_apiKey'];
        $SECRET_KEY = $this->app_config['baidu_secretKey'];

        $pattern = '/(?<=\[CQ:image,)[^\]]+/';//$pattern = '/a.*?d/';
        preg_match($pattern,$this->Event['message'],$match);

        if (empty($match)) {
            return_send();
        }

        $msg = explode(',',$match[0]);
        if (!isset($msg[1])){
            return_send();
        }

        $url = $msg[1];
        $url = str_replace('url=','',$url); 
        $url = htmlspecialchars_decode($url);

        require './lib/ai_baidu/AipOcr.php';
        $client = new AipOcr($APP_ID, $API_KEY, $SECRET_KEY);

        //图片后缀
        $ext = substr(strrchr($msg[0],"."),1);

        //处理gif动图
        if ($ext == 'gif') {
            $tmp_name = './tmp/img/'.time().rand(0,1000).'.'.$ext;
            dlfile($url,$tmp_name);

            $output='./tmp/img/'.time().rand(0,1000).'.jpg';  ;
            $image=imagecreatefromgif($tmp_name);
            imagejpeg($image,$output);
            imagedestroy($image);
            unlink($tmp_name);

            $image = file_get_contents($output);
            $res = $client->basicGeneral($image);
            unlink($output);
        }else{
            // 调用通用文字识别, 图片参数为远程url图片
            $res = $client->basicGeneralUrl($url);
        }

        if (!isset($res['words_result'])) {
            return_send();
        }
        
        $word = array_column($res['words_result'],'words');
        $index = 0;

        //过滤特殊符号
        $word[$index] = replaceSpecialChar($word[$index]);

        //去最长的关键字
        foreach ($word as $k => &$v) {
            $v = replaceSpecialChar($v);
            if (strlen($word[$index]) < strlen($v))
                $index = $k;
        }
        
        if (empty($word[$index])) {
            return_send();
        }

        //过滤关键字太长和太多的
        $length = mb_strlen($word[$index]);
        if ($length<=1 || $length >= 12 ||count($word) >= 4 ) {
            return_send();
        }
        w_log($word[$index],'word');
        
        //调用图片接口
        $this->return_img($word[$index]);
        // $this->return_gif($word[$index]);
    }

    public function return_img($key_word = '有问题'){
        $url = 'https://www.doutula.com/api/search?keyword='.$key_word.'&mime=0&page=1';

        $ch = curl_init();
        $timeout = 5;
        curl_setopt ($ch, CURLOPT_URL, $url);
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        $data = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($data,true);

        if (!$data || empty($data['data']['list'])) {
            $this->return_gif($key_word);
        }

        $list = $data['data']['list'];

        shuffle($list);
        $img = $list[0]['image_url'];

        //下载图片
        $ext = substr(strrchr($img,"."),1);
        $name = time().rand(0,1000).'.'.$ext;
        $file = './tmp/img/'.$name;
        dlfile($img,$file);

        //等高压缩图片,防止发图影响
        require_once './lib/grafika/autoloader.php';
        $editor = Grafika::createEditor();
        $editor->open($image1 , $file);
        $Height = $image1->getHeight();
        if ($Height>200) {
            $editor->resizeExactHeight($image1 , 200);
            $editor->save($image1 , $file);
        }

        $file = __DIR__.'/tmp/img/'.$name;

        return_send("[CQ:image,file=file:///".$file."]");
    }

    public function return_gif($msg = '有问题'){

        $url = 'http://www.weshineapp.com/api/v1/index/search/'.urlencode($msg).'?offset=0&limit=18&block=hot';
        $res = _get($url);
        $res = json_decode($res,true);
        
        if (!$res || empty($res['data'])) {
            return_send();
        }

        if (empty($res['data']['hot'])) {
            if (empty($res['hotkeywords'])) {
                return_send();
            }
            shuffle($res['hotkeywords']);
            $url = 'http://www.weshineapp.com/api/v1/index/search/'.urlencode($res['hotkeywords'][0]).'?offset=0&limit=18&block=hot';
            $res = _get($url);
            $res = json_decode($res,true);
        }
        
        if (empty($res['data']['hot'])) {
            $offset = rand(0,5)*50;
            $url = 'http://www.weshineapp.com/api/v1/index/hot/?offset='.$offset.'&limit=50';
            $res = _get($url);
            $res = json_decode($res,true);
            $res['data']['hot'] = $res['data'];
        }

        $list = $res['data']['hot'];

        shuffle($list);
        if (!empty($list[0]['url'])) {
            $img = $list[0]['url'];
        }
        if (!empty($list[0]['imgurl'])) {
            $img = $list[0]['imgurl'];
        }

        // w_log($img,'gif_url');
        if (!$img) return_send();

        //下载图片
        $ext = substr(strrchr($img,"."),1);

        if (stripos($ext,"?")) {
            $ext = substr($ext,0,stripos($ext,"?"));
        }

        $name = time().rand(0,1000).'.'.$ext;
        $file = './tmp/img/'.$name;;
        dlfile($img,$file);

        require_once './lib/grafika/autoloader.php';
        $editor = Grafika::createEditor();
        $editor->open($image1 , $file);
        $Height = $image1->getHeight();
        if ($Height>200) {
            $editor->resizeExactHeight($image1 , 200);
            $editor->save($image1 , $file);
        }

        $file = __DIR__.'/tmp/img/'.$name;

        //记录发送时间
        file_lock($this->Event['group_id']);

        return_send("[CQ:image,file=file:///".$file."]");
    }

    public function exclamatory(){

        if(substr($this->Event['message'], 0, 1) != '!'){
            return;
        }

        $msg = ltrim($this->Event['message'],'!');
        $this->return_img($msg);
    }

    public function check_qq(){
        if (in_array($this->Event['user_id'],$this->config['black_qq'])) {
            exit;
        }
    }

    public function check_time(){
        //判断频率
        $lock_file = './tmp/log/lock/lock_'.$this->Event['group_id'].'.txt';
        if (!is_file($lock_file)) {
            $file = fopen($lock_file,'w+');
            fwrite($file,0);
            fclose($file);
        }
        $lock = file_get_contents($lock_file);
        if ((getMsecTime()-$lock)<$this->config['reply_time']) {

            //@没有限制
            if (!strpos($this->Event['message'], 'CQ:at,qq='.$this->app_config['bot_qq'])) {
                return_send();
            }
        }
    }

    public function check_ad(){
        $msg        = $this->Event['message'];
        $user_id    = $this->Event['user_id'];
        $group_id   = $this->Event['group_id'];
        $message_id = $this->Event['message_id'];
        $che = 0;

        //qq小冰
        if (in_array($user_id, ['2854196306'])) {
            return;
        }

        //需要管理的群
        if (!in_array($group_id, $this->app_config['admin_group'])) {
            return;
        }

        if (strpos($msg, 'CQ:rich') === false) {
            //去除表情图片
            $msg = preg_replace("/(?<=\[)[^\]]+/","",$msg);
        }
        
        $length = mb_strlen($msg);

        if ($length<=30) {
            return;
        }

        //判断字符串中是否有中文-避免禁言链接
        if (preg_match("/[\x7f-\xff]/", $msg) == 0) {  
            return;
        }

        //聊天消息广告
        $bad_word = ['色','粉','姐','妹','抱','哥哥','少妇','幼女','屁','淫','群','加'];

        foreach($bad_word as $val){
            if (strpos($msg, $val) !== false) {
                $che ++;
            }
        }

        $nr = substr_count($msg,"\n\r");
        $n  = substr_count($msg,"\n");

        //字数
        if ($length>180) {
            $che +=1;
        }

        //换行符
        if ($n>5 || $nr>5) {
            $che +=1;
        }

        //匹配qq群
        $che += preg_match("/[1-9]\d{5,10}/",$msg);

        //微信号
        $che += preg_match("/[a-zA-Z]{1}[-_a-zA-Z0-9]{5,19}/",$msg);

        //q
        $che += preg_match("/[qQ]/",$msg);

        //b
        $che += preg_match("/bB/",$msg);

        if ($che<2) {
            return;
        }

        w_log($user_id.' 等级:'.$che.' '.$this->Event['message'],'ad_log');

        //撤回
        if ($che>=2) {
            $res = send_api(['message_id'=>$message_id],'delete_msg');
            w_log('delete_msg: '.var_export($res,true),'ad_log');
        }
        
        //禁言
        if ($che>=3) {
            $res = send_api(['group_id'=>$group_id,'user_id'=>$user_id,'duration'=>600],'set_group_ban');
            w_log('set_group_ban: '.var_export($res,true),'ad_log');
        }
        
        return_send();
    }
}