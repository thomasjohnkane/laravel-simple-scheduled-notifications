<?php

use Thomasjohnkane\Snooze\Models\ScheduledNotification;

return [
    /*
     * The table the scheduled notifications are stored in
     */
    'table' => 'scheduled_notifications',

    /*
     * The ScheduledNotification model to use.
     * If you need to customise the model you can override this
     */
    'model' => ScheduledNotification::class,

    /*
     * The frequency at which to send notifications
     *
     * Available options are everyMinute, everyFiveMinutes, everyTenMinutes,
     * everyFifteenMinutes, everyThirtyMinutes, hourly, and daily.
     */
    'sendFrequency' => env('SCHEDULED_NOTIFICATION_SEND_FREQUENCY', 'everyMinute'),

    /*
     * The tolerance at which to look for old notifications waiting to be sent, in seconds.
     * This is to prevent sending a large amount of notifications if the command stops
     * running. By default it's set to 24 hours
     */
    'sendTolerance' => env('SCHEDULED_NOTIFICATION_SEND_TOLERANCE', 60 * 60 * 24),

    /*
     * The age at which to prune sent/cancelled notifications, in days.
     * If set to null, pruning will be turned off. By default it's turned off
     */
    'pruneAge' => env('SCHEDULED_NOTIFICATION_PRUNE_AGE', null),

    /*
     * Disable sending of scheduled notifications
     * You will still be able to schedule notifications,
     * and they will be sent once the scheduler is enabled.
     */
    'disabled' => env('SCHEDULED_NOTIFICATIONS_DISABLED', false),

    /*
     * Should the snooze commands utilise the Laravel onOneServer functionality
     */
    'onOneServer' => env('SCHEDULED_NOTIFICATIONS_ONE_SERVER', false),

    /*
     * Should snooze automatically schedule the snooze:send and snooze:prune commands
     */
    'scheduleCommands' => env('SCHEDULED_NOTIFICATIONS_SCHEDULE_COMMANDS', true),

    /*
     * Should snooze automatically register the migrations
     */
    'registerMigrations' => env('SCHEDULED_NOTIFICATIONS_REGISTER_MIGRATIONS', true),

    /*
     * Will not save loaded relationships to the database when serializing
     */
    'doNotLoadRelationsOnSerialize' => env('DO_NOT_LOAD_RELATIONS_ON_SERIALIZE', false),

    /*
     * Set true if you want delete notification when
     * any model missing during unserialization
     * otherwise an exception will thrown
     */
    'deleteWhenMissingModels' => env('SCHEDULED_NOTIFICATIONS_DELETE_WHEN_MISSING_MODELS', false),
];
