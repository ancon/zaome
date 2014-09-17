<?php

namespace User\Model;
use Think\Model;
/**
 * 会员模型
 */
class UcenterMemberModel extends Model{
	/* 用户模型自动验证 */
	protected $_validate = array(
		/* 验证用户名 */
		array('username', '5,30', -1, self::EXISTS_VALIDATE, 'length'), //用户名长度不合法
		array('username', 'checkDenyMember', -2, self::EXISTS_VALIDATE, 'callback'), //用户名禁止注册
		array('username', '', -3, self::EXISTS_VALIDATE, 'unique'), //用户名被占用

		/* 验证邮箱 */
		array('email', 'email', -4, self::EXISTS_VALIDATE), //邮箱格式不正确
		array('email', '3,32', -5, self::EXISTS_VALIDATE, 'length'), //邮箱长度不合法
		array('email', 'checkDenyEmail', -6, self::EXISTS_VALIDATE, 'callback'), //邮箱禁止注册
		array('email', '', -7, self::EXISTS_VALIDATE, 'unique'), //邮箱被占用

		/* 验证手机号码 */
		array('mobile', 'checkMobile', -8, self::EXISTS_VALIDATE, 'callback'), //手机格式不正确
		array('mobile', 'checkDenyMobile', -9, self::EXISTS_VALIDATE, 'callback'), //手机禁止注册
		array('mobile', '', -10, self::EXISTS_VALIDATE, 'unique'), //手机号被占用

		/* 验证密码 */
		array('password', '6,30', -11, self::EXISTS_VALIDATE, 'length'), //密码长度不合法
		/* 验证sms验证码 */
		array('smscode', 'checkSmsode', -12, self::EXISTS_VALIDATE, 'callback'), //验证码是否正确
		// array('smscode', 'smscode', -12, self::EXISTS_VALIDATE, 'confirm'), //验证码是否正确
		array('smscode', 'expireSmscode', -13, self::EXISTS_VALIDATE, 'callback'), //验证短信的有效期
		// array('smscode', array(sendtime,sendtime+1800), -13, self::EXISTS_VALIDATE, 'between'),
	);

	/* 用户模型自动完成 */
	protected $_auto = array(
		array('password', 'zaome_ucenter_md5', self::MODEL_BOTH, 'function', ZM_AUTH_KEY),
		array('reg_time', NOW_TIME, self::MODEL_INSERT),
		array('reg_ip', 'get_client_ip', self::MODEL_INSERT, 'function', 1),
		array('update_time', NOW_TIME),
		array('status', 'getStatus', self::MODEL_BOTH, 'callback'),
	);

	/**
	 * 检测用户名是不是被禁止注册,写在数据库里面吧
	 * @param  string $username 用户名
	 * @return boolean          ture - 未禁用，false - 禁止注册
	 */
	protected function checkDenyMember($username){
		return true; //TODO: 暂不限制，下一个版本完善
	}

	/**
	 * 检测邮箱是不是被禁止注册,查询注册过的邮箱
	 * @param  string $email 邮箱
	 * @return boolean       ture - 未禁用，false - 禁止注册
	 */
	protected function checkDenyEmail($email){
		return true; //TODO: 暂不限制，下一个版本完善
	}

	/**
	 * 检测手机号是否正确,声明一点,这个函数应该是变化的,因为手机号在发展.
	 * 目前只支持国内用户手机号.
	 * @param  string $mobile 手机
	 * @return boolean        ture - 正确，false - 手机号不对
	 */
	protected function checkMobile($mobile){
		return (strlen($mobile) == 11
			&& (preg_match("/^13\d{9}$/", $mobile)
				|| preg_match("/^15\d{9}$/", $mobile)
				|| preg_match("/^18\d{9}$/", $mobile)
				|| preg_match("/^14\d{9}$/", $mobile)
			)?ture:'';
				)
	}

	/**
	 * 检测手机是不是被禁止注册
	 * @param  string $mobile 手机
	 * @return boolean        ture - 未禁用，false - 禁止注册
	 */
	protected function checkDenyMobile($mobile){
		return true; //TODO: 暂不限制，下一个版本完善
	}

	/**
	 * 检查短信验证码是否正确
	 * @param string $mobile 手机号
	 * @param string $smscode 短信验证码
	 * @return boolean		ture - 验证码正确, false - 验证码错误
	 */
	protected function checkSmscode($mobile, $smscode){
		$dbsmscode = $this->where($mobile)->field('smscode')->find();
		// $dbsmscode = $this->getFieldByMobile($mobile,'smscode');
		if($smscode == $dbsmscode){
			return ture;
		} else {
			return $this->getError();
		}
	}
	/**
	 * 检查短信验证码有效期默认是半个小时
	 * @param string $mobile 手机号
	 * @param string $smscode 短信验证码
	 * @return boolean		ture - 验证码有效, false - 验证码过期
	 */
	protected function expireSmscode($mobile, $smscode, $expire = 1800){
		$sendtime = $this->where($mobile)->field('sendtime')->find();
		if($sendtime + $expire > NOW_TIME){
			return ture;
		} else {
			return $this->getError();
		}
	}

