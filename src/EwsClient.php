<?php
/**
 * Created by PhpStorm.
 * User: jona
 * Date: 18-11-08
 * Time: 3:43 PM
 */

namespace andres3210\laraews;



use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfNotificationEventTypesType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfPathsToElementType;
use jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfSubscriptionIdsType;
use \jamesiarmes\PhpEws\Client;
use \jamesiarmes\PhpEws\Enumeration\MapiPropertyTypeType;
use jamesiarmes\PhpEws\Request\FindFolderType;
use \jamesiarmes\PhpEws\Request\FindItemType;

use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;

use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Enumeration\UnindexedFieldURIType;
use \jamesiarmes\PhpEws\Enumeration\ContainmentComparisonType;
use \jamesiarmes\PhpEws\Enumeration\ContainmentModeType;

use \jamesiarmes\PhpEws\Request\GetItemType;
use jamesiarmes\PhpEws\Request\SubscribeType;
use \jamesiarmes\PhpEws\Type\AndType;
use \jamesiarmes\PhpEws\Type\ConnectingSIDType;
use \jamesiarmes\PhpEws\Type\ConstantValueType;
use jamesiarmes\PhpEws\Type\ContainsExpressionType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ExchangeImpersonationType;
use \jamesiarmes\PhpEws\Type\FieldURIOrConstantType;
use jamesiarmes\PhpEws\Type\FolderResponseShapeType;
use \jamesiarmes\PhpEws\Type\IsGreaterThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\IsLessThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\PathToExtendedFieldType;
use \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType;
use jamesiarmes\PhpEws\Type\PushSubscriptionRequestType;
use \jamesiarmes\PhpEws\Type\RestrictionType;

use \jamesiarmes\PhpEws\Request\GetInboxRulesRequestType;

use \jamesiarmes\PhpEws\Type\FolderIdType;
use \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType;
use jamesiarmes\PhpEws\Type\StreamingSubscriptionRequest;

class EwsClient {

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

    public function __construct($server, $username, $password, $version)
    {
        $this->client = new Client($server, $username, $password, $version);
        $this->client->setTimezone('Eastern Standard Time');
    }

    public function impersonate($email)
    {
        $ei = new ExchangeImpersonationType();
        $sid = new ConnectingSIDType();
        $sid->PrimarySmtpAddress = $email;
        $ei->ConnectingSID = $sid;
        $this->client->setImpersonation($ei);
    }

    public function getInbox($_folder_id = null)
    {
        $start_date = new \DateTime('now');
        $start_date->modify('-1 hour');

        $end_date = new \DateTime('now');

        //$start_date = new \DateTime('2018-11-06 00:00:00');
        //$end_date = new \DateTime('2018-11-09 23:59:59');

        $start_date = new \DateTime('2018-11-10 00:00:00');
        $end_date = new \DateTime('2018-11-13 23:59:59');


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
        $request = new \StdClass;
        //$request = new FindItemType();

        $request->Restriction = new RestrictionType();
        $request->Restriction->And = new AndType();
        $request->Restriction->And->IsGreaterThanOrEqualTo = $greater_than;
        $request->Restriction->And->IsLessThanOrEqualTo = $less_than;


        if( $_folder_id != null && false){
            // Search recursively.
            $request->Traversal = FolderQueryTraversalType::SHALLOW;

            // if you know exact folder id, then use this piece of code instead.
            if( is_string($_folder_id) ){
                $folderIdType = new FolderIdType();
                $folderIdType->Id = $_folder_id;
                $request->ParentFolderIds->FolderId[] = $folderIdType;
            }
            // If List of folders Ids is passed, append all to the search query.
            elseif( is_array($_folder_id) ){
                foreach( $_folder_id AS $_id){
                    $folderIdType = new FolderIdType();
                    $folderIdType->Id = $_id;
                    $request->ParentFolderIds->FolderId[] = $folderIdType;
                }
            }
        }
        else{

            // Get mailbox link
            $distinguishedFolderId = new DistinguishedFolderIdType();
            //$distinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;
            $distinguishedFolderId->Id = DistinguishedFolderIdNameType::SENT;

            $request->Traversal = FolderQueryTraversalType::SHALLOW;
            $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
            $request->ParentFolderIds->DistinguishedFolderId[] = $distinguishedFolderId;
        }

        // Return all message properties.
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;


        $response = $this->client->FindItem($request);


        // Iterate over the results, printing any error messages or message subjects.
        $response_messages = $response->ResponseMessages->FindItemResponseMessage;
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                $code = $response_message->ResponseCode;
                $message = $response_message->MessageText;
                /*fwrite(
                    STDERR,
                    "Failed to search for messages with \"$code: $message\"\n"
                );*/
                return -1;
            }

