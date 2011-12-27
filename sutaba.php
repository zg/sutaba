<?php
//sutaba
//re-written by zgold [2011-08-04]

//**************************** CONFIG ****************************
$cfg = array (
	'board1' => array (
		'syspath'          => '/srv/http/org/sutaba/',
		'thumbpath'        => 'thumb/',
		'imgpath'          => 'src/',
		'webpath'          => '/', // relative path from the domain; i.e.: http://www.example.com/sutaba/ means this variable would be 'sutaba/'
		'mysql'            => array (
			'hostname' => '',
			'username' => '',
			'password' => '',
			'database' => ''
		),
		'title'            => '&#12473;&#12479;&#12496; &#12481;&#12515;&#12493;&#12523; / sutaba channel',
		'desc'             => 'intimate discussion on a broad array of topics',
		'img'              => array (
			'enabled' => true, //images enabled on this board?
			'max_width' => 250,
			'max_height' => 250,
			'max_size' => 10485760 // 10MB
		),
		'datetime_format'  => 'm/d/y H:i:s',
		'guest_name'       => 'Anonymous',
		'threads_per_page' => 15, // messages per page
		'delay_time'       => 5, // time in seconds for how long you have to wait before posting again
		'session_expire'   => (60 * 60 * 24 * 7), // when the session should expire
		'permissions'      => 'all',//array('127.0.0.1'), //permissions for this board; 'all' = anyone, or make this into an array of IPs to allow
		'subject_min'      => 3, // minimum subject length
		'comment_min'      => 5, // minumum comment length
		'subject_max'      => 100, // maximum subject length (BE SURE TO CHANGE THE DATABASE!)
		'comment_max'      => 2500, // maximum comment length
		'image_required'   => true, // image required to post a new topic?
		'access'           => array (
			'admin' => array ( //admin tripcodes
				'LLVegDyAFo',
				'8pjmkGgGGE' //webs
			),
			'mod' => array ( //mod tripcodes
			)
		)
	)/*,
	'cc' => array (
		'syspath'          => '/srv/http/org/sutaba/',
		'thumbpath'        => 'thumb/',
		'imgpath'          => 'src/',
		'webpath'          => '/', // relative path from the domain; i.e.: http://www.example.com/sutaba/ means this variable would be 'sutaba/'
		'mysql'            => array (
			'hostname' => '',
			'username' => '',
			'password' => '',
			'database' => ''
		),
		'title'            => 'constructive criticism',
		'desc'             => '',
		'img'              => array (
			'enabled' => true, //images enabled on this board?
			'max_width' => 250,
			'max_height' => 250,
			'max_size' => 10485760 // 10MB
		),
		'datetime_format'  => 'm/d/y H:i:s',
		'guest_name'       => 'Anonymous',
		'threads_per_page' => 15, // messages per page
		'delay_time'       => 5, // time in seconds for how long you have to wait before posting again
		'session_expire'   => (60 * 60 * 24 * 7), // when the session should expire
		'permissions'      => 'all',//array('127.0.0.1'), //permissions for this board; 'all' = anyone, or make this into an array of IPs to allow
		'subject_min'      => 3, // minimum subject length
		'comment_min'      => 5, // minumum comment length
		'subject_max'      => 100, // maximum subject length (BE SURE TO CHANGE THE DATABASE!)
		'comment_max'      => 2500, // maximum comment length
		'image_required'   => false, // image required to post a new topic?
		'access'           => array (
			'admin' => array ( //admin tripcodes
				'LLVegDyAFo',
				'8pjmkGgGGE' //webs
			),
			'mod' => array ( //mod tripcodes
			)
		)
	)*/
);

ob_start();
session_start();
//**************************** CONTROLLER ****************************
class Sutaba {
	private $common; //common class connection
	private $connect; //mysql connection

