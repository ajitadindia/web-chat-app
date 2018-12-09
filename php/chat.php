<?php


class Chat {
	
	private $name;
	private $host;
	private $username;
	private $password;
	private $imageDir;
	//generate and set key first time in the below variable
	//$key = (new \CodeCollab\Encryption\Defusev2\Key())->generate();
	private $key = 'def00000062819ccdb3a11c44a260ec7f76cf72ed9a7dce09d8ea5646263ae4c2164a930eb88d636509df04e6e07d6435ff8804c69adb7bf031b89702d4c10fc7fa91406';
	
	function __construct($name, $host, $username, $password, $imageDir)
    {
        $this->dbh = new PDO('mysql:dbname='.$name.';host='.$host.";port=3306",$username, $password);
		$this->imageDir = $imageDir;
    }
	
	function user_login($user, $password, $avatar ){
		$name = htmlspecialchars($user);
		$data = array();
		$sql=$this->dbh->prepare("SELECT name,password FROM users WHERE name=?");
		$sql->execute(array($name));
		$e_password = $this->encrypt_message($password);
		if($sql->rowCount() == 0){
			$upd=$this->dbh->prepare("INSERT INTO users (name,password,avatar,login,status) VALUES (?,?,?,NOW(),?)");
			$status = $upd->execute(array($name, $e_password, $avatar, 'online'));
			$data['status'] = 'success';
		}elseif($sql->rowCount() == 1){
			while($r = $sql->fetch()){
				$d_password = $this->decrypt_message($r['password']);
				if( $d_password == $password ) {
					$upd=$this->dbh->prepare("UPDATE users SET avatar=?, login=NOW(), status=? WHERE name=?");
					$upd->execute(array($avatar, 'online', $name));
					$data['status'] = 'success';
				} else {
					$data['status'] = 'error';
				}
				break;
			}
		}else{
			$data['status'] = 'error';
		}
		return $data;
	}
	
	function get_message($type, $user_id, $user){
		$data = array();
		if($type == 'rooms'){
			if($user_id == 'all'){
				$sql=$this->dbh->prepare("SELECT * FROM messages WHERE type=? order by date DESC");
				$sql->execute(array($type));
			}else{
				$sql=$this->dbh->prepare("SELECT * FROM messages WHERE user_id=? order by date DESC");
				$sql->execute(array($user_id));
			}
			while($r = $sql->fetch()){
				$decryptedData = $this->decrypt_message($r['message']);
				$data[] = array(
					'name' => $r['name'],
					'avatar' => $r['avatar'],
					'message' => $decryptedData,
					'image' => $r['image'],
					'type' => $r['type'],
					'date' => $r['date'],
					'selector' => $r['user_id']
				);
			}
		}else if($type == 'users'){
			if($user_id == 'all'){
				$sql=$this->dbh->prepare("SELECT * FROM messages WHERE (name = :id1 AND type= :id2) OR (user_id = :id1 AND type = :id2) order by date DESC");
				$sql->execute(array(':id1' => $user, ':id2' => $type));
				$tmp = array();
				while($r = $sql->fetch()){
					$name = ($r['name'] == $user ? $r['user_id'] : $r['name']);
					if(!in_array($name, $tmp)){
						array_push($tmp, $name);
						$get=$this->dbh->prepare("SELECT status FROM users WHERE name=?");
						$get->execute(array($name));
						$decryptedData = $this->decrypt_message($r['message']);
						$data[] = array(
							'name' => $name,
							'avatar' => $r['avatar'],
							'date' => $r['date'],
							'message' => $decryptedData,
							'status' => $get->fetch()['status'],
							'selector' => ($r['name'] == $user ? "to" : "from")
						);
					}
				}
			}else{
				$sql=$this->dbh->prepare("SELECT * FROM messages WHERE (name = :id1 AND user_id= :id2) OR (name = :id2 AND user_id = :id1) order by date DESC");
				$sql->execute(array(':id1' => $user, ':id2' => $user_id));
				while($r = $sql->fetch()){
					$decryptedData = $this->decrypt_message($r['message']);
					$data[] = array(
						'name' => $r['name'],
						'avatar' => $r['avatar'],
						'message' => $decryptedData,
						'image' => $r['image'],
						'type' => $r['type'],
						'date' => $r['date'],
						'selector' => ($r['name'] == $user ? $r['user_id'] : $r['name'])
					);
				}
			}
		}
		return $data;
	}
	
	function get_user($user){
		if(isset($user)){
			$sqlm=$this->dbh->prepare("SELECT name FROM users WHERE name=?");
			$sqlm->execute(array($user));
			if($sqlm->rowCount() > 0){
				$upd=$this->dbh->prepare("UPDATE users SET login=NOW() WHERE name=?");
				$upd->execute(array($user));
			}
		}
		$data = array();
		$sql=$this->dbh->prepare("SELECT * FROM users");
		$sql->execute();
		while($r = $sql->fetch()){
			$data["all"][] = array(
				'name' => $r['name'],
				'avatar' => $r['avatar'],
				'login' => $r['login'],
				'status' => $r['status']
			);
		}
		$data["chat"] = $this->get_message("users", "all", $user);
		return $data;
	}
	
	function send_message($name, $user_id, $message, $image, $date, $avatar, $type){		
		$data = array();
		$encryptedData = $this->encrypt_message($message);
		$sql=$this->dbh->prepare("INSERT INTO messages (name,user_id,avatar,message,image,type,date) VALUES (?,?,?,?,?,?,?)");
		$sql->execute(array($name,$user_id,$avatar,$encryptedData,$image,$type,$date));
		$data['status'] = 'success';
		return $data;
	}
	
	function user_logout($name){
		$data = array();
		$user = $this->dbh->prepare("UPDATE users SET status=? WHERE name=?");
		$user->execute(array('offline',$name));
		$data['status'] = 'success';
		return $data;
	}
	
	// upload image
	function arrayToBinaryString($arr) {
		$str = "";
		foreach($arr as $elm) {
			$str .= chr((int) $elm);
		}
		return $str;
	}

	function createImg($string, $name, $type){
		$im = imagecreatefromstring($string); 
		if($type == 'image/png'){
				imageAlphaBlending($im, true);
				imageSaveAlpha($im, true);
			imagepng($im, $this->imageDir.'/'.$name);
		}else if($type == 'image/gif'){
			imagegif($im, $this->imageDir.'/'.$name);
		}else{
			imagejpeg($im, $this->imageDir.'/'.$name);
		}
		imagedestroy($im);
	}
	
	function encrypt_message($message) {
		return (new \CodeCollab\Encryption\Defusev2\Encryptor($this->key))->encrypt($message);
	}
	
	function decrypt_message($message) {
		return (new \CodeCollab\Encryption\Defusev2\Decryptor($this->key))->decrypt($message);
	}
}
