<?php

namespace Webklex\PHPIMAP\Connection\Protocols;

use Exception;
use Throwable;
use Webklex\PHPIMAP\Exceptions\AuthFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionClosedException;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Exceptions\ConnectionTimedOutException;
use Webklex\PHPIMAP\Exceptions\ImapBadRequestException;
use Webklex\PHPIMAP\Exceptions\ImapServerErrorException;
use Webklex\PHPIMAP\Exceptions\MessageNotFoundException;
use Webklex\PHPIMAP\Exceptions\RuntimeException;
use Webklex\PHPIMAP\Header;
use Webklex\PHPIMAP\IMAP;
use Webklex\PHPIMAP\Support\Escape;

/**
 * @see https://www.rfc-editor.org/rfc/rfc2087.txt
 */
class ImapProtocol extends Protocol
{
    /**
     * The current request sequence.
     */
    protected int $sequence = 0;

    /**
     * Constructor.
     */
    public function __construct(bool $certValidation = true, ?string $encryption = null)
    {
        $this->certValidation = $certValidation;
        $this->encryption = $encryption;
    }

    /**
     * Handle the class destruction / tear down.
     */
    public function __destruct()
    {
        $this->logout();
    }

    /**
     * Open connection to IMAP server.
     *
     * @param  string  $host  hostname or IP address of IMAP server
     * @param  int|null  $port  of IMAP server, default is 143 and 993 for ssl
     */
    public function connect(string $host, ?int $port = null): bool
    {
        $transport = 'tcp';
        $encryption = '';

        if ($this->encryption) {
            $encryption = strtolower($this->encryption);

            if (in_array($encryption, ['ssl', 'tls'])) {
                $transport = $encryption;
                $port ??= 993;
            }
        }

        $port ??= 143;

        try {
            $response = new Response(0, $this->debug);

            $this->stream = $this->createStream(
                $transport,
                $host,
                $port,
                $this->connectionTimeout,
            );

            if (! $this->stream || ! $this->assumedNextLine($response, '* OK')) {
                throw new ConnectionFailedException('Connection refused');
            }

            $this->setStreamTimeout($this->connectionTimeout);

            if ($encryption == 'starttls') {
                $this->enableStartTls();
            }
        } catch (Exception $e) {
            throw new ConnectionFailedException('Connection failed', 0, $e);
        }

        return true;
    }

    /**
     * Enable TLS on the current connection.
     */
    protected function enableStartTls(): void
    {
        $response = $this->requestAndResponse('STARTTLS');

        $result = $response->successful() && stream_socket_enable_crypto($this->stream, true, $this->getCryptoMethod());

        if (! $result) {
            throw new ConnectionFailedException('Failed to enable TLS');
        }
    }

    /**
     * Get the next line from stream.
     */
    public function nextLine(Response $response): string
    {
        $line = fgets($this->stream);

        if ($line === false) {
            $meta = $this->meta();

            throw match (true) {
                $meta['timed_out'] ?? false => new ConnectionTimedOutException('Stream timed out, no response'),
                $meta['eof'] ?? false => new ConnectionClosedException('Server closed the connection (EOF)'),
                default => new RuntimeException('Unknown read error, no response: '.json_encode($meta)),
            };
        }

        $response->push($line);

        if ($this->debug) {
            echo '<< '.$line;
        }

        return $line;
    }

    /**
     * Get the next tagged line along with the containing tag.
     */
    protected function nextTaggedLine(Response $response, ?string &$tag): string
    {
        $line = $this->nextLine($response);

        if (str_contains($line, ' ')) {
            [$tag, $line] = explode(' ', $line, 2);
        }

        return $line ?? '';
    }

    /**
     * Get the next line and check if it starts with a given string.
     */
    protected function assumedNextLine(Response $response, string $start): bool
    {
        return str_starts_with($this->nextLine($response), $start);
    }

    /**
     * Get the next line and check if it contains a given string and split the tag.
     */
    protected function assumedNextTaggedLine(Response $response, string $start, ?string &$tag): bool
    {
        return str_contains($this->nextTaggedLine($response, $tag), $start);
    }

