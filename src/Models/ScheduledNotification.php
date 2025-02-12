<?php

namespace Thomasjohnkane\Snooze\Models;

use __PHP_Incomplete_Class;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Thomasjohnkane\Snooze\Events\NotificationDeleted;
use Thomasjohnkane\Snooze\Events\NotificationInterrupted;
use Thomasjohnkane\Snooze\Events\NotificationRescheduled;
use Thomasjohnkane\Snooze\Events\NotificationSent;
use Thomasjohnkane\Snooze\Exception\NotificationAlreadySentException;
use Thomasjohnkane\Snooze\Exception\NotificationCancelledException;
use Thomasjohnkane\Snooze\Exception\UnserializeFailedException;
use Thomasjohnkane\Snooze\Serializer;

class ScheduledNotification extends Model
{
    /** @var string */
    protected $table;
    /** @var Serializer */
    protected $serializer;

    protected $guarded = [];

    protected $attributes = [
        'sent_at' => null,
        'rescheduled_at' => null,
        'cancelled_at' => null,
    ];

    protected $casts = [
        'meta' => 'array',
        'send_at' => 'immutable_datetime',
        'sent_at' => 'immutable_datetime',
        'rescheduled_at' => 'immutable_datetime',
        'cancelled_at' => 'immutable_datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = config('snooze.table');
        $this->serializer = app(Serializer::class);
    }

    public function send(): void
    {
        if ($this->cancelled_at !== null) {
            throw new NotificationCancelledException('Cannot Send. Notification cancelled.', 1);
        }

        if ($this->sent_at !== null) {
            throw new NotificationAlreadySentException('Cannot Send. Notification already sent.', 1);
        }

        try {
            $notifiable = $this->serializer->unserialize($this->target);
            $notification = $this->serializer->unserialize($this->notification);

            if (! is_object($notifiable) || ! is_object($notification)) {
                throw new ModelNotFoundException('Unserialized event is not an object.');
            }

            if ($notifiable instanceof __PHP_Incomplete_Class || $notification instanceof __PHP_Incomplete_Class) {
                throw new ModelNotFoundException('Unserialized event is an incomplete class (__PHP_Incomplete_Class).');
            }
        } catch (ModelNotFoundException $e) {
            if (config('snooze.deleteWhenMissingModels', false) === true) {
                $this->delete();
                event(new NotificationDeleted($this));

                return;
            }

            throw $e;
        } catch (\Exception $exception) {
            throw new UnserializeFailedException(sprintf('Cannot Send. Unserialize Failed. (%s)', $exception->getMessage()), 2, $exception);
        }

        if ($this->shouldInterrupt($notification, $notifiable)) {
            $this->cancel();
            event(new NotificationInterrupted($this));

            return;
        }

        if ($sendAt = $this->shouldRescheduleFor($notification, $notifiable)) {
            $this->reschedule($sendAt);
            event(new NotificationRescheduled($this));

            return;
        }

        $notifiable->notify($notification);

        $this->sent_at = Carbon::now();
        $this->save();

        event(new NotificationSent($this));
    }

    /**
     * @param  object|null  $notification
     * @param  object|null  $notifiable
     * @return bool
     */
    public function shouldInterrupt(?object $notification = null, ?object $notifiable = null): bool
    {
        if (! $notification) {
            $notification = $this->serializer->unserialize($this->notification);
        }

        if (! $notifiable) {
            $notifiable = $this->serializer->unserialize($this->target);
        }

        if (method_exists($notification, 'shouldInterrupt')) {
            return (bool) $notification->shouldInterrupt($notifiable);
        }

        return false;
    }

    /**
     * @param  object|null  $notification
     * @param  object|null  $notifiable
     * @return \DateTimeInterface|string|null
     */
    public function shouldRescheduleFor(?object $notification = null, ?object $notifiable = null)
    {
        if (! $notification) {
            $notification = $this->serializer->unserialize($this->notification);
        }

        if (! $notifiable) {
            $notifiable = $this->serializer->unserialize($this->target);
        }

        if (method_exists($notification, 'shouldRescheduleFor')) {
            return $notification->shouldRescheduleFor($notifiable);
        }

        return null;
    }

    /**
     * @return void
     *
     * @throws NotificationAlreadySentException
     */
    public function cancel(): void
    {
        if ($this->sent_at !== null) {
            throw new NotificationAlreadySentException('Cannot Cancel. Notification already sent.', 1);
        }

        $this->cancelled_at = Carbon::now();
        $this->save();
    }

    /**
     * @param  \DateTimeInterface|string  $sendAt
     * @param  bool  $force
     * @return self
     *
     * @throws NotificationAlreadySentException
     * @throws NotificationCancelledException
     */
    public function reschedule($sendAt, $force = false): self
    {
        if (! $sendAt instanceof \DateTimeInterface) {
            $sendAt = Carbon::parse($sendAt);
        }

        if (($this->sent_at !== null || $this->cancelled_at !== null) && $force) {
            return $this->scheduleAgainAt($sendAt);
        }

        if ($this->sent_at !== null) {
            throw new NotificationAlreadySentException('Cannot Reschedule. Notification Already Sent', 1);
        }

        if ($this->cancelled_at !== null) {
            throw new NotificationCancelledException('Cannot Reschedule. Notification cancelled.', 1);
        }

        $this->send_at = $sendAt;
        $this->rescheduled_at = Carbon::now();
        $this->save();

        return $this;
    }

    /**
     * @param  \DateTimeInterface|string  $sendAt
     * @return self
     */
    public function scheduleAgainAt($sendAt): self
    {
        if (! $sendAt instanceof \DateTimeInterface) {
            $sendAt = Carbon::parse($sendAt);
        }

        $notification = $this->replicate();

        $notification->fill([
            'send_at' => $sendAt,
            'sent_at' => null,
            'rescheduled_at' => null,
            'cancelled_at' => null,
        ]);

        $notification->save();

        return $notification;
    }
}
