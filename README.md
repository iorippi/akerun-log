# akerun-log
Akerun API interpreter for PHP
Version 0.8.0

## Get started

1. Download akerun-log.php
2. Call them from `include`
3. Instantiate class and save to your favorite variable with options for your needs
4. Access object!!

## Example

```php
// 1. Include source code
include 'akerun-log.php';
// 
$roomA = new AkerunLogByNFCUsers(
	array(
		'name' => 'Room A',
		'akerun_id' => 'A0000001',
		'access_token' => 'abcdef123456789',
		'log_hours' => 24 * 3,
		'filter_user_full_name' => array(
			'Jon Doe',
			'田中 太郎'
		)
	)
);

echo $roomA->name;		// "Room A"
echo $roomA->data_users		// Array (List of history in last 3 days without Mr.Jon and 田中-san)
echo $roomA->nfc_user_count;	// 23 (Current estimate of users in Room A besides Mr.Jon and 田中-san)
```

## Prerequists
- PHP Version > 5.5

## Classes
### AkerunLog
Get list from API request, and save to cache (per session/time)
### AkerunLogByUsers
Sort list by unique users
### AkerunLogByNFCUsers
Get estimate of current total number of users in the room

## Options
Common options for all Classes

| Option Name | Default | Input |
| ----------- | ------- | ----- |
| `name` | none | (string) Name of the room |
| `akerun_id` | none **(Required)** |  (string) Akerun ID |
| `offset` | `NULL` |  (int) Position for log acquiring |
| `limit` | `300` | (int) Max number of log entry |
| `from` | `NULL` | (datetime) Start date of log (This overrides `log_hours` option.) |
| `til` | `NULL` | (datetime) End date of log (Recommended to leave it NULL for cache controlling purpose if this were for the current time) |
| `access_token` | none **(Required)** |  (string) Access Token for API Request |
| `log_hours` | `24` | (number) Length of time in hours from now |
| `timezone` | `Asia/Tokyo` | (timezone) PHP timezone to utilize (For actual available option, check with your PHP server) |
| `filter_user_id` | `array()` | (array => string) User ids to ditch from the log |
| `filter_user_full_name` | `array()` | (array => string) User fullnames to ditch from the log |
| `nfc_only` | `TRUE` | (boolean) Whether or not to filter non-NFC users <br>This will be forced to set `TRUE` for `AkerunLogByNFCUsers` |
| `max_apireq_permin` | `50` | (int) Max API request to be executed in a minute (This is 50 [according to official statement](https://photosynth-inc.github.io/apidocs.html#api%E3%81%AE%E3%83%AA%E3%82%AF%E3%82%A8%E3%82%B9%E3%83%88%E5%88%B6%E9%99%90%E3%81%AF%E3%81%82%E3%82%8A%E3%81%BE%E3%81%99%E3%81%8B) as the time when this is developed)
| `test` | `FALSE` | (boolean) Whether or not to print out whole series of variables defined in class, including options. |

## Available objects
- `name`: (string) Name for the room
- `data`: (array) Raw data (Parsed to PHP Array from JSON)
```php
Array
(
	[Success] => 1,
	[accesses] => Array
	(
		// Newest
		[0] => Array		// Entry #0
		(
			[is_locked] => (int) 0 or 1,
                    	[client_type] => (string) Type of key (nfc_inside, nfc_outside, hand, autolock..),
			[created_at] => (datetime) Time of action,
			[user] => Array
			(
			    [id] => User ID (1),
			    [full_name] => User fullname (1),
			    [mail] => User email address (1),
			    [image_url] => User image url (1)
			),
			[akerun] => Array
			(
			    [id] => Akerun ID,
			    [name] => Akerun Name,
			    [image_url] => Akerun image url
			)
		),
		// Second Newest
		[1] => Array (..)	// Entry #1
	)
)
```
- `data_users`: (array) Data sorted by users
```php
Array
(
	[User ID (1)] => Array
	(
		[full_name] => User fullname (1),
		[history] => Array
		(
			[0] => Array
			(
				// Newest
				[client_type] => (string) Type of key (nfc_inside, nfc_outside, hand, autolock..)
				[created_at] => (datetime) Time of action
			),
			[1] => Array
			(
				// Second Newest
				..
			),
			..
		)
	),
	[User ID (2)] => Array(..),
	..
)
```
- `nfc_user_count`: (int) Estimate of current users in the room

* There are some other objects for testing/developing purposes too. (See `exec_err_log`)
For the reference (I'm sorry but) check the source code.

- - - - - - - - - - - - - - - - - -

## Caching feature

It will automatically set maximum API request interval allowed in accordance to the number of unique instance called within document and `max_apireq_permin` option. Cache will be stored in both per-session and to the file (which path is configured by `AKERUNLOG_CACHE_DIR` and `AKERUNLOG_CACHE_FILENAME`).

- - - - - - - - - - - - - - - - - -

## Upcoming features

- 日本語で書く
	- 変換面倒くさいので誰かやってくれ

- Multiple instances
	- This is possible already, though it isn't tested, and there's more than just one line that has to be fixed before putting onto test.
	- Function for making it easier to handle. (Probably no one needs but..)

- Saving Daily logs (version 2?)
	- Currently this doesn't really support caching on daily basis, but instead it only keeps the newest cache for the last 24 hours by default.
	- Maybe Cache in the all past days if it doesn't exist. (First visit on site since days after the last visit may take a while, also error prone for exceeding max-request for API.)

- Error handling
	- Currently I couldn't test API request over 50/sec, so this app still doesn't know how to handle them on failure
	- Log message truncating (Half way built `exec_err_log`, but this isn't really tested yet)
	- Currently this could just stop and mess your document if it fails.

- - - - - - - - - - - - - - - - - -

Tested on:
PHP Version
	- 5.5.27
	- 5.6.32