    /**
     * Split a given line in values. A value is literal of any form or a list.
     */
    protected function decodeLine(Response $response, string $line): array
    {
        $tokens = [];
        $stack = [];

        // Replace any trailing <NL> including spaces with a single space.
        $line = rtrim($line).' ';

        while (($pos = strpos($line, ' ')) !== false) {
            $token = substr($line, 0, $pos);

            if (! strlen($token)) {
                $line = substr($line, $pos + 1);

                continue;
            }

            while ($token[0] == '(') {
                $stack[] = $tokens;

                $tokens = [];

                $token = substr($token, 1);
            }

            if ($token[0] == '"') {
                if (preg_match('%^\(*\"((.|\\\|\")*?)\"( |$)%', $line, $matches)) {

                    $tokens[] = $matches[1];

                    $line = substr($line, strlen($matches[0]));

                    continue;
                }
            }

            if ($token[0] == '{') {
                $endPos = strpos($token, '}');
                $chars = substr($token, 1, $endPos - 1);

                if (is_numeric($chars)) {
                    $token = '';

                    while (strlen($token) < $chars) {
                        $token .= $this->nextLine($response);
                    }

                    $line = '';

                    if (strlen($token) > $chars) {
                        $line = substr($token, $chars);
                        $token = substr($token, 0, $chars);
                    } else {
                        $line .= $this->nextLine($response);
                    }

                    $tokens[] = $token;

                    $line = trim($line).' ';

                    continue;
                }
            }

            if ($stack && $token[strlen($token) - 1] == ')') {
                // Closing braces are not separated by spaces, so we need to count them.
                $braces = strlen($token);

                $token = rtrim($token, ')');

                // Only count braces if more than one.
                $braces -= strlen($token) + 1;

                // Only add if token had more than just closing braces.
                if (rtrim($token) != '') {
                    $tokens[] = rtrim($token);
                }

                $token = $tokens;

                $tokens = array_pop($stack);

                // Special handling if more than one closing brace.
                while ($braces-- > 0) {
                    $tokens[] = $token;
                    $token = $tokens;
                    $tokens = array_pop($stack);
                }
            }

            $tokens[] = $token;

            $line = substr($line, $pos + 1);
        }

        // Maybe the server forgot to send some closing braces.
        while ($stack) {
            $child = $tokens;

            $tokens = array_pop($stack);

            $tokens[] = $child;
        }

        return $tokens;
    }

    /**
     * Read abd decode a response "line".
     *
     * @param  array|string  $tokens  to decode
     * @param  string  $wantedTag  targeted tag
     * @param  bool  $dontParse  if true only the unparsed line is returned in $tokens
     */
    public function readLine(Response $response, array|string &$tokens = [], string $wantedTag = '*', bool $dontParse = false): bool
    {
        $line = $this->nextTaggedLine($response, $tag); // Get next tag

        if (! $dontParse) {
            $tokens = $this->decodeLine($response, $line);
        } else {
            $tokens = $line;
        }

        // If tag is wanted tag we might be at the end of a multiline response.
        return $tag == $wantedTag;
    }

    /**
     * Read all lines of response until given tag is found.
     *
     * @param  string  $tag  request tag
     * @param  bool  $dontParse  if true every line is returned unparsed instead of the decoded tokens
     */
    public function readResponse(Response $response, string $tag, bool $dontParse = false): array
    {
        $lines = [];

        $tokens = '';

        do {
            $readAll = $this->readLine($response, $tokens, $tag, $dontParse);

            $lines[] = $tokens;
        } while (! $readAll);

        $original = $tokens;

        if ($dontParse) {
            // First two chars are still needed for the response code.
            $tokens = [trim(substr($tokens, 0, 3))];
        }

        $original = is_array($original) ? $original : [$original];

        // Last line has response code.
        if ($tokens[0] == 'OK') {
            return $lines ?: [true];
        }

        if (in_array($tokens[0], ['NO', 'BAD', 'BYE'])) {
            throw ImapServerErrorException::fromResponseTokens($original);
        }

        throw ImapBadRequestException::fromResponseTokens($original);
    }

