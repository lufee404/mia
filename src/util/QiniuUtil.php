<?php
namespace mia\miagroup\Util;

use Qiniu\Auth;
use Qiniu\Storage\UploadManager;
use Qiniu\Processing\PersistentFop;
use Pili;
use \F_Ice;
use Qiniu;
use mia\miagroup\Lib\Redis;

class QiniuUtil {

    private $qiniuAuth;
    
    private $qiniuHub;

    private $config;

    public function __construct() {
        $this->config = F_Ice::$ins->workApp->config->get('busconf.qiniu');
        $this->qiniuAuth = new Auth($this->config['access_key'], $this->config['secret_key']);
        $this->qiniuHub = new \Pili\Hub(new \Qiniu\Credentials($this->config['access_key'], $this->config['secret_key']), $this->config['live_hub']);
    }

    /**
     * ******************
     */
    /**
     * * 七牛直播相关API **
     */
    /**
     * ******************
     */
    
    /**
     * 创建直播流
     */
    public function createStream($streamId) {
        $stream = $this->qiniuHub->createStream((string)$streamId);
        $stream = $stream->toJSONString();
        $stream = json_decode($stream, true);
        return $stream;
    }
    
    /**
     * 获取直播流
     */
    public function getStream($streamId) {
        $stream = $this->qiniuHub->getStream((string)$streamId);
        $stream = $stream->toJSONString();
        $stream = json_decode($stream, true);
        return $stream;
    }
    
    /**
     * 获取直播地址
     */
    public function getLiveUrls($streamId) {
        $idInfo = explode('.', $streamId);
        $returnData = [];
        if(isset($idInfo[2])){
            $returnData = [
                'hls' => 'http://' . $this->config['live_host']['hls'] . '/' . $this->config['live_hub'] . '/' . $idInfo[2] . ".m3u8",
                'rtmp'=> 'rtmp://' . $this->config['live_host']['rtmp'] . '/' . $this->config['live_hub'] . '/' . $idInfo[2],
                'hdl' => 'http://' . $this->config['live_host']['hdl'] . '/' . $this->config['live_hub'] . '/' . $idInfo[2] . ".flv",
            ];
        }
        return $returnData;
    }
    
    /**
     * 获取直播回放地址
     */
    public function getPalyBackUrls($streamId) {
        $idInfo = explode('.', $streamId);
        $returnData = [];
        if(isset($idInfo[2])){
            $returnData = [
                'hls' => 'http://' . $this->config['live_host']['playback'] . '/' . $this->config['live_hub'] . '/' . $idInfo[2] . '.m3u8?start=0&end=' . time(),
            ];
        }
        return $returnData;
    }
    
    /**
     * 获取直播流状态
     */
    public function getStatus($streamId){
        $stream = $this->qiniuHub->getStream($streamId);
        $result = $stream->status();
        $returnValue = 'connected';
        if(isset($result['status']) && $result['status']=='disconnected'){
            //策略： 直播状态首次中断并不立即返回中断状态，而是再次检测到中断并且相差5秒以上，才中断
            //此策略为防止第三方瞬间返回中断然而实际没中断问题
            //获取数量
            $liveStatusKey = sprintf(\F_Ice::$ins->workApp->config->get('busconf.rediskey.liveKey.live_stream_status.key'), $streamId);
            $redis = new Redis();
            $liveStreamStatus = $redis->get($liveStatusKey);
            $lastDisconnectedTime = intval($redis->get($liveStatusKey));
            if($lastDisconnectedTime > 0){
                if(time() - $lastDisconnectedTime >= 10){
                    $returnValue = 'disconnected';
                }
            }else{
                $redis->setex($liveStatusKey, time(), \F_Ice::$ins->workApp->config->get('busconf.rediskey.liveKey.live_stream_status.expire_time'));
            }
        }
        return $returnValue;
    }
    
    /**
     * 获取直播流状态
     */
    public function getRawStatus($streamId){
        $stream = $this->qiniuHub->getStream($streamId);
        $result = $stream->status();
        return $result;
    }
    
    /**
     * 获取直播快照
     */
    public function getSnapShot($streamId, $size = 100, $name = null, $time = null){
        $returnValue = [];
        if(!$name){
            $time = time();
            $name = "{$streamId}.{$time}.jpg";
        }
        $format = 'jpg';
        $stream = $this->qiniuHub->getStream($streamId);
        $result = $stream->snapshot($name, $format, $time);
        if(isset($result['targetUrl'])){
            $returnValue['origin'] = $result['targetUrl'];
            $returnValue[$size] = "{$result['targetUrl']}?imageView2/2/w/{$size}/h/{$size}/q/85";
        }
        return $returnValue;
    }
    
    /**
     * ******************
     */
    /**
     * * 七牛视频相关API **
     */
    /**
     * ******************
     */
    
    /**
     * 客户端获取上传token和key
     */
    public function getUploadTokenAndKey($filePath) {
        // 要上传的空间
        $bucket = $this->config['video_bucket'];
        // 生成上传 Token
        $token = $this->qiniuAuth->uploadToken($bucket);
        $key = $this->_getVideoFileName($filePath);
        return array('token' => $token, 'key' => $key);
    }

