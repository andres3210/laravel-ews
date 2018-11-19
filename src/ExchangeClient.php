<?php

namespace andres3210\laraews;

use andres3210\laraews\models\ExchangeSubscription;

use \jamesiarmes\PhpEws\Client;

use jamesiarmes\PhpEws\Enumeration\DisposalType;
use jamesiarmes\PhpEws\Request\DeleteItemType;
use \jamesiarmes\PhpEws\Request\FindFolderType;
use \jamesiarmes\PhpEws\Request\FindItemType;
use \jamesiarmes\PhpEws\Request\GetInboxRulesRequestType;
use \jamesiarmes\PhpEws\Request\GetItemType;
use \jamesiarmes\PhpEws\Request\SubscribeType;

use \jamesiarmes\PhpEws\Enumeration\MapiPropertyTypeType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Enumeration\UnindexedFieldURIType;
use \jamesiarmes\PhpEws\Enumeration\ContainmentComparisonType;
use \jamesiarmes\PhpEws\Enumeration\ContainmentModeType;
use \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType;

use \jamesiarmes\PhpEws\Type\AndType;
use \jamesiarmes\PhpEws\Type\ConnectingSIDType;
use \jamesiarmes\PhpEws\Type\ConstantValueType;
use \jamesiarmes\PhpEws\Type\ContainsExpressionType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ExchangeImpersonationType;
use \jamesiarmes\PhpEws\Type\FieldURIOrConstantType;
use \jamesiarmes\PhpEws\Type\FolderResponseShapeType;
use \jamesiarmes\PhpEws\Type\IsGreaterThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\IsLessThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\PathToExtendedFieldType;
use \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType;
use \jamesiarmes\PhpEws\Type\PushSubscriptionRequestType;
use \jamesiarmes\PhpEws\Type\RestrictionType;
use \jamesiarmes\PhpEws\Type\FolderIdType;
use jamesiarmes\PhpEws\Type\SendNotificationResultType;
use \jamesiarmes\PhpEws\Type\StreamingSubscriptionRequest;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfNotificationEventTypesType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfPathsToElementType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfSubscriptionIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use Mockery\CountValidator\Exception;

class ExchangeClient extends Client {

    public static $DistinguishedFolderNames = [
        'INBOX',
        'SENT',
        'OUTBOX',
        'DELETED',
        'DRAFTS',
        'JOURNAL',
        'NOTES',
        'JUNK',
        'VOICE_MAIL',
        'ROOT'
    ];

    public $distinguishedFolders = [
        'Inbox'                     => null,
        'Deleted Items'             => null,
        'Drafts'                    => null,
        'Journal'                   => null,
        'Notes'                     => null,
        'Outbox'                    => null,
        'Sent Items'                => null,
        'Message Folder'            => null,
        'Junk Email'                => null,
        'Voice Mail'                => null,
        'Working Set'               => null,
        'Top of Information Store'  => null
    ];


    public function __construct($server = null, $username = null, $password = null, $version = null)
    {
        // Set the object properties.
        $this->setServer($server        ? $server   : env('EXCHANGE_HOST'));
        $this->setUsername($username    ? $username : env('EXCHANGE_USER'));
        $this->setPassword($password    ? $password : env('EXCHANGE_PASSWORD'));
        $this->setVersion($version      ? $version  : env('EXCHANGE_VERSION', self::VERSION_2013));

        $impersonateEmail = env('EXCHANGE_IMPERSONATE_EMAIL', false);
        if( $impersonateEmail )
            $this->setImpersonationByEmail($impersonateEmail);
    }


    public function setImpersonationByEmail($email)
    {
        $ei = new ExchangeImpersonationType();
        $sid = new ConnectingSIDType();
        $sid->PrimarySmtpAddress = $email;
        $ei->ConnectingSID = $sid;
        $this->setImpersonation($ei);
    }


    public function getInbox()
    {
        return $this->getFolderItems('INBOX');
    }


