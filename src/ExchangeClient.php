<?php

namespace andres3210\laraews;

use andres3210\laraews\models\ExchangeSubscription;
use Mockery\CountValidator\Exception;


use \jamesiarmes\PhpEws\Client;

use \jamesiarmes\PhpEws\Request\DeleteItemType;
use \jamesiarmes\PhpEws\Request\FindFolderType;
use \jamesiarmes\PhpEws\Request\GetItemType;
use \jamesiarmes\PhpEws\Request\MoveItemType;
use \jamesiarmes\PhpEws\Request\CopyItemType;
use \jamesiarmes\PhpEws\Request\SubscribeType;
use \jamesiarmes\PhpEws\Request\FindItemType;
use \jamesiarmes\PhpEws\Request\CreateItemType;

use \jamesiarmes\PhpEws\Enumeration\DisposalType;
use \jamesiarmes\PhpEws\Enumeration\DefaultShapeNamesType;
use \jamesiarmes\PhpEws\Enumeration\DistinguishedFolderIdNameType;
use \jamesiarmes\PhpEws\Enumeration\ResponseClassType;
use \jamesiarmes\PhpEws\Enumeration\UnindexedFieldURIType;
use \jamesiarmes\PhpEws\Enumeration\FolderQueryTraversalType;
use \jamesiarmes\PhpEws\Enumeration\ItemQueryTraversalType;
use \jamesiarmes\PhpEws\Enumeration\BodyTypeType;
use \jamesiarmes\PhpEws\Enumeration\ItemClassType;
use \jamesiarmes\PhpEws\Enumeration\CalendarItemCreateOrDeleteOperationType;
use \jamesiarmes\PhpEws\Enumeration\RoutingType;
use \jamesiarmes\PhpEws\Enumeration\MessageDispositionType;

use \jamesiarmes\PhpEws\Type\AndType;
use \jamesiarmes\PhpEws\Type\ConnectingSIDType;
use \jamesiarmes\PhpEws\Type\ConstantValueType;
use \jamesiarmes\PhpEws\Type\DistinguishedFolderIdType;
use \jamesiarmes\PhpEws\Type\ExchangeImpersonationType;
use \jamesiarmes\PhpEws\Type\FieldURIOrConstantType;
use \jamesiarmes\PhpEws\Type\FolderResponseShapeType;
use \jamesiarmes\PhpEws\Type\IsGreaterThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\IsLessThanOrEqualToType;
use \jamesiarmes\PhpEws\Type\ItemIdType;
use \jamesiarmes\PhpEws\Type\ItemResponseShapeType;
use \jamesiarmes\PhpEws\Type\PathToUnindexedFieldType;
use \jamesiarmes\PhpEws\Type\PushSubscriptionRequestType;
use \jamesiarmes\PhpEws\Type\RestrictionType;
use \jamesiarmes\PhpEws\Type\FolderIdType;
use \jamesiarmes\PhpEws\Type\IndexedPageViewType;
use \jamesiarmes\PhpEws\Type\FieldOrderType;
use \jamesiarmes\PhpEws\Type\CalendarViewType;
use \jamesiarmes\PhpEws\Type\CalendarItemType;
use \jamesiarmes\PhpEws\Type\BodyType;
use \jamesiarmes\PhpEws\Type\TimeZoneDefinitionType;
use \jamesiarmes\PhpEws\Type\AttendeeType;
use \jamesiarmes\PhpEws\Type\EmailAddressType;
use \jamesiarmes\PhpEws\Type\MessageType;
use \jamesiarmes\PhpEws\Type\SingleRecipientType;



