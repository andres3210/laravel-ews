<?php

namespace andres3210\laraews\Commands;

use andres3210\laraews\models\ExchangeItem;
use andres3210\laraews\models\ExchangeMailbox;
use Illuminate\Console\Command;


use andres3210\laraews\ExchangeClient;
use andres3210\laraews\models\ExchangeContact;
use andres3210\laraews\models\ExchangeAddressBook;

class ExchangeSyncContacts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exchange:sync-contacts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Email Queue [options: sync | sync-progressive | delete-old | route]';

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
     *
     * @return mixed
     */
    public function handle()
    {
        $mailboxes = ExchangeMailbox::all();
        foreach($mailboxes AS $mailbox)
        {
            // First sync Root Address Book
            echo $mailbox->email . ' >> Sync Root Address Book' . PHP_EOL;
            $mailbox->syncContacts();

            // Second - sync each sub-folder Address Books
            $addressBooks = ExchangeAddressBook::where('exchange_mailbox_id', '=', $mailbox->id)->get();
            foreach($addressBooks AS $addressBook)
            {
                echo $mailbox->email . ' >> Sync '.$addressBook->name.' Address Book' . PHP_EOL;
                $mailbox->syncContacts($addressBook);
            }
        }
    }


}
