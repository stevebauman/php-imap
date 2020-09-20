<?php
/*
* File: ImapProtocol.php
* Category: Protocol
* Author: M.Goldenbaum
* Created: 16.09.20 18:27
* Updated: -
*
* Description:
*  -
*/

namespace Webklex\PHPIMAP\Connection\Protocols;

use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;

/**
 * Interface ProtocolInterface
 *
 * @package Webklex\PHPIMAP\Connection\Protocols
 */
interface ProtocolInterface {

    /**
     * Protocol constructor.
     * @param bool $cert_validation set to false to skip SSL certificate validation
     */
    public function __construct($cert_validation = true);

    /**
     * Public destructor
     */
    public function __destruct();

    /**
     * Open a new connection / session
     * @param string $host hostname or IP address of IMAP server
     * @param int|null $port of service server
     * @param string|bool $encryption use 'SSL', 'TLS' or false
     *
     * @throws \ErrorException
     * @throws ConnectionFailedException
     * @throws RuntimeException
     */
    public function connect($host, $port = null, $encryption = false);

    /**
     * Login to a new session.
     *
     * @param string $user username
     * @param string $password password
     * @return bool success
     * @throws AuthFailedException
     */
    public function login($user, $password);

    /**
     * Logout of the current server session
     *
     * @return bool success
     */
    public function logout();

    /**
     * Get an array of available capabilities
     *
     * @return array list of capabilities
     * @throws RuntimeException
     */
    public function getCapabilities();

    /**
     * Change the current folder
     *
     * @param string $folder change to this folder
     * @return bool|array see examineOrselect()
     * @throws RuntimeException
     */
    public function selectFolder($folder = 'INBOX');

    /**
     * Examine a given folder
     *
     * @param string $folder
     * @return bool|array
     * @throws RuntimeException
     */
    public function examineFolder($folder = 'INBOX');

    /**
     * Fetch one or more items of one or more messages
     *
     * @param string|array $items items to fetch from message(s) as string (if only one item)
     *                             or array of strings
     * @param int|array $from message for items or start message if $to !== null
     * @param int|null $to if null only one message ($from) is fetched, else it's the
     *                             last message, INF means last message available
     * @param bool $uid set to true if passing a unique id
     * @return string|array if only one item of one message is fetched it's returned as string
     *                      if items of one message are fetched it's returned as (name => value)
     *                      if one items of messages are fetched it's returned as (msgno => value)
     *                      if items of messages are fetched it's returned as (msgno => (name => value))
     * @throws RuntimeException
     */
    public function fetch($items, $from, $to = null, $uid = false);

    /**
     * Fetch message headers
     * @param array|int $uids
     * @param string $rfc
     *
     * @return array
     * @throws RuntimeException
     */
    public function content($uids, $rfc = "RFC822");

    /**
     * Fetch message headers
     * @param array|int $uids
     * @param string $rfc
     *
     * @return array
     * @throws RuntimeException
     */
    public function headers($uids, $rfc = "RFC822");

    /**
     * Fetch message flags
     * @param array|int $uids
     *
     * @return array
     * @throws RuntimeException
     */
    public function flags($uids);

    /**
     * Get uid for a given id
     * @param int|null $id message number
     *
     * @return array|string message number for given message or all messages as array
     * @throws RuntimeException
     */
    public function getUid($id = null);

    /**
     * Get a message number for a uid
     * @param string $id uid
     *
     * @return int message number
     * @throws RuntimeException
     */
    public function getMessageNumber($id);

    /**
     * Get a list of available folders
     *
     * @param string $reference mailbox reference for list
     * @param string $mailbox mailbox name match with wildcards
     * @return array mailboxes that matched $mailbox as array(globalName => array('delim' => .., 'flags' => ..))
     * @throws RuntimeException
     */
    public function folders($reference = '', $mailbox = '*');

    /**
     * Set message flags
     *
     * @param array $flags flags to set, add or remove
     * @param int $from message for items or start message if $to !== null
     * @param int|null $to if null only one message ($from) is fetched, else it's the
     *                             last message, INF means last message available
     * @param string|null $mode '+' to add flags, '-' to remove flags, everything else sets the flags as given
     * @param bool $silent if false the return values are the new flags for the wanted messages
     * @return bool|array new flags if $silent is false, else true or false depending on success
     * @throws RuntimeException
     */
    public function store(array $flags, $from, $to = null, $mode = null, $silent = true);

    /**
     * Append a new message to given folder
     *
     * @param string $folder name of target folder
     * @param string $message full message content
     * @param array $flags flags for new message
     * @param string $date date for new message
     * @return bool success
     * @throws RuntimeException
     */
    public function appendMessage($folder, $message, $flags = null, $date = null);

    /**
     * Copy message set from current folder to other folder
     *
     * @param string $folder destination folder
     * @param $from
     * @param int|null $to if null only one message ($from) is fetched, else it's the
     *                         last message, INF means last message available
     * @return bool success
     * @throws RuntimeException
     */
    public function copyMessage($folder, $from, $to = null);

    /**
     * Create a new folder
     *
     * @param string $folder folder name
     * @return bool success
     * @throws RuntimeException
     */
    public function createFolder($folder);

    /**
     * Rename an existing folder
     *
     * @param string $old old name
     * @param string $new new name
     * @return bool success
     * @throws RuntimeException
     */
    public function renameFolder($old, $new);

    /**
     * Delete a folder
     *
     * @param string $folder folder name
     * @return bool success
     * @throws RuntimeException
     */
    public function deleteFolder($folder);

    /**
     * Subscribe to a folder
     *
     * @param string $folder folder name
     * @return bool success
     * @throws RuntimeException
     */
    public function subscribeFolder($folder);

    /**
     * Apply session saved changes to the server
     *
     * @return bool success
     * @throws RuntimeException
     */
    public function expunge();

    /**
     * Send noop command
     *
     * @return bool success
     * @throws RuntimeException
     */
    public function noop();

    /**
     * Do a search request
     *
     * @param array $params
     * @return array message ids
     * @throws RuntimeException
     */
    public function search(array $params);

    /**
     * Enable the debug mode
     */
    public function enableDebug();

    /**
     * Disable the debug mode
     */
    public function disableDebug();
}