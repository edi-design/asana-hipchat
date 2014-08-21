asana-hipchat
=============

Here you'll find a script that pushes changes from an [Asana](https://www.asana.com) workspace to a [HipChat](https://www.hipchat.com) room.

This script was created during our work on [hikewith.me](http://hikewith.me).

Thanks goes out to [HipChat](https://github.com/hipchat/hipchat-php) and [Ajimix](https://github.com/ajimix/asana-api-php-class) for their libraries.

## notification features

* new workspace has been created
* new project has been created
* new task has been created
	* ![new task](http://img.edi-design.net/github/asana_hipchat/created_task_v2.png)
* task changes
	* assigned
	* ![changed task](http://img.edi-design.net/github/asana_hipchat/assigned_task_v2.png)
	* completed 
	* ![completed task](http://img.edi-design.net/github/asana_hipchat/completed_task_v2.png)
	* due date changed
	* task informations changed (due, name ...)
	* ![changed task](http://img.edi-design.net/github/asana_hipchat/changed_task_v2.png)

## planed feature

* notifications on task comments
* send creator on new workspace, project

## installation

### dependencies
* php5-sqlite

		# apt-get install php5-sqlite

### script installation
	$ git clone https://github.com/edi-design/asana-hipchat.git asana_hipchat
	$ cd asana_hipchat/ext-lib
	$ wget https://raw.github.com/ajimix/asana-api-php-class/master/asana.php
	$ wget https://raw2.github.com/hipchat/hipchat-php/master/src/HipChat/HipChat.php
	$ cd ../config
	$ cp config.example.php config.php
	$ vi config.php

Edit the config.php according to your asana and hipchat installation.
The following params need to be set.

```
/**
 * Asana configuration
 */
// api key
define('ASANA_API_KEY', 'asana_api_key_here');

// if workspace id is empty, it will check all workspaces
/**
 * @todo implement this feature
 */
define('ASANA_WORKSPACE_ID', 'asana_workspace_id_here');


/**
 * hipchat configuration
 */
// api_key
define('HIPCHAT_API_KEY', 'hipchat_api_key_here');

// room_id
define('HIPCHAT_API_ROOM', 0000);

// turn notifications on and off
define('HIPCHAT_NOTIFICATIONS', true);

// hipchat name of notifier
define('HIPCHAT_NOTIFIER', 'Asana');


/**
 * script configuration
 */
define('SQLITE_DATA_FILE', BASE_DIR . '/db/asana.sqlite');
```

## run script

* simple one-time run

		$ php asana.php 
* cron script every 5 minutes

		$ crontab -e
		*/5  *    *    *    *  /usr/bin/php /path/to/script/asana.php
	