            // Iterate over the messages that were found, printing the subject for each.
            $items = $response_message->RootFolder->Items->Message;
            $emails = [];
            $leadEmails = [];
            foreach ($items as $item) {
                //print_r($item); exit();

                $emails[] = [
                    'subject' => $item->Subject,
                    'id' => $item->ItemId->Id,
                    'DisplayTo' => $item->DisplayTo,
                    'DateTimeReceived' => $item->DateTimeReceived
                ];
            }

            return $emails;
        }

        return -1;
    }

    public function getInboxRules(){
        $response = $this->client->GetInboxRules(new GetInboxRulesRequestType());

        if( isset($response->InboxRules) && isset($response->InboxRules->Rule) ){

            $rulesArr = [];
            foreach ( $response->InboxRules->Rule AS $rule ){
                //print_r($rule); exit();

                $conditions = [];
                if( isset($rule->Conditions) )
                    foreach( $rule->Conditions AS $condition => $value){
                        if( $value != null ){
                            switch($condition){
                                case 'SentToAddresses':
                                    $conditions[$condition] = [];
                                    foreach($value->Address AS $address){
                                        $conditions[$condition][] = $address->EmailAddress;
                                    }
                                    break;
                            }
                        }
                    }

                $actions = [];
                if( isset($rule->Actions) )
                    foreach( $rule->Actions AS $action => $value ){
                        if($value != null){
                            switch($action){
                                case 'MoveToFolder':
                                    $actions[$action] = $value->FolderId->Id;
                                    break;
                            }

                        }
                    }

                $rule = (object)[
                    'DisplayName'   => $rule->DisplayName,
                    'IsEnabled'     => $rule->IsEnabled,
                    'Conditions'    => $conditions,
                    'Actions'       => $actions
                ];

                $rulesArr[] = $rule;
            }

            return $rulesArr;
        }

        return [];
    }


    public function subscribeNotifications()
    {
        // how to register the socket
        // https://github.com/jamesiarmes/php-ews/issues/280

        /*$susbcriptionId = 'GQB1bml2ZXJzZS5jYW5hZGF2aXNhLmxvY2FsEAAAAMvBlI/8JP5CjZHaByn0/mkvLdfspknWCBAAAAAvSRjKaRryT4S2lWaVflrW';
        if( $susbcriptionId ){
            $streamRequest = new \stdClass();
            $subs_ids = new NonEmptyArrayOfSubscriptionIdsType();
            $subs_ids->SubscriptionId = $susbcriptionId;
            $streamRequest->SubscriptionIds = $subs_ids;
            $streamRequest->ConnectionTimeout = 1;
            $response2 = $this->client->GetStreamingEvents($streamRequest);

            print_r($response2); exit();
        }*/

        $request = new SubscribeType();

        $eventTypes = new NonEmptyArrayOfNotificationEventTypesType();
        $eventTypes->EventType = [
            'CreatedEvent',
            //'DeletedEvent',
            //'ModifiedEvent',
            'NewMailEvent',
            //'MovedEvent',
            //'CopiedEvent',
            //'FreeBusyChangedEvent'
        ];

        // Get Main Folder ID
        $folder_id = new NonEmptyArrayOfBaseFolderIdsType();
        $folder_id->DistinguishedFolderId = new DistinguishedFolderIdType();
        $folder_id->DistinguishedFolderId->Id = DistinguishedFolderIdNameType::INBOX;

        $mode = 'push-notification';
        switch($mode){
            case 'streaming':
                $streamSubs = new StreamingSubscriptionRequest();
                $streamSubs->EventTypes = $eventTypes;
                $streamSubs->SubscribeToAllFolders = true;
                $streamSubs->FolderIds = $folder_id;
                $request->StreamingSubscriptionRequest = $streamSubs;
                break;

            case 'push-notification':
                $pushSubscription = new PushSubscriptionRequestType();
                $pushSubscription->EventTypes = $eventTypes;
                $pushSubscription->SubscribeToAllFolders = true;
                $pushSubscription->StatusFrequency = 1;
                $pushSubscription->URL = 'http://48b24309.ngrok.io/api/ews/notification';
                //$pushSubscription->FolderIds = $folder_id;
                $request->PushSubscriptionRequest = $pushSubscription;
                break;
        }

        $response = $this->client->Subscribe($request);

        if( $mode == 'streaming' ){
            // Not working yet
            // how to register the socket
            // https://github.com/jamesiarmes/php-ews/issues/280
            if( isset($response->ResponseMessages) && isset($response->ResponseMessages->SubscribeResponseMessage) ){
                if( count($response->ResponseMessages->SubscribeResponseMessage) > 0 ){
                    $subscriptionResponse = $response->ResponseMessages->SubscribeResponseMessage[0];

                    $streamRequest = new \stdClass();
                    $subs_ids = new NonEmptyArrayOfSubscriptionIdsType();
                    $subs_ids->SubscriptionId = $subscriptionResponse->SubscriptionId;
                    $streamRequest->SubscriptionIds = $subs_ids;
                    $streamRequest->ConnectionTimeout = 10;
                    $response2 = $this->client->GetStreamingEvents($streamRequest);

                    print_r($response2); exit();
                }
            }
        }

        return $response;

    }


    public function listFolders($search = null){

        // Build the request.
        $request = new FindFolderType();
        $request->FolderShape = new FolderResponseShapeType();
        $request->FolderShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->Restriction = new RestrictionType();

        // Search recursively.
        $request->Traversal = FolderQueryTraversalType::DEEP;

        // Search within the root folder. Combined with the traversal set above, this
        // should search through all folders in the user's mailbox.
        $parent = new DistinguishedFolderIdType();
        $parent->Id = DistinguishedFolderIdNameType::ROOT;
        $request->ParentFolderIds->DistinguishedFolderId[] = $parent;

        /*if( $search != null ){
            // Build the restriction that will search for folders containing "Cal".
            $contains = new ContainsExpressionType();
            $contains->FieldURI = new PathToUnindexedFieldType();
            $contains->FieldURI->FieldURI = UnindexedFieldURIType::FOLDER_DISPLAY_NAME;
            $contains->Constant = new ConstantValueType();
            $contains->Constant->Value = $search;
            $contains->ContainmentComparison = ContainmentComparisonType::EXACT;
            $contains->ContainmentMode = ContainmentModeType::SUBSTRING;
            $request->Restriction->Contains = $contains;
        }*/

        $response = $this->client->FindFolder($request);

        // Iterate over the results, printing any error messages or folder names and
        // ids.
        $response_messages = $response->ResponseMessages->FindFolderResponseMessage;



        $folderStructure = [];
        $allFoldersArr = [];
        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                $code = $response_message->ResponseCode;
                $message = $response_message->MessageText;
                return false;
            }

            // The folders could be of any type, so combine all of them into a single
            // array to iterate over them.
            $folders = array_merge(
                $response_message->RootFolder->Folders->CalendarFolder,
                $response_message->RootFolder->Folders->ContactsFolder,
                $response_message->RootFolder->Folders->Folder,
                $response_message->RootFolder->Folders->SearchFolder,
                $response_message->RootFolder->Folders->TasksFolder
            );

            // Iterate over the found folders.
            $inboxId = null;
            foreach ($folders as $folder) {
                $tmp = (object)[
                    'id' => $folder->FolderId->Id,
                    'name' => $folder->DisplayName,
                    'ParentFolderId' => $folder->ParentFolderId != null ? $folder->ParentFolderId->Id : null
                ];

                if( in_array($folder->DisplayName, array_keys($this->distinguishedFolders)) ){
                    $folderStructure[] = $tmp;
                    $this->distinguishedFolders[$folder->DisplayName] = $folder->FolderId->Id;
                }

                $allFoldersArr[] = $tmp;
            }
        }

        for( $i = 0; $i < count($folderStructure); $i++)
            $this->appendChildFolders($folderStructure[$i], $allFoldersArr);

        if($search != null ){
            return $this->extractFolderFromStructure($search, $folderStructure);
        }

        return $folderStructure;
    }

    private function appendChildFolders(&$nodeFolder, $allFolders)
    {
        if( !isset($nodeFolder->children) )
            $nodeFolder->children = [];

        foreach($allFolders AS $folder){
            if( in_array($folder->id, array_values($this->distinguishedFolders)) )
                continue;

            if( $nodeFolder->id == $folder->ParentFolderId)
                $nodeFolder->children[] = $folder;
        }


        // Recursive method
        if( count($nodeFolder->children ) > 0 )
            foreach( $nodeFolder->children AS &$childFolder )
                self::appendChildFolders($childFolder, $allFolders);
    }


    private function extractFolderFromStructure($search, $folderStructure){
        // 1st level search
        foreach($folderStructure AS $folder){
            if( isset($folder->name) ){
                //echo $folder->name . PHP_EOL;
                if( $folder->name == $search )
                    return $folder;
            }
        }

        foreach($folderStructure AS $folder)
            if( isset($folder->children) && count($folder->children) > 0){
                //echo $folder->name . ' >> children >> '.PHP_EOL;
                $tmp = $this->extractFolderFromStructure($search, $folder->children);

                if($tmp != null)
                    return $tmp;
            }

        return null;
    }


    public static function extractSubFolderIds($folder, &$ids = [])
    {
        if( $folder ){
            $ids[] = $folder->id;

            if( isset($folder->children) && count($folder->children) > 0)
                foreach($folder->children AS $subFolder )
                    self::extractSubFolderIds($subFolder, $ids);
        }

        return $ids;
    }

    public function getItem($id)
    {
        $request = new GetItemType();
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();

        $item = new ItemIdType();
        $item->Id = urldecode($id);
        $request->ItemIds->ItemId[] = $item;

        $response = $this->client->GetItem($request);
        return $response->ResponseMessages->GetItemResponseMessage[0]->Items->Message[0];//->Body->_;
    }

}