	public function __construct($board)
	{
		global $common,$cfg;
		$this->common = $common;
		if(!isset($_SESSION[$board]['session_expire']))
		{
			$time = time();
			$expiration = time() + $cfg[$board]['session_expire'];
			setcookie('PHPSESSID',session_id(),$expiration,$cfg[$board]['webpath']);
			$_SESSION[$board]['session_expire'] = $expiration;
		}
		//add trailing slashes if they're missing on any cfg vars with "path"
		if(!$this->common->has_permission($board))
			die("You don't have permission to view this board.");
		foreach($cfg[$board] as $index => $value)
			if(strpos($index,'path') && $index !== 'webpath')
				if($cfg[$board][$index][(strlen($cfg[$board][$index]) - 1)] !== '/')
					$cfg[$board][$index] .= '/';
		$this->connect = mysql_connect($cfg[$board]['mysql']['hostname'],$cfg[$board]['mysql']['username'],$cfg[$board]['mysql']['password']);
		mysql_select_db($cfg[$board]['mysql']['database'],$this->connect);
		header('Content-Type: text/html; charset=UTF-8');
		if(!isset($_SESSION[$board]['password']))
			$_SESSION[$board]['password'] = $this->common->generate_password();
		if($cfg[$board]['img']['enabled'] === true && !$this->check_img_permissions())
			die('The image paths specified cannot be written to!');
	}

	public function get_thread($id=false,$page=false)
	{
		$return_val = array();
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if($id !== false && !is_numeric($id))
			return $return_val;
		$start = ($page - 1) * $cfg[$board]['threads_per_page'];
		$sql = "SELECT threads.id,threads.parent_id,threads.time,threads.ip,threads.name,threads.email,threads.subject,threads.comment,threads.file,threads.password,threads.pinned,threads.locked FROM posts AS threads LEFT JOIN posts AS replies ON threads.id = replies.parent_id WHERE threads.board = '$board' AND threads.parent_id = '0'".($id !== false ? " AND threads.id = '$id'" : '')." GROUP BY threads.id ORDER BY threads.pinned DESC, coalesce(MAX(replies.time),threads.time) DESC, threads.time DESC".($page !== false ? " LIMIT $start,{$cfg[$board]['threads_per_page']}" : "");
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
		{
			while($result = mysql_fetch_assoc($query))
			{
				if($id !== false)
				{
					$return_val = $result;
					$return_val['replies'] = $this->get_post($result['id'],true);
				}
				else
				{
					$result['replies'] = $this->get_post($result['id'],true);
					$return_val[] = $result;
				}
			}
		}
		return $return_val;
	}

