<?php
namespace Jeexiang;
/**
 * 配置：
 * 'aliyun_sms' =>[
 *      'access_key'=>'12345678',
 *      'access_secret' => 'bf152c66c869535ace62e11a59cdaec3'
 *  ]
 *  数据表
 *  CREATE TABLE `sms_history` (
 *    `id` int(11) NOT NULL AUTO_INCREMENT,
 *    `session_id` varchar(36) NOT NULL DEFAULT '' COMMENT '用户session_id',
 *    `mobile` varchar(15) NOT NULL DEFAULT '' COMMENT '手机号码',
 *    `code` varchar(6) NOT NULL DEFAULT '' COMMENT '验证码',
 *    `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态（0正常，1发送失败，2已验证）',
 *    `errormsg` varchar(500) NOT NULL DEFAULT '' COMMENT '错误信息',
 *    `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
 *    `update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
 *    PRIMARY KEY (`id`),
 *    KEY `mobile` (`mobile`) USING BTREE,
 *    KEY `session_id` (`session_id`) USING BTREE
 *  ) ENGINE=InnoDB AUTO_INCREMENT=33 DEFAULT CHARSET=utf8;
 */
use think\Db;
use Flc\Alidayu\Client;
use Flc\Alidayu\App;
use Flc\Alidayu\Requests\AlibabaAliqinFcSmsNumSend;
use Flc\Alidayu\Requests\IRequest;
class Aliyunsmsvcode
{
  public $success = false; //状态
  public $message = ''; // 成功或失败信息
  public $mobile = '';  // 手机号码
  public $session_id = ''; // session_id
  public $vcode = ''; // 验证码
  public $interval = 60; // 发送频率（秒）
  public $expiry_time = 30; // 短信有效期（分钟）
  public $sign_name = '珆熙'; //短信签名
  public $template_code = 'SMS_47110107'; // 短信模版编号
  /**
   * 校验验证码
   * @param  [type] $mobile [description]
   * @param  [type] $code   [description]
   * @return [type]         [description]
   */
  public function verify($mobile,$code){
    $this->mobile = $mobile;
    if($this->check_mobile()){
      $item = Db::name('sms_history')->where('mobile',$this->mobile)->where('status',0)->order('create_time desc')->limit('0,1')->find();
      if($item){
        if((time()-strtotime($sms['create_time'])) > 60*$this->expiry_time){
           $this->message ='验证码过期，请重新获取';
           $this->success = false;
        }else{
          $this->success = true;
        }
      }else{
        $this->success = false;
        $this->message = '验证码错误';
      }
    }
    return $this->success;
  }
  /**
   * 发送手机验证码
   * @param  [type] $mobile     [description]
   * @param  [type] $session_id [description]
   * @return [type]             [description]
   */
  public function send_vcode($mobile,$session_id){
    $this->mobile = $mobile;
    $this->session_id = $session_id;
    if($this->check_mobile()){
      $access_key = config('aliyun_sms.access_key');
      $access_secret = config('aliyun_sms.access_secret');
      if($access_key !='' && $access_secret!=''){
        if($this->sign_name == '' ){
          $this->success = false;
          $this->message = '未提供短信签名';
        }elseif($this->template_code == ''){
          $this->success = false;
          $this->message = '未提供短信模版';
        }else{
          $item = Db::name('sms_history')->where('session_id',$this->session_id)->where('mobile',$this->mobile)->where('status',0)->order('create_time desc')->limit('0,1')->find();
          if($item && (time()-strtotime($item['create_time'])) < $this->interval ){
            $this->message = '发送太频繁了，请稍后再发';
            $this->success = false;
          }else{
            $this->vcode = str_pad(mt_rand(1,999999),6,'0',STR_PAD_LEFT);
            $templateParam = ['parm0'=>$this->vcode];
            $outId = false;
            $c = new \TopClient;
            $c->appkey = $access_key;
            $c->secretKey = $access_secret;
            $req = new \AlibabaAliqinFcSmsNumSendRequest;
            $req->setExtend($mobile);
            $req->setSmsType("normal");
            $req->setSmsFreeSignName($this->sign_name);
            $req->setSmsParam(json_encode($templateParam));
            $req->setRecNum($mobile);
            $req->setSmsTemplateCode($this->template_code);
            $resp = $c->execute($req);
            if($resp && $resp->result && $resp->result->success == 'true'){
              $this->success = true;
              $this->message = '短信发送成功';
            }else{
              $this->message = '短信发送失败';
              $this->success = false;
            }
            Db::name('sms_history')->insert(['session_id'=>$this->session_id,'mobile'=>$this->mobile,'code'=>$this->vcode,'errormsg'=>json_encode($resp),'status'=>$this->success?0:1]);
          }
        }
      }else{
        $this->success = false;
        $this->message = '参数未配置';
      }
    }
    return $this->success;
  }
  /**
   * 检查手机号码合法性
   * @return [type] [description]
   */
  private function check_mobile(){
    $this->mobile = trim($this->mobile);
    if($this->mobile == ''){
      $this->success = false;
      $this->message = '请填写手机号码';
    }else if(!preg_match('/^1[35678]\d{9}$/', $this->mobile)){
      $this->success = false;
      $this->message = '请填写正确的手机号码';
    }else{
      $this->success = true;
    }
    return $this->success;
  }
}