    /**
     * Send a new request.
     *
     * @param  array  $tokens  additional parameters to command, use escapeString() to prepare
     * @param  string|null  $tag  provide a tag otherwise an autogenerated is returned
     */
    public function sendRequest(string $command, array $tokens = [], ?string &$tag = null): Response
    {
        if (! $tag) {
            $this->sequence++;
            $tag = 'TAG'.$this->sequence;
        }

        $line = $tag.' '.$command;

        $response = new Response($this->sequence, $this->debug);

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $this->write($response, $line.' '.$token[0]);

                if (! $this->assumedNextLine($response, '+ ')) {
                    throw new RuntimeException('Failed to send literal string');
                }

                $line = $token[1];
            } else {
                $line .= ' '.$token;
            }
        }

        $this->write($response, $line);

        return $response;
    }

    /**
     * Write data to the current stream.
     */
    public function write(Response $response, string $data): void
    {
        $command = $data."\r\n";

        if ($this->debug) {
            echo '>> '.$command."\n";
        }

        $response->addCommand($command);

        if (fwrite($this->stream, $command) === false) {
            throw new RuntimeException('Failed to write - connection closed?');
        }
    }

    /**
     * Send a request and get response at once.
     *
     * @param  bool  $dontParse  if true unparsed lines are returned instead of tokens
     * @return Response response as in readResponse()
     */
    public function requestAndResponse(string $command, array $tokens = [], bool $dontParse = false): Response
    {
        $response = $this->sendRequest($command, $tokens, $tag);

        $response->setResult(
            $this->readResponse($response, $tag, $dontParse)
        );

        return $response;
    }

    /**
     * Login to a new session.
     */
    public function login(string $user, string $password): Response
    {
        try {
            $command = 'LOGIN';
            $params = $this->escapeString($user, $password);

            return $this->requestAndResponse($command, $params, true);
        } catch (RuntimeException $e) {
            throw new AuthFailedException('Failed to authenticate', 0, $e);
        }
    }

    /**
     * Authenticate your current IMAP session.
     */
    public function authenticate(string $user, string $token): Response
    {
        try {
            $authenticateParams = ['XOAUTH2', base64_encode("user=$user\1auth=Bearer $token\1\1")];

            $response = $this->sendRequest('AUTHENTICATE', $authenticateParams);

            while (true) {
                $tokens = '';

                if ($this->readLine($response, $tokens, '+', true)) {
                    // Try to log the challenge somewhere where it can be found.
                    error_log("got an extra server challenge: $tokens");

                    // Respond with an empty response.
                    $response->addResponse($this->sendRequest(''));
                } else {
                    if (preg_match('/^NO /i', $tokens) ||
                        preg_match('/^BAD /i', $tokens)) {
                        error_log("got failure response: $tokens");

                        return $response->addError("got failure response: $tokens");
                    } elseif (preg_match('/^OK /i', $tokens)) {
                        return $response->setResult(is_array($tokens) ? $tokens : [$tokens]);
                    }
                }
            }
        } catch (RuntimeException $e) {
            throw new AuthFailedException('Failed to authenticate', 0, $e);
        }
    }

    /**
     * Logout of imap server.
     */
    public function logout(): Response
    {
        if (! $this->stream) {
            $this->reset();

            return new Response(0, $this->debug);
        } elseif ($this->meta()['timed_out']) {
            $this->reset();

            return new Response(0, $this->debug);
        }

        $result = null;

        try {
            $result = $this->requestAndResponse('LOGOUT', [], true);

            fclose($this->stream);
        } catch (Throwable) {
            // Do nothing.
        }

        $this->reset();

        return $result ?? new Response(0, $this->debug);
    }

    /**
     * Reset the current stream and uid cache.
     */
    public function reset(): void
    {
        $this->stream = null;
        $this->uidCache = [];
    }

    /**
     * Get an array of available capabilities.
     */
    public function getCapabilities(): Response
    {
        $response = $this->requestAndResponse('CAPABILITY');

        if (! $response->getResponse()) {
            return $response;
        }

        return $response->setResult($response->getValidatedData()[0]);
    }

    /**
     * Examine and select have the same response.
     *
     * @param  string  $command  can be 'EXAMINE' or 'SELECT'
     * @param  string  $folder  target folder
     */
    public function examineOrSelect(string $command = 'EXAMINE', string $folder = 'INBOX'): Response
    {
        $response = $this->sendRequest($command, [$this->escapeString($folder)], $tag);

        $result = [];
        $tokens = [];

        while (! $this->readLine($response, $tokens, $tag)) {
            if ($tokens[0] == 'FLAGS') {
                array_shift($tokens);
                $result['flags'] = $tokens;

                continue;
            }
            switch ($tokens[1]) {
                case 'EXISTS':
                case 'RECENT':
                    $result[strtolower($tokens[1])] = (int) $tokens[0];
                    break;
                case '[UIDVALIDITY':
                    $result['uidvalidity'] = (int) $tokens[2];
                    break;
                case '[UIDNEXT':
                    $result['uidnext'] = (int) $tokens[2];
                    break;
                case '[UNSEEN':
                    $result['unseen'] = (int) $tokens[2];
                    break;
                case '[NONEXISTENT]':
                    throw new RuntimeException("Folder doesn't exist");
                default:
                    // ignore
                    break;
            }
        }

        $response->setResult($result);

        if ($tokens[0] != 'OK') {
            $response->addError('request failed');
        }

        return $response;
    }

    /**
     * Select the current folder.
     */
    public function selectFolder(string $folder = 'INBOX'): Response
    {
        $this->uidCache = [];

        return $this->examineOrSelect('SELECT', $folder);
    }

    /**
     * Examine the given folder.
     */
    public function examineFolder(string $folder = 'INBOX'): Response
    {
        return $this->examineOrSelect('EXAMINE', $folder);
    }

    /**
     * Get the status of a given folder.
     */
    public function folderStatus(string $folder = 'INBOX', array $arguments = ['MESSAGES', 'UNSEEN', 'RECENT', 'UIDNEXT', 'UIDVALIDITY']): Response
    {
        $response = $this->requestAndResponse('STATUS', [
            $this->escapeString($folder),
            $this->escapeList($arguments),
        ]);

        $data = $response->getValidatedData();

        if (! isset($data[0]) || ! isset($data[0][2])) {
            throw new RuntimeException('Folder status could not be fetched');
        }

        $result = [];

        $key = null;

        foreach ($data[0][2] as $value) {
            if ($key === null) {
                $key = $value;
            } else {
                $result[strtolower($key)] = (int) $value;
                $key = null;
            }
        }

        $response->setResult($result);

        return $response;
    }

    /**
     * Fetch one or more items of one or more messages.
     *
     * @param  array|string  $items  items to fetch [RFC822.HEADER, FLAGS, RFC822.TEXT, etc]
     * @param  array|int  $from  message for items or start message if $to !== null
     * @param  int|null  $to  if null only one message ($from) is fetched, else it's the
     *                        last message, INF means last message available
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     * @return Response if only one item of one message is fetched it's returned as string
     *                  if items of one message are fetched it's returned as (name => value)
     *                  if one item of messages are fetched it's returned as (msgno => value)
     *                  if items of messages are fetched it's returned as (msgno => (name => value))
     */
    public function fetch(array|string $items, array|int $from, mixed $to = null, int|string $uid = IMAP::ST_UID): Response
    {
        if (is_array($from) && count($from) > 1) {
            $set = implode(',', $from);
        } elseif (is_array($from) && count($from) === 1) {
            $set = $from[0].':'.$from[0];
        } elseif ($to === null) {
            $set = $from.':'.$from;
        } elseif ($to == INF) {
            $set = $from.':*';
        } else {
            $set = $from.':'.(int) $to;
        }

        $items = (array) $items;
        $itemList = $this->escapeList($items);

        $response = $this->sendRequest($this->buildUIDCommand('FETCH', $uid), [$set, $itemList], $tag);

        $result = [];
        $tokens = [];

        while (! $this->readLine($response, $tokens, $tag)) {
            // Ignore other responses.
            if ($tokens[1] != 'FETCH') {
                continue;
            }

            $uidKey = 0;
            $data = [];

            // Find array key of UID value; try the last elements, or search for it.
            if ($uid === IMAP::ST_UID) {
                $count = count($tokens[2]);
                if ($tokens[2][$count - 2] == 'UID') {
                    $uidKey = $count - 1;
                } elseif ($tokens[2][0] == 'UID') {
                    $uidKey = 1;
                } else {
                    $found = array_search('UID', $tokens[2]);
                    if ($found === false || $found === -1) {
                        continue;
                    }

                    $uidKey = $found + 1;
                }
            }

            // Ignore other messages.
            if ($to === null && ! is_array($from) && ($uid === IMAP::ST_UID ? $tokens[2][$uidKey] != $from : $tokens[0] != $from)) {
                continue;
            }

            // If we only want one item we return that one directly.
            if (count($items) == 1) {
                if ($tokens[2][0] == $items[0]) {
                    $data = $tokens[2][1];
                } elseif ($uid === IMAP::ST_UID && $tokens[2][2] == $items[0]) {
                    $data = $tokens[2][3];
                } else {
                    $expectedResponse = 0;

                    // Maybe the server send another field we didn't wanted.
                    $count = count($tokens[2]);

                    // We start with 2, because 0 was already checked.
                    for ($i = 2; $i < $count; $i += 2) {
                        if ($tokens[2][$i] != $items[0]) {
                            continue;
                        }

                        $data = $tokens[2][$i + 1];

                        $expectedResponse = 1;

                        break;
                    }

                    if (! $expectedResponse) {
                        continue;
                    }
                }
            } else {
                while (key($tokens[2]) !== null) {
                    $data[current($tokens[2])] = next($tokens[2]);

                    next($tokens[2]);
                }
            }

            // if we want only one message we can ignore everything else and just return
            if ($to === null && ! is_array($from) && ($uid === IMAP::ST_UID ? $tokens[2][$uidKey] == $from : $tokens[0] == $from)) {
                // we still need to read all lines
                if (! $this->readLine($response, $tokens, $tag)) {
                    return $response->setResult($data);
                }
            }

            if ($uid === IMAP::ST_UID) {
                $result[$tokens[2][$uidKey]] = $data;
            } else {
                $result[$tokens[0]] = $data;
            }
        }

        if ($to === null && ! is_array($from)) {
            throw new RuntimeException('The single id was not found in response');
        }

        return $response->setResult($result);
    }

    /**
     * Fetch message body (without headers).
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function content(int|array $uids, string $rfc = 'RFC822', int|string $uid = IMAP::ST_UID): Response
    {
        return $this->fetch(["$rfc.TEXT"], is_array($uids) ? $uids : [$uids], null, $uid);
    }

    /**
     * Fetch message headers.
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function headers(int|array $uids, string $rfc = 'RFC822', int|string $uid = IMAP::ST_UID): Response
    {
        return $this->fetch(["$rfc.HEADER"], is_array($uids) ? $uids : [$uids], null, $uid);
    }

    /**
     * Fetch message flags.
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function flags(int|array $uids, int|string $uid = IMAP::ST_UID): Response
    {
        return $this->fetch(['FLAGS'], is_array($uids) ? $uids : [$uids], null, $uid);
    }

    /**
     * Fetch message sizes.
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function sizes(int|array $uids, int|string $uid = IMAP::ST_UID): Response
    {
        return $this->fetch(['RFC822.SIZE'], is_array($uids) ? $uids : [$uids], null, $uid);
    }

    /**
     * Get uid for a given id.
     *
     * @param  int|null  $id  message number
     * @return Response message number for given message or all messages as array
     */
    public function getUid(?int $id = null): Response
    {
        if (! $this->enableUidCache || empty($this->uidCache) || count($this->uidCache) <= 0) {
            try {
                // Set cache for this folder.
                $this->setUidCache((array) $this->fetch('UID', 1, INF)->data());
            } catch (RuntimeException) {
            }
        }

        $uids = $this->uidCache;

        if ($id == null) {
            return Response::empty($this->debug)->setResult($uids);
        }

        foreach ($uids as $k => $v) {
            if ($k == $id) {
                return Response::empty($this->debug)->setResult($v);
            }
        }

        // Clear uid cache and run method again.
        if ($this->enableUidCache && $this->uidCache) {
            $this->setUidCache(null);

            return $this->getUid($id);
        }

        throw new MessageNotFoundException('Unique id not found');
    }

    /**
     * Get a message number for a uid.
     *
     * @param  string  $id  uid
     * @return Response message number
     */
    public function getMessageNumber(string $id): Response
    {
        foreach ($this->getUid()->data() as $k => $v) {
            if ($v == $id) {
                return Response::empty($this->debug)->setResult((int) $k);
            }
        }

        throw new MessageNotFoundException('Message number not found: '.$id);
    }

    /**
     * Get a list of available folders.
     *
     * @param  string  $reference  mailbox reference for list
     * @param  string  $folder  mailbox name match with wildcards
     * @return Response folders that matched $folder as array(name => array('delimiter' => .., 'flags' => ..))
     */
    public function folders(string $reference = '', string $folder = '*'): Response
    {
        $response = $this->requestAndResponse('LIST', $this->escapeString($reference, $folder))->setCanBeEmpty(true);

        $list = $response->data();

        $result = [];

        if ($list[0] !== true) {
            foreach ($list as $item) {
                if (count($item) != 4 || $item[0] != 'LIST') {
                    continue;
                }

                $item[3] = str_replace('\\\\', '\\', str_replace('\\"', '"', $item[3]));

                $result[$item[3]] = ['delimiter' => $item[2], 'flags' => $item[1]];
            }
        }

        return $response->setResult($result);
    }

    /**
     * Manage flags.
     *
     * @param  array|string  $flags  flags to set, add or remove - see $mode
     * @param  int  $from  message for items or start message if $to !== null
     * @param  int|null  $to  if null only one message ($from) is fetched, else it's the
     *                        last message, INF means last message available
     * @param  string|null  $mode  '+' to add flags, '-' to remove flags, everything else sets the flags as given
     * @param  bool  $silent  if false the return values are the new flags for the wanted messages
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     * @param  string|null  $item  command used to store a flag
     * @return Response new flags if $silent is false, else true or false depending on success
     */
    public function store(
        array|string $flags,
        int $from,
        ?int $to = null,
        ?string $mode = null,
        bool $silent = true,
        int|string $uid = IMAP::ST_UID,
        ?string $item = null
    ): Response {
        $flags = $this->escapeList(
            is_array($flags) ? $flags : [$flags]
        );

        $set = $this->buildSet($from, $to);

        $command = $this->buildUIDCommand('STORE', $uid);

        $item = ($mode == '-' ? '-' : '+').($item === null ? 'FLAGS' : $item).($silent ? '.SILENT' : '');

        $response = $this->requestAndResponse($command, [$set, $item, $flags], $silent);

        if ($silent) {
            return $response;
        }

        $result = [];

        foreach ($response as $token) {
            if ($token[1] != 'FETCH' || $token[2][0] != 'FLAGS') {
                continue;
            }

            $result[$token[0]] = $token[2][1];
        }

        return $response->setResult($result);
    }

    /**
     * Append a new message to given folder.
     *
     * @param  string  $folder  name of target folder
     * @param  string  $message  full message content
     * @param  array|null  $flags  flags for new message
     * @param  string|null  $date  date for new message
     */
    public function appendMessage(string $folder, string $message, ?array $flags = null, ?string $date = null): Response
    {
        $tokens = [];

        $tokens[] = $this->escapeString($folder);

        if ($flags !== null) {
            $tokens[] = $this->escapeList($flags);
        }

        if ($date !== null) {
            $tokens[] = $this->escapeString($date);
        }

        $tokens[] = $this->escapeString($message);

        return $this->requestAndResponse('APPEND', $tokens, true);
    }

    /**
     * Copy a message set from current folder to another folder.
     *
     * @param  string  $folder  destination folder
     * @param  int|null  $to  if null only one message ($from) is fetched, else it's the
     *                        last message, INF means last message available
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function copyMessage(string $folder, $from, ?int $to = null, int|string $uid = IMAP::ST_UID): Response
    {
        $set = $this->buildSet($from, $to);

        $command = $this->buildUIDCommand('COPY', $uid);

        return $this->requestAndResponse($command, [$set, $this->escapeString($folder)], true);
    }

    /**
     * Copy multiple messages to the target folder.
     *
     * @param  array  $messages  List of message identifiers
     * @param  string  $folder  Destination folder
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     * @return Response Tokens if operation successful, false if an error occurred
     */
    public function copyManyMessages(array $messages, string $folder, int|string $uid = IMAP::ST_UID): Response
    {
        $command = $this->buildUIDCommand('COPY', $uid);

        $set = implode(',', $messages);

        $tokens = [$set, $this->escapeString($folder)];

        return $this->requestAndResponse($command, $tokens, true);
    }

    /**
     * Move a message set from current folder to another folder.
     *
     * @param  string  $folder  destination folder
     * @param  int|null  $to  if null only one message ($from) is fetched, else it's the
     *                        last message, INF means last message available
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function moveMessage(string $folder, $from, ?int $to = null, int|string $uid = IMAP::ST_UID): Response
    {
        $set = $this->buildSet($from, $to);

        $command = $this->buildUIDCommand('MOVE', $uid);

        return $this->requestAndResponse($command, [$set, $this->escapeString($folder)], true);
    }

    /**
     * Move multiple messages to the target folder.
     *
     * @param  array  $messages  List of message identifiers
     * @param  string  $folder  Destination folder
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function moveManyMessages(array $messages, string $folder, int|string $uid = IMAP::ST_UID): Response
    {
        $command = $this->buildUIDCommand('MOVE', $uid);

        $set = implode(',', $messages);

        $tokens = [$set, $this->escapeString($folder)];

        return $this->requestAndResponse($command, $tokens, true);
    }

    /**
     * Exchange identification information.
     *
     * @see https://datatracker.ietf.org/doc/html/rfc2971.
     */
    public function id(?array $ids = null): Response
    {
        $token = 'NIL';

        if (is_array($ids) && ! empty($ids)) {
            $token = '(';

            foreach ($ids as $id) {
                $token .= '"'.$id.'" ';
            }

            $token = rtrim($token).')';
        }

        return $this->requestAndResponse('ID', [$token], true);
    }

    /**
     * Create a new folder (and parent folders if needed).
     *
     * @param  string  $folder  folder name
     */
    public function createFolder(string $folder): Response
    {
        return $this->requestAndResponse('CREATE', [$this->escapeString($folder)], true);
    }

    /**
     * Rename an existing folder.
     *
     * @param  string  $old  old name
     * @param  string  $new  new name
     */
    public function renameFolder(string $old, string $new): Response
    {
        return $this->requestAndResponse('RENAME', $this->escapeString($old, $new), true);
    }

    /**
     * Delete a folder.
     *
     * @param  string  $folder  folder name
     */
    public function deleteFolder(string $folder): Response
    {
        return $this->requestAndResponse('DELETE', [$this->escapeString($folder)], true);
    }

    /**
     * Subscribe to a folder.
     *
     * @param  string  $folder  folder name
     */
    public function subscribeFolder(string $folder): Response
    {
        return $this->requestAndResponse('SUBSCRIBE', [$this->escapeString($folder)], true);
    }

    /**
     * Unsubscribe from a folder.
     *
     * @param  string  $folder  folder name
     */
    public function unsubscribeFolder(string $folder): Response
    {
        return $this->requestAndResponse('UNSUBSCRIBE', [$this->escapeString($folder)], true);
    }

    /**
     * Apply session saved changes to the server.
     */
    public function expunge(): Response
    {
        $this->uidCache = [];

        return $this->requestAndResponse('EXPUNGE');
    }

    /**
     * Send noop command.
     */
    public function noop(): Response
    {
        return $this->requestAndResponse('NOOP');
    }

    /**
     * Retrieve the quota level settings, and usage statics per mailbox.
     *
     * @Doc https://www.rfc-editor.org/rfc/rfc2087.txt
     */
    public function getQuota($username): Response
    {
        return $this->requestAndResponse('GETQUOTA', ['"#user/'.$username.'"']);
    }

    /**
     * Retrieve the quota settings per user.
     *
     * @Doc https://www.rfc-editor.org/rfc/rfc2087.txt
     */
    public function getQuotaRoot(string $quotaRoot = 'INBOX'): Response
    {
        return $this->requestAndResponse('GETQUOTAROOT', [$quotaRoot]);
    }

    /**
     * Send idle command.
     */
    public function idle(): void
    {
        $response = $this->sendRequest('IDLE');

        while (true) {
            $line = $this->nextLine($response);

            if (str_starts_with($line, '+ ')) {
                return;
            }

            if (preg_match('/^\* OK /i', $line) || preg_match('/^TAG\d+ OK /i', $line)) {
                continue;
            }

            throw new RuntimeException('Idle failed - unexpected response: '.trim($line));
        }
    }

    /**
     * Send done command.
     */
    public function done(): bool
    {
        $response = new Response($this->sequence, $this->debug);

        $this->write($response, 'DONE');

        if (! $this->assumedNextTaggedLine($response, 'OK', $tags)) {
            throw new RuntimeException('Done failed');
        }

        return true;
    }

    /**
     * Search for matching messages.
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     * @return Response message ids
     */
    public function search(array $params, int|string $uid = IMAP::ST_UID): Response
    {
        $command = $this->buildUIDCommand('SEARCH', $uid);

        $response = $this->requestAndResponse($command, $params)->setCanBeEmpty(true);

        foreach ($response->data() as $ids) {
            if ($ids[0] === 'SEARCH') {
                array_shift($ids);

                return $response->setResult($ids);
            }
        }

        return $response;
    }

    /**
     * Get a message overview.
     *
     * @param  int|string  $uid  set to IMAP::ST_UID or any string representing the UID - set to IMAP::ST_MSGN to use
     *                           message numbers instead.
     */
    public function overview(string $sequence, int|string $uid = IMAP::ST_UID): Response
    {
        $result = [];

        [$from, $to] = explode(':', $sequence);

        $response = $this->getUid();

        $ids = [];

        foreach ($response->data() as $msgn => $v) {
            $id = $uid === IMAP::ST_UID ? $v : $msgn;

            if (($to >= $id && $from <= $id) || ($to === '*' && $from <= $id)) {
                $ids[] = $id;
            }
        }

        if (! empty($ids)) {
            $headers = $this->headers($ids, 'RFC822', $uid);

            $response->addResponse($headers);

            foreach ($headers->data() as $id => $rawHeader) {
                $result[$id] = (new Header($rawHeader))->getAttributes();
            }
        }

        return $response->setResult($result)->setCanBeEmpty(true);
    }

    /**
     * Enable the debug mode.
     */
    public function enableDebug(): void
    {
        $this->debug = true;
    }

    /**
     * Disable the debug mode.
     */
    public function disableDebug(): void
    {
        $this->debug = false;
    }

    /**
     * Build a valid UID number set.
     *
     * @param  null  $to
     */
    public function buildSet($from, $to = null): int|string
    {
        $set = (int) $from;

        if ($to !== null) {
            $set .= ':'.($to == INF ? '*' : (int) $to);
        }

        return $set;
    }

    /**
     * Escape one or more literals i.e. for sendRequest.
     */
    protected function escapeString(array|string ...$string): array|string
    {
        return Escape::string($string);
    }

    /**
     * Escape a list with literals or lists.
     */
    protected function escapeList(array $list): string
    {
        return Escape::list($list);
    }
}
