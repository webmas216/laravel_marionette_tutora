<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\LessonBooking;
use Illuminate\Database\DatabaseManager;
use App\Billing\Contracts\BillingInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use App\Billing\Contracts\Exceptions\BillingExceptionInterface;
use App\Repositories\Contracts\LessonBookingRepositoryInterface;

class CaptureLessonBookingPaymentsCommand extends Command
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'tutora:capture_lesson_booking_payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Capture payments on lesson bookings.';

    /**
     * @var DatabaseManager
     */
    protected $database;

    /**
     * @var LessonBookingRepositoryInterface
     */
    protected $bookings;

    /**
     * @var BillingInterface
     */
    protected $billing;

    /**
     * Create a new command instance.
     *
     * @param  DatabaseManager                  $database
     * @param  LessonBookingRepositoryInterface $bookings
     * @param  BillingInterface                 $billing
     * @return void
     */
    public function __construct(
        DatabaseManager                  $database,
        LessonBookingRepositoryInterface $bookings,
        BillingInterface                 $billing
    ) {
        parent::__construct();

        $this->database = $database;
        $this->bookings = $bookings;
        $this->billing  = $billing;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function fire()
    {
        loginfo("[ Background ] {$this->name}");

        // Lookup
        $date     = $this->getDate();
        $bookings = $this->bookings->getCapturableBeforeDate($date);
        // Loopy
        foreach ($bookings as $booking) {

            $this->database->transaction(function () use ($booking, $date) {
                // Lookups
                $lesson       = $booking->lesson;
                $relationship = $lesson->relationship;
                $student      = $relationship->student;
                $tutor        = $relationship->tutor;
                // Billing
                try {
                    // Account
                    $customer = $this->billing->account($student);
                    // Capture
                    $charge = $this->billing->charge($customer, $booking);
                    $charge->capture();
                    // Vent
                    LessonBooking::paid($booking, $charge);
                } catch (BillingExceptionInterface $e) {
                    LessonBooking::paymentFailed($booking, $date, $e);
                }
                // Dispatch
                $this->dispatchFor($booking);
                // Save
                $this->bookings->save($booking);
            });
        }
        
    }

    /**
     * Get the date to look for bookings before
     *
     * @return Carbon
     */
    protected function getDate()
    {
        $minutes = config('lessons.capture_payment_period', -1440);

        return Carbon::now()->addMinutes($minutes);
    }

}