	public function get_post($id,$has_replies=false)
	{
		$return_val = array();
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($id))
			return $return_val;
		$sql = "SELECT id,parent_id,time,ip,name,email,subject,comment,file,password,pinned,locked FROM posts WHERE board = '$board' AND ".($has_replies ? 'parent_' : '')."id = '$id'";
		$query = mysql_query($sql);
		$num_rows = mysql_num_rows($query);
		if(0 < mysql_num_rows($query))
		{
			while($result = mysql_fetch_assoc($query))
			{
				if($has_replies === true)
					$return_val[] = $result;
				else
					$return_val = $result;
			}
		}
		return $return_val;
	}

	public function get_thread_count()
	{
		$return_val = 0;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$sql = "SELECT count(*) AS cout FROM posts";
		$query = mysql_query($sql);
		$result = mysql_fetch_assoc($query);
		return $result['cout'];
	}

	public function is_thread($id)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($id))
			return $return_val;
		$sql = "SELECT id FROM posts WHERE board = '$board' AND id = '$id' AND parent_id = '0' LIMIT 1";
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
			$return_val = true;
		return $return_val;
	}

	public function is_post($id)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($id))
			return $return_val;
		$sql = "SELECT id FROM posts WHERE board = '$board' AND id = '$id' LIMIT 1";
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
			$return_val = true;
		return $return_val;
	}

	public function is_locked($id)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($id))
			return $return_val;
		$sql = "SELECT locked FROM posts WHERE board = '$board' AND id = '$id' AND locked = '1' LIMIT 1";
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
			$return_val = true;
		return $return_val;
	}

	public function get_bans($ip=false,$sort=false)
	{
		$return_val = array();
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if($ip !== false && !$this->is_banned($ip))
			return $return_val;
		$sql = "SELECT id,board,time,ip,expires,reason FROM bans WHERE board = '$board'".($ip !== false ? " AND ip = '$ip'" : "").($sort !== false ? " ORDER BY ".$sort : "");
		$query = mysql_query($sql);
		if(mysql_num_rows($query) == 0)
			return $return_val;
		if($ip !== false)
			$return_val = mysql_fetch_assoc($query);
		else
			while($result = mysql_fetch_assoc($query))
				$return_val[$result['id']] = $result;
		return $return_val;
	}

	public function get_reports($sort=false)
	{
		$return_val = array();
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$sql = "SELECT DISTINCT reports.post_id,reports.board,reports.time,reports.ip,posts.parent_id FROM reports LEFT JOIN posts ON reports.post_id = posts.id WHERE reports.board = '$board'".($sort !== false ? " ORDER BY reports.".$sort : "");
		$query = mysql_query($sql);
		if(mysql_num_rows($query) == 0)
			return $return_val;
		while($result = mysql_fetch_assoc($query))
		{
			if(isset($return_val[$result['post_id']]))
				$return_val[$result['post_id']]['ip'][] = $result['ip'];
			else
			{
				$result['ip'] = array($result['ip']);
				$return_val[$result['post_id']] = $result;
			}
		}
		return $return_val;
	}

	public function delete_reports($post_id)
	{
		$return_val = array();
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		if(!$this->is_post($post_id))
			return $return_val;
		$sql = "DELETE FROM reports WHERE board = '$board' AND post_id = '$post_id'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function get_wordfilters($sort=false)
	{
		$return_val = array();
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$sql = "SELECT wordfilter.* FROM wordfilter WHERE wordfilter.board = '$board'".($sort !== false ? " ORDER BY wordfilter.".$sort : "");
		$query = mysql_query($sql);
		if(mysql_num_rows($query) == 0)
			return $return_val;
		while($result = mysql_fetch_assoc($query))
		{
			$return_val[] = $result;
		}
		return $return_val;
	}

	public function add_wordfilter($word,$replacement)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if($word == $replacement)
			return $return_val;
		$sql = "INSERT INTO wordfilter (board,word,replacement) VALUES ('$board','$word','$replacement');";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function delete_wordfilter($wordfilter_id)
	{
		$return_val = array();
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($wordfilter_id))
			return $return_val;
		$sql = "DELETE FROM wordfilter WHERE board = '$board' AND id = '$wordfilter_id'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function is_banned($ip=false)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$time = time();
		$ip = ($ip !== false ? $ip : $_SERVER['REMOTE_ADDR']);
		$sql = "SELECT expires FROM bans WHERE board = '$board' AND ip = '$ip'";
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
		{
			while($result = mysql_fetch_assoc($query))
			{
				if(0 < $result['expires'])
				{
					if($result['expires'] < time())
					{
						$sql = "DELETE FROM bans WHERE board = '$board' AND ip = '$ip'";
						$return_val = mysql_query($sql);
					}
					else
						$return_val = true;
				}
				else
					$return_val = true;
			}
		}
		return $return_val;
	}

	public function cleanup_bans()
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$time = time();
		$sql = "DELETE FROM bans WHERE board = '$board' AND expires < '$time'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function ban_user($ip,$post_id,$expires,$reason)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!$this->is_post($post_id))
			return $return_val;
		$time = time();
		$expires = $expires + $time;
		$ip = $ip;
		$sql = "INSERT INTO bans (board,post_id,time,ip,expires,reason) VALUES ('$board','$post_id','$time','$ip','$expires','$reason')";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function unban_user($ip)
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$sql = "DELETE FROM bans WHERE board = '$board' AND ip = '$ip'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function toggle_thread_attributes($post_id,$locked=false,$pinned=false)
	{
		$return_val = false;
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		if(!$common->is_staff())
			return $return_val;
		$attributes = array();
		if($locked !== false && $common->is_staff())
		{
			if(is_numeric($locked))
				$attributes[] = "locked = '$locked'";
			else
			{
				$locked_sql = "SELECT locked FROM posts WHERE board = '$board' AND id = '$post_id'";
				$locked_query = mysql_query($locked_sql);
				if(0 < mysql_num_rows($locked_query))
				{
					$locked_result = mysql_fetch_assoc($locked_query);
					$attributes[] = "locked = '".($locked_result['locked'] == 1 ? "0" : "1")."'";
				}
			}
		}
		if($pinned !== false && $common->is_admin())
		{
			if(is_numeric($pinned))
				$attributes[] = "pinned = '$pinned'";
			else
			{
				$pinned_sql = "SELECT pinned FROM posts WHERE board = '$board' AND id = '$post_id'";
				$pinned_query = mysql_query($pinned_sql);
				if(0 < mysql_num_rows($pinned_query))
				{
					$pinned_result = mysql_fetch_assoc($pinned_query);
					$attributes[] = "pinned = '".($pinned_result['pinned'] == 1 ? "0" : "1")."'";
				}
			}
		}
		if(count($attributes) == 0)
			return $return_val;
		$sql = "UPDATE posts SET ".implode(",",$attributes)." WHERE board = '$board' AND id = '$post_id' AND parent_id = '0'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function create_post(array $data)
	{
		$return_val = false;
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;

		$_SESSION[$board]['name'] = $data['name'];
		$_SESSION[$board]['email'] = $data['email'];
		$_SESSION[$board]['password'] = $data['password'];

		$parent_id = 0;
		if(isset($data['parent_id']))
		{
			if(!$this->is_thread($data['parent_id']))
				return $common->redirect('error=thread_doesnt_exist',true);
			if($this->is_locked($data['parent_id']) && !$common->is_staff())
				return $common->redirect('error=thread_locked',true);
			$parent_id = $data['parent_id'];
		}
		if(strlen(trim($data['subject'])) < $cfg[$board]['subject_min'] && $parent_id == 0)
			return $common->redirect('error=subject_length',true);
		if(strlen(trim($data['comment'])) < $cfg[$board]['comment_min'])
		{
			if(strlen(trim($data['comment'])) == 0 && $common->is_staff() && $parent_id !== 0)
				return $this->toggle_thread_attributes($parent_id,(isset($data['locked']) ? 1 : 0),(isset($data['pinned']) ? 1 : 0));
			return $common->redirect('error=comment_length',true);
		}
		if($cfg[$board]['subject_max'] < strlen(trim($data['subject'])))
			return $common->redirect('error=subject_too_long',true);
		if($cfg[$board]['comment_max'] < strlen(trim($data['comment'])) && !$common->is_staff())
			return $common->redirect('error=comment_too_long',true);

		if(!$this->can_post())
			return $common->redirect('error=wait',true);
		$time = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		if(strpos($data['name'],'#'))
		{
			list($name,$tripcode_password) = explode('#',$data['name'],2);
			if(0 < strlen($tripcode_password))
				$tripcode = $common->generate_tripcode($tripcode_password);
		}
		else
		{
			$name = $data['name'];
		}
		$name_to_post = $name.(isset($tripcode) ? '#'.$tripcode : '');
		$email = $data['email'];
		$subject = $data['subject'];
		$comment = ($common->is_admin() ? str_replace('&#039;',"\'",html_entity_decode($data['comment'])) : $data['comment']);
		$file = '';
		if($cfg[$board]['img']['enabled'] === true)
		{
			if(isset($_FILES['file']) && isset($_FILES['file']['size']) && 0 < $_FILES['file']['size'])
			{
				$file = $this->process_image($_FILES['file']);
				switch($file)
				{
					case 'type':
						$common->redirect('error=invalid_filetype',true);
					break;
					case 'size':
						$common->redirect('error=file_too_large',true);
					break;
				}
			}
		}
		if(count($file) == 8)
			list($filename,$type,$size,$time,$tail,$location,$width,$height) = $file;
		else
			$location = '';
		if($location == '' && $cfg[$board]['image_required'] && $parent_id == 0 && !$common->is_staff())
			return $common->redirect('error=image_required');
		$password = sha1((isset($data['password']) ? $data['password'] : $_SESSION[$board]['password']));
		$is_admin = (isset($tripcode) && in_array($tripcode,$cfg[$board]['access']['admin']) ? true : false);
		$is_mod = (isset($tripcode) && in_array($tripcode,$cfg[$board]['access']['mod']) ? true : false);
		$pinned = 0;
		$locked = 0;
		if($is_admin || $is_mod)
		{
			$pinned = (isset($data['pinned']) ? 1 : 0);
			$locked = (isset($data['locked']) ? 1 : 0);
			if($parent_id !== 0)
			{
				mysql_query("UPDATE posts SET pinned = '$pinned', locked = '$locked' WHERE id = '$parent_id'");
				$pinned = 0;
				$locked = 0;
			}
		}

		$sql = "INSERT INTO posts (board,parent_id,time,ip,name,email,subject,comment,file,password,pinned,locked) VALUES ('$board','$parent_id','$time','$ip','$name_to_post','$email','$subject','$comment','$location','$password','$pinned','$locked');";
		mysql_query($sql);
		$post_id = mysql_insert_id($this->connect);

		if($file !== '')
			mysql_query("INSERT INTO images (filename,type,size,width,height) VALUES ('$location','$type','$size','$width','$height');");

		$thread_id = ($parent_id == 0 ? $post_id : $data['parent_id']);
		return $common->redirect("action=view_thread&thread_id=$thread_id#$post_id");
	}

	public function delete_post($post_id,$password=false)
	{
		$return_val = false;
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		$password = sha1($password);
		$sql = "SELECT parent_id,file FROM posts WHERE board = '$board' AND id = '$post_id'".($common->is_staff() ? "" : " AND password = '$password'");
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
		{
			$parent_id = 0;
			while($result = mysql_fetch_assoc($query))
			{
				$parent_id = $result['parent_id'];
				$delete_post_sql = "DELETE FROM posts WHERE board = '$board' AND id = '$post_id'";
				mysql_query($delete_post_sql);
				if(0 < strlen($result['file']))
				{
					$delete_images_sql = "DELETE FROM images WHERE filename = '{$result['file']}'";
					mysql_query($delete_images_sql);
					unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$result['file']);
					unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$result['file']);
				}
				if($parent_id == 0)
				{
					$delete_replies_sql = "DELETE FROM posts WHERE board = '$board' AND parent_id = '$post_id'";
					mysql_query($delete_replies_sql);
					$select_replies_sql = "SELECT id,file FROM posts WHERE board = '$board' AND parent_id = '$post_id'";
					$select_replies_query = mysql_query($select_replies_sql);
					if(0 < mysql_num_rows($select_replies_query))
					{
						while($select_replies_result = mysql_fetch_assoc($select_replies_query))
						{
							$delete_images_sql = "DELETE FROM images WHERE filename = '{$delete_posts_result['file']}'";
							mysql_query($delete_images_sql);
							unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$select_replies_result['file']);
							unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$select_replies_result['file']);
						}
					}
				}
			}
		}
		return $common->redirect("");
	}

	public function delete_image($post_id,$password=false)
	{
		$return_val = false;
		global $cfg,$board,$common;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		$password = sha1($password);
		$sql = "SELECT parent_id,file FROM posts WHERE board = '$board' AND id = '$post_id'".($common->is_staff() ? "" : " AND password = '$password'");
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
		{
			$parent_id = 0;
			while($result = mysql_fetch_assoc($query))
			{
				$parent_id = $result['parent_id'];
				$delete_images_sql = "DELETE FROM images WHERE filename = '{$result['file']}'";
				mysql_query($delete_images_sql);
				unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$result['file']);
				unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$result['file']);
				$update_posts_sql = "UPDATE posts SET file = '' WHERE id = '$post_id'";
				mysql_query($update_posts_sql);
			}
			if($parent_id !== 0)
				return $common->redirect("action=view_thread&thread_id=$parent_id#$post_id");
		}
		return $common->redirect("");
	}

	public function report_post($post_id)
	{
		$return_val = false;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		$time = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		$sql = "SELECT ip FROM reports WHERE board = '$board' AND post_id = '$post_id' AND ip = '$ip'";
		$query = mysql_query($sql);
		if(mysql_num_rows($query) == 0)
		{
			$insert_report_sql = "INSERT INTO reports (board,post_id,time,ip) VALUES ('$board','$post_id','$time','$ip')";
			$return_val = mysql_query($insert_report_sql);
		}
		return $return_val;
	}

	public function get_report_count($post_id)
	{
		$return_val = 0;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!is_numeric($post_id))
			return $return_val;
		$sql = "SELECT id FROM reports WHERE board = '$board' AND post_id = '$post_id'";
		$query = mysql_query($sql);
		$return_val = mysql_num_rows($query);
		return $return_val;
	}

	public function cleanup_reports()
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$sql = "SELECT DISTINCT post_id,id FROM reports WHERE board = '$board'";
		$query = mysql_query($sql);
		while($result = mysql_fetch_assoc($query))
		{
			if(!$this->is_post($result['post_id']))
			{
				$sql = "DELETE FROM reports WHERE board = '$board' AND id = '{$result['id']}'";
				$return_val = mysql_query($sql);
			}
		}
		return $return_val;
	}

	public function check_img_permissions()
	{
		$return_val = false;
		global $cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$image_paths = array($cfg[$board]['thumbpath'],$cfg[$board]['imgpath']);
		foreach($image_paths as $path)
			if(!is_writable($path))
				return $return_val;
		$return_val = true;
		return $return_val;
	}
	
	public function process_image($image)
	{
		global $cfg,$board;
		$name      = mysql_escape_string($image['name']);
		$extension = explode('.',strtolower($image['name']));
		$type      = $image['type'];
		$tmp_name  = $image['tmp_name'];
		$error     = $image['error'];
		$size      = $image['size'];
		$time      = time();
		$tail      = substr(microtime(),2,3);
		if($error == 4)
		{
			return 'nofile';
		}
		switch($type){
			case 'image/jpg':
			case 'image/jpeg':
				$img = @imagecreatefromjpeg($tmp_name);
				if(!$img)
					return 'type';
				else {
					$width = imagesx($img);
					$height = imagesy($img);
				}
			break;
			case 'image/gif':
				$img = @imagecreatefromgif($tmp_name);
				if(!$img)
					return 'type';
				else {
					$width = imagesx($img);
					$height = imagesy($img);
				}
			break;
			case 'image/png':
				$img = @imagecreatefrompng($tmp_name);
				if(!$img)
					return 'type';
				else {
					$width = imagesx($img);
					$height = imagesy($img);
				}
			break;
			default:
				return 'type';
			break;
		}
		if(10485760 < $size)
			return 'size';
		$location = $time.$tail.'.'.$extension[(count($extension) - 1)];
		@move_uploaded_file($tmp_name,$cfg[$board]['syspath'].$cfg[$board]['imgpath'].$location);
		$this->process_thumbnail (
			$cfg[$board]['syspath'].$cfg[$board]['imgpath'].$location,
			$cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$location
		);
		return array($name,$type,$size,$time,$tail,$location,$width,$height);
	}

	public function process_thumbnail($name,$filename)
	{
		global $cfg,$board;
		$extension = explode(".",$name);
		$extension = $extension[(count($extension) - 1)];
		if($extension == "jpg" || $extension == "jpeg")
			$source = @imagecreatefromjpeg($name);
		if($extension == "png")
			$source = @imagecreatefrompng($name);
		if($extension == "gif")
			$source = @imagecreatefromgif($name);
		$old_width = imageSX($source);
		$old_height = imageSY($source);
		if($cfg[$board]['img']['max_width'] < $old_width || $cfg[$board]['img']['max_height'] < $old_height){
			$key_width = $cfg[$board]['img']['max_width'] / $old_width;
			$key_height = $cfg[$board]['img']['max_height'] / $old_height;
			($key_width < $key_height) ? $keys = $key_width : $keys = $key_height;
			$thumb_width = ceil($old_width * $keys) + 1;
			$thumb_height = ceil($old_height * $keys) + 1;
		} else {
			$thumb_width = $old_width;
			$thumb_height = $old_height;
		}
		$destination = imagecreatetruecolor($thumb_width,$thumb_height);
		imagecopyresampled($destination,$source,0,0,0,0,$thumb_width,$thumb_height,$old_width,$old_height);
		switch($extension){
			case 'png':
				imagepng($destination,$filename); 
			break;
			case 'jpg':
			case 'jpeg':
				imagejpeg($destination,$filename); 
			break;
			case 'gif':
				imagegif($destination,$filename);
			break;
		}
		imagedestroy($destination);
		imagedestroy($source);
	}

	public function cleanup_spam()
	{
		$return_val = false;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		$time = time();
		$sql = "DELETE FROM spam WHERE board = '$board' AND time < '$time'";
		$return_val = mysql_query($sql);
		return $return_val;
	}

	public function can_post()
	{
		$return_val = false;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if($common->is_staff())
			return true;
		$time = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		$sql = "SELECT time FROM spam WHERE board = '$board' AND ip = '$ip' LIMIT 1";
		$query = mysql_query($sql);
		if(0 < mysql_num_rows($query))
		{
			while($result = mysql_fetch_assoc($query))
			{
				if($result['time'] < $time)
				{
					$sql = "DELETE FROM spam WHERE board = '$board' AND ip = '$ip'";
					$return_val = mysql_query($sql);
				}
			}
		}
		else
		{
			$sql = "INSERT INTO spam (board,ip,time) VALUES ('$board','$ip','$time');";
			$return_val = mysql_query($sql);
		}
		return $return_val;
	}
}

