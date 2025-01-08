Laravel Snooze
=================================

> Schedule future notifications and reminders in Laravel

<p align="center">
    <img src="./snooze-logo-v1.png" />
</p>

[![Build Status](https://img.shields.io/github/actions/workflow/status/thomasjohnkane/snooze/php.yml?branch=master
)](https://github.com/thomasjohnkane/snooze/actions)
[![styleci](https://styleci.io/repos/173246329/shield)](https://styleci.io/repos/173246329)

[![Latest Stable Version](https://poser.pugx.org/thomasjohnkane/snooze/v/stable)](https://packagist.org/packages/thomasjohnkane/snooze)
[![Total Downloads](https://poser.pugx.org/thomasjohnkane/snooze/downloads)](https://packagist.org/packages/thomasjohnkane/snooze)
[![License](https://poser.pugx.org/thomasjohnkane/snooze/license)](https://packagist.org/packages/thomasjohnkane/snooze)

### Why use this package?
- Ever wanted to schedule a <b>future</b> notification to go out at a specific time? (was the delayed queue option not enough?)
- Want a simple on-boarding email drip?
- How about happy birthday emails?

#### Common use cases
- Reminder system (1 week before appt, 1 day before, 1 hour before, etc)
- Follow-up surveys (2 days after purchase)
- On-boarding Email Drips (Welcome email after sign-up, additional tips after 3 days, upsell offer after 7 days)
- Short-Term Recurring reports (send every week for the next 4 weeks)

## Installation

Install via composer
```bash
composer require thomasjohnkane/snooze
```
```bash
php artisan migrate
```

### Publish Configuration File

```bash
php artisan vendor:publish --provider="Thomasjohnkane\Snooze\ServiceProvider" --tag="config"
```

## Usage

#### Using the model trait
Snooze provides a trait for your model, similar to the standard `Notifiable` trait.
It adds a `notifyAt()` method to your model to schedule notifications.

```php
use Thomasjohnkane\Snooze\Traits\SnoozeNotifiable;
use Illuminate\Notifications\Notifiable;

class User extends Model {
    use Notifiable, SnoozeNotifiable;

    // ...
}

// Schedule a birthday notification
$user->notifyAt(new BirthdayNotification, Carbon::parse($user->birthday));

// Schedule for a week from now
$user->notifyAt(new NextWeekNotification, Carbon::now()->addDays(7));

// Schedule for new years eve
$user->notifyAt(new NewYearNotification, Carbon::parse('last day of this year'));
```

#### Using the ScheduledNotification::create helper
You can also use the `create` method on the `ScheduledNotification`.
```php
ScheduledNotification::create(
     Auth::user(), // Target
     new ScheduledNotificationExample($order), // Notification
     Carbon::now()->addHour() // Send At
);
```

This is also useful for scheduling anonymous notifications (routed direct, rather than on a model).
```php
$target = (new AnonymousNotifiable)
    ->route('mail', 'hello@example.com')
    ->route('sms', '56546456566');

ScheduledNotification::create(
     $target, // Target
     new ScheduledNotificationExample($order), // Notification
     Carbon::now()->addDay() // Send At
);
```

#### An important note about scheduling the `snooze:send` command

Creating a scheduled notification will add the notification to the database. It will be sent by running `snooze:send` command at (or after) the stored `sendAt` time.

The `snooze:send` command is scheduled to run every minute by default. You can change this value (`sendFrequency`) in the published config file. Available options are `everyMinute`, `everyFiveMinutes`, `everyTenMinutes`, `everyFifteenMinutes`, `everyThirtyMinutes`, `hourly`, and `daily`.

The only thing you need to do is make sure `schedule:run` is also running. You can test this by running `php artisan schedule:run` in the console. [To make it run automatically, read here][6].

>Note: If you would prefer snooze to not automatically schedule the commands, you can set the `scheduleCommands` config value to `false`

### Setting the send tolerance

If your scheduler stops working, a backlog of scheduled notifications will build up. To prevent users receiving all of
the old scheduled notifications at once, the command will only send mail within the configured tolerance.
By default this is set to 24 hours, so only mail scheduled to be sent within that window will be sent. This can be
configured (in seconds) using the `SCHEDULED_NOTIFICATION_SEND_TOLERANCE` environment variable or in the `snooze.php` config file.

### Setting the prune age

The package can prune sent and cancelled messages that were sent/cancelled more than x days ago. You can
configure this using the `SCHEDULED_NOTIFICATION_PRUNE_AGE` environment variable or in the `snooze.php` config file
(unit is days). This feature is turned off by default.

#### Detailed Examples

- [Delayed Notification (1 week)][3]
- [Simple On-boarding Email Drip][5]
- [Exposing Custom Data to the Notification/Email][4]

**Cancelling Scheduled Notifications**

```php
$notification->cancel();
```
<small><b>Note:</b> you cannot cancel a notification that has already been sent.</small>

**Rescheduling Scheduled Notifications**

```php
$rescheduleAt = Carbon::now()->addDay(1)

$notification->reschedule($rescheduleAt)
```
<small><b>Note:</b> you cannot reschedule a notification that has already been sent or cancelled.</small>
<small>If you want to duplicate a notification that has already been sent or cancelled, pass a truthy second parameter along with the new send date; `reschedule($date, true)`, or use the `scheduleAgainAt($date)` method shown below.</small>

**Duplicate a Scheduled Notification to be sent again**

```php
$notification->scheduleAgainAt($newDate); // Returns the new (duplicated) $notification
```

**Check a scheduled notification's status**
```php
// Check if a notification is already cancelled

$result = $notification->isCancelled(); // returns a bool

// Check if a notification is already sent

$result = $notification->isSent(); // returns a bool
```

**Conditionally interrupt a scheduled notification**

If you'd like to stop an email from being sent conditionally, you can add the `shouldInterrupt()` method to any notification. This method will be checked immediately before the notification is sent.

For example, you might not send a future drip notification if a user has become inactive, or the order the notification was for has been canceled.

```php
public function shouldInterrupt($notifiable) {
    return $notifiable->isInactive() || $this->order->isCanceled();
}
```

If this method is not present on your notification, the notification will *not* be interrupted. Consider creating a shouldInterupt trait if you'd like to repeat conditional logic on groups of notifications.


**Setting Next Schedule**

If you want to send a reoccuring notification, you can add the `nextSchedule()` method to any notification. This method will be used to tell snooze if and when to send another notifiaction.

For example, you might want to send a reminder every 7 days

```php
public function nextSchedule($notifiable) {
    return now()->addDays();
}
```

If this method is present on your notification, The time it returns will be used to schedule a notification again for that particular notification.

**Scheduled Notification Meta Information**

It's possible to store meta information on a scheduled notification, and then query the scheduled notifications by this meta information at a later stage.

This functionality could be useful for when you store notifications for a future date, but some change in the system requires
you to update them. By using the meta column, it's possible to more easily query these scheduled notifications from the database by something else than
the notifiable.

***Storing Meta Information***

Using the `ScheduledNotification::create` helper

```php
ScheduledNotification::create(
     $target, // Target
     new ScheduledNotificationExample($order), // Notification
     Carbon::now()->addDay(), // Send At,
     ['foo' => 'bar'] // Meta Information
);
```

Using the `notifyAt` trait

```php
  $user->notifyAt(new BirthdayNotification, Carbon::parse($user->birthday), ['foo' => 'bar']);
```

***Retrieving Meta Information from Scheduled Notifications***

You can call the `getMeta` function on an existing scheduled notification to retrieve the meta information for the specific notification.

Passing no parameters to this function will return the entire meta column in array form.

Passing a string key (`getMeta('foo')`), will retrieve the specific key from the meta column.

***Querying Scheduled Notifications using the ScheduledNotification::findByMeta helper***

It's possible to query the database for scheduled notifications with certain meta information, by using the `findByMeta` helper.

```php
  ScheduledNotification::findByMeta('foo', 'bar'); //key and value
```

The first parameter is the meta key, and the second parameter is the value to look for.

>Note: The index column doesn't currently make use of a database index

**Conditionally turn off scheduler**

If you would like to disable sending of scheduled notifications, set an env variable of `SCHEDULED_NOTIFICATIONS_DISABLED` to `true`. You will still be able to schedule notifications, and they will be sent once the scheduler is enabled.

This could be useful for ensuring that scheduled notifications are only sent by a specific server, for example.

**Enable onOneServer**

If you would like the `snooze` commands to utilise the Laravel Scheduler's `onOneServer` functionality, you can use the following environment variable:

```bash

SCHEDULED_NOTIFICATIONS_ONE_SERVER = true

```

## Running the Tests

```bash
composer test
```

## Security

If you discover any security related issues, please email instead of using the issue tracker.

## Contributing

1. Fork it (<https://github.com/thomasjohnkane/snooze/fork>)
2. Create your feature branch (`git checkout -b feature/fooBar`)
3. Commit your changes (`git commit -am 'Add some fooBar'`)
4. Push to the branch (`git push origin feature/fooBar`)
5. Create a new Pull Request

## Credits

- [Thomas Kane && Flux Bucket](https://github.com/thomasjohnkane)
- [Atymic](https://github.com/atymic)
- [All contributors](https://github.com/thomasjohnkane/snooze/graphs/contributors)

This package is bootstrapped with the help of
[melihovv/laravel-package-generator](https://github.com/melihovv/laravel-package-generator).

[1]: ./docs/register-provider-and-facade.md "Register Service Provider && Facade"
[2]: https://carbon.nesbot.com/docs/ "Carbon"
[3]: ./docs/examples/basic-delayed-notification.md "Delayed 1 Week Example"
[4]: ./docs/examples/custom-data-example.md "Custom Data Example"
[5]: ./docs/examples/on-boarding-email-drip.md  "On-boarding Drip Example"
[6]: https://laravel.com/docs/5.7/scheduling#introduction "Configure Laravel Scheduler"
[7]: https://laravel.com/docs/5.7/scheduling#introduction "Generators"
