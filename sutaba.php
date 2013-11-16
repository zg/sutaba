<?php
//sutaba
//re-written by zgold [2011-08-04]

//**************************** CONFIG ****************************
date_default_timezone_set('America/New_York');
$cfg = array (
    'board1' => array (
        'syspath'          => '/srv/http/org/sutaba/'
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
    public $connect; //mysql connection

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
        if(!function_exists('mysqli_connect'))
            die("You must have mysqli installed in order to use sutaba.");
        if(!$this->common->has_permission($board))
            die("You don't have permission to view this board.");
        //add trailing slashes if they're missing on any cfg vars with "path"
        foreach($cfg[$board] as $index => $value)
            if(strpos($index,'path') && $index !== 'webpath')
                if($cfg[$board][$index][(strlen($cfg[$board][$index]) - 1)] !== '/')
                    $cfg[$board][$index] .= '/';
        $this->connect = new mysqli($cfg[$board]['mysql']['hostname'],$cfg[$board]['mysql']['username'],$cfg[$board]['mysql']['password'],$cfg[$board]['mysql']['database']);
        if(mysqli_connect_errno())
            die("Connection failed. ".mysqli_connect_error());
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
        if($query = $this->connect->query($sql)){
            if(0 < $query->num_rows){
                while($result = $query->fetch_assoc())
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
        if($query = $this->connect->query($sql)){
            $num_rows = $query->num_rows;
            if(0 < $num_rows)
            {
                while($result = $query->fetch_assoc())
                {
                    if($has_replies === true)
                        $return_val[] = $result;
                    else
                        $return_val = $result;
                }
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
        if($query = $this->connect->query($sql)){
            $result = $query->fetch_assoc();
            return $result['cout'];
        }
        return 0;
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
        if($query = $this->connect->query($sql))
            if(0 < $query->num_rows)
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
        if($query = $this->connect->query($sql))
            if(0 < $query->num_rows)
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
        if($query = $this->connect->query($sql))
            if(0 < $query->num_rows)
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
        if($query = $this->connect->query($sql))
            if($query->num_rows == 0)
                return $return_val;
        if($ip !== false)
            $return_val = $query->fetch_assoc();
        else
            while($result = $query->fetch_assoc())
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
        if($query = $this->connect->query($sql)){
            if($query->num_rows == 0)
                return $return_val;
            while($result = $query->fetch_assoc()){
                if(isset($return_val[$result['post_id']])){
                    $return_val[$result['post_id']]['ip'][] = $result['ip'];
                } else {
                    $result['ip'] = array($result['ip']);
                    $return_val[$result['post_id']] = $result;
                }
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
        $return_val = $this->connect->query($sql);
        return $return_val;
    }

    public function get_wordfilters($sort=false)
    {
        $return_val = array();
        global $cfg,$board;
        if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
            return $return_val;
        $sql = "SELECT wordfilter.* FROM wordfilter WHERE wordfilter.board = '$board'".($sort !== false ? " ORDER BY wordfilter.".$sort : "");
        if($query = $this->connect->query($sql)){
            if($query->num_rows == 0)
                return $return_val;
            while($result = $query->fetch_assoc())
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
        $return_val = $this->connect->query($sql);
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
        $return_val = $this->connect->query($sql);
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
        if($query = $this->connect->query($sql)){
            $num_rows = $query->num_rows;
            if(0 < $num_rows){
                while($result = $query->fetch_assoc()){
                    if(0 < $result['expires']){
                        if($result['expires'] < time()){
                            $sql = "DELETE FROM bans WHERE board = '$board' AND ip = '$ip'";
                            $return_val = $this->connect->query($sql);
                        }
                        else
                            $return_val = true;
                    }
                    else
                        $return_val = true;
                }
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
        $return_val = $this->connect->query($sql);
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
        $return_val = $this->connect->query($sql);
        return $return_val;
    }

    public function unban_user($ip)
    {
        $return_val = false;
        global $cfg,$board;
        if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
            return $return_val;
        $sql = "DELETE FROM bans WHERE board = '$board' AND ip = '$ip'";
        $return_val = $this->connect->query($sql);
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
                if($locked_query = $this->connect->query($locked_sql)){
                    if(0 < $locked_query->num_rows){
                        $locked_result = $locked_query->fetch_assoc();
                        $attributes[] = "locked = '".($locked_result['locked'] == 1 ? "0" : "1")."'";
                    }
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
                if($pinned_query = $this->connect->query($pinned_sql)){
                    if(0 < $pinned_query->num_rows){
                        $pinned_result = $pinned_query->fetch_assoc();
                        $attributes[] = "pinned = '".($pinned_result['pinned'] == 1 ? "0" : "1")."'";
                    }
                }
            }
        }
        if(count($attributes) == 0)
            return $return_val;
        $sql = "UPDATE posts SET ".implode(",",$attributes)." WHERE board = '$board' AND id = '$post_id' AND parent_id = '0'";
        $return_val = $this->connect->query($sql);
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
                $this->connect->query("UPDATE posts SET pinned = '$pinned', locked = '$locked' WHERE id = '$parent_id'");
                $pinned = 0;
                $locked = 0;
            }
        }

        $sql = "INSERT INTO posts (board,parent_id,time,ip,name,email,subject,comment,file,password,pinned,locked) VALUES ('$board','$parent_id','$time','$ip','$name_to_post','$email','$subject','$comment','$location','$password','$pinned','$locked');";
        if($query = $this->connect->query($sql)){
            $post_id = $this->connect->insert_id;
        }

        if($file !== '')
            $this->connect->query("INSERT INTO images (filename,type,size,width,height) VALUES ('$location','$type','$size','$width','$height');");

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
        if($query = $this->connect->query($sql)){
            if(0 < $query->num_rows){
                $parent_id = 0;
                while($result = $query->fetch_assoc()){
                    $parent_id = $result['parent_id'];
                    $delete_post_sql = "DELETE FROM posts WHERE board = '$board' AND id = '$post_id'";
                    $this->connect->query($delete_post_sql);
                    if(0 < strlen($result['file'])){
                        $delete_images_sql = "DELETE FROM images WHERE filename = '{$result['file']}'";
                        $this->connect->query($delete_images_sql);
                        unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$result['file']);
                        unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$result['file']);
                    }
                    if($parent_id == 0){
                        $delete_replies_sql = "DELETE FROM posts WHERE board = '$board' AND parent_id = '$post_id'";
                        $this->connect->query($delete_replies_sql);
                        $select_replies_sql = "SELECT id,file FROM posts WHERE board = '$board' AND parent_id = '$post_id'";
                        if($select_replies_query = $this->connect->query($select_replies_sql)){
                            if(0 < $select_replies_query->num_rows){
                                while($select_replies_result = $select_replies_query->fetch_assoc()){
                                    $delete_images_sql = "DELETE FROM images WHERE filename = '{$delete_posts_result['file']}'";
                                    $this->connect->query($delete_images_sql);
                                    unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$select_replies_result['file']);
                                    unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$select_replies_result['file']);
                                }
                            }
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
        if($query = $this->connect->query($sql)){
            if(0 < $query->num_rows){
                $parent_id = 0;
                while($result = $query->fetch_assoc())
                {
                    $parent_id = $result['parent_id'];
                    $delete_images_sql = "DELETE FROM images WHERE filename = '{$result['file']}'";
                    $this->connect->query($delete_images_sql);
                    unlink($cfg[$board]['syspath'].$cfg[$board]['imgpath'].$result['file']);
                    unlink($cfg[$board]['syspath'].$cfg[$board]['thumbpath'].$result['file']);
                    $update_posts_sql = "UPDATE posts SET file = '' WHERE id = '$post_id'";
                    $this->connect->query($update_posts_sql);
                }
                if($parent_id !== 0)
                    return $common->redirect("action=view_thread&thread_id=$parent_id#$post_id");
            }
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
        if($query = $this->connect->query($sql)){
            if($query->num_rows == 0){
                $insert_report_sql = "INSERT INTO reports (board,post_id,time,ip) VALUES ('$board','$post_id','$time','$ip')";
                $return_val = $this->connect->query($insert_report_sql);
            }
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
        if($query = $this->connect->query($sql)){
            $return_val = $query->num_rows;
        }
        return $return_val;
    }

    public function cleanup_reports()
    {
        $return_val = false;
        global $cfg,$board;
        if(!isset($cfg) || !isset($board) || !isset($cfg[$board]))
            return $return_val;
        $sql = "SELECT DISTINCT post_id,id FROM reports WHERE board = '$board'";
        if($query = $this->connect->query($sql)){
            while($result = $query->fetch_assoc()){
                if(!$this->is_post($result['post_id'])){
                    $sql = "DELETE FROM reports WHERE board = '$board' AND id = '{$result['id']}'";
                       $return_val = $this->connect->query($sql);
                }
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
        $name      = $this->connect->real_escape_string($image['name']);
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
                $img = imagecreatefromjpeg($tmp_name);
                if(!$img)
                    return 'type';
                else {
                    $width = imagesx($img);
                    $height = imagesy($img);
                }
            break;
            case 'image/gif':
                $img = imagecreatefromgif($tmp_name);
                if(!$img)
                    return 'type';
                else {
                    $width = imagesx($img);
                    $height = imagesy($img);
                }
            break;
            case 'image/png':
                $img = imagecreatefrompng($tmp_name);
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
        $return_val = $this->connect->query($sql);
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
        if($query = $this->connect->query($sql)){
            if(0 < $query->num_rows){
                while($result = $query->fetch_assoc())
                {
                    if($result['time'] < $time)
                    {
                        $sql = "DELETE FROM spam WHERE board = '$board' AND ip = '$ip'";
                        $return_val = $this->connect->query($sql);
                    }
                }
            }
            else
            {
                $sql = "INSERT INTO spam (board,ip,time) VALUES ('$board','$ip','$time');";
                $return_val = $this->connect->query($sql);
            }
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

class HTML {
    public function build_form($thread_data=false)
    {
        global $cfg,$board,$_CLEAN,$common,$Sutaba;
        $name = (isset($_SESSION[$board]['name']) ? $_SESSION[$board]['name'] : $cfg[$board]['guest_name']);
        $email = (isset($_SESSION[$board]['email']) ? $_SESSION[$board]['email'] : '');
        $form = "<form method=post name=form enctype=multipart/form-data>";
            $form .= "<table>";
                $form .= "<tr><td>Name</td><td><input type=text name=name size=32 value=\"{$name}\"></td></tr>";
                $form .= "<tr><td>E-mail</td><td><input type=text name=email size=32 maxlength=100 value=\"$email\"></td></tr>";
                $form .= "<tr><td>Subject</td><td><input type=text name=subject size=32 maxlength=100><input type=submit value=Submit>";
                $form .= "<input type=hidden name=action value=post />";
                if($thread_data !== false && isset($thread_data['id']))
                    $form .= "<input type=hidden name=parent_id value={$thread_data['id']} />";
                $form .= "</td></tr>";
                $form .= "<tr><td>Message</td><td><textarea name=comment cols=58 rows=5></textarea></td></tr>";
                $form .= "<tr><td>File</td><td><input type=file name=file size=75></td></tr>";
                $form .= "<tr><td>Password</td><td><input type=password name=password value={$_SESSION[$board]['password']} /> <small>(for post and file deletion)</small></td></tr>";
                if($common->is_staff())
                {
                    $form .= "<tr>";
                    $form .= "<td>Moderation</td>";
                    $form .= "<td>";
                    $form .= "<input type=checkbox name=locked".($thread_data['locked'] == 1 ? " checked" : "")." value=1 /> Lock Thread";
                    if($common->is_admin())
                        $form .= "<br /><input type=checkbox name=pinned".($thread_data['pinned'] == 1 ? " checked" : "")." value=1 /> Pin Thread";
                    $form .= "</td>";
                    $form .= "</tr>";
                }
            $form .= "</table>";
        $form .= "</form>";
        return $form;
    }

    public function build_menu()
    {
        global $common,$cfg,$board;
        $menu_items = array();
        foreach($cfg as $board_name => $board_data)
        {
            $menu_items[] = '[ <a href=?board='.$board_name.'>'.$board_data['title'].'</a> ]';
        }
        if($common->is_staff())
            $menu_items[] = '[ <a href=?action=manage>manage</a> ]';
        $menu_items[] = '[ <a href=/releases/>releases</a> ]';
        return (0 < count($menu_items) ? implode(' ',$menu_items) : '');
    }

    public function build_post(array $data)
    {
        global $cfg,$board,$Sutaba,$common;
        $data['comment'] = str_replace(array("&lt;pre&gt;","&lt;/pre&gt;"),array('<pre>','</pre>'),$data['comment']);
        /* quoting */
        if(preg_match_all('/&gt;&gt;(\d+)/',$data['comment'],$quoted_text_match))
        {
            foreach($quoted_text_match[1] as $key => $quoted_post_id)
            {
                if($Sutaba->is_thread($quoted_post_id))
                {
                    $data['comment'] = str_replace($quoted_text_match[0][$key],'<a href='.$cfg[$board]['webpath'].'?action=view_thread&thread_id='.$quoted_post_id.'>>>'.$quoted_post_id.'</a>',$data['comment']);
                }
                elseif($Sutaba->is_post($quoted_post_id))
                {
                    $post_data = $Sutaba->get_post($quoted_post_id);
                    $data['comment'] = str_replace($quoted_text_match[0][$key],'<a href='.$cfg[$board]['webpath'].'?action=view_thread&thread_id='.$post_data['parent_id'].'#'.$quoted_post_id.'>>>'.$quoted_post_id.'</a>',$data['comment']);
                }
                else
                {
                    $data['comment'] = str_replace($quoted_text_match[0][$key],'<b id=q>>>'.$quoted_post_id.'</b>',$data['comment']);
                }
            }
        }

        if(preg_match_all('/^&gt;(.+)/',$data['comment'],$quoted_text_match))
            foreach($quoted_text_match[1] as $key => $quoted_post_id)
                $data['comment'] = str_replace($quoted_text_match[0][$key],'<b id=q>>'.$quoted_post_id.'</b>',$data['comment']);

        //parse URLs
        //$data['comment'] = preg_replace('@(https?://([-\w\.]+)+(:\d+)?(/([\w/_\.]*(\?\S+)?)?)?)@', '<a href="$1">$1</a>',$data['comment']);
        $sql = 'SELECT word,replacement FROM wordfilter WHERE board = "'.$board.'" ORDER BY id ASC';
        if($query = $Sutaba->connect->query($sql))
            if(0 < $query->num_rows)
                while($result = $query->fetch_assoc())
                    $data['comment'] = str_replace($result['word'],$result['replacement'],$data['comment']);

        $data['name'] = ($data['name'] == "Anonymous" ? '<b id=c>'.$data['name'].'</b>' : $data['name']);

        $return = "<a name={$data['id']}></a>";
        $return .= "<table width=100% border=1 cellpadding=5 class=tbl>";
        $return .= "<tr>";
        $return .= "<td class=td2>";

        $link = $cfg[$board]['webpath'].'?action=view_thread&thread_id='.($data['parent_id'] == 0 ? $data['id'] : $data['parent_id']).'#'.$data['id'];

        $return .= "<input type=checkbox name=posts[] value={$data['id']}>";
        $return .= '<a href='.$link.'>No.</a> ';
        $return .= '<a href=javascript:Main.quote(\''.$data['id'].'\')>';
            $return .= $data['id'];
        $return .= '</a> ';

        $return .= '<b id=a>'.$data['subject'].'</b>';

        if(strpos($data['name'],'#'))
            list($name,$tripcode) = explode('#',$data['name'],2);
        else
            $name = $data['name'];

        if(isset($tripcode) && in_array($tripcode,$cfg[$board]['access']['admin']))
            $return .= ' <b id=u1>'.($data['email'] !== '' ? '<a href=mailto:'.$data['email'].'>' : '').$name.($data['email'] !== '' ? '</a>' : '').'</b><span id=u0>'.(isset($tripcode) ? ' !'.$tripcode : '').'</span> <b id=u2>## Admin ##</b> ';
        else
            $return .= ' <b id=u0>'.($data['email'] !== '' ? '<a href=mailto:'.$data['email'].'>' : '').$name.($data['email'] !== '' ? '</a>' : '').'</b><span id=u0>'.(isset($tripcode) ? ' !'.$tripcode : '').'</span> ';
        if(isset($tripcode) && in_array($tripcode,$cfg[$board]['access']['mod']))
            $return .= '<b id=u1>## Mod ##</b> ';

        $return .= date($cfg[$board]['datetime_format'],$data['time']);

        if($data['locked'] == 1 && $data['parent_id'] == 0)
            $return .= ' <img src=lock.gif title=Locked width=10 height=10 alt=L>';
        if($data['pinned'] == 1 && $data['parent_id'] == 0)
            $return .= ' <img src=pin.gif title=Pinned width=10 height=10 alt=P>';

        if($cfg[$board]['img']['enabled'] === true){
            if(0 < strlen($data['file'])){
                $sql = 'SELECT * FROM images WHERE filename = "'.$data['file'].'" LIMIT 1';
                if($query = $Sutaba->connect->query($sql)){
                    if(0 < $query->num_rows){
                        while($result = $query->fetch_assoc()){
                            $return .= '</td></tr><tr><td class=td1>File: <a href='.$cfg[$board]['imgpath'].$data['file'].'>';
                                $return .= $data['file'];
                            $return .= '</a> - ';
                                $return .= $common->format_size($result['size']).', ';
                                $return .= $result['width'].'x'.$result['height'];
                        }
                    }
                }
            }
        }
        $return .= '</td></tr>';
        $return .= '<tr><td class="td1'.(isset($data['report_count']) ? (0 < $data['report_count'] ? (10 < $data['report_count'] ? ' r9' : ' r'.$data['report_count']) : '') : '').'" valign=top>';
        if($cfg[$board]['img']['enabled'] === true)
            if(0 < strlen($data['file'])){
                $return .= '<a href='.$cfg[$board]['imgpath'].$data['file'].'>';
                    $return .= '<img src='.$cfg[$board]['thumbpath'].$data['file'].' style=float:left;margin-right:5px border=0 alt=>';
                $return .= '</a>';
            }

        /* short br tags to save bandwidth, allow ppl to use <pre> for code */
        $return .= stripslashes(str_replace(array("\n","\r","<br />"),array("","","<br>"),nl2br($data['comment'])));
        $sql = "SELECT id FROM bans WHERE board = '$board' AND post_id = '{$data['id']}'";
        if($query = $Sutaba->connect->query($sql))
            if(0 < $query->num_rows)
                $return .= '<br><br><b><font color=red>(USER WAS BANNED FOR THIS POST.)</font></b>';
        $return .= '</td></tr></table>';
        return $return;
    }
}

class paginate {
    public $thread_count, $threads_per_page;
    public $page_range = 5;
    public $current_page = 1;

    public function __construct()
    {
        global $cfg,$board;
        $this->threads_per_page = $cfg[$board]['threads_per_page'];
    }

    public function paginate()
    {
        $return_val = '';
        if($this->thread_count == 0)
            return $return_val;
        for($page = 1; $page < ceil($this->thread_count / $this->threads_per_page); $page++)
            $return_val .= ($this->current_page == $page ? ' [ <b>'.$page.'</b> ] ' : ' [ <a href=?page='.$page.'>'.$page.'</a> ] ');
        return $return_val;
    }
}

$common = new common();
$_CLEAN['GET'] = $common->clean($_GET);
$_CLEAN['POST'] = $common->clean($_POST);

//**************************** MODEL ****************************
// determine whether the client can view any boards
$board = 0; // default board
$board_changed = false; // the board was changed
foreach($cfg as $board_id => $board_data) //get the first valid board they can visit
{
    if(!$common->has_permission($board_id))
        continue;
    $board_changed = true;
    $board = $board_id;
    break;
}
if($board_changed === false) // they can't view any boards; exit immediately (IP-restricted board or tripcode-restricted board)
    die("You don't have permission to view any boards.");

if(isset($_CLEAN['GET']['board']) && isset($cfg[$_CLEAN['GET']['board']]) && $common->has_permission($_CLEAN['GET']['board'])) // client is requesting to view a different board
    $_SESSION['board'] = $_CLEAN['GET']['board'];
elseif(isset($_CLEAN['GET']['board']) && !$common->has_permission($_CLEAN['GET']['board']))
    $common->redirect('error=board_not_found');
elseif(isset($_CLEAN['GET']['board']) && !isset($cfg[$_CLEAN['GET']['board']]))
    $common->redirect('error=board_not_found');

if(!isset($_SESSION['board']) || !isset($cfg[$_SESSION['board']]) || !$common->has_permission($_SESSION['board']))
    $_SESSION['board'] = $board;

$board = $_SESSION['board'];

$common->errors['subject_length'] = 'Your subject must be at least '.$cfg[$board]['subject_min'].' characters long (not including whitespace).';
$common->errors['comment_length'] = 'Your comment must be at least '.$cfg[$board]['comment_min'].' characters long (not including whitespace).';
$common->errors['subject_too_long'] = 'Your subject must be less than '.$cfg[$board]['subject_max'].' characters long.';
$common->errors['comment_too_long'] = 'Your comment must be less than '.$cfg[$board]['comment_max'].' characters long.';
$Sutaba = new Sutaba($board);
$Sutaba->cleanup_bans();
$Sutaba->cleanup_reports();
$Sutaba->cleanup_spam();
$HTML = new HTML($Sutaba);
$paginate = new paginate();

$hr = '<hr noshade color=black size=1>';

$_CLEAN['POST']['action'] = (isset($_CLEAN['POST']['action']) ? $_CLEAN['POST']['action'] : '');
$_CLEAN['GET']['action'] = (isset($_CLEAN['GET']['action']) ? $_CLEAN['GET']['action'] : '');

if($Sutaba->is_banned())
{
    $ban_data = $Sutaba->get_bans($_SERVER['REMOTE_ADDR']);
    echo '<h1><font color="red">BANNED</font></h1>';
    echo '<p>Your IP ('.$ban_data['ip'].') was banned on '.date('F j, Y \a\t h:i:s A',$ban_data['time']).' for the reason:</p>';
    echo '<p><b>'.$ban_data['reason'].'</b></p>';
    echo '<p>Your ban will '.($ban_data['expires'] == 0 ? 'never expire.' : 'expire on '.date('F j, Y \a\t h:i:s A',$ban_data['expires'])).'</p>';
    exit;
}

if(isset($_CLEAN['POST']['action']))
{
    switch($_CLEAN['POST']['action'])
    {
        case 'post':
            $Sutaba->create_post($_CLEAN['POST']);
        break;
        case 'Ban':
            var_dump($_CLEAN['POST']);
            $post_data = array();
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']))
            {
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $post_data[$post_id] = $Sutaba->get_post($post_id);
            }
            elseif(isset($_CLEAN['POST']['users']) && is_array($_CLEAN['POST']['users']) && isset($_CLEAN['POST']['expires']) && isset($_CLEAN['POST']['reason']))
            {
                foreach($_CLEAN['POST']['users'] as $post_id => $ip)
                    if(is_numeric($post_id))
                        $Sutaba->ban_user($ip,$post_id,$_CLEAN['POST']['expires'],$_CLEAN['POST']['reason']);
                $common->redirect("",true);
            }
            if(count($post_data) > 0)
            {
                $ips = array();
                foreach($post_data as $post_id => $data)
                    if(!in_array($data['ip'],$ips))
                        $ips[$post_id] = $data['ip'];
                echo '<h3>Are you sure you want to ban the creators of these posts? '.implode(',',$_CLEAN['POST']['posts']).'</h3>';
                echo '<form method=post>';
                    echo '<table>';
                        echo '<tr><td>Reason</td><td><input type=text name=reason size=50></td></tr>';
                        echo '<tr>';
                            echo '<td>Expires</td>';
                            echo '<td>';
                                echo '<select name=expires>';
                                    echo '<option value='.(60 * 60).'>An hour</option>';
                                    echo '<option value='.(60 * 60 * 6).'>Six hours</option>';
                                    echo '<option value='.(60 * 60 * 24).'>A day</option>';
                                    echo '<option value='.(60 * 60 * 24 * 2).'>Two days</option>';
                                    echo '<option value='.(60 * 60 * 24 * 5).'>Five days</option>';
                                    echo '<option value='.(60 * 60 * 24 * 7).'>A week</option>';
                                    echo '<option value='.(60 * 60 * 24 * 7 * 2).'>Two weeks</option>';
                                    echo '<option value='.(60 * 60 * 24 * 7 * 4).'>One month</option>';
                                    echo '<option value='.(60 * 60 * 24 * 7 * 4 * 6).'>Six months</option>';
                                    echo '<option value='.(60 * 60 * 24 * 365).'>One year</option>';
                                    echo '<option value=0>Never expires</option>';
                                echo '</select>';
                            echo '</td>';
                        echo '</tr>';
                        echo '<tr><td colspan=2><font color=red>All bans are logged.</font></b></td></tr>';
                        echo '<tr><td colspan=2>';
                        foreach($ips as $post_id => $ip)
                            echo '<input type=hidden name=users['.$post_id.'] value='.$ip.'>';
                        echo '<input type=hidden name=action value=Ban><input type=submit value=Submit>';
                        echo '</td></tr>';
                    echo '</table>';
                echo '</form>';
                echo $hr;
            }
            else
                $common->redirect("");
        break;
        case 'Report':
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']))
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $Sutaba->report_post($post_id);
        break;
        case 'Delete Post':
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']) && (isset($_CLEAN['POST']['password']) || $common->is_staff()))
            {
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $Sutaba->delete_post($post_id,(!isset($_CLEAN['POST']['password']) && $common->is_staff() ? false : $_CLEAN['POST']['password']));
            }
            elseif(!isset($_CLEAN['POST']['password']))
                $common->redirect('error=password_missing',true);
        break;
        case 'Delete Image':
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']) && (isset($_CLEAN['POST']['password']) || $common->is_staff()))
            {
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $Sutaba->delete_image($post_id,(!isset($_CLEAN['POST']['password']) && $common->is_staff() ? false : $_CLEAN['POST']['password']));
            }
            elseif(!isset($_CLEAN['POST']['password']))
                $common->redirect('error=password_missing',true);
        break;
        case 'Toggle Pin':
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']))
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $Sutaba->toggle_thread_attributes($post_id,false,true);
        break;
        case 'Toggle Lock':
            if(isset($_CLEAN['POST']['posts']) && is_array($_CLEAN['POST']['posts']))
                foreach($_CLEAN['POST']['posts'] as $post_id)
                    if(is_numeric($post_id))
                        $Sutaba->toggle_thread_attributes($post_id,true);
        break;
    }
}

if(isset($_CLEAN['GET']['action']))
{
    switch($_CLEAN['GET']['action'])
    {
        case 'manage':
            if(!$common->is_staff())
                $common->redirect("");
            echo "<b class=t><a href={$cfg[$board]['webpath']}>{$cfg[$board]['title']}</a></b>".(strlen($cfg[$board]['desc']) > 0 ? " - <small>{$cfg[$board]['desc']}</small>" : "");
            echo $hr;
            echo "<b class=ls>{$HTML->build_menu()}</b>";
            echo $hr;
            if(isset($_CLEAN['GET']['subaction']))
            {
                switch($_CLEAN['GET']['subaction'])
                {
                    case 'Unban User':
                        if(isset($_CLEAN['POST']['users']) && is_array($_CLEAN['POST']['users']))
                            foreach($_CLEAN['POST']['users'] as $ip)
                                $Sutaba->unban_user($ip);
                        $sort = '';
                        $sortAD = '';
                        if(isset($_CLEAN['GET']['sort']) && isset($_CLEAN['GET']['sortAD']))
                        {
                            $sortAD = ($_CLEAN['GET']['sortAD'] == "a" ? "ASC" : "DESC");
                            switch($_CLEAN['GET']['sort'])
                            {
                                case 'time':
                                case 'ip':
                                case 'expires':
                                    $sort = $_CLEAN['GET']['sort'];
                                break;
                            }
                        }
                        $bans = $Sutaba->get_bans(false,(1 < strlen($sort.' '.$sortAD) ? $sort.' '.$sortAD : false));
                        echo "<form method=post>";
                            echo "<p>";
                                echo "<table cellpadding=5 border=1 class=tbl>";
                                echo "<tr class=td2><th>&nbsp;</th><th>Board</th><th>IP</th><th>Banned on</th><th>Reason</th><th>Expires</th></tr>";
                                $count = 0;
                                if(count($bans) > 0)
                                {
                                    foreach($bans as $ban_id => $ban)
                                    {
                                        echo "<tr>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2")."><input type=checkbox name=users[] value=".$ban['ip']."></td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$ban['board']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$ban['ip']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".date('F j, Y h:i:s A',$ban['time'])."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$ban['reason']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$common->relative_time(date('F j, Y h:i:s A',$ban['expires']))."</td>";
                                        echo "</tr>";
                                        $count++;
                                    }
                                }
                                else
                                {
                                    echo "<tr><td class=td1 colspan=6>No banned users found.</td></tr>";
                                }
                                echo "</table>";
                            echo "</p>";
                            echo "<input type=submit name=action value=Unban>";
                        echo "</form>";
                    break;
                    case 'Reports':
                        if(isset($_CLEAN['POST']['reports']) && is_array($_CLEAN['POST']['reports']))
                            foreach($_CLEAN['POST']['reports'] as $post_id)
                                $Sutaba->delete_reports($post_id);
                        $sort = '';
                        $sortAD = '';
                        if(isset($_CLEAN['GET']['sort']) && isset($_CLEAN['GET']['sortAD']))
                        {
                            $sortAD = ($_CLEAN['GET']['sortAD'] == "a" ? "ASC" : "DESC");
                            switch($_CLEAN['GET']['sort'])
                            {
                                case 'post_id':
                                case 'time':
                                case 'ip':
                                    $sort = $_CLEAN['GET']['sort'];
                                break;
                            }
                        }
                        $reports = $Sutaba->get_reports((1 < strlen($sort.' '.$sortAD) ? $sort.' '.$sortAD : false));
                        echo "<form method=post>";
                            echo "<p>";
                                echo "<table cellpadding=5 border=1 class=tbl>";
                                echo "<tr class=td2><th>&nbsp;</th><th>Board</th><th>Post ID</th><th>Time</th><th>Reporter IPs</th></tr>";
                                $count = 0;
                                if(count($reports) > 0)
                                {
                                    foreach($reports as $post_id => $report_data)
                                    {
                                        if($report_data['parent_id'] == 0)
                                            $link = "?action=view_thread&thread_id=".$report_data['post_id'];
                                        else
                                            $link = "?action=view_thread&thread_id=".$report_data['parent_id']."#".$report_data['post_id'];
                                        echo "<tr>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2")."><input type=checkbox name=reports[] value=".$report_data['post_id']."></td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$report_data['board']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$report_data['post_id']." [<a href=$link target=_blank>Link</a>]</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".date('F j, Y h:i:s A',$report_data['time'])."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".implode(', ',$report_data['ip'])."</td>";
                                        echo "</tr>";
                                        $count++;
                                    }
                                }
                                else
                                {
                                    echo "<tr><td class=td1 colspan=5>No reports found.</td></tr>";
                                }
                                echo "</table>";
                            echo "</p>";
                            echo "<input type=submit name=action value='Clear reports'>";
                        echo "</form>";
                    break;
                    case 'Wordfilter':
                        if(isset($_CLEAN['POST']['wordfilters']) && is_array($_CLEAN['POST']['wordfilters']))
                            foreach($_CLEAN['POST']['wordfilters'] as $wordfilter_id)
                                $Sutaba->delete_wordfilter($wordfilter_id);
                        elseif(isset($_CLEAN['POST']['word']) && isset($_CLEAN['POST']['replacement']))
                            $Sutaba->add_wordfilter($_CLEAN['POST']['word'],$_CLEAN['POST']['replacement']);
                        $sort = '';
                        $sortAD = '';
                        if(isset($_CLEAN['GET']['sort']) && isset($_CLEAN['GET']['sortAD']))
                        {
                            $sortAD = ($_CLEAN['GET']['sortAD'] == "a" ? "ASC" : "DESC");
                            switch($_CLEAN['GET']['sort'])
                            {
                                case 'board':
                                case 'word':
                                case 'replacement':
                                    $sort = $_CLEAN['GET']['sort'];
                                break;
                            }
                        }
                        $wordfilters = $Sutaba->get_wordfilters((1 < strlen($sort.' '.$sortAD) ? $sort.' '.$sortAD : false));
                        echo "<form method=post>";
                            echo "<p>";
                                echo "<table cellpadding=5>";
                                echo "<tr><td>Board</td><td>$board</td></tr>";
                                echo "<tr><td>Word</td><td><input type=text name=word /></td></tr>";
                                echo "<tr><td>Replacement</td><td><input type=text name=replacement /></td></tr>";
                                echo "<tr><td colspan=2><input type=submit value='Add Wordfilter' /></td></tr>";
                                echo "</table>";
                            echo "</p>";
                            echo "<p>";
                                echo "<table cellpadding=5 border=1 class=tbl>";
                                echo "<tr class=td2><th>&nbsp;</th><th>Board</th><th>Word</th><th>Replacement</th></tr>";
                                $count = 0;
                                if(count($wordfilters) > 0)
                                {
                                    foreach($wordfilters as $wordfilter_id => $wordfilter_data)
                                    {
                                        echo "<tr>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2")."><input type=checkbox name=wordfilters[] value=".$wordfilter_data['id']."></td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$wordfilter_data['board']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$wordfilter_data['word']."</td>";
                                            echo "<td class=".($count % 2 == 0 ? "td1" : "td2").">".$wordfilter_data['replacement']."</td>";
                                        echo "</tr>";
                                        $count++;
                                    }
                                }
                                else
                                {
                                    echo "<tr><td class=td1 colspan=5>No wordfilters found.</td></tr>";
                                }
                                echo "</table>";
                            echo "</p>";
                            echo "<input type=submit name=action value='Delete wordfilters'>";
                        echo "</form>";
                    break;
                }
            }
            else
            {
                echo "<form method=get>";
                    echo "<input type=hidden name=action value=manage>";
                    echo "<input type=submit name=subaction value='Unban User'>";
                    echo "<input type=submit name=subaction value='Reports'>";
                    echo "<input type=submit name=subaction value='Wordfilter'>";
                echo "</form>";
            }
        break;
        case 'view_thread':
            if(isset($_CLEAN['GET']['thread_id']) && is_numeric($_CLEAN['GET']['thread_id']))
            {
                echo "<b class=t><a href={$cfg[$board]['webpath']}>{$cfg[$board]['title']}</a></b>".(strlen($cfg[$board]['desc']) > 0 ? " - <small>{$cfg[$board]['desc']}</small>" : "");
                echo $hr;
                echo "<b class=ls>{$HTML->build_menu()}</b>";
                echo $hr;
                if(isset($_CLEAN['GET']['error']) && isset($common->errors[$_CLEAN['GET']['error']]))
                {
                    echo "<b><font color=red>{$common->errors[$_CLEAN['GET']['error']]}</font></b>";
                    echo $hr;
                }
                if($Sutaba->is_thread($_CLEAN['GET']['thread_id']))
                {
                    $thread_data = $Sutaba->get_thread($_CLEAN['GET']['thread_id']);
                    if(($thread_data['locked'] == 0) || ($thread_data['locked'] == 1 && $common->is_staff()))
                    {
                        echo $HTML->build_form($thread_data);
                        echo $hr;
                    }
                    echo "<form method=post name=moderate enctype=multipart/form-data>";
                        echo '<div id=msg>';
                            echo $HTML->build_post($thread_data);
                            foreach($thread_data['replies'] as $post)
                            {
                                $post['report_count'] = $Sutaba->get_report_count($post['id']);
                                echo '<p>'.$HTML->build_post($post).'</p>';
                            }
                        echo '</div>';
                        echo $hr;
                        echo "<div class='r b'>";
                            echo "<input type=password name=password onfocus='this.type=\"text\"' onblur='this.type=\"password\"' value={$_SESSION[$board]['password']}>";
                            echo "<input type=submit name=action value='Delete Post'>";
                            echo "<input type=submit name=action value='Delete Image'>";
                            if($common->is_staff())
                                echo "<input type=submit name=action value=Ban>";
                            echo "<input type=submit name=action value=Report>";
                        echo "</div>";
                    echo "</form>";
                }
                else
                {
                    echo "<b><font color=red>Sorry, but the thread you requested doesn't exist.</font></b>";
                    echo $hr;
                }
            }
            else
            {
                $common->redirect('error=thread_not_found');
            }
        break;
        default:
            $page = (isset($_CLEAN['GET']['page']) && is_numeric($_CLEAN['GET']['page']) && 0 < $_CLEAN['GET']['page'] ? $_CLEAN['GET']['page'] : 1);
            $posts = $Sutaba->get_thread(false,$page);
            $paginate->thread_count = $Sutaba->get_thread_count();
            $paginate->current_page = $page;
            echo "<b class=t>";
            if($_SERVER['REQUEST_URI'] !== $cfg[$board]['webpath'])
                echo "<a href={$cfg[$board]['webpath']}>{$cfg[$board]['title']}</a>";
            else
                echo $cfg[$board]['title'];
            echo "</b>".(strlen($cfg[$board]['desc']) > 0 ? " - <small>{$cfg[$board]['desc']}</small>" : "");
            echo $hr;
            echo "<b class=ls>{$HTML->build_menu()}</b>";
            echo $hr;
            if(isset($_CLEAN['GET']['error']) && isset($common->errors[$_CLEAN['GET']['error']]))
            {
                echo "<b><font color=red>{$common->errors[$_CLEAN['GET']['error']]}</font></b>";
                echo $hr;
            }
            echo $HTML->build_form();
            echo $hr;
            //check for messages
            echo "<form method=post name=moderate enctype=multipart/form-data>";
                echo "<div id=msg>";
                    echo "<table width=100% border=1 cellpadding=5 class=tbl>";
                        echo "<tr><td class=td2><b><div style=float:right>Replies</div>Title</b></td></tr>";
                        if(0 < count($posts))
                        {
                            $count = 0;
                            foreach($posts as $post)
                            {
                                $replies = count($post['replies']);
                                echo "<tr>";
                                    echo "<td class=td".($count % 2 ? 2 : 1).">";
                                    echo "<div style=float:right>{$replies}</div>";
                                    echo "<input type=checkbox name=posts[] value={$post['id']}>";
                                    echo "<a href=?action=view_thread&thread_id={$post['id']}>{$post['subject']}</a>";
                                    if($post['file'] !== '')
                                        echo " <img src=image.gif title=Image width=10 height=10 alt=I>";
                                    if($post['locked'] == 1)
                                        echo " <img src=lock.gif title=Locked width=10 height=10 alt=L>";
                                    if($post['pinned'] == 1)
                                        echo " <img src=pin.gif title=Pinned width=10 height=10 alt=P>";
                                    echo "</td>";
                                echo "</tr>";
                                $count++;
                            }
                        }
                        else
                        {
                            echo "<tr><td class=td1>No posts found in this board.</td></tr>";
                        }
                    echo "</table>";
                echo "</div>";
                echo $hr;
                echo "<div class='r b'>";
                    echo "<input type=password name=password id=moderate_input onfocus='this.type=\"text\"' onblur='this.type=\"password\"' value={$_SESSION[$board]['password']}>";
                    echo "<input type=submit name=action value='Delete Post'>";
                    echo "<input type=submit name=action value='Delete Image'>";
                    if($common->is_admin())
                        echo "<input type=submit name=action value='Toggle Pin'>";
                    if($common->is_staff())
                    {
                        echo "<input type=submit name=action value='Toggle Lock'>";
                        echo "<input type=submit name=action value='Ban'>";
                    }
                echo "</div>";
                echo "<div class=p>";
                    echo $paginate->paginate();
                echo "</div>";
            echo "</form>";
        break;
    }
}
$output = ob_get_contents();
ob_end_clean();

//**************************** VIEW ****************************
?>
<html>
<head>
<title><?php echo $cfg[$board]['title']; echo (strlen($cfg[$board]['desc']) > 0 ? $cfg[$board]['desc'] : ''); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<style type=text/css><!--body,td{background:#B2B292;font-family:arial;font-size:13px}form{margin:0px;padding:0px}a{color:#000;text-decoration:none}a:hover{color:#F00;text-decoration:none}input#moderate_input{font-size:10px}.t a:hover{color:#D3D3AB}#a{color:#CC1105}#q{color:#789922}#u0{color:#117743}#u0 a,#u1 a,#u2 a{color:#0000FF;text-decoration:underline}#u1{color:#FF0000}#u2{color:#800080}.td1{background:#FFFFCC}.td2{background:#D3D3AB}.ls{font-weight:normal}.ls a:hover{color:#D3D3AB}.c0{background:#99FFCC}.c16{background:#CCCC99}.c2{background:#CCCCCC}.c3{background:#CCCCFF}.c4{background:#CCFF99}.c5{background:#CCFFCC}.c6{background:#CCFFFF}.c7{background:#FFCC99}.c8{background:#FFCCCC}.c9{background:#FFCCFF}.c10{background:#FFFF99}.c11{background:#FFFFCC}.w{background:#FFF}.tbl{border-collapse:collapse;border-color:#000}.r{float:right}.b{margin-bottom:5px}.p{font-size:16px}#msg p{margin:6px 0px}.r0{background-color:#FFE5E5}.r1{background-color:#FFCCCC}.r2{background-color:#FFB2B2}.r3{background-color:#FF9999}.r4{background-color:#FF7F7F}.r5{background-color:#FF6666}.r6{background-color:#FF4C4C}.r7{background-color:#FF3232}.r8{background-color:#FF1919}.r9{background-color:#FF0000}--></style>
<script type=text/javascript src=jquery.js></script>
<script type=text/javascript src=main.js></script>
</head>
<body>
<?php echo $output; ?>
</body>
</html>