class common {
	public $errors = array (
		'thread_doesnt_exist' => 'The thread you tried to request does not exist.',
		'thread_locked'       => 'The thread you tried to reply to is locked.',
		'wait'                => 'Please wait before attempting to post again.',
		'invalid_filetype'    => 'The file you uploaded contains an invalid/unsupported filetype.',
		'file_too_large'      => 'The file you uploaded is too large.',
		'board_not_found'     => 'The board you specified could not be found or you do not have permission to view it.',
		'password_missing'    => 'You must input a password to delete a post or image.',
		'thread_not_found'    => 'The thread you specified could not be found or you do not have permission to view it.',
		'image_required'      => 'An image is required to start a new thread.'
	);

	public function clean($elem)
	{
		if(!is_array($elem))
			$elem = htmlentities($elem,ENT_QUOTES | ENT_IGNORE,'UTF-8',false);
		else
			foreach ($elem as $key => $value)
				$elem[$key] = $this->clean($value);
		return $elem;
	}

	public function validate_email($email,$return=false)
	{
		return (preg_match("/([0-9a-z][-_.]?[0-9a-z]*)@([0-9a-z][-.]?[0-9a-z]*\\.[a-z]{2,3})/",$email,$match)?($return?$match:true):false);
	}

	public function format_size($size,$decimals=1)
	{
		$suffix = array('B','KB','MB','GB','TB','PB','EB','ZB','YB','NB','DB');
		$index = 0;
		while($size >= 1024 && ($index < count($suffix) - 1))
		{
			$size /= 1024;
			$index++;
		}
		return round($size, $decimals).' '.$suffix[$index];
	}

