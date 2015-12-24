<?php

namespace api\modules\weixin\controllers;

use api\modules\weixin\controllers\common\BaseController;
use common\models\library\Book;
use common\models\posts\Posts;
use common\models\search\IndexSearch;
use common\service\bat\QQService;
use common\service\weixin\RecordService;

class DefaultController extends  BaseController {
    public function actionIndex(){
        if(array_key_exists('echostr',$_GET) && $_GET['echostr']){//用于微信第一次认证的
            echo $_GET['echostr'];exit();
        }
        if(!$this->checkSignature()){
            die("error");
            return false;
        }
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        if($postStr){
            $this->recode_log($postStr);
            RecordService::add( $postStr,$this->getSource() );
            $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $msgType = trim($postObj->MsgType);
            $fromUsername = $postObj->FromUserName;
            $toUsername = $postObj->ToUserName;
            $res = ['type'=>'text','data'=>$this->help()];
            switch($msgType){
                case "text":
                    $res = $this->parseText($postObj);
                    break;
                case "image":
                case "voice":
                case "video":
                    $res = ['type'=>"text",'data'=>$this->richMediaTips()];
                    break;
                case "event":
                    $res = $this->parseEvent($postObj);
                    break;
            }
            switch($res['type']){
                case "rich":
                    return $this->richTpl($fromUsername,$toUsername,$res['data']);
                    break;
                default:
                    return $this->textTpl($fromUsername,$toUsername,$res['data']);
            }
        }
        return "消息接口";
    }

    private function parseText($dataObj){
        $keyword = trim($dataObj->Content);
        if( filter_var($keyword, FILTER_VALIDATE_URL) !== FALSE ){
            return ['type'=> "text",'data'=> $this->urlTips() ];
        }

        if( substr($keyword,0,1) == "@" ){//搜歌曲
            return $this->searchMusicByKw($keyword);
        }

        return $this->getDataByKeyword($keyword);
    }

    private function parseEvent($dataObj){
        $resType = "text";
        $resData = $this->help();
        $event = $dataObj->Event;
        switch($event){
            case "subscribe":
                $resData = $this->subscribeTips();
                break;
            case "CLICK":
                $eventKey = trim($dataObj->EventKey);
                switch($eventKey){
                    case "blog_original":
                        return $this->getOriginalBlog();
                        break;
                    case "ktv":
                        $resData =  $this->songTips();
                        break;
                }
                break;
        }
        return ['type'=>$resType,'data'=>$resData];
    }

    private function textTpl($fromUsername,$toUsername,$data){
        $textTpl = "<xml>
        <ToUserName><![CDATA[%s]]></ToUserName>
        <FromUserName><![CDATA[%s]]></FromUserName>
        <CreateTime>%s</CreateTime>
        <MsgType><![CDATA[%s]]></MsgType>
        <Content><![CDATA[%s]]></Content>
        <FuncFlag>0</FuncFlag>
        </xml>";
        return sprintf($textTpl, $fromUsername, $toUsername, time(), "text", $data);
    }

    private function richTpl($fromUsername,$toUsername,$data){
        $tpl = <<<EOT
<xml>
<ToUserName><![CDATA[%s]]></ToUserName>
<FromUserName><![CDATA[%s]]></FromUserName>
<CreateTime>%s</CreateTime>
<MsgType><![CDATA[news]]></MsgType>
%s
</xml>
EOT;
        return sprintf($tpl, $fromUsername, $toUsername, time(), $data);

    }

    private function getDataByKeyword($keyword){

        $search_key =  ['LIKE' ,'search_key','%'.strtr($keyword,['%'=>'\%', '_'=>'\_', '\\'=>'\\\\']).'%', false];
        $mixed_list = IndexSearch::find()->where($search_key)->orderBy("id desc")->limit(5)->all();
        $list = [];
        if( $mixed_list ){
            $domain_static = \Yii::$app->params['domains']['static'];
            $domain_blog = \Yii::$app->params['domains']['blog'];
            foreach($mixed_list as $_item){
                $tmp_image = "{$domain_static}/wx/".mt_rand(1,7).".jpg";
                if( $_item['image'] ){
                    $tmp_image = $_item['image'];
                }

                if( $_item['post_id'] ){
                    $tmp_url = "{$domain_blog}/default/".$_item['post_id'];
                }else{
                    $tmp_url = "{$domain_blog}/library/detail/".$_item['book_id'];
                }

                $list[] = [
                    "title" => $_item['title'],
                    "description" => $_item['title'],
                    "picurl" => $tmp_image,
                    "url" => $tmp_url
                ];
            }
        }

        $data = $list?$this->getRichXml($list):$this->help();
        $type = $list?"rich":"text";
        return ['type' => $type ,"data" => $data];
    }


