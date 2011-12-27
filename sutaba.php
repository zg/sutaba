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
