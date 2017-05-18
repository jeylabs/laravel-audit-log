# Log audit inside your Laravel app

The `jeylabs/laravel-audit-log` package provides easy to use functions to log the activities of the users of your app. It can also automatically log model events. All activity will be stored in the `audit_logs` table.


```php
auditLog()->log('Look, I logged something');
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
        'text' => 'New Text',
    ],
    'old' => [
        'name' => 'original name',
        'text' => 'Old text',
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
    /**
     * You can specify the route prefix
     */
    'route_prefix' => 'audit-log',
    /**
     * When user visit every url update audit log
     */
    'record_visiting' => false,

    /*
     * If set to false, no audits will be saved to the database.
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
    'audit_log_model' => \Jeylabs\AuditLog\Models\AuditLog::class,
];
```

---
Logging model events
---

A neat feature of this package is that it can automatically log events such as when a model is created, updated and deleted.  To make this work all you need to do is let your model use the `Jeylabs\AuditLog\Traits\LogsAudit`-trait.

As a bonus the package will also log the changed attributes for all these events when setting `$logAttributes` property on the model.

Here's an example:

```php
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Traits\LogsAudit

class NewsItem extends Model
{
    use LogsAudit;

    protected $fillable = ['name', 'text'];
    
    protected static $logAttributes = ['name', 'text'];
}
```

Let's see what gets logged when creating an instance of that model.

```php
$newsItem = NewsItem::create([
   'name' => 'original name',
   'text' => 'New Text'
]);

//creating the newsItem will cause an activity being logged
$auditLog = AuditLog::all()->last();

$auditLog->description; //returns 'created'
$auditLog->subject; //returns the instance of NewsItem that was created
$auditLog->changes; //returns ['attributes' => ['name' => 'original name', 'text' => 'Text']];
```

Now let's update some that `$newsItem`.

```php
$newsItem->name = 'updated name'
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
        'text' => 'New text',
    ],
    'old' => [
        'name' => 'original name',
        'text' => 'Old text',
    ],
];
```

Now, what happens when you call delete?

```php
$newsItem->delete();

//deleting the newsItem will cause an activity being logged
$auditLog = AuditLog::all()->last();

$auditLog->description; //returns 'deleted'
$auditLog->changes; //returns ['attributes' => ['name' => 'updated name', 'text' => 'Text']];
```

## Customizing the events being logged

By default the package will log the `created`, `updated`, `deleted` events. You can modify this behaviour by setting the `$recordEvents` property on a model.

```php
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Traits\CausesAudit;

class NewsItem extends Model
{
    use CausesAudit;

    //only the `deleted` event will get logged automatically
    protected static $recordEvents = ['deleted'];
}
```

## Customizing the description

By default the package will log `created`, `updated`, `deleted` in the description of the activity. You can modify this text by overriding the `getDescriptionForEvent` function.

```php
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Traits\CausesAudit;

class NewsItem extends Model
{
    use CausesAudit;

    protected $fillable = ['name', 'text'];

    public function getDescriptionForEvent(string $eventName): string
    {
        return "This model has been {$eventName}";
    }

}
```

Let's see what happens now:

```php
$newsItem = NewsItem::create([
   'name' => 'original name',
   'text' => 'original Text'
]);

//creating the newsItem will cause an activity being logged
$auditLog = AuditLog::all()->last();

$auditLog->description; //returns 'This model has been created'
```

## Ignoring changes to certain attributes

If your model contains attributes whose change don't need to trigger an activity being logged you can use `$ignoreChangedAttributes`

```php
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Traits\LogsAudit;

class NewsItem extends Model
{
    use LogsAudit;
    
    protected static $ignoreChangedAttributes = ['text'];

    protected $fillable = ['name', 'text'];
    
    protected static $logAttributes = ['name', 'text'];
}
```

Changing `text` will not trigger an audit being logged.

By default the `updated_at` attribute is _not_ ignored and will trigger an activity being logged. You can simply add the `updated_at` attribute to the `$ignoreChangedAttributes` array to override this behaviour.

## Logging only the changed attributes

If you do not want to log every attribute in your `$logAttributes` variable, but only those that has actually changed after the update, you can use `$logOnlyDirty`

```php
use Illuminate\Database\Eloquent\Model;
use Jeylabs\AuditLog\Traits\LogsAudit;

class NewsItem extends Model
{
    use LogsAudit;

    protected $fillable = ['name', 'text'];
    
    protected static $logAttributes = ['name', 'text'];
    
    protected static $logOnlyDirty = true;
}
```

Changing only `name` means only the `name` attribute will be logged in the activity, and `text` will be left out.

## Using the CausesAudit trait

The package ships with a `CausesAudit` trait which can be added to any model that you use as a causer. It provides an `activity` relationship which returns all activities that are caused by the model.

If you include it in the `User` model you can simply retrieve all the current users activities like this:

```php

\Auth::user()->auditLog;

```
