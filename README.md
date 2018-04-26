# akerun-log
Akerun API interpreter for PHP
Version 0.3

## Prerequists
- PHP Version > 5.5

## Classes
### AkerunLog
(API アクセス、JSON の取得)
### AkerunLogByUsers
(AkerunLog で取得したデータをユーザ毎に整理)
### AkerunLogByNFCUsers
(AkerunLogByUsers で取得したデータから在室人数を予測)

## Options
各クラス共通
```php
Array(
	'name' => [str: データ名（部屋名など）],
	'akerun_id' => [str, required: 履歴を取得するakerunのid],
	'access_token' => [str, required: API発行トークン],
	'log_hours' => [num, default: 24: 履歴取得時間],
	'nfc_only' => [num(0/1), default: 1: NFC 制限スイッチ],
	'test' => array(
		[test index] => [num(0/1), default: 0: テストスイッチ],
		..
	)
)
```

## Objects
- name
	- (String)
	- ::AkerunLog
	- データ名（部屋名など）
- log
	- (Array)
	- ::AkerunLog
	- API 取得データ（JSON）
- akerun_json_log
	- (String)
	- ::AkerunLog
	- API 取得エラーメッセージ
- log_users
	- (Array)
	- ::AkerunLogByUsers
	- ユーザ毎履歴
```php
Array(
	[ユーザID 1] => Array (
		[full_name] => [ユーザ名 1],
		[history] => Array (
			[0] => Array (
				* * 最新解錠履歴 * *
				[0] => [解錠タイプ (nfc_inside, nfc_outside, hand, autolock..)]
				[1] => [解錠時間]
			),
			[1] => Array(
				* * 古い解錠履歴 * *
				..
			),
			..
		)
	),
	[ユーザID 2] => Array(
		..
	),
	..
)
```
- nfc_user_count
	- (Number)
	- ::AkerunLogByNFCUsers
	- NFC 在室予測人数（log_hours 時間内で最後に nfc_outside: 室外 NFC 解錠を行ったユーザ数）

## Example
### Example A
```php
include 'akerun-log.php';
$roomA = new AkerunLogByNFCUsers(array(
	'name' => 'Room A',
	'akerun_id' => 'xxxxx',
	'access_token' => 'yyyyy'
));
echo $roomA->nfc_user_count;	// 23
echo $roomA->name;		// "Room A"
```

### Example B
```php
include 'akerun-log.php';
$roomB_param = array(
	'name' => 'Room B',
	'akerun_id' => 'ppppp',
	'access_token' => 'qqqqq',
	'log_hours' => 72, // last 3days
);
$roomB_all = new AkerunLogByUsers($roomB_param);
$roomB_nfc = new AkerunLogByNFCUsers($roomB_param); // インスタンス間でキャッシュを共有しているのでAPIリクエストは１回のみ
```

- - - - - - - - - - - - - - - - - -

## Caching feature

### AkerunLog
Make API Call Cache per akerun_id every 50/n times in 60sec
(where n is the number of unique akerun_id stored to cache: 3 akerun_ids
=> 16 times per minute = every 60 / 16 sec = every 4 sec)

* This is per http request: will be updated to per time in version 0.4

- - - - - - - - - - - - - - - - - -

## Upcoming features

- AkerunLog
	- Caching per time
	- Error handling when API call exceeded 50times/minutes (API max request is not working?? will update if successful.)

- - - - - - - - - - - - - - - - - -

Tested on:
PHP Version
	- 5.5.27
	- 5.6.32

- - - - - - - - - - - - - - - - - -