    public function getFolderItems($folder = null, $search = [])
    {
        if($folder == null)
            return [];

        $request = new \StdClass;

        // Return all message properties.
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;


        $end_date = new \DateTime('now');
        $start_date = new \DateTime('now');
        $start_date->modify('-1 day');

        if( count($search) > 0 ){
            if( isset($search['dateFrom']) && get_class($search['dateFrom']) == 'DateTime' )
                $start_date = $search['dateFrom'];

            if( isset($search['dateTo']) && get_class($search['dateTo']) == 'DateTime' )
                $end_date = $search['dateTo'];

        }


        // Build the start date restriction.
        $greater_than = new IsGreaterThanOrEqualToType();
        $greater_than->FieldURI = new PathToUnindexedFieldType();
        $greater_than->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_DATE_TIME_RECEIVED;
        $greater_than->FieldURIOrConstant = new FieldURIOrConstantType();
        $greater_than->FieldURIOrConstant->Constant = new ConstantValueType();
        $greater_than->FieldURIOrConstant->Constant->Value = $start_date->format('c');


        // Build the end date restriction;
        $less_than = new IsLessThanOrEqualToType();
        $less_than->FieldURI = new PathToUnindexedFieldType();
        $less_than->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_DATE_TIME_RECEIVED;
        $less_than->FieldURIOrConstant = new FieldURIOrConstantType();
        $less_than->FieldURIOrConstant->Constant = new ConstantValueType();
        $less_than->FieldURIOrConstant->Constant->Value = $end_date->format('c');


        // Build the restriction.
        $request->Restriction = new RestrictionType();
        $request->Restriction->And = new AndType();
        $request->Restriction->And->IsGreaterThanOrEqualTo = $greater_than;
        $request->Restriction->And->IsLessThanOrEqualTo = $less_than;

        // Search recursively.
        $request->Traversal = FolderQueryTraversalType::SHALLOW;


        // Append Folder ID Request
        if( is_string($folder) && in_array($folder, self::$DistinguishedFolderNames))
        {
            // Get mailbox link
            $distinguishedFolderId = new DistinguishedFolderIdType();
            $distinguishedFolderId->Id = constant('\jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType' . '::' . $folder);

            $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $request->ParentFolderIds->DistinguishedFolderId[] = $distinguishedFolderId;
        }
        else
        {
            if( is_string($folder) )
            {
                $folderIdType = new FolderIdType();
                $folderIdType->Id = $folder;
                $request->ParentFolderIds->FolderId[] = $folderIdType;
            }
            elseif( is_array($folder) )
                foreach($folder AS $_id)
                {
                    $folderIdType = new FolderIdType();
                    $folderIdType->Id = $_id;
                    $request->ParentFolderIds->FolderId[] = $folderIdType;
                }
        }

        // Send Request
        $response = $this->FindItem($request);


        // Iterate over the results, printing any error messages or message subjects.
        $response_messages = $response->ResponseMessages->FindItemResponseMessage;

        //print_r($response_messages); exit();

        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS)
                throw( new Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));


            // Iterate over the messages that were found, printing the subject for each.
            $items = $response_message->RootFolder->Items->Message;
            $emails = [];
            foreach ($items as $item)
                $emails[] = (object)[
                    'Subject' => $item->Subject,
                    'ItemId' => $item->ItemId->Id,
                    'DisplayTo' => $item->DisplayTo,
                    'DateTimeReceived' => $item->DateTimeReceived
                ];

            return $emails;
        }

        return [];
    }


    public function getEmailItem($id){

        $request = new GetItemType();
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        //$request->ItemShape->BaseShape = DefaultShapeNamesType::DEFAULT_PROPERTIES;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        if( is_array($id) )
            foreach($id AS $_id){
                $item = new ItemIdType();

                // CV Office Exchange was workign with URL-Econded IDs
                //$item->Id = urldecode($_id);

                // Office 365 only works sending plain ItemId
                $item->Id = $_id;

                $request->ItemIds->ItemId[] = $item;
            }
        else{
            $item = new ItemIdType();

            // CV Office Exchange was workign with URL-Econded IDs
            //$item->Id = urldecode($id);

            // Office 365 only works sending plain ItemId
            $item->Id = $id;

            $request->ItemIds->ItemId[] = $item;
        }

        $response = $this->GetItem($request);

        $buffer = []; $errors = [];
        foreach($response->ResponseMessages->GetItemResponseMessage AS $item){

            if( $item->ResponseClass != 'Success' ){
                $errors[] = $item->ResponseCode . ' :: ' .$item->MessageText;
                continue;
            }

            if( isset($item->Items) && isset($item->Items->Message) && isset($item->Items->Message[0]) ){
                $email = $item->Items->Message[0];

                //print_r($email); exit();

                $emailObj = (object)[
                    'ItemId' => isset($email->ItemId->Id) ? $email->ItemId->Id : null,
                    'Subject' => isset($email->Subject) ? $email->Subject : null,
                    'From' =>  isset($email->From) ? $email->From->Mailbox->EmailAddress : null,
                    'To' => [],
                    'Cc' => [],
                    'Bcc' => [],
                    'DateTimeCreated' => isset($email->DateTimeCreated) ? $email->DateTimeCreated : null,
                    'DateTimeSent'    => isset($email->DateTimeSent) ? $email->DateTimeSent : null,
                    'Body' => $email->Body->_,
                    'ConversationId' => $email->ConversationId->Id, // ALL_PROPERTIES
                    'ParentFolderId' => $email->ParentFolderId->Id, // ALL_PROPERTIES
                ];

                if( isset($email->ToRecipients) && isset($email->ToRecipients->Mailbox) )
                    foreach( $email->ToRecipients->Mailbox AS $recipient)
                        $emailObj->To[] = $recipient->EmailAddress;

                if( isset($email->CcRecipients) && isset($email->CcRecipients->Mailbox) )
                    foreach( $email->CcRecipients->Mailbox AS $recipient)
                        $emailObj->Cc[] = $recipient->EmailAddress;

                if( isset($email->BccRecipients) && isset($email->BccRecipients->Mailbox) )
                    foreach( $email->BccRecipients->Mailbox AS $recipient)
                        $emailObj->Bcc[] = $recipient->EmailAddress;

                $buffer[] = $emailObj;
            }
        }

        if( count($errors) > 0 )
            throw( new Exception('Exchange EWS GetItems Error >> ' . print_r($errors, 1)) );

        return $buffer;
    }


    public function deleteEmailItem($id)
    {
        $request = new DeleteItemType();
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        if( is_array($id) )
            foreach($id AS $_id){
                $item = new ItemIdType();
                $item->Id = $_id;
                $request->ItemIds->ItemId[] = $item;
            }
        else{
            $item = new ItemIdType();
            $item->Id = $id;
            $request->ItemIds->ItemId[] = $item;
        }

        $request->DeleteType = new DisposalType();
        $request->DeleteType->_ = DisposalType::MOVE_TO_DELETED_ITEMS;

        $response = $this->DeleteItem($request);

        if( isset($response->ResponseMessages) && isset($response->ResponseMessages->DeleteItemResponseMessage) )
            return $response->ResponseMessages->DeleteItemResponseMessage;

        return false;
    }


    public function subscribePushNotifications($callbackUri, $callback)
    {
        $request = new SubscribeType();

        $eventTypes = new NonEmptyArrayOfNotificationEventTypesType();
        $eventTypes->EventType = [
            'CreatedEvent',
            'DeletedEvent',
            'ModifiedEvent',
            'NewMailEvent',
            'MovedEvent',
            'CopiedEvent',
            'FreeBusyChangedEvent'
        ];

        // Get Main Folder ID
        $folder_id = new NonEmptyArrayOfBaseFolderIdsType();
        $folder_id->DistinguishedFolderId = new DistinguishedFolderIdType();
        $folder_id->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;

        // Set Subscription Type
        $pushSubscription = new PushSubscriptionRequestType();
        $pushSubscription->EventTypes = $eventTypes;
        $pushSubscription->StatusFrequency = 1;
        $pushSubscription->URL = $callbackUri;
        $pushSubscription->SubscribeToAllFolders = true;
        //$pushSubscription->FolderIds = $folder_id;
        $request->PushSubscriptionRequest = $pushSubscription;

        $response = $this->Subscribe($request);

        if( isset($response->ResponseMessages->SubscribeResponseMessage) ){
            $subscription = $response->ResponseMessages->SubscribeResponseMessage[0];
            if( $subscription->ResponseClass == 'Success'){

                // Register Subscription
                $laraewsSubscription = ExchangeSubscription::create([
                    'subscription_id'   => $subscription->SubscriptionId,
                    'callback'          => $callback,
                    'expire_on'         => null //\Carbon\Carbon::now()->modify('+1 hour')
                ]);

                return $laraewsSubscription;
            }

        }

        $subscriptionResponse = $response->ResponseMessages->SubscribeResponseMessage[0];
        return [
            'error' => $subscriptionResponse->ResponseClass . '::' . $subscriptionResponse->MessageText
        ];
    }


    public static function getNotificationSoapServer()
    {
        $server = new \SoapServer(\andres3210\laraews\NotificationRequest::getNotificationsWsdl());
        $server->setClass('andres3210\laraews\NotificationRequest');
        return $server;
    }

}