<?php

namespace andres3210\laraews\Commands;


use Illuminate\Console\Command;


use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeMailbox;
use andres3210\laraews\models\ExchangeSubscription;


class ExchangeSubscriptionKeepAlive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:subscription-keep-alive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor Subscription status and perform re-subscription if connection drops';


    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     * - empty notifications should be expected every 1 minute
     * - Subscription need to refresh watermark every 30 days
     *
     * @return mixed
     */
    public function handle()
    {
        $subscriptions = ExchangeSubscription::all();

        foreach( $subscriptions AS $subscription )
        {
            $diff = \Carbon\Carbon::now()->diff( $subscription->updated_at );
            $diffMinutes = ($diff->d * 24 * 60) + ($diff->h * 60) + $diff->i;
            $diffSeconds = ($diffMinutes * 60) + $diff->s;

            if( $diffSeconds >= $subscription->keep_alive )
            {
                echo 'Subscription #'. $subscription->id . ' Timeout Expired -> time since last push notification: ' . $diff->format('%i minutes and %s seconds') .  PHP_EOL;

                $urlSubscribe = config('app.url').'/api/mailboxes/'.$subscription->exchange_mailbox_id.'/subscribe?domain='.config('app.url');

                $client = new \GuzzleHttp\Client();
                $res = $client->request('GET', $urlSubscribe, [
                    'headers'        => ['AuthToken' => config('authorization.consumers.canadavisa_evaluator_local.auth_token')],
                ]);

                if( $res->getStatusCode() == 200)
                {
                    $reSubscription = json_decode($res->getBody());
                    if( 
                        $reSubscription 
                        && isset($reSubscription->subscription) 
                        && isset($reSubscription->subscription->inbox) 
                        && isset($reSubscription->subscription->inbox->id) 
                    )
                    {
                        // successfully re-subscribed
                        // delete old subscription
                        $subscription->delete();
                        echo 'Re-Subscription Successful New #'. $reSubscription->subscription->inbox->id . PHP_EOL;
                    }
                }
            }

        }

    }


}
