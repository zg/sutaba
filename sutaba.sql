SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `bans` (
  `id` int(40) NOT NULL AUTO_INCREMENT,
  `board` varchar(40) NOT NULL,
  `post_id` int(40) NOT NULL,
  `time` int(10) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `expires` int(10) NOT NULL,
  `reason` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id` (`post_id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `images` (
  `id` int(50) NOT NULL AUTO_INCREMENT,
  `filename` varchar(20) NOT NULL,
  `type` varchar(20) NOT NULL,
  `size` int(50) NOT NULL,
  `width` int(20) NOT NULL,
  `height` int(20) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `posts` (
  `id` int(40) NOT NULL AUTO_INCREMENT,
  `board` varchar(40) NOT NULL,
  `parent_id` int(40) DEFAULT '0',
  `time` int(10) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `name` varchar(30) DEFAULT NULL,
  `email` text,
  `subject` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `file` varchar(20) NOT NULL,
  `password` varchar(40) NOT NULL,
  `pinned` tinyint(1) NOT NULL DEFAULT '0',
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(40) NOT NULL AUTO_INCREMENT,
  `board` varchar(40) NOT NULL,
  `post_id` int(40) NOT NULL,
  `time` int(10) NOT NULL,
  `ip` varchar(15) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `spam` (
  `board` varchar(40) NOT NULL,
  `ip` varchar(15) NOT NULL,
  `time` int(10) NOT NULL,
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `wordfilter` (
  `id` int(40) NOT NULL AUTO_INCREMENT,
  `board` varchar(40) NOT NULL,
  `word` text NOT NULL,
  `replacement` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
