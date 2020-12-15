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
    protected $signature = 'exchange:sync-folders {mailbox} {folder=null} {force_resync=false}';

    protected $description = 'Sync all folders from a mailbox';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $mailbox = ExchangeMailbox::where('email', '=', $this->argument('mailbox'))->firstOrFail();


        $foldersQuery = ExchangeFolder::where('exchange_mailbox_id', '=', $mailbox->id)
            ->whereNotNull('item_id')
            ->whereNull('status');
        
        if( $this->argument('folder') != null )
            $foldersQuery->where('name', $this->argument('folder'));
            
        $folders = $foldersQuery->get();

        $count = 0;
        foreach($folders AS $folder)
        {
            // Refresh object as state might changed by an external process
            $folder = ExchangeFolder::whereId($folder->id)->first();

            // Only restart the process if instructed
            if( $folder->status == 'COMPLETE_SYNC' && $this->argument('force_resync') )
            {
                $folder->status = null;
                $folder->status_data = null;
                $folder->save();
            }

            echo $folder->name . PHP_EOL;
            while( $folder->status != ExchangeFolder::STATUS_COMPLETE_SYNC && $folder->status != ExchangeFolder::STATUS_SYNC_IN_PROGRESS )
            {
                $res = $folder->syncExchange(ExchangeFolder::MODE_PROGRESSIVE);
                echo 'Saved: ' . $res['inserted'] . ' Duplicate: ' . $res['existing'] . ' Re-link: ' . $res['re-linked'] . PHP_EOL;
            }

            $count++;
        }
    }
}