use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseItemIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfNotificationEventTypesType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfBaseFolderIdsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfFieldOrdersType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAllItemsType;
use \jamesiarmes\PhpEws\ArrayType\NonEmptyArrayOfAttendeesType;
use \jamesiarmes\PhpEws\ArrayType\ArrayOfRecipientsType;



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

    private $impersonationEmail = '';

    private $ews_connection = 'default';


    public function __construct($server = null, $username = null, $password = null, $version = null, $env = null)
    {
        $config = config('exchange.connections.'.config('exchange.default'));

        $this->setServer($server        ? $server   : $config['host']);
        $this->setUsername($username    ? $username : $config['username']);
        $this->setPassword($password    ? $password : $config['password']);
        $this->setVersion($version      ? $version  : constant('self::'.$config['version']) );

        if( !$config['ssl_verify'] )
            $this->setCurlOptions([
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false
            ]);
    }


    public static function getConnection($connection = null)
    {
        $config = $connection != null ?
            config('exchange.connections.'.$connection) : config('exchange.connections.'.config('exchange.default'));

        if( $config != null ){
            $version = isset(self::$config['version']) ? constant('self::'.$config['version']) : self::VERSION_2013;
            $conn = new self($config['host'], $config['username'], $config['password'], $version);
            if( !$config['ssl_verify'] )
                $conn->setCurlOptions([
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false
                ]);
            $conn->ews_connection = $connection;
            return $conn;
        }

        return null;
    }


    public function getServer()
    {
        return $this->server;
    }


    public function getUsername()
    {
        return $this->username;
    }


    public function setImpersonationByEmail($email)
    {
        if($email == $this->impersonationEmail)
            return;

        $sid = new ConnectingSIDType();
        $sid->PrimarySmtpAddress = $email;
        $ei = new ExchangeImpersonationType();
        $ei->ConnectingSID = $sid;
        $this->setImpersonation($ei);

        $this->impersonationEmail = $email;
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

            // Build the restriction.
            $request->Restriction = new RestrictionType();
            $enableAndCondition = false;
            if( count(array_intersect( array_keys($search), ['dateFrom', 'dateTo'])) > 1 ){
                $request->Restriction->And = new AndType();
                $enableAndCondition = true;
            }



            if( isset($search['dateFrom']) && get_class($search['dateFrom']) == 'DateTime' ){
                $start_date = $search['dateFrom'];

                // Build the start date restriction.
                $greater_than = new IsGreaterThanOrEqualToType();
                $greater_than->FieldURI = new PathToUnindexedFieldType();
                $greater_than->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_DATE_TIME_RECEIVED;
                $greater_than->FieldURIOrConstant = new FieldURIOrConstantType();
                $greater_than->FieldURIOrConstant->Constant = new ConstantValueType();
                $greater_than->FieldURIOrConstant->Constant->Value = $start_date->format('c');

                if( $enableAndCondition )
                    $request->Restriction->And->IsGreaterThanOrEqualTo = $greater_than;
                else
                    $request->Restriction->IsGreaterThanOrEqualTo = $greater_than;
            }


            if( isset($search['dateTo']) && get_class($search['dateTo']) == 'DateTime' ){
                $end_date = $search['dateTo'];

                // Build the end date restriction;
                $less_than = new IsLessThanOrEqualToType();
                $less_than->FieldURI = new PathToUnindexedFieldType();
                $less_than->FieldURI->FieldURI = UnindexedFieldURIType::ITEM_DATE_TIME_RECEIVED;
                $less_than->FieldURIOrConstant = new FieldURIOrConstantType();
                $less_than->FieldURIOrConstant->Constant = new ConstantValueType();
                $less_than->FieldURIOrConstant->Constant->Value = $end_date->format('c');

                if( $enableAndCondition )
                    $request->Restriction->And->IsLessThanOrEqualTo = $less_than;
                else
                    $request->Restriction->IsLessThanOrEqualTo = $less_than;
            }
        }

        //print_r($request); exit();

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

        // Limits the number of items retrieved
        // Can not exceed 1000 as its the EWS default limit
        if( isset($search['limit']) ){
            $request->IndexedPageItemView = new IndexedPageViewType();
            $request->IndexedPageItemView->BasePoint = "Beginning"; // Newest batch
            //$request->IndexedPageItemView->BasePoint = "End";
            $request->IndexedPageItemView->Offset = 0;
            $request->IndexedPageItemView->MaxEntriesReturnedSpecified = true;
            $request->IndexedPageItemView->MaxEntriesReturned = $search['limit'];
        }

        // sort order
        $order = new FieldOrderType();
        $order->Order = 'Descending';

        $order->FieldURI = new PathToUnindexedFieldType();
        $order->FieldURI->FieldURI  = 'item:DateTimeReceived';

        $request->SortOrder = new NonEmptyArrayOfFieldOrdersType();
        $request->SortOrder->FieldOrder = array();
        $request->SortOrder->FieldOrder[] = $order;


        // Send Request
        $response = $this->FindItem($request);


        // Iterate over the results, printing any error messages or message subjects.
        $response_messages = $response->ResponseMessages->FindItemResponseMessage;

        //print_r($response_messages); exit();

        foreach ($response_messages as $response_message) {
            // Make sure the request succeeded.
            if ($response_message->ResponseClass != ResponseClassType::SUCCESS){
                //print_r($response_message);
                throw( new Exception($response_message->ResponseCode . ' - ' . $response_message->MessageText));
            }



            // Iterate over the messages that were found, printing the subject for each.
            $items = $response_message->RootFolder->Items->Message;
            $emails = [];
            foreach ($items as $item) {
                //print_r($item); exit();
                $emails[] = (object)[
                    'Subject' => $item->Subject,
                    'ItemId' => $item->ItemId->Id,
                    'From' => (isset($item->From) && isset($item->From->Mailbox) ) ? $item->From->Mailbox->Name : '',
                    'FromEmail' => (isset($item->From) && isset($item->From->Mailbox) && isset($item->From->Mailbox->EmailAddress) ) ? $item->From->Mailbox->EmailAddress : '',
                    'DisplayTo' => $item->DisplayTo,
                    'DateTimeReceived' => $item->DateTimeReceived
                ];
            }

            return $emails;
        }

        return [];
    }





    public function createEmailItem($email)
    {

        // Build the request,
        $request = new CreateItemType();
        $request->Items = new NonEmptyArrayOfAllItemsType();

        // Save the message, but do not send it.
        $request->MessageDisposition = MessageDispositionType::SEND_ONLY;

        // Create the message.
        $message = new MessageType();
        $message->Subject = $email['subject'];
        $message->ToRecipients = new ArrayOfRecipientsType();

        // Set the sender.
        $message->From = new SingleRecipientType();
        $message->From->Mailbox = new EmailAddressType();
        $message->From->Mailbox->EmailAddress = $this->getUsername();

        // Set the recipient.
        $recipient = new EmailAddressType();
        $recipient->Name = isset($email['to']['name']) ? $email['to']['name'] : null;
        $recipient->EmailAddress = $email['to']['email'];
        $message->ToRecipients->Mailbox[] = $recipient;

        // Set the message body.
        $message->Body = new BodyType();
        $message->Body->BodyType = BodyTypeType::TEXT;
        $message->Body->_ = $email['body'];


        // Add the message to the request.
        $request->Items->Message[] = $message;
        $response = $this->CreateItem($request);

        // Iterate over the results, printing any error messages or message ids.
        if( isset($response->ResponseMessages) && isset($response->ResponseMessages->CreateItemResponseMessage) )
        {
            $response_messages = $response->ResponseMessages->CreateItemResponseMessage;
            foreach ($response_messages as $response_message)
            {
                // Make sure the request succeeded.
                if ($response_message->ResponseClass != ResponseClassType::SUCCESS) {
                    $code = $response_message->ResponseCode;
                    $message = $response_message->MessageText;
                    //fwrite(STDERR, "Message failed to create with \"$code: $message\"\n");
                    echo "Message failed to create with \"$code: $message\"\n";
                    continue;
                }
                // Iterate over the created messages, printing the id for each.
                foreach ($response_message->Items->Message as $item) {
                    $output = '- Id: ' . $item->ItemId->Id . "\n";
                    $output .= '- Change key: ' . $item->ItemId->ChangeKey . "\n";
                    //fwrite(STDOUT, "Message created successfully.\n$output");
                    echo  "Message created successfully.\n$output";
                }
            }

            //echo print_r($response_messages[0]);

            if( $response_messages[0]->ResponseClass == 'Success' )
                return true;
        }

        return false;
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
                // $item->Id = urldecode($_id);

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

                $headers = [];
                if( isset($email->InternetMessageHeaders) && isset($email->InternetMessageHeaders->InternetMessageHeader) ){
                    foreach($email->InternetMessageHeaders->InternetMessageHeader AS $header){
                        if( !isset($headers[$header->HeaderName]) ) $headers[$header->HeaderName] = [];

                        // Add grouped header
                        $headers[$header->HeaderName][] = $header->_;
                    }
                }

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
                    'InternetMessageId' => $email->InternetMessageId,
                    'ParentFolderId' => $email->ParentFolderId->Id, // ALL_PROPERTIES
                    'Header' => $headers
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
            throw( new Exception('Exchange EWS GetItems Error >> ' . print_r($errors, 1)));

        return $buffer;
    }


    public function moveEmailItem($id, $folderId)
    {
        $request = new MoveItemType();

        $request->ToFolderId = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ToFolderId->FolderId = new FolderIdType();
        $request->ToFolderId->FolderId->Id = $folderId;

        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = new ItemIdType();
        $request->ItemIds->ItemId->Id = $id;


        // Generic execution sample code
        $response = $this->MoveItem($request);

        if( isset($response->ResponseMessages) && isset($response->ResponseMessages->MoveItemResponseMessage) && isset($response->ResponseMessages->MoveItemResponseMessage[0]) ){
            $node = $response->ResponseMessages->MoveItemResponseMessage[0];

            if( isset($node->ResponseClass) && $node->ResponseClass == ResponseClassType::SUCCESS ){
                if( isset($node->Items) && isset($node->Items->Message) && isset($node->Items->Message[0]) )
                    return $node->Items->Message[0]->ItemId;

                // Lost item id (know bug while moving between mailboxes)
                return (object)[
                    'Id' => ''
                ];
            }

            //print_r($response);
            throw(new Exception('Exchange EWS Move Item Error >> ' . $node->ResponseCode . ': '. $node->MessageText));
        }

        return false;
    }


    public function copyEmailItem($id, $folderId)
    {
        $request = new CopyItemType();

        $request->ToFolderId = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ToFolderId->FolderId = new FolderIdType();
        $request->ToFolderId->FolderId->Id = $folderId;

        $request->ItemIds = new NonEmptyArrayOfBaseItemIdsType();
        $request->ItemIds->ItemId = new ItemIdType();
        $request->ItemIds->ItemId->Id = $id;

        // Generic execution sample code
        $response = $this->CopyItem($request);

        if( isset($response->ResponseMessages) && isset($response->ResponseMessages->CopyItemResponseMessage) && isset($response->ResponseMessages->CopyItemResponseMessage[0]) ){
            $node = $response->ResponseMessages->CopyItemResponseMessage[0];

            if( isset($node->ResponseClass) && $node->ResponseClass == ResponseClassType::SUCCESS ){
                if( isset($node->Items) && isset($node->Items->Message) && isset($node->Items->Message[0]) )
                    return $node->Items->Message[0]->ItemId;

                // Lost item id (know bug while moving between mailboxes)
                return (object)[
                    'Id' => ''
                ];
            }

            throw(new Exception('Exchange EWS Move Item Error >> ' . $node->ResponseCode . ': '. $node->MessageText));
        }

        return false;
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


    public function listFolders($search = null){

        // Build the request.
        $request = new FindFolderType();
        $request->FolderShape = new FolderResponseShapeType();
        $request->FolderShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;
        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        //$request->Restriction = new RestrictionType();

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

        $response = $this->FindFolder($request);

        // Iterate over the results, printing any error messages or folder names and ids.
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

        return $allFoldersArr;
    }


    public function getCalendarEvents()
    {
        $request = new FindItemType();
        $request->Traversal = ItemQueryTraversalType::SHALLOW;
        $request->ItemShape = new ItemResponseShapeType();
        $request->ItemShape->BaseShape = DefaultShapeNamesType::ALL_PROPERTIES;

        $folder_id = new DistinguishedFolderIdType();
        $folder_id->Id = new DistinguishedFolderIdNameType();
        $folder_id->Id->_ = DistinguishedFolderIdNameType::CALENDAR;

        $request->ParentFolderIds = new NonEmptyArrayOfBaseFolderIdsType();
        $request->ParentFolderIds->DistinguishedFolderId[] = $folder_id;

        $request->CalendarView = new CalendarViewType();
        $request->CalendarView->StartDate = '2019-04-01T00:00:00Z';
        $request->CalendarView->EndDate = '2019-04-24T23:59:59Z';

        $result = $this->FindItem($request);

        $items = [];
        if(
            isset($result->ResponseMessages) &&
            isset($result->ResponseMessages->FindItemResponseMessage) &&
            isset($result->ResponseMessages->FindItemResponseMessage[0]) &&
            isset($result->ResponseMessages->FindItemResponseMessage[0]->RootFolder) &&
            isset($result->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items) &&
            isset($result->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items->CalendarItem)
        )
            $items = $result->ResponseMessages->FindItemResponseMessage[0]->RootFolder->Items->CalendarItem;

        $events = [];
        foreach ($items as $item) {
            $events[] = (object)[
                'Subject'   => $item->Subject,
                'ItemId'    => $item->ItemId->Id,
                'Starts'    => $item->Start,
                'End'       => $item->End,
                'Duration'  => $item->Duration,
                'Organizer' => isset($item->Organizer) && isset($item->Organizer->Mailbox) ? $item->Organizer->Mailbox->Name : ''
            ];
        }

        return $events;
    }


    /**
     * @param $invites array | object contact [name, email]
     */
    public function createCalendarEvent($create = [])
    {
        if( !isset($create['subject']) || !isset($create['message']) )
            return 'missing fields';

        if( !isset($create['start']) || get_class($create['start']) != 'DateTime' )
            return 'invalid start date';

        if( !isset($create['end']) || get_class($create['end']) != 'DateTime' )
            return 'invalid start date';

        // Replace this with your desired start/end times and guests.
        $start = $create['start'];
        $end = $create['end'];


        // Build the request,
        $request = new CreateItemType();
        $request->SendMeetingInvitations = CalendarItemCreateOrDeleteOperationType::SEND_TO_NONE;
        $request->Items = new NonEmptyArrayOfAllItemsType();

        // Build the event to be added.
        //$this->setTimezone('Eastern Standard Time');
        $event = new CalendarItemType();
        $event->Start = $start->format('c');
        $event->End = $end->format('c');


        $event->Subject = $create['subject'];

        // Set the event body.
        $event->Body = new BodyType();
        $event->Body->_ = $create['message'];
        $event->Body->BodyType = BodyTypeType::TEXT;

        // Iterate over the guests, adding each as an attendee to the request.
        if( count($create['invites']) >= 1){
            $request->SendMeetingInvitations = CalendarItemCreateOrDeleteOperationType::SEND_ONLY_TO_ALL;

            $event->RequiredAttendees = new NonEmptyArrayOfAttendeesType();

            foreach ($create['invites'] as $guest) {
                $attendee = new AttendeeType();
                $attendee->Mailbox = new EmailAddressType();
                $attendee->Mailbox->EmailAddress = $guest['email'];
                $attendee->Mailbox->Name = $guest['name'];
                $attendee->Mailbox->RoutingType = RoutingType::SMTP;
                $event->RequiredAttendees->Attendee[] = $attendee;
            }
        }

        // Add the event to the request. You could add multiple events to create more
        // than one in a single request.
        $request->Items->CalendarItem[] = $event;

        $response = $this->CreateItem($request);

        if(
            isset($response->ResponseMessages) &&
            isset($response->ResponseMessages->CreateItemResponseMessage) &&
            isset($response->ResponseMessages->CreateItemResponseMessage[0]) &&
            isset($response->ResponseMessages->CreateItemResponseMessage[0]->Items) &&
            isset($response->ResponseMessages->CreateItemResponseMessage[0]->Items->CalendarItem)
        ){
            $items = $response->ResponseMessages->CreateItemResponseMessage[0]->Items->CalendarItem;

            $events = [];
            foreach ($items as $item)
                $events[] = (object)[
                    'ItemId'    => $item->ItemId->Id,
                ];

            return $events;
        }

        return [];

    }


    public function subscribePushNotifications($callbackUri, $mailbox = null, $callbackFunction)
    {
        $request = new SubscribeType();

        $eventTypes = new NonEmptyArrayOfNotificationEventTypesType();
        $eventTypes->EventType = [
            'CreatedEvent',
            //'DeletedEvent',
            //'ModifiedEvent',
            'NewMailEvent',
            'MovedEvent',
            'CopiedEvent',
            //'FreeBusyChangedEvent'
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
                    'item_id'               => $subscription->SubscriptionId,
                    'expire_on'             => null,
                    'exchange_mailbox_id'   => $mailbox ? $mailbox->id : null,
                    'keep_alive'            => 120,
                    'rules'                 => [],
                    'callback'              => $callbackFunction 
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