    private function searchMusicByKw($kw){
        $songs = QQService::search($kw);
        $list = [];
        if( $songs ){
            foreach( $songs as $_song_info ){
                $list[] = [
                    "title" => $_song_info['fsinger']." -- ".$_song_info['fsong'],
                    "description" => '',
                    "picurl" => $_song_info['cover_image'],
                    "url" => $_song_info['view_url']
                ];
            }
        }
        $data = $list?$this->getRichXml($list):"抱歉没有搜索到关于 {$kw} 的歌曲";
        $type = $list?"rich":"text";
        return ['type' => $type ,"data" => $data];
    }

    private function getOriginalBlog(){
        $post_list = Posts::find()
            ->where([ 'original' => 1,'status' => 1 ])
            ->orderBy("updated_time desc")
            ->limit(5)
            ->all();

        $list = [];
        if( $post_list ){
            $domain_static = \Yii::$app->params['domains']['static'];
            $domain_m = \Yii::$app->params['domains']['m'];
            foreach($post_list as $_item){
                $tmp_image = "{$domain_static}/wx/".mt_rand(1,7).".jpg";
                if( $_item['image_url'] ){
                    $tmp_image = $_item['image_url'];
                }
                $list[] = [
                    "title" => $_item['title'],
                    "description" => $_item['title'],
                    "picurl" => $tmp_image,
                    "url" => "{$domain_m}/default/".$_item['id']
                ];
            }
        }
        $data = $list?$this->getRichXml($list):$this->help();
        $type = $list?"rich":"text";
        return ['type' => $type ,"data" => $data];
    }


    private function getRichXml($list){
        $article_count = count( $list );
        $article_content = "";
        foreach($list as $_item){
            $article_content .= "
<item>
<Title><![CDATA[{$_item['title']}]]></Title>
<Description><![CDATA[{$_item['description']}]]></Description>
<PicUrl><![CDATA[{$_item['picurl']}]]></PicUrl>
<Url><![CDATA[{$_item['url']}]]></Url>
</item>";
        }

        $article_body = "<ArticleCount>%s</ArticleCount>
<Articles>
%s
</Articles>";
        return sprintf($article_body,$article_count,$article_content);
    }


    private function help(){
        $resData = <<<EOT
郭大帅哥没有找到你想要的东西（：
试试hadoop,mysql等等，
 也可以直接去我的网站 www.vincentguo.cn
EOT;
        return $resData;
    }

    /**
     * 关注默认提示
     */
    private function subscribeTips(){
        $from = $this->getSource();
        if($from == "imguowei_888" ){
            $resData = <<<EOT
感谢您关注郭大帅哥的故事，除了菜单还可以输入关键字，郭大帅哥会回复你的！！
EOT;
        }else{
            $resData = <<<EOT
感谢您关注郭大帅哥的公众号
输入关键字,郭大帅哥会回复你的！！
EOT;
        }

        return $resData;
    }

    private function richMediaTips(){
        $resData = <<<EOT
郭大帅哥收到您提供的多媒体信息
审核通过之后就会在博客展示！！
EOT;
        return $resData;
    }

    private function urlTips(){
        $resData = <<<EOT
郭大帅哥收到您提供的链接
系统会自己抓取内容,审核之后就会展示！！
EOT;
        return $resData;
    }

    private function songTips(){
        $resData = <<<EOT
点歌，请回复“@歌曲名称或者@歌手名”
例如“@王菲”，“@匆匆那年”
EOT;
        return $resData;
    }
} 