<?php
/**
 * cli script to collect data from Asana
 * and check it against the data collected during the last runtime of this script.
 *
 * if something changed, you will be notified via HipChat
 *
 * @author		André Wiedemann (andre@hikewith.me)
 * @package 	asana-hipchat
 * @copyright 	2014 hikewith.me
 * @version 	1.0
 */

define('BASE_DIR', dirname(__FILE__));
/**
 * install external libraries from followoing urls to ext-lib:
 * $ wget https://raw.github.com/ajimix/asana-api-php-class/master/asana.php
 * $ wget https://raw2.github.com/hipchat/hipchat-php/master/src/HipChat/HipChat.php
 */
require_once(BASE_DIR . '/config/config.php');
require_once(BASE_DIR . '/ext-lib/asana.php');
require_once(BASE_DIR . '/ext-lib/HipChat.php');
require_once(BASE_DIR . '/lib/AsanaHipchat.php');

/**
 * init
 */
$obj_asana_hipchat = new AsanaHipchat();
$obj_asana_hipchat->setAsanaApiKey(ASANA_API_KEY);
$obj_asana_hipchat->setHipchatApiKey(HIPCHAT_API_KEY);
$obj_asana_hipchat->setHipchatRoomId(HIPCHAT_API_ROOM);
$obj_asana_hipchat->setHipchatNotifications(HIPCHAT_NOTIFICATIONS);
$obj_asana_hipchat->setHipchatNotifier(HIPCHAT_NOTIFIER);
$obj_asana_hipchat->setOldDataFile(OLD_DATA_FILE);

/**
 * run
 */
$obj_asana_hipchat->run();
