<?php 

namespace andres3210\laraews\Commands;

use andres3210\laraews\models\ExchangeItem;
use andres3210\laraews\models\ExchangeMailbox;
use andres3210\laraews\models\ExchangeFolder;
use Illuminate\Console\Command;

use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeContact;
use andres3210\laraews\models\ExchangeAddressBook;


class ExchangeSyncFolders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:sync-folders {mailbox}';

    protected $description = 'Sync all folders from a mailbox';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $mailbox = ExchangeMailbox::where('email', '=', $this->argument('mailbox'))->firstOrFail();


        $folders = ExchangeFolder::where('exchange_mailbox_id', '=', $mailbox->id)
            ->whereNotNull('item_id')
            ->get();

        $count = 0;
        foreach($folders AS $folder)
        {
            echo $folder->name . PHP_EOL;
            while( $folder->status != ExchangeFolder::STATUS_COMPLETE_SYNC )
            {
                $res = $folder->syncExchange(ExchangeFolder::MODE_PROGRESSIVE);
                echo 'Saved: ' . $res['inserted'] . ' Duplicate: ' . $res['existing'] . ' Re-link: ' . $res['re-linked'] . PHP_EOL;
            }
            
            exit();

            $count++;
        }
    }
}