	public function redirect($location,$maintain_GET=false,$html=false,$wait=0)
	{
		$redirect_to = array();
		$location = str_replace('?','',$location);
		if($maintain_GET === true)
		{
			global $_CLEAN;
			foreach($_CLEAN['GET'] as $index => $value)
				$redirect_to[$index] = $value;
		}
		$implode_array = array();
		foreach($redirect_to as $index => $value)
			$implode_array[] = $index.'='.$value;
		$redirect_to = implode('&',$implode_array);
		if($html)
			return '<meta http-equiv="refresh" content="'.$wait.';?'.$location.'" />';
		else
			header('Location: ?'.(strpos($redirect_to,$location) ? $redirect_to : $redirect_to.(0 < strlen($redirect_to) ? '&' : '').$location));
	}

	public function is_admin()
	{
		$return_val = false;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!isset($_SESSION[$board]['name']))
			return $return_val;

		if(strpos($_SESSION[$board]['name'],'#'))
		{
			list($name,$tripcode) = explode('#',$_SESSION[$board]['name'],2);
			if(in_array($this->generate_tripcode($tripcode),$cfg[$board]['access']['admin']))
				$return_val = true;
		}

		return $return_val;
	}

	public function is_mod()
	{
		$return_val = false;
		global $common,$cfg,$board;
		if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
			return $return_val;
		if(!isset($_SESSION[$board]['name']))
			return $return_val;

		if(strpos($_SESSION[$board]['name'],'#'))
		{
			list($name,$tripcode) = explode('#',$_SESSION[$board]['name'],2);
			if(in_array($this->generate_tripcode($tripcode),$cfg[$board]['access']['mod']))
				$return_val = true;
		}

		return $return_val;
	}

	public function is_staff()
	{
		return ($this->is_admin() || $this->is_mod());
	}

	public function has_permission($board)
	{
		global $cfg,$_CLEAN;
		if($cfg[$board]['permissions'] == 'all')
			return true;
		if(in_array($_SERVER['REMOTE_ADDR'],$cfg[$board]['permissions']))
			return true;
		//cookie check for valid tripcode
		return false;
	}

	public function generate_tripcode($password)
	{
		$password = mb_convert_encoding($password,'SJIS','UTF-8');
		$password = str_replace(array('&','"',"'",'<','>'),array('&amp;','&quot;','&#39;','&lt;','&gt;'),$password);
		$salt = substr($password.'H.',1,2);
		$salt = preg_replace('/[^.\/0-9:;<=>?@A-Z\[\\\]\^_`a-z]/','.',$salt);
		$salt = strtr($salt,':;<=>?@[\]^_`','ABCDEFGabcdef');
		return substr(crypt($password,$salt),-10);
	}

	public function generate_password($length=16,$params=array('nums'=>true,'alpha'=>true,'cap_alpha'=>true,'special'=>true))
	{
		$password = '';
		$characters = array();
		if($params['nums'] === true)
			foreach(range(0,9) as $number)
				$characters[] = $number;
		if($params['alpha'] === true)
			foreach(range('a','z') as $alpha)
				$characters[] = $alpha;
		if($params['cap_alpha'] === true)
			foreach(range('A','Z') as $alpha)
				$characters[] = $alpha;
		if($params['special'] === true)
			foreach(array('!','@','$','*','-','_','+','=') as $special)
				$characters[] = $special;
		for($index = 0; $index < $length; $index++)
			$password .= $characters[mt_rand(0,(count($characters) - 1))];
		return $password;
	}
	
	public function relative_time($date)
	{
		$diff = time() - strtotime($date);
		$plural = function($diff,$negative=false){
			return ($negative ? (-$diff == 1 ? '' : 's') : ($diff == 1 ? '' : 's'));
		};
		if($diff > 0)
		{
			if($diff < 60)
				return $diff . " second" . $plural($diff) . " ago";
			$diff = round($diff / 60);
			if($diff < 60)
				return $diff . " minute" . $plural($diff) . " ago";
			$diff = round($diff / 60);
			if($diff < 24)
				return $diff . " hour" . $plural($diff) . " ago";
			$diff = round($diff / 24);
			if($diff < 7)
				return $diff . " day" . $plural($diff) . " ago";
			$diff = round($diff / 7);
			if($diff < 4)
				return $diff . " week" . $plural($diff) . " ago";
			return "on " . date("F j, Y", strtotime($date));
		} else {
			if($diff > -60)
				return "in about " . -$diff . " second" . $plural($diff,true);
			$diff = round($diff / 60);
			if ($diff > -60)
				return "in about " . -$diff . " minute" . $plural($diff,true);
			$diff = round($diff / 60);
			if ($diff > -24)
				return "in about " . -$diff . " hour" . $plural($diff,true);
			$diff = round($diff / 24);
			if ($diff > -7)
				return "in about " . -$diff . " day" . $plural($diff,true);
			$diff = round($diff / 7);
			if ($diff > -4)
				return "in about " . -$diff . " week" . $plural($diff,true);
			return "on " . date("F j, Y", strtotime($date));
		}
	}
}