    /**
     * 上传视频文件
     */
    public function uploadVideoFile($filePath) {
        set_time_limit(0);
        // 要上传的空间
        $bucket = $this->config['video_bucket'];
        // 生成上传 Token
        $token = $this->qiniuAuth->uploadToken($bucket);
        // 上传到七牛后保存的文件名
        $key = $this->_getVideoFileName($filePath);
        // 初始化 UploadManager 对象并进行文件的上传。
        $uploadMgr = new UploadManager();
        // 调用 UploadManager 的 putFile 方法进行文件的上传。
        $ret = $uploadMgr->putFile($token, $key, $filePath);
        if (!isset($ret['error'])) {
            return $ret;
        } else {
            return false;
        }
    }

    /**
     * 视频文件转码，生成两个文件，格式分别为HLS-2400k和MP4 720P
     *
     * @param $key 要转码的文件名称            
     * @param $notifyUrl 转码完成后通知的业务服务器            
     */
    public function videoTrancodingHLS($key, $notifyUrl = null) {
        // 要转码的文件所在的空间
        $bucket = $this->config['video_bucket'];
        // 转码时使用的队列名称。
        $pipeline = $this->config['video_transcoding_pipe'];
        // 转码后的文件名
        $keyPre = explode('.', $key);
        $keyPre = $keyPre[0];
        $fileMP4 = $this->_urlsafe_base64_encode("{$bucket}:{$keyPre}.mp4");
        $fileHLS = $this->_urlsafe_base64_encode("{$bucket}:{$keyPre}.m3u8");
        // 要进行转码的转码操作(MP4)
        $fops_mp4 = "avthumb/mp4/s/1280x720/autoscale/1/vb/2400k|saveas/{$fileMP4}";
        // 要进行转码的转码操作(HLS)
        $fops_hls = "avthumb/m3u8/noDomain/1/vb/2400k|saveas/{$fileHLS}";
        $pfop = new PersistentFop($this->qiniuAuth, $bucket, $pipeline, $notifyUrl);
        $ret = $pfop->execute($key, $fops_hls);
        $ret1 = $pfop->execute($key, $fops_mp4); // MP4格式备用
        if (!isset($ret['error'])) {
            return $ret[0];
        } else {
            return false;
        }
    }

    /**
     * 获取视频转码是否完成
     */
    public function getVideoPfopStatus($persistentId) {
        // 通过persistentId查询该 触发持久化处理的状态
        $ret = PersistentFop::status($persistentId);
        if (!isset($ret['error'])) {
            if ($ret[0][code] == 0) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * 获取视频缩略图
     */
    public function getVideoThumb($videoUrl) {
        // 缩略图存储空间
        $bucket = $this->config['image_bucket'];
        $fileName = $this->_getImageFileName($videoUrl, 'jpg');
        $fileName = Qiniu\entry($bucket, $fileName);
        $url = substr($videoUrl, strlen('http://'));
        $url = $url . "?vframe/jpg/offset/1|imageMogr2/thumbnail/640x|saveas/$fileName";
        $image_url = 'http://' . $url . '/sign/' . $this->qiniuAuth->sign($url);
        $ret = file_get_contents($image_url);
        $ret = json_decode($ret, true);
        if (empty($ret['error'])) {
            return $this->config['image_host'] . $ret['key'];
        } else {
            return false;
        }
    }

    /**
     * 获取视频信息
     */
    public function getVideoFileInfo($videoUrl) {
        $url = $this->config['video_host'] . $videoUrl;
        $url .= '?avinfo';
        $ret = file_get_contents($url);
        $ret = json_decode($ret, true);
        return $ret['format'];
    }

    /**
     * 生成视频文件名
     */
    private function _getVideoFileName($filePath, $suffix = '') {
        if (strlen($suffix) <= 1) {
            // 获取文件后缀名
            $lenth = strrpos($filePath, '.');
            if ($lenth) {
                if (!$suffix) {
                    $suffix = substr($filePath, $lenth + 1);
                }
            } else {
                $suffix = '';
            }
        }
        $key = $this->config['video_bucket'] . '/' . date('Y/m/d') . '/' . md5($filePath . time() . rand(1000, 9999)) . '.' . $suffix;
        return $key;
    }

    /**
     * ******************
     */
    /**
     * * 七牛图片相关API **
     */
    /**
     * ******************
     */
    
    /**
     * 上传图片文件
     */
    public function uploadImageFile($param) {}

    /**
     * 生成图片文件名
     */
    private function _getImageFileName($filePath, $suffix = '') {
        if (strlen($suffix) <= 1) {
            // 获取文件后缀名
            $lenth = strrpos($filePath, '.');
            if ($lenth) {
                if (!$suffix) {
                    $suffix = substr($filePath, $lenth + 1);
                }
            } else {
                $suffix = '';
            }
        }
        $key = $this->config['image_bucket'] . '/' . date('Y/m/d') . '/' . md5($filePath . time() . rand(1000, 9999)) . '.' . $suffix;
        return $key;
    }

    private function _urlsafe_base64_encode($str) {
        $find = array('+', '/');
        $replace = array('-', '_');
        return str_replace($find, $replace, base64_encode($str));
    }
}