	/**
	 * 根据配置指定用户状态
	 * @return integer 用户状态
	 */
	protected function getStatus(){
		return true; //TODO: 暂不限制，下一个版本完善
	}

	/**
	 * 注册一个新用户
	 * @param  string $mobile   用户手机号码
	 * @param  string $smscode    短信验证码
	 * @return integer          注册成功-用户信息，注册失败-错误编号
	 */
	public function register($mobile){
		/* 添加用户 */
		if($this->create($mobile)){
			$uid = $this->add();
			return $uid ? $uid : 0; //0-未知错误，大于0-注册成功
		} else {
			return $this->getError(); //错误详情见自动验证注释
		}
	}

	/**
	 * 用户登录认证
	 * @param  string  $username 用户名
	 * @param  string  $password 用户密码
	 * @param  integer $type     用户名类型 （1-用户名，2-邮箱，3-手机，4-UID）
	 * @return integer           登录成功-用户ID，登录失败-错误编号
	 */
	public function login($username, $password, $type = 3){
		$map = array();
		switch ($type) {
			case 1:
				$map['username'] = $username;
				break;
			case 2:
				$map['email'] = $username;
				break;
			case 3:
				$map['mobile'] = $username;
				break;
			case 4:
				$map['id'] = $username;
				break;
			default:
				return 0; //参数错误
		}

		/* 获取用户数据 */
		$user = $this->where($map)->find();
		if(is_array($user) && $user['status']){
			/* 验证用户密码 */
			if(zaome_ucenter_md5($password, ZM_AUTH_KEY) === $user['password']){
				$this->updateLogin($user['id']); //更新用户登录信息
				return $user['id']; //登录成功，返回用户ID
			} else {
				return -2; //密码错误
			}
		} else {
			return -1; //用户不存在或被禁用
		}
	}

	/**
	 * 获取用户信息
	 * @param  string  $uid         用户ID或用户名
	 * @param  boolean $is_username 是否使用用户名查询
	 * @return array                用户信息
	 */
	public function info($uid, $is_username = false){
		$map = array();
		if($is_username){ //通过用户名获取
			$map['username'] = $uid;
		} else {
			$map['id'] = $uid;
		}

		$user = $this->where($map)->field('id,username,email,mobile,status')->find();
		if(is_array($user) && $user['status'] = 1){
			return array($user['id'], $user['username'], $user['email'], $user['mobile']);
		} else {
			return -1; //用户不存在或被禁用
		}
	}

	/**
	 * 检测用户信息
	 * @param  string  $field  用户名
	 * @param  integer $type   用户名类型 1-用户名，2-用户邮箱，3-用户电话
	 * @return integer         错误编号
	 */
	public function checkField($field, $type = 1){
		$data = array();
		switch ($type) {
			case 1:
				$data['username'] = $field;
				break;
			case 2:
				$data['email'] = $field;
				break;
			case 3:
				$data['mobile'] = $field;
				break;
			default:
				return 0; //参数错误
		}

		return $this->create($data) ? 1 : $this->getError();
	}

	/**
	 * 更新用户登录信息
	 * @param  integer $uid 用户ID
	 */
	protected function updateLogin($uid){
		$data = array(
			'id'              => $uid,
			'last_login_time' => NOW_TIME,
			'last_login_ip'   => get_client_ip(1),
		);
		$this->save($data);
	}

	/**
	 * 更新用户信息
	 * @param int $uid 用户id
	 * @param string $password 密码，用来验证
	 * @param array $data 修改的字段数组
	 * @return true 修改成功，false 修改失败
	 * @author huajie <banhuajie@163.com>
	 */
	public function updateUserFields($uid, $password, $data){
		if(empty($uid) || empty($password) || empty($data)){
			$this->error = '参数错误！';
			return false;
		}

		//更新前检查用户密码
		if(!$this->verifyUser($uid, $password)){
			$this->error = '验证出错：密码不正确！';
			return false;
		}

		//更新用户信息
		$data = $this->create($data);
		if($data){
			return $this->where(array('id'=>$uid))->save($data);
		}
		return false;
	}

	/**
	 * 增加用户密码
	 * @param int $uid 用户uid
	 * @param string $password 新的密码
	 * @return ture - 成功  false - 失败
	 */
	public function addPassword($uid, $password){
		if(empty($uid) || empty($password)){
			$this->error = '参数错误！';
			return false;
		}

		//更新用户信息
		$data = $this->create($password);
		if($data){
			return $this->where(array('id'=>$uid))->save($password);
		}
		return false;
	}

	/**
	 * 验证用户密码
	 * @param int $uid 用户id
	 * @param string $password_in 密码
	 * @return true 验证成功，false 验证失败
	 * @author huajie <banhuajie@163.com>
	 */
	protected function verifyUser($uid, $password_in){
		$password = $this->getFieldById($uid, 'password');
		if(zaome_ucenter_md5($password_in, ZM_AUTH_KEY) === $password){
			return true;
		}
		return false;
	}

    /**
     * 注销当前用户
     * @return void
     */
    public function logout(){
        session('user_auth', null);
        session('user_auth_sign', null);
        return ture;
    }


}
