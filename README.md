# Log audit inside your Laravel app

The `jeylabs/laravel-audit-log` package provides easy to use functions to log the activities of the users of your app. It can also automatically log model events. All activity will be stored in the `audit_logs` table.


```php
AuditLog()->log('Look, I logged something');
```

You can retrieve all activity using the `Jeylabs\Auditlog\Models\AuditLog` model.

```php
AuditLog::all();
```

Here's a more advanced example:
```php
auditLog()
   ->performedOn($anEloquentModel)
   ->causedBy($user)
   ->withProperties(['customProperty' => 'customValue'])
   ->log('Look, I logged something');
   
$lastLoggedAudit = AuditLog::all()->last();

$lastLoggedAudit->subject; //returns an instance of an eloquent model
$lastLoggedAudit->causer; //returns an instance of your user model
$lastLoggedAudit->getExtraProperty('customProperty'); //returns 'customValue'
$lastLoggedAudit->description; //returns 'Look, I logged something'
```

```php
$newsItem->name = 'updated name';
$newsItem->save();

//updating the newsItem will cause an activity being logged
$auditLog = AuditLog::all()->last();

$auditLog->description; //returns 'updated'
$auditLog->subject; //returns the instance of NewsItem that was created
```

Calling `$auditLog->changes` will return this array:

```php
[
   'attributes' => [
        'name' => 'updated name',
        'text' => 'Lorum',
    ],
    'old' => [
        'name' => 'original name',
        'text' => 'Lorum',
    ],
];
```


## Installation

You can install the package via composer:

``` bash
composer require jeylabs/laravel-audit-log
```

Next, you must install the service provider:

```php
// config/app.php
'providers' => [
    ...
    Jeylabs\AuditLog\AuditLogServiceProvider::class,
];
```

You can publish the migration with:
```bash
php artisan vendor:publish --provider="Jeylabs\AuditLog\AuditLogServiceProvider" --tag="migrations"
```

*Note*: The default migration assumes you are using integers for your model IDs. If you are using UUIDs, or some other format, adjust the format of the subject_id and causer_id fields in the published migration before continuing.

After the migration has been published you can create the `audit-logs` table by running the migrations:


```bash
php artisan migrate
```

You can optionally publish the config file with:
```bash
php artisan vendor:publish --provider="Jeylabs\AuditLog\AuditLogServiceProvider" --tag="config"
```

This is the contents of the published config file:

```php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('AUDIT_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording audits older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the audit() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the default Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log audit. The only requirement is that
     * it should be or extend the \Jeylabs\AuditLog\Models\AuditLog model.
     */
    'activity_model' => \Jeylabs\AuditLog\Models\AuditLog::class,
];
```