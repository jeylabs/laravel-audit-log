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
     * it should implement \Jeylabs\AuditLog\Contracts\AuditLogModel.
     * Only used by the eloquent driver; kept for backwards compatibility,
     * prefer 'stores.eloquent.model' below.
     */
    'audit_log_model' => \Jeylabs\AuditLog\Models\AuditLog::class,

    /*
     * If set to true, it will store lat/long to the database
     */
    'track_location' => true,

    /*
     * If set to true, it will store ip address to the database
     */
    'track_ip' => true,

    /*
     * Which storage backend to write/read audit logs through.
     * Supported: 'eloquent', 'mongodb', 'dynamodb'.
     */
    'driver' => env('AUDIT_LOGGER_DRIVER', 'eloquent'),

    'stores' => [
        'eloquent' => [
            'model' => \Jeylabs\AuditLog\Models\AuditLog::class,
        ],
        'mongodb' => [
            'connection' => env('AUDIT_LOGGER_MONGODB_CONNECTION', 'mongodb'),
            'model' => \Jeylabs\AuditLog\Models\MongoAuditLog::class,
        ],
        'dynamodb' => [
            'region' => env('AUDIT_LOGGER_DYNAMODB_REGION', 'us-east-1'),
            'table' => env('AUDIT_LOGGER_DYNAMODB_TABLE', 'audit_logs'),
            'causer_index' => 'causer_index',
            'subject_index' => 'subject_index',
            'endpoint' => env('AUDIT_LOGGER_DYNAMODB_ENDPOINT'),
            'credentials' => [
                'key' => env('AUDIT_LOGGER_DYNAMODB_KEY'),
                'secret' => env('AUDIT_LOGGER_DYNAMODB_SECRET'),
            ],
            'delete_records_older_than_days_via_ttl' => null,
            'ttl_attribute' => 'ttl',
        ],
    ],
];
```

---
Storing and accessing audit logs in MySQL, PostgreSQL, MongoDB, or DynamoDB
---

Every write goes through the `Jeylabs\AuditLog\Contracts\AuditLogStore` contract, and which concrete backend it talks to is picked by `laravel-audit-log.driver`. `auditLog()->log(...)`, `LogsAudit`'s automatic model-event logging, `CausesAudit`, the `auditlog:clean` command, and the visitor-location controller all work the same way regardless of driver — only the driver-specific setup below changes.

### MySQL / PostgreSQL (and SQLite, SQL Server)

This is the default `eloquent` driver and needs nothing beyond the standard installation above — Eloquent is database-agnostic, so pointing your app's default database connection at MySQL or PostgreSQL (any currently supported version) just works. `subject()`/`causer()` relations, `AuditLog::causedBy()`/`forSubject()`/`inLog()` scopes, and `$user->auditLog`/`$user->activity` (via `LogsAudit`/`CausesAudit`) are all fully supported.

The unit tests exercise this same `EloquentAuditLogStore` code path against sqlite. To also run it against a live MySQL or PostgreSQL server (catches anything sqlite's more lenient typing papers over):

```bash
MYSQL_LOCAL_DSN=mysql://user:pass@127.0.0.1:3306/audit_log_it composer test-mysql-local
POSTGRES_LOCAL_DSN=pgsql://user:pass@127.0.0.1:5432/audit_log_it composer test-postgres-local
```

`tests/Integration/MySqlLocalIntegrationTest.php` and `tests/Integration/PostgresLocalIntegrationTest.php` skip themselves when their respective env var isn't set, so they're a no-op in normal `composer test` runs / CI without those servers available.

### MongoDB

1. `composer require mongodb/laravel-mongodb` (and the PHP `mongodb` extension) — not a dependency of this package, since most apps don't need it.
2. Configure a `mongodb` connection in `config/database.php` per that package's docs.
3. Set `AUDIT_LOGGER_DRIVER=mongodb` (or `'driver' => 'mongodb'` in the published config).

Audit logs are then written through `Jeylabs\AuditLog\Models\MongoAuditLog`, which mirrors the SQL `AuditLog` model (same relations, scopes, and casts) on top of `mongodb/laravel-mongodb`'s Eloquent-compatible base model, including cross-database (`subject`/`causer`) relations to your normal SQL models.

The unit tests run the mongodb driver's code path against sqlite (both go through the same `EloquentAuditLogStore`), so they need no MongoDB server. To also exercise the real `mongodb/laravel-mongodb` + `ext-mongodb` stack against a live MongoDB:

```bash
mongod --dbpath /tmp/mongo-data --port 27017 --bind_ip 127.0.0.1
MONGODB_LOCAL_URI=mongodb://127.0.0.1:27017 composer test-mongodb-local
```

`tests/Integration/MongoDbLocalIntegrationTest.php` skips itself when `MONGODB_LOCAL_URI` isn't set (or the `mongodb` extension isn't loaded), so it's a no-op in normal `composer test` runs / CI without a Mongo instance available.

### DynamoDB

DynamoDB has no joins or secondary query engine, so `subject()`/`causer()` relations and the `$user->auditLog`/`$user->activity` traits are **not** available under this driver (resolving them throws `InvalidConfiguration`) — use `AuditLogStore::causedBy()`/`forSubject()` directly instead:

```php
app(\Jeylabs\AuditLog\Contracts\AuditLogStore::class)->causedBy(\App\Models\User::class, $user->id);
```

Setup:

1. `composer require async-aws/dynamo-db` (a lightweight independent client, not the full `aws/aws-sdk-php`) — or bind your own implementation of `Jeylabs\AuditLog\Contracts\DynamoDbClient` in a service provider if you'd rather use the full AWS SDK or another client.
2. Provision the table yourself (this package does not create it) with:
   - Partition key: `id` (String)
   - A Global Secondary Index named `causer_index` (configurable via `stores.dynamodb.causer_index`) with partition key `causer_key` (String)
   - A Global Secondary Index named `subject_index` (configurable via `stores.dynamodb.subject_index`) with partition key `subject_key` (String)
3. Set `AUDIT_LOGGER_DRIVER=dynamodb` and the `AUDIT_LOGGER_DYNAMODB_*` environment variables (region, table, endpoint, credentials).

`auditlog:clean` has no efficient range-delete on DynamoDB, so it falls back to a full table scan for this driver — fine for occasional maintenance, but for production-scale cleanup set `stores.dynamodb.delete_records_older_than_days_via_ttl` to a day count and enable DynamoDB's native TTL on the `ttl` attribute in the table settings; expired items are then removed automatically with no scan needed.

The unit tests mock `Jeylabs\AuditLog\Contracts\DynamoDbClient`, so they need no AWS access. To also exercise the real async-aws wire protocol (marshaling, `CreateTable`/GSIs, queries) against [DynamoDB Local](https://docs.aws.amazon.com/amazondynamodb/latest/developerguide/DynamoDBLocal.html):

```bash
java -jar DynamoDBLocal.jar -inMemory -port 8000 -sharedDb
DYNAMODB_LOCAL_ENDPOINT=http://127.0.0.1:8000 composer test-dynamodb-local
```

`tests/Integration/DynamoDbLocalIntegrationTest.php` skips itself when `DYNAMODB_LOCAL_ENDPOINT` isn't set, so it's a no-op in normal `composer test` runs / CI without a Dynamo endpoint available.

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

The package ships with a `CausesAudit` trait which can be added to any model that you use as a causer. It provides an `auditLog` relationship which returns all activities that are caused by the model.

If you include it in the `User` model you can simply retrieve all the current users activities like this:

```php

\Auth::user()->auditLog